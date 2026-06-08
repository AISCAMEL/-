<?php
/**
 * Fallback template (blog/index/search/404 container).
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>
<section class="page-hero">
	<div class="container">
		<h1>
			<?php
			if ( is_search() ) {
				/* translators: %s: search query. */
				printf( esc_html__( '「%s」の検索結果', 'apprex' ), esc_html( get_search_query() ) );
			} elseif ( is_404() ) {
				esc_html_e( 'ページが見つかりません', 'apprex' );
			} else {
				echo esc_html( get_the_archive_title() );
			}
			?>
		</h1>
	</div>
</section>

<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="grid grid--3">
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<a class="feature-card is-reveal" href="<?php the_permalink(); ?>">
						<h3><?php the_title(); ?></h3>
						<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 40 ) ); ?></p>
					</a>
					<?php
				endwhile;
				?>
			</div>
			<div class="text-center mt-32"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<div class="content-prose text-center">
				<p><?php esc_html_e( 'お探しのコンテンツは見つかりませんでした。', 'apprex' ); ?></p>
				<a class="btn btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'トップへ戻る', 'apprex' ); ?></a>
			</div>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
