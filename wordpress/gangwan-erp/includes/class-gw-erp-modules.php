<?php
/**
 * 港湾ERP —— GitHub 模块中心
 *
 * 后台直接查看 GitHub 仓库（gangwanERP）的模块清单、状态、权限点与最近提交。
 *
 * @package GangwanERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GW_ERP_Modules {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 20 );
		add_action( 'admin_post_gw_erp_refresh_modules', array( __CLASS__, 'handle_refresh' ) );
		add_action( 'admin_post_gw_erp_save_github', array( __CLASS__, 'handle_save' ) );
	}

	public static function menu() {
		add_submenu_page(
			'gw-erp',
			'GitHub 模块中心',
			'GitHub 模块中心',
			apply_filters( 'gw_erp_capability', 'manage_options' ),
			'gw-erp-modules',
			array( __CLASS__, 'render' )
		);
	}

	/** 手动刷新：清空缓存后回跳 */
	public static function handle_refresh() {
		if ( ! current_user_can( apply_filters( 'gw_erp_capability', 'manage_options' ) ) ) {
			wp_die( '权限不足' );
		}
		check_admin_referer( 'gw_erp_refresh_modules' );

		GW_ERP_Github::flush_cache();
		GW_ERP_Github::modules( true );
		GW_ERP_Github::repo( true );
		GW_ERP_Github::commits( 5, true );

		wp_safe_redirect( admin_url( 'admin.php?page=gw-erp-modules&refreshed=1' ) );
		exit;
	}

	/** 保存 GitHub 设置 */
	public static function handle_save() {
		if ( ! current_user_can( apply_filters( 'gw_erp_capability', 'manage_options' ) ) ) {
			wp_die( '权限不足' );
		}
		check_admin_referer( 'gw_erp_save_github' );

		if ( isset( $_POST['gw_erp_github'] ) && is_array( $_POST['gw_erp_github'] ) ) {
			$data = wp_unslash( $_POST['gw_erp_github'] ); // phpcs:ignore
			// Token 为空表示「不修改」：保留原值
			if ( empty( $data['token'] ) ) {
				$old = GW_ERP_Github::settings();
				$data['token'] = $old['token'];
			}
			GW_ERP_Github::save_settings( $data );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=gw-erp-modules&saved=1' ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( apply_filters( 'gw_erp_capability', 'manage_options' ) ) ) {
			wp_die( '权限不足' );
		}

		$modules = GW_ERP_Github::modules();
		$repo    = GW_ERP_Github::repo();
		$commits = GW_ERP_Github::commits( 5 );
		$cfg     = GW_ERP_Github::settings();

		include GW_ERP_DIR . 'views/modules.php';
	}

	/**
	 * 供前台短代码使用：取分组模块
	 */
	public static function for_shortcode() {
		$data = GW_ERP_Github::modules();
		return $data;
	}
}
