<?php
/**
 * 港湾ERP —— 数据表安装 / 升级 / 数据接管 / 重置
 *
 * 表前缀：{wp_}gw_。插件激活时若检测到主题遗留的 {wp_}cerp_ 表（旧版内嵌 ERP），
 * 会在 gw_ 表为空时做一次性数据接管，保证升级不丢数据。
 *
 * @package GangwanERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GW_ERP_Install {

	const OPTION_DB_VERSION = 'gw_erp_db_version';
	const OPTION_MIGRATED   = 'gw_erp_cerp_migrated';

	/**
	 * 激活：建表 + 接管旧数据 + 可选示例数据
	 */
	public static function activate() {
		self::create_tables();
		self::migrate_from_cerp();
		self::seed_sample_if_empty();
		self::seed_defaults();
		update_option( self::OPTION_DB_VERSION, GW_ERP_DB_VERSION );
		// 注册 CPT 后刷新一次重写规则，保证 /tools/ 与 /scenario/ 可访问
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * 停用：仅清缓存与重写规则，不删数据（数据删除交给 uninstall.php）
	 */
	public static function deactivate() {
		delete_transient( 'gw_erp_github_modules' );
		delete_transient( 'gw_erp_github_repo' );
		if ( function_exists( 'flush_rewrite_rules' ) ) {
			flush_rewrite_rules( false );
		}
	}

	/**
	 * 版本变化时幂等升级（每次请求最多判定一次，靠 option 与 transient 防抖）
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::OPTION_DB_VERSION, '' );
		if ( $installed === GW_ERP_DB_VERSION && get_transient( 'gw_erp_tables_ok' ) ) {
			return;
		}
		self::create_tables();
		if ( version_compare( (string) $installed, '1.1.0', '<' ) ) {
			self::migrate_from_cerp();
			self::seed_defaults();
		}
		update_option( self::OPTION_DB_VERSION, GW_ERP_DB_VERSION );
		set_transient( 'gw_erp_tables_ok', 1, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * 建表（dbDelta，幂等）
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'gw_';

		$sql = array();

		// 商品
		$sql[] = "CREATE TABLE {$p}products (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sku           VARCHAR(64)  NOT NULL,
			name          VARCHAR(255) NOT NULL,
			category      VARCHAR(64)  NOT NULL DEFAULT '',
			sale_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
			cost_price    DECIMAL(12,2) NOT NULL DEFAULT 0,
			safety_stock  INT(11)      NOT NULL DEFAULT 0,
			unit          VARCHAR(20)  NOT NULL DEFAULT '件',
			status        VARCHAR(20)  NOT NULL DEFAULT 'active',
			note          TEXT,
			created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY sku (sku),
			KEY category (category),
			KEY status (status)
		) $charset;";

		// 仓库
		$sql[] = "CREATE TABLE {$p}warehouses (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(120) NOT NULL,
			location   VARCHAR(120) NOT NULL DEFAULT '',
			is_default TINYINT(1)   NOT NULL DEFAULT 0,
			created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset;";

		// 库存（分仓）
		$sql[] = "CREATE TABLE {$p}inventory (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id   BIGINT(20) UNSIGNED NOT NULL,
			warehouse_id BIGINT(20) UNSIGNED NOT NULL,
			qty          INT(11)     NOT NULL DEFAULT 0,
			updated_at   DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY product_wh (product_id, warehouse_id),
			KEY product_id (product_id),
			KEY warehouse_id (warehouse_id)
		) $charset;";

		// 库存流水（库存变动唯一入口）
		$sql[] = "CREATE TABLE {$p}stock_movements (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id   BIGINT(20) UNSIGNED NOT NULL,
			warehouse_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			type         VARCHAR(32)  NOT NULL DEFAULT '',
			qty          INT(11)      NOT NULL DEFAULT 0,
			related_type VARCHAR(32)  NOT NULL DEFAULT '',
			related_id   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			note         VARCHAR(255) NOT NULL DEFAULT '',
			created_by   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY type (type),
			KEY related (related_type, related_id)
		) $charset;";

		// 往来单位（客户 / 供应商）
		$sql[] = "CREATE TABLE {$p}partners (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			type       VARCHAR(20)  NOT NULL DEFAULT 'customer',
			name       VARCHAR(160) NOT NULL,
			contact    VARCHAR(80)  NOT NULL DEFAULT '',
			phone      VARCHAR(40)  NOT NULL DEFAULT '',
			email      VARCHAR(120) NOT NULL DEFAULT '',
			address    VARCHAR(255) NOT NULL DEFAULT '',
			note       TEXT,
			created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY type (type)
		) $charset;";

		// 销售订单
		$sql[] = "CREATE TABLE {$p}orders (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_no    VARCHAR(64)  NOT NULL,
			customer    VARCHAR(160) NOT NULL DEFAULT '',
			partner_id  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			channel     VARCHAR(64)  NOT NULL DEFAULT '',
			status      VARCHAR(32)  NOT NULL DEFAULT 'pending',
			revenue     DECIMAL(12,2) NOT NULL DEFAULT 0,
			cost        DECIMAL(12,2) NOT NULL DEFAULT 0,
			fee         DECIMAL(12,2) NOT NULL DEFAULT 0,
			profit      DECIMAL(12,2) NOT NULL DEFAULT 0,
			paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
			order_date  DATE         NOT NULL DEFAULT '0000-00-00',
			note        TEXT,
			created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY order_no (order_no),
			KEY status (status),
			KEY order_date (order_date)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}order_items (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id   BIGINT(20) UNSIGNED NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			qty        INT(11)      NOT NULL DEFAULT 1,
			price      DECIMAL(12,2) NOT NULL DEFAULT 0,
			cost       DECIMAL(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY product_id (product_id)
		) $charset;";

		// 采购单
		$sql[] = "CREATE TABLE {$p}purchases (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			po_no         VARCHAR(64)  NOT NULL,
			supplier      VARCHAR(160) NOT NULL DEFAULT '',
			partner_id    BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			warehouse_id  BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			status        VARCHAR(32)  NOT NULL DEFAULT 'ordered',
			total_cost    DECIMAL(12,2) NOT NULL DEFAULT 0,
			paid_amount   DECIMAL(12,2) NOT NULL DEFAULT 0,
			purchase_date DATE         NOT NULL DEFAULT '0000-00-00',
			expected_at   DATE         NULL DEFAULT NULL,
			note          TEXT,
			created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY po_no (po_no),
			KEY status (status)
		) $charset;";

		$sql[] = "CREATE TABLE {$p}purchase_items (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			purchase_id BIGINT(20) UNSIGNED NOT NULL,
			product_id  BIGINT(20) UNSIGNED NOT NULL,
			qty         INT(11)      NOT NULL DEFAULT 0,
			unit_cost   DECIMAL(12,2) NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			KEY purchase_id (purchase_id),
			KEY product_id (product_id)
		) $charset;";

		// 资金账户
		$sql[] = "CREATE TABLE {$p}finance_accounts (
			id         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name       VARCHAR(120) NOT NULL,
			type       VARCHAR(20)  NOT NULL DEFAULT 'cash',
			balance    DECIMAL(12,2) NOT NULL DEFAULT 0,
			note       VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset;";

		// 收支流水
		$sql[] = "CREATE TABLE {$p}finance_flows (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			account_id   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			direction    VARCHAR(10)  NOT NULL DEFAULT 'in',
			category     VARCHAR(60)  NOT NULL DEFAULT '',
			amount       DECIMAL(12,2) NOT NULL DEFAULT 0,
			related_type VARCHAR(32)  NOT NULL DEFAULT '',
			related_id   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			remark       VARCHAR(255) NOT NULL DEFAULT '',
			occurred_at  DATE         NOT NULL DEFAULT '0000-00-00',
			created_by   BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY account_id (account_id),
			KEY occurred_at (occurred_at),
			KEY direction (direction)
		) $charset;";

		foreach ( $sql as $s ) {
			dbDelta( $s );
		}
	}

	/**
	 * 从主题遗留表 {prefix}cerp_* 接管数据（仅当 gw_ 主表为空且 cerp_ 存在时）
	 */
	public static function migrate_from_cerp() {
		global $wpdb;

		if ( get_option( self::OPTION_MIGRATED ) ) {
			return;
		}

		$old = $wpdb->prefix . 'cerp_';
		$old_products = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old . 'products' ) );
		if ( ! $old_products ) {
			update_option( self::OPTION_MIGRATED, current_time( 'mysql' ) );
			return;
		}

		$new_count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . gw_erp_tbl( 'products' ) );
		$old_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$old}products" );
		if ( $new_count > 0 || $old_count === 0 ) {
			update_option( self::OPTION_MIGRATED, current_time( 'mysql' ) );
			return;
		}

		$wpdb->query( 'START TRANSACTION' );

		// 商品（id 保持一致，便于明细表直接迁移）
		$wpdb->query( "INSERT INTO " . gw_erp_tbl( 'products' ) . "
			(id, sku, name, category, sale_price, cost_price, created_at)
			SELECT id, sku, name, category, sale_price, cost_price, created_at FROM {$old}products" );

		// 仓库
		$wpdb->query( "INSERT INTO " . gw_erp_tbl( 'warehouses' ) . "
			(id, name, location, created_at)
			SELECT id, name, location, created_at FROM {$old}warehouses" );

		// 库存
		$wpdb->query( "INSERT INTO " . gw_erp_tbl( 'inventory' ) . "
			(product_id, warehouse_id, qty)
			SELECT product_id, warehouse_id, qty FROM {$old}inventory" );

		// 订单 + 明细
		$wpdb->query( "INSERT INTO " . gw_erp_tbl( 'orders' ) . "
			(id, order_no, customer, channel, status, revenue, cost, fee, profit, order_date, created_at)
			SELECT id, order_no, customer, channel, status, revenue, cost, fee, profit, order_date, created_at FROM {$old}orders" );
		$wpdb->query( "INSERT INTO " . gw_erp_tbl( 'order_items' ) . "
			(order_id, product_id, qty, price)
			SELECT order_id, product_id, qty, price FROM {$old}order_items" );

		// 采购 + 明细
		$wpdb->query( "INSERT INTO " . gw_erp_tbl( 'purchases' ) . "
			(id, po_no, supplier, status, total_cost, purchase_date, created_at)
			SELECT id, po_no, supplier, status, total_cost, purchase_date, created_at FROM {$old}purchases" );
		$wpdb->query( "INSERT INTO " . gw_erp_tbl( 'purchase_items' ) . "
			(purchase_id, product_id, qty, unit_cost)
			SELECT purchase_id, product_id, qty, unit_cost FROM {$old}purchase_items" );

		$wpdb->query( 'COMMIT' );

		// 供应商补进往来单位
		$sups = $wpdb->get_results( "SELECT DISTINCT supplier FROM {$old}purchases WHERE supplier <> ''" );
		foreach ( $sups as $s ) {
			$wpdb->insert( gw_erp_tbl( 'partners' ), array(
				'type' => 'supplier',
				'name' => $s->supplier,
			) );
		}

		// 按现有库存补一条期初流水，保证库存流水有据可查
		$rows = $wpdb->get_results( 'SELECT product_id, warehouse_id, qty FROM ' . gw_erp_tbl( 'inventory' ) );
		$now  = current_time( 'mysql' );
		foreach ( $rows as $r ) {
			$wpdb->insert( gw_erp_tbl( 'stock_movements' ), array(
				'product_id'   => (int) $r->product_id,
				'warehouse_id' => (int) $r->warehouse_id,
				'type'         => 'opening',
				'qty'          => (int) $r->qty,
				'related_type' => 'migrate',
				'note'         => '主题遗留数据接管·期初库存',
				'created_at'   => $now,
			) );
		}

		update_option( self::OPTION_MIGRATED, current_time( 'mysql' ) );
	}

	/**
	 * 默认数据：默认仓库、默认资金账户
	 */
	public static function seed_defaults() {
		global $wpdb;

		$wh = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . gw_erp_tbl( 'warehouses' ) );
		if ( 0 === $wh ) {
			$wpdb->insert( gw_erp_tbl( 'warehouses' ), array(
				'name'       => '主仓库',
				'location'   => '',
				'is_default' => 1,
			) );
		}

		$acc = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . gw_erp_tbl( 'finance_accounts' ) );
		if ( 0 === $acc ) {
			$wpdb->insert( gw_erp_tbl( 'finance_accounts' ), array( 'name' => '现金账户', 'type' => 'cash' ) );
			$wpdb->insert( gw_erp_tbl( 'finance_accounts' ), array( 'name' => '对公银行账户', 'type' => 'bank' ) );
		}
	}

	/**
	 * 示例数据（仅在商品表为空时灌入，便于空站演示）
	 */
	public static function seed_sample_if_empty() {
		global $wpdb;

		$count = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . gw_erp_tbl( 'products' ) );
		if ( $count > 0 ) {
			return;
		}

		$products = array(
			array( 'GW-EARBUD-01', '无线蓝牙耳机',   '3C数码',   199.00, 65.00 ),
			array( 'GW-MAT-01',    '天然橡胶瑜伽垫', '运动户外', 89.00,  28.00 ),
			array( 'GW-CASE-01',   '液态硅胶手机壳', '3C数码',   39.00,  9.00  ),
			array( 'GW-RING-01',   '18寸直播补光灯', '摄影器材', 129.00, 42.00 ),
			array( 'GW-BRUSH-01',  '宠物去浮毛梳',   '宠物用品', 59.00,  18.00 ),
		);

		$pid = array();
		foreach ( $products as $pr ) {
			$wpdb->insert( gw_erp_tbl( 'products' ), array(
				'sku'        => $pr[0],
				'name'       => $pr[1],
				'category'   => $pr[2],
				'sale_price' => $pr[3],
				'cost_price' => $pr[4],
			), array( '%s', '%s', '%s', '%f', '%f' ) );
			$pid[ $pr[0] ] = $wpdb->insert_id;
		}

		$whs = array( '深圳仓', '义乌仓', '海外仓(美西)' );
		$wid = array();
		foreach ( $whs as $i => $w ) {
			$wpdb->insert( gw_erp_tbl( 'warehouses' ), array(
				'name'       => $w,
				'location'   => 'CN',
				'is_default' => 0 === $i ? 1 : 0,
			) );
			$wid[] = $wpdb->insert_id;
		}

		$stock = array(
			'GW-EARBUD-01' => array( 320, 150, 80 ),
			'GW-MAT-01'    => array( 140, 90, 30 ),
			'GW-CASE-01'   => array( 600, 400, 120 ),
			'GW-RING-01'   => array( 95, 60, 20 ),
			'GW-BRUSH-01'  => array( 210, 130, 45 ),
		);
		foreach ( $stock as $sku => $qtys ) {
			foreach ( $qtys as $i => $q ) {
				$wpdb->insert( gw_erp_tbl( 'inventory' ), array(
					'product_id'   => $pid[ $sku ],
					'warehouse_id' => $wid[ $i ],
					'qty'          => $q,
				), array( '%d', '%d', '%d' ) );
			}
		}

		$orders = array(
			array( 'GW-2026-0801', 'amazon_us',  'paid',    399.00, 130.00, 39.90, 'GW-EARBUD-01', 2, 199.00 ),
			array( 'GW-2026-0802', 'shopee_my',  'shipped', 178.00, 56.00,  17.80, 'GW-MAT-01',    2, 89.00  ),
			array( 'GW-2026-0803', 'shopify_cn', 'done',    390.00, 90.00,  19.50, 'GW-CASE-01',  10, 39.00  ),
		);
		foreach ( $orders as $o ) {
			$wpdb->insert( gw_erp_tbl( 'orders' ), array(
				'order_no'   => $o[0],
				'channel'    => $o[1],
				'status'     => $o[2],
				'revenue'    => $o[3],
				'cost'       => $o[4],
				'fee'        => $o[5],
				'profit'     => round( $o[3] - $o[4] - $o[5], 2 ),
				'order_date' => current_time( 'Y-m' ) . '-01',
			) );
			$oid = $wpdb->insert_id;
			$wpdb->insert( gw_erp_tbl( 'order_items' ), array(
				'order_id'   => $oid,
				'product_id' => $pid[ $o[6] ],
				'qty'        => $o[7],
				'price'      => $o[8],
			) );
		}
	}

	/**
	 * 重置业务数据（保留表结构）
	 */
	public static function reset_data() {
		global $wpdb;
		$tables = array(
			'finance_flows', 'finance_accounts', 'purchase_items', 'purchases',
			'order_items', 'orders', 'stock_movements', 'inventory',
			'warehouses', 'partners', 'products',
		);
		foreach ( $tables as $t ) {
			$wpdb->query( 'DELETE FROM ' . gw_erp_tbl( $t ) );
		}
		self::seed_defaults();
	}
}
