<?php
/**
 * 经营报表：利润 / 月度趋势 / 渠道分布 / 导出
 *
 * @package GangwanERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$month  = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : current_time( 'Y-m' ); // phpcs:ignore
$months = array();
for ( $i = 0; $i < 12; $i++ ) {
	$months[] = gmdate( 'Y-m', strtotime( current_time( 'Y-m-01' ) . ' -' . $i . ' months' ) );
}

$summary = GW_ERP_DB::profit_summary( $month );
$all     = GW_ERP_DB::profit_summary();
$trend   = GW_ERP_DB::monthly_trend( 12 );
$channel = GW_ERP_DB::sales_by_channel();
$margin  = $summary->revenue > 0 ? round( $summary->profit / $summary->revenue * 100, 1 ) : 0;
?>
<div class="gw-erp-card">
	<form method="get" class="gw-inline-form">
		<input type="hidden" name="page" value="gw-erp-reports">
		<label>统计月份
			<select name="month">
				<?php foreach ( $months as $m ) : ?>
				<option value="<?php echo esc_attr( $m ); ?>" <?php selected( $month, $m ); ?>><?php echo esc_html( $m ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<button class="button">查看</button>
	</form>
</div>

<div class="gw-erp-kpis">
	<div class="gw-kpi"><span class="gw-kpi-label"><?php echo esc_html( $month ); ?> 营收</span><strong><?php echo esc_html( gw_erp_money( $summary->revenue ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">成本</span><strong><?php echo esc_html( gw_erp_money( $summary->cost ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">平台费</span><strong><?php echo esc_html( gw_erp_money( $summary->fee ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">利润</span><strong class="<?php echo $summary->profit >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $summary->profit ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">毛利率</span><strong><?php echo esc_html( $margin ); ?>%</strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">订单数</span><strong><?php echo esc_html( number_format_i18n( $summary->cnt ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">累计营收</span><strong><?php echo esc_html( gw_erp_money( $all->revenue ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">累计利润</span><strong class="<?php echo $all->profit >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $all->profit ) ); ?></strong></div>
</div>

<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2>近 12 个月趋势</h2>
		<table class="gw-erp-table">
			<thead><tr><th>月份</th><th>订单数</th><th>营收</th><th>利润</th><th>毛利率</th></tr></thead>
			<tbody>
			<?php if ( $trend ) : ?>
				<?php foreach ( $trend as $t ) : ?>
				<tr>
					<td><?php echo esc_html( $t->ym ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $t->cnt ) ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $t->revenue ) ); ?></td>
					<td class="<?php echo $t->profit >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $t->profit ) ); ?></td>
					<td><?php echo esc_html( $t->revenue > 0 ? round( $t->profit / $t->revenue * 100, 1 ) : 0 ); ?>%</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="5">暂无数据。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>

	<div class="gw-erp-card">
		<h2>渠道贡献</h2>
		<table class="gw-erp-table">
			<thead><tr><th>渠道</th><th>订单</th><th>营收</th><th>利润</th></tr></thead>
			<tbody>
			<?php if ( $channel ) : ?>
				<?php foreach ( $channel as $c ) : ?>
				<tr>
					<td><?php echo esc_html( $c->channel ); ?></td>
					<td><?php echo esc_html( number_format_i18n( $c->cnt ) ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $c->revenue ) ); ?></td>
					<td class="<?php echo $c->profit >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $c->profit ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="4">暂无数据。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<div class="gw-erp-card">
	<h2>导出</h2>
	<p>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=orders' ), 'gw_erp_export' ) ); ?>">订单 CSV</a>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=products' ), 'gw_erp_export' ) ); ?>">商品 CSV</a>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=inventory' ), 'gw_erp_export' ) ); ?>">库存 CSV</a>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=flows' ), 'gw_erp_export' ) ); ?>">资金流水 CSV</a>
		<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=purchases' ), 'gw_erp_export' ) ); ?>">采购 CSV</a>
	</p>
</div>
