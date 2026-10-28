<?php
/**
 * Plugin Name: 港湾ERP — 统一企业经营管理插件
 * Plugin URI:  https://github.com/mornrain-lin/gangwanERP-free
 * Description: 港湾ERP（gangwanERP）WordPress 统一插件：ERP工具（tool 文章类型 + 使用场景分类）、商品库存、订单、采购、客户供应商、财务资金、经营报表、前台卖家工具台，并内置 GitHub 模块中心（在后台直接查看 GitHub 仓库的模块清单与最近提交）。纯 PHP + $wpdb，零外部依赖。
 * Version:     1.0.0
 * Requires at least: 5.9
 * Requires PHP: 7.4
 * Author:      GangWan ERP
 * Author URI:  https://github.com/mornrain-lin/gangwanERP-free
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gangwan-erp
 * Domain Path: /languages
 *
 * @package GangwanERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------------------------------------------------------------
 * 常量
 * ------------------------------------------------------------- */
define( 'GW_ERP_VERSION', '1.0.0' );
define( 'GW_ERP_DB_VERSION', '1.1.0' );
define( 'GW_ERP_FILE', __FILE__ );
define( 'GW_ERP_DIR', plugin_dir_path( __FILE__ ) );
define( 'GW_ERP_URL', plugin_dir_url( __FILE__ ) );

/* ---------------------------------------------------------------
 * 自动加载（includes/class-gw-*.php → GW_ERP_* 类名）
 * ------------------------------------------------------------- */
if ( ! function_exists( 'gw_erp_autoload' ) ) {
	function gw_erp_autoload( $class ) {
		if ( 0 !== strpos( $class, 'GW_ERP_' ) ) {
			return;
		}
		$slug = strtolower( str_replace( '_', '-', $class ) ); // gw-erp-install
		$file = GW_ERP_DIR . 'includes/class-' . $slug . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
	spl_autoload_register( 'gw_erp_autoload' );
}

/* ---------------------------------------------------------------
 * 表名助手：统一前缀 {wp_}gw_
 * ------------------------------------------------------------- */
if ( ! function_exists( 'gw_erp_tbl' ) ) {
	function gw_erp_tbl( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'gw_' . $name;
	}
}

/** 插件是否已接管（主题据此停用自带的 inc/erp 与 inc/cpt） */
if ( ! function_exists( 'gw_erp_loaded' ) ) {
	function gw_erp_loaded() {
		return true;
	}
}

/* ---------------------------------------------------------------
 * 生命周期钩子
 * ------------------------------------------------------------- */
register_activation_hook( __FILE__, array( 'GW_ERP_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'GW_ERP_Install', 'deactivate' ) );

/* ---------------------------------------------------------------
 * 启动
 * ------------------------------------------------------------- */
add_action( 'plugins_loaded', 'gw_erp_bootstrap' );

function gw_erp_bootstrap() {
	load_plugin_textdomain( 'gangwan-erp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	// 主题遗留函数名兼容层（必须早于任何模板调用）
	require_once GW_ERP_DIR . 'includes/compat-legacy.php';

	// 数据结构自检（版本变化时幂等升级）
	GW_ERP_Install::maybe_upgrade();

	// 内容层：ERP工具（tool）+ 使用场景（scenario），从主题接管
	GW_ERP_CPT::init();

	// 后台菜单与页面
	if ( is_admin() ) {
		GW_ERP_Admin::init();
		GW_ERP_Modules::init();
	}

	// 前台短代码
	GW_ERP_Shortcode::init();

	/**
	 * 插件启动完成动作：第三方可在此挂载。
	 */
	do_action( 'gw_erp_loaded' );
}

/* ---------------------------------------------------------------
 * 公共小工具
 * ------------------------------------------------------------- */

/** 金额格式化（人民币口径，符号可配置） */
if ( ! function_exists( 'gw_erp_money' ) ) {
	function gw_erp_money( $amount ) {
		$symbol = get_option( 'gw_erp_currency_symbol', '¥' );
		return $symbol . number_format( (float) $amount, 2, '.', ',' );
	}
}

/** 当前用户是否可操作 ERP */
if ( ! function_exists( 'gw_erp_can' ) ) {
	function gw_erp_can() {
		$cap = apply_filters( 'gw_erp_capability', 'manage_options' );
		return current_user_can( $cap );
	}
}

/** 生成单号（GW + 类型 + 日期 + 随机） */
if ( ! function_exists( 'gw_erp_next_no' ) ) {
	function gw_erp_next_no( $prefix = 'SO' ) {
		return strtoupper( $prefix ) . '-' . current_time( 'Ymd' ) . '-' . strtoupper( substr( wp_generate_password( 6, false, false ), 0, 4 ) );
	}
}

/** 订单状态字典 */
if ( ! function_exists( 'gw_erp_order_statuses' ) ) {
	function gw_erp_order_statuses() {
		return array(
			'pending'  => '待付款',
			'paid'     => '已付款',
			'shipped'  => '已发货',
			'done'     => '已完成',
			'refund'   => '已退款',
			'cancel'   => '已取消',
		);
	}
}

/** 采购状态字典 */
if ( ! function_exists( 'gw_erp_purchase_statuses' ) ) {
	function gw_erp_purchase_statuses() {
		return array(
			'draft'    => '草稿',
			'ordered'  => '已下单',
			'partial'  => '部分到货',
			'received' => '已入库',
			'cancel'   => '已取消',
		);
	}
}
