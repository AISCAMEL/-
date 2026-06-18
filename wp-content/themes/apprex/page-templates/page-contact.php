<?php
/**
 * Template Name: お問い合わせページ (Contact)
 *
 * Assign to the "contact" page. Spec §12.
 *
 * Recommended: paste a Contact Form 7 / WPForms shortcode into the page editor.
 * A static placeholder form is shown when the editor content is empty.
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
			</nav>
			<h1><?php the_title(); ?></h1>
			<p><?php esc_html_e( 'ご質問・オンライン相談のご予約はこちらから。担当者より折り返しご連絡します。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container content-prose">
			<?php if ( trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php endif; ?>

			<div class="callout is-reveal" style="margin-bottom:28px">
				<h3><?php esc_html_e( 'パートナー（取次販売・紹介）も募集中', 'apprex' ); ?></h3>
				<p><?php esc_html_e( '紹介するだけで継続報酬。代理店・Web制作・SNS集客の方を歓迎します。', 'apprex' ); ?></p>
				<a class="btn btn--light" href="<?php echo esc_url( apprex_page_url( 'partner' ) ); ?>"><?php esc_html_e( 'パートナー制度を見る', 'apprex' ); ?></a>
			</div>

			<?php apprex_render_form( 'contact' ); ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
