<?php
/**
 * ブログ一覧（投稿ページ）。NEW・アイキャッチ付きカード。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_blog_page = get_option( 'page_for_posts' );
?>
<section class="page-hero">
	<div class="container">
		<nav class="breadcrumbs">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
		</nav>
		<h1><?php echo esc_html( $apprex_blog_page ? get_the_title( $apprex_blog_page ) : __( 'ブログ', 'apprex' ) ); ?></h1>
		<p><?php esc_html_e( 'ノーコードアプリ開発・DX・集客のノウハウを全国へ発信しています。', 'apprex' ); ?></p>
	</div>
</section>

<section class="section">
	<div class="container">
		<?php if ( have_posts() ) : ?>
			<div class="grid grid--3">
				<?php
				while ( have_posts() ) :
					the_post();
					apprex_post_card();
				endwhile;
				?>
			</div>
			<div class="text-center mt-32"><?php the_posts_pagination( array( 'mid_size' => 1 ) ); ?></div>
		<?php else : ?>
			<p class="text-center"><?php esc_html_e( '記事は準備中です。', 'apprex' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_template_part( 'template-parts/final-cta' );
get_footer();
