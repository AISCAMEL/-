<?php
/**
 * AI記事生成（手動＋自動投稿）＋ アイキャッチAI生成（Nano Banana 等）。
 *
 * - 投稿 > AI記事生成：手動生成（画像生成オプション付き）
 * - 自動投稿：トピックキューから定期的に生成・公開（cron）
 * - 公開時に SNS連動（blog.php の transition_post_status フックが発火）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 自動投稿の初期トピック（10本）。設定が空のときの既定値。
 *
 * @return string
 */
function apprex_default_autopost_topics() {
	return implode(
		"\n",
		array(
			'ノーコードアプリ開発のメリットと始め方',
			'飲食店アプリで再来店を増やす5つの方法',
			'美容サロンの予約・会員アプリ導入事例',
			'マッチングアプリ開発の費用と期間の目安',
			'アプリとホームページの使い分け',
			'プッシュ通知で売上を伸ばすコツ',
			'補助金を活用したアプリ・DX導入',
			'スタンプカード・クーポンのアプリ活用術',
			'多店舗展開に強い店舗アプリの作り方',
			'MEO・LLMOで集客を強化する方法',
		)
	);
}

/** 「調べて決める」で探す分野（既定）。 */
function apprex_ai_research_focus() {
	$v = trim( (string) get_option( 'apprex_autopost_focus', '' ) );
	if ( '' !== $v ) {
		return $v;
	}
	return implode(
		"\n",
		array(
			'アプリ開発の技術・コード（AIによる開発、ノーコード・ローコード、Flutter など）',
			'App Store・Google Play の新しいルールや動向',
			'店舗・中小企業のアプリ活用（予約・会員証・ポイント・プッシュ通知）',
			'LINE公式アカウント・LINEミニアプリの新機能',
			'アプリと集客（SNS・MEO・AI検索）',
		)
	);
}

/**
 * 「調べて決める」の依頼文。最近の記事タイトルを渡して、同じテーマを避ける。
 *
 * @param string $tone   トーン。
 * @param int    $length 文字数。
 * @return string
 */
function apprex_ai_research_prompt( $tone, $length, $hint = '' ) {
	$recent = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'future', 'draft' ),
			'posts_per_page' => 40,
			'fields'         => 'ids',
		)
	);
	$titles = array_map( 'get_the_title', $recent );
	return "今日は" . wp_date( 'Y年n月j日' ) . "です。APPREXのブログに載せる記事を1本作ってください。\n\n"
		. "手順：\n"
		. "1. Web検索で、次の分野のここ1〜2週間ほどの新しいニュース・発表・話題を調べる（日本の情報を優先。公式発表や信頼できるメディアを使う）。\n"
		. apprex_ai_research_focus() . "\n"
		. ( '' !== $hint ? "今回は特に「{$hint}」の分野から話題を選ぶ（同じ日のほかの記事と分野が重ならないように）。\n" : '' )
		. "2. その中から、店舗や中小企業の経営者が「自分ごと」として読める話題を1つ選ぶ。下の既存記事と同じテーマは避ける。\n"
		. "3. 何が起きたか（事実）→ 店舗・中小企業にとって何が変わるか → どう活かすか、の順で解説し、最後の章で APPREX（ノーコードのアプリ開発・AIスタジオ・LINEのLABOなど）でできることと自然につなげる。宣伝ばかりにしない。\n\n"
		. "条件：トーンは{$tone}文体。本文は約{$length}字。調べて分からなかったことは書かない。日付や数字は調べた情報のとおりに書く。\n"
		. "出力形式（厳守）：1行目に「TITLE: 記事タイトル（32字前後。検索されやすい言葉を入れる）」。2行目以降に本文を HTML（<h2>,<h3>,<p>,<ul><li> のみ、<h1>不可）で。前置きやあいさつは書かない。\n"
		. "TITLE の行より前と本文の最初に、作業の説明（「調べました」「以下が記事です」「出力します」など）は一切書かない。\n"
		. "Web検索ができなかった・十分な情報が見つからなかったときは、記事を書かずに「ERROR: 理由」の1行だけを出力する。\n\n"
		. "既存記事（同じテーマは避ける）：\n- " . implode( "\n- ", array_slice( array_filter( $titles ), 0, 40 ) );
}

/**
 * AIの本文から、記事ではない部分（作業の説明・前置き）を外す。
 *
 * @param string $body 本文（HTML）。
 * @return string
 */
function apprex_ai_clean_body( $body ) {
	// 最初のタグ（見出し・段落・リスト）より前の地の文を外す。
	if ( preg_match( '/<(h2|h3|p|ul|ol)\b/i', $body, $m, PREG_OFFSET_CAPTURE ) && $m[0][1] > 0 ) {
		$body = substr( $body, $m[0][1] );
	}
	// 先頭の短い段落が、作業の説明なら外す（2つまで）。
	$meta = '/TITLE|出力|以下(が|に|の)|記事を(書|作|お届け|まとめ)|調べ(た|まし)|検索し|HTML|作成しました|お待たせ/u';
	for ( $i = 0; $i < 2; $i++ ) {
		if ( ! preg_match( '/^\s*<p[^>]*>(.*?)<\/p>\s*/su', $body, $m ) ) {
			break;
		}
		$t = trim( wp_strip_all_tags( $m[1] ) );
		if ( mb_strlen( $t ) > 140 || ! preg_match( $meta, $t ) ) {
			break;
		}
		$body = substr( $body, strlen( $m[0] ) );
	}
	return trim( $body );
}

/**
 * 本文の見出しの下に、AIで作った写真を入れる（安い画像モデル・1枚約1円）。
 *
 * @param int    $post_id 投稿ID。
 * @param string $title   記事タイトル。
 * @param int    $count   入れる枚数（0〜3）。
 * @return int 入れた枚数。
 */
function apprex_ai_body_images( $post_id, $title, $count ) {
	$count = max( 0, min( 3, (int) $count ) );
	if ( ! $count || ! function_exists( 'apprex_openrouter_image' ) ) {
		return 0;
	}
	$content = (string) get_post_field( 'post_content', $post_id );
	if ( ! preg_match_all( '/<h2[^>]*>(.*?)<\/h2>/su', $content, $hs, PREG_OFFSET_CAPTURE ) ) {
		return 0;
	}
	// 「参考」「まとめ」の見出しには入れない。残りの見出しから均等に選ぶ。
	$cands = array();
	foreach ( $hs[0] as $i => $h ) {
		$text = trim( wp_strip_all_tags( $hs[1][ $i ][0] ) );
		if ( preg_match( '/参考|まとめ|出典|おわりに/u', $text ) ) {
			continue;
		}
		$cands[] = array( 'end' => $h[1] + strlen( $h[0] ), 'text' => $text );
	}
	if ( ! $cands ) {
		return 0;
	}
	$picks = array();
	$step  = max( 1, count( $cands ) / $count );
	for ( $k = 0; $k < $count && (int) floor( $k * $step ) < count( $cands ); $k++ ) {
		$picks[] = $cands[ (int) floor( $k * $step ) ];
	}
	// 画質：標準＝安い画像モデル（約1円）／高画質＝通常の画像モデル（約10円）。
	$model = 'high' === get_option( 'apprex_ai_body_img_quality', 'std' ) ? '' : ( function_exists( 'apprex_bulk_image_model' ) ? apprex_bulk_image_model() : '' );
	$done  = 0;
	// 後ろから入れると、前の位置がずれない。
	foreach ( array_reverse( $picks ) as $pk ) {
		$prompt = 'Photorealistic editorial photograph for a Japanese business blog article. Article: "' . $title . '". Section: "' . $pk['text'] . '". '
			. 'Show a realistic scene that illustrates the section for small shop owners in Japan (shops, smartphones, apps, customers, staff). Natural light, clean and modern, 16:9 horizontal. No text, no letters, no logos, no watermark.';
		$img = apprex_openrouter_image( $prompt, $model ? array( 'model' => $model ) : array() );
		if ( is_wp_error( $img ) ) {
			continue;
		}
		$att = apprex_save_generated_image( $img, $title . ' ' . $pk['text'], $post_id );
		if ( is_wp_error( $att ) ) {
			continue;
		}
		$src = wp_get_attachment_image_url( $att, 'large' );
		$src = $src ? $src : wp_get_attachment_url( $att );
		$fig = "\n" . '<figure class="wp-block-image size-large ap-body-img"><img src="' . esc_url( $src ) . '" alt="' . esc_attr( $pk['text'] ) . '" class="wp-image-' . (int) $att . '" loading="lazy"/></figure>' . "\n";
		$content = substr( $content, 0, $pk['end'] ) . $fig . substr( $content, $pk['end'] );
		$done++;
	}
	if ( $done ) {
		wp_update_post( array( 'ID' => $post_id, 'post_content' => $content ) );
	}
	return $done;
}

/** 記事を書くAIへの指示（新しく書く・見直すで共通）。 */
function apprex_ai_article_system() {
	return 'あなたは APPREX（アプリックス／ノーコードアプリ開発・合同会社アイズ）のオウンドメディア編集者です。読者の課題解決に役立つ、SEO/LLMOを意識した日本語ブログ記事を作成します。誇大表現や事実誤認を避け、自然にAPPREXの活用に触れます。「即日公開」は使わず「最短2週間」と表現します。全国の読者に届く一般性のある内容にします。'
		. "\n守ること：APPREXの導入事例・お客様の声・数字（〇〇%増など）・社名や業種のA社B社の例を作らない（実在しない事例は書かない）。導入社数などの実績の数字は書かない。APPREXはIT導入補助金の支援事業者ではないので、APPREXをIT導入補助金で導入できるとは書かない（補助金は一般的な制度の紹介にとどめ、APPREXについては持続化補助金・自治体の補助金の相談ができる、と書く）。"
		. "\nAPPREXの会社情報：運営は合同会社アイズ（福島県いわき市）。オンラインで全国対応。"
		. ( function_exists( 'apprex_pricing_summary_text' ) ? "\nAPPREXの料金・期間はこの情報だけを使う：\n" . apprex_pricing_summary_text() : '' );
}

/* -------------------------------------------------------------------------
 * 重複防止・自動301（SEO最適化）
 * ---------------------------------------------------------------------- */

/** タイトル/トピックを比較用に正規化（空白・記号除去・小文字化）。 */
function apprex_ai_norm( $s ) {
	$s = wp_strip_all_tags( (string) $s );
	$s = mb_strtolower( $s, 'UTF-8' );
	// 空白・約物・記号を除去（日英）。
	$s = preg_replace( '/[\s\x{3000}、。・！？!?「」『』（）\(\)\[\]{}\-—…,.:：;；\/\\\\|~〜"\'’”“]+/u', '', $s );
	return (string) $s;
}

/** 2つの文字列の類似度（0〜100）。 */
function apprex_ai_similarity( $a, $b ) {
	$a = apprex_ai_norm( $a );
	$b = apprex_ai_norm( $b );
	if ( '' === $a || '' === $b ) {
		return 0;
	}
	if ( $a === $b ) {
		return 100;
	}
	$pct = 0;
	similar_text( $a, $b, $pct );
	return (float) $pct;
}

/**
 * 与えられたタイトル/トピックに類似する既存記事のIDを返す（無ければ0）。
 *
 * @param string $title     判定対象。
 * @param int    $exclude   除外する投稿ID。
 * @param float  $threshold 類似度しきい値（％）。
 * @return int 類似記事ID（0＝重複なし）。
 */
function apprex_ai_find_similar( $title, $exclude = 0, $threshold = 80 ) {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => array( 'publish', 'future', 'draft', 'pending' ),
			'posts_per_page' => 800,
			'fields'         => 'ids',
			'exclude'        => $exclude ? array( (int) $exclude ) : array(),
			'no_found_rows'  => true,
		)
	);
	$best_id = 0;
	$best    = 0.0;
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, '_apprex_301_to', true ) ) {
			continue; // すでに301済みは対象外。
		}
		$pct = apprex_ai_similarity( $title, get_the_title( $id ) );
		if ( $pct > $best ) {
			$best    = $pct;
			$best_id = (int) $id;
		}
	}
	return $best >= $threshold ? $best_id : 0;
}

/**
 * 公開記事をスキャンし、類似（重複）記事を古い方（正規）へ自動301する。
 * 新しい重複側に _apprex_301_to（正規記事ID）を付与し、SEO評価を集約。
 *
 * @return int 301化した件数。
 */
function apprex_ai_dedupe_scan() {
	$ids = get_posts(
		array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'fields'         => 'ids',
			'orderby'        => 'date',
			'order'          => 'ASC', // 古い順＝先に見た方を正規にする。
			'no_found_rows'  => true,
		)
	);
	$canon     = array(); // 正規記事のID配列。
	$redirects = 0;
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, '_apprex_301_to', true ) ) {
			continue;
		}
		$match = 0;
		foreach ( $canon as $cid ) {
			if ( apprex_ai_similarity( get_the_title( $id ), get_the_title( $cid ) ) >= 82 ) {
				$match = $cid;
				break;
			}
		}
		if ( $match ) {
			update_post_meta( $id, '_apprex_301_to', $match ); // 新しい重複→古い正規へ301。
			$redirects++;
		} else {
			$canon[] = $id;
		}
	}
	update_option( 'apprex_ai_dedupe_last', array( 'time' => time(), 'redirects' => $redirects ), false );
	return $redirects;
}

/* 重複記事は正規記事へ301リダイレクト（SEO評価の集約）。 */
add_action( 'template_redirect', function () {
	if ( ! is_singular( 'post' ) ) {
		return;
	}
	$to = (int) get_post_meta( get_queried_object_id(), '_apprex_301_to', true );
	if ( $to && 'publish' === get_post_status( $to ) ) {
		$url = get_permalink( $to );
		if ( $url ) {
			wp_safe_redirect( $url, 301 );
			exit;
		}
	}
} );

/* -------------------------------------------------------------------------
 * 記事生成（再利用関数）
 * ---------------------------------------------------------------------- */

/**
 * AIで記事を生成して投稿を作成。
 *
 * @param array $args topic, keywords, tone, length, publish, gen_image.
 * @return array|WP_Error { post_id, title, image_note }
 */
function apprex_ai_generate_post( $args ) {
	$topic    = sanitize_text_field( $args['topic'] ?? '' );
	$keywords = sanitize_text_field( $args['keywords'] ?? '' );
	$tone     = sanitize_text_field( $args['tone'] ?? '専門的で信頼感のある' );
	$length   = (int) ( $args['length'] ?? 2000 );
	$publish  = ! empty( $args['publish'] );
	$gen_img  = ! empty( $args['gen_image'] );
	$force    = ! empty( $args['force'] );
	$research = ! empty( $args['research'] );

	if ( '' === $topic && ! $research ) {
		return new WP_Error( 'no_topic', 'テーマが空です。' );
	}

	// 重複防止（生成前）：類似トピックの記事が既にあれば作らない。
	if ( ! $force && ! $research ) {
		$dup = apprex_ai_find_similar( $topic, 0, 80 );
		if ( $dup ) {
			return new WP_Error(
				'duplicate',
				sprintf( '類似記事が既にあるため生成を中止しました（重複量産の防止）：「%s」', get_the_title( $dup ) ),
				array( 'post_id' => $dup )
			);
		}
	}

	$system = apprex_ai_article_system();
	$user   = "次の条件でブログ記事を作成してください。\n"
		. "テーマ：{$topic}\n"
		. ( $keywords ? "SEOキーワード：{$keywords}\n" : '' )
		. "トーン：{$tone}文体\n"
		. "文字数：約{$length}字\n\n"
		. "出力形式（厳守）：1行目に「TITLE: 記事タイトル」。2行目以降に本文を HTML（<h2>,<h3>,<p>,<ul><li> のみ、<h1>不可）で。前置き不要。";

	$sources = array();
	if ( $research ) {
		// Claude が Web で最新の情報を調べて、テーマ選びから書く（毎日の自動投稿）。
		$r = apprex_claude_research( $system, apprex_ai_research_prompt( $tone, $length, isset( $args['focus_hint'] ) ? (string) $args['focus_hint'] : '' ), array( 'timeout' => 300, 'model' => apprex_claude_article_model() ) );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		$result  = $r['text'];
		$sources = $r['sources'];
	} else {
		$result = apprex_openrouter_complete(
			array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user', 'content' => $user ),
			),
			array_merge(
				array( 'temperature' => 0.7, 'max_tokens' => min( 4000, (int) round( $length * 2.2 ) ), 'timeout' => 90, 'article' => true ),
				function_exists( 'apprex_claude_for' ) && apprex_claude_for( 'article' ) ? array( 'model' => apprex_claude_article_model() ) : array()
			)
		);
	}
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$title = '';
	$body  = $result;
	if ( preg_match( '/^\s*TITLE\s*[:：]\s*(.+)$/m', $result, $m, PREG_OFFSET_CAPTURE ) ) {
		$title = trim( $m[1][0] );
		// TITLE の行より前（作業の説明など）は捨てて、その後ろだけを本文にする。
		$body = trim( substr( $result, $m[0][1] + strlen( $m[0][0] ) ) );
	}
	$body = apprex_ai_clean_body( $body );
	$title = $title ? $title : $topic;
	// 調べた記事：前置き（「調べました」など）を外し、出典を最後に。
	if ( $research ) {
		if ( ! preg_match( '/^\s*TITLE\s*[:：]/m', $result ) ) {
			return new WP_Error( 'no_title', '記事の形で返ってこなかったため、作りませんでした。' );
		}
		if ( $sources && false === mb_strpos( $body, '参考' ) ) {
			$body .= "\n<h2>参考にした情報</h2>\n<ul>";
			foreach ( $sources as $src ) {
				$body .= '<li><a href="' . esc_url( $src[0] ) . '" target="_blank" rel="noopener nofollow">' . esc_html( $src[1] ) . '</a></li>';
			}
			$body .= "</ul>\n";
		}
	}
	if ( '' === $title ) {
		return new WP_Error( 'no_title', '記事のタイトルを作れませんでした。' );
	}

	// 重複防止（生成後）：出来上がったタイトルが既存と酷似していれば公開しない。
	if ( ! $force ) {
		$dup = apprex_ai_find_similar( $title, 0, 82 );
		if ( $dup ) {
			return new WP_Error(
				'duplicate',
				sprintf( '生成結果が既存記事と酷似していたため中止しました：「%s」', get_the_title( $dup ) ),
				array( 'post_id' => $dup )
			);
		}
	}

	$post_id = wp_insert_post(
		array(
			'post_type'    => 'post',
			'post_status'  => $publish ? 'publish' : 'draft',
			'post_title'   => wp_strip_all_tags( $title ),
			'post_content' => wp_kses_post( $body ),
			// 自動投稿は、LINEへの配信を設定に合わせる（毎日LINEが届かないよう、既定は配信しない）。
			'meta_input'   => ! empty( $args['auto'] ) ? array( 'apprex_distribute_line' => get_option( 'apprex_autopost_line', 0 ) ? '1' : '0' ) : array(),
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	update_post_meta( $post_id, '_apprex_ai_generated', 1 );
	update_post_meta( $post_id, '_apprex_ai_topic_key', apprex_ai_norm( $topic ? $topic : $title ) );
	if ( $research ) {
		update_post_meta( $post_id, '_apprex_ai_research', 1 );
	}

	$image_note = '';
	if ( $gen_img && function_exists( 'apprex_openrouter_image' ) ) {
		$prompt = "ブログ記事のアイキャッチ画像。テーマ『{$title}』。清潔感のあるビジネス向けデザイン、明るいブルー基調、テキストは入れない、横長16:9。";
		$img    = apprex_openrouter_image( $prompt );
		if ( is_wp_error( $img ) ) {
			$image_note = '（画像生成はスキップ：' . $img->get_error_message() . '）';
		} else {
			$att = apprex_save_generated_image( $img, $title, $post_id );
			if ( ! is_wp_error( $att ) ) {
				set_post_thumbnail( $post_id, $att );
			} else {
				$image_note = '（画像保存に失敗）';
			}
		}
	}

	// 本文の見出しの下に写真を入れる。
	if ( ! empty( $args['body_images'] ) ) {
		$n          = apprex_ai_body_images( $post_id, $title, (int) $args['body_images'] );
		$image_note .= '（本文の写真 ' . $n . '枚）';
	}

	return array( 'post_id' => $post_id, 'title' => $title, 'image_note' => $image_note );
}

/* -------------------------------------------------------------------------
 * 管理画面：投稿 > AI記事生成
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php', 'AI記事生成', 'AI記事生成', 'edit_posts', 'apprex-ai-blog', 'apprex_ai_blog_page' );
} );

add_action( 'admin_init', function () {
	register_setting( 'apprex_aiblog', 'apprex_image_model', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_aiblog', 'apprex_autopost_enabled', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_aiblog', 'apprex_autopost_freq', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_aiblog', 'apprex_autopost_hour', array( 'sanitize_callback' => 'apprex_sanitize_hour' ) );
	register_setting( 'apprex_aiblog', 'apprex_autopost_image', array( 'sanitize_callback' => 'absint' ) );
	register_setting( 'apprex_aiblog', 'apprex_autopost_tone', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_aiblog', 'apprex_autopost_topics', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
	register_setting(
		'apprex_aiblog',
		'apprex_autopost_mode',
		array(
			'sanitize_callback' => function ( $v ) {
				return 'research' === $v ? 'research' : 'queue';
			},
		)
	);
	register_setting( 'apprex_aiblog', 'apprex_autopost_focus', array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
	register_setting( 'apprex_aiblog', 'apprex_autopost_line', array( 'sanitize_callback' => 'absint' ) );
	register_setting(
		'apprex_aiblog',
		'apprex_ai_body_img_quality',
		array(
			'sanitize_callback' => function ( $v ) {
				return 'high' === $v ? 'high' : 'std';
			},
		)
	);
	register_setting(
		'apprex_aiblog',
		'apprex_autopost_per_day',
		array(
			'sanitize_callback' => function ( $v ) {
				return max( 1, min( 3, absint( $v ) ) );
			},
		)
	);
	register_setting(
		'apprex_aiblog',
		'apprex_autopost_body_images',
		array(
			'sanitize_callback' => function ( $v ) {
				return max( 0, min( 3, absint( $v ) ) );
			},
		)
	);
} );

/**
 * 管理ページを描画。
 */
function apprex_ai_blog_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$uid    = get_current_user_id();
	$notice = get_transient( 'apprex_ai_blog_notice_' . $uid );
	if ( $notice ) {
		delete_transient( 'apprex_ai_blog_notice_' . $uid );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI記事生成', 'apprex' ); ?></h1>
		<p class="description"><?php echo esc_html( '記事に使うAI：' . ( function_exists( 'apprex_claude_for' ) && apprex_claude_for( 'article' ) ? 'Claude（' . apprex_claude_article_model() . '）' : 'OpenRouter（' . apprex_openrouter_model() . '）' ) . '。変更は 設定 → APPREX チャット の「Claude を使う範囲」「Claude のモデル（記事）」。' ); ?></p>

		<?php if ( ! apprex_chat_enabled() ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'OpenRouter APIキーが未設定です。設定 > APPREX チャット で登録してください。', 'apprex' ); ?></p></div>
		<?php endif; ?>
		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo wp_kses_post( $notice['msg'] ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Claude に最新の情報を調べて1記事つくってもらう', 'apprex' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px">
			<input type="hidden" name="action" value="apprex_generate_post">
			<input type="hidden" name="research" value="1">
			<input type="hidden" name="length" value="2500">
			<input type="hidden" name="tone" value="<?php echo esc_attr( get_option( 'apprex_autopost_tone', '専門的で信頼感のある' ) ); ?>">
			<?php wp_nonce_field( 'apprex_generate_post' ); ?>
			<p class="description"><?php esc_html_e( '「調べる分野」（下の自動投稿の設定）のニュースを Claude が Web で調べ、テーマを選んで書きます。下書きで保存するので、内容を確かめてから公開してください。1〜3分かかります。', 'apprex' ); ?></p>
			<p><label><input type="checkbox" name="gen_image" value="1"> <?php esc_html_e( 'アイキャッチ画像もAIで作る（約10円）', 'apprex' ); ?></label>
				<label style="margin-left:16px"><?php esc_html_e( '本文の写真', 'apprex' ); ?> <select name="body_images"><option value="0">入れない</option><option value="1">1枚</option><option value="2" selected>2枚</option><option value="3">3枚</option></select> <?php esc_html_e( '（1枚約1円）', 'apprex' ); ?></label></p>
			<?php submit_button( 'Claude に調べて書いてもらう（下書き）', 'primary', 'submit', false, ( function_exists( 'apprex_ai_use_claude' ) && apprex_ai_use_claude() ) ? array() : array( 'disabled' => 'disabled' ) ); ?>
			<?php if ( ! ( function_exists( 'apprex_ai_use_claude' ) && apprex_ai_use_claude() ) ) : ?><span class="description" style="margin-left:8px"><?php esc_html_e( '先に Claude APIキーを入れて保存してください（設定 → APPREX チャット）。', 'apprex' ); ?></span><?php endif; ?>
			<?php $apprex_lr = get_option( 'apprex_autopost_last_result', '' ); ?>
			<?php if ( $apprex_lr ) : ?><p class="description"><?php echo esc_html( '前回の自動投稿：' . $apprex_lr ); ?></p><?php endif; ?>
		</form>

		<hr>
		<h2><?php esc_html_e( '今すぐ1記事つくる（テーマを指定）', 'apprex' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px">
			<input type="hidden" name="action" value="apprex_generate_post">
			<?php wp_nonce_field( 'apprex_generate_post' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="ab-topic"><?php esc_html_e( 'テーマ・キーワード', 'apprex' ); ?> <span style="color:#d63638">*</span></label></th>
					<td><input name="topic" id="ab-topic" type="text" class="regular-text" required placeholder="例：飲食店がアプリで再来店を増やす方法"></td></tr>
				<tr><th><label for="ab-keywords"><?php esc_html_e( 'SEOキーワード', 'apprex' ); ?></label></th>
					<td><input name="keywords" id="ab-keywords" type="text" class="regular-text" placeholder="ノーコード, 集客, モバイルオーダー"></td></tr>
				<tr><th><label for="ab-tone"><?php esc_html_e( 'トーン', 'apprex' ); ?></label></th>
					<td><select name="tone" id="ab-tone">
						<option value="専門的で信頼感のある">専門的・信頼感</option>
						<option value="親しみやすく初心者向けの">親しみやすい・初心者向け</option>
						<option value="説得力のあるセールス">セールス寄り</option>
					</select></td></tr>
				<tr><th><label for="ab-length"><?php esc_html_e( '文字数', 'apprex' ); ?></label></th>
					<td><select name="length" id="ab-length"><option value="1200">約1,200字</option><option value="2000" selected>約2,000字</option><option value="3000">約3,000字</option></select></td></tr>
				<tr><th><?php esc_html_e( 'アイキャッチ画像', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="gen_image" value="1"> <?php esc_html_e( 'AIで自動生成する（Nano Banana 等の画像モデル）', 'apprex' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( '本文の写真', 'apprex' ); ?></th>
					<td><select name="body_images"><option value="0">入れない</option><option value="1">1枚</option><option value="2" selected>2枚</option><option value="3">3枚</option></select> <span class="description"><?php esc_html_e( '見出しの下にAIの写真を入れます（1枚約1円）', 'apprex' ); ?></span></td></tr>
				<tr><th><?php esc_html_e( '公開', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="publish" value="1"> <?php esc_html_e( '生成後すぐ公開（既定は下書き）', 'apprex' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( '重複チェック', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="force" value="1"> <?php esc_html_e( '類似記事があっても強制生成する（通常はOFF推奨）', 'apprex' ); ?></label>
					<p class="description"><?php esc_html_e( '既定では、似たテーマの記事が既にある場合は重複量産を防ぐため生成しません。', 'apprex' ); ?></p></td></tr>
			</table>
			<?php submit_button( 'AIで記事を生成', 'primary', 'submit', true, apprex_chat_enabled() ? array() : array( 'disabled' => 'disabled' ) ); ?>
			<p class="description"><?php esc_html_e( '公開前に内容・事実関係を必ずご確認ください。', 'apprex' ); ?></p>
		</form>

		<hr>
		<h2><?php esc_html_e( '自動投稿の設定', 'apprex' ); ?></h2>
		<form method="post" action="options.php" style="max-width:720px">
			<?php settings_fields( 'apprex_aiblog' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><?php esc_html_e( '自動投稿', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="apprex_autopost_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_autopost_enabled', 0 ) ); ?>> <?php esc_html_e( '有効にする（トピックから定期的に生成・公開）', 'apprex' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( '頻度', 'apprex' ); ?></th>
					<td><select name="apprex_autopost_freq">
						<?php $f = get_option( 'apprex_autopost_freq', 'weekly' ); ?>
						<option value="daily" <?php selected( $f, 'daily' ); ?>>毎日</option>
						<option value="weekly" <?php selected( $f, 'weekly' ); ?>>週1回</option>
						<option value="biweekly" <?php selected( $f, 'biweekly' ); ?>>2週に1回</option>
					</select>
					<label style="margin-left:12px"><?php esc_html_e( '毎日のとき1日に', 'apprex' ); ?>
						<select name="apprex_autopost_per_day"><?php $apprex_pd = (int) get_option( 'apprex_autopost_per_day', 1 ); ?>
							<?php foreach ( array( 1, 2, 3 ) as $apprex_n ) : ?><option value="<?php echo (int) $apprex_n; ?>" <?php selected( $apprex_pd, $apprex_n ); ?>><?php echo (int) $apprex_n; ?>本</option><?php endforeach; ?>
						</select></label>
					<p class="description"><?php esc_html_e( '2本以上のときは、投稿時刻から4時間おきに出します。「調べる分野」を順番に変えて、同じ日に似た記事が並ばないようにします。', 'apprex' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( '投稿時刻', 'apprex' ); ?></th>
						<td><select name="apprex_autopost_hour">
							<?php
							$sel_hour = (int) get_option( 'apprex_autopost_hour', 10 );
							for ( $h = 0; $h < 24; $h++ ) :
								?>
								<option value="<?php echo esc_attr( $h ); ?>" <?php selected( $sel_hour, $h ); ?>><?php echo esc_html( sprintf( '%02d:00', $h ) ); ?></option>
							<?php endfor; ?>
						</select>
						<span style="margin-left:8px;color:#666"><?php esc_html_e( '（日本時間／この時刻以降の最初のアクセスで実行）', 'apprex' ); ?></span>
						<?php
						$apprex_next = wp_next_scheduled( 'apprex_autopost_cron' );
						if ( $apprex_next ) :
							?>
							<p class="description"><?php printf( esc_html__( '次回の自動実行予定：%s', 'apprex' ), esc_html( wp_date( 'Y年n月j日(D) H:i', $apprex_next ) ) ); ?></p>
						<?php endif; ?>
						</td></tr>
					<tr><th><?php esc_html_e( 'LINEへの配信', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="apprex_autopost_line" value="1" <?php checked( 1, (int) get_option( 'apprex_autopost_line', 0 ) ); ?>> <?php esc_html_e( '自動投稿した記事も、公開時にLINEの友だちへ配信する', 'apprex' ); ?></label>
					<p class="description"><?php esc_html_e( '毎日投稿で配信すると、友だちに毎日LINEが届き、LINEの無料メッセージ数も早く減ります。ふだんはOFFにして、選んだ記事だけ投稿の編集画面から配信するのがおすすめです。', 'apprex' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( '本文の写真', 'apprex' ); ?></th>
					<td><select name="apprex_autopost_body_images"><?php $apprex_bi = (int) get_option( 'apprex_autopost_body_images', 2 ); ?>
						<?php foreach ( array( 0 => '入れない', 1 => '1枚', 2 => '2枚（おすすめ）', 3 => '3枚' ) as $apprex_k => $apprex_l ) : ?><option value="<?php echo esc_attr( $apprex_k ); ?>" <?php selected( $apprex_bi, $apprex_k ); ?>><?php echo esc_html( $apprex_l ); ?></option><?php endforeach; ?>
					</select>
					<select name="apprex_ai_body_img_quality" style="margin-left:8px"><?php $apprex_q = get_option( 'apprex_ai_body_img_quality', 'std' ); ?>
						<option value="std" <?php selected( $apprex_q, 'std' ); ?>>標準（1枚約1円）</option>
						<option value="high" <?php selected( $apprex_q, 'high' ); ?>>高画質（1枚約10円）</option>
					</select>
					<p class="description"><?php echo esc_html( '見出しの下に、内容に合う写真をAIで作って入れます。標準：' . ( function_exists( 'apprex_bulk_image_model' ) ? apprex_bulk_image_model() : '' ) . '／高画質：' . ( function_exists( 'apprex_image_model' ) ? apprex_image_model() : '' ) . '。画質は記事の見直し・「AIで写真を入れる」にも使います。' ); ?></p></td></tr>
					<tr><th><?php esc_html_e( 'アイキャッチAI生成', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="apprex_autopost_image" value="1" <?php checked( 1, (int) get_option( 'apprex_autopost_image', 0 ) ); ?>> <?php esc_html_e( '自動投稿でも画像を生成する', 'apprex' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'トーン', 'apprex' ); ?></th>
					<td><input type="text" name="apprex_autopost_tone" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_autopost_tone', '専門的で信頼感のある' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'テーマの決め方', 'apprex' ); ?></th>
					<td><?php $apprex_mode = get_option( 'apprex_autopost_mode', 'queue' ); ?>
						<label><input type="radio" name="apprex_autopost_mode" value="research" <?php checked( 'research', $apprex_mode ); ?>> <?php esc_html_e( 'Claude が毎回Webで最新の情報を調べて決める（おすすめ）', 'apprex' ); ?></label><br>
						<label><input type="radio" name="apprex_autopost_mode" value="queue" <?php checked( 'queue', $apprex_mode ); ?>> <?php esc_html_e( '下のトピック一覧から順番に', 'apprex' ); ?></label>
						<p class="description"><?php esc_html_e( '「調べて決める」は Claude APIキーが必要です（設定 → APPREX チャット）。アプリ開発のニュース・技術・活用事例を調べ、APPREXの視点と組み合わせて書きます。参考にした記事へのリンクを最後に付けます。', 'apprex' ); ?><?php if ( 'research' === $apprex_mode && ! ( function_exists( 'apprex_ai_use_claude' ) && apprex_ai_use_claude() ) ) : ?><br><b style="color:#b91c1c"><?php esc_html_e( 'Claude APIキーが未設定のため、いまはトピック一覧から順番に作ります。', 'apprex' ); ?></b><?php endif; ?></p></td></tr>
				<tr><th><?php esc_html_e( '調べる分野', 'apprex' ); ?></th>
					<td><textarea name="apprex_autopost_focus" rows="4" class="large-text"><?php echo esc_textarea( apprex_ai_research_focus() ); ?></textarea>
					<p class="description"><?php esc_html_e( '「調べて決める」で、どの分野のニュースを探すか（1行に1つ）。', 'apprex' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( 'トピック一覧（1行に1テーマ）', 'apprex' ); ?></th>
					<td><textarea name="apprex_autopost_topics" rows="8" class="large-text" placeholder="ノーコードアプリ開発のメリット&#10;飲食店アプリで再来店を増やす方法&#10;補助金を活用したアプリ導入&#10;アプリとホームページの使い分け"><?php echo esc_textarea( get_option( 'apprex_autopost_topics', apprex_default_autopost_topics() ) ); ?></textarea>
					<p class="description"><?php esc_html_e( '上から順に1記事ずつ自動生成します（一巡したら先頭へ戻ります）。', 'apprex' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( '画像モデル', 'apprex' ); ?></th>
					<td><input type="text" name="apprex_image_model" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_image_model', '' ) ); ?>" placeholder="<?php echo esc_attr( APPREX_IMAGE_DEFAULT_MODEL ); ?>">
					<p class="description"><?php esc_html_e( '未入力時は Gemini Image（通称 Nano Banana / google/gemini-2.5-flash-image）。OpenRouterの画像対応モデルIDを指定できます。', 'apprex' ); ?></p></td></tr>
			</table>
			<?php submit_button( '保存', 'secondary' ); ?>
		</form>
		<p class="description"><?php esc_html_e( '自動投稿は「投稿時刻」を基準に1日1回判定され、頻度（毎日/週1/隔週）に達していれば公開します。WordPress標準のcronはアクセス時に動くため、指定時刻ちょうどに出すにはサーバーのcronで wp-cron.php を定期実行する設定（wpXの自動実行など）を推奨します。', 'apprex' ); ?></p>

		<hr>
		<h2><?php esc_html_e( '重複記事の自動整理（301最適化）', 'apprex' ); ?></h2>
		<p class="description"><?php esc_html_e( '公開済みの記事をスキャンし、内容が重複・酷似している記事を検出して、古い（正規）記事へ自動で301リダイレクトします。検索評価を1本に集約でき、重複ペナルティを防げます。自動投稿の実行時にも毎回チェックされます。', 'apprex' ); ?></p>
		<?php $dd = (array) get_option( 'apprex_ai_dedupe_last', array() ); ?>
		<?php if ( ! empty( $dd['time'] ) ) : ?>
			<p style="color:#6b7280;"><?php printf( esc_html__( '前回の整理：%1$s ／ 301化した記事 %2$d件', 'apprex' ), esc_html( wp_date( 'Y-m-d H:i', (int) $dd['time'] ) ), (int) ( $dd['redirects'] ?? 0 ) ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_ai_dedupe">
			<?php wp_nonce_field( 'apprex_ai_dedupe' ); ?>
			<?php submit_button( '重複記事をスキャンして301整理', 'secondary', 'submit', false ); ?>
			<span style="margin-left:8px;color:#9ca3af;"><?php esc_html_e( '※ 重複と判定された新しい記事は、古い記事へ自動転送されます（記事は残ります）。', 'apprex' ); ?></span>
		</form>

		<hr>
		<h2><?php esc_html_e( '画像生成テスト（診断）', 'apprex' ); ?></h2>
		<p class="description"><?php esc_html_e( 'アイキャッチが生成されない場合、ここでテスト実行するとエラー内容（モデル未対応・権限・残高不足など）が表示されます。生成できた画像はメディアに保存されます。', 'apprex' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_test_image">
			<?php wp_nonce_field( 'apprex_test_image' ); ?>
			<?php submit_button( '画像生成をテストする', 'secondary', 'submit', false, apprex_chat_enabled() ? array() : array( 'disabled' => 'disabled' ) ); ?>
			<span style="margin-left:8px;color:#666;"><?php echo esc_html( 'モデル：' . apprex_image_model() ); ?></span>
		</form>
	</div>
	<?php
}

/* 投稿一覧：AIの記事に「写真を入れる」（前置きの文も外す）。 */
add_filter(
	'post_row_actions',
	function ( $actions, $post ) {
		if ( 'post' === $post->post_type && current_user_can( 'edit_post', $post->ID ) ) {
			$url                        = wp_nonce_url( admin_url( 'admin-post.php?action=apprex_ai_add_images&post=' . $post->ID ), 'apprex_ai_add_images_' . $post->ID );
			$actions['apprex_ai_images'] = '<a href="' . esc_url( $url ) . '" onclick="return confirm(\'見出しの下にAIの写真を2枚入れます（1枚約1円・30秒ほど）。よろしいですか？\')">AIで写真を入れる</a>';
		}
		return $actions;
	},
	10,
	2
);

add_action(
	'admin_post_apprex_ai_add_images',
	function () {
		$id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! check_admin_referer( 'apprex_ai_add_images_' . $id ) ) {
			wp_die( '権限がありません。' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		// 先頭の前置き（「〜を出力します」など）を外す。
		// 前置きを外すのは、AIが書いた記事だけ（自分で書いた記事の書き出しは触らない）。
		$c = (string) get_post_field( 'post_content', $id );
		$n = get_post_meta( $id, '_apprex_ai_generated', true ) ? apprex_ai_clean_body( $c ) : $c;
		if ( $n !== $c ) {
			wp_update_post( array( 'ID' => $id, 'post_content' => $n ) );
		}
		$done = apprex_ai_body_images( $id, get_the_title( $id ), 2 );
		set_transient( 'apprex_ai_blog_notice_' . get_current_user_id(), array( 'type' => $done ? 'success' : 'warning', 'msg' => esc_html( '「' . get_the_title( $id ) . '」に写真を' . $done . '枚入れました。' . ( $n !== $c ? '先頭の前置きの文も外しました。' : '' ) ) . ' <a href="' . esc_url( get_permalink( $id ) ) . '" target="_blank">記事を見る</a>' ), 60 );
		wp_safe_redirect( admin_url( 'edit.php?page=apprex-ai-blog' ) );
		exit;
	}
);

/**
 * 手動生成のハンドラ。
 */
add_action( 'admin_post_apprex_generate_post', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'apprex_generate_post' ) ) {
		wp_die( 'forbidden' );
	}
	$uid    = get_current_user_id();
	$notify = function ( $type, $msg ) use ( $uid ) {
		set_transient( 'apprex_ai_blog_notice_' . $uid, array( 'type' => $type, 'msg' => $msg ), 60 );
		wp_safe_redirect( admin_url( 'edit.php?page=apprex-ai-blog' ) );
		exit;
	};

	$research = ! empty( $_POST['research'] );
	if ( $research ) {
		if ( ! ( function_exists( 'apprex_ai_use_claude' ) && apprex_ai_use_claude() ) ) {
			$notify( 'error', 'Claude APIキーが未設定です（設定 → APPREX チャット）。' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
	}
	$res = apprex_ai_generate_post(
		array(
			'research'  => $research,
			'topic'     => $research ? '' : wp_unslash( $_POST['topic'] ?? '' ),
			'keywords'  => wp_unslash( $_POST['keywords'] ?? '' ),
			'tone'      => wp_unslash( $_POST['tone'] ?? '' ),
			'length'    => $_POST['length'] ?? 2000,
			'publish'   => ! empty( $_POST['publish'] ),
			'gen_image' => ! empty( $_POST['gen_image'] ),
			'force'     => ! empty( $_POST['force'] ),
			'body_images' => isset( $_POST['body_images'] ) ? absint( $_POST['body_images'] ) : 0,
		)
	);
	if ( is_wp_error( $res ) ) {
		if ( 'duplicate' === $res->get_error_code() ) {
			$d    = $res->get_error_data();
			$link = ( is_array( $d ) && ! empty( $d['post_id'] ) ) ? ' <a href="' . esc_url( get_edit_post_link( $d['post_id'], 'raw' ) ) . '">既存記事を見る</a>' : '';
			$notify( 'warning', esc_html( $res->get_error_message() ) . $link . '（重複を作らない設定です。どうしても作る場合は「類似があっても強制生成」にチェック）' );
		}
		$notify( 'error', '生成に失敗しました：' . esc_html( $res->get_error_message() ) );
	}
	$edit = get_edit_post_link( $res['post_id'], 'raw' );
	$view = get_permalink( $res['post_id'] );
	$notify(
		'success',
		sprintf(
			'記事「%s」を作成しました。%s <a href="%s">編集</a> ／ <a href="%s" target="_blank">プレビュー</a>',
			esc_html( $res['title'] ),
			esc_html( $res['image_note'] ),
			esc_url( $edit ),
			esc_url( $view )
		)
	);
} );

/**
 * 重複記事スキャン→301整理のハンドラ。
 */
add_action( 'admin_post_apprex_ai_dedupe', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'apprex_ai_dedupe' ) ) {
		wp_die( 'forbidden' );
	}
	$uid = get_current_user_id();
	$n   = apprex_ai_dedupe_scan();
	$msg = $n > 0
		? sprintf( '重複記事を %d 件、古い記事へ301リダイレクトしました（SEO評価を集約）。', $n )
		: '重複する記事は見つかりませんでした。';
	set_transient( 'apprex_ai_blog_notice_' . $uid, array( 'type' => 'success', 'msg' => $msg ), 60 );
	wp_safe_redirect( admin_url( 'edit.php?page=apprex-ai-blog' ) );
	exit;
} );

/**
 * 画像生成テスト（診断）のハンドラ。
 */
add_action( 'admin_post_apprex_test_image', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'apprex_test_image' ) ) {
		wp_die( 'forbidden' );
	}
	$uid = get_current_user_id();
	if ( ! function_exists( 'apprex_openrouter_image' ) ) {
		set_transient( 'apprex_ai_blog_notice_' . $uid, array( 'type' => 'error', 'msg' => '画像生成機能が無効です。' ), 60 );
		wp_safe_redirect( admin_url( 'edit.php?page=apprex-ai-blog' ) );
		exit;
	}

	$img = apprex_openrouter_image( 'APPREXのブログ用テスト画像。明るいブルー基調、クリーンなビジネス向け、テキストなし、16:9。' );
	if ( is_wp_error( $img ) ) {
		$msg = '画像生成テストに失敗：' . esc_html( $img->get_error_message() );
		$type = 'error';
	} else {
		$att = apprex_save_generated_image( $img, 'apprex-test', 0 );
		if ( is_wp_error( $att ) ) {
			$msg  = '画像は生成できましたが保存に失敗：' . esc_html( $att->get_error_message() );
			$type = 'error';
		} else {
			$msg  = sprintf( '画像生成テスト成功。<a href="%s" target="_blank">生成画像を見る</a>（モデル：%s）', esc_url( wp_get_attachment_url( $att ) ), esc_html( apprex_image_model() ) );
			$type = 'success';
		}
	}
	set_transient( 'apprex_ai_blog_notice_' . $uid, array( 'type' => $type, 'msg' => $msg ), 60 );
	wp_safe_redirect( admin_url( 'edit.php?page=apprex-ai-blog' ) );
	exit;
} );

/* -------------------------------------------------------------------------
 * 自動投稿（cron）
 * ---------------------------------------------------------------------- */

/**
 * 投稿時刻（0〜23時）に丸める。
 *
 * @param mixed $v 入力値。
 * @return int 0〜23。
 */
function apprex_sanitize_hour( $v ) {
	$h = (int) $v;
	return max( 0, min( 23, $h ) );
}

/**
 * 指定時刻（サイトのタイムゾーン）における次の実行タイムスタンプ（UTC秒）。
 *
 * @param int $hour 0〜23。
 * @return int タイムスタンプ。
 */
function apprex_autopost_next_run( $hour ) {
	$tz   = wp_timezone();
	$now  = new DateTime( 'now', $tz );
	$next = new DateTime( 'now', $tz );
	$next->setTime( apprex_sanitize_hour( $hour ), 0, 0 );
	if ( $next <= $now ) {
		$next->modify( '+1 day' );
	}
	return $next->getTimestamp();
}

/**
 * 自動投稿cronを、設定された投稿時刻に合わせて（再）スケジュールする。
 */
function apprex_reschedule_autopost() {
	$ts = wp_next_scheduled( 'apprex_autopost_cron' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'apprex_autopost_cron' );
	}
	$hour = (int) get_option( 'apprex_autopost_hour', 10 );
	wp_schedule_event( apprex_autopost_next_run( $hour ), 'daily', 'apprex_autopost_cron' );
}

add_action( 'init', function () {
	$hour      = (int) get_option( 'apprex_autopost_hour', 10 );
	$scheduled = wp_next_scheduled( 'apprex_autopost_cron' );
	if ( ! $scheduled ) {
		wp_schedule_event( apprex_autopost_next_run( $hour ), 'daily', 'apprex_autopost_cron' );
		return;
	}
	// 既存スケジュールの時刻が設定とズレていたら合わせ直す。
	if ( (int) wp_date( 'G', $scheduled ) !== apprex_sanitize_hour( $hour ) ) {
		apprex_reschedule_autopost();
	}
} );
// 管理画面で投稿時刻を変更したら即リスケ。
add_action( 'update_option_apprex_autopost_hour', 'apprex_reschedule_autopost' );
add_action( 'add_option_apprex_autopost_hour', 'apprex_reschedule_autopost' );

add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( 'apprex_autopost_cron' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'apprex_autopost_cron' );
	}
} );
add_action( 'apprex_autopost_cron', 'apprex_run_autopost' );

/**
 * 自動投稿の実行（頻度を判定して1記事生成・公開）。
 */
function apprex_run_autopost() {
	if ( ! get_option( 'apprex_autopost_enabled', 0 ) || ! apprex_chat_enabled() ) {
		return;
	}
	$topics = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) get_option( 'apprex_autopost_topics', apprex_default_autopost_topics() ) ) ) );
	$research = 'research' === get_option( 'apprex_autopost_mode', 'queue' ) && function_exists( 'apprex_claude_research' ) && apprex_ai_use_claude();
	if ( empty( $topics ) && ! $research ) {
		return;
	}

	$freq      = get_option( 'apprex_autopost_freq', 'weekly' );
	$interval  = array( 'daily' => DAY_IN_SECONDS, 'weekly' => 7 * DAY_IN_SECONDS, 'biweekly' => 14 * DAY_IN_SECONDS );
	$need      = isset( $interval[ $freq ] ) ? $interval[ $freq ] : 7 * DAY_IN_SECONDS;
	$last      = (int) get_option( 'apprex_autopost_last', 0 );
	// 毎日：1日に何本出すか（2本目からは4時間おき）。
	$per_day = 'daily' === $freq ? max( 1, min( 3, (int) get_option( 'apprex_autopost_per_day', 1 ) ) ) : 1;
	$today   = wp_date( 'Ymd' );
	$tc      = get_option( 'apprex_autopost_today', array( 'd' => '', 'n' => 0 ) );
	$tc      = ( is_array( $tc ) && isset( $tc['d'] ) && $today === $tc['d'] ) ? $tc : array( 'd' => $today, 'n' => 0 );
	if ( 'daily' === $freq ) {
		if ( $tc['n'] >= $per_day || ( $tc['n'] > 0 && $last && time() - $last < 3 * HOUR_IN_SECONDS + 30 * MINUTE_IN_SECONDS ) ) {
			return; // 今日の分は出した／前の記事から時間がたっていない。
		}
		if ( 0 === $tc['n'] && $last && wp_date( 'Ymd', $last ) === $today ) {
			return;
		}
	} elseif ( $last && ( time() - $last ) < $need - HOUR_IN_SECONDS ) {
		return; // まだ時期ではない。
	}
	// 今日の何本目か（分野を変えるため）。次の回を予約。
	$slot = $tc['n'];
	$tc['n']++;
	update_option( 'apprex_autopost_today', $tc, false );
	if ( $tc['n'] < $per_day ) {
		wp_schedule_single_event( time() + 4 * HOUR_IN_SECONDS, 'apprex_autopost_cron' );
	}
	$focus = array_values( array_filter( array_map( 'trim', preg_split( '/\R/', apprex_ai_research_focus() ) ) ) );
	$hint  = $focus ? $focus[ ( (int) wp_date( 'z' ) * $per_day + $slot ) % count( $focus ) ] : '';

	// Claude が調べてテーマから決める。
	if ( $research ) {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 900 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$res = apprex_ai_generate_post(
			array(
				'research'    => true,
				'auto'        => true,
				'focus_hint'  => $hint,
				'body_images' => (int) get_option( 'apprex_autopost_body_images', 2 ),
				'tone'      => get_option( 'apprex_autopost_tone', '専門的で信頼感のある' ),
				'length'    => 2500,
				'publish'   => true,
				'gen_image' => (bool) get_option( 'apprex_autopost_image', 0 ),
			)
		);
		update_option( 'apprex_autopost_last', time() );
		update_option( 'apprex_autopost_last_result', is_wp_error( $res ) ? '失敗：' . $res->get_error_message() : '公開：' . $res['title'], false );
		apprex_ai_dedupe_scan();
		return;
	}

	// 重複を作らないよう、既に類似記事があるトピックはスキップして未出のテーマを選ぶ。
	$vals  = array_values( $topics );
	$count = count( $vals );
	$start = (int) get_option( 'apprex_autopost_index', 0 ) % $count;
	$picked = -1;
	for ( $k = 0; $k < $count; $k++ ) {
		$i = ( $start + $k ) % $count;
		if ( apprex_ai_find_similar( $vals[ $i ], 0, 80 ) ) {
			continue; // 既に似た記事がある＝重複になるのでスキップ。
		}
		$picked = $i;
		break;
	}
	if ( $picked < 0 ) {
		// 全トピックが既出。重複量産はしない。次回に備えて時刻だけ更新。
		update_option( 'apprex_autopost_last', time() );
		apprex_ai_dedupe_scan(); // 念のため既存重複を整理。
		return;
	}
	$topic = $vals[ $picked ];

	$res = apprex_ai_generate_post(
		array(
			'topic'       => $topic,
			'auto'        => true,
			'body_images' => (int) get_option( 'apprex_autopost_body_images', 2 ),
			'tone'      => get_option( 'apprex_autopost_tone', '専門的で信頼感のある' ),
			'length'    => 2000,
			'publish'   => true,
			'gen_image' => (bool) get_option( 'apprex_autopost_image', 0 ),
		)
	);

	update_option( 'apprex_autopost_last', time() );
	update_option( 'apprex_autopost_index', $picked + 1 );

	// 公開後、既存の重複があれば自動で301に整理（SEO最適化）。
	apprex_ai_dedupe_scan();
	// 公開されると blog.php の transition_post_status が SNS連動 Webhook を送出。
}
