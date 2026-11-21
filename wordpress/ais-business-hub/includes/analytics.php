<?php
/**
 * 軽量アクセス解析＋日次ダイジェスト
 * - 第一者計測：ページビュー数・流入経路（検索/SNS/参照/直接）・人気ページ
 * - お問い合わせ数／公開記事数を集計
 * - 日次で Slack にダイジェスト送信（AIによる次アクション提案つき）
 * ※ ページキャッシュ環境ではPHPを経由せず配信される場合があり、PVは実数より少なく出ることがあります。
 *    正確な分析はGA4併用を推奨（本機能は社内把握・通知用の目安です）。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'AIS_HITS_DB_VER', '1' );

/** テーブル名 */
function ais_hub_hits_table() {
	global $wpdb;
	return $wpdb->prefix . 'ais_hits';
}

/** テーブル作成（冪等） */
function ais_hub_hits_install() {
	global $wpdb;
	$table   = ais_hub_hits_table();
	$charset = $wpdb->get_charset_collate();
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$sql = "CREATE TABLE $table (
		day DATE NOT NULL,
		channel VARCHAR(20) NOT NULL,
		object_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		hits BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		PRIMARY KEY  (day, channel, object_id)
	) $charset;";
	dbDelta( $sql );
	update_option( 'ais_hub_hits_db', AIS_HITS_DB_VER );
}
add_action( 'admin_init', function () {
	if ( get_option( 'ais_hub_hits_db' ) !== AIS_HITS_DB_VER ) { ais_hub_hits_install(); }
} );

/** リファラからチャネル分類 */
function ais_hub_channel_from_referer() {
	$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '';
	if ( ! $ref ) { return 'direct'; }
	$host = strtolower( (string) wp_parse_url( $ref, PHP_URL_HOST ) );
	$self = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( ! $host || $host === $self ) { return 'direct'; }
	$search = array( 'google.', 'bing.', 'yahoo.', 'duckduckgo.', 'baidu.', 'yandex.', 'ecosia.' );
	foreach ( $search as $s ) { if ( false !== strpos( $host, $s ) ) { return 'organic'; } }
	$social = array( 't.co', 'twitter.', 'x.com', 'facebook.', 'fb.', 'instagram.', 'line.me', 'lin.ee', 'youtube.', 'youtu.be', 'tiktok.', 'linkedin.', 'pinterest.', 'note.com' );
	foreach ( $social as $s ) { if ( false !== strpos( $host, $s ) ) { return 'social'; } }
	return 'referral';
}

/** ボット判定 */
function ais_hub_is_bot() {
	$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	if ( ! $ua ) { return true; }
	return (bool) preg_match( '/bot|crawl|spider|slurp|mediapartners|facebookexternalhit|embedly|preview|monitor|headless|lighthouse|pingdom|uptime/i', $ua );
}

/** 計測 */
add_action( 'template_redirect', function () {
	if ( is_admin() || wp_doing_ajax() || is_feed() || is_robots() || is_trackback() ) { return; }
	if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) { return; }
	if ( is_user_logged_in() && current_user_can( 'edit_posts' ) ) { return; } // 社内アクセスを除外
	if ( ais_hub_is_bot() ) { return; }
	if ( get_option( 'ais_hub_hits_db' ) !== AIS_HITS_DB_VER ) { ais_hub_hits_install(); }

	$object_id = ( is_singular() || is_page() ) ? (int) get_queried_object_id() : 0;
	$channel   = ais_hub_channel_from_referer();
	$day       = current_time( 'Y-m-d' );

	global $wpdb;
	$table = ais_hub_hits_table();
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->query( $wpdb->prepare(
		"INSERT INTO $table (day, channel, object_id, hits) VALUES (%s, %s, %d, 1)
		 ON DUPLICATE KEY UPDATE hits = hits + 1",
		$day, $channel, $object_id
	) );
}, 99 );

/* =======================================================
 *  集計
 * ======================================================= */
/** 指定日数の指標を返す */
function ais_hub_metrics( $days = 7 ) {
	global $wpdb;
	$table = ais_hub_hits_table();
	$from  = gmdate( 'Y-m-d', strtotime( '-' . ( (int) $days - 1 ) . ' days', current_time( 'timestamp' ) ) );

	$out = array( 'days' => (int) $days, 'from' => $from, 'to' => current_time( 'Y-m-d' ), 'views' => 0, 'channels' => array(), 'top' => array(), 'inquiries' => 0, 'published' => 0 );

	if ( get_option( 'ais_hub_hits_db' ) === AIS_HITS_DB_VER ) {
		// phpcs:ignore WordPress.DB
		$out['views'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(hits),0) FROM $table WHERE day >= %s", $from ) );
		// phpcs:ignore WordPress.DB
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT channel, SUM(hits) h FROM $table WHERE day >= %s GROUP BY channel ORDER BY h DESC", $from ) );
		foreach ( (array) $rows as $r ) { $out['channels'][ $r->channel ] = (int) $r->h; }
		// phpcs:ignore WordPress.DB
		$top = $wpdb->get_results( $wpdb->prepare( "SELECT object_id, SUM(hits) h FROM $table WHERE day >= %s AND object_id > 0 GROUP BY object_id ORDER BY h DESC LIMIT 5", $from ) );
		foreach ( (array) $top as $r ) {
			$out['top'][] = array( 'id' => (int) $r->object_id, 'title' => get_the_title( $r->object_id ), 'url' => get_permalink( $r->object_id ), 'hits' => (int) $r->h );
		}
	}

	// お問い合わせ数
	$inq = new WP_Query( array(
		'post_type'      => 'ais_inquiry',
		'post_status'    => 'any',
		'date_query'     => array( array( 'after' => $from . ' 00:00:00', 'inclusive' => true ) ),
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'no_found_rows'  => false,
	) );
	$out['inquiries'] = (int) $inq->found_posts;

	// 公開記事数
	$pub = new WP_Query( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'date_query'     => array( array( 'after' => $from . ' 00:00:00', 'inclusive' => true ) ),
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'no_found_rows'  => false,
	) );
	$out['published'] = (int) $pub->found_posts;

	return $out;
}

/** チャネル表示名 */
function ais_hub_channel_label( $key ) {
	$m = array( 'organic' => '検索（オーガニック）', 'social' => 'SNS', 'referral' => '他サイト参照', 'direct' => '直接・ブックマーク' );
	return $m[ $key ] ?? $key;
}

/* =======================================================
 *  管理画面：アクセス解析
 * ======================================================= */
add_action( 'admin_menu', function () {
	add_submenu_page( 'ais-hub', 'アクセス解析', 'アクセス解析', 'manage_options', 'ais-hub-analytics', 'ais_hub_analytics_page' );
}, 34 );

function ais_hub_analytics_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$days = isset( $_GET['days'] ) ? max( 1, min( 90, (int) $_GET['days'] ) ) : 7;
	$m    = ais_hub_metrics( $days );
	echo '<div class="wrap"><h1>アクセス解析（集客ダッシュボード）</h1>';
	echo '<p>期間：' . esc_html( $m['from'] ) . ' 〜 ' . esc_html( $m['to'] ) . '　';
	foreach ( array( 7 => '7日', 30 => '30日', 90 => '90日' ) as $d => $lbl ) {
		echo '<a class="button ' . ( $days === $d ? 'button-primary' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=ais-hub-analytics&days=' . $d ) ) . '">' . esc_html( $lbl ) . '</a> ';
	}
	echo '</p>';

	// サマリーカード
	echo '<div style="display:flex;gap:16px;flex-wrap:wrap;margin:16px 0">';
	$cards = array(
		'ページビュー' => number_format( $m['views'] ),
		'お問い合わせ' => number_format( $m['inquiries'] ) . ' 件',
		'公開記事'     => number_format( $m['published'] ) . ' 本',
	);
	foreach ( $cards as $k => $v ) {
		echo '<div style="background:#fff;border:1px solid #dcdfe3;border-radius:10px;padding:16px 22px;min-width:160px"><div style="color:#64748b;font-size:13px">' . esc_html( $k ) . '</div><div style="font-size:26px;font-weight:700;color:#092448">' . esc_html( $v ) . '</div></div>';
	}
	echo '</div>';

	// 流入経路
	echo '<h2>流入経路</h2>';
	if ( $m['views'] > 0 && $m['channels'] ) {
		echo '<table class="widefat striped" style="max-width:520px"><thead><tr><th>経路</th><th>PV</th><th>比率</th></tr></thead><tbody>';
		foreach ( $m['channels'] as $ch => $h ) {
			$pct = $m['views'] ? round( $h / $m['views'] * 100 ) : 0;
			echo '<tr><td>' . esc_html( ais_hub_channel_label( $ch ) ) . '</td><td>' . number_format( $h ) . '</td><td>' . esc_html( $pct ) . '%</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p class="description">まだ計測データがありません（社内ログインユーザーとボットは集計対象外です）。</p>';
	}

	// 人気ページ
	echo '<h2>人気ページ Top5</h2>';
	if ( $m['top'] ) {
		echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>ページ</th><th>PV</th></tr></thead><tbody>';
		foreach ( $m['top'] as $t ) {
			echo '<tr><td><a href="' . esc_url( $t['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $t['title'] ? $t['title'] : ( '#' . $t['id'] ) ) . '</a></td><td>' . number_format( $t['hits'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	} else {
		echo '<p class="description">データ蓄積中です。</p>';
	}

	echo '<p style="margin-top:18px" class="description">※ ページキャッシュ利用時はPVが少なく出ることがあります。広告費対効果や詳細な経路分析はGA4併用を推奨します。</p>';
	echo '</div>';
}

/* =======================================================
 *  日次ダイジェスト（Slack）
 * ======================================================= */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'ais_hub_daily_digest' ) ) {
		// 毎日 09:05（サイトのタイムゾーン）に最初の実行を合わせる
		$ts = strtotime( 'tomorrow 09:05', current_time( 'timestamp' ) ) - ( (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		wp_schedule_event( $ts, 'daily', 'ais_hub_daily_digest' );
	}
} );

add_action( 'ais_hub_daily_digest', 'ais_hub_send_daily_digest' );

function ais_hub_send_daily_digest() {
	if ( ! function_exists( 'ais_hub_slack_webhook' ) || ! ais_hub_slack_webhook() ) { return; }
	if ( ! ais_hub_notify_opt( 'daily', 1 ) ) { return; }

	$d1 = ais_hub_metrics( 1 );  // 昨日〜今日
	$d7 = ais_hub_metrics( 7 );

	$lines = array(
		'• ページビュー：直近1日 *' . number_format( $d1['views'] ) . '* ／ 7日 ' . number_format( $d7['views'] ),
		'• お問い合わせ：7日 *' . number_format( $d7['inquiries'] ) . '* 件',
		'• 公開記事：7日 ' . number_format( $d7['published'] ) . ' 本',
	);
	if ( $d7['channels'] ) {
		$parts = array();
		foreach ( $d7['channels'] as $ch => $h ) { $parts[] = ais_hub_channel_label( $ch ) . ' ' . $h; }
		$lines[] = '• 流入（7日）：' . implode( '／', $parts );
	}
	if ( $d7['top'] ) {
		$t = $d7['top'][0];
		$lines[] = '• 人気No.1：<' . esc_url_raw( $t['url'] ) . '|' . $t['title'] . '>（' . $t['hits'] . 'PV）';
	}

	$suggest = ais_hub_digest_suggestion( $d7 );
	$blocks  = ais_hub_slack_blocks( '📊 本日の集客ダイジェスト（' . $d7['from'] . '〜' . $d7['to'] . '）', $lines, $suggest );
	$blocks[] = array( 'type' => 'actions', 'elements' => array( array(
		'type' => 'button',
		'text' => array( 'type' => 'plain_text', 'text' => '詳細を見る（WP）' ),
		'url'  => admin_url( 'admin.php?page=ais-hub-analytics&days=7' ),
	) ) );
	ais_hub_slack_send( '📊 本日の集客ダイジェスト', $blocks );
}

/** AIによる次アクション提案（Claudeキーがあれば生成、無ければ簡易ルール） */
function ais_hub_digest_suggestion( $m ) {
	$fallback = '🤖 提案：検索流入を伸ばすため、人気ページに関連記事の内部リンクを追加し、問い合わせの多い種別のコラムを週2本ペースで増やしましょう。';
	if ( ! function_exists( 'ais_hub_ai_anthropic_key' ) || ! ais_hub_ai_anthropic_key() ) { return $fallback; }

	$chan = array();
	foreach ( ( $m['channels'] ?? array() ) as $ch => $h ) { $chan[] = ais_hub_channel_label( $ch ) . ':' . $h; }
	$topt = array();
	foreach ( ( $m['top'] ?? array() ) as $t ) { $topt[] = $t['title'] . '(' . $t['hits'] . ')'; }
	$data = "期間:{$m['from']}〜{$m['to']}\nPV:{$m['views']}\n流入:" . implode( ' / ', $chan )
		. "\n人気:" . implode( ' / ', $topt ) . "\n問い合わせ:{$m['inquiries']}\n公開記事:{$m['published']}";

	$prompt = "あなたは自動車・モビリティ事業のWeb集客コンサルタントです。次の1週間のサイト指標をもとに、"
		. "今週実行すべき具体的な施策を日本語で3つ、各40字以内の箇条書きで提案してください。"
		. "数値の断定や誇大表現はせず、実行可能な行動に絞ること。出力は箇条書き3行のみ。\n\n" . $data;

	$text = ais_hub_ai_call_claude( $prompt );
	if ( is_wp_error( $text ) || ! $text ) { return $fallback; }
	$text = trim( wp_strip_all_tags( $text ) );
	return '🤖 AI提案：' . "\n" . $text;
}

/** 無効化時にダイジェストCronを解除 */
register_deactivation_hook( AIS_HUB_FILE, function () { wp_clear_scheduled_hook( 'ais_hub_daily_digest' ); } );
