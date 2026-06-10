<?php
/**
 * 単記事（ブログ）。NEW・シェアボタン・関連記事。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero">
		<div class="container">
			<nav class="breadcrumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
				<span> / </span>
				<a href="<?php echo esc_url( apprex_page_url( 'blog' ) ); ?>"><?php esc_html_e( 'ブログ', 'apprex' ); ?></a>
				<span> / </span><?php the_title(); ?>
			</nav>
			<h1><?php the_title(); ?> <?php apprex_new_badge(); ?></h1>
			<p class="post-meta"><?php echo esc_html( get_the_date() ); ?>｜<?php echo esc_html( get_the_author() ); ?></p>
		</div>
	</section>

	<article class="section">
		<div class="container content-prose">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'large' ); ?>
			<?php endif; ?>

			<?php the_content(); ?>

			<?php apprex_share_buttons(); ?>
		</div>
	</article>

	<?php
	// 関連記事（最新3件、自分以外）。
	$apprex_rel = new WP_Query(
		array(
			'post_type'           => 'post',
			'posts_per_page'      => 3,
			'post__not_in'        => array( get_the_ID() ),
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		)
	);
	if ( $apprex_rel->have_posts() ) :
		?>
		<section class="section section--soft">
			<div class="container">
				<?php apprex_section_head( 'Related', __( '関連記事', 'apprex' ) ); ?>
				<div class="grid grid--3">
					<?php
					while ( $apprex_rel->have_posts() ) :
						$apprex_rel->the_post();
						apprex_post_card();
					endwhile;
					wp_reset_postdata();
					?>
				</div>
			</div>
		</section>
		<?php
	endif;
	?>
	<?php
endwhile;

get_template_part( 'template-parts/final-cta' );
get_footer();
