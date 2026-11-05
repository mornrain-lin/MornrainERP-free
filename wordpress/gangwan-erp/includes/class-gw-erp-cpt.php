<?php
/**
 * 晨雨ERP —— 内容层：ERP工具（tool）+ 使用场景（scenario）
 *
 * 从主题 inc/cpt.php 接管而来。为兼容主题既有模板（archive-tool.php / single-tool.php /
 * taxonomy-scenario.php / inc/cleanup.php），本文件同时提供与主题同名的全局函数；
 * 这些函数均以 function_exists 包裹，主题侧加载器加同样的守卫即可安全停用自带版本。
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GW_ERP_CPT {

	public static function init() {
		// 优先级 9：早于 WP 核心 sitemaps（init 默认 10），保证被站点地图收录
		add_action( 'init', array( __CLASS__, 'register_tool_cpt' ), 9 );
		add_action( 'init', array( __CLASS__, 'register_scenario_tax' ), 9 );

		add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_box' ) );
		add_action( 'save_post', array( __CLASS__, 'save_meta' ) );

		// 列表列：让「ERP工具」后台列表能直接看到收费类型与官网
		add_filter( 'manage_tool_posts_columns', array( __CLASS__, 'list_columns' ) );
		add_action( 'manage_tool_posts_custom_column', array( __CLASS__, 'list_column_render' ), 10, 2 );
	}

	/* ---------------- 注册 ---------------- */

	public static function register_tool_cpt() {
		if ( post_type_exists( 'tool' ) ) {
			return;
		}

		$labels = array(
			'name'               => _x( 'ERP工具', 'post type general name', 'gangwan-erp' ),
			'singular_name'      => _x( 'ERP工具', 'post type singular name', 'gangwan-erp' ),
			'menu_name'          => _x( 'ERP工具', 'admin menu', 'gangwan-erp' ),
			'name_admin_bar'     => _x( 'ERP工具', 'add new on admin bar', 'gangwan-erp' ),
			'add_new'            => _x( '添加工具', 'tool', 'gangwan-erp' ),
			'add_new_item'       => __( '添加新工具', 'gangwan-erp' ),
			'edit_item'          => __( '编辑工具', 'gangwan-erp' ),
			'new_item'           => __( '新工具', 'gangwan-erp' ),
			'all_items'          => __( '全部工具', 'gangwan-erp' ),
			'view_item'          => __( '查看工具', 'gangwan-erp' ),
			'search_items'       => __( '搜索工具', 'gangwan-erp' ),
			'not_found'          => __( '未找到工具', 'gangwan-erp' ),
			'not_found_in_trash' => __( '回收站中未找到', 'gangwan-erp' ),
		);

		$args = array(
			'labels'             => $labels,
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array( 'slug' => 'tool', 'with_front' => false ),
			'capability_type'    => 'post',
			'has_archive'        => 'tools',
			'hierarchical'       => false,
			'menu_position'      => 20,
			'menu_icon'          => 'dashicons-admin-tools',
			'supports'           => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields' ),
			'show_in_rest'       => true,
		);

		register_post_type( 'tool', $args );
	}

	public static function register_scenario_tax() {
		if ( taxonomy_exists( 'scenario' ) ) {
			return;
		}

		$labels = array(
			'name'          => _x( '使用场景', 'taxonomy general name', 'gangwan-erp' ),
			'singular_name' => _x( '使用场景', 'taxonomy singular name', 'gangwan-erp' ),
			'search_items'  => __( '搜索使用场景', 'gangwan-erp' ),
			'all_items'     => __( '全部使用场景', 'gangwan-erp' ),
			'edit_item'     => __( '编辑使用场景', 'gangwan-erp' ),
			'update_item'   => __( '更新使用场景', 'gangwan-erp' ),
			'add_new_item'  => __( '添加使用场景', 'gangwan-erp' ),
			'new_item_name' => __( '新使用场景名', 'gangwan-erp' ),
			'menu_name'     => __( '使用场景', 'gangwan-erp' ),
		);

		register_taxonomy( 'scenario', array( 'tool', 'post' ), array(
			'hierarchical'      => true,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'scenario', 'with_front' => false ),
			'show_in_rest'      => true,
		) );
	}

	/* ---------------- 元字段 ---------------- */

	public static function meta_fields() {
		return array(
			'one_liner'    => array( 'label' => '一句话简介',   'type' => 'text',     'full' => true,  'desc' => '一句话说明这个工具是什么，用于卡片与 SEO。' ),
			'for_whom'     => array( 'label' => '适合人群',     'type' => 'text',     'full' => true,  'desc' => '例如：刚起步、订单量不大的跨境小卖家。' ),
			'price'        => array( 'label' => '价格概览',     'type' => 'text',     'full' => false, 'desc' => '例如：免费起步 / 按模块订阅。' ),
			'type'         => array( 'label' => '收费类型',     'type' => 'select',   'full' => false, 'options' => array( 'free' => '免费/免费版', 'paid' => '付费' ), 'desc' => '用于 SoftwareApplication 结构化数据。' ),
			'official_url' => array( 'label' => '官网链接',     'type' => 'url',      'full' => false, 'desc' => '工具官方网站。' ),
			'pricing'      => array( 'label' => '定价方案',     'type' => 'textarea', 'full' => true,  'desc' => "每行一档，用竖线分隔三段：版本｜价格｜包含内容。例：免费版｜0 元｜3 店铺、基础订单与库存。" ),
			'modules'      => array( 'label' => '功能模块详解', 'type' => 'textarea', 'full' => true,  'desc' => "每行一个模块，用竖线分隔两段：模块名｜能力说明。例：订单管理｜多平台订单自动下载、审核、合并与发货。" ),
			'scenarios'    => array( 'label' => '适用场景',     'type' => 'textarea', 'full' => true,  'desc' => "每行一个业务场景，用竖线分隔两段：场景名｜说明。" ),
			'not_for'      => array( 'label' => '不适合的情况', 'type' => 'textarea', 'full' => true,  'desc' => '每行一条，说明哪些阶段或需求下不建议选它。' ),
			'scale'        => array( 'label' => '适用规模',     'type' => 'text',     'full' => false, 'desc' => '例如：日单量 0–300 单 / 1–5 人团队。' ),
			'trial'        => array( 'label' => '试用与上手',   'type' => 'text',     'full' => false, 'desc' => '例如：注册即用，1–2 天可跑通基础流程。' ),
			'features'     => array( 'label' => '核心功能速览', 'type' => 'textarea', 'full' => true,  'desc' => '每行一条短句。' ),
			'pros'         => array( 'label' => '优点',         'type' => 'textarea', 'full' => true,  'desc' => '每行一条。' ),
			'cons'         => array( 'label' => '不足',         'type' => 'textarea', 'full' => true,  'desc' => '每行一条，客观呈现。' ),
			'advice'       => array( 'label' => '使用建议',     'type' => 'textarea', 'full' => true,  'desc' => '给读者的选型建议。' ),
		);
	}

	public static function add_meta_box() {
		add_meta_box(
			'gangwan_tool_meta',
			__( '工具信息（晨雨ERP 插件）', 'gangwan-erp' ),
			array( __CLASS__, 'render_meta_box' ),
			'tool',
			'normal',
			'high'
		);
	}

	public static function render_meta_box( $post ) {
		wp_nonce_field( 'gangwan_tool_meta_save', 'gangwan_tool_meta_nonce' );
		$fields = self::meta_fields();
		echo '<div class="gw-tool-meta">';
		foreach ( $fields as $key => $f ) {
			$val = get_post_meta( $post->ID, '_tool_' . $key, true );
			$cls = ! empty( $f['full'] ) ? 'full' : '';
			echo '<p class="gw-tool-meta-field ' . esc_attr( $cls ) . '">';
			echo '<label for="tool_' . esc_attr( $key ) . '"><strong>' . esc_html( $f['label'] ) . '</strong></label>';
			if ( 'textarea' === $f['type'] ) {
				echo '<textarea id="tool_' . esc_attr( $key ) . '" name="tool_' . esc_attr( $key ) . '" rows="4">' . esc_textarea( $val ) . '</textarea>';
			} elseif ( 'select' === $f['type'] ) {
				echo '<select id="tool_' . esc_attr( $key ) . '" name="tool_' . esc_attr( $key ) . '">';
				foreach ( $f['options'] as $ov => $ol ) {
					echo '<option value="' . esc_attr( $ov ) . '"' . selected( $val, $ov, false ) . '>' . esc_html( $ol ) . '</option>';
				}
				echo '</select>';
			} else {
				echo '<input id="tool_' . esc_attr( $key ) . '" name="tool_' . esc_attr( $key ) . '" type="' . esc_attr( $f['type'] ) . '" value="' . esc_attr( $val ) . '">';
			}
			if ( ! empty( $f['desc'] ) ) {
				echo '<span class="description">' . esc_html( $f['desc'] ) . '</span>';
			}
			echo '</p>';
		}
		echo '</div>';
	}

	public static function save_meta( $post_id ) {
		if ( ! isset( $_POST['gangwan_tool_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['gangwan_tool_meta_nonce'] ) ), 'gangwan_tool_meta_save' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( 'tool' !== get_post_type( $post_id ) ) {
			return;
		}

		foreach ( self::meta_fields() as $key => $f ) {
			$name = 'tool_' . $key;
			if ( ! isset( $_POST[ $name ] ) ) {
				continue;
			}
			$val = wp_unslash( $_POST[ $name ] ); // phpcs:ignore
			if ( 'url' === $f['type'] ) {
				$val = esc_url_raw( $val );
			} elseif ( 'textarea' === $f['type'] ) {
				$val = sanitize_textarea_field( $val );
			} else {
				$val = sanitize_text_field( $val );
			}
			update_post_meta( $post_id, '_tool_' . $key, $val );
		}
	}

	/* ---------------- 后台列表列 ---------------- */

	public static function list_columns( $cols ) {
		$new = array();
		foreach ( $cols as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['tool_type'] = '收费类型';
				$new['tool_price'] = '价格概览';
				$new['scenario']   = '使用场景';
			}
		}
		return $new;
	}

	public static function list_column_render( $col, $post_id ) {
		if ( 'tool_type' === $col ) {
			$t = get_post_meta( $post_id, '_tool_type', true );
			echo esc_html( 'paid' === $t ? '付费' : ( 'free' === $t ? '免费' : '—' ) );
		} elseif ( 'tool_price' === $col ) {
			echo esc_html( get_post_meta( $post_id, '_tool_price', true ) );
		} elseif ( 'scenario' === $col ) {
			$terms = get_the_terms( $post_id, 'scenario' );
			echo $terms && ! is_wp_error( $terms ) ? esc_html( implode( '、', wp_list_pluck( $terms, 'name' ) ) ) : '—';
		}
	}

	/* ---------------- 元字段解析助手 ---------------- */

	public static function meta_lines( $post_id, $key ) {
		$val = get_post_meta( $post_id, '_tool_' . $key, true );
		if ( empty( $val ) ) {
			return array();
		}
		return array_filter( array_map( 'trim', explode( "\n", $val ) ), 'strlen' );
	}

	/** 兼容中文全角竖线「｜」与半角「|」，缺列自动兜底 */
	public static function meta_rows( $post_id, $key, $cols = 2 ) {
		$rows = array();
		foreach ( self::meta_lines( $post_id, $key ) as $line ) {
			$parts = preg_split( '/[|｜]/u', $line );
			$parts = array_map( 'trim', $parts );
			$parts = array_pad( $parts, $cols, '' );
			$rows[] = array_slice( $parts, 0, $cols );
		}
		return $rows;
	}
}

/* ---------------------------------------------------------------
 * 主题兼容层：保留主题模板与治理模块用到的全局函数名
 * ------------------------------------------------------------- */
if ( ! function_exists( 'gangwan_tool_meta_fields' ) ) {
	function gangwan_tool_meta_fields() {
		return GW_ERP_CPT::meta_fields();
	}
}
if ( ! function_exists( 'gangwan_meta_lines' ) ) {
	function gangwan_meta_lines( $post_id, $key ) {
		return GW_ERP_CPT::meta_lines( $post_id, $key );
	}
}
if ( ! function_exists( 'gangwan_meta_rows' ) ) {
	function gangwan_meta_rows( $post_id, $key, $cols = 2 ) {
		return GW_ERP_CPT::meta_rows( $post_id, $key, $cols );
	}
}
if ( ! function_exists( 'gangwan_register_tool_cpt' ) ) {
	function gangwan_register_tool_cpt() {
		GW_ERP_CPT::register_tool_cpt();
	}
}
if ( ! function_exists( 'gangwan_register_scenario_tax' ) ) {
	function gangwan_register_scenario_tax() {
		GW_ERP_CPT::register_scenario_tax();
	}
}
