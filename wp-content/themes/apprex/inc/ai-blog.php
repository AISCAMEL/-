<?php
/**
 * AI blog post generator (admin).
 *
 * Adds 投稿 > AI記事生成. Generates a draft post from a topic using OpenRouter.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the admin page under Posts.
 */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php', 'AI記事生成', 'AI記事生成', 'edit_posts', 'apprex-ai-blog', 'apprex_ai_blog_page' );
} );

/**
 * Render the generator page.
 */
function apprex_ai_blog_page() {
	if ( ! current_user_can( 'edit_posts' ) ) {
		return;
	}
	$notice = get_transient( 'apprex_ai_blog_notice_' . get_current_user_id() );
	if ( $notice ) {
		delete_transient( 'apprex_ai_blog_notice_' . get_current_user_id() );
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI記事生成（OpenRouter）', 'apprex' ); ?></h1>

		<?php if ( ! apprex_chat_enabled() ) : ?>
			<div class="notice notice-warning"><p><?php esc_html_e( 'OpenRouter APIキーが未設定です。設定 > APPREX チャット でキーを登録してください。', 'apprex' ); ?></p></div>
		<?php endif; ?>

		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo wp_kses_post( $notice['msg'] ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width:720px">
			<input type="hidden" name="action" value="apprex_generate_post">
			<?php wp_nonce_field( 'apprex_generate_post' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="ab-topic"><?php esc_html_e( 'テーマ・キーワード', 'apprex' ); ?> <span style="color:#d63638">*</span></label></th>
					<td><input name="topic" id="ab-topic" type="text" class="regular-text" required placeholder="<?php esc_attr_e( '例：飲食店がアプリで再来店を増やす方法', 'apprex' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ab-keywords"><?php esc_html_e( 'SEOキーワード（任意）', 'apprex' ); ?></label></th>
					<td><input name="keywords" id="ab-keywords" type="text" class="regular-text" placeholder="<?php esc_attr_e( 'ノーコード, 集客, モバイルオーダー', 'apprex' ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="ab-tone"><?php esc_html_e( 'トーン', 'apprex' ); ?></label></th>
					<td>
						<select name="tone" id="ab-tone">
							<option value="専門的で信頼感のある"><?php esc_html_e( '専門的・信頼感', 'apprex' ); ?></option>
							<option value="親しみやすく初心者向けの"><?php esc_html_e( '親しみやすい・初心者向け', 'apprex' ); ?></option>
							<option value="説得力のあるセールス"><?php esc_html_e( 'セールス寄り', 'apprex' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="ab-length"><?php esc_html_e( '文字数の目安', 'apprex' ); ?></label></th>
					<td>
						<select name="length" id="ab-length">
							<option value="1200">約1,200字（短め）</option>
							<option value="2000" selected>約2,000字（標準）</option>
							<option value="3000">約3,000字（長め）</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( '投稿ステータス', 'apprex' ); ?></th>
					<td><label><input type="checkbox" name="publish" value="1"> <?php esc_html_e( '生成後すぐに公開する（既定は下書き保存）', 'apprex' ); ?></label></td>
				</tr>
			</table>
			<?php submit_button( 'AIで記事を生成', 'primary', 'submit', true, apprex_chat_enabled() ? array() : array( 'disabled' => 'disabled' ) ); ?>
			<p class="description"><?php esc_html_e( '生成結果は記事として保存されます。公開前に内容・事実関係を必ずご確認ください。', 'apprex' ); ?></p>
		</form>
	</div>
	<?php
}

/**
 * Handle generation: call OpenRouter, create the post, redirect back.
 */
add_action( 'admin_post_apprex_generate_post', function () {
	if ( ! current_user_can( 'edit_posts' ) || ! check_admin_referer( 'apprex_generate_post' ) ) {
		wp_die( 'forbidden' );
	}

	$topic    = sanitize_text_field( wp_unslash( $_POST['topic'] ?? '' ) );
	$keywords = sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) );
	$tone     = sanitize_text_field( wp_unslash( $_POST['tone'] ?? '' ) );
	$length   = (int) ( $_POST['length'] ?? 2000 );
	$publish  = ! empty( $_POST['publish'] );

	$uid    = get_current_user_id();
	$notice = function ( $type, $msg ) use ( $uid ) {
		set_transient( 'apprex_ai_blog_notice_' . $uid, array( 'type' => $type, 'msg' => $msg ), 60 );
		wp_safe_redirect( admin_url( 'edit.php?page=apprex-ai-blog' ) );
		exit;
	};

	if ( '' === $topic ) {
		$notice( 'error', 'テーマ・キーワードを入力してください。' );
	}

	$max_tokens = min( 4000, (int) round( $length * 2.2 ) );
	$system     = 'あなたは APPREX（ノーコードアプリ開発プラットフォーム／合同会社アイズ）のオウンドメディア編集者です。読者の課題解決に役立つ、SEOを意識した日本語ブログ記事を作成します。誇大表現や事実誤認を避け、自然にAPPREXの活用に触れます。「即日公開」という表現は使わず「最短2週間」と表現します。';
	$user       = "次の条件でブログ記事を作成してください。\n"
		. "テーマ：{$topic}\n"
		. ( $keywords ? "SEOキーワード：{$keywords}\n" : '' )
		. "トーン：{$tone}文体\n"
		. "文字数：約{$length}字\n\n"
		. "出力形式（厳守）：1行目に「TITLE: 記事タイトル」。2行目以降に本文を HTML（<h2>, <h3>, <p>, <ul><li> のみ使用、<h1>は使わない）で出力。前置きや説明文は不要。";

	$result = apprex_openrouter_complete(
		array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => $user ),
		),
		array( 'temperature' => 0.7, 'max_tokens' => $max_tokens, 'timeout' => 90 )
	);

	if ( is_wp_error( $result ) ) {
		$notice( 'error', '生成に失敗しました：' . esc_html( $result->get_error_message() ) );
	}

	// Parse "TITLE: ..." first line.
	$title = '';
	$body  = $result;
	if ( preg_match( '/^\s*TITLE\s*[:：]\s*(.+)$/m', $result, $m ) ) {
		$title = trim( $m[1] );
		$body  = trim( preg_replace( '/^\s*TITLE\s*[:：].+$/m', '', $result, 1 ) );
	}
	if ( '' === $title ) {
		$title = $topic;
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
		$notice( 'error', '記事の保存に失敗しました。' );
	}
	update_post_meta( $post_id, '_apprex_ai_generated', 1 );

	$edit = get_edit_post_link( $post_id, 'raw' );
	$view = get_permalink( $post_id );
	$notice(
		'success',
		sprintf(
			'記事「%s」を%sしました。 <a href="%s">編集する</a> ／ <a href="%s" target="_blank">プレビュー</a>',
			esc_html( $title ),
			$publish ? '公開' : '下書き保存',
			esc_url( $edit ),
			esc_url( $view )
		)
	);
} );
