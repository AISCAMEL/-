<?php
/**
 * テーマを入れ替えたときに1回だけ行う、保存済みデータの手直し。
 *
 * v1.60.0
 * - メール文面の上書き（設定 > APPREX メール文面）：一度保存すると全項目が当時の文面で固定され、
 *   テーマ側の修正（料金・表現）が届かなくなっていた。リンクを入れて使っている自動返信
 *   （お問い合わせ・資料請求・ミーティング）だけ残し、ほかは初期文面に戻す。元の内容は控えに保存。
 * - チャットの知識（設定 > APPREX チャット）：古い料金・実績の数字を、今の内容に置き換える。
 *   元の内容は控えに保存。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	$v = (int) get_option( 'apprex_migrated', 0 );
	if ( $v < 160 ) {
		apprex_migrate_mail_overrides_160();
		apprex_migrate_chat_knowledge_160();
	}
	if ( $v < 161 ) {
		apprex_migrate_lp_161();
		apprex_migrate_posts_161();
	}
	if ( $v < 162 ) {
		apprex_migrate_partner_page_162();
		update_option( 'apprex_migrated', 162 );
	}
	if ( $v < 163 ) {
		apprex_migrate_agency_page_163();
		update_option( 'apprex_migrated', 163 );
	}
	if ( $v < 167 ) {
		apprex_migrate_lp_167();
		update_option( 'apprex_migrated', 167 );
	}
	if ( $v < 174 ) {
		apprex_migrate_privacy_174();
		update_option( 'apprex_migrated', 174 );
	}
}, 20 );

/** メール文面の上書きを整理する。 */
function apprex_migrate_mail_overrides_160() {
	$o = get_option( 'apprex_mail_overrides', array() );
	if ( ! is_array( $o ) || ! $o ) {
		return;
	}
	add_option( 'apprex_mail_overrides_backup_160', $o, '', false );
	$keep = array_intersect_key( $o, array_flip( array( 'autoreply.contact', 'autoreply.document', 'autoreply.meeting' ) ) );
	update_option( 'apprex_mail_overrides', $keep );
}

/** チャットの知識の、古い料金・実績の記述を置き換える。 */
function apprex_migrate_chat_knowledge_160() {
	$k = (string) get_option( 'apprex_chat_knowledge', '' );
	if ( '' === $k || ( false === strpos( $k, '初期費用0円' ) && false === strpos( $k, '8,000' ) && false === strpos( $k, '制作代行' ) ) ) {
		return;
	}
	add_option( 'apprex_chat_knowledge_backup_160', $k, '', false );
	$n = str_replace( "\r\n", "\n", $k );

	$map = array(
		'アプリは月額19,800円〜・初期費用0円キャンペーン中です。' => 'アプリは月額19,800円〜で、作った内容から制作する初期費用は15万円〜です。',
		'自社で作るか制作代行か／予算感' => '予算感／補助金を使いたいか',
		'- まず触ってみたい → 30日間無料体験・カード登録不要（/free-trial）' => "- まず触ってみたい → 30日間無料体験・カード登録不要（/free-trial）\n- 自社のアプリの形を見たい → AIスタジオ（無料・デザインと仕様書をAIが作成）\n- 補助金を使いたい → 補助金の案内・オンライン相談（/meeting）",
		'・提供形態：(1)自社で作る (2)制作代行 (3)ホームページ制作。' => '・提供形態：(1)AIスタジオ（無料）でデザイン・仕様書を作り、その内容からAPPREXが制作 (2)オリジナル開発・マッチングアプリ (3)ホームページ制作 (4)AIMEO（AIで運用するMEO）・LLMOなどの集客施策。',
		'・導入実績8,000社以上、業種問わず対応' => '・業種問わず対応（導入社数などの実績の数字は言わない）',
		'・補助金：IT導入補助金等が対象になる場合あり、申請サポート可。対象可否は事業内容による。' => '・補助金：小規模事業者持続化補助金や自治体の補助金の相談ができる（申請に使う企画書・見積書の用意とスケジュールのサポート。申請書の作成代行はしない）。APPREXはIT導入補助金の支援事業者ではないため、IT導入補助金は対象外。',
		'・制作代行納期：標準4〜6週間（要件により前後）。' => '・公開までの目安：ノーコード最短2週間（ストア審査を含む）、オリジナル開発・マッチングアプリ3ヶ月〜。',
		'アプリは月額19,800円〜・初期費用0円で始められます。' => 'アプリは月額19,800円〜、初期費用15万円〜（AIスタジオで作った内容から制作）です。',
		'月額19,800円〜・初期費用0円で始められますが、' => '月額19,800円〜・初期費用15万円〜です。',
	);
	$n = strtr( $n, $map );

	// 料金のかたまりを、今の料金に入れ替える。
	$price = "【料金（税抜）】\n"
		. "・デザイン・試作・仕様書づくりはAIスタジオで無料。\n"
		. "・アプリの初期費用：AIスタジオで作った内容から制作 15万円〜／AIスタジオを使わず1からヒアリングして制作 30万円〜（制作・ストア申請・初期設定を含む）。自分で設定する0円のプランはない。\n"
		. "・アプリ月額（最低契約12ヶ月）：トライアル 19,800円／スタート 39,800円（電子カタログ）／ビジネス 59,800円（多店舗）\n"
		. "・アプリ登録費（iOS/Android）：一律55,000円。\n"
		. "・オプション：位置情報プッシュ 20,000円／チャット機能 300,000円。\n"
		. "・ホームページ（初期費用0円）：Light 9,800円／Standard 19,800円／Premium 39,800円（月額）。\n"
		. "・AIMEO（AIで運用するMEO）：ライト 月15,000円／スタンダード 月30,000円／プロ 月50,000円。\n"
		. "・マッチングアプリ：開発費300万円〜・月額保守7万円〜・期間3ヶ月〜。\n"
		. "・30日間無料体験あり（カード登録不要）。\n"
		. "・正確な見積りは見積もりフォーム（/estimate）。そのまま発注（仮申込）も可能。\n";
	$n = preg_replace( '/【料金（税抜）】\n.*?(?=※基盤ベースのオリジナル化)/su', $price, $n, 1 );

	update_option( 'apprex_chat_knowledge', $n );
}

/* -------------------------------------------------------------------------
 * v1.61.0：広告LP（LPtools）と記事の古い表記を直す
 * ---------------------------------------------------------------------- */

/**
 * 投稿本文の画像ブロック（ファイル名で特定）を置き換える。
 *
 * @param string $content 本文。
 * @param string $file    画像のファイル名（例 ai-lp-slide-xxxx）。
 * @param string $new     置き換えるブロック。
 * @return string
 */
function apprex_replace_image_block( $content, $file, $new ) {
	$re = '#<!-- wp:image[^>]*-->(?:(?!<!-- /wp:image -->).)*?' . preg_quote( $file, '#' ) . '.*?<!-- /wp:image -->#s';
	return preg_replace_callback( $re, function () use ( $new ) {
		return $new;
	}, $content, 1 );
}

/** 本文を保存（元の本文は控えに残す。整形フィルタは通さない）。 */
function apprex_migrate_save_content( $post, $content, $tag ) {
	if ( $content === $post->post_content ) {
		return;
	}
	add_post_meta( $post->ID, '_apprex_content_backup_' . $tag, $post->post_content, true );
	kses_remove_filters();
	wp_update_post( array( 'ID' => $post->ID, 'post_content' => wp_slash( $content ) ) );
	kses_init_filters();
}

/** 広告LP：古い料金・キャンペーン・期間のスライドを、今の内容に差し替える（スライドの中身は inc/lp-slides.php）。 */
function apprex_migrate_lp_161() {
	if ( ! post_type_exists( 'clp' ) ) {
		return;
	}
	foreach ( apprex_lp_slide_defs() as $slug => $slides ) {
		$post = get_page_by_path( $slug, OBJECT, 'clp' );
		if ( ! $post ) {
			continue;
		}
		$c = $post->post_content;
		foreach ( $slides as $file => $def ) {
			if ( false !== strpos( $c, $file ) ) {
				$c = apprex_replace_image_block( $c, $file, apprex_lp_slide_html( $def[1] ) );
			}
		}
		apprex_migrate_save_content( $post, $c, '161' );
	}
}

/** v1.67.0：すでに差し替えた文字のスライドを、新しいデザインに作り直す。 */
function apprex_migrate_lp_167() {
	if ( ! post_type_exists( 'clp' ) ) {
		return;
	}
	foreach ( apprex_lp_slide_defs() as $slug => $slides ) {
		$post = get_page_by_path( $slug, OBJECT, 'clp' );
		if ( ! $post ) {
			continue;
		}
		$c = $post->post_content;
		foreach ( $slides as $file => $def ) {
			// 前の版のスライド（紺のグラデーションの section）を、見出しのラベルで見つける。
			$re = '#<!-- wp:html -->\s*<section style="background:linear-gradient\(160deg,\#0b1d3f(?:(?!<!-- /wp:html -->).)*?>' . preg_quote( esc_html( $def[0] ), '#' ) . '</p>.*?<!-- /wp:html -->#s';
			$c  = preg_replace_callback( $re, function () use ( $def ) {
				return apprex_lp_slide_html( $def[1] );
			}, $c, 1 );
			// まだ画像のままなら、ここで差し替える。
			if ( false !== strpos( $c, $file ) ) {
				$c = apprex_replace_image_block( $c, $file, apprex_lp_slide_html( $def[1] ) );
			}
		}
		apprex_migrate_save_content( $post, $c, '167' );
	}
}

/** 記事：APPREXの架空の事例・IT導入補助金での導入を書いた部分を直す。 */
function apprex_migrate_posts_161() {
	global $wpdb;
	$note  = "\n\n<!-- wp:paragraph -->\n<p><small>※ APPREXはIT導入補助金の支援事業者ではないため、APPREXの導入にIT導入補助金はお使いいただけません。小規模事業者持続化補助金や自治体の補助金のご相談は、<a href=\"" . esc_url( home_url( '/meeting/' ) ) . "\">無料のオンライン相談</a>で承ります。</small></p>\n<!-- /wp:paragraph -->";
	$right = "<h2>APPREXで補助金を使うときのポイント</h2>\n<p>APPREXはIT導入補助金の支援事業者ではないため、IT導入補助金ではご利用いただけません。小規模事業者持続化補助金や、都道府県・市区町村の補助金が使える場合があります。</p>\n<ul>\n  <li><strong>計画の材料づくり</strong> - AIスタジオで作る企画書・仕様書を、事業計画の材料にできます。</li>\n  <li><strong>見積書のご用意</strong> - 申請に必要な見積書を、仕様書の内容に合わせてお出しします。</li>\n  <li><strong>スケジュールの調整</strong> - 交付決定のあとに契約・制作を始めるよう、公開日を計画します。</li>\n</ul>\n<p>使える制度は、業種・会社の規模・地域によって変わります。まずは無料のオンライン相談でご確認ください。</p>\n\n";
	$posts = $wpdb->get_results( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status IN ('publish','draft','future','private') AND post_content LIKE '%IT導入補助金%'" );
	foreach ( (array) $posts as $row ) {
		$post = get_post( (int) $row->ID );
		$c    = $post->post_content;
		// 「実際に補助金を活用してAPPREXで…」のような架空の事例の章を、正しい案内に差し替える。
		$c = preg_replace( '#<h2>(成功事例の紹介|成功事例から学ぶ)</h2>.*?(?=<h2>)#s', $right, $c, 1 );
		if ( false === strpos( $c, 'IT導入補助金の支援事業者ではない' ) ) {
			$c .= $note;
		}
		apprex_migrate_save_content( $post, $c, '161' );
	}
}

/* -------------------------------------------------------------------------
 * v1.62.0：代理店募集ページ（/partner/）が無いサイトに作る
 * ---------------------------------------------------------------------- */
function apprex_migrate_partner_page_162() {
	$page = get_page_by_path( 'partner' );
	if ( $page && 'trash' !== $page->post_status ) {
		if ( 'publish' !== $page->post_status ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_status' => 'publish' ) );
		}
		update_post_meta( $page->ID, '_wp_page_template', 'page-templates/page-partner.php' );
		return;
	}
	$id = wp_insert_post(
		array(
			'post_type'   => 'page',
			'post_status' => 'publish',
			'post_title'  => '代理店募集',
			'post_name'   => 'partner',
		)
	);
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_wp_page_template', 'page-templates/page-partner.php' );
	}
}

/* -------------------------------------------------------------------------
 * v1.63.0：代理店専用ページ（/agency/）を作る
 * ---------------------------------------------------------------------- */
function apprex_migrate_agency_page_163() {
	$page = get_page_by_path( 'agency' );
	if ( $page && 'trash' !== $page->post_status ) {
		update_post_meta( $page->ID, '_wp_page_template', 'page-templates/page-agency.php' );
		return;
	}
	$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => '代理店専用ページ', 'post_name' => 'agency' ) );
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_wp_page_template', 'page-templates/page-agency.php' );
	}
}

/* -------------------------------------------------------------------------
 * v1.75.0：プライバシーポリシーのページを公開する（下書きのままのサイト向け）
 * ---------------------------------------------------------------------- */
function apprex_migrate_privacy_174() {
	$id   = (int) get_option( 'wp_page_for_privacy_policy', 0 );
	$page = $id ? get_post( $id ) : null;
	if ( ! $page || 'page' !== $page->post_type || 'trash' === $page->post_status ) {
		$page = get_page_by_path( 'privacy-policy' );
	}
	// すでに公開しているページはそのまま（URLだけそろえる）。
	if ( $page && 'publish' === $page->post_status ) {
		update_option( 'wp_page_for_privacy_policy', $page->ID );
		return;
	}
	if ( $page ) {
		// WordPressが最初に作る下書き（見本の文章）は控えに残して、テーマの標準の文面を表示する。
		if ( '' !== trim( $page->post_content ) ) {
			add_post_meta( $page->ID, '_apprex_content_backup_174', $page->post_content, true );
		}
		wp_update_post(
			array(
				'ID'           => $page->ID,
				'post_status'  => 'publish',
				'post_name'    => 'privacy-policy',
				'post_title'   => 'プライバシーポリシー',
				'post_content' => '',
			)
		);
		$id = $page->ID;
	} else {
		$id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'プライバシーポリシー',
				'post_name'   => 'privacy-policy',
			)
		);
	}
	if ( $id && ! is_wp_error( $id ) ) {
		update_post_meta( $id, '_wp_page_template', 'page-templates/page-privacy.php' );
		update_option( 'wp_page_for_privacy_policy', (int) $id );
	}
}
