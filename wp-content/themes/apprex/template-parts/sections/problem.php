<?php
/**
 * 03. Problem — empathy with the target audience, spec §6.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_problems = array(
	__( 'アプリ開発費用が高すぎる', 'apprex' ),
	__( '開発に半年以上かかり遅い', 'apprex' ),
	__( 'エンジニアの採用・確保が難しい', 'apprex' ),
	__( '更新のたびにコストが重い', 'apprex' ),
);
?>
<section class="section" id="problem">
	<div class="container">
		<?php apprex_section_head( 'Problem', __( 'こんなお悩みありませんか？', 'apprex' ) ); ?>
		<div class="grid grid--2">
			<?php foreach ( $apprex_problems as $i => $problem ) : ?>
				<div class="problem-card is-reveal">
					<span class="icon"><?php echo esc_html( $i + 1 ); ?></span>
					<p><?php echo esc_html( $problem ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
