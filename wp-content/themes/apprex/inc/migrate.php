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
 * スライド1枚分の HTML ブロック（画像の代わり）。
 *
 * @param array $a label / title / body / list / table / cta。
 * @return string
 */
function apprex_lp_slide_html( $a ) {
	$h  = '<section style="background:linear-gradient(160deg,#0b1d3f 0%,#13306a 60%,#1f4398 100%);color:#ffffff;padding:56px 24px;text-align:center;font-family:\'Noto Sans JP\',\'Hiragino Kaku Gothic ProN\',sans-serif;line-height:1.75;margin:0">';
	$h .= '<p style="color:#ffcf6e;font-weight:700;letter-spacing:.12em;margin:0 0 10px;font-size:13px">' . esc_html( $a['label'] ) . '</p>';
	$h .= '<h2 style="color:#ffffff;font-size:30px;line-height:1.4;margin:0 0 16px;font-weight:900;border:0;padding:0;background:none;text-align:center">' . wp_kses( $a['title'], array( 'br' => array() ) ) . '</h2>';
	if ( ! empty( $a['body'] ) ) {
		$h .= '<p style="margin:0 auto;max-width:34em;color:#dbe4f7;font-size:16px">' . esc_html( $a['body'] ) . '</p>';
	}
	if ( ! empty( $a['list'] ) ) {
		$h .= '<div style="margin:18px auto 0;max-width:30em;text-align:left">';
		foreach ( $a['list'] as $li ) {
			$h .= '<p style="margin:8px 0;padding:10px 14px;background:rgba(255,255,255,.08);border-radius:10px;font-size:15px;color:#ffffff"><span style="color:#ffcf6e;font-weight:700">✓</span> ' . esc_html( $li ) . '</p>';
		}
		$h .= '</div>';
	}
	if ( ! empty( $a['table'] ) ) {
		$h .= '<table style="width:100%;max-width:640px;margin:18px auto 0;border-collapse:collapse;font-size:14px;background:#ffffff;color:#0e1830;border-radius:10px;overflow:hidden"><tbody>';
		$h .= '<tr><th style="padding:10px;background:#e8edf7;text-align:left">項目</th><th style="padding:10px;background:#e8edf7">一般的なスクラッチ開発会社</th><th style="padding:10px;background:#ff6b1f;color:#ffffff">APPREX</th></tr>';
		foreach ( $a['table'] as $r ) {
			$h .= '<tr><td style="padding:10px;border-top:1px solid #e3e8f2;text-align:left;font-weight:700">' . esc_html( $r[0] ) . '</td><td style="padding:10px;border-top:1px solid #e3e8f2">' . esc_html( $r[1] ) . '</td><td style="padding:10px;border-top:1px solid #e3e8f2;font-weight:700">' . esc_html( $r[2] ) . '</td></tr>';
		}
		$h .= '</tbody></table>';
	}
	if ( ! empty( $a['note'] ) ) {
		$h .= '<p style="margin:14px auto 0;max-width:36em;color:#9fb2dd;font-size:12px">' . esc_html( $a['note'] ) . '</p>';
	}
	if ( ! empty( $a['cta'] ) ) {
		$h .= '<p style="margin:24px 0 0">';
		foreach ( $a['cta'] as $i => $c ) {
			$st = 0 === $i ? 'background:#ff6b1f;color:#ffffff' : 'background:transparent;color:#ffffff;border:2px solid rgba(255,255,255,.6)';
			$h .= '<a href="' . esc_url( home_url( $c[1] ) ) . '" style="display:inline-block;margin:6px;padding:14px 26px;border-radius:999px;font-weight:700;text-decoration:none;' . $st . '">' . esc_html( $c[0] ) . '</a>';
		}
		$h .= '</p>';
	}
	$h .= '</section>';
	return "<!-- wp:html -->\n" . $h . "\n<!-- /wp:html -->";
}

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

/** 広告LP：古い料金・キャンペーン・期間のスライドを、今の内容に差し替える。 */
function apprex_migrate_lp_161() {
	if ( ! post_type_exists( 'clp' ) ) {
		return;
	}
	$cta = array( array( '無料相談する', '/meeting/' ), array( '資料を請求する', '/document/' ) );
	$fix = array(
		'app-development' => array(
			'ai-lp-slide-jPSLSHYI' => array( 'label' => 'APPREX ／ 自社アプリ', 'title' => 'LINE集客の次は、<br>自社アプリ。', 'body' => '予約・会員・EC・クーポン・スタンプカードまで標準搭載。プログラミング不要、最短2週間で公開。月額19,800円から始められます。', 'cta' => $cta ),
			'ai-lp-slide-yYKb6xFf' => array( 'label' => 'Solution 解決策 3', 'title' => '最短2週間で公開。<br>制作も申請もおまかせ。', 'body' => 'プログラミングは不要です。デザイン・仕様書づくりからストア申請までAPPREXが行い、初期費用15万円〜・月額19,800円から自社アプリを始められます。' ),
			'ai-lp-slide-trJyo8fW' => array( 'label' => 'Offer ご提案', 'title' => 'まずは無料で、<br>あなたのアプリの形を。', 'list' => array( 'デザイン・仕様書づくりは無料', '初期費用15万円〜・月額19,800円〜', '30日間の無料体験（クレジットカード登録不要）', 'プッシュ通知は無制限', '予約・会員・EC・クーポン・スタンプカードを標準搭載' ), 'note' => '税抜。アプリストア登録費（一律55,000円）が別途かかります。', 'cta' => $cta ),
			'ai-lp-slide-MqZ2nn5W' => array( 'label' => 'Schedule 公開までの目安', 'title' => '次の販促に、<br>間に合わせるなら今。', 'body' => 'アプリの公開まで最短2週間（ストア審査を含む）。季節のキャンペーンや新メニューの告知に間に合わせたい方は、早めにご相談ください。' ),
			'ai-lp-slide-DTAs3gMm' => array( 'label' => 'Action', 'title' => 'LINEだけに頼る集客から、<br>育てる自社アプリへ。', 'body' => 'APPREXなら、月額制・ノーコードで自社アプリを始められます。まずは無料相談で、あなたのお店に合うアプリの活用方法を確認してください。', 'cta' => $cta ),
		),
		'matching-app'    => array(
			'ai-lp-slide-XCVMEQ2B' => array( 'label' => 'APPREX ／ マッチングアプリ開発', 'title' => 'マッチングアプリを、<br>300万円〜・3ヶ月〜で。', 'body' => 'APPREXは、Flutterによるスクラッチ開発で、iOS・Android対応のマッチングアプリを企画から運用まで支援します。', 'cta' => array( array( '無料で相談する', '/meeting/' ), array( '概算見積りをもらう', '/contact/' ) ) ),
			'ai-lp-slide-zLTAd2Mf' => array( 'label' => 'Solution 解決策 2', 'title' => 'Flutterで、<br>iOS・Androidを同時に開発', 'body' => 'Flutterを使うため、iPhone向けとAndroid向けを効率よく開発できます。両OSに対応したアプリを、3ヶ月〜を目安に制作します。' ),
			'ai-lp-slide-JFjVg1K5' => array( 'label' => '比較表 1', 'title' => '一般的なスクラッチ開発会社と<br>APPREXの比較', 'table' => array( array( '初期費用の目安', '高額になりやすく要見積り', '300万円〜' ), array( '両OS対応', '別々に作る場合がある', 'Flutterで両OS対応' ), array( '納期', '数ヶ月以上かかることがある', '3ヶ月〜が目安' ) ) ),
			'ai-lp-slide-RYRLUEzM' => array( 'label' => 'Action', 'title' => 'アイデアを、<br>事業になるアプリへ。', 'body' => 'マッチングアプリの新規事業・刷新を、APPREXが企画から開発、保守まで伴走します。まずは無料相談で、あなたのアイデアを形にする方法を一緒に整理しましょう。', 'note' => '開発費300万円〜、月額保守・サーバ代7万円〜、制作期間3ヶ月〜、最低利用期間1年契約（税抜）。', 'cta' => array( array( '無料相談・見積りを依頼する', '/meeting/' ) ) ),
		),
	);
	foreach ( $fix as $slug => $slides ) {
		$post = get_page_by_path( $slug, OBJECT, 'clp' );
		if ( ! $post ) {
			continue;
		}
		$c = $post->post_content;
		foreach ( $slides as $file => $slide ) {
			if ( false !== strpos( $c, $file ) ) {
				$c = apprex_replace_image_block( $c, $file, apprex_lp_slide_html( $slide ) );
			}
		}
		apprex_migrate_save_content( $post, $c, '161' );
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
