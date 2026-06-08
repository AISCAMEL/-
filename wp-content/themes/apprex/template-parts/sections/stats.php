<?php
/**
 * 02. Stats — counter animation, spec §6.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_stats = array(
	array(
		'value'  => '8,000',
		'suffix' => '+',
		'label'  => __( '導入実績', 'apprex' ),
	),
	array(
		'value'  => '1/10',
		'suffix' => '',
		'label'  => __( '従来コスト比', 'apprex' ),
	),
	array(
		'value'  => '2',
		'suffix' => __( '週間', 'apprex' ),
		'label'  => __( '最短公開', 'apprex' ),
	),
	array(
		'value'  => '99.9',
		'suffix' => '%',
		'label'  => __( '稼働率保証', 'apprex' ),
	),
);
?>
<section class="section section--soft" id="stats">
	<div class="container">
		<div class="stats">
			<?php foreach ( $apprex_stats as $stat ) : ?>
				<div class="stat is-reveal">
					<div class="stat__num" data-counter="<?php echo esc_attr( $stat['value'] ); ?>">
						<?php echo esc_html( $stat['value'] ); ?><small><?php echo esc_html( $stat['suffix'] ); ?></small>
					</div>
					<div class="stat__label"><?php echo esc_html( $stat['label'] ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
