<?php
/**
 * 晨雨ERP —— GitHub 数据源客户端
 *
 * 只读访问 GitHub REST API：仓库元信息、最近提交、配置文件内容。
 * · 支持可选 Token（优先 wp-config.php 里 define('GW_ERP_GITHUB_TOKEN', '...')，
 *   其次后台设置项），Token 只保存在数据库/常量中，界面永不回显明文；
 * · 所有响应按 12 小时 transient 缓存，避免触发 60 次/小时 的匿名配额；
 * · 配额耗尽或网络失败时回退到已缓存数据，最终回退到插件内置快照。
 *
 * @package MornrainERP
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GW_ERP_Github {

	const API = 'https://api.github.com';
	const TTL = 43200; // 12 小时

	/* ---------------- 配置 ---------------- */

	public static function settings() {
		$opts = get_option( 'gw_erp_github', array() );
		return wp_parse_args( is_array( $opts ) ? $opts : array(), array(
			'owner'  => 'mornrain-lin',
			'repo'   => 'MornrainERP',
			'branch' => 'main',
			'token'  => '',
		) );
	}

	public static function save_settings( $data ) {
		$clean = array(
			'owner'  => sanitize_text_field( $data['owner'] ),
			'repo'   => sanitize_text_field( $data['repo'] ),
			'branch' => sanitize_text_field( $data['branch'] ),
			'token'  => sanitize_text_field( $data['token'] ),
		);
		update_option( 'gw_erp_github', $clean );
		self::flush_cache();
		return $clean;
	}

	/** Token：常量优先，其次设置项；永不在前端/日志输出 */
	public static function token() {
		if ( defined( 'GW_ERP_GITHUB_TOKEN' ) && GW_ERP_GITHUB_TOKEN ) {
			return (string) GW_ERP_GITHUB_TOKEN;
		}
		$s = self::settings();
		return trim( (string) $s['token'] );
	}

	public static function flush_cache() {
		delete_transient( 'gw_erp_github_modules' );
		delete_transient( 'gw_erp_github_repo' );
		delete_transient( 'gw_erp_github_commits' );
	}

	/* ---------------- HTTP ---------------- */

	private static function request( $path, $args = array() ) {
		$url = self::API . $path;

		$headers = array(
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'MornrainERP-WordPress-Plugin/' . GW_ERP_VERSION,
		);
		$token = self::token();
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$res = wp_remote_get( $url, array(
			'timeout'    => 15,
			'headers'    => $headers,
			'sslverify'  => true,
			'redirection' => 3,
		) );

		if ( is_wp_error( $res ) ) {
			return array( 'error' => $res->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = wp_remote_retrieve_body( $res );
		$data = json_decode( $body, true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = isset( $data['message'] ) ? $data['message'] : ( 'HTTP ' . $code );
			if ( 403 === $code && false !== strpos( strtolower( (string) $msg ), 'rate limit' ) ) {
				$msg = 'GitHub API 配额已用尽（匿名 60 次/小时）。可在设置里填写 Token 提高到 5000 次/小时，或稍后再试。';
			}
			return array( 'error' => $msg, 'code' => $code );
		}

		$remaining = wp_remote_retrieve_header( $res, 'x-ratelimit-remaining' );

		return array(
			'data'      => $data,
			'remaining' => null === $remaining ? null : (int) $remaining,
		);
	}

	/** 仓库元信息 */
	public static function repo( $force = false ) {
		$key = 'gw_erp_github_repo';
		if ( ! $force ) {
			$cached = get_transient( $key );
			if ( $cached ) {
				return $cached;
			}
		}
		$s   = self::settings();
		$res = self::request( '/' . rawurlencode( $s['owner'] ) . '/' . rawurlencode( $s['repo'] ) );
		$out = array(
			'ok'        => empty( $res['error'] ),
			'error'     => isset( $res['error'] ) ? $res['error'] : '',
			'full_name' => $s['owner'] . '/' . $s['repo'],
			'html_url'  => 'https://github.com/' . $s['owner'] . '/' . $s['repo'],
			'desc'      => '',
			'stars'     => 0,
			'branch'    => $s['branch'],
			'pushed_at' => '',
			'remaining' => isset( $res['remaining'] ) ? $res['remaining'] : null,
			'fetched'   => current_time( 'mysql' ),
		);
		if ( $out['ok'] && isset( $res['data'] ) ) {
			$d               = $res['data'];
			$out['desc']     = isset( $d['description'] ) ? (string) $d['description'] : '';
			$out['stars']    = isset( $d['stargazers_count'] ) ? (int) $d['stargazers_count'] : 0;
			$out['branch']   = isset( $d['default_branch'] ) ? $d['default_branch'] : $out['branch'];
			$out['pushed_at'] = isset( $d['pushed_at'] ) ? (string) $d['pushed_at'] : '';
			$out['html_url'] = isset( $d['html_url'] ) ? $d['html_url'] : $out['html_url'];
		}
		set_transient( $key, $out, self::TTL );
		return $out;
	}

	/** 最近提交 */
	public static function commits( $per_page = 5, $force = false ) {
		$key = 'gw_erp_github_commits';
		if ( ! $force ) {
			$cached = get_transient( $key );
			if ( $cached ) {
				return $cached;
			}
		}
		$s   = self::settings();
		$res = self::request( '/' . rawurlencode( $s['owner'] ) . '/' . rawurlencode( $s['repo'] ) . '/commits?per_page=' . (int) $per_page );
		$out = array( 'ok' => empty( $res['error'] ), 'error' => isset( $res['error'] ) ? $res['error'] : '', 'items' => array() );
		if ( $out['ok'] && is_array( $res['data'] ) ) {
			foreach ( $res['data'] as $c ) {
				$out['items'][] = array(
					'sha'     => isset( $c['sha'] ) ? substr( $c['sha'], 0, 7 ) : '',
					'message' => isset( $c['commit']['message'] ) ? self::first_line( $c['commit']['message'] ) : '',
					'author'  => isset( $c['commit']['author']['name'] ) ? $c['commit']['author']['name'] : '',
					'date'    => isset( $c['commit']['author']['date'] ) ? $c['commit']['author']['date'] : '',
					'url'     => isset( $c['html_url'] ) ? $c['html_url'] : '',
				);
			}
		}
		set_transient( $key, $out, self::TTL );
		return $out;
	}

	/** 读取仓库内文本文件内容（走 contents API，返回 base64） */
	public static function file_contents( $path, $force = false ) {
		$s   = self::settings();
		$res = self::request(
			'/repos/' . rawurlencode( $s['owner'] ) . '/' . rawurlencode( $s['repo'] ) . '/contents/' . ltrim( $path, '/' ) . '?ref=' . rawurlencode( $s['branch'] )
		);
		if ( ! empty( $res['error'] ) || empty( $res['data']['content'] ) ) {
			return '';
		}
		if ( ! isset( $res['data']['encoding'] ) || 'base64' !== $res['data']['encoding'] ) {
			return '';
		}
		return base64_decode( $res['data']['content'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	/* ---------------- 模块清单 ---------------- */

	/**
	 * 取模块清单（优先 docs/modules.json，回退解析 config/modules.php，最后回退内置快照）
	 *
	 * @return array{source:string,fetched:string,error:string,groups:array,stats:array}
	 */
	public static function modules( $force = false ) {
		$key = 'gw_erp_github_modules';
		if ( ! $force ) {
			$cached = get_transient( $key );
			if ( $cached ) {
				return $cached;
			}
		}

		$out = array(
			'source'  => '',
			'fetched' => current_time( 'mysql' ),
			'error'   => '',
			'groups'  => array(),
			'stats'   => array( 'ready' => 0, 'planned' => 0, 'total' => 0 ),
		);

		// ① JSON 清单（仓库维护，最稳）
		$json = self::file_contents( 'docs/modules.json', $force );
		if ( $json ) {
			$parsed = json_decode( $json, true );
			if ( is_array( $parsed ) && ! empty( $parsed['groups'] ) ) {
				$out['source'] = 'docs/modules.json';
				$out['groups'] = self::normalize_groups( $parsed['groups'] );
				$out['stats']  = self::calc_stats( $out['groups'] );
				set_transient( $key, $out, self::TTL );
				return $out;
			}
		}

		// ② 解析 config/modules.php（尽力而为）
		$php = self::file_contents( 'config/modules.php', $force );
		if ( $php ) {
			$groups = self::parse_modules_php( $php );
			if ( $groups ) {
				$out['source'] = 'config/modules.php（尽力解析）';
				$out['groups'] = $groups;
				$out['stats']  = self::calc_stats( $groups );
				set_transient( $key, $out, self::TTL );
				return $out;
			}
		}

		// ③ 内置快照
		$snapshot = GW_ERP_DIR . 'data/modules-snapshot.json';
		if ( file_exists( $snapshot ) ) {
			$parsed = json_decode( (string) file_get_contents( $snapshot ), true ); // phpcs:ignore
			if ( is_array( $parsed ) && ! empty( $parsed['groups'] ) ) {
				$out['source']  = '插件内置快照（' . ( isset( $parsed['generated_at'] ) ? $parsed['generated_at'] : '' ) . '）';
				$out['groups']  = self::normalize_groups( $parsed['groups'] );
				$out['stats']   = self::calc_stats( $out['groups'] );
				$out['error']   = '未能从 GitHub 取得实时数据，当前展示插件内置快照。';
				set_transient( $key, $out, HOUR_IN_SECONDS );
				return $out;
			}
		}

		$out['error'] = '未能获取模块清单：请检查仓库设置、网络连通性，或填写 Token 提升 API 配额。';
		set_transient( $key, $out, 10 * MINUTE_IN_SECONDS );
		return $out;
	}

	private static function normalize_groups( $groups ) {
		$out = array();
		foreach ( (array) $groups as $g ) {
			$items = array();
			foreach ( (array) ( isset( $g['items'] ) ? $g['items'] : array() ) as $it ) {
				$items[] = array(
					'name'       => isset( $it['name'] ) ? (string) $it['name'] : '',
					'status'     => isset( $it['status'] ) ? (string) $it['status'] : 'ready',
					'permission' => isset( $it['permission'] ) ? (string) $it['permission'] : '',
					'route'      => isset( $it['route'] ) ? (string) $it['route'] : ( isset( $it['hub'] ) ? (string) $it['hub'] : '' ),
					'desc'       => isset( $it['desc'] ) ? (string) $it['desc'] : '',
				);
			}
			$out[] = array(
				'name'  => isset( $g['name'] ) ? (string) $g['name'] : '',
				'items' => $items,
			);
		}
		return $out;
	}

	private static function calc_stats( $groups ) {
		$stats = array( 'ready' => 0, 'planned' => 0, 'total' => 0 );
		foreach ( $groups as $g ) {
			foreach ( $g['items'] as $it ) {
				$stats['total']++;
				if ( 'planned' === $it['status'] ) {
					$stats['planned']++;
				} else {
					$stats['ready']++;
				}
			}
		}
		return $stats;
	}

	/**
	 * 尽力解析 config/modules.php：逐行状态机
	 * —— 组定义行含 'title'（旧版 'name'）且不含 'status'，随后出现 'items' 即确认为分组；
	 * —— 模块行单行含 'name' + 'status'（ready/planned）。
	 */
	private static function parse_modules_php( $src ) {
		$src = preg_replace( '#/\*.*?\*/#s', '', $src );
		$src = preg_replace( '#^\s*//.*$#m', '', $src );

		$groups = array();
		$idx    = null;   // 当前组在 $groups 中的下标
		$pending = null;  // 尚未确认的组名（'title' 行与 'items' 行之间）

		$ensure_group = static function ( $name ) use ( &$groups, &$idx ) {
			$idx = count( $groups );
			$groups[] = array( 'name' => $name, 'items' => array() );
		};

		foreach ( preg_split( '/\r\n|\r|\n/', $src ) as $line ) {
			$has_title  = preg_match( "/'(?:title|group)'\s*=>\s*'([^']+)'/", $line, $tm );
			$has_name   = preg_match( "/'name'\s*=>\s*'([^']+)'/", $line, $nm );
			$has_status = (bool) preg_match( "/'status'\s*=>\s*'(ready|planned)'/", $line, $sm );
			$has_items  = (bool) preg_match( "/'items'\s*=>/", $line );

			if ( $has_name && $has_status ) {
				// 模块行：组尚未建立时用 pending 惰性建立
				if ( null === $idx && null !== $pending ) {
					$ensure_group( $pending );
				}
				$pending = null;
				if ( null !== $idx ) {
					$route = preg_match( "/'(?:route|hub)'\s*=>\s*'([^']+)'/", $line, $rm ) ? $rm[1] : '';
					$perm  = preg_match( "/'permission'\s*=>\s*'([^']+)'/", $line, $pm ) ? $pm[1] : '';
					$groups[ $idx ]['items'][] = array(
						'name'       => $nm[1],
						'status'     => $sm[1],
						'permission' => $perm,
						'route'      => $route,
						'desc'       => '',
					);
				}
			} elseif ( $has_title ) {
				// 组名行（'title' => 'X'）
				$pending = $tm[1];
			} elseif ( $has_name ) {
				// 候选组名（旧结构 'name' => 'X'，或组内 meta，先暂存）
				$pending = $nm[1];
			} elseif ( $has_items && null !== $pending ) {
				// 确认为分组
				$ensure_group( $pending );
				$pending = null;
			}
		}

		if ( ! $groups && preg_match_all( "/\[\s*'name'\s*=>\s*'([^']+)'[\s\S]{0,300}?'status'\s*=>\s*'(ready|planned)'/s", $src, $mm ) ) {
			$items = array();
			foreach ( $mm[1] as $i => $name ) {
				$items[] = array(
					'name'       => $name,
					'status'     => $mm[2][ $i ],
					'permission' => '',
					'route'      => '',
					'desc'       => '',
				);
			}
			$groups[] = array( 'name' => '全部模块', 'items' => $items );
		}

		return $groups;
	}

	private static function first_line( $text ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $text );
		return trim( isset( $lines[0] ) ? $lines[0] : '' );
	}
}
