<?php
/**
 * 财务资金：账户 / 收支流水 / 应收应付
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$accounts    = GW_ERP_DB::get_accounts();
$flows       = GW_ERP_DB::get_flows( 80 );
$receivables = GW_ERP_DB::receivables();
$payables    = GW_ERP_DB::payables();
$total_in    = 0.0;
$total_out   = 0.0;
foreach ( $flows as $f ) {
	if ( 'in' === $f->direction ) {
		$total_in += (float) $f->amount;
	} else {
		$total_out += (float) $f->amount;
	}
}
$cash_total = 0.0;
foreach ( $accounts as $a ) {
	$cash_total += (float) $a->balance;
}
?>
<div class="gw-erp-kpis">
	<div class="gw-kpi"><span class="gw-kpi-label">账户总余额</span><strong><?php echo esc_html( gw_erp_money( $cash_total ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">流水收入</span><strong class="gw-pos"><?php echo esc_html( gw_erp_money( $total_in ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">流水支出</span><strong class="gw-neg"><?php echo esc_html( gw_erp_money( $total_out ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">应收未收</span><strong><?php echo esc_html( gw_erp_money( array_sum( array_map( function ( $o ) { return (float) $o->revenue - (float) $o->paid_amount; }, $receivables ) ) ) ); ?></strong></div>
	<div class="gw-kpi"><span class="gw-kpi-label">应付未付</span><strong><?php echo esc_html( gw_erp_money( array_sum( array_map( function ( $p ) { return (float) $p->total_cost - (float) $p->paid_amount; }, $payables ) ) ) ); ?></strong></div>
</div>

<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2>资金账户</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="gw-inline-form">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="account">
			<?php wp_nonce_field( 'gw_erp_save_account' ); ?>
			<input type="text" name="gw[name]" placeholder="账户名称" required>
			<select name="gw[type]">
				<option value="cash">现金</option>
				<option value="bank">银行</option>
				<option value="other">其他</option>
			</select>
			<button class="button">新增账户</button>
		</form>
		<table class="gw-erp-table">
			<thead><tr><th>账户</th><th>类型</th><th>余额</th></tr></thead>
			<tbody>
			<?php if ( $accounts ) : ?>
				<?php foreach ( $accounts as $a ) : ?>
				<tr>
					<td><?php echo esc_html( $a->name ); ?></td>
					<td><?php echo esc_html( 'bank' === $a->type ? '银行' : ( 'cash' === $a->type ? '现金' : '其他' ) ); ?></td>
					<td class="<?php echo $a->balance >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $a->balance ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="3">暂无账户。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
		<p class="description">余额由收支流水自动加减，不手工填写。</p>
	</div>

	<div class="gw-erp-card">
		<h2>记一笔收支</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="flow">
			<?php wp_nonce_field( 'gw_erp_save_flow' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>账户</th><td>
					<select name="gw[account_id]" required>
						<?php foreach ( $accounts as $a ) : ?>
						<option value="<?php echo esc_attr( $a->id ); ?>"><?php echo esc_html( $a->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td></tr>
				<tr><th>方向</th><td>
					<select name="gw[direction]">
						<option value="in">收入</option>
						<option value="out">支出</option>
					</select>
				</td></tr>
				<tr><th>类别</th><td><input name="gw[category]" type="text" class="regular-text" placeholder="销售回款 / 采购付款 / 广告费等"></td></tr>
				<tr><th>金额</th><td><input name="gw[amount]" type="number" step="0.01" class="regular-text" required></td></tr>
				<tr><th>日期</th><td><input name="gw[occurred_at]" type="date" value="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>"></td></tr>
				<tr><th>备注</th><td><input name="gw[remark]" type="text" class="regular-text"></td></tr>
			</table>
			<?php submit_button( '保存流水' ); ?>
		</form>
	</div>
</div>

<div class="gw-erp-card">
	<h2>收支流水（最近 80 条）</h2>
	<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=flows' ), 'gw_erp_export' ) ); ?>">导出 CSV</a></p>
	<table class="gw-erp-table">
		<thead><tr><th>日期</th><th>账户</th><th>方向</th><th>类别</th><th>金额</th><th>备注</th><th>操作</th></tr></thead>
		<tbody>
		<?php if ( $flows ) : ?>
			<?php foreach ( $flows as $f ) : ?>
			<tr>
				<td><?php echo esc_html( $f->occurred_at ); ?></td>
				<td><?php echo esc_html( $f->account_name ); ?></td>
				<td><?php echo esc_html( 'in' === $f->direction ? '收入' : '支出' ); ?></td>
				<td><?php echo esc_html( $f->category ); ?></td>
				<td class="<?php echo 'in' === $f->direction ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $f->amount ) ); ?></td>
				<td><?php echo esc_html( $f->remark ); ?></td>
				<td><a class="gw-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_delete&gw_entity=flow&id=' . (int) $f->id ), 'gw_erp_delete_flow_' . (int) $f->id ) ); ?>" onclick="return confirm('删除该流水会同时冲销账户余额，确认？')">删除</a></td>
			</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="7">暂无流水。</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>

<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2>应收未收（<?php echo count( $receivables ); ?>）</h2>
		<table class="gw-erp-table">
			<thead><tr><th>订单号</th><th>应收</th><th>已收</th><th>差额</th></tr></thead>
			<tbody>
			<?php if ( $receivables ) : ?>
				<?php foreach ( array_slice( $receivables, 0, 10 ) as $o ) : ?>
				<tr>
					<td><?php echo esc_html( $o->order_no ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $o->revenue ) ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $o->paid_amount ) ); ?></td>
					<td class="gw-neg"><?php echo esc_html( gw_erp_money( $o->revenue - $o->paid_amount ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="4">无应收。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
	<div class="gw-erp-card">
		<h2>应付未付（<?php echo count( $payables ); ?>）</h2>
		<table class="gw-erp-table">
			<thead><tr><th>采购单</th><th>应付</th><th>已付</th><th>差额</th></tr></thead>
			<tbody>
			<?php if ( $payables ) : ?>
				<?php foreach ( array_slice( $payables, 0, 10 ) as $p ) : ?>
				<tr>
					<td><?php echo esc_html( $p->po_no ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $p->total_cost ) ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $p->paid_amount ) ); ?></td>
					<td class="gw-neg"><?php echo esc_html( gw_erp_money( $p->total_cost - $p->paid_amount ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="4">无应付。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
