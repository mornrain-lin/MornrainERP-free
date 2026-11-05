<?php
/**
 * 订单管理
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$edit_id  = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore
$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : ''; // phpcs:ignore
$editing  = $edit_id ? GW_ERP_DB::get_order( $edit_id ) : null;
$orders   = GW_ERP_DB::get_orders( 100, array( 'status' => $status ) );
$statuses = gw_erp_order_statuses();
$products = GW_ERP_DB::get_products( '', 500 );
?>
<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2><?php echo $editing ? '编辑订单 ' . esc_html( $editing->order_no ) : '新增订单'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="order">
			<input type="hidden" name="gw[id]" value="<?php echo $editing ? esc_attr( $editing->id ) : ''; ?>">
			<?php wp_nonce_field( 'gw_erp_save_order' ); ?>

			<table class="form-table" role="presentation">
				<tr><th>订单号</th><td><input name="gw[order_no]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->order_no : '' ); ?>" placeholder="留空自动生成"></td></tr>
				<tr><th>客户</th><td><input name="gw[customer]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->customer : '' ); ?>"></td></tr>
				<tr><th>渠道</th><td><input name="gw[channel]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->channel : '' ); ?>" placeholder="amazon_us / shopee_my / …"></td></tr>
				<tr><th>状态</th><td>
					<select name="gw[status]">
						<?php foreach ( $statuses as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $editing ? $editing->status : 'pending', $k ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">置为「已发货 / 已完成」时按明细自动出库（仅一次）。</p>
				</td></tr>
				<tr><th>营收</th><td><input name="gw[revenue]" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->revenue : '0' ); ?>"></td></tr>
				<tr><th>成本</th><td><input name="gw[cost]" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->cost : '0' ); ?>"></td></tr>
				<tr><th>平台费</th><td><input name="gw[fee]" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->fee : '0' ); ?>"></td></tr>
				<tr><th>已收款</th><td><input name="gw[paid_amount]" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->paid_amount : '0' ); ?>"></td></tr>
				<tr><th>日期</th><td><input name="gw[order_date]" type="date" value="<?php echo esc_attr( $editing ? $editing->order_date : current_time( 'Y-m-d' ) ); ?>"></td></tr>
			</table>

			<h3>订单明细</h3>
			<table class="gw-erp-table gw-items-table" data-prefix="gw[items]">
				<thead><tr><th>商品</th><th>数量</th><th>单价</th><th>操作</th></tr></thead>
				<tbody>
				<?php
				$items = ( $editing && ! empty( $editing->items ) ) ? $editing->items : array( (object) array( 'product_id' => 0, 'qty' => 1, 'price' => 0 ) );
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
						<td><input type="number" step="0.01" name="gw[items][<?php echo esc_attr( $idx ); ?>][price]" value="<?php echo esc_attr( $it->price ); ?>"></td>
						<td><button type="button" class="button gw-remove-row">删除</button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button gw-add-row" data-table="gw-items-table">+ 增加明细行</button></p>

			<?php submit_button( $editing ? '保存订单' : '创建订单' ); ?>
			<?php if ( $editing ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-orders' ) ); ?>">取消编辑</a>
			<?php endif; ?>
		</form>
	</div>

	<div class="gw-erp-card">
		<h2>订单列表</h2>
		<p class="gw-filters">
			状态：
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-orders' ) ); ?>">全部</a>
			<?php foreach ( $statuses as $k => $label ) : ?>
				| <a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-orders&status=' . $k ) ); ?>"><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=orders' ), 'gw_erp_export' ) ); ?>">导出 CSV</a>
		</p>
		<table class="gw-erp-table">
			<thead><tr><th>订单号</th><th>客户</th><th>渠道</th><th>状态</th><th>营收</th><th>利润</th><th>日期</th><th>操作</th></tr></thead>
			<tbody>
			<?php if ( $orders ) : ?>
				<?php foreach ( $orders as $o ) : ?>
				<tr>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-orders&edit=' . (int) $o->id ) ); ?>"><?php echo esc_html( $o->order_no ); ?></a></td>
					<td><?php echo esc_html( $o->customer ); ?></td>
					<td><?php echo esc_html( $o->channel ); ?></td>
					<td><?php echo esc_html( isset( $statuses[ $o->status ] ) ? $statuses[ $o->status ] : $o->status ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $o->revenue ) ); ?></td>
					<td class="<?php echo $o->profit >= 0 ? 'gw-pos' : 'gw-neg'; ?>"><?php echo esc_html( gw_erp_money( $o->profit ) ); ?></td>
					<td><?php echo esc_html( $o->order_date ); ?></td>
					<td>
						<a class="gw-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_delete&gw_entity=order&id=' . (int) $o->id ), 'gw_erp_delete_order_' . (int) $o->id ) ); ?>" onclick="return confirm('确认删除该订单？')">删除</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="8">暂无订单。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
