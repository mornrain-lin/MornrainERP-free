<?php
/**
 * GitHub 模块中心
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$refresh_url = wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_refresh_modules' ), 'gw_erp_refresh_modules' );
?>
<div class="gw-erp-card gw-erp-modules-head">
	<h2>
		GitHub 仓库
		<a class="button button-primary gw-right" href="<?php echo esc_url( $refresh_url ); ?>">立即刷新</a>
	</h2>

	<?php if ( ! empty( $repo['ok'] ) ) : ?>
		<p>
			<strong><a href="<?php echo esc_url( $repo['html_url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $repo['full_name'] ); ?></a></strong>
			<?php if ( $repo['stars'] ) : ?>
				· ⭐ <?php echo esc_html( number_format_i18n( $repo['stars'] ) ); ?>
			<?php endif; ?>
			<?php if ( $repo['branch'] ) : ?>
				· 分支 <?php echo esc_html( $repo['branch'] ); ?>
			<?php endif; ?>
			<?php if ( $repo['pushed_at'] ) : ?>
				· 最近推送 <?php echo esc_html( $repo['pushed_at'] ); ?>
			<?php endif; ?>
		</p>
		<?php if ( $repo['desc'] ) : ?>
			<p class="description"><?php echo esc_html( $repo['desc'] ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p class="gw-neg">仓库信息获取失败：<?php echo esc_html( $repo['error'] ); ?></p>
	<?php endif; ?>

	<p class="description">
		数据源：<?php echo esc_html( $modules['source'] ); ?>
		· 抓取时间：<?php echo esc_html( $modules['fetched'] ); ?>
		<?php if ( null !== $repo['remaining'] ) : ?>
			· API 剩余配额：<?php echo esc_html( number_format_i18n( $repo['remaining'] ) ); ?> 次
		<?php endif; ?>
		· 缓存 12 小时
	</p>

	<?php if ( ! empty( $modules['error'] ) ) : ?>
		<div class="notice notice-warning inline"><p><?php echo esc_html( $modules['error'] ); ?></p></div>
	<?php endif; ?>
</div>

<div class="gw-erp-kpis">
	<div class="gw-kpi"><span class="gw-kpi-label">模块总数</span><strong><?php echo esc_html( number_format_i18n( $modules['stats']['total'] ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">已上线</span><strong class="gw-pos"><?php echo esc_html( number_format_i18n( $modules['stats']['ready'] ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">规划中</span><strong class="gw-neg"><?php echo esc_html( number_format_i18n( $modules['stats']['planned'] ) ); ?></strong></div>
</div>

<?php if ( ! empty( $modules['groups'] ) ) : ?>
	<div class="gw-erp-grid-2">
	<?php foreach ( $modules['groups'] as $g ) : ?>
		<div class="gw-erp-card">
			<h3><?php echo esc_html( $g['name'] ); ?>（<?php echo count( $g['items'] ); ?>）</h3>
			<table class="gw-erp-table gw-erp-table-compact">
				<thead><tr><th>模块</th><th>状态</th><th>权限点</th><th>路由</th></tr></thead>
				<tbody>
				<?php foreach ( $g['items'] as $it ) : ?>
					<tr>
						<td><?php echo esc_html( $it['name'] ); ?></td>
						<td>
							<span class="gw-badge gw-badge-<?php echo esc_attr( 'planned' === $it['status'] ? 'planned' : 'ready' ); ?>">
								<?php echo esc_html( 'planned' === $it['status'] ? '规划中' : '已上线' ); ?>
							</span>
						</td>
						<td><code><?php echo esc_html( $it['permission'] ); ?></code></td>
						<td><code><?php echo esc_html( $it['route'] ); ?></code></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endforeach; ?>
	</div>
<?php else : ?>
	<div class="gw-erp-card"><p>未能取得模块清单，请检查设置或稍后刷新。</p></div>
<?php endif; ?>

<?php if ( ! empty( $commits['items'] ) ) : ?>
<div class="gw-erp-card">
	<h2>最近提交</h2>
	<ul class="gw-commits">
		<?php foreach ( $commits['items'] as $c ) : ?>
		<li>
			<a href="<?php echo esc_url( $c['url'] ); ?>" target="_blank" rel="noopener"><code><?php echo esc_html( $c['sha'] ); ?></code></a>
			<?php echo esc_html( $c['message'] ); ?>
			<span class="description">— <?php echo esc_html( $c['author'] ); ?> · <?php echo esc_html( $c['date'] ); ?></span>
		</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>

<div class="gw-erp-card">
	<h2>连接说明</h2>
	<ul class="gw-ul">
		<li>本页只读调用 GitHub REST API：仓库信息、<code>/commits</code>、<code>docs/modules.json</code>（或 <code>config/modules.php</code>）。</li>
		<li>结果缓存 12 小时（transient），点击「立即刷新」可强制拉取最新。</li>
		<li>匿名访问配额 60 次/小时；需要更高配额请在「设置」里填写 Token，或在 wp-config.php 定义常量。</li>
		<li>前台可用 <code>[gangwan_erp_modules]</code> 展示同一份清单。</li>
	</ul>
</div>
