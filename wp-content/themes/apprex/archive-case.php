<?php
/**
 * Archive for the "case" CPT (/cases) with industry filter, spec §8.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_industries = get_terms(
	array(
		'taxonomy'   => 'industry',
		'hide_empty' => true,
	)
);
$apprex_current = get_queried_object();
?>
<section class="page-hero">
	<div class="container">
		<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'パンくず', 'apprex' ); ?>">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
			<span> / </span><?php esc_html_e( '導入事例', 'apprex' ); ?>
		</nav>
		<h1><?php esc_html_e( '導入事例', 'apprex' ); ?></h1>
		<p><?php esc_html_e( '9業種8,000社以上。業種別の成果につながった APPREX の活用事例をご紹介します。', 'apprex' ); ?></p>
	</div>
</section>

<section class="section">
	<div class="container">
		<?php if ( ! is_wp_error( $apprex_industries ) && ! empty( $apprex_industries ) ) : ?>
			<div class="cases-filter">
				<a class="tabs__btn<?php echo is_post_type_archive( 'case' ) ? ' is-active' : ''; ?>"
					href="<?php echo esc_url( get_post_type_archive_link( 'case' ) ); ?>"
					<?php echo is_post_type_archive( 'case' ) ? 'aria-selected="true"' : ''; ?>>
					<?php esc_html_e( 'すべて', 'apprex' ); ?>
				</a>
				<?php foreach ( $apprex_industries as $term ) : ?>
					<?php $is_current = ( $apprex_current instanceof WP_Term && $apprex_current->term_id === $term->term_id ); ?>
					<a class="tabs__btn<?php echo $is_current ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( get_term_link( $term ) ); ?>"
						<?php echo $is_current ? 'aria-selected="true"' : ''; ?>>
						<?php echo esc_html( $term->name ); ?>
					</a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="grid grid--3">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/case-card' );
				endwhile;
				?>
			</div>
			<div class="text-center mt-32"><?php the_posts_pagination(); ?></div>
		<?php else : ?>
			<p class="text-center"><?php esc_html_e( '導入事例は準備中です。', 'apprex' ); ?></p>
		<?php endif; ?>
	</div>
</section>

<?php
get_template_part( 'template-parts/final-cta' );
get_footer();
