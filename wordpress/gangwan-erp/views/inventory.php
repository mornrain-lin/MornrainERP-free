<?php
/**
 * 库存与流水：分仓库存 / 出入库流水 / 手工调整 / 调拨 / 盘点
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$rows        = GW_ERP_DB::get_inventory_rows();
$movements   = GW_ERP_DB::get_movements( 50 );
$warehouses  = GW_ERP_DB::get_warehouses();
$products    = GW_ERP_DB::get_products( '', 500 );
$types       = GW_ERP_DB::movement_types();
$filter_type = isset( $_GET['type'] ) ? sanitize_key( $_GET['type'] ) : ''; // phpcs:ignore
if ( $filter_type ) {
	$movements = GW_ERP_DB::get_movements( 50, array( 'type' => $filter_type ) );
}
?>
<div class="gw-erp-card">
	<h2>分仓库存</h2>
	<table class="gw-erp-table">
		<thead><tr><th>SKU</th><th>商品</th><th>仓库</th><th>数量</th><th>安全库存</th><th>货值</th></tr></thead>
		<tbody>
		<?php if ( $rows ) : ?>
			<?php foreach ( $rows as $r ) : ?>
			<tr>
				<td><?php echo esc_html( $r->sku ); ?></td>
				<td><?php echo esc_html( $r->name ); ?></td>
				<td><?php echo esc_html( $r->wh_name ); ?></td>
				<td class="<?php echo ( $r->safety_stock && $r->qty <= $r->safety_stock ) ? 'gw-neg' : ''; ?>"><?php echo esc_html( number_format_i18n( $r->qty ) ); ?></td>
				<td><?php echo esc_html( number_format_i18n( $r->safety_stock ) ); ?></td>
				<td><?php echo esc_html( gw_erp_money( $r->qty * $r->cost_price ) ); ?></td>
			</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="6">暂无库存记录。可在左侧新增商品后，用「其他入库」或采购入库补货。</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
	<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=inventory' ), 'gw_erp_export' ) ); ?>">导出库存 CSV</a></p>
</div>

<div class="gw-erp-grid-3">
	<div class="gw-erp-card">
		<h2>其他出入库</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="adjust">
			<?php wp_nonce_field( 'gw_erp_save_adjust' ); ?>
			<p><label>商品<br>
				<select name="gw[product_id]" required>
					<?php foreach ( $products as $p ) : ?>
					<option value="<?php echo esc_attr( $p->id ); ?>"><?php echo esc_html( $p->sku . ' · ' . $p->name ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>仓库<br>
				<select name="gw[warehouse_id]" required>
					<?php foreach ( $warehouses as $w ) : ?>
					<option value="<?php echo esc_attr( $w->id ); ?>"><?php echo esc_html( $w->name ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>数量（正数入库 / 负数出库）<br><input type="number" step="1" name="gw[qty]" required></label></p>
			<p><label>备注<br><input type="text" name="gw[note]" class="regular-text" placeholder="如：样品领用"></label></p>
			<?php submit_button( '提交出入库' ); ?>
		</form>
		<p class="description">所有库存变动都会写入流水，禁止直接改库存数字。</p>
	</div>

	<div class="gw-erp-card">
		<h2>仓库调拨</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="transfer">
			<?php wp_nonce_field( 'gw_erp_save_transfer' ); ?>
			<p><label>商品<br>
				<select name="gw[product_id]" required>
					<?php foreach ( $products as $p ) : ?>
					<option value="<?php echo esc_attr( $p->id ); ?>"><?php echo esc_html( $p->sku . ' · ' . $p->name ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>调出仓库<br>
				<select name="gw[from_warehouse]" required>
					<?php foreach ( $warehouses as $w ) : ?>
					<option value="<?php echo esc_attr( $w->id ); ?>"><?php echo esc_html( $w->name ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>调入仓库<br>
				<select name="gw[to_warehouse]" required>
					<?php foreach ( $warehouses as $w ) : ?>
					<option value="<?php echo esc_attr( $w->id ); ?>"><?php echo esc_html( $w->name ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>数量<br><input type="number" step="1" name="gw[qty]" required></label></p>
			<?php submit_button( '执行调拨' ); ?>
		</form>
	</div>

	<div class="gw-erp-card">
		<h2>库存盘点</h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="stocktake">
			<?php wp_nonce_field( 'gw_erp_save_stocktake' ); ?>
			<p><label>商品<br>
				<select name="gw[product_id]" required>
					<?php foreach ( $products as $p ) : ?>
					<option value="<?php echo esc_attr( $p->id ); ?>"><?php echo esc_html( $p->sku . ' · ' . $p->name ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>仓库<br>
				<select name="gw[warehouse_id]" required>
					<?php foreach ( $warehouses as $w ) : ?>
					<option value="<?php echo esc_attr( $w->id ); ?>"><?php echo esc_html( $w->name ); ?></option>
					<?php endforeach; ?>
				</select></label></p>
			<p><label>实盘数量<br><input type="number" step="1" name="gw[qty]" required></label></p>
			<?php submit_button( '保存盘点（差额自动入流水）' ); ?>
		</form>
	</div>
</div>

<div class="gw-erp-card">
	<h2>库存流水（最近 50 条）</h2>
	<p class="gw-filters">
		类型筛选：
		<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-inventory' ) ); ?>">全部</a>
		<?php foreach ( $types as $k => $label ) : ?>
			| <a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-inventory&type=' . $k ) ); ?>"><?php echo esc_html( $label ); ?></a>
		<?php endforeach; ?>
	</p>
	<table class="gw-erp-table">
		<thead><tr><th>时间</th><th>SKU</th><th>商品</th><th>仓库</th><th>类型</th><th>数量</th><th>关联</th><th>备注</th></tr></thead>
		<tbody>
		<?php if ( $movements ) : ?>
			<?php foreach ( $movements as $m ) : ?>
			<tr>
				<td><?php echo esc_html( $m->created_at ); ?></td>
				<td><?php echo esc_html( $m->sku ); ?></td>
				<td><?php echo esc_html( $m->name ); ?></td>
				<td><?php echo esc_html( $m->wh_name ); ?></td>
				<td><?php echo esc_html( isset( $types[ $m->type ] ) ? $types[ $m->type ] : $m->type ); ?></td>
				<td class="<?php echo $m->qty >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( number_format_i18n( $m->qty ) ); ?></td>
				<td><?php echo esc_html( $m->related_type . ( $m->related_id ? '#' . $m->related_id : '' ) ); ?></td>
				<td><?php echo esc_html( $m->note ); ?></td>
			</tr>
			<?php endforeach; ?>
		<?php else : ?>
			<tr><td colspan="8">暂无流水。</td></tr>
		<?php endif; ?>
		</tbody>
	</table>
</div>
