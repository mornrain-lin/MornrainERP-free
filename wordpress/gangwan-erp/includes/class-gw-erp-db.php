<?php
/**
 * 港湾ERP —— 数据访问层（CRUD + 库存唯一入口 + 利润核算 + 财务）
 *
 * 约定：
 * 1) 所有 SQL 走 $wpdb->prepare；
 * 2) 库存加减只能走 self::move_stock()，禁止直接改 inventory；
 * 3) 利润 = 营收 − 成本 − 平台费，统一在 self::save_order() 里计算落库；
 * 4) 金额一律 DECIMAL(12,2)，人民币口径。
 *
 * @package GangwanERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GW_ERP_DB {

	/* ================= 商品 ================= */

	public static function get_products( $search = '', $limit = 200 ) {
		global $wpdb;
		$t = gw_erp_tbl( 'products' );

		if ( $search ) {
			$like = '%' . $wpdb->esc_like( $search ) . '%';
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $t WHERE sku LIKE %s OR name LIKE %s OR category LIKE %s ORDER BY id DESC LIMIT %d",
				$like, $like, $like, (int) $limit
			) );
		}
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t ORDER BY id DESC LIMIT %d", (int) $limit ) );
	}

	public static function get_product( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gw_erp_tbl( 'products' ) . ' WHERE id = %d', (int) $id ) );
	}

	public static function save_product( $data ) {
		global $wpdb;
		$t   = gw_erp_tbl( 'products' );
		$row = array(
			'sku'          => sanitize_text_field( $data['sku'] ),
			'name'         => sanitize_text_field( $data['name'] ),
			'category'     => sanitize_text_field( isset( $data['category'] ) ? $data['category'] : '' ),
			'sale_price'   => (float) $data['sale_price'],
			'cost_price'   => (float) $data['cost_price'],
			'safety_stock' => isset( $data['safety_stock'] ) ? (int) $data['safety_stock'] : 0,
			'unit'         => sanitize_text_field( isset( $data['unit'] ) ? $data['unit'] : '件' ),
			'status'       => sanitize_text_field( isset( $data['status'] ) ? $data['status'] : 'active' ),
			'note'         => sanitize_textarea_field( isset( $data['note'] ) ? $data['note'] : '' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $data['id'] ) );
			return (int) $data['id'];
		}
		$row['created_at'] = current_time( 'mysql' );
		$wpdb->insert( $t, $row );
		return $wpdb->insert_id;
	}

	public static function delete_product( $id ) {
		global $wpdb;
		$wpdb->delete( gw_erp_tbl( 'products' ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/* ================= 仓库 & 库存 ================= */

	public static function get_warehouses() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . gw_erp_tbl( 'warehouses' ) . ' ORDER BY is_default DESC, id ASC' );
	}

	public static function default_warehouse_id() {
		global $wpdb;
		$id = (int) $wpdb->get_var( 'SELECT id FROM ' . gw_erp_tbl( 'warehouses' ) . ' ORDER BY is_default DESC, id ASC LIMIT 1' );
		return $id;
	}

	public static function get_inventory_rows() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT i.product_id, i.warehouse_id, i.qty, p.sku, p.name, p.sale_price, p.cost_price, p.safety_stock, w.name AS wh_name
			 FROM ' . gw_erp_tbl( 'inventory' ) . ' i
			 JOIN ' . gw_erp_tbl( 'products' ) . ' p ON p.id = i.product_id
			 JOIN ' . gw_erp_tbl( 'warehouses' ) . ' w ON w.id = i.warehouse_id
			 ORDER BY p.id ASC, w.id ASC'
		);
	}

	public static function stock_total( $product_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(qty),0) FROM ' . gw_erp_tbl( 'inventory' ) . ' WHERE product_id = %d',
			(int) $product_id
		) );
	}

	public static function low_stock( $threshold = null ) {
		global $wpdb;
		if ( null === $threshold ) {
			$threshold = (int) get_option( 'gw_erp_low_stock_threshold', 50 );
		}
		$inv = gw_erp_tbl( 'inventory' );
		$pro = gw_erp_tbl( 'products' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT p.id, p.sku, p.name, p.safety_stock, COALESCE(s.total,0) AS total
			 FROM $pro p
			 LEFT JOIN (SELECT product_id, SUM(qty) AS total FROM $inv GROUP BY product_id) s ON s.product_id = p.id
			 WHERE p.status = 'active'
			   AND COALESCE(s.total,0) <= COALESCE(NULLIF(p.safety_stock,0), %d)
			 ORDER BY total ASC",
			(int) $threshold
		) );
	}

	/**
	 * 库存变动唯一入口
	 *
	 * @param int    $product_id   商品 ID
	 * @param int    $warehouse_id 仓库 ID
	 * @param int    $qty          正数入库 / 负数出库
	 * @param string $type         流水类型（opening/purchase_in/sale_out/adjust_in/adjust_out/transfer/return_in/…）
	 * @param string $related_type 关联业务（order/purchase/adjust/transfer）
	 * @param int    $related_id   关联业务 ID
	 * @param string $note         备注
	 */
	public static function move_stock( $product_id, $warehouse_id, $qty, $type = 'adjust_in', $related_type = '', $related_id = 0, $note = '' ) {
		global $wpdb;

		$product_id   = (int) $product_id;
		$warehouse_id = (int) $warehouse_id;
		$qty          = (int) $qty;

		if ( ! $product_id || ! $warehouse_id || 0 === $qty ) {
			return new WP_Error( 'gw_erp_bad_stock_args', '库存变动参数不完整。' );
		}

		$inv = gw_erp_tbl( 'inventory' );
		$wpdb->query( 'START TRANSACTION' );

		$exists = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM $inv WHERE product_id = %d AND warehouse_id = %d",
			$product_id, $warehouse_id
		) );

		if ( $exists ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE $inv SET qty = qty + %d, updated_at = %s WHERE product_id = %d AND warehouse_id = %d",
				$qty, current_time( 'mysql' ), $product_id, $warehouse_id
			) );
		} else {
			$wpdb->insert( $inv, array(
				'product_id'   => $product_id,
				'warehouse_id' => $warehouse_id,
				'qty'          => $qty,
			), array( '%d', '%d', '%d' ) );
		}

		$wpdb->insert( gw_erp_tbl( 'stock_movements' ), array(
			'product_id'   => $product_id,
			'warehouse_id' => $warehouse_id,
			'type'         => sanitize_key( $type ),
			'qty'          => $qty,
			'related_type' => sanitize_key( $related_type ),
			'related_id'   => (int) $related_id,
			'note'         => sanitize_text_field( $note ),
			'created_by'   => get_current_user_id(),
		) );

		$wpdb->query( 'COMMIT' );

		return true;
	}

	/** 直接设定某仓库存（盘点用，内部仍写流水） */
	public static function set_inventory( $product_id, $warehouse_id, $qty ) {
		$current = self::warehouse_qty( $product_id, $warehouse_id );
		$delta   = (int) $qty - $current;
		if ( 0 === $delta ) {
			return true;
		}
		return self::move_stock(
			$product_id, $warehouse_id, $delta,
			$delta > 0 ? 'stocktake_in' : 'stocktake_out',
			'stocktake', 0, '库存盘点调整'
		);
	}

	public static function warehouse_qty( $product_id, $warehouse_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(qty,0) FROM ' . gw_erp_tbl( 'inventory' ) . ' WHERE product_id = %d AND warehouse_id = %d',
			(int) $product_id, (int) $warehouse_id
		) );
	}

	public static function get_movements( $limit = 100, $args = array() ) {
		global $wpdb;
		$sql = 'SELECT m.*, p.sku, p.name, w.name AS wh_name
			FROM ' . gw_erp_tbl( 'stock_movements' ) . ' m
			JOIN ' . gw_erp_tbl( 'products' ) . ' p ON p.id = m.product_id
			LEFT JOIN ' . gw_erp_tbl( 'warehouses' ) . ' w ON w.id = m.warehouse_id';

		$where = array();
		$params = array();
		if ( ! empty( $args['product_id'] ) ) {
			$where[]  = 'm.product_id = %d';
			$params[] = (int) $args['product_id'];
		}
		if ( ! empty( $args['type'] ) ) {
			$where[]  = 'm.type = %s';
			$params[] = sanitize_key( $args['type'] );
		}
		if ( $where ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}
		$sql .= ' ORDER BY m.id DESC LIMIT %d';
		$params[] = (int) $limit;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function movement_types() {
		return array(
			'opening'       => '期初库存',
			'purchase_in'   => '采购入库',
			'sale_out'      => '销售出库',
			'return_in'     => '退货入库',
			'stocktake_in'  => '盘盈',
			'stocktake_out' => '盘亏',
			'transfer_in'   => '调拨入库',
			'transfer_out'  => '调拨出库',
			'adjust_in'     => '其他入库',
			'adjust_out'    => '其他出库',
		);
	}

	/* ================= 往来单位 ================= */

	public static function get_partners( $type = '' ) {
		global $wpdb;
		$t = gw_erp_tbl( 'partners' );
		if ( $type ) {
			return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE type = %s ORDER BY id DESC", sanitize_key( $type ) ) );
		}
		return $wpdb->get_results( "SELECT * FROM $t ORDER BY type ASC, id DESC" );
	}

	public static function save_partner( $data ) {
		global $wpdb;
		$t = gw_erp_tbl( 'partners' );
		$row = array(
			'type'    => in_array( $data['type'], array( 'customer', 'supplier' ), true ) ? $data['type'] : 'customer',
			'name'    => sanitize_text_field( $data['name'] ),
			'contact' => sanitize_text_field( isset( $data['contact'] ) ? $data['contact'] : '' ),
			'phone'   => sanitize_text_field( isset( $data['phone'] ) ? $data['phone'] : '' ),
			'email'   => sanitize_email( isset( $data['email'] ) ? $data['email'] : '' ),
			'address' => sanitize_text_field( isset( $data['address'] ) ? $data['address'] : '' ),
			'note'    => sanitize_textarea_field( isset( $data['note'] ) ? $data['note'] : '' ),
		);
		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $data['id'] ) );
			return (int) $data['id'];
		}
		$wpdb->insert( $t, $row );
		return $wpdb->insert_id;
	}

	public static function delete_partner( $id ) {
		global $wpdb;
		$wpdb->delete( gw_erp_tbl( 'partners' ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/* ================= 销售订单 ================= */

	public static function get_orders( $limit = 50, $args = array() ) {
		global $wpdb;
		$t = gw_erp_tbl( 'orders' );

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(order_no LIKE %s OR customer LIKE %s OR channel LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		if ( ! empty( $args['month'] ) ) {
			$where[]  = "DATE_FORMAT(order_date, '%%Y-%%m') = %s";
			$params[] = sanitize_text_field( $args['month'] );
		}

		$sql = "SELECT * FROM $t WHERE " . implode( ' AND ', $where ) . ' ORDER BY order_date DESC, id DESC LIMIT %d';
		$params[] = (int) $limit;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function get_order( $id ) {
		global $wpdb;
		$order = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gw_erp_tbl( 'orders' ) . ' WHERE id = %d', (int) $id ) );
		if ( $order ) {
			$order->items = $wpdb->get_results( $wpdb->prepare(
				'SELECT oi.*, p.sku, p.name FROM ' . gw_erp_tbl( 'order_items' ) . ' oi
				 JOIN ' . gw_erp_tbl( 'products' ) . ' p ON p.id = oi.product_id
				 WHERE oi.order_id = %d',
				(int) $id
			) );
		}
		return $order;
	}

	/**
	 * 保存订单：自动核算利润；状态进入「已发货/已完成」时出库一次（幂等）
	 */
	public static function save_order( $data ) {
		global $wpdb;

		$revenue = (float) $data['revenue'];
		$cost    = (float) ( isset( $data['cost'] ) ? $data['cost'] : 0 );
		$fee     = (float) ( isset( $data['fee'] ) ? $data['fee'] : 0 );
		$profit  = round( $revenue - $cost - $fee, 2 );

		$t   = gw_erp_tbl( 'orders' );
		$row = array(
			'order_no'    => sanitize_text_field( $data['order_no'] ),
			'customer'    => sanitize_text_field( isset( $data['customer'] ) ? $data['customer'] : '' ),
			'partner_id'  => (int) ( isset( $data['partner_id'] ) ? $data['partner_id'] : 0 ),
			'channel'     => sanitize_text_field( isset( $data['channel'] ) ? $data['channel'] : '' ),
			'status'      => sanitize_key( isset( $data['status'] ) ? $data['status'] : 'pending' ),
			'revenue'     => $revenue,
			'cost'        => $cost,
			'fee'         => $fee,
			'profit'      => $profit,
			'paid_amount' => (float) ( isset( $data['paid_amount'] ) ? $data['paid_amount'] : 0 ),
			'order_date'  => sanitize_text_field( isset( $data['order_date'] ) ? $data['order_date'] : current_time( 'Y-m-d' ) ),
			'note'        => sanitize_textarea_field( isset( $data['note'] ) ? $data['note'] : '' ),
		);

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $data['id'] ) );
			$oid = (int) $data['id'];
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $t, $row );
			$oid = $wpdb->insert_id;
		}

		// 明细
		$wpdb->delete( gw_erp_tbl( 'order_items' ), array( 'order_id' => $oid ), array( '%d' ) );
		if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $it ) {
				if ( empty( $it['product_id'] ) ) {
					continue;
				}
				$pid = (int) $it['product_id'];
				$product = self::get_product( $pid );
				$wpdb->insert( gw_erp_tbl( 'order_items' ), array(
					'order_id'   => $oid,
					'product_id' => $pid,
					'qty'        => (int) $it['qty'],
					'price'      => (float) $it['price'],
					'cost'       => $product ? (float) $product->cost_price : 0,
				) );
			}
		}

		// 出库（仅一次）
		$need_out = in_array( $row['status'], array( 'shipped', 'done' ), true );
		if ( $need_out ) {
			$already = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM " . gw_erp_tbl( 'stock_movements' ) . " WHERE related_type = 'order' AND related_id = %d AND type = 'sale_out'",
				$oid
			) );
			if ( 0 === $already ) {
				$wh = self::default_warehouse_id();
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, qty FROM ' . gw_erp_tbl( 'order_items' ) . ' WHERE order_id = %d', $oid ) ) as $it ) {
					self::move_stock( $it->product_id, $wh, -1 * (int) $it->qty, 'sale_out', 'order', $oid, '销售出库 ' . $row['order_no'] );
				}
			}
		}

		return $oid;
	}

	public static function delete_order( $id ) {
		global $wpdb;
		$wpdb->delete( gw_erp_tbl( 'order_items' ), array( 'order_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( gw_erp_tbl( 'orders' ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/* ================= 采购 ================= */

	public static function get_purchases( $limit = 50, $args = array() ) {
		global $wpdb;
		$t = gw_erp_tbl( 'purchases' );

		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[]  = '(po_no LIKE %s OR supplier LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		$sql = 'SELECT * FROM ' . gw_erp_tbl( 'purchases' ) . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY purchase_date DESC, id DESC LIMIT %d';
		$params[] = (int) $limit;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function get_purchase( $id ) {
		global $wpdb;
		$po = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gw_erp_tbl( 'purchases' ) . ' WHERE id = %d', (int) $id ) );
		if ( $po ) {
			$po->items = $wpdb->get_results( $wpdb->prepare(
				'SELECT pi.*, p.sku, p.name FROM ' . gw_erp_tbl( 'purchase_items' ) . ' pi
				 JOIN ' . gw_erp_tbl( 'products' ) . ' p ON p.id = pi.product_id
				 WHERE pi.purchase_id = %d',
				(int) $id
			) );
		}
		return $po;
	}

	/**
	 * 保存采购单：状态置为「已入库」时按明细入库一次（幂等）
	 */
	public static function save_purchase( $data ) {
		global $wpdb;

		$total = 0;
		if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $it ) {
				$total += (float) $it['unit_cost'] * (int) $it['qty'];
			}
		}

		$t   = gw_erp_tbl( 'purchases' );
		$row = array(
			'po_no'         => sanitize_text_field( $data['po_no'] ),
			'supplier'      => sanitize_text_field( isset( $data['supplier'] ) ? $data['supplier'] : '' ),
			'partner_id'    => (int) ( isset( $data['partner_id'] ) ? $data['partner_id'] : 0 ),
			'warehouse_id'  => (int) ( isset( $data['warehouse_id'] ) ? $data['warehouse_id'] : self::default_warehouse_id() ),
			'status'        => sanitize_key( isset( $data['status'] ) ? $data['status'] : 'ordered' ),
			'total_cost'    => round( $total, 2 ),
			'paid_amount'   => (float) ( isset( $data['paid_amount'] ) ? $data['paid_amount'] : 0 ),
			'purchase_date' => sanitize_text_field( isset( $data['purchase_date'] ) ? $data['purchase_date'] : current_time( 'Y-m-d' ) ),
			'expected_at'   => ! empty( $data['expected_at'] ) ? sanitize_text_field( $data['expected_at'] ) : null,
			'note'          => sanitize_textarea_field( isset( $data['note'] ) ? $data['note'] : '' ),
		);

		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $data['id'] ) );
			$pid = (int) $data['id'];
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $t, $row );
			$pid = $wpdb->insert_id;
		}

		$wpdb->delete( gw_erp_tbl( 'purchase_items' ), array( 'purchase_id' => $pid ), array( '%d' ) );
		if ( ! empty( $data['items'] ) && is_array( $data['items'] ) ) {
			foreach ( $data['items'] as $it ) {
				if ( empty( $it['product_id'] ) ) {
					continue;
				}
				$wpdb->insert( gw_erp_tbl( 'purchase_items' ), array(
					'purchase_id' => $pid,
					'product_id'  => (int) $it['product_id'],
					'qty'         => (int) $it['qty'],
					'unit_cost'   => (float) $it['unit_cost'],
				) );
			}
		}

		// 入库（仅一次）
		if ( in_array( $row['status'], array( 'received' ), true ) ) {
			$already = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM " . gw_erp_tbl( 'stock_movements' ) . " WHERE related_type = 'purchase' AND related_id = %d AND type = 'purchase_in'",
				$pid
			) );
			if ( 0 === $already ) {
				foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT product_id, qty FROM ' . gw_erp_tbl( 'purchase_items' ) . ' WHERE purchase_id = %d', $pid ) ) as $it ) {
					self::move_stock( $it->product_id, $row['warehouse_id'], (int) $it->qty, 'purchase_in', 'purchase', $pid, '采购入库 ' . $row['po_no'] );
				}
			}
		}

		return $pid;
	}

	public static function delete_purchase( $id ) {
		global $wpdb;
		$wpdb->delete( gw_erp_tbl( 'purchase_items' ), array( 'purchase_id' => (int) $id ), array( '%d' ) );
		$wpdb->delete( gw_erp_tbl( 'purchases' ), array( 'id' => (int) $id ), array( '%d' ) );
	}

	/* ================= 财务 ================= */

	public static function get_accounts() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . gw_erp_tbl( 'finance_accounts' ) . ' ORDER BY id ASC' );
	}

	public static function save_account( $data ) {
		global $wpdb;
		$t = gw_erp_tbl( 'finance_accounts' );
		$row = array(
			'name' => sanitize_text_field( $data['name'] ),
			'type' => sanitize_key( isset( $data['type'] ) ? $data['type'] : 'cash' ),
			'note' => sanitize_text_field( isset( $data['note'] ) ? $data['note'] : '' ),
		);
		if ( ! empty( $data['id'] ) ) {
			$wpdb->update( $t, $row, array( 'id' => (int) $data['id'] ) );
			return (int) $data['id'];
		}
		$wpdb->insert( $t, $row );
		return $wpdb->insert_id;
	}

	/**
	 * 记一笔收支：改余额 + 写流水（同一事务）
	 */
	public static function record_flow( $data ) {
		global $wpdb;

		$account_id = (int) $data['account_id'];
		$amount     = (float) $data['amount'];
		$direction  = 'out' === $data['direction'] ? 'out' : 'in';

		if ( ! $account_id || $amount <= 0 ) {
			return new WP_Error( 'gw_erp_bad_flow', '资金流水参数不完整。' );
		}

		$wpdb->query( 'START TRANSACTION' );
		$sign = 'in' === $direction ? 1 : -1;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . gw_erp_tbl( 'finance_accounts' ) . ' SET balance = balance + %f WHERE id = %d',
			$sign * $amount, $account_id
		) );
		$wpdb->insert( gw_erp_tbl( 'finance_flows' ), array(
			'account_id'   => $account_id,
			'direction'    => $direction,
			'category'     => sanitize_text_field( isset( $data['category'] ) ? $data['category'] : '' ),
			'amount'       => $amount,
			'related_type' => sanitize_key( isset( $data['related_type'] ) ? $data['related_type'] : '' ),
			'related_id'   => (int) ( isset( $data['related_id'] ) ? $data['related_id'] : 0 ),
			'remark'       => sanitize_text_field( isset( $data['remark'] ) ? $data['remark'] : '' ),
			'occurred_at'  => sanitize_text_field( isset( $data['occurred_at'] ) ? $data['occurred_at'] : current_time( 'Y-m-d' ) ),
			'created_by'   => get_current_user_id(),
		) );
		$wpdb->query( 'COMMIT' );

		return $wpdb->insert_id;
	}

	public static function get_flows( $limit = 100, $args = array() ) {
		global $wpdb;
		$sql = 'SELECT f.*, a.name AS account_name
			FROM ' . gw_erp_tbl( 'finance_flows' ) . ' f
			LEFT JOIN ' . gw_erp_tbl( 'finance_accounts' ) . ' a ON a.id = f.account_id';
		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['account_id'] ) ) {
			$where[]  = 'f.account_id = %d';
			$params[] = (int) $args['account_id'];
		}
		if ( ! empty( $args['direction'] ) ) {
			$where[]  = 'f.direction = %s';
			$params[] = sanitize_key( $args['direction'] );
		}
		$sql .= ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY f.occurred_at DESC, f.id DESC LIMIT %d';
		$params[] = (int) $limit;

		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	public static function delete_flow( $id ) {
		global $wpdb;
		$flow = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . gw_erp_tbl( 'finance_flows' ) . ' WHERE id = %d', (int) $id ) );
		if ( ! $flow ) {
			return false;
		}
		$sign = 'in' === $flow->direction ? -1 : 1;
		$wpdb->query( $wpdb->prepare(
			'UPDATE ' . gw_erp_tbl( 'finance_accounts' ) . ' SET balance = balance + %f WHERE id = %d',
			$sign * (float) $flow->amount, (int) $flow->account_id
		) );
		$wpdb->delete( gw_erp_tbl( 'finance_flows' ), array( 'id' => (int) $id ), array( '%d' ) );
		return true;
	}

	/* ================= 统计 ================= */

	/** 利润汇总（可传月份 YYYY-MM） */
	public static function profit_summary( $month = '' ) {
		global $wpdb;
		$t      = gw_erp_tbl( 'orders' );
		$where  = '';
		$params = array();
		if ( $month ) {
			$where   = " WHERE DATE_FORMAT(order_date, '%%Y-%%m') = %s";
			$params  = array( sanitize_text_field( $month ) );
		}
		$sql = "SELECT
			COALESCE(SUM(revenue),0) AS revenue,
			COALESCE(SUM(cost),0)    AS cost,
			COALESCE(SUM(fee),0)     AS fee,
			COALESCE(SUM(profit),0)  AS profit,
			COUNT(*)                 AS cnt
			FROM $t $where";
		return $wpdb->get_row( $wpdb->prepare( $sql, $params ) );
	}

	public static function sales_by_channel() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT channel, COALESCE(SUM(revenue),0) AS revenue, COALESCE(SUM(profit),0) AS profit, COUNT(*) AS cnt
			 FROM " . gw_erp_tbl( 'orders' ) . "
			 WHERE channel <> ''
			 GROUP BY channel ORDER BY revenue DESC"
		);
	}

	public static function monthly_trend( $n = 6 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE_FORMAT(order_date, '%%Y-%%m') AS ym,
			        COALESCE(SUM(revenue),0) AS revenue,
			        COALESCE(SUM(profit),0)  AS profit,
			        COUNT(*)                 AS cnt
			 FROM " . gw_erp_tbl( 'orders' ) . "
			 WHERE order_date >= DATE_SUB(CURDATE(), INTERVAL %d MONTH)
			 GROUP BY ym ORDER BY ym ASC",
			(int) $n
		) );
	}

	/** 应收账款（已发货/已完成但未收齐） */
	public static function receivables() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM " . gw_erp_tbl( 'orders' ) . "
			 WHERE status IN ('shipped','done') AND paid_amount < revenue
			 ORDER BY order_date ASC LIMIT 100"
		);
	}

	/** 应付账款（已下单未付清） */
	public static function payables() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM " . gw_erp_tbl( 'purchases' ) . "
			 WHERE status IN ('ordered','partial','received') AND paid_amount < total_cost
			 ORDER BY purchase_date ASC LIMIT 100"
		);
	}
}
