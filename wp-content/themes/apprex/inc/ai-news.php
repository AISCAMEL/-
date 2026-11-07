<?php
/**
 * AIで「お知らせ（新着ニュース）」と「導入事例」をつくる。
 *
 * お知らせ：本当にあった出来事（導入事例の公開・お休み・先月の新着コラム・ひとことメモ）から、
 *           AIが文章を書いて、カテゴリー「news」に自動で公開する。
 * 導入事例：制作が終わったお客様の本当の記録（AIスタジオの内容・制作の期間）から、AIが下書きを作る。
 *           お客様の掲載許可にチェックがないと公開できない（作り話の事例を載せないため）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 共通
 * ====================================================================== */

/** AIで文章を作る（記事あつかい。使えなければ null）。 */
function apprex_an_ai( $system, $user, $max = 1200 ) {
	if ( ! function_exists( 'apprex_openrouter_complete' ) || ! function_exists( 'apprex_ai_text_ready' ) ) {
		return null;
	}
	$ready = apprex_ai_text_ready() || ( function_exists( 'apprex_claude_for' ) && apprex_claude_for( 'article' ) );
	if ( ! $ready ) {
		return null;
	}
	$r = apprex_openrouter_complete(
		array( array( 'role' => 'system', 'content' => $system ), array( 'role' => 'user', 'content' => $user ) ),
		array( 'max_tokens' => $max, 'temperature' => 0.5, 'timeout' => 90, 'article' => true )
	);
	if ( is_wp_error( $r ) ) {
		return null;
	}
	$t = trim( str_replace( "\r", '', (string) $r ) );
	return '' !== $t ? $t : null;
}

/** 前後の空白・記号・かぎかっこを外す（日本語の文字をこわさない）。 */
function apprex_an_mbtrim( $t ) {
	return preg_replace( '/^[\s　#*「『]+|[\s　#*」』]+$/u', '', (string) $t );
}

/** 「タイトル：…」の1行目と本文に分ける。 */
function apprex_an_split( $t ) {
	if ( preg_match( '/^(?:#+\s*)?(?:タイトル|件名)[:：]\s*(.+)\n+([\s\S]+)$/u', trim( (string) $t ), $m ) ) {
		return array( apprex_an_mbtrim( $m[1] ), trim( $m[2] ) );
	}
	return null;
}

/** 本文（かんたんなMarkdown）をブロックエディタのHTMLに。 */
function apprex_an_html( $md ) {
	$out = array();
	foreach ( preg_split( '/\n{2,}/', trim( (string) $md ) ) as $p ) {
		$p = trim( $p );
		if ( '' === $p ) {
			continue;
		}
		if ( preg_match( '/^#{2,3}\s*(.+)$/u', $p, $m ) ) {
			$out[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( trim( $m[1], ' *' ) ) . "</h2>\n<!-- /wp:heading -->";
			continue;
		}
		if ( preg_match_all( '/^[-・*]\s*(.+)$/um', $p, $li ) && count( $li[1] ) === count( preg_split( '/\n/', $p ) ) ) {
			$items = '';
			foreach ( $li[1] as $x ) {
				$items .= '<li>' . apprex_an_inline( $x ) . '</li>';
			}
			$out[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . $items . "</ul>\n<!-- /wp:list -->";
			continue;
		}
		$out[] = "<!-- wp:paragraph -->\n<p>" . nl2br( apprex_an_inline( $p ), false ) . "</p>\n<!-- /wp:paragraph -->";
	}
	return implode( "\n\n", $out );
}
function apprex_an_inline( $t ) {
	$t = esc_html( $t );
	$t = preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t );
	return preg_replace_callback(
		'#\[([^\]]+)\]\((https?://[^)\s]+)\)#u',
		function ( $m ) {
			return '<a href="' . esc_url( html_entity_decode( $m[2] ) ) . '">' . $m[1] . '</a>';
		},
		$t
	);
}

/* =========================================================================
 * お知らせ
 * ====================================================================== */

/** カテゴリー「news」（なければ作る）。 */
function apprex_an_news_cat() {
	$c = get_category_by_slug( 'news' );
	if ( $c ) {
		return (int) $c->term_id;
	}
	$r = wp_insert_term( 'ニュース', 'category', array( 'slug' => 'news' ) );
	return is_wp_error( $r ) ? 0 : (int) $r['term_id'];
}

/** お知らせの設定（出来事ごとのオン・オフ）。 */
function apprex_an_opt( $k ) {
	$o = get_option( 'apprex_an', array() );
	$o = is_array( $o ) ? $o : array();
	$d = array( 'case' => 1, 'holiday' => 1, 'monthly' => 1, 'publish' => 1 );
	return (int) ( $o[ $k ] ?? $d[ $k ] ?? 0 );
}

/**
 * お知らせを1つ作る。
 *
 * @param string $key   同じ出来事で2回作らないための名前。'' なら確かめない。
 * @param string $facts 本当のこと（これだけを使って書く）。
 * @param array  $fb    AIが使えないときの [タイトル, 本文]。
 * @param bool   $pub   公開する（false なら下書き）。
 * @return int|WP_Error 投稿ID。
 */
function apprex_an_news_create( $key, $facts, $fb, $pub = true ) {
	$done = (array) get_option( 'apprex_an_done', array() );
	if ( '' !== $key && isset( $done[ $key ] ) ) {
		return new WP_Error( 'an', 'このお知らせは作成済みです。' );
	}
	$sys = 'あなたはアプリ制作サービス「APPREX」（合同会社アイズ・福島県いわき市）のサイトの担当者です。サイトの「お知らせ」を書きます。'
		. '守ること：下の「本当のこと」に書いてあることだけを使う。日付・数字・名前を作らない。誇張しない。です・ます調で短く（本文150〜400字）。絵文字は使わない。';
	$usr = "本当のこと：\n" . $facts . "\n\n形式：1行目に「タイトル：」から始めてタイトル（35字以内）、2行目から本文。見出しは不要。リンクがあれば [文字](URL) の形で入れる。";
	$t   = apprex_an_ai( $sys, $usr, 900 );
	$sp  = $t ? apprex_an_split( $t ) : null;
	list( $title, $body ) = $sp ? $sp : $fb;
	$id = wp_insert_post(
		array(
			'post_type'     => 'post',
			'post_status'   => $pub ? 'publish' : 'draft',
			'post_title'    => mb_substr( $title, 0, 60 ),
			'post_content'  => apprex_an_html( $body ),
			'post_category' => array_filter( array( apprex_an_news_cat() ) ),
			'post_author'   => (int) ( get_current_user_id() ? get_current_user_id() : ( get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0] ?? 1 ) ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, 'apprex_an_auto', $key ? $key : 'memo' );
	if ( '' !== $key ) {
		$done[ $key ] = $id;
		update_option( 'apprex_an_done', array_slice( $done, -300, null, true ), false );
	}
	if ( function_exists( 'apprex_notify' ) ) {
		apprex_notify( 'post', '📰 AIでお知らせを' . ( $pub ? '公開' : '下書き' ) . 'しました：' . get_the_title( $id ) . "\n" . ( $pub ? get_permalink( $id ) : admin_url( 'post.php?action=edit&post=' . $id ) ) );
	}
	return (int) $id;
}

// 導入事例を公開したら、お知らせ。
add_action(
	'transition_post_status',
	function ( $new, $old, $post ) {
		if ( 'case' !== $post->post_type || 'publish' !== $new || 'publish' === $old || ! apprex_an_opt( 'case' ) ) {
			return;
		}
		$ind   = wp_get_post_terms( $post->ID, 'industry', array( 'fields' => 'names' ) );
		$url   = get_permalink( $post );
		$facts = '導入事例のページを新しく公開した。' . "\n事例のタイトル：" . $post->post_title . ( $ind && ! is_wp_error( $ind ) ? "\n業種：" . implode( '・', $ind ) : '' )
			. ( '' !== trim( $post->post_excerpt ) ? "\n概要：" . wp_strip_all_tags( $post->post_excerpt ) : '' ) . "\n事例のページ：" . $url;
		apprex_an_news_create( 'case_' . $post->ID, $facts, array( '導入事例「' . $post->post_title . '」を公開しました', "導入事例を新しく公開しました。\n\n[" . $post->post_title . '](' . $url . ')' ), (bool) apprex_an_opt( 'publish' ) );
	},
	20,
	3
);

/** お休み（ミーティング予約の「お休みの日」から、続いている日をまとめる）。 */
function apprex_an_holidays() {
	$s = function_exists( 'apprex_mb_settings' ) ? apprex_mb_settings() : array( 'holidays' => '' );
	$d = array();
	foreach ( preg_split( '/\R/', (string) $s['holidays'] ) as $l ) {
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})$/', trim( $l ), $m ) ) {
			$d[] = $m[1];
		}
	}
	sort( $d );
	$d   = array_values( array_unique( $d ) );
	$out = array();
	foreach ( $d as $x ) {
		$n = count( $out );
		if ( $n && gmdate( 'Y-m-d', strtotime( $out[ $n - 1 ][1] . ' +1 day' ) ) === $x ) {
			$out[ $n - 1 ][1] = $x;
		} else {
			$out[] = array( $x, $x );
		}
	}
	return $out;
}

/** 1日1回：お休みのお知らせ・先月の新着コラム。 */
function apprex_an_daily() {
	$today = wp_date( 'Y-m-d' );
	$wd    = array( '日', '月', '火', '水', '木', '金', '土' );
	$fmt   = function ( $ymd ) use ( $wd ) {
		$t = strtotime( $ymd . ' 12:00:00' );
		return (int) gmdate( 'n', $t ) . '月' . (int) gmdate( 'j', $t ) . '日（' . $wd[ (int) gmdate( 'w', $t ) ] . '）';
	};
	// お休み：始まる14日前から（2日以上続くお休みだけ）。
	if ( apprex_an_opt( 'holiday' ) ) {
		foreach ( apprex_an_holidays() as $h ) {
			$days = (int) round( ( strtotime( $h[1] ) - strtotime( $h[0] ) ) / DAY_IN_SECONDS ) + 1;
			$left = (int) round( ( strtotime( $h[0] ) - strtotime( $today ) ) / DAY_IN_SECONDS );
			if ( $days < 2 || $left < 0 || $left > 14 ) {
				continue;
			}
			$span  = $fmt( $h[0] ) . '〜' . $fmt( $h[1] );
			$back  = $fmt( gmdate( 'Y-m-d', strtotime( $h[1] . ' +1 day' ) ) );
			$facts = "お休みの期間：{$span}（{$days}日間）\nこの期間は、オンラインのご相談（ミーティング）の予約を受け付けていない。\nお問い合わせ・AIスタジオ（無料でアプリの見本を作れるページ）はいつでも使える。お休み中のお問い合わせへの返事は {$back} から順番にする。\nミーティング予約：" . home_url( '/meeting/' ) . "\nAIスタジオ：" . home_url( '/studio/' );
			apprex_an_news_create( 'hol_' . $h[0], $facts, array( 'お休みのお知らせ（' . $span . '）', "{$span}はお休みをいただきます。\n\nこの期間のお問い合わせには、{$back}から順番にお返事します。AIスタジオはお休み中もご利用いただけます。\n\n[AIスタジオ](" . home_url( '/studio/' ) . ')' ), (bool) apprex_an_opt( 'publish' ) );
		}
	}
	// 先月の新着コラム（毎月1日〜3日のあいだに1回。2本以上あったときだけ）。
	if ( apprex_an_opt( 'monthly' ) && (int) wp_date( 'j' ) <= 3 ) {
		$ym    = wp_date( 'Y-m', strtotime( wp_date( 'Y-m-01' ) . ' -1 day' ) );
		$first = $ym . '-01 00:00:00';
		$last  = wp_date( 'Y-m-d', strtotime( wp_date( 'Y-m-01' ) . ' -1 day' ) ) . ' 23:59:59';
		$cat   = apprex_an_news_cat();
		$q     = get_posts(
			array(
				'post_type'        => 'post',
				'post_status'      => 'publish',
				'posts_per_page'   => 12,
				'category__not_in' => $cat ? array( $cat ) : array(),
				'date_query'       => array( array( 'after' => $first, 'before' => $last, 'inclusive' => true ) ),
				'orderby'          => 'date',
				'order'            => 'ASC',
			)
		);
		if ( count( $q ) >= 2 ) {
			$mon   = (int) substr( $ym, 5, 2 );
			$list  = '';
			$lines = '';
			foreach ( $q as $p ) {
				$list  .= '- ' . $p->post_title . '（' . get_permalink( $p ) . "）\n";
				$lines .= '- [' . $p->post_title . '](' . get_permalink( $p ) . ")\n";
			}
			$facts = "{$mon}月に公開したコラム（" . count( $q ) . "本）：\n" . $list . 'コラムの一覧：' . get_post_type_archive_link( 'post' );
			apprex_an_news_create( 'month_' . $ym, $facts, array( $mon . '月の新着コラム（' . count( $q ) . '本）', "{$mon}月に公開したコラムをまとめました。\n\n" . trim( $lines ) ), (bool) apprex_an_opt( 'publish' ) );
		}
	}
}

add_action(
	'init',
	function () {
		if ( ! wp_next_scheduled( 'apprex_an_cron' ) ) {
			$next = strtotime( wp_date( 'Y-m-d' ) . ' 09:10:00 ' . wp_timezone_string() );
			wp_schedule_event( $next > time() ? $next : $next + DAY_IN_SECONDS, 'daily', 'apprex_an_cron' );
		}
	}
);
add_action( 'apprex_an_cron', 'apprex_an_daily' );
add_action(
	'apprex_an_cron',
	function () {
		if ( get_option( 'apprex_case_auto', 1 ) ) {
			apprex_case_auto_drafts();
		}
	},
	20
);
add_filter(
	'apprex_cron_hooks',
	function ( $h ) {
		$h['apprex_an_cron'] = 'AIのお知らせ・導入事例の下書き';
		return $h;
	}
);

/* =========================================================================
 * 導入事例（本当の記録から下書き → 掲載許可で公開）
 * ====================================================================== */

/** 制作が終わったお客様（まだ事例を作っていない人）。 */
function apprex_case_finished_users() {
	$out = array();
	foreach ( get_users( array( 'meta_key' => 'apprex_prod_type', 'number' => 200, 'fields' => 'ID' ) ) as $uid ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$st = function_exists( 'apprex_prod_state' ) ? apprex_prod_state( (int) $uid ) : null;
		if ( $st && ! empty( $st['finished'] ) ) {
			$out[] = (int) $uid;
		}
	}
	return $out;
}

/** お客様の本当の記録を、AIに渡す文にする。 */
function apprex_case_facts( $uid ) {
	$u = get_userdata( $uid );
	if ( ! $u ) {
		return '';
	}
	$f     = array();
	$state = json_decode( (string) get_user_meta( $uid, 'apprex_studio_state', true ), true );
	$state = is_array( $state ) ? $state : array();
	$map   = array( 'industry' => '業種', 'name' => 'アプリの名前', 'goal' => 'アプリで実現したいこと', 'target' => '使う人', 'catch' => 'キャッチコピー', 'request' => 'ご要望' );
	foreach ( $map as $k => $l ) {
		if ( ! empty( $state[ $k ] ) && is_scalar( $state[ $k ] ) ) {
			$f[] = $l . '：' . mb_substr( wp_strip_all_tags( (string) $state[ $k ] ), 0, 200 );
		}
	}
	foreach ( array( 'menus' => 'アプリのメニュー', 'pages' => '画面' ) as $k => $l ) {
		if ( ! empty( $state[ $k ] ) && is_array( $state[ $k ] ) ) {
			$names = array();
			foreach ( $state[ $k ] as $m ) {
				$names[] = is_array( $m ) ? (string) ( $m['label'] ?? $m['name'] ?? $m['title'] ?? '' ) : (string) $m;
			}
			$names = array_filter( array_map( 'wp_strip_all_tags', $names ) );
			if ( $names ) {
				$f[] = $l . '：' . implode( '・', array_slice( $names, 0, 10 ) );
			}
		}
	}
	$sum = (string) get_user_meta( $uid, 'apprex_studio_summary', true );
	if ( '' !== $sum ) {
		$f[] = 'ご相談のまとめ：' . mb_substr( wp_strip_all_tags( $sum ), 0, 600 );
	}
	if ( function_exists( 'apprex_prod_get' ) ) {
		$p    = apprex_prod_get( $uid );
		$pre  = function_exists( 'apprex_prod_presets' ) ? apprex_prod_presets() : array();
		$f[]  = '作り方：' . ( $pre[ $p['type'] ][0] ?? $p['type'] );
		$done = array_values( array_filter( (array) $p['done'] ) );
		$kick = '' !== $p['kickoff'] ? $p['kickoff'] : ( $done[0] ?? '' );
		$end  = $done ? end( $done ) : '';
		if ( '' !== $kick && '' !== $end && $end >= $kick ) {
			$f[] = '制作の期間：' . $kick . ' 〜 ' . $end . '（' . (int) round( ( strtotime( $end ) - strtotime( $kick ) ) / DAY_IN_SECONDS ) . '日）';
		}
	}
	return implode( "\n", $f );
}

/**
 * 事例の下書きを作る。
 *
 * @param array $in uid（お客様）／industry・area・made・problem・result・name（手で入れた本当のこと）・memo。
 * @return int|WP_Error
 */
function apprex_case_make_draft( $in ) {
	$uid   = (int) ( $in['uid'] ?? 0 );
	$facts = $uid ? apprex_case_facts( $uid ) : '';
	$hand  = array( 'industry' => '業種', 'area' => '地域', 'made' => '作ったもの', 'problem' => '困っていたこと', 'result' => '結果（お客様から聞いたこと）', 'memo' => 'そのほかのメモ' );
	foreach ( $hand as $k => $l ) {
		if ( ! empty( $in[ $k ] ) ) {
			$facts .= ( '' !== $facts ? "\n" : '' ) . $l . '：' . $in[ $k ];
		}
	}
	if ( '' === trim( $facts ) ) {
		return new WP_Error( 'case', '事例のもとになる本当のこと（お客様か、作ったもの・困っていたことなど）を入れてください。' );
	}
	$name = trim( (string) ( $in['name'] ?? '' ) );
	$who  = '' !== $name ? $name : ( ( ! empty( $in['area'] ) ? $in['area'] . 'の' : '' ) . ( ! empty( $in['industry'] ) ? $in['industry'] : 'お店' ) . '様' );
	$sys  = 'あなたはアプリ制作サービス「APPREX」の導入事例の記事を書く担当者です。'
		. '守ること：下の「本当のこと」に書いてあることだけを使う。数字・効果・お客様の言葉（「」のコメント）を作らない。結果が書かれていなければ「これから」の書き方にして、効果があったとは書かない。'
		. 'お客様の呼び方は「' . $who . '」だけを使う（ほかの名前を作らない）。です・ます調。';
	$usr  = "本当のこと：\n" . $facts . "\n\n次の形で書いてください。\n1行目：「タイトル：」から始めてタイトル（40字以内・「{$who}」を入れる）\n2行目：「概要：」から始めて一覧に出す説明（80字以内）\nそのあと本文（600〜1200字）。見出しは「## 」で、「お客様について」「困っていたこと」「APPREXでつくったもの」「これから・結果」の順。";
	$t    = apprex_an_ai( $sys, $usr, 2200 );
	$title = $who . 'の導入事例';
	$ex    = '';
	$body  = "## お客様について\n\n" . $who . "\n\n## つくったもの\n\n" . str_replace( "\n", "\n\n", $facts );
	if ( $t && preg_match( '/^(?:#+\s*)?タイトル[:：]\s*(.+)\n+(?:概要[:：]\s*(.+)\n+)?([\s\S]+)$/u', trim( $t ), $m ) ) {
		$title = apprex_an_mbtrim( $m[1] );
		$ex    = trim( (string) $m[2] );
		$body  = trim( $m[3] );
	}
	$id = wp_insert_post(
		array(
			'post_type'    => 'case',
			'post_status'  => 'draft',
			'post_title'   => mb_substr( $title, 0, 70 ),
			'post_excerpt' => mb_substr( $ex, 0, 120 ),
			'post_content' => apprex_an_html( $body ),
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, 'apprex_case_ai', 1 );
	update_post_meta( $id, 'apprex_case_facts', $facts );
	if ( $uid ) {
		update_post_meta( $id, 'apprex_case_uid', $uid );
		update_user_meta( $uid, 'apprex_case_drafted', $id );
	}
	$ind = trim( (string) ( $in['industry'] ?? '' ) );
	if ( '' === $ind && $uid ) {
		$state = json_decode( (string) get_user_meta( $uid, 'apprex_studio_state', true ), true );
		$ind   = is_array( $state ) && ! empty( $state['industry'] ) && is_string( $state['industry'] ) ? $state['industry'] : '';
	}
	if ( '' !== $ind && taxonomy_exists( 'industry' ) ) {
		$term = get_term_by( 'name', $ind, 'industry' );
		if ( $term ) {
			wp_set_object_terms( $id, (int) $term->term_id, 'industry' );
		}
	}
	return (int) $id;
}

/** 制作が終わったお客様の下書きを、自動で作る（1回に3件まで）。 */
function apprex_case_auto_drafts() {
	$n = 0;
	foreach ( apprex_case_finished_users() as $uid ) {
		if ( $n >= 3 || get_user_meta( $uid, 'apprex_case_drafted', true ) ) {
			continue;
		}
		$id = apprex_case_make_draft( array( 'uid' => $uid ) );
		if ( ! is_wp_error( $id ) ) {
			$n++;
			if ( function_exists( 'apprex_notify' ) ) {
				$u = get_userdata( $uid );
				apprex_notify( 'post', '📝 制作が終わった ' . ( $u ? $u->display_name : '' ) . ' 様の導入事例を、AIで下書きしました。お客様に掲載の許可をもらってから公開してください。' . "\n" . admin_url( 'post.php?action=edit&post=' . $id ) );
			}
		}
	}
	return $n;
}

// 掲載の許可がない、AIの事例は公開させない（下書きに戻す）。
add_filter(
	'wp_insert_post_data',
	function ( $data, $postarr ) {
		if ( 'case' !== $data['post_type'] || ! in_array( $data['post_status'], array( 'publish', 'future' ), true ) || empty( $postarr['ID'] ) ) {
			return $data;
		}
		$id = (int) $postarr['ID'];
		if ( ! get_post_meta( $id, 'apprex_case_ai', true ) ) {
			return $data;
		}
		$ok = isset( $_POST['apprex_case_consent'] ) ? ! empty( $_POST['apprex_case_consent'] ) : (bool) get_post_meta( $id, 'apprex_case_consent', true ); // phpcs:ignore WordPress.Security.NonceVerification
		if ( ! $ok ) {
			$data['post_status'] = 'draft';
			set_transient( 'apprex_case_block_' . get_current_user_id(), 1, 60 );
		}
		return $data;
	},
	10,
	2
);

// 編集画面：掲載の許可。
add_action(
	'add_meta_boxes_case',
	function () {
		add_meta_box(
			'apprex_case_consent',
			'掲載の許可',
			function ( $post ) {
				wp_nonce_field( 'apprex_case_consent', 'apprex_case_consent_nonce' );
				$on = (bool) get_post_meta( $post->ID, 'apprex_case_consent', true );
				$ai = (bool) get_post_meta( $post->ID, 'apprex_case_ai', true );
				echo '<p><label><input type="hidden" name="apprex_case_consent" value="0"><input type="checkbox" name="apprex_case_consent" value="1" ' . checked( $on, true, false ) . '> お客様から、この内容（名前・画像を含む）で載せる許可をもらいました</label></p>';
				echo '<p><input name="apprex_case_consent_note" value="' . esc_attr( (string) get_post_meta( $post->ID, 'apprex_case_consent_note', true ) ) . '" placeholder="例：11/10 メールで山田様から許可" style="width:100%"></p>';
				// ブロックエディタは「公開」を先に保存するので、チェックしたらすぐ保存する。
				echo '<script>(function(){var b=document.getElementById("apprex_case_consent");if(!b)return;var t;function sv(){var c=b.querySelector("input[type=checkbox]"),n=b.querySelector("input[name=apprex_case_consent_note]"),f=new FormData();f.append("action","apprex_case_consent");f.append("_ajax_nonce",' . wp_json_encode( wp_create_nonce( 'apprex_case_consent_ajax' ) ) . ');f.append("id",' . (int) $post->ID . ');f.append("on",c.checked?1:0);f.append("note",n.value);fetch(ajaxurl,{method:"POST",body:f,credentials:"same-origin"});}b.addEventListener("change",sv);b.addEventListener("input",function(){clearTimeout(t);t=setTimeout(sv,800);});})();</script>';
				if ( $ai ) {
					echo '<p class="description">AIが本当の記録から下書きした事例です。<b>許可にチェックがないと公開できません。</b>数字やお客様の言葉を足すときは、お客様に確かめたことだけを書いてください。</p>';
					$facts = (string) get_post_meta( $post->ID, 'apprex_case_facts', true );
					if ( '' !== $facts ) {
						echo '<details><summary style="cursor:pointer">AIに渡した本当のこと</summary><pre style="white-space:pre-wrap;font-size:11px">' . esc_html( $facts ) . '</pre></details>';
					}
				}
			},
			'case',
			'side',
			'high'
		);
	}
);
add_action(
	'wp_ajax_apprex_case_consent',
	function () {
		check_ajax_referer( 'apprex_case_consent_ajax' );
		$id = absint( $_POST['id'] ?? 0 );
		if ( ! $id || 'case' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
			wp_send_json_error();
		}
		update_post_meta( $id, 'apprex_case_consent', ! empty( $_POST['on'] ) ? 1 : 0 );
		update_post_meta( $id, 'apprex_case_consent_note', sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ) );
		wp_send_json_success();
	}
);
add_action(
	'save_post_case',
	function ( $id ) {
		if ( ! isset( $_POST['apprex_case_consent_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['apprex_case_consent_nonce'] ), 'apprex_case_consent' ) || ! current_user_can( 'edit_post', $id ) ) {
			return;
		}
		update_post_meta( $id, 'apprex_case_consent', ! empty( $_POST['apprex_case_consent'] ) ? 1 : 0 );
		update_post_meta( $id, 'apprex_case_consent_note', sanitize_text_field( wp_unslash( $_POST['apprex_case_consent_note'] ?? '' ) ) );
	}
);
add_action(
	'admin_notices',
	function () {
		if ( get_transient( 'apprex_case_block_' . get_current_user_id() ) ) {
			delete_transient( 'apprex_case_block_' . get_current_user_id() );
			echo '<div class="notice notice-warning"><p>この導入事例はAIの下書きなので、右の「掲載の許可」にチェックを入れるまで公開できません（下書きのまま保存しました）。</p></div>';
		}
	}
);

/* =========================================================================
 * 画面：投稿 → AIでお知らせ／導入事例 → AIで事例を作る
 * ====================================================================== */

add_action(
	'admin_menu',
	function () {
		add_submenu_page( 'edit.php', 'AIでお知らせ', 'AIでお知らせ', 'edit_posts', 'apprex-ai-news', 'apprex_an_page' );
		add_submenu_page( 'edit.php?post_type=case', 'AIで事例を作る', 'AIで事例を作る', 'edit_posts', 'apprex-ai-case', 'apprex_case_page' );
	}
);

add_action(
	'admin_post_apprex_an',
	function () {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( '権限がありません。' );
		}
		check_admin_referer( 'apprex_an' );
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$do   = sanitize_key( $_POST['do'] ?? '' );
		$back = wp_get_referer() ? wp_get_referer() : admin_url();
		$msg  = '';
		if ( 'memo' === $do ) {
			$memo = sanitize_textarea_field( wp_unslash( $_POST['memo'] ?? '' ) );
			if ( '' === trim( $memo ) ) {
				$msg = 'お知らせにしたいことを入れてください。';
			} else {
				$r   = apprex_an_news_create( '', $memo, array( mb_substr( strtok( $memo, "\n" ), 0, 35 ), $memo ), ! empty( $_POST['publish'] ) );
				$msg = is_wp_error( $r ) ? '失敗：' . $r->get_error_message() : ( ! empty( $_POST['publish'] ) ? 'お知らせを公開しました：' : '下書きにしました：' ) . get_the_title( $r );
				$back = is_wp_error( $r ) ? $back : admin_url( 'post.php?action=edit&post=' . $r );
			}
		} elseif ( 'settings' === $do ) {
			$o = array();
			foreach ( array( 'case', 'holiday', 'monthly', 'publish' ) as $k ) {
				$o[ $k ] = ! empty( $_POST[ $k ] ) ? 1 : 0;
			}
			update_option( 'apprex_an', $o, false );
			$msg = '保存しました。';
		} elseif ( 'runnow' === $do ) {
			$before = count( (array) get_option( 'apprex_an_done', array() ) );
			apprex_an_daily();
			$msg = 'いまの状態で確かめました（新しいお知らせ ' . max( 0, count( (array) get_option( 'apprex_an_done', array() ) ) - $before ) . '件）。';
		} elseif ( 'case' === $do ) {
			$in = array( 'uid' => absint( $_POST['uid'] ?? 0 ) );
			foreach ( array( 'industry', 'area', 'made', 'problem', 'result', 'name', 'memo' ) as $k ) {
				$in[ $k ] = sanitize_textarea_field( wp_unslash( $_POST[ $k ] ?? '' ) );
			}
			$r = apprex_case_make_draft( $in );
			if ( is_wp_error( $r ) ) {
				$msg = '失敗：' . $r->get_error_message();
			} else {
				$back = admin_url( 'post.php?action=edit&post=' . $r );
				$msg  = '下書きを作りました。';
			}
		} elseif ( 'caseauto' === $do ) {
			update_option( 'apprex_case_auto', ! empty( $_POST['auto'] ) ? 1 : 0, false );
			$msg = '保存しました。';
		}
		wp_safe_redirect( add_query_arg( 'anmsg', rawurlencode( $msg ), $back ) );
		exit;
	}
);

function apprex_an_msg() {
	if ( isset( $_GET['anmsg'] ) && '' !== $_GET['anmsg'] ) { // phpcs:ignore WordPress.Security.NonceVerification
		echo '<div class="notice notice-info inline"><p>' . esc_html( rawurldecode( wp_unslash( $_GET['anmsg'] ) ) ) . '</p></div>'; // phpcs:ignore
	}
}

function apprex_an_page() {
	$act  = esc_url( admin_url( 'admin-post.php' ) );
	$news = get_posts( array( 'post_type' => 'post', 'post_status' => array( 'publish', 'draft' ), 'posts_per_page' => 10, 'meta_key' => 'apprex_an_auto' ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$hols = apprex_an_holidays();
	?>
	<div class="wrap">
		<h1>AIでお知らせ</h1>
		<?php apprex_an_msg(); ?>
		<p>トップページの「新着ニュース」（カテゴリー「ニュース」）に出すお知らせを、AIが書きます。<b>本当にあった出来事だけ</b>をもとに書き、日付や数字を作りません。</p>

		<h2>ひとことで、お知らせを作る</h2>
		<form method="post" action="<?php echo $act; // phpcs:ignore ?>" style="max-width:820px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px">
			<input type="hidden" name="action" value="apprex_an"><input type="hidden" name="do" value="memo"><?php wp_nonce_field( 'apprex_an' ); ?>
			<textarea name="memo" rows="4" class="large-text" placeholder="例：11月20日にいわき市で、飲食店向けのアプリ活用セミナーをします。参加無料・定員10名。申し込みはミーティング予約のページから。"></textarea>
			<p><label><input type="checkbox" name="publish" value="1"> すぐ公開する</label>（チェックなしなら下書きにします）　<?php submit_button( 'AIで書く', 'primary', 'submit', false ); ?></p>
		</form>

		<h2>自動のお知らせ</h2>
		<form method="post" action="<?php echo $act; // phpcs:ignore ?>" style="max-width:820px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:12px 16px">
			<input type="hidden" name="action" value="apprex_an"><input type="hidden" name="do" value="settings"><?php wp_nonce_field( 'apprex_an' ); ?>
			<p><label><input type="checkbox" name="case" value="1" <?php checked( apprex_an_opt( 'case' ) ); ?>> 導入事例を公開したとき</label></p>
			<p><label><input type="checkbox" name="holiday" value="1" <?php checked( apprex_an_opt( 'holiday' ) ); ?>> お休みの14日前（ミーティング予約の「お休みの日」が2日以上続くとき）</label>
				<br><span class="description">いま入っているお休み：<?php echo $hols ? esc_html( implode( '、', array_map( function ( $h ) { return $h[0] === $h[1] ? $h[0] : $h[0] . '〜' . $h[1]; }, $hols ) ) ) : 'なし'; ?>　<a href="<?php echo esc_url( admin_url( 'admin.php?page=apprex-meeting' ) ); ?>">お休みの日を変える</a></span></p>
			<p><label><input type="checkbox" name="monthly" value="1" <?php checked( apprex_an_opt( 'monthly' ) ); ?>> 毎月はじめに「先月の新着コラム」のまとめ（2本以上あったとき）</label></p>
			<p><label><input type="checkbox" name="publish" value="1" <?php checked( apprex_an_opt( 'publish' ) ); ?>> 自動のお知らせは、そのまま公開する</label>（チェックなしなら下書きにして通知します）</p>
			<?php submit_button( '保存', 'secondary', 'submit', false ); ?>
		</form>
		<form method="post" action="<?php echo $act; // phpcs:ignore ?>" style="margin-top:8px"><input type="hidden" name="action" value="apprex_an"><input type="hidden" name="do" value="runnow"><?php wp_nonce_field( 'apprex_an' ); ?><?php submit_button( 'いま確かめる（お休み・先月のまとめ）', 'secondary', 'submit', false ); ?></form>

		<h2>AIが作ったお知らせ</h2>
		<table class="widefat striped" style="max-width:820px"><tbody>
		<?php if ( ! $news ) : ?><tr><td>まだありません。</td></tr><?php endif; ?>
		<?php foreach ( $news as $p ) : ?>
			<tr><td style="width:110px"><?php echo esc_html( get_the_date( 'Y/n/j', $p ) ); ?></td><td><a href="<?php echo esc_url( get_edit_post_link( $p ) ); ?>"><?php echo esc_html( $p->post_title ); ?></a></td><td style="width:70px"><?php echo 'publish' === $p->post_status ? '公開' : '下書き'; ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	<?php
}

function apprex_case_page() {
	$act   = esc_url( admin_url( 'admin-post.php' ) );
	$users = apprex_case_finished_users();
	?>
	<div class="wrap">
		<h1>AIで事例を作る</h1>
		<?php apprex_an_msg(); ?>
		<p><b>本当のお客様の記録</b>から、AIが導入事例の下書きを作ります。数字やお客様の言葉は作りません。<br>
		お客様から<b>掲載の許可</b>をもらって、編集画面の右の「掲載の許可」にチェックを入れると公開できます（チェックがないと公開できません）。公開すると、お知らせも自動で出ます。</p>

		<h2>制作が終わったお客様</h2>
		<form method="post" action="<?php echo $act; // phpcs:ignore ?>" style="margin-bottom:8px"><input type="hidden" name="action" value="apprex_an"><input type="hidden" name="do" value="caseauto"><?php wp_nonce_field( 'apprex_an' ); ?>
			<label><input type="checkbox" name="auto" value="1" <?php checked( (int) get_option( 'apprex_case_auto', 1 ) ); ?> onchange="this.form.submit()"> 制作が「公開」まで終わったら、自動で下書きを作って知らせる</label></form>
		<table class="widefat striped" style="max-width:900px"><tbody>
		<?php if ( ! $users ) : ?><tr><td>まだいません（お客様の「制作の進み具合」が「公開」まで進むと、ここに出ます）。</td></tr><?php endif; ?>
		<?php foreach ( $users as $uid ) : ?>
			<?php $u = get_userdata( $uid ); $did = (int) get_user_meta( $uid, 'apprex_case_drafted', true ); ?>
			<tr><td><?php echo esc_html( $u ? $u->display_name : '' ); ?></td>
				<td><?php echo $did && get_post( $did ) ? '<a href="' . esc_url( get_edit_post_link( $did ) ) . '">事例（' . ( 'publish' === get_post_status( $did ) ? '公開中' : '下書き' ) . '）</a>' : '—'; ?></td>
				<td style="width:200px"><form method="post" action="<?php echo $act; // phpcs:ignore ?>"><input type="hidden" name="action" value="apprex_an"><input type="hidden" name="do" value="case"><input type="hidden" name="uid" value="<?php echo (int) $uid; ?>"><?php wp_nonce_field( 'apprex_an' ); ?><?php submit_button( $did ? 'もう一度AIで下書き' : 'AIで下書き', 'secondary', 'submit', false ); ?></form></td></tr>
		<?php endforeach; ?>
		</tbody></table>

		<h2>手で入れて作る（サイトの外で作ったお客様など）</h2>
		<form method="post" action="<?php echo $act; // phpcs:ignore ?>" style="max-width:900px;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:8px 16px">
			<input type="hidden" name="action" value="apprex_an"><input type="hidden" name="do" value="case"><?php wp_nonce_field( 'apprex_an' ); ?>
			<table class="form-table" role="presentation">
				<tr><th>業種</th><td><input name="industry" class="regular-text" placeholder="例：美容室"></td></tr>
				<tr><th>地域</th><td><input name="area" class="regular-text" placeholder="例：福島県いわき市"></td></tr>
				<tr><th>載せる名前</th><td><input name="name" class="regular-text" placeholder="例：ヘアサロン〇〇様（空なら「いわき市の美容室様」のように名前を出さない）"></td></tr>
				<tr><th>困っていたこと</th><td><textarea name="problem" rows="3" class="large-text" placeholder="例：紙のポイントカードをなくすお客様が多い。予約が電話だけで、営業中に手が止まる。"></textarea></td></tr>
				<tr><th>作ったもの</th><td><textarea name="made" rows="3" class="large-text" placeholder="例：予約・ポイントカード・クーポン・お知らせのアプリ（ノーコード・3週間）"></textarea></td></tr>
				<tr><th>結果</th><td><textarea name="result" rows="3" class="large-text" placeholder="お客様から聞いたことだけ。例：公開1か月で会員が120人。分からなければ空のまま"></textarea></td></tr>
				<tr><th>メモ</th><td><textarea name="memo" rows="2" class="large-text"></textarea></td></tr>
			</table>
			<?php submit_button( 'AIで下書きを作る' ); ?>
		</form>
	</div>
	<?php
}
