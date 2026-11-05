<?php
/**
 * 晨雨ERP —— 后台菜单、页面路由与表单处理
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GW_ERP_Admin {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_gw_erp_save', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_gw_erp_delete', array( __CLASS__, 'handle_delete' ) );
		add_action( 'admin_post_gw_erp_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_gw_erp_reset', array( __CLASS__, 'handle_reset' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
	}

	/** 统一的权限能力 */
	private static function cap() {
		return apply_filters( 'gw_erp_capability', 'manage_options' );
	}

	public static function menu() {
		add_menu_page(
			'晨雨ERP',
			'晨雨ERP',
			self::cap(),
			'gw-erp',
			array( __CLASS__, 'render_page' ),
			'dashicons-store',
			56
		);

		$pages = array(
			'gw-erp'             => '概览看板',
			'gw-erp-products'    => '商品管理',
			'gw-erp-inventory'   => '库存与流水',
			'gw-erp-orders'      => '订单管理',
			'gw-erp-purchases'   => '采购管理',
			'gw-erp-partners'    => '客户与供应商',
			'gw-erp-finance'     => '财务资金',
			'gw-erp-reports'     => '经营报表',
			'gw-erp-settings'    => '设置',
		);

		foreach ( $pages as $slug => $title ) {
			add_submenu_page( 'gw-erp', $title, $title, self::cap(), $slug, array( __CLASS__, 'render_page' ) );
		}
	}

	public static function assets( $hook ) {
		if ( ! $hook || false === strpos( $hook, 'gw-erp' ) ) {
			return;
		}
		wp_enqueue_style( 'gw-erp-admin', GW_ERP_URL . 'assets/css/admin.css', array(), GW_ERP_VERSION );
		wp_enqueue_script( 'gw-erp-admin', GW_ERP_URL . 'assets/js/admin.js', array( 'jquery' ), GW_ERP_VERSION, true );
	}

	/* ---------------- 页面路由 ---------------- */

	public static function render_page() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( '权限不足' );
		}

		$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : 'gw-erp'; // phpcs:ignore

		echo '<div class="wrap gw-erp-wrap">';
		echo '<h1 class="gw-erp-title">晨雨ERP<span class="gw-erp-ver">v' . esc_html( GW_ERP_VERSION ) . '</span></h1>';

		self::tabs( $page );

		switch ( $page ) {
			case 'gw-erp-products':
				include GW_ERP_DIR . 'views/products.php';
				break;
			case 'gw-erp-inventory':
				include GW_ERP_DIR . 'views/inventory.php';
				break;
			case 'gw-erp-orders':
				include GW_ERP_DIR . 'views/orders.php';
				break;
			case 'gw-erp-purchases':
				include GW_ERP_DIR . 'views/purchases.php';
				break;
			case 'gw-erp-partners':
				include GW_ERP_DIR . 'views/partners.php';
				break;
			case 'gw-erp-finance':
				include GW_ERP_DIR . 'views/finance.php';
				break;
			case 'gw-erp-reports':
				include GW_ERP_DIR . 'views/reports.php';
				break;
			case 'gw-erp-settings':
				include GW_ERP_DIR . 'views/settings.php';
				break;
			default:
				include GW_ERP_DIR . 'views/overview.php';
		}

		echo '</div>';
	}

	private static function tabs( $current ) {
		$tabs = array(
			'gw-erp'           => '概览看板',
			'gw-erp-products'  => '商品',
			'gw-erp-inventory' => '库存',
			'gw-erp-orders'    => '订单',
			'gw-erp-purchases' => '采购',
			'gw-erp-partners'  => '往来单位',
			'gw-erp-finance'   => '财务',
			'gw-erp-reports'   => '报表',
			'gw-erp-modules'   => '模块中心',
			'gw-erp-settings'  => '设置',
		);
		echo '<nav class="nav-tab-wrapper gw-erp-tabs">';
		foreach ( $tabs as $slug => $label ) {
			$cls = $slug === $current ? 'nav-tab nav-tab-active' : 'nav-tab';
			echo '<a class="' . esc_attr( $cls ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</nav>';
	}

	/* ---------------- 表单处理 ---------------- */

	public static function handle_save() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( '权限不足' );
		}

		$entity = isset( $_POST['gw_entity'] ) ? sanitize_key( $_POST['gw_entity'] ) : ''; // phpcs:ignore
		check_admin_referer( 'gw_erp_save_' . $entity );

		$data = isset( $_POST['gw'] ) ? (array) wp_unslash( $_POST['gw'] ) : array(); // phpcs:ignore
		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=gw-erp' );
		$msg  = 'saved';

		switch ( $entity ) {
			case 'product':
				if ( empty( $data['sku'] ) || empty( $data['name'] ) ) {
					wp_safe_redirect( add_query_arg( array( 'gw_err' => 'sku_name_required' ), $back ) );
					exit;
				}
				GW_ERP_DB::save_product( $data );
				break;

			case 'order':
				if ( empty( $data['order_no'] ) ) {
					$data['order_no'] = gw_erp_next_no( 'SO' );
				}
				if ( isset( $data['items'] ) ) {
					$data['items'] = self::normalize_items( $data['items'], 'price' );
				}
				GW_ERP_DB::save_order( $data );
				break;

			case 'purchase':
				if ( empty( $data['po_no'] ) ) {
					$data['po_no'] = gw_erp_next_no( 'PO' );
				}
				if ( isset( $data['items'] ) ) {
					$data['items'] = self::normalize_items( $data['items'], 'unit_cost' );
				}
				GW_ERP_DB::save_purchase( $data );
				break;

			case 'partner':
				if ( empty( $data['name'] ) ) {
					wp_safe_redirect( add_query_arg( array( 'gw_err' => 'name_required' ), $back ) );
					exit;
				}
				GW_ERP_DB::save_partner( $data );
				break;

			case 'account':
				GW_ERP_DB::save_account( $data );
				break;

			case 'flow':
				$res = GW_ERP_DB::record_flow( $data );
				if ( is_wp_error( $res ) ) {
					wp_safe_redirect( add_query_arg( array( 'gw_err' => 'flow_invalid' ), $back ) );
					exit;
				}
				break;

			case 'stocktake':
				GW_ERP_DB::set_inventory( (int) $data['product_id'], (int) $data['warehouse_id'], (int) $data['qty'] );
				break;

			case 'adjust':
				$qty = (int) $data['qty'];
				GW_ERP_DB::move_stock(
					(int) $data['product_id'], (int) $data['warehouse_id'], $qty,
					$qty >= 0 ? 'adjust_in' : 'adjust_out', 'adjust', 0,
					sanitize_text_field( isset( $data['note'] ) ? $data['note'] : '手工调整' )
				);
				break;

			case 'transfer':
				$qty = absint( $data['qty'] );
				if ( $qty <= 0 || (int) $data['from_warehouse'] === (int) $data['to_warehouse'] ) {
					wp_safe_redirect( add_query_arg( array( 'gw_err' => 'transfer_invalid' ), $back ) );
					exit;
				}
				GW_ERP_DB::move_stock( (int) $data['product_id'], (int) $data['from_warehouse'], -1 * $qty, 'transfer_out', 'transfer', 0, '调拨出库' );
				GW_ERP_DB::move_stock( (int) $data['product_id'], (int) $data['to_warehouse'], $qty, 'transfer_in', 'transfer', 0, '调拨入库' );
				break;

			case 'settings':
				update_option( 'gw_erp_low_stock_threshold', absint( $data['low_stock_threshold'] ) );
				update_option( 'gw_erp_currency_symbol', sanitize_text_field( $data['currency_symbol'] ) );
				update_option( 'gw_erp_workbench_roles', array_map( 'sanitize_key', (array) $data['workbench_roles'] ) );
				$msg = 'settings_saved';
				break;

			default:
				wp_safe_redirect( $back );
				exit;
		}

		wp_safe_redirect( add_query_arg( array( 'gw_msg' => $msg ), $back ) );
		exit;
	}

	public static function handle_delete() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( '权限不足' );
		}
		$entity = isset( $_GET['gw_entity'] ) ? sanitize_key( $_GET['gw_entity'] ) : ''; // phpcs:ignore
		$id     = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0; // phpcs:ignore
		check_admin_referer( 'gw_erp_delete_' . $entity . '_' . $id );

		switch ( $entity ) {
			case 'product':
				GW_ERP_DB::delete_product( $id );
				break;
			case 'order':
				GW_ERP_DB::delete_order( $id );
				break;
			case 'purchase':
				GW_ERP_DB::delete_purchase( $id );
				break;
			case 'partner':
				GW_ERP_DB::delete_partner( $id );
				break;
			case 'flow':
				GW_ERP_DB::delete_flow( $id );
				break;
		}

		$back = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=gw-erp' );
		wp_safe_redirect( add_query_arg( array( 'gw_msg' => 'deleted' ), $back ) );
		exit;
	}

	/** CSV 导出 */
	public static function handle_export() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( '权限不足' );
		}
		$type = isset( $_GET['gw_type'] ) ? sanitize_key( $_GET['gw_type'] ) : 'orders'; // phpcs:ignore
		check_admin_referer( 'gw_erp_export' );

		$filename = 'mornrain-erp-' . $type . '-' . current_time( 'Ymd-His' ) . '.csv';
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		if ( 'products' === $type ) {
			$rows = array();
			foreach ( GW_ERP_DB::get_products( '', 5000 ) as $p ) {
				$rows[] = array( $p->sku, $p->name, $p->category, $p->sale_price, $p->cost_price, GW_ERP_DB::stock_total( $p->id ), $p->status );
			}
			GW_ERP_Dashboard::export_csv( $filename, array( 'SKU', '名称', '分类', '售价', '成本价', '库存', '状态' ), $rows );
		} elseif ( 'inventory' === $type ) {
			$rows = array();
			foreach ( GW_ERP_DB::get_inventory_rows() as $r ) {
				$rows[] = array( $r->sku, $r->name, $r->wh_name, $r->qty, $r->cost_price, round( $r->qty * $r->cost_price, 2 ) );
			}
			GW_ERP_Dashboard::export_csv( $filename, array( 'SKU', '名称', '仓库', '数量', '成本价', '货值' ), $rows );
		} elseif ( 'flows' === $type ) {
			$rows = array();
			foreach ( GW_ERP_DB::get_flows( 5000 ) as $f ) {
				$rows[] = array( $f->occurred_at, $f->account_name, 'in' === $f->direction ? '收入' : '支出', $f->category, $f->amount, $f->remark );
			}
			GW_ERP_Dashboard::export_csv( $filename, array( '日期', '账户', '方向', '类别', '金额', '备注' ), $rows );
		} elseif ( 'purchases' === $type ) {
			$rows = array();
			foreach ( GW_ERP_DB::get_purchases( 5000 ) as $p ) {
				$rows[] = array( $p->po_no, $p->supplier, $p->status, $p->total_cost, $p->paid_amount, $p->purchase_date );
			}
			GW_ERP_Dashboard::export_csv( $filename, array( '采购单号', '供应商', '状态', '金额', '已付', '日期' ), $rows );
		} else {
			$rows = array();
			foreach ( GW_ERP_DB::get_orders( 5000 ) as $o ) {
				$rows[] = array( $o->order_no, $o->customer, $o->channel, $o->status, $o->revenue, $o->cost, $o->fee, $o->profit, $o->order_date );
			}
			GW_ERP_Dashboard::export_csv( $filename, array( '订单号', '客户', '渠道', '状态', '营收', '成本', '费用', '利润', '日期' ), $rows );
		}
		exit;
	}

	/** 重置业务数据 */
	public static function handle_reset() {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( '权限不足' );
		}
		check_admin_referer( 'gw_erp_reset' );
		if ( ! isset( $_POST['gw_confirm'] ) || 'RESET' !== strtoupper( sanitize_text_field( wp_unslash( $_POST['gw_confirm'] ) ) ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=gw-erp-settings&gw_err=reset_confirm' ) );
			exit;
		}
		GW_ERP_Install::reset_data();
		wp_safe_redirect( admin_url( 'admin.php?page=gw-erp-settings&gw_msg=reset_done' ) );
		exit;
	}

	/* ---------------- 提示 ---------------- */

	public static function notices() {
		if ( ! isset( $_GET['gw_msg'] ) && ! isset( $_GET['gw_err'] ) ) { // phpcs:ignore
			return;
		}
		$msg = isset( $_GET['gw_msg'] ) ? sanitize_key( $_GET['gw_msg'] ) : ''; // phpcs:ignore
		$err = isset( $_GET['gw_err'] ) ? sanitize_key( $_GET['gw_err'] ) : ''; // phpcs:ignore

		$ok = array(
			'saved'          => '已保存。',
			'deleted'        => '已删除。',
			'settings_saved' => '设置已保存。',
			'reset_done'     => '业务数据已重置（表结构保留）。',
		);
		$bad = array(
			'sku_name_required' => 'SKU 与名称不能为空。',
			'name_required'     => '名称不能为空。',
			'flow_invalid'      => '资金流水参数不完整（需选择账户且金额大于 0）。',
			'transfer_invalid'  => '调拨参数不合法（数量需大于 0，且调出与调入仓库不同）。',
			'reset_confirm'     => '重置需输入 RESET 确认。',
		);

		if ( $msg && isset( $ok[ $msg ] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $ok[ $msg ] ) . '</p></div>';
		}
		if ( $err && isset( $bad[ $err ] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $bad[ $err ] ) . '</p></div>';
		}
	}

	/** 明细行归一化：过滤空行 */
	private static function normalize_items( $items, $price_key ) {
		$out = array();
		foreach ( (array) $items as $it ) {
			if ( empty( $it['product_id'] ) && empty( $it['qty'] ) ) {
				continue;
			}
			$out[] = array(
				'product_id' => (int) $it['product_id'],
				'qty'        => (int) $it['qty'],
				$price_key   => (float) ( isset( $it[ $price_key ] ) ? $it[ $price_key ] : 0 ),
			);
		}
		return $out;
	}
}
