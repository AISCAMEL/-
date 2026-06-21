<?php
/**
 * ブログ機能：NEWバッジ・新着記事・SNS連動・シェアボタン。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 公開からN日以内なら「新着」。
 *
 * @param int|WP_Post|null $post Post.
 * @param int              $days 日数。
 * @return bool
 */
function apprex_is_new_post( $post = null, $days = 14 ) {
	$t = (int) get_post_time( 'U', false, $post );
	return $t && ( time() - $t ) < $days * DAY_IN_SECONDS;
}

/**
 * 読了時間（分）を推定（日本語 約500字/分）。
 *
 * @param int|WP_Post|null $post Post.
 * @return int
 */
function apprex_reading_time( $post = null ) {
	$content = get_post_field( 'post_content', $post ? $post : get_the_ID() );
	$chars   = mb_strlen( wp_strip_all_tags( (string) $content ) );
	return max( 1, (int) ceil( $chars / 500 ) );
}

/**
 * NEW バッジを出力（新着のときだけ）。
 *
 * @param int|WP_Post|null $post Post.
 */
function apprex_new_badge( $post = null ) {
	if ( apprex_is_new_post( $post ) ) {
		echo '<span class="badge-new">NEW</span>';
	}
}

/**
 * 記事カードを出力（ループ内で使用）。
 */
function apprex_post_card() {
	?>
	<a class="post-card is-reveal" href="<?php the_permalink(); ?>">
		<span class="post-card__thumb">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'apprex-card', array( 'alt' => esc_attr( get_the_title() ) ) ); ?>
			<?php endif; ?>
			<?php apprex_new_badge(); ?>
		</span>
		<span class="post-card__body">
			<span class="post-card__date"><?php echo esc_html( get_the_date() ); ?></span>
			<span class="post-card__title"><?php the_title(); ?></span>
			<span class="post-card__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 44 ) ); ?></span>
		</span>
	</a>
	<?php
}

/* -------------------------------------------------------------------------
 * 新着記事セクション（フロントページ）
 * ---------------------------------------------------------------------- */
function apprex_latest_posts( $count = 3 ) {
	return new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => $count,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
}

/* -------------------------------------------------------------------------
 * SNS シェアボタン（単記事）
 * ---------------------------------------------------------------------- */
function apprex_share_buttons() {
	$url   = rawurlencode( get_permalink() );
	$title = rawurlencode( get_the_title() );
	$links = array(
		'X'        => "https://twitter.com/intent/tweet?text={$title}&url={$url}",
		'Facebook' => "https://www.facebook.com/sharer/sharer.php?u={$url}",
		'LINE'     => "https://social-plugins.line.me/lineit/share?url={$url}",
	);
	echo '<div class="share-buttons"><span>シェア：</span>';
	foreach ( $links as $label => $href ) {
		printf(
			'<a class="share-buttons__btn share-%1$s" href="%2$s" target="_blank" rel="noopener">%3$s</a>',
			esc_attr( strtolower( $label ) ),
			esc_url( $href ),
			esc_html( $label )
		);
	}
	echo '</div>';
}

/* -------------------------------------------------------------------------
 * SNS連動：投稿公開時に GAS Webhook へ通知（GAS側でSNS投稿）
 * ---------------------------------------------------------------------- */
add_action( 'transition_post_status', function ( $new, $old, $post ) {
	if ( 'publish' !== $new || 'publish' === $old ) {
		return;
	}
	if ( 'post' !== $post->post_type ) {
		return;
	}
	$data = array(
		'id'      => $post->ID,
		'title'   => get_the_title( $post ),
		'url'     => get_permalink( $post ),
		'excerpt' => wp_trim_words( wp_strip_all_tags( $post->post_content ), 60 ),
		'image'   => get_the_post_thumbnail_url( $post, 'large' ),
		'ai'      => (bool) get_post_meta( $post->ID, '_apprex_ai_generated', true ),
	);
	// GAS（任意）と Slack 直結（任意）の両方へ。
	// ただし WordPress 直接LINE配信が有効なときは、GASへ記事公開を送らない
	// （GAS側のLINEテキスト配信との「二重配信」を防ぐ）。Flex(ボタン)は直接配信が担当。
	$direct_line = function_exists( 'apprex_line_direct_ready' ) && apprex_line_direct_ready();
	$send_to_gas = apply_filters( 'apprex_dispatch_post_published', ! $direct_line, $post, $data );
	if ( $send_to_gas && function_exists( 'apprex_dispatch_event' ) ) {
		apprex_dispatch_event( 'post_published', $data );
	}
	if ( function_exists( 'apprex_slack_notify_post' ) ) {
		apprex_slack_notify_post( $data );
	}
}, 10, 3 );
