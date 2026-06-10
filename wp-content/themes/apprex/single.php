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
	<section class="page-hero post-hero">
		<div class="container">
			<nav class="breadcrumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
				<span> / </span>
				<a href="<?php echo esc_url( apprex_page_url( 'blog' ) ); ?>"><?php esc_html_e( 'ブログ', 'apprex' ); ?></a>
				<span> / </span><?php the_title(); ?>
			</nav>
			<?php
			$apprex_cats = get_the_category();
			if ( $apprex_cats ) :
				?>
				<span class="post-cat"><?php echo esc_html( $apprex_cats[0]->name ); ?></span>
			<?php endif; ?>
			<h1><?php the_title(); ?> <?php apprex_new_badge(); ?></h1>
			<p class="post-meta">
				<span>📅 <?php echo esc_html( get_the_date() ); ?></span>
				<span>✍ <?php echo esc_html( get_the_author() ); ?></span>
				<?php if ( function_exists( 'apprex_reading_time' ) ) : ?>
					<span>⏱ <?php echo esc_html( apprex_reading_time() ); ?>分で読めます</span>
				<?php endif; ?>
			</p>
		</div>
	</section>

	<article class="section">
		<div class="container">
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="post-hero-img"><?php the_post_thumbnail( 'large' ); ?></figure>
			<?php endif; ?>

			<div class="post-article">
				<?php the_content(); ?>
			</div>

			<div class="post-article">
				<?php apprex_share_buttons(); ?>
			</div>
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
