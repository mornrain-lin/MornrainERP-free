<?php
/**
 * 概览看板
 *
 * @package GangwanERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$kpis   = GW_ERP_Dashboard::kpis();
$alerts = GW_ERP_Dashboard::alerts();
$recent = GW_ERP_DB::get_orders( 8 );
$low    = GW_ERP_DB::low_stock();
?>
<div class="gw-erp-kpis">
	<div class="gw-kpi"><span class="gw-kpi-label">本月销售额</span><strong><?php echo esc_html( gw_erp_money( $kpis['month_revenue'] ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">本月利润</span><strong class="<?php echo $kpis['month_profit'] >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $kpis['month_profit'] ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">毛利率</span><strong><?php echo esc_html( $kpis['month_margin'] ); ?>%</strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">本月订单</span><strong><?php echo esc_html( number_format_i18n( $kpis['month_orders'] ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">库存件数</span><strong><?php echo esc_html( number_format_i18n( $kpis['stock'] ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">库存货值</span><strong><?php echo esc_html( gw_erp_money( $kpis['stock_value'] ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">资金余额</span><strong><?php echo esc_html( gw_erp_money( $kpis['cash'] ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">应收 / 应付</span><strong><?php echo esc_html( gw_erp_money( $kpis['receivable'] ) ); ?> / <?php echo esc_html( gw_erp_money( $kpis['payable'] ) ); ?></strong></div>
</div>

<?php if ( $alerts ) : ?>
<div class="gw-erp-card">
	<h2>经营预警</h2>
	<ul class="gw-alerts">
		<?php foreach ( $alerts as $a ) : ?>
		<li class="gw-alert gw-alert-<?php echo esc_attr( $a['level'] ); ?>">
			<a href="<?php echo esc_url( $a['link'] ); ?>"><?php echo esc_html( $a['title'] ); ?></a>
		</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php endif; ?>

<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2>近期订单</h2>
		<table class="gw-erp-table">
			<thead><tr><th>订单号</th><th>渠道</th><th>状态</th><th>营收</th><th>利润</th></tr></thead>
			<tbody>
			<?php if ( $recent ) : ?>
				<?php foreach ( $recent as $o ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-orders&edit=' . (int) $o->id ) ); ?>"><?php echo esc_html( $o->order_no ); ?></a></td>
					<td><?php echo esc_html( $o->channel ); ?></td>
					<td><?php echo esc_html( GW_ERP_Shortcode::order_status_label( $o->status ) ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $o->revenue ) ); ?></td>
					<td class="<?php echo $o->profit >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $o->profit ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="5">暂无订单，去「订单管理」录入第一张。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div class="gw-erp-card">
		<h2>低库存提醒</h2>
		<table class="gw-erp-table">
			<thead><tr><th>SKU</th><th>商品</th><th>现存</th><th>安全库存</th></tr></thead>
			<tbody>
			<?php if ( $low ) : ?>
				<?php foreach ( array_slice( $low, 0, 10 ) as $p ) : ?>
				<tr>
					<td><?php echo esc_html( $p->sku ); ?></td>
					<td><?php echo esc_html( $p->name ); ?></td>
					<td class="gw-neg"><?php echo esc_html( $p->total ); ?></td>
					<td><?php echo esc_html( $p->safety_stock ); ?></td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="4">库存健康，无预警。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="gw-erp-card">
	<h2>渠道分布</h2>
	<table class="gw-erp-table">
		<thead><tr><th>渠道</th><th>订单数</th><th>销售额</th><th>利润</th></tr></thead>
		<tbody>
		<?php $channels = GW_ERP_DB::sales_by_channel(); ?>
		<?php if ( $channels ) : ?>
			<?php foreach ( $channels as $c ) : ?>
			<tr>
				<td><?php echo esc_html( $c->channel ); ?></td>
				<td><?php echo esc_html( number_format_i18n( $c->cnt ) ); ?></td>
				<td><?php echo esc_html( gw_erp_money( $c->revenue ) ); ?></td>
				<td class="<?php echo $c->profit >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $c->profit ) ); ?></td>
			</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="4">暂无渠道数据。</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>
