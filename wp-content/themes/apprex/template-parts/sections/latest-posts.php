<?php
/**
 * 新着記事セクション（NEW付き）。トップで興味喚起。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_q = apprex_latest_posts( 3 );
if ( ! $apprex_q->have_posts() ) {
	return;
}
?>
<section class="section section--soft" id="latest-posts">
	<div class="container">
		<?php apprex_section_head( 'Blog', __( '新着記事・お役立ち情報', 'apprex' ), __( 'ノーコード開発・DX・集客のヒントを発信中。', 'apprex' ) ); ?>
		<div class="grid grid--3">
			<?php
			while ( $apprex_q->have_posts() ) :
				$apprex_q->the_post();
				apprex_post_card();
			endwhile;
			wp_reset_postdata();
			?>
		</div>
		<div class="text-center mt-32 is-reveal">
			<a class="btn btn--ghost" href="<?php echo esc_url( apprex_page_url( 'blog' ) ); ?>"><?php esc_html_e( '記事一覧へ', 'apprex' ); ?></a>
		</div>
	</div>
</section>
