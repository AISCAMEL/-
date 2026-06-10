<?php
/**
 * Template Name: 会社概要ページ (Company)
 *
 * Assign to the "company" page. 合同会社アイズ。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_company = array(
	array( __( '会社名', 'apprex' ), __( '合同会社アイズ', 'apprex' ) ),
	array( __( 'サービス名', 'apprex' ), __( 'APPREX（アプリックス）', 'apprex' ) ),
	array( __( '事業内容', 'apprex' ), __( 'ノーコードアプリ開発プラットフォームの提供／アプリ制作代行／ホームページ制作／DX・補助金活用支援', 'apprex' ) ),
	array( __( '受付時間', 'apprex' ), __( '平日 10:00〜18:00（チャット・メール・オンライン相談）', 'apprex' ) ),
	array( __( 'メール', 'apprex' ), apprex_contact_email() ),
	array( __( '公式SNS', 'apprex' ), __( 'Instagram：@apprex1173', 'apprex' ) ),
);

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
			<p><?php esc_html_e( '中小企業のDXを、ノーコードと制作代行で支援します。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container content-prose">
			<table style="width:100%;border-collapse:collapse">
				<tbody>
					<?php foreach ( $apprex_company as $row ) : ?>
						<tr>
							<th style="text-align:left;padding:16px;border-bottom:1px solid var(--color-line);width:30%;vertical-align:top;color:var(--color-ink)"><?php echo esc_html( $row[0] ); ?></th>
							<td style="padding:16px;border-bottom:1px solid var(--color-line)"><?php echo esc_html( $row[1] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( trim( get_the_content() ) ) : ?>
				<div class="mt-32"><?php the_content(); ?></div>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_template_part( 'template-parts/final-cta' );
get_footer();
