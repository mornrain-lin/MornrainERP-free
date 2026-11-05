<?php
/**
 * 客户与供应商
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$edit_id  = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore
$editing  = null;
$all      = GW_ERP_DB::get_partners();
if ( $edit_id ) {
	foreach ( $all as $p ) {
		if ( (int) $p->id === $edit_id ) {
			$editing = $p;
			break;
		}
	}
}
?>
<div class="gw-erp-grid-2">
	<div class="gw-erp-card">
		<h2><?php echo $editing ? '编辑往来单位' : '新增往来单位'; ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="gw_erp_save">
			<input type="hidden" name="gw_entity" value="partner">
			<input type="hidden" name="gw[id]" value="<?php echo $editing ? esc_attr( $editing->id ) : ''; ?>">
			<?php wp_nonce_field( 'gw_erp_save_partner' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>类型</th><td>
					<select name="gw[type]">
						<option value="customer" <?php selected( $editing ? $editing->type : 'customer', 'customer' ); ?>>客户</option>
						<option value="supplier" <?php selected( $editing ? $editing->type : '', 'supplier' ); ?>>供应商</option>
					</select>
				</td></tr>
				<tr><th>名称 *</th><td><input name="gw[name]" type="text" class="regular-text" required value="<?php echo esc_attr( $editing ? $editing->name : '' ); ?>"></td></tr>
				<tr><th>联系人</th><td><input name="gw[contact]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->contact : '' ); ?>"></td></tr>
				<tr><th>电话</th><td><input name="gw[phone]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->phone : '' ); ?>"></td></tr>
				<tr><th>邮箱</th><td><input name="gw[email]" type="email" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->email : '' ); ?>"></td></tr>
				<tr><th>地址</th><td><input name="gw[address]" type="text" class="regular-text" value="<?php echo esc_attr( $editing ? $editing->address : '' ); ?>"></td></tr>
			</table>
			<?php submit_button( $editing ? '保存修改' : '新增' ); ?>
			<?php if ( $editing ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-partners' ) ); ?>">取消编辑</a>
			<?php endif; ?>
		</form>
	</div>

	<div class="gw-erp-card">
		<h2>往来单位列表（<?php echo count( $all ); ?>）</h2>
		<table class="gw-erp-table">
			<thead><tr><th>名称</th><th>类型</th><th>联系人</th><th>电话</th><th>操作</th></tr></thead>
			<tbody>
			<?php if ( $all ) : ?>
				<?php foreach ( $all as $p ) : ?>
				<tr>
					<td><?php echo esc_html( $p->name ); ?></td>
					<td><?php echo esc_html( 'supplier' === $p->type ? '供应商' : '客户' ); ?></td>
					<td><?php echo esc_html( $p->contact ); ?></td>
					<td><?php echo esc_html( $p->phone ); ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=gw-erp-partners&edit=' . (int) $p->id ) ); ?>">编辑</a>
						|
						<a class="gw-danger" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=gw_erp_delete&gw_entity=partner&id=' . (int) $p->id ), 'gw_erp_delete_partner_' . (int) $p->id ) ); ?>" onclick="return confirm('确认删除？')">删除</a>
					</td>
				</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr><td colspan="5">暂无往来单位。</td></tr>
			<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>
