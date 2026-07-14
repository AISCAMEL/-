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

	if ( '' === $topic ) {
		return new WP_Error( 'no_topic', 'テーマが空です。' );
	}

	// 重複防止（生成前）：類似トピックの記事が既にあれば作らない。
	if ( ! $force ) {
		$dup = apprex_ai_find_similar( $topic, 0, 80 );
		if ( $dup ) {
			return new WP_Error(
				'duplicate',
				sprintf( '類似記事が既にあるため生成を中止しました（重複量産の防止）：「%s」', get_the_title( $dup ) ),
				array( 'post_id' => $dup )
			);
		}
	}

	$system = 'あなたは APPREX（アプリックス／ノーコードアプリ開発・合同会社アイズ）のオウンドメディア編集者です。読者の課題解決に役立つ、SEO/LLMOを意識した日本語ブログ記事を作成します。誇大表現や事実誤認を避け、自然にAPPREXの活用に触れます。「即日公開」は使わず「最短2週間」と表現します。全国の読者に届く一般性のある内容にします。';
	$user   = "次の条件でブログ記事を作成してください。\n"
		. "テーマ：{$topic}\n"
		. ( $keywords ? "SEOキーワード：{$keywords}\n" : '' )
		. "トーン：{$tone}文体\n"
		. "文字数：約{$length}字\n\n"
		. "出力形式（厳守）：1行目に「TITLE: 記事タイトル」。2行目以降に本文を HTML（<h2>,<h3>,<p>,<ul><li> のみ、<h1>不可）で。前置き不要。";

	$result = apprex_openrouter_complete(
		array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $user ),
		),
		array( 'temperature' => 0.7, 'max_tokens' => min( 4000, (int) round( $length * 2.2 ) ), 'timeout' => 90 )
	);
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$title = '';
	$body  = $result;
	if ( preg_match( '/^\s*TITLE\s*[:：]\s*(.+)$/m', $result, $m ) ) {
		$title = trim( $m[1] );
		$body  = trim( preg_replace( '/^\s*TITLE\s*[:：].+$/m', '', $result, 1 ) );
	}
	$title = $title ? $title : $topic;

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
		),
		true
	);
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}
	update_post_meta( $post_id, '_apprex_ai_generated', 1 );
	update_post_meta( $post_id, '_apprex_ai_topic_key', apprex_ai_norm( $topic ) );

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
		<h1><?php esc_html_e( 'AI記事生成（OpenRouter）', 'apprex' ); ?></h1>

		<?php if ( ! apprex_chat_enabled() ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'OpenRouter APIキーが未設定です。設定 > APPREX チャット で登録してください。', 'apprex' ); ?></p></div>
		<?php endif; ?>
		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo wp_kses_post( $notice['msg'] ); ?></p></div>
		<?php endif; ?>

		<h2><?php esc_html_e( '今すぐ1記事つくる', 'apprex' ); ?></h2>
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
					</select></td></tr>
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
					<tr><th><?php esc_html_e( 'アイキャッチAI生成', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="apprex_autopost_image" value="1" <?php checked( 1, (int) get_option( 'apprex_autopost_image', 0 ) ); ?>> <?php esc_html_e( '自動投稿でも画像を生成する', 'apprex' ); ?></label></td></tr>
				<tr><th><?php esc_html_e( 'トーン', 'apprex' ); ?></th>
					<td><input type="text" name="apprex_autopost_tone" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_autopost_tone', '専門的で信頼感のある' ) ); ?>"></td></tr>
				<tr><th><?php esc_html_e( 'トピック一覧（1行に1テーマ）', 'apprex' ); ?></th>
					<td><textarea name="apprex_autopost_topics" rows="8" class="large-text" placeholder="ノーコードアプリ開発のメリット&#10;飲食店アプリで再来店を増やす方法&#10;補助金を活用したアプリ導入&#10;アプリとホームページの使い分け"><?php echo esc_textarea( get_option( 'apprex_autopost_topics', apprex_default_autopost_topics() ) ); ?></textarea>
					<p class="description"><?php esc_html_e( '上から順に1記事ずつ自動生成します（一巡したら先頭へ戻ります）。', 'apprex' ); ?></p></td></tr>
				<tr><th><?php esc_html_e( '画像モデル', 'apprex' ); ?></th>
					<td><input type="text" name="apprex_image_model" class="regular-text" value="<?php echo esc_attr( get_option( 'apprex_image_model', '' ) ); ?>" placeholder="google/gemini-2.5-flash-image">
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

	$res = apprex_ai_generate_post(
		array(
			'topic'     => wp_unslash( $_POST['topic'] ?? '' ),
			'keywords'  => wp_unslash( $_POST['keywords'] ?? '' ),
			'tone'      => wp_unslash( $_POST['tone'] ?? '' ),
			'length'    => $_POST['length'] ?? 2000,
			'publish'   => ! empty( $_POST['publish'] ),
			'gen_image' => ! empty( $_POST['gen_image'] ),
			'force'     => ! empty( $_POST['force'] ),
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
	if ( empty( $topics ) ) {
		return;
	}

	$freq      = get_option( 'apprex_autopost_freq', 'weekly' );
	$interval  = array( 'daily' => DAY_IN_SECONDS, 'weekly' => 7 * DAY_IN_SECONDS, 'biweekly' => 14 * DAY_IN_SECONDS );
	$need      = isset( $interval[ $freq ] ) ? $interval[ $freq ] : 7 * DAY_IN_SECONDS;
	$last      = (int) get_option( 'apprex_autopost_last', 0 );
	if ( $last && ( time() - $last ) < $need - HOUR_IN_SECONDS ) {
		return; // まだ時期ではない。
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
			'topic'     => $topic,
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
