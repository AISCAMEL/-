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

	if ( '' === $topic ) {
		return new WP_Error( 'no_topic', 'テーマが空です。' );
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
		)
	);
	if ( is_wp_error( $res ) ) {
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

	$idx   = (int) get_option( 'apprex_autopost_index', 0 ) % count( $topics );
	$topic = array_values( $topics )[ $idx ];

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
	update_option( 'apprex_autopost_index', $idx + 1 );
	// 公開されると blog.php の transition_post_status が SNS連動 Webhook を送出。
}
