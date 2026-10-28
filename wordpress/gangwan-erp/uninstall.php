<?php
/**
 * 港湾ERP —— 卸载清理
 *
 * 默认删除插件自建的 {prefix}gw_* 数据表与设置项。
 * 如需保留数据，请在 wp-config.php 中定义：define( 'GW_ERP_KEEP_DATA', true );
 *
 * @package GangwanERP
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( defined( 'GW_ERP_KEEP_DATA' ) && GW_ERP_KEEP_DATA ) {
	return;
}

global $wpdb;

$tables = array(
	'finance_flows', 'finance_accounts', 'purchase_items', 'purchases',
	'order_items', 'orders', 'stock_movements', 'inventory',
	'warehouses', 'partners', 'products',
);

foreach ( $tables as $t ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'gw_' . $t ); // phpcs:ignore
}

delete_option( 'gw_erp_db_version' );
delete_option( 'gw_erp_cerp_migrated' );
delete_option( 'gw_erp_github' );
delete_option( 'gw_erp_low_stock_threshold' );
delete_option( 'gw_erp_currency_symbol' );
delete_option( 'gw_erp_workbench_roles' );

delete_transient( 'gw_erp_github_modules' );
delete_transient( 'gw_erp_github_repo' );
delete_transient( 'gw_erp_github_commits' );
delete_transient( 'gw_erp_tables_ok' );
