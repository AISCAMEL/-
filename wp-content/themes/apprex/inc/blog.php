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
	if ( ! function_exists( 'apprex_dispatch_event' ) ) {
		return;
	}
	apprex_dispatch_event(
		'post_published',
		array(
			'id'        => $post->ID,
			'title'     => get_the_title( $post ),
			'url'       => get_permalink( $post ),
			'excerpt'   => wp_trim_words( wp_strip_all_tags( $post->post_content ), 60 ),
			'image'     => get_the_post_thumbnail_url( $post, 'large' ),
			'ai'        => (bool) get_post_meta( $post->ID, '_apprex_ai_generated', true ),
		)
	);
}, 10, 3 );
