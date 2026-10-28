<?php
/**
 * 采购管理
 *
 * @package GangwanERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$edit_id  = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore
$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore
$editing  = $edit_id ? GW_ERP_DB::get_purchase( $edit_id ) : null;
$purchases = GW_ERP_DB::get_purchases( 100, array( 'status' => $status ) );
$statuses  = gw_erp_purchase_statuses();
$products  = GW_ERP_DB::get_products( '', 500 );
$warehouses = GW_ERP_DB::get_warehouses();
$suppliers = GW_ERP_DB::get_partners( 'supplier' );
?>
<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2><?php echo $editing ? '编辑采购单 ' . esc_html( $editing->po_no ) : '新增采购单'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="purchase">
			<input type="hidden" name="gw[id]" value="<?php echo $editing ? esc_attr( $editing->id ) : ''; ?>">
			<?php wp_nonce_field( 'gw_erp_save_purchase' ); ?>

			<table class="form-table" role="presentation">
				<tr><th>采购单号</th><td><input name="gw[po_no]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->po_no : '' ); ?>" placeholder="留空自动生成"></td></tr>
				<tr><th>供应商</th><td><input name="gw[supplier]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->supplier : '' ); ?>" list="gw-supplier-list">
					<datalist id="gw-supplier-list">
						<?php foreach ( $suppliers as $s ) : ?>
						<option value="<?php echo esc_attr( $s->name ); ?>"></option>
						<?php endforeach; ?>
					</datalist>
				</td></tr>
				<tr><th>入库仓库</th><td>
					<select name="gw[warehouse_id]">
						<?php foreach ( $warehouses as $w ) : ?>
						<option value="<?php echo esc_attr( $w->id ); ?>" <?php selected( $editing ? $editing->warehouse_id : GW_ERP_DB::default_warehouse_id(), $w->id ); ?>><?php echo esc_html( $w->name ); ?></option>
						<?php endforeach; ?>
					</select>
				</td></tr>
				<tr><th>状态</th><td>
					<select name="gw[status]">
						<?php foreach ( $statuses as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $editing ? $editing->status : 'ordered', $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">置为「已入库」时按明细自动入库（仅一次）。</p>
				</td></tr>
				<tr><th>已付款</th><td><input name="gw[paid_amount]" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->paid_amount : '0' ); ?>"></td></tr>
				<tr><th>采购日期</th><td><input name="gw[purchase_date]" type="date" value="<?php echo esc_attr( $editing ? $editing->purchase_date : current_time( 'Y-m-d' ) ); ?>"></td></tr>
				<tr><th>预计到货</th><td><input name="gw[expected_at]" type="date" value="<?php echo esc_attr( $editing ? $editing->expected_at : '' ); ?>"></td></tr>
			</table>

			<h3>采购明细</h3>
			<table class="gw-erp-table gw-items-table">
				<thead><tr><th>商品</th><th>数量</th><th>单价</th><th>操作</th></tr></thead>
				<tbody>
				<?php
				$items = ( $editing && ! empty( $editing->items ) ) ? $editing->items : array( (object) array( 'product_id' => 0, 'qty' => 1, 'unit_cost' => 0 ) );
				foreach ( $items as $i => $it ) :
					$idx = (int) $i;
					?>
					<tr>
						<td>
							<select name="gw[items][<?php echo esc_attr( $idx ); ?>][product_id]">
								<?php foreach ( $products as $p ) : ?>
								<option value="<?php echo esc_attr( $p->id ); ?>" <?php selected( $it->product_id, $p->id ); ?>><?php echo esc_html( $p->sku . ' · ' . $p->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><input type="number" step="1" name="gw[items][<?php echo esc_attr( $idx ); ?>][qty]" value="<?php echo esc_attr( $it->qty ); ?>"></td>
						<td><input type="number" step="0.01" name="gw[items][<?php echo esc_attr( $idx ); ?>][unit_cost]" value="<?php echo esc_attr( $it->unit_cost ); ?>"></td>
						<td><button type="button" class="button gw-remove-row">删除</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button gw-add-row">+ 增加明细行</button></p>

			<?php submit_button( $editing ? '保存采购单' : '创建采购单' ); ?>
			<?php if ( $editing ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-purchases' ) ); ?>">取消编辑</a>
			<?php endif; ?>
		</form>
	</div>

	<div class="gw-erp-card">
		<h2>采购列表</h2>
		<p class="gw-filters">
			状态：
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-purchases' ) ); ?>">全部</a>
			<?php foreach ( $statuses as $k => $label ) : ?>
				| <a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-purchases&status=' . $k ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=purchases' ), 'gw_erp_export' ) ); ?>">导出 CSV</a>
		</p>
		<table class="gw-erp-table">
			<thead><tr><th>单号</th><th>供应商</th><th>状态</th><th>金额</th><th>已付</th><th>日期</th><th>操作</th></tr></thead>
			<tbody>
			<?php if ( $purchases ) : ?>
				<?php foreach ( $purchases as $p ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-purchases&edit=' . (int) $p->id ) ); ?>"><?php echo esc_html( $p->po_no ); ?></a></td>
					<td><?php echo esc_html( $p->supplier ); ?></td>
					<td><?php echo esc_html( isset( $statuses[ $p->status ] ) ? $statuses[ $p->status ] : $p->status ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $p->total_cost ) ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $p->paid_amount ) ); ?></td>
					<td><?php echo esc_html( $p->purchase_date ); ?></td>
					<td>
						<a class="gw-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_delete&gw_entity=purchase&id=' . (int) $p->id ), 'gw_erp_delete_purchase_' . (int) $p->id ) ); ?>" onclick="return confirm('确认删除该采购单？')">删除</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="7">暂无采购单。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
