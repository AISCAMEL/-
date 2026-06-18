<?php
/**
 * Template Name: 資料請求ページ (Document Request)
 *
 * Assign to the "document" page. 資料請求＋LINE誘導。
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
				<span> / </span><?php the_title(); ?>
			</nav>
			<h1><?php the_title(); ?></h1>
			<p><?php esc_html_e( 'APPREX のサービス内容・料金・導入事例をまとめた資料を無料でダウンロードいただけます。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container content-prose">
			<?php if ( trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php endif; ?>

			<div class="doc-view-cta" style="margin:0 0 28px;padding:20px 22px;background:var(--color-bg-soft,#f5f7fb);border:1px solid var(--color-line,#e5e7eb);border-radius:14px;">
				<p style="margin:0 0 12px;font-weight:700;"><?php esc_html_e( 'メール登録なしで、今すぐ資料をご覧いただけます。', 'apprex' ); ?></p>
				<a class="btn btn--cta" href="<?php echo esc_url( apprex_document_view_url() ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( '📄 資料をブラウザで見る', 'apprex' ); ?>
				</a>
				<p style="margin:12px 0 0;font-size:.9rem;color:var(--color-muted,#6b7280);"><?php esc_html_e( '※ 保存用に資料のお届け（メール）をご希望の方は、下のフォームからご請求ください。', 'apprex' ); ?></p>
			</div>

			<?php apprex_render_form( 'document' ); ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
