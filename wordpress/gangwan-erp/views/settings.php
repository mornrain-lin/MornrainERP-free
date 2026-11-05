<?php
/**
 * 设置：通用参数 / GitHub 连接 / 短代码说明 / 数据重置
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cfg      = GW_ERP_Github::settings();
$has_tok  = defined( 'GW_ERP_GITHUB_TOKEN' ) && GW_ERP_GITHUB_TOKEN;
$threshold = (int) get_option( 'gw_erp_low_stock_threshold', 50 );
$symbol    = get_option( 'gw_erp_currency_symbol', '¥' );
$roles     = (array) get_option( 'gw_erp_workbench_roles', array() );
$wp_roles  = wp_roles()->get_names();
?>
<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2>通用设置</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="settings">
			<?php wp_nonce_field( 'gw_erp_save_settings' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th>低库存阈值</th>
					<td><input name="gw[low_stock_threshold]" type="number" step="1" min="0" value="<?php echo esc_attr( $threshold ); ?>" class="small-text">
						<p class="description">商品未单独设置安全库存时，按此阈值预警。</p>
					</td>
				</tr>
				<tr>
					<th>货币符号</th>
					<td><input name="gw[currency_symbol]" type="text" value="<?php echo esc_attr( $symbol ); ?>" class="small-text"></td>
				</tr>
				<tr>
					<th>前台工具台可见角色</th>
					<td>
						<?php foreach ( $wp_roles as $key => $name ) : ?>
							<label style="display:block">
								<input type="checkbox" name="gw[workbench_roles][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $roles, true ) ); ?>>
								<?php echo esc_html( $name ); ?>
							</label>
						<?php endforeach; ?>
						<p class="description">全不勾选 = 所有登录用户可见。</p>
					</td>
				</tr>
			</table>
			<?php submit_button( '保存设置' ); ?>
		</form>
	</div>

	<div class="gw-erp-card">
		<h2>GitHub 连接</h2>
		<p class="description">用于在「GitHub 模块中心」查看仓库模块清单与最近提交。只读访问，不写入任何数据。</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save_github">
			<?php wp_nonce_field( 'gw_erp_save_github' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>仓库 Owner</th><td><input name="gw_erp_github[owner]" type="text" class="regular-text" value="<?php echo esc_attr( $cfg['owner'] ); ?>"></td></tr>
				<tr><th>仓库名</th><td><input name="gw_erp_github[repo]" type="text" class="regular-text" value="<?php echo esc_attr( $cfg['repo'] ); ?>"></td></tr>
				<tr><th>分支</th><td><input name="gw_erp_github[branch]" type="text" class="regular-text" value="<?php echo esc_attr( $cfg['branch'] ); ?>"></td></tr>
				<tr>
					<th>访问 Token（可选）</th>
					<td>
						<?php if ( $has_tok ) : ?>
							<p class="description">已在 wp-config.php 中定义 <code>GW_ERP_GITHUB_TOKEN</code>，此项会被忽略。</p>
						<?php else : ?>
							<input name="gw_erp_github[token]" type="password" class="regular-text" autocomplete="new-password" placeholder="<?php echo $cfg['token'] ? esc_attr__( '已配置，留空表示不修改', 'gangwan-erp' ) : 'ghp_…（可选）'; ?>">
							<p class="description">
								留空即可（公开仓库匿名可读，60 次/小时）。填写后可提升到 5000 次/小时。
								Token 仅保存在本站数据库，界面永不回显明文。
								更安全的做法是在 wp-config.php 里 <code>define('GW_ERP_GITHUB_TOKEN','…');</code>。
							</p>
						<?php endif; ?>
					</td>
				</tr>
			</table>
			<?php submit_button( '保存并刷新' ); ?>
		</form>
	</div>
</div>

<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2>短代码</h2>
		<table class="gw-erp-table">
			<thead><tr><th>短代码</th><th>说明</th></tr></thead>
			<tbody>
				<tr><td><code>[gangwan_erp]</code></td><td>前台卖家工具台（KPI + 近期订单）。参数：<code>limit</code></td></tr>
				<tr><td><code>[gangwan_erp_modules]</code></td><td>前台展示 GitHub 模块清单。参数：<code>group="分组名"</code></td></tr>
			</tbody>
		</table>
		<p class="description">「ERP工具」文章类型（tool）与「使用场景」分类（scenario）已由本插件接管，主题模板无需改动即可继续工作。</p>
	</div>

	<div class="gw-erp-card">
		<h2>数据维护</h2>
		<p class="description">重置会清空商品 / 库存 / 订单 / 采购 / 往来单位 / 财务数据，保留表结构，不可撤销。</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('确认清空全部业务数据？该操作不可撤销。');">
			<input type="hidden" name="action" value="gw_erp_reset">
			<?php wp_nonce_field( 'gw_erp_reset' ); ?>
			<p><label>请输入 <code>RESET</code> 确认：<br><input name="gw_confirm" type="text" class="regular-text" placeholder="RESET"></label></p>
			<?php submit_button( '清空业务数据', 'delete', 'submit', false, array( 'class' => 'button button-danger' ) ); ?>
		</form>
	</div>
</div>
