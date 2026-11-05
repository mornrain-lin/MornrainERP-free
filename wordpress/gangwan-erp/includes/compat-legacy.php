<?php
/**
 * 晨雨ERP —— 主题遗留函数兼容层
 *
 * 主题内嵌 ERP（inc/erp/*）被本插件接管后，主题模板/短代码里若仍调用旧的
 * gangwan_erp_* 函数，由此文件转发到新实现，数据统一走 {prefix}gw_ 表。
 * 全部以 function_exists 包裹：主题自带版本若先加载也不会冲突。
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'gangwan_erp_tbl' ) ) {
	function gangwan_erp_tbl( $name ) {
		return gw_erp_tbl( $name );
	}
}

if ( ! function_exists( 'gangwan_erp_activate' ) ) {
	function gangwan_erp_activate() {
		GW_ERP_Install::activate();
	}
}
if ( ! function_exists( 'gangwan_erp_reset_data' ) ) {
	function gangwan_erp_reset_data() {
		GW_ERP_Install::reset_data();
	}
}
if ( ! function_exists( 'gangwan_erp_seed_sample' ) ) {
	function gangwan_erp_seed_sample() {
		GW_ERP_Install::seed_sample_if_empty();
	}
}

/* 商品 */
if ( ! function_exists( 'gangwan_erp_get_products' ) ) {
	function gangwan_erp_get_products( $search = '' ) {
		return GW_ERP_DB::get_products( $search );
	}
}
if ( ! function_exists( 'gangwan_erp_get_product' ) ) {
	function gangwan_erp_get_product( $id ) {
		return GW_ERP_DB::get_product( $id );
	}
}
if ( ! function_exists( 'gangwan_erp_save_product' ) ) {
	function gangwan_erp_save_product( $data ) {
		return GW_ERP_DB::save_product( $data );
	}
}
if ( ! function_exists( 'gangwan_erp_delete_product' ) ) {
	function gangwan_erp_delete_product( $id ) {
		return GW_ERP_DB::delete_product( $id );
	}
}

/* 仓库与库存 */
if ( ! function_exists( 'gangwan_erp_get_warehouses' ) ) {
	function gangwan_erp_get_warehouses() {
		return GW_ERP_DB::get_warehouses();
	}
}
if ( ! function_exists( 'gangwan_erp_get_inventory_rows' ) ) {
	function gangwan_erp_get_inventory_rows() {
		return GW_ERP_DB::get_inventory_rows();
	}
}
if ( ! function_exists( 'gangwan_erp_stock_total' ) ) {
	function gangwan_erp_stock_total( $product_id ) {
		return GW_ERP_DB::stock_total( $product_id );
	}
}
if ( ! function_exists( 'gangwan_erp_set_inventory' ) ) {
	function gangwan_erp_set_inventory( $product_id, $warehouse_id, $qty ) {
		return GW_ERP_DB::set_inventory( $product_id, $warehouse_id, $qty );
	}
}
if ( ! function_exists( 'gangwan_erp_low_stock' ) ) {
	function gangwan_erp_low_stock( $threshold = 50 ) {
		return GW_ERP_DB::low_stock( $threshold );
	}
}

/* 订单 */
if ( ! function_exists( 'gangwan_erp_get_orders' ) ) {
	function gangwan_erp_get_orders( $limit = 50 ) {
		return GW_ERP_DB::get_orders( $limit );
	}
}
if ( ! function_exists( 'gangwan_erp_get_order' ) ) {
	function gangwan_erp_get_order( $id ) {
		return GW_ERP_DB::get_order( $id );
	}
}
if ( ! function_exists( 'gangwan_erp_save_order' ) ) {
	function gangwan_erp_save_order( $data ) {
		return GW_ERP_DB::save_order( $data );
	}
}
if ( ! function_exists( 'gangwan_erp_delete_order' ) ) {
	function gangwan_erp_delete_order( $id ) {
		return GW_ERP_DB::delete_order( $id );
	}
}

/* 采购 */
if ( ! function_exists( 'gangwan_erp_get_purchases' ) ) {
	function gangwan_erp_get_purchases( $limit = 50 ) {
		return GW_ERP_DB::get_purchases( $limit );
	}
}
if ( ! function_exists( 'gangwan_erp_save_purchase' ) ) {
	function gangwan_erp_save_purchase( $data ) {
		return GW_ERP_DB::save_purchase( $data );
	}
}
if ( ! function_exists( 'gangwan_erp_delete_purchase' ) ) {
	function gangwan_erp_delete_purchase( $id ) {
		return GW_ERP_DB::delete_purchase( $id );
	}
}

/* 统计 */
if ( ! function_exists( 'gangwan_erp_profit_summary' ) ) {
	function gangwan_erp_profit_summary( $month = '' ) {
		return GW_ERP_DB::profit_summary( $month );
	}
}
if ( ! function_exists( 'gangwan_erp_sales_by_channel' ) ) {
	function gangwan_erp_sales_by_channel() {
		return GW_ERP_DB::sales_by_channel();
	}
}
if ( ! function_exists( 'gangwan_erp_monthly_trend' ) ) {
	function gangwan_erp_monthly_trend( $n = 6 ) {
		return GW_ERP_DB::monthly_trend( $n );
	}
}
if ( ! function_exists( 'gangwan_erp_kpis' ) ) {
	function gangwan_erp_kpis() {
		$k = GW_ERP_Dashboard::kpis();
		return array(
			'products'      => $k['products'],
			'stock'         => $k['stock'],
			'stock_value'   => $k['stock_value'],
			'low_stock'     => $k['low_stock'],
			'month_revenue' => $k['month_revenue'],
			'month_profit'  => $k['month_profit'],
			'month_orders'  => $k['month_orders'],
		);
	}
}
