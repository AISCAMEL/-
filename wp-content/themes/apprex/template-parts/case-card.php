<?php
/**
 * Reusable case card. Expects the loop to be set up (the_post()).
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_industry = apprex_field( 'case_industry' );
$apprex_metric_1 = apprex_field( 'case_metric_1' );
$apprex_duration = apprex_field( 'case_duration' );
?>
<a class="case-card is-reveal" href="<?php the_permalink(); ?>">
	<?php if ( has_post_thumbnail() ) : ?>
		<?php the_post_thumbnail( 'apprex-card', array( 'class' => 'case-card__thumb', 'alt' => esc_attr( get_the_title() ) ) ); ?>
	<?php else : ?>
		<span class="case-card__thumb" aria-hidden="true"></span>
	<?php endif; ?>

	<div class="case-card__body">
		<?php if ( $apprex_industry ) : ?>
			<span class="case-card__industry"><?php echo esc_html( $apprex_industry ); ?></span>
		<?php endif; ?>
		<h3 class="case-card__title"><?php the_title(); ?></h3>
		<div class="case-card__metrics">
			<?php if ( $apprex_metric_1 ) : ?>
				<span class="tag"><?php echo esc_html( $apprex_metric_1 ); ?></span>
			<?php endif; ?>
			<?php if ( $apprex_duration ) : ?>
				<span class="tag tag--accent"><?php echo esc_html( $apprex_duration ); ?></span>
			<?php endif; ?>
		</div>
	</div>
</a>
