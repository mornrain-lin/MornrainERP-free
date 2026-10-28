<?php
/**
 * 港湾ERP —— 前台短代码
 *
 * [gangwan_erp]          卖家工具台（原主题短代码，行为保持一致）
 * [gangwan_erp_modules]  GitHub 模块清单（分组 + 状态）
 *
 * @package GangwanERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GW_ERP_Shortcode {

	public static function init() {
		add_shortcode( 'gangwan_erp', array( __CLASS__, 'workbench' ) );
		add_shortcode( 'gangwan_erp_modules', array( __CLASS__, 'modules' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function assets() {
		wp_register_style( 'gw-erp-front', GW_ERP_URL . 'assets/css/front.css', array(), GW_ERP_VERSION );
	}

	/** 可见角色（默认：登录用户即可；可在设置里限定角色） */
	private static function can_view() {
		if ( ! is_user_logged_in() ) {
			return false;
		}
		$roles = (array) get_option( 'gw_erp_workbench_roles', array() );
		$roles = array_filter( $roles );
		if ( empty( $roles ) ) {
			return true;
		}
		$user = wp_get_current_user();
		return (bool) array_intersect( $roles, (array) $user->roles );
	}

	public static function workbench( $atts ) {
		$atts = shortcode_atts( array(
			'limit' => 10,
		), $atts, 'gangwan_erp' );

		if ( ! self::can_view() ) {
			return '<div class="gw-erp-workbench gw-erp-locked"><p>卖家工具台仅对授权用户开放。'
				. ' <a class="button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">登录</a></p></div>';
		}

		wp_enqueue_style( 'gw-erp-front' );

		$k      = GW_ERP_Dashboard::kpis();
		$orders = GW_ERP_DB::get_orders( (int) $atts['limit'] );

		ob_start();
		?>
		<div class="gw-erp-workbench">
			<h2 class="gw-wb-title">卖家工具台</h2>
			<div class="gw-wb-kpis">
				<div class="gw-wb-kpi"><span>本月销售额</span><strong><?php echo esc_html( gw_erp_money( $k['month_revenue'] ) ); ?></strong></div>
				<div class="gw-wb-kpi"><span>本月利润</span><strong class="<?php echo $k['month_profit'] >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $k['month_profit'] ) ); ?></strong></div>
				<div class="gw-wb-kpi"><span>库存总量</span><strong><?php echo esc_html( number_format_i18n( $k['stock'] ) ); ?> 件</strong></div>
				<div class="gw-wb-kpi"><span>低库存</span><strong><?php echo esc_html( number_format_i18n( $k['low_stock'] ) ); ?> 个</strong></div>
			</div>

			<?php if ( current_user_can( 'manage_options' ) ) : ?>
			<p class="gw-wb-actions">
				<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-orders' ) ); ?>" target="_blank">+ 录订单</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp' ) ); ?>" target="_blank">进入后台</a>
			</p>
			<?php endif; ?>

			<h3>近期订单</h3>
			<table class="gw-erp-table">
				<thead><tr><th>订单号</th><th>渠道</th><th>状态</th><th>利润</th></tr></thead>
				<tbody>
				<?php if ( $orders ) : ?>
					<?php foreach ( $orders as $o ) : ?>
					<tr>
						<td><?php echo esc_html( $o->order_no ); ?></td>
						<td><?php echo esc_html( $o->channel ); ?></td>
						<td><?php echo esc_html( self::order_status_label( $o->status ) ); ?></td>
						<td class="<?php echo $o->profit >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $o->profit ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr><td colspan="4">暂无订单数据。</td></tr>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	public static function modules( $atts ) {
		$atts = shortcode_atts( array(
			'group' => '',
		), $atts, 'gangwan_erp_modules' );

		wp_enqueue_style( 'gw-erp-front' );

		$data   = GW_ERP_Modules::for_shortcode();
		$groups = isset( $data['groups'] ) ? $data['groups'] : array();

		if ( empty( $groups ) ) {
			return '<div class="gw-erp-modules gw-erp-empty"><p>暂未获取到模块清单。</p></div>';
		}

		ob_start();
		echo '<div class="gw-erp-modules">';
		echo '<p class="gw-erp-modules-meta">数据来源：' . esc_html( isset( $data['source'] ) ? $data['source'] : '' ) . ' ｜ 更新于 ' . esc_html( isset( $data['fetched'] ) ? $data['fetched'] : '' ) . '</p>';
		foreach ( $groups as $g ) {
			if ( $atts['group'] && $atts['group'] !== $g['name'] ) {
				continue;
			}
			echo '<section class="gw-mod-group"><h3>' . esc_html( $g['name'] ) . '</h3><ul class="gw-mod-list">';
			foreach ( $g['items'] as $it ) {
				echo '<li class="gw-mod-item gw-mod-' . esc_attr( $it['status'] ) . '">'
					. '<span class="gw-mod-name">' . esc_html( $it['name'] ) . '</span>'
					. '<span class="gw-mod-badge">' . esc_html( 'planned' === $it['status'] ? '规划中' : '已上线' ) . '</span>'
					. '</li>';
			}
			echo '</ul></section>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	public static function order_status_label( $status ) {
		$map = gw_erp_order_statuses();
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}
}

/* 主题兼容：旧版 [gangwan_erp] 回调函数名 */
if ( ! function_exists( 'gangwan_erp_workbench' ) ) {
	function gangwan_erp_workbench( $atts ) {
		return GW_ERP_Shortcode::workbench( $atts );
	}
}
