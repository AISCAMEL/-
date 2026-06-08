<?php
/**
 * Template Name: 機能説明ページ (Functions)
 *
 * Assign to the "functions" page. Spec §7 — 6 categories.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_categories = array(
	array( 'title' => __( '基本プラットフォーム', 'apprex' ), 'items' => array( __( 'プッシュ通知', 'apprex' ), __( '会員管理', 'apprex' ), __( 'ニュース・お知らせ配信', 'apprex' ) ) ),
	array( 'title' => __( 'コミュニケーション', 'apprex' ), 'items' => array( __( 'チャット / トーク', 'apprex' ), __( 'お問い合わせフォーム', 'apprex' ), __( 'アンケート', 'apprex' ) ) ),
	array( 'title' => __( 'マーケティング・販促', 'apprex' ), 'items' => array( __( 'クーポン配信', 'apprex' ), __( 'スタンプカード', 'apprex' ), __( 'セグメント配信', 'apprex' ) ) ),
	array( 'title' => __( 'EC・決済機能', 'apprex' ), 'items' => array( __( 'オンライン注文', 'apprex' ), __( 'モバイルオーダー', 'apprex' ), __( '各種決済対応', 'apprex' ) ) ),
	array( 'title' => __( '管理・運営', 'apprex' ), 'items' => array( __( '予約管理', 'apprex' ), __( '在庫確認', 'apprex' ), __( 'レポート・分析', 'apprex' ) ) ),
	array( 'title' => __( '外部連携', 'apprex' ), 'items' => array( __( 'API連携', 'apprex' ), __( '既存システム接続', 'apprex' ), __( '外部サービス連携', 'apprex' ) ) ),
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
			<p><?php esc_html_e( '6カテゴリの豊富な機能で、あらゆる業種のビジネスをサポートします。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<div class="grid grid--3">
				<?php foreach ( $apprex_categories as $cat ) : ?>
					<div class="feature-card is-reveal">
						<h3><?php echo esc_html( $cat['title'] ); ?></h3>
						<ul style="display:flex;flex-direction:column;gap:8px;margin-top:8px">
							<?php foreach ( $cat['items'] as $item ) : ?>
								<li style="padding-left:20px;position:relative">
									<span style="position:absolute;left:0;color:var(--color-primary);font-weight:800">›</span>
									<?php echo esc_html( $item ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endforeach; ?>
			</div>

			<?php if ( trim( get_the_content() ) ) : ?>
				<div class="content-prose mt-32"><?php the_content(); ?></div>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_template_part( 'template-parts/final-cta' );
get_footer();
