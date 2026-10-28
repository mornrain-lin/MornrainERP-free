<?php
/**
 * 港湾ERP —— 经营看板聚合（KPI / 渠道分布 / 月度趋势 / 预警）
 *
 * @package GangwanERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GW_ERP_Dashboard {

	/**
	 * 经营概览 KPI
	 */
	public static function kpis() {
		global $wpdb;

		$products    = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . gw_erp_tbl( 'products' ) );
		$stock       = (int) $wpdb->get_var( 'SELECT COALESCE(SUM(qty),0) FROM ' . gw_erp_tbl( 'inventory' ) );
		$stock_value = (float) $wpdb->get_var(
			'SELECT COALESCE(SUM(i.qty * p.cost_price),0)
			 FROM ' . gw_erp_tbl( 'inventory' ) . ' i
			 JOIN ' . gw_erp_tbl( 'products' ) . ' p ON p.id = i.product_id'
		);
		$low         = count( GW_ERP_DB::low_stock() );

		$month  = current_time( 'Y-m' );
		$ps     = GW_ERP_DB::profit_summary( $month );

		$cash   = (float) $wpdb->get_var( 'SELECT COALESCE(SUM(balance),0) FROM ' . gw_erp_tbl( 'finance_accounts' ) );
		$recv   = (float) $wpdb->get_var( "SELECT COALESCE(SUM(revenue - paid_amount),0) FROM " . gw_erp_tbl( 'orders' ) . " WHERE status IN ('shipped','done') AND paid_amount < revenue" );
		$pay    = (float) $wpdb->get_var( "SELECT COALESCE(SUM(total_cost - paid_amount),0) FROM " . gw_erp_tbl( 'purchases' ) . " WHERE status IN ('ordered','partial','received') AND paid_amount < total_cost" );

		return array(
			'products'      => $products,
			'stock'         => $stock,
			'stock_value'   => $stock_value,
			'low_stock'     => $low,
			'month_revenue' => (float) $ps->revenue,
			'month_cost'    => (float) $ps->cost,
			'month_fee'     => (float) $ps->fee,
			'month_profit'  => (float) $ps->profit,
			'month_orders'  => (int) $ps->cnt,
			'month_margin'  => $ps->revenue > 0 ? round( $ps->profit / $ps->revenue * 100, 1 ) : 0,
			'cash'          => $cash,
			'receivable'    => $recv,
			'payable'       => $pay,
		);
	}

	/**
	 * 预警信号（低库存 / 负利润订单 / 逾期应收 / 逾期应付）
	 */
	public static function alerts() {
		global $wpdb;

		$alerts = array();

		$low = GW_ERP_DB::low_stock();
		if ( $low ) {
			$alerts[] = array(
				'level' => 'warn',
				'title' => count( $low ) . ' 个商品低于安全库存',
				'link'  => admin_url( 'admin.php?page=gw-erp-inventory' ),
			);
		}

		$neg = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . gw_erp_tbl( 'orders' ) . ' WHERE profit < 0' );
		if ( $neg > 0 ) {
			$alerts[] = array(
				'level' => 'warn',
				'title' => $neg . ' 张订单利润为负',
				'link'  => admin_url( 'admin.php?page=gw-erp-orders' ),
			);
		}

		$recv_cnt = count( GW_ERP_DB::receivables() );
		if ( $recv_cnt > 0 ) {
			$alerts[] = array(
				'level' => 'info',
				'title' => $recv_cnt . ' 张订单待收款',
				'link'  => admin_url( 'admin.php?page=gw-erp-finance' ),
			);
		}

		$pay_cnt = count( GW_ERP_DB::payables() );
		if ( $pay_cnt > 0 ) {
			$alerts[] = array(
				'level' => 'info',
				'title' => $pay_cnt . ' 张采购单待付款',
				'link'  => admin_url( 'admin.php?page=gw-erp-finance' ),
			);
		}

		return $alerts;
	}

	/**
	 * 简易 CSV 导出
	 */
	public static function export_csv( $filename, $headers, $rows ) {
		$out = fopen( 'php://output', 'w' );
		if ( ! $out ) {
			return;
		}
		// BOM：让 Excel 正确识别 UTF-8 中文
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, $headers );
		foreach ( $rows as $r ) {
			fputcsv( $out, $r );
		}
		fclose( $out );
	}
}
