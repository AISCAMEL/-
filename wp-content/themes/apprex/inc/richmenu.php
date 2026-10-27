<?php
/**
 * LINEリッチメニュー：一般向けと代理店向けの2つ。
 *
 * - 一般向け … 公式LINEの全員に出すメニュー（AIスタジオ・料金・相談・事例・会員マイページ・代理店説明会）。
 * - 代理店向け … 説明会を予約した人に自動で切り替えるメニュー（説明会・申し込み・面談・デモ・質問・代理店専用ページ）。
 * - 画像は assets/images/richmenu/{general,agency}.jpg（2500×1686）。
 * - ボタンのリンクは /line-go/{key} を通すので、ページのURLが変わってもメニューを作り直さなくてよい
 *   （LIFFのページだけは、LINEの中で開くように liff.line.me のURLを直接使う）。
 *
 * 設定：設定 → LINEリッチメニュー
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** メニューの中身（左上から右へ、上段3つ・下段3つ）。 */
function apprex_rm_defs() {
	return array(
		'general' => array(
			'name'  => 'APPREX 一般向け',
			'bar'   => 'メニュー',
			'label' => '一般向け（全員）',
			'keys'  => array( 'studio', 'estimate', 'meeting', 'cases', 'mypage', 'seminar' ),
		),
		'agency'  => array(
			'name'  => 'APPREX 代理店向け',
			'bar'   => '代理店メニュー',
			'label' => '代理店向け（説明会を予約した人）',
			'keys'  => array( 'seminar', 'apply', 'meeting', 'studio', 'faq', 'agency' ),
		),
	);
}

/** ボタンの名前（LINEの読み上げなどに使う。20文字まで）。 */
function apprex_rm_labels() {
	return array(
		'studio'   => 'AIスタジオ',
		'estimate' => '料金・見積もり',
		'meeting'  => '無料相談を予約',
		'cases'    => '導入事例',
		'mypage'   => '会員マイページ',
		'seminar'  => '代理店説明会',
		'apply'    => '代理店に申し込む',
		'faq'      => 'よくある質問',
		'agency'   => '代理店専用ページ',
	);
}

/** /line-go/{key} の行き先。 */
function apprex_rm_target( $key ) {
	$page = function ( $slug, $fallback ) {
		return function_exists( 'apprex_page_url' ) ? apprex_page_url( $slug ) : home_url( $fallback );
	};
	switch ( $key ) {
		case 'studio':
			// 公開前（プレビュー中）はAIスタジオが見えないので、無料体験へ。
			$pub = function_exists( 'apprex_rn_public' ) && apprex_rn_public();
			return ( $pub && function_exists( 'apprex_studio_url' ) ) ? apprex_studio_url() : $page( 'free-trial', '/free-trial/' );
		case 'estimate':
			return $page( 'estimate', '/estimate/' );
		case 'meeting':
			// サイトで予約を受け付けるときは、LINEの中で開く予約ページ（LINEに確認が届く）。
			if ( function_exists( 'apprex_mb_enabled' ) && apprex_mb_enabled() && apprex_mb_liff_url() ) {
				return apprex_mb_liff_url();
			}
			$m = function_exists( 'apprex_meeting_url' ) ? apprex_meeting_url() : '';
			return $m ? $m : $page( 'meeting', '/meeting/' );
		case 'cases':
			return home_url( '/cases/' );
		case 'mypage':
			$id = function_exists( 'apprex_liff_id' ) ? apprex_liff_id() : '';
			if ( '' !== $id ) {
				return 'https://liff.line.me/' . rawurlencode( $id );
			}
			return function_exists( 'apprex_mypage_url' ) ? apprex_mypage_url() : home_url( '/mypage/' );
		case 'seminar':
			return function_exists( 'apprex_seminar_url' ) ? apprex_seminar_url() : home_url( '/partner/#seminar' );
		case 'apply':
			return home_url( '/partner/#apply' );
		case 'faq':
			return home_url( '/partner/#faq' );
		case 'agency':
			return function_exists( 'apprex_agency_portal_url' ) ? apprex_agency_portal_url() : home_url( '/agency/' );
	}
	return home_url( '/' );
}

/** メニューのボタンに入れるURL。LIFFのページは liff.line.me を直接、ほかは /line-go/ を通す。 */
function apprex_rm_uri( $key ) {
	$t = apprex_rm_target( $key );
	return 0 === strpos( $t, 'https://liff.line.me/' ) ? $t : home_url( '/line-go/' . $key );
}

/* /line-go/{key} → 行き先へ転送。 */
add_action( 'init', function () {
	add_rewrite_rule( '^line-go/([a-z]+)/?$', 'index.php?apprex_line_go=$matches[1]', 'top' );
	if ( '1' !== get_option( 'apprex_line_go_rw' ) ) {
		flush_rewrite_rules( false );
		update_option( 'apprex_line_go_rw', '1' );
	}
} );
add_filter( 'query_vars', function ( $v ) {
	$v[] = 'apprex_line_go';
	return $v;
} );
add_filter( 'redirect_canonical', function ( $r ) {
	return get_query_var( 'apprex_line_go' ) ? false : $r;
} );
add_action( 'template_redirect', function () {
	$key = get_query_var( 'apprex_line_go' );
	if ( ! $key ) {
		return;
	}
	$key = sanitize_key( $key );
	nocache_headers();
	wp_redirect( array_key_exists( $key, apprex_rm_labels() ) ? apprex_rm_target( $key ) : home_url( '/' ), 302 ); // phpcs:ignore WordPress.Security.SafeRedirect
	exit;
} );

/* =========================================================================
 * LINE Messaging API
 * ====================================================================== */

/** APIを呼ぶ。成功は配列（中身が無ければ空配列）、失敗は WP_Error。 */
function apprex_rm_api( $method, $url, $body = null, $ctype = 'application/json' ) {
	$token = function_exists( 'apprex_line_channel_token' ) ? apprex_line_channel_token() : '';
	if ( '' === $token ) {
		return new WP_Error( 'line', 'チャネルアクセストークンが未設定です（APPREX 配信(LINE) の設定）。' );
	}
	$args = array(
		'method'  => $method,
		'timeout' => 30,
		'headers' => array( 'Authorization' => 'Bearer ' . $token ),
	);
	if ( null !== $body ) {
		$args['headers']['Content-Type'] = $ctype;
		$args['body']                    = 'application/json' === $ctype ? wp_json_encode( $body ) : $body;
	}
	$res = wp_remote_request( $url, $args );
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = wp_remote_retrieve_response_code( $res );
	$data = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code < 200 || $code >= 300 ) {
		$msg = isset( $data['message'] ) ? $data['message'] : 'HTTP ' . $code;
		if ( ! empty( $data['details'][0]['message'] ) ) {
			$msg .= '（' . $data['details'][0]['message'] . '）';
		}
		return new WP_Error( 'line', $msg );
	}
	return is_array( $data ) ? $data : array();
}

/** 登録済みのリッチメニューID（general / agency）。 */
function apprex_rm_ids() {
	$o = get_option( 'apprex_rm_ids', array() );
	return is_array( $o ) ? $o : array();
}

/** 代理店メニューに切り替えた人（LINE userId の一覧）。 */
function apprex_rm_agency_uids() {
	$o = get_option( 'apprex_rm_agency_uids', array() );
	return is_array( $o ) ? array_values( array_unique( $o ) ) : array();
}

/** 1つのメニューを作って画像を上げる。成功は richMenuId。 */
function apprex_rm_create( $key ) {
	$defs = apprex_rm_defs();
	$def  = $defs[ $key ];
	$file = APPREX_DIR . '/assets/images/richmenu/' . $key . '.jpg';
	if ( ! is_readable( $file ) ) {
		return new WP_Error( 'file', '画像がありません：' . $file );
	}
	$labels = apprex_rm_labels();
	$areas  = array();
	$w      = array( 833, 834, 833 );
	foreach ( $def['keys'] as $i => $k ) {
		$col     = $i % 3;
		$areas[] = array(
			'bounds' => array(
				'x'      => array_sum( array_slice( $w, 0, $col ) ),
				'y'      => $i < 3 ? 0 : 843,
				'width'  => $w[ $col ],
				'height' => 843,
			),
			'action' => array(
				'type'  => 'uri',
				'label' => $labels[ $k ],
				'uri'   => apprex_rm_uri( $k ),
			),
		);
	}
	$r = apprex_rm_api(
		'POST',
		'https://api.line.me/v2/bot/richmenu',
		array(
			'size'        => array( 'width' => 2500, 'height' => 1686 ),
			'selected'    => true,
			'name'        => $def['name'],
			'chatBarText' => $def['bar'],
			'areas'       => $areas,
		)
	);
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	if ( empty( $r['richMenuId'] ) ) {
		return new WP_Error( 'line', 'リッチメニューIDが返ってきませんでした。' );
	}
	$id = $r['richMenuId'];
	$up = apprex_rm_api( 'POST', 'https://api-data.line.me/v2/bot/richmenu/' . rawurlencode( $id ) . '/content', file_get_contents( $file ), 'image/jpeg' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( is_wp_error( $up ) ) {
		apprex_rm_api( 'DELETE', 'https://api.line.me/v2/bot/richmenu/' . rawurlencode( $id ) );
		return $up;
	}
	return $id;
}

/** 代理店メニューを何人かにまとめて付ける（500人ずつ）。 */
function apprex_rm_bulk( $action, $uids, $menu_id = '' ) {
	foreach ( array_chunk( $uids, 500 ) as $chunk ) {
		$body = array( 'userIds' => $chunk );
		if ( 'link' === $action ) {
			$body['richMenuId'] = $menu_id;
		}
		$r = apprex_rm_api( 'POST', 'https://api.line.me/v2/bot/richmenu/bulk/' . $action, $body );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
	}
	return true;
}

/** 1人を代理店メニューに切り替える。 */
function apprex_rm_link_agency( $uid ) {
	$ids = apprex_rm_ids();
	if ( '' === $uid || empty( $ids['agency'] ) || ! get_option( 'apprex_rm_auto', 1 ) ) {
		return false;
	}
	$r = apprex_rm_api( 'POST', 'https://api.line.me/v2/bot/user/' . rawurlencode( $uid ) . '/richmenu/' . rawurlencode( $ids['agency'] ) );
	if ( is_wp_error( $r ) ) {
		update_option( 'apprex_rm_last_error', $r->get_error_message(), false );
		return false;
	}
	$list   = apprex_rm_agency_uids();
	$list[] = $uid;
	update_option( 'apprex_rm_agency_uids', array_values( array_unique( $list ) ), false );
	return true;
}

/* 説明会を予約した人（LINE）を代理店メニューに。 */
add_action( 'apprex_seminar_registered', function ( $entry_id, $uid ) {
	if ( $uid ) {
		apprex_rm_link_agency( $uid );
	}
}, 10, 2 );

/* =========================================================================
 * 設定ページ
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'LINEリッチメニュー', 'LINEリッチメニュー', 'manage_options', 'apprex-richmenu', 'apprex_rm_page' );
} );

add_action( 'admin_post_apprex_rm', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'apprex_rm' ) ) {
		wp_die( '権限がありません。' );
	}
	$do  = isset( $_POST['do'] ) ? sanitize_key( $_POST['do'] ) : '';
	$ids = apprex_rm_ids();
	$msg = '';
	$err = '';
	update_option( 'apprex_rm_auto', empty( $_POST['auto'] ) ? 0 : 1 );

	if ( 'publish' === $do ) {
		$new = array();
		foreach ( array_keys( apprex_rm_defs() ) as $k ) {
			$id = apprex_rm_create( $k );
			if ( is_wp_error( $id ) ) {
				$err = apprex_rm_defs()[ $k ]['label'] . '：' . $id->get_error_message();
				break;
			}
			$new[ $k ] = $id;
		}
		if ( $err ) {
			foreach ( $new as $id ) {
				apprex_rm_api( 'DELETE', 'https://api.line.me/v2/bot/richmenu/' . rawurlencode( $id ) );
			}
		} else {
			$notes = array();
			if ( ! empty( $_POST['default'] ) ) {
				$r       = apprex_rm_api( 'POST', 'https://api.line.me/v2/bot/user/all/richmenu/' . rawurlencode( $new['general'] ) );
				$notes[] = is_wp_error( $r ) ? '全員のメニューにできませんでした（' . $r->get_error_message() . '）' : '一般向けを全員のメニューにしました';
			}
			$uids = apprex_rm_agency_uids();
			if ( $uids ) {
				$r       = apprex_rm_bulk( 'link', $uids, $new['agency'] );
				$notes[] = is_wp_error( $r ) ? '代理店メニューの付け直しに失敗（' . $r->get_error_message() . '）' : count( $uids ) . '人に代理店メニューを付け直しました';
			}
			// 前に作ったメニューは消す。
			foreach ( $ids as $old ) {
				apprex_rm_api( 'DELETE', 'https://api.line.me/v2/bot/richmenu/' . rawurlencode( $old ) );
			}
			update_option( 'apprex_rm_ids', $new );
			update_option( 'apprex_rm_published', time() );
			$msg = 'リッチメニューを登録しました。' . ( $notes ? implode( '／', $notes ) . '。' : '' );
		}
	} elseif ( 'reset_users' === $do ) {
		$uids = apprex_rm_agency_uids();
		$r    = $uids ? apprex_rm_bulk( 'unlink', $uids ) : true;
		if ( is_wp_error( $r ) ) {
			$err = $r->get_error_message();
		} else {
			update_option( 'apprex_rm_agency_uids', array(), false );
			$msg = count( $uids ) . '人を一般向けのメニューに戻しました。';
		}
	} elseif ( 'remove' === $do ) {
		apprex_rm_api( 'DELETE', 'https://api.line.me/v2/bot/user/all/richmenu' );
		foreach ( $ids as $old ) {
			apprex_rm_api( 'DELETE', 'https://api.line.me/v2/bot/richmenu/' . rawurlencode( $old ) );
		}
		delete_option( 'apprex_rm_ids' );
		update_option( 'apprex_rm_agency_uids', array(), false );
		$msg = 'リッチメニューを外しました（LINE公式アカウントの管理画面で作ったメニューに戻ります）。';
	}
	set_transient( 'apprex_rm_notice', array( $msg, $err ), 60 );
	wp_safe_redirect( admin_url( 'options-general.php?page=apprex-richmenu' ) );
	exit;
} );

function apprex_rm_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$ids    = apprex_rm_ids();
	$notice = get_transient( 'apprex_rm_notice' );
	delete_transient( 'apprex_rm_notice' );
	$labels = apprex_rm_labels();
	$token  = function_exists( 'apprex_line_channel_token' ) && '' !== apprex_line_channel_token();
	$last   = get_option( 'apprex_rm_last_error', '' );
	?>
	<div class="wrap">
		<h1>LINEリッチメニュー</h1>
		<?php if ( $notice && $notice[0] ) : ?>
			<div class="notice notice-success"><p><?php echo esc_html( $notice[0] ); ?></p></div>
		<?php endif; ?>
		<?php if ( $notice && $notice[1] ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php endif; ?>
		<div class="notice notice-info"><p>
			公式LINEのトーク画面の下に出るメニューです。お客様には<b>一般向け</b>、代理店説明会を予約した人には自動で<b>代理店向け</b>が出ます。<br>
			LINE公式アカウントの管理画面で作ったメニューより、ここで登録したメニューが優先されます。<br>
			<?php if ( ! $token ) : ?><b style="color:#b91c1c">先に「APPREX 配信(LINE)」でチャネルアクセストークンを設定してください。</b><br><?php endif; ?>
			<?php if ( function_exists( 'apprex_seminar_liff_id' ) && '' === apprex_seminar_liff_id() ) : ?><b style="color:#b45309">説明会のLIFF IDが未設定です。設定してから登録すると、「代理店説明会」のボタンがLINEの中で開きます。</b><br><?php endif; ?>
			状態：<strong><?php echo $ids ? '登録済み（' . esc_html( wp_date( 'Y/n/j H:i', (int) get_option( 'apprex_rm_published' ) ) ) . '）' : '未登録'; ?></strong>
			／代理店メニューの人：<strong><?php echo count( apprex_rm_agency_uids() ); ?>人</strong>
			<?php if ( $last ) : ?><br>最後のエラー：<?php echo esc_html( $last ); ?><?php endif; ?>
		</p></div>

		<div style="display:flex;flex-wrap:wrap;gap:24px">
			<?php foreach ( apprex_rm_defs() as $k => $d ) : ?>
				<div style="flex:1 1 420px;max-width:560px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px">
					<h2 style="margin-top:0"><?php echo esc_html( $d['label'] ); ?></h2>
					<img src="<?php echo esc_url( APPREX_URI . '/assets/images/richmenu/' . $k . '.jpg?v=' . APPREX_VERSION ); ?>" alt="" style="width:100%;border-radius:6px;display:block">
					<table class="widefat striped" style="margin-top:10px"><tbody>
						<?php foreach ( $d['keys'] as $key ) : ?>
							<tr><td style="width:40%"><?php echo esc_html( $labels[ $key ] ); ?></td><td><a href="<?php echo esc_url( apprex_rm_target( $key ) ); ?>" target="_blank" rel="noopener" style="word-break:break-all"><?php echo esc_html( apprex_rm_target( $key ) ); ?></a></td></tr>
						<?php endforeach; ?>
					</tbody></table>
				</div>
			<?php endforeach; ?>
		</div>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:20px">
			<?php wp_nonce_field( 'apprex_rm' ); ?>
			<input type="hidden" name="action" value="apprex_rm">
			<p><label><input type="checkbox" name="default" value="1" checked> 一般向けを全員のメニューにする</label></p>
			<p><label><input type="checkbox" name="auto" value="1" <?php checked( (bool) get_option( 'apprex_rm_auto', 1 ) ); ?>> 説明会をLINEで予約した人を、代理店向けメニューに切り替える</label></p>
			<p>
				<button class="button button-primary" name="do" value="publish"><?php echo $ids ? 'LINEに登録し直す' : 'LINEに登録する'; ?></button>
				<?php if ( apprex_rm_agency_uids() ) : ?>
					<button class="button" name="do" value="reset_users" onclick="return confirm('代理店向けメニューの人を、全員一般向けに戻します。よろしいですか？');">代理店メニューの人を一般向けに戻す</button>
				<?php endif; ?>
				<?php if ( $ids ) : ?>
					<button class="button button-link-delete" name="do" value="remove" onclick="return confirm('このメニューをLINEから外します。よろしいですか？');">メニューを外す</button>
				<?php endif; ?>
			</p>
			<p class="description">リンク先のURLは、メニューを作り直さなくても自動で今のページに向きます（AIスタジオは公開前は無料体験へ）。LIFF IDを入れたときだけ「登録し直す」を押してください。</p>
		</form>
	</div>
	<?php
}
