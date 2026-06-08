<?php
/**
 * Template Name: 特徴ページ (Features)
 *
 * Assign to the "features" page. Spec §7.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_features = array(
	array( 'icon' => '⚡', 'title' => __( 'ノーコード開発', 'apprex' ), 'desc' => __( 'プログラミング知識は一切不要。直感的なエディタで、誰でもアプリを構築・更新できます。', 'apprex' ) ),
	array( 'icon' => '💴', 'title' => __( '圧倒的な低価格', 'apprex' ), 'desc' => __( '従来の開発コストの1/10。初期費用0円キャンペーン、月額19,800円〜の業界最安水準。', 'apprex' ) ),
	array( 'icon' => '🚀', 'title' => __( 'スピード開発', 'apprex' ), 'desc' => __( '企画から最短2週間で公開。スピードが命のビジネスチャンスを逃しません。', 'apprex' ) ),
	array( 'icon' => '🎨', 'title' => __( 'カスタマイズ自由', 'apprex' ), 'desc' => __( '業種・用途に合わせ、デザインも機能も自由自在。成長に合わせて拡張できます。', 'apprex' ) ),
	array( 'icon' => '📱', 'title' => __( 'マルチプラットフォーム対応', 'apprex' ), 'desc' => __( 'iOS / Android の両方に、1つの管理画面から同時対応します。', 'apprex' ) ),
	array( 'icon' => '🔒', 'title' => __( 'セキュリティ万全', 'apprex' ), 'desc' => __( '稼働率99.9%保証。堅牢なクラウドインフラで、安心して運用いただけます。', 'apprex' ) ),
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
			<p><?php esc_html_e( 'APPREX が選ばれる6つの理由。ビジネスを加速させる強みをご紹介します。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<div class="grid grid--3">
				<?php foreach ( $apprex_features as $feature ) : ?>
					<div class="feature-card is-reveal">
						<div class="icon" aria-hidden="true"><?php echo esc_html( $feature['icon'] ); ?></div>
						<h3><?php echo esc_html( $feature['title'] ); ?></h3>
						<p><?php echo esc_html( $feature['desc'] ); ?></p>
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
