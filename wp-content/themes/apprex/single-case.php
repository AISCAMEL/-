<?php
/**
 * Single "case" — detail page describing the solution process, spec §8.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

while ( have_posts() ) :
	the_post();

	$apprex_industry = apprex_field( 'case_industry' );
	$apprex_metric_1 = apprex_field( 'case_metric_1' );
	$apprex_metric_2 = apprex_field( 'case_metric_2' );
	$apprex_duration = apprex_field( 'case_duration' );
	$apprex_features = apprex_field( 'case_features' );
	?>
	<section class="page-hero">
		<div class="container">
			<nav class="breadcrumbs" aria-label="<?php esc_attr_e( 'パンくず', 'apprex' ); ?>">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
				<span> / </span>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'case' ) ); ?>"><?php esc_html_e( '導入事例', 'apprex' ); ?></a>
			</nav>
			<?php if ( $apprex_industry ) : ?>
				<span class="case-card__industry"><?php echo esc_html( $apprex_industry ); ?></span>
			<?php endif; ?>
			<h1><?php the_title(); ?></h1>
		</div>
	</section>

	<article class="section">
		<div class="container content-prose">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail( 'large' ); ?>
			<?php endif; ?>

			<div class="case-meta">
				<?php if ( $apprex_metric_1 ) : ?>
					<div class="case-meta__item">
						<div class="case-meta__label"><?php esc_html_e( '成果指標', 'apprex' ); ?></div>
						<div class="case-meta__value"><?php echo esc_html( $apprex_metric_1 ); ?></div>
					</div>
				<?php endif; ?>
				<?php if ( $apprex_metric_2 ) : ?>
					<div class="case-meta__item">
						<div class="case-meta__label"><?php esc_html_e( '成果指標', 'apprex' ); ?></div>
						<div class="case-meta__value"><?php echo esc_html( $apprex_metric_2 ); ?></div>
					</div>
				<?php endif; ?>
				<?php if ( $apprex_duration ) : ?>
					<div class="case-meta__item">
						<div class="case-meta__label"><?php esc_html_e( '開発期間', 'apprex' ); ?></div>
						<div class="case-meta__value"><?php echo esc_html( $apprex_duration ); ?></div>
					</div>
				<?php endif; ?>
				<?php if ( $apprex_industry ) : ?>
					<div class="case-meta__item">
						<div class="case-meta__label"><?php esc_html_e( '業種', 'apprex' ); ?></div>
						<div class="case-meta__value"><?php echo esc_html( $apprex_industry ); ?></div>
					</div>
				<?php endif; ?>
			</div>

			<?php the_content(); ?>

			<?php if ( $apprex_features ) : ?>
				<h2><?php esc_html_e( '利用した主な機能', 'apprex' ); ?></h2>
				<ul>
					<?php foreach ( preg_split( '/\r\n|\r|\n/', $apprex_features ) as $feat ) : ?>
						<?php if ( '' !== trim( $feat ) ) : ?>
							<li><?php echo esc_html( trim( $feat ) ); ?></li>
						<?php endif; ?>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
	</article>
	<?php
endwhile;

get_template_part( 'template-parts/final-cta' );
get_footer();
