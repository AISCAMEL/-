<?php
/**
 * 業務ナビ（管理画面を迷わず使うための入口）。
 *
 * - 「お問い合わせ → ミーティング → 見積もり → 契約 → 請求 → 入金 → 制作」の流れを、手順の順にボタンで並べる
 * - いま対応が必要なこと（新しいお問い合わせ・予約・締結待ち・未払い・LINEのご依頼）を件数で出す
 * - たくさんある設定は「設定の一覧」に、目的ごとにまとめる
 * - 「かんたんメニュー」：左のメニューを、よく使うものだけにする（いつでも全部に戻せる）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** かんたんメニューを使うか（管理者ごと。既定はオン）。 */
function apprex_nav_simple() {
	return current_user_can( 'manage_options' ) && ! get_user_meta( get_current_user_id(), 'apprex_full_menu', true );
}

/** 管理画面のURL。 */
function apprex_nav_url( $path ) {
	return admin_url( $path );
}

/* -------------------------------------------------------------------------
 * 業務の流れ
 * ---------------------------------------------------------------------- */
function apprex_nav_flows() {
	$flows = array(
		array(
			'key'   => 'inquiry',
			'title' => 'お問い合わせ・ご相談',
			'desc'  => 'お客様から連絡が来たら',
			'steps' => array(
				array( 'お問い合わせを確認する', 'フォーム・見積もり・資料請求から届いたもの。内容とメモを残せます。', 'edit.php?post_type=apprex_inquiry', '一覧を開く' ),
				array( 'オンライン相談の予約を確認する', 'お客様が予約した日時と、Google Meet のURL。', 'edit.php?post_type=apprex_inquiry&page=apprex-meeting', '予約を見る' ),
				array( 'チャットの会話を確認する', 'サイトのチャットでのやりとり。担当者が必要なものもここに。', 'edit.php?post_type=apprex_chatlog', '会話を見る' ),
			),
		),
		array(
			'key'   => 'contract',
			'title' => '見積もり → ご契約',
			'desc'  => '金額が決まったら',
			'steps' => array(
				array( '見積もり・発注を確認する', 'お客様が出した見積もりと発注。', 'edit.php?post_type=apprex_order', '見積もりを見る' ),
				array( '契約を登録する', 'お客様の名前・メール・プラン・月額・支払い方法（カード／請求書）・支払い日を入れます。', 'post-new.php?post_type=apprex_contract', '新しく登録' ),
				array( '契約書を送って締結する', '契約の画面の「契約書」欄から送ります。締結されると自動で「締結済み」になります。', 'edit.php?post_type=apprex_contract', '契約の一覧' ),
				array( '会員アカウントを発行する', '契約の画面の「会員アカウントを発行する」を押すと、お客様にマイページのログイン情報が届きます。', 'edit.php?post_type=apprex_contract', '契約の一覧' ),
			),
		),
		array(
			'key'   => 'billing',
			'title' => '請求 → 入金',
			'desc'  => '毎月のお支払い',
			'steps' => array(
				array( 'カード払いの請求書を出す', 'Square の請求書。毎月自動でも、手でも出せます。', 'options-general.php?page=apprex-square', 'Squareの請求' ),
				array( '請求書（振込）払いの請求書を出す', 'マネーフォワードの請求書、またはPDFの請求書。', 'options-general.php?page=apprex-mf', '振込の請求' ),
				array( '入金を確認する', '支払済み・未払いの一覧と、売上の数字。', 'admin.php?page=apprex-dashboard', '数字を見る' ),
				array( 'お支払いの自動案内', '期日前・期日を過ぎたときのお知らせを、LINE・メール・マイページに自動で送ります。', 'options-general.php?page=apprex-line#apprex-bn', '自動案内の設定' ),
			),
		),
		array(
			'key'   => 'production',
			'title' => '制作 → 公開',
			'desc'  => 'ご契約のあと',
			'steps' => array(
				array( '制作の進み具合を更新する', 'ユーザー → お客様の「編集」→「AIスタジオ」欄で、いまの工程を選びます。お客様のマイページに表示されます。', 'users.php', 'ユーザー一覧' ),
				array( 'LINEで届いたご依頼に対応する', 'プラン変更・解約・支払いの相談など。対応したら「対応済み」に。', 'options-general.php?page=apprex-line#apprex-line-requests', 'ご依頼を見る' ),
			),
		),
		array(
			'key'   => 'marketing',
			'title' => '発信・集客',
			'desc'  => 'お客様を増やす',
			'steps' => array(
				array( 'AIで記事を作る', 'テーマを入れると、記事を下書きで作ります。', 'edit.php?page=apprex-ai-blog', '記事を作る' ),
				array( '記事を見直す', '検索・地図・AIに強い記事に作り直します。', 'edit.php?page=apprex-brush', '見直す' ),
				array( '導入事例を追加する', '制作したアプリ・ホームページを紹介します。', 'post-new.php?post_type=case', '事例を追加' ),
				array( 'LINEのステップ配信', '友だち追加のあと、日にちを空けて自動で送るメッセージ。', 'options-general.php?page=apprex-line-steps', 'ステップ配信' ),
				array( 'Instagram に投稿する', '記事を公開すると、画像と文章を自動で作って投稿します。', 'options-general.php?page=apprex-sns', 'SNSの設定' ),
			),
		),
	);
	if ( post_type_exists( 'apprex_agency' ) ) {
		$flows[] = array(
			'key'   => 'agency',
			'title' => '代理店・説明会',
			'desc'  => '代理店さんとのやりとり',
			'steps' => array(
				array( '代理店を確認する', '申し込み・登録された代理店。', 'edit.php?post_type=apprex_agency', '代理店の一覧' ),
				array( '報酬・支払い', '代理店への報酬の計算と支払い。', 'edit.php?post_type=apprex_agency&page=apprex-agency-pay', '報酬を見る' ),
				array( '説明会の設定', '説明会の日程と案内。', 'edit.php?post_type=apprex_agency&page=apprex-seminar', '説明会' ),
			),
		);
	}
	return $flows;
}

/* -------------------------------------------------------------------------
 * 設定の一覧（目的ごと）
 * ---------------------------------------------------------------------- */
function apprex_nav_settings() {
	return array(
		'LINE'           => array(
			array( 'LINEの配信・AI自動応答', 'トークン・AIの返信・会員との連動・お支払いの自動案内', 'options-general.php?page=apprex-line' ),
			array( 'LINEのステップ配信', '友だち追加後に自動で送るメッセージ', 'options-general.php?page=apprex-line-steps' ),
			array( 'LINEのリッチメニュー', 'トーク画面の下のメニュー', 'options-general.php?page=apprex-richmenu' ),
			array( 'LINEの会員マイページ（LIFF）', 'LINEでマイページを開く設定', 'options-general.php?page=apprex-liff' ),
			array( 'LINEの友だち', '友だちの一覧（テスト送信の宛先もここ）', 'edit.php?post_type=apprex_line_friend' ),
		),
		'メール'          => array(
			array( 'メールの文面', 'お客様に届く自動メールの文章を直す', 'options-general.php?page=apprex-mail-content' ),
			array( 'メールのテスト・見本', '届くメールを自分に送って確かめる', 'options-general.php?page=apprex-mail' ),
			array( 'メールの送信（SMTP）', 'メールが届かないときの設定', 'options-general.php?page=apprex-smtp' ),
		),
		'お支払い・契約'    => array(
			array( 'Square（カード払い）', 'カード払いの請求書と、Squareとのつなぎ方', 'options-general.php?page=apprex-square' ),
			array( 'マネーフォワード（振込）', '振込払いの請求書と、マネーフォワードとのつなぎ方', 'options-general.php?page=apprex-mf' ),
			array( '請求書の発行元・振込先', '会社名・住所・インボイス番号・振込先', 'options-general.php?page=apprex-invoice' ),
			array( '契約書のひな形', '契約書の文章', 'edit.php?post_type=apprex_contract&page=apprex-contract-template' ),
		),
		'チャット・AI'     => array(
			array( 'サイトのチャット', 'AIのキー・モデル・答え方', 'options-general.php?page=apprex-chat' ),
			array( 'オペレーター（人が対応）', 'チャットを担当者に引き継ぐ設定', 'options-general.php?page=apprex-operator' ),
		),
		'SNS・広告'       => array(
			array( 'SNSへの自動投稿', 'Instagram・Facebook・X', 'options-general.php?page=apprex-sns' ),
			array( 'SNS広告', '広告の計測タグ', 'options-general.php?page=apprex-sns-ads' ),
			array( '広告枠', 'サイト内の広告の表示', 'options-general.php?page=apprex-ads' ),
			array( '広告用のLP', '広告から来た人に見せるページ', 'options-general.php?page=apprex-lp' ),
		),
		'サイト・連携'      => array(
			array( '外部サービスとの連携', 'Slack・Googleなどとのつなぎ方', 'options-general.php?page=apprex-integrations' ),
			array( 'Googleドライブ連携', 'お客様ごとの資料フォルダ', 'options-general.php?page=apprex-drive' ),
			array( '記事どうしのリンク（SEO）', '関連する記事へのリンクを自動で入れる', 'options-general.php?page=apprex-internal-links' ),
			array( 'LABO', '無料のアプリ体験ページ', 'edit.php?post_type=apprex_labo_post' ),
			array( 'AIMEO（地図の口コミ）', 'Googleマップ向けの投稿', 'edit.php?post_type=apprex_meo' ),
			array( 'サイトの基本設定', 'サイト名・アドレスなど（WordPress）', 'options-general.php' ),
		),
	);
}

/* -------------------------------------------------------------------------
 * いま対応が必要なこと
 * ---------------------------------------------------------------------- */
function apprex_nav_todo() {
	$out = array();
	// 新しいお問い合わせ（7日以内）。
	$q = get_posts( array( 'post_type' => 'apprex_inquiry', 'post_status' => 'any', 'posts_per_page' => 50, 'fields' => 'ids', 'date_query' => array( array( 'after' => '7 days ago' ) ) ) );
	$out[] = array( '新しいお問い合わせ', count( $q ), '7日以内', 'edit.php?post_type=apprex_inquiry', count( $q ) ? 'blue' : 'gray' );
	// これからのオンライン相談。
	$mt   = get_posts( array( 'post_type' => 'apprex_inquiry', 'post_status' => 'any', 'posts_per_page' => 50, 'fields' => 'ids', 'meta_query' => array( array( 'key' => 'apprex_meeting_at', 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC' ) ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$next = 0;
	$n    = 0;
	foreach ( $mt as $id ) {
		if ( get_post_meta( $id, 'apprex_mb_cancel', true ) ) {
			continue;
		}
		$n++;
		$at   = (int) get_post_meta( $id, 'apprex_meeting_at', true );
		$next = ( ! $next || $at < $next ) ? $at : $next;
	}
	$out[] = array( 'これからのオンライン相談', $n, $next ? '次は ' . ( function_exists( 'apprex_mb_when' ) ? apprex_mb_when( $next ) : wp_date( 'n/j G:i', $next ) ) : '予定なし', 'edit.php?post_type=apprex_inquiry&page=apprex-meeting', $n ? 'blue' : 'gray' );
	// 契約書の締結待ち・未払い・更新が近い。
	$sign = 0;
	$late = 0;
	$soon = 0;
	foreach ( function_exists( 'apprex_get_contracts' ) ? apprex_get_contracts() : array() as $id ) {
		if ( 'sent' === get_post_meta( $id, 'apprex_c_mf_status', true ) ) {
			$sign++;
		}
		$s = function_exists( 'apprex_bn_state' ) ? apprex_bn_state( $id ) : null;
		if ( $s && false === $s['paid'] && $s['days'] >= 0 ) {
			$late++;
		}
		$r = (string) get_post_meta( $id, 'apprex_c_renewal', true );
		if ( $r && 'cancelled' !== get_post_meta( $id, 'apprex_c_status', true ) && strtotime( $r ) - time() < 30 * DAY_IN_SECONDS && strtotime( $r ) > time() - DAY_IN_SECONDS ) {
			$soon++;
		}
	}
	$out[] = array( '契約書の締結待ち', $sign, 'お客様の締結を待っています', 'edit.php?post_type=apprex_contract', $sign ? 'amber' : 'gray' );
	$out[] = array( 'お支払いの期日超過', $late, $late ? '自動案内の画面で確認' : 'ありません', 'options-general.php?page=apprex-line#apprex-bn', $late ? 'red' : 'gray' );
	$out[] = array( '30日以内に更新', $soon, '契約の更新日が近い', 'edit.php?post_type=apprex_contract', $soon ? 'amber' : 'gray' );
	// LINEのご依頼（未対応）。
	$req = 0;
	foreach ( (array) get_option( 'apprex_line_requests', array() ) as $r ) {
		if ( is_array( $r ) && empty( $r['done'] ) ) {
			$req++;
		}
	}
	$out[] = array( 'LINEのご依頼（未対応）', $req, $req ? '対応したら「対応済み」に' : 'ありません', 'options-general.php?page=apprex-line#apprex-line-requests', $req ? 'red' : 'gray' );
	return $out;
}

/* -------------------------------------------------------------------------
 * システムの状態（止まっていないか）
 * ---------------------------------------------------------------------- */
function apprex_nav_health() {
	$out = array();
	$now = time();
	// 自動処理：予定の時刻を2時間以上過ぎても動いていなければ、止まっている。
	$stuck = array();
	$names = function_exists( 'apprex_cron_hooks' ) ? apprex_cron_hooks() : array( 'apprex_contract_cron' => '契約・月次請求' );
	foreach ( $names as $hook => $label ) {
		$next = wp_next_scheduled( $hook );
		if ( ! $next || $next < $now - 2 * HOUR_IN_SECONDS ) {
			$stuck[] = $label;
		}
	}
	$out[] = $stuck
		? array( '自動処理', false, '予定より遅れています：' . implode( '・', $stuck ) . '（数字を見る画面のいちばん下で確認）', 'admin.php?page=apprex-dashboard' )
		: array( '自動処理', true, '毎日の請求・お知らせ・配信は予定どおり動いています', 'admin.php?page=apprex-dashboard' );
	// AI。
	$ai = function_exists( 'apprex_ai_text_ready' ) && apprex_ai_text_ready();
	$er = get_option( 'apprex_orig_last_error', array() );
	if ( ! $ai ) {
		$out[] = array( 'AI', false, 'AIのキーが入っていません', 'options-general.php?page=apprex-chat' );
	} elseif ( is_array( $er ) && ! empty( $er['t'] ) && $now - (int) $er['t'] < DAY_IN_SECONDS ) {
		$out[] = array( 'AI', false, '24時間以内にAIの失敗がありました（' . wp_date( 'n/j G:i', (int) $er['t'] ) . '）。続く場合は残高を確認', 'options-general.php?page=apprex-chat' );
	} else {
		$out[] = array( 'AI', true, 'チャット・AIスタジオのAIは使える状態です', 'options-general.php?page=apprex-chat' );
	}
	// LINE。
	$line = function_exists( 'apprex_line_direct_ready' ) && apprex_line_direct_ready();
	$hook = (int) get_option( 'apprex_line_hook_last', 0 );
	$last = get_option( 'apprex_line_ai_last', array() );
	if ( ! $line ) {
		$out[] = array( 'LINE', false, 'LINEのトークンが入っていません', 'options-general.php?page=apprex-line' );
	} elseif ( is_array( $last ) && ! empty( $last['t'] ) && 'ok' !== $last['s'] && $now - (int) $last['t'] < DAY_IN_SECONDS ) {
		$out[] = array( 'LINE', false, 'LINEのAIの返信に失敗しました（' . wp_date( 'n/j G:i', (int) $last['t'] ) . '）', 'options-general.php?page=apprex-line' );
	} else {
		$out[] = array( 'LINE', true, $hook ? 'つながっています（最後に受信 ' . wp_date( 'n/j G:i', $hook ) . '）' : 'つながっています', 'options-general.php?page=apprex-line' );
	}
	// お支払い。
	$sq = function_exists( 'apprex_square_enabled' ) && apprex_square_enabled();
	$mf = function_exists( 'apprex_mf_ready' ) && apprex_mf_ready();
	$out[] = array( 'お支払い', $sq || $mf, ( $sq ? 'Square（カード）：つながっています' : 'Square：未設定' ) . '／' . ( $mf ? 'マネーフォワード：つながっています' : 'マネーフォワード：未設定' ), 'admin.php?page=apprex-nav-settings' );
	// メール。
	$smtp  = function_exists( 'apprex_smtp_ready' ) && apprex_smtp_ready();
	$out[] = array( 'メール', $smtp, $smtp ? 'メール送信（SMTP）が設定されています' : 'メール送信（SMTP）が未設定です。届きにくいことがあります', 'options-general.php?page=apprex-smtp' );
	return $out;
}

/* -------------------------------------------------------------------------
 * メニュー
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_menu_page( '業務ナビ', '業務ナビ', 'manage_options', 'apprex-nav', 'apprex_nav_page', 'dashicons-flag', 1 );
	add_submenu_page( 'apprex-nav', '業務ナビ', 'やることと手順', 'manage_options', 'apprex-nav', 'apprex_nav_page' );
	add_submenu_page( 'apprex-nav', '設定の一覧', '設定の一覧', 'manage_options', 'apprex-nav-settings', 'apprex_nav_settings_page' );
	add_submenu_page( 'apprex-nav', '数字を見る', '数字を見る（売上・入金）', 'manage_options', 'admin.php?page=apprex-dashboard' );
}, 5 );

/* かんたんメニュー：よく使うものだけを左に残す（ページ自体は消さないので、業務ナビ・設定の一覧から開ける）。 */
add_action( 'admin_menu', function () {
	if ( ! apprex_nav_simple() ) {
		return;
	}
	global $menu, $submenu;
	$hide = array( 'apprex-dashboard', 'edit-comments.php', 'edit.php?post_type=apprex_chatlog', 'edit.php?post_type=apprex_labo_post', 'edit.php?post_type=apprex_meo', 'tools.php', 'plugins.php' );
	foreach ( (array) $menu as $i => $item ) {
		$slug  = isset( $item[2] ) ? $item[2] : '';
		$title = isset( $item[0] ) ? wp_strip_all_tags( $item[0] ) : '';
		if ( in_array( $slug, $hide, true ) || preg_match( '/LPtools|LPを作る|テンプレートを作/u', $title ) ) {
			$menu[ $i ][4] = ( isset( $item[4] ) ? $item[4] . ' ' : '' ) . 'apprex-hide'; // 見えなくするだけ（画面の題名などは残す）。
		}
	}
	// 設定の下の APPREX の設定は「業務ナビ → 設定の一覧」にまとめる。
	if ( isset( $submenu['options-general.php'] ) ) {
		foreach ( $submenu['options-general.php'] as $i => $item ) {
			$slug  = isset( $item[2] ) ? $item[2] : '';
			$title = isset( $item[0] ) ? wp_strip_all_tags( $item[0] ) : '';
			if ( 0 === strpos( $slug, 'apprex-' ) || false !== strpos( $slug, 'apprex_line_friend' ) || preg_match( '/^(APPREX|LINE)/u', $title ) ) {
				$submenu['options-general.php'][ $i ][4] = 'apprex-hide';
			}
		}
	}
}, 9999 );

/* 見えなくした項目（いま開いている画面のものは、迷わないよう表示する）。 */
add_action( 'admin_head', function () {
	if ( apprex_nav_simple() ) {
		echo '<style>#adminmenu li.apprex-hide:not(.current):not(.wp-has-current-submenu):not(.wp-menu-open){display:none}</style>';
	}
} );

/* 左のメニューの並び：業務の流れの順。 */
add_filter( 'custom_menu_order', function ( $on ) {
	return apprex_nav_simple() ? true : $on;
} );
add_filter( 'menu_order', function ( $order ) {
	if ( ! apprex_nav_simple() ) {
		return $order;
	}
	$first = array(
		'apprex-nav',
		'index.php',
		'separator1',
		'edit.php?post_type=apprex_inquiry',
		'edit.php?post_type=apprex_order',
		'edit.php?post_type=apprex_contract',
		'edit.php?post_type=apprex_agency',
		'separator2',
		'edit.php',
		'edit.php?post_type=case',
		'edit.php?post_type=page',
		'upload.php',
	);
	$rest = array_values( array_diff( (array) $order, $first ) );
	return array_merge( array_values( array_intersect( $first, (array) $order ) ), $rest );
} );

/* 切り替え（かんたん／すべて）。 */
add_action( 'admin_post_apprex_nav_menu', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_nav_menu' );
	if ( ! empty( $_GET['full'] ) ) {
		update_user_meta( get_current_user_id(), 'apprex_full_menu', 1 );
	} else {
		delete_user_meta( get_current_user_id(), 'apprex_full_menu' );
	}
	wp_safe_redirect( admin_url( 'admin.php?page=apprex-nav' ) );
	exit;
} );

/* ダッシュボード（WordPressの最初の画面）にも入口を出す。 */
add_action( 'admin_notices', function () {
	$s = get_current_screen();
	if ( ! $s || 'dashboard' !== $s->id || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	echo '<div class="notice notice-info"><p style="font-size:14px"><b>何をすればいいか迷ったら：</b> <a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=apprex-nav' ) ) . '">業務ナビを開く</a> いま対応が必要なことと、お問い合わせから入金までの手順をまとめています。</p></div>';
} );

/* -------------------------------------------------------------------------
 * 画面
 * ---------------------------------------------------------------------- */
function apprex_nav_css() {
	?>
	<style>
	.anav{max-width:1180px;margin:18px 20px 40px 0;font-size:14px;color:#1f2937}
	.anav h1{font-size:24px;margin:0 0 4px}
	.anav .lead{color:#6b7280;margin:0 0 18px}
	.anav-bar{display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:space-between;margin-bottom:18px}
	.anav-mode{background:#fff;border:1px solid #e5e7eb;border-radius:999px;padding:6px 8px 6px 14px;display:flex;align-items:center;gap:10px}
	.anav-mode b{font-size:13px}
	.anav-todo{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:10px;margin:0 0 26px}
	.anav-todo a{display:block;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:12px 14px;text-decoration:none;color:inherit;border-top:4px solid #d1d5db}
	.anav-todo a:hover{box-shadow:0 4px 14px rgba(15,23,42,.08)}
	.anav-todo small{display:block;color:#6b7280;font-size:12px}
	.anav-todo b{display:block;font-size:26px;line-height:1.3;margin:2px 0}
	.anav-todo em{font-style:normal;font-size:12px;color:#6b7280}
	.anav-todo .is-blue{border-top-color:#2f66f5}.anav-todo .is-blue b{color:#1c47c4}
	.anav-todo .is-amber{border-top-color:#f59e0b}.anav-todo .is-amber b{color:#a16207}
	.anav-todo .is-red{border-top-color:#dc2626}.anav-todo .is-red b{color:#b91c1c}
	.anav-todo .is-gray b{color:#9ca3af}
	.anav h2{font-size:18px;margin:0 0 10px}
	.anav-health{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:8px;margin:0 0 26px}
	.anav-health a{display:grid;grid-template-columns:24px 76px 1fr;gap:8px;align-items:center;background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:9px 12px;text-decoration:none;color:inherit}
	.anav-health i{width:22px;height:22px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-weight:700;font-size:12px;color:#fff;background:#16a34a}
	.anav-health a.ng{border-color:#fecaca;background:#fef2f2}
	.anav-health a.ng i{background:#dc2626}
	.anav-health span{font-size:12px;color:#4b5563;line-height:1.5}
	.anav-flows{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px}
	.anav-flow{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px 16px 8px}
	.anav-flow header{display:flex;align-items:baseline;gap:8px;margin-bottom:8px}
	.anav-flow header b{font-size:16px}
	.anav-flow header span{color:#6b7280;font-size:12px}
	.anav-flow ol{list-style:none;margin:0;padding:0;counter-reset:s}
	.anav-flow li{counter-increment:s;display:grid;grid-template-columns:26px 1fr auto;gap:10px;align-items:center;padding:10px 0;border-top:1px dashed #e5e7eb}
	.anav-flow li:before{content:counter(s);width:24px;height:24px;border-radius:50%;background:#eef2ff;color:#3730a3;font-weight:700;font-size:12px;display:grid;place-items:center}
	.anav-flow li b{display:block;font-size:14px}
	.anav-flow li span{display:block;color:#6b7280;font-size:12px;line-height:1.6}
	.anav-flow li .button{white-space:nowrap}
	.anav-set{display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:14px}
	.anav-set section{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px 16px}
	.anav-set h3{margin:0 0 6px;font-size:15px}
	.anav-set a{display:block;padding:9px 0;border-top:1px dashed #e5e7eb;text-decoration:none}
	.anav-set a:first-of-type{border-top:0}
	.anav-set a b{display:block;font-size:14px}
	.anav-set a span{display:block;color:#6b7280;font-size:12px}
	@media (max-width:782px){.anav{margin-right:10px}.anav-flows,.anav-set{grid-template-columns:1fr}.anav-flow li{grid-template-columns:26px 1fr}.anav-flow li .button{grid-column:2;justify-self:start}}
	</style>
	<?php
}

/** 切り替えボタン。 */
function apprex_nav_mode_box() {
	$simple = apprex_nav_simple();
	$url    = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_nav_menu' . ( $simple ? '&full=1' : '' ) ), 'apprex_nav_menu' );
	echo '<div class="anav-mode"><b>左のメニュー：' . ( $simple ? 'かんたん（よく使うものだけ）' : 'すべて表示' ) . '</b><a class="button button-small" href="' . esc_url( $url ) . '">' . ( $simple ? 'すべて表示にする' : 'かんたんにする' ) . '</a></div>';
}

function apprex_nav_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	apprex_nav_css();
	?>
	<div class="anav">
		<div class="anav-bar">
			<div><h1>業務ナビ</h1><p class="lead">いま対応が必要なことと、お問い合わせから入金までの手順です。上から順に進めれば大丈夫です。</p></div>
			<?php apprex_nav_mode_box(); ?>
		</div>

		<h2>いま対応が必要なこと</h2>
		<div class="anav-todo">
			<?php foreach ( apprex_nav_todo() as $t ) : ?>
				<a class="is-<?php echo esc_attr( $t[4] ); ?>" href="<?php echo esc_url( apprex_nav_url( $t[3] ) ); ?>"><small><?php echo esc_html( $t[0] ); ?></small><b><?php echo (int) $t[1]; ?><span style="font-size:13px;font-weight:600"> 件</span></b><em><?php echo esc_html( $t[2] ); ?></em></a>
			<?php endforeach; ?>
		</div>

		<h2>システムの状態</h2>
		<div class="anav-health">
			<?php foreach ( apprex_nav_health() as $h ) : ?>
				<a class="<?php echo $h[1] ? 'ok' : 'ng'; ?>" href="<?php echo esc_url( apprex_nav_url( $h[3] ) ); ?>"><i><?php echo $h[1] ? '✓' : '!'; ?></i><b><?php echo esc_html( $h[0] ); ?></b><span><?php echo esc_html( $h[2] ); ?></span></a>
			<?php endforeach; ?>
		</div>

		<h2>業務の流れ（手順）</h2>
		<div class="anav-flows">
			<?php foreach ( apprex_nav_flows() as $f ) : ?>
				<div class="anav-flow" id="flow-<?php echo esc_attr( $f['key'] ); ?>">
					<header><b><?php echo esc_html( $f['title'] ); ?></b><span><?php echo esc_html( $f['desc'] ); ?></span></header>
					<ol>
						<?php foreach ( $f['steps'] as $s ) : ?>
							<li><div><b><?php echo esc_html( $s[0] ); ?></b><span><?php echo esc_html( $s[1] ); ?></span></div><a class="button" href="<?php echo esc_url( apprex_nav_url( $s[2] ) ); ?>"><?php echo esc_html( $s[3] ); ?></a></li>
						<?php endforeach; ?>
					</ol>
				</div>
			<?php endforeach; ?>
		</div>

		<p style="margin-top:22px"><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=apprex-nav-settings' ) ); ?>">設定の一覧を開く</a> <span style="color:#6b7280">LINE・メール・お支払い・チャットなどの設定は、ここに目的ごとにまとめています。</span></p>
	</div>
	<?php
}

function apprex_nav_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	apprex_nav_css();
	?>
	<div class="anav">
		<div class="anav-bar">
			<div><h1>設定の一覧</h1><p class="lead">設定は、ふだんは触らなくて大丈夫です。変えたいことがあるときだけ、目的の欄から開いてください。</p></div>
			<?php apprex_nav_mode_box(); ?>
		</div>
		<div class="anav-set">
			<?php foreach ( apprex_nav_settings() as $group => $items ) : ?>
				<section>
					<h3><?php echo esc_html( $group ); ?></h3>
					<?php foreach ( $items as $it ) : ?>
						<a href="<?php echo esc_url( apprex_nav_url( $it[2] ) ); ?>"><b><?php echo esc_html( $it[0] ); ?></b><span><?php echo esc_html( $it[1] ); ?></span></a>
					<?php endforeach; ?>
				</section>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
}

/* かんたんメニューで左から外した設定の画面にも、タブの題名を出す。 */
add_filter( 'admin_title', function ( $title, $orig ) {
	if ( '' !== trim( (string) $orig ) || empty( $_GET['page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return $title;
	}
	$page = sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	foreach ( apprex_nav_settings() as $items ) {
		foreach ( $items as $it ) {
			if ( false !== strpos( $it[2], 'page=' . $page ) ) {
				return $it[0] . ' ' . $title;
			}
		}
	}
	return $title;
}, 10, 2 );
