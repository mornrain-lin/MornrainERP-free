<?php
/**
 * 商品管理
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$edit_id  = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore
$search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore
$editing  = $edit_id ? GW_ERP_DB::get_product( $edit_id ) : null;
$products = GW_ERP_DB::get_products( $search );
?>
<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2><?php echo $editing ? '编辑商品' : '新增商品'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="product">
			<input type="hidden" name="gw[id]" value="<?php echo $editing ? esc_attr( $editing->id ) : ''; ?>">
			<?php wp_nonce_field( 'gw_erp_save_product' ); ?>

			<table class="form-table" role="presentation">
				<tr><th><label for="gw-sku">SKU *</label></th><td><input id="gw-sku" name="gw[sku]" type="text" class="regular-text" required value="<?php echo esc_attr( $editing ? $editing->sku : '' ); ?>"></td></tr>
				<tr><th><label for="gw-name">名称 *</label></th><td><input id="gw-name" name="gw[name]" type="text" class="regular-text" required value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>"></td></tr>
				<tr><th><label for="gw-category">分类</label></th><td><input id="gw-category" name="gw[category]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->category : '' ); ?>"></td></tr>
				<tr><th><label for="gw-sale">售价</label></th><td><input id="gw-sale" name="gw[sale_price]" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->sale_price : '0' ); ?>"></td></tr>
				<tr><th><label for="gw-cost">成本价</label></th><td><input id="gw-cost" name="gw[cost_price]" type="number" step="0.01" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->cost_price : '0' ); ?>"></td></tr>
				<tr><th><label for="gw-safety">安全库存</label></th><td><input id="gw-safety" name="gw[safety_stock]" type="number" step="1" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->safety_stock : '0' ); ?>"></td></tr>
				<tr><th><label for="gw-unit">单位</label></th><td><input id="gw-unit" name="gw[unit]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->unit : '件' ); ?>"></td></tr>
				<tr><th><label for="gw-status">状态</label></th><td>
					<select id="gw-status" name="gw[status]">
						<option value="active" <?php selected( $editing ? $editing->status : 'active', 'active' ); ?>>在售</option>
						<option value="inactive" <?php selected( $editing ? $editing->status : '', 'inactive' ); ?>>停售</option>
					</select>
				</td></tr>
			</table>
			<?php submit_button( $editing ? '保存修改' : '新增商品' ); ?>
			<?php if ( $editing ) : ?>
				<a class="button-link" href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-products' ) ); ?>">取消编辑</a>
			<?php endif; ?>
		</form>
	</div>

	<div class="gw-erp-card">
		<h2>商品列表（<?php echo count( $products ); ?>）</h2>
		<form method="get" class="gw-inline-form">
			<input type="hidden" name="page" value="gw-erp-products">
			<input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="搜索 SKU / 名称 / 分类">
			<button class="button">搜索</button>
			<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_export&gw_type=products' ), 'gw_erp_export' ) ); ?>">导出 CSV</a>
		</form>
		<table class="gw-erp-table">
			<thead><tr><th>SKU</th><th>名称</th><th>分类</th><th>售价</th><th>成本</th><th>库存</th><th>操作</th></tr></thead>
			<tbody>
			<?php if ( $products ) : ?>
				<?php foreach ( $products as $p ) : ?>
				<tr>
					<td><?php echo esc_html( $p->sku ); ?></td>
					<td><?php echo esc_html( $p->name ); ?></td>
					<td><?php echo esc_html( $p->category ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $p->sale_price ) ); ?></td>
					<td><?php echo esc_html( gw_erp_money( $p->cost_price ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( GW_ERP_DB::stock_total( $p->id ) ) ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-products&edit=' . (int) $p->id ) ); ?>">编辑</a>
						|
						<a class="gw-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_delete&gw_entity=product&id=' . (int) $p->id ), 'gw_erp_delete_product_' . (int) $p->id ) ); ?>" onclick="return confirm('确认删除该商品？')">删除</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="7">暂无商品。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
