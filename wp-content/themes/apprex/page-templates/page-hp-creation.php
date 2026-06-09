<?php
/**
 * Template Name: ホームページ制作ページ (HP Creation)
 *
 * Assign to the "hp-creation" page. 修正要件 §4：初期費用0円・月額制＋サブスク。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_hp_plans = array(
	array( 'name' => __( 'Light', 'apprex' ), 'price' => '9,800', 'desc' => __( '基本的なコーポレートサイト', 'apprex' ), 'featured' => false ),
	array( 'name' => __( 'Standard', 'apprex' ), 'price' => '19,800', 'desc' => __( 'LP付き・問い合わせフォーム充実', 'apprex' ), 'featured' => true ),
	array( 'name' => __( 'Premium', 'apprex' ), 'price' => '39,800', 'desc' => __( 'EC機能・会員機能付き', 'apprex' ), 'featured' => false ),
	array( 'name' => __( 'Enterprise', 'apprex' ), 'price' => __( '要相談', 'apprex' ), 'desc' => __( '大規模・カスタム開発対応', 'apprex' ), 'featured' => false ),
);

$apprex_hp_subs = array(
	array( 'name' => __( 'デザイン・バナー制作', 'apprex' ), 'price' => __( '月額 5万円〜', 'apprex' ), 'desc' => __( 'バナー・SNS画像・LPビジュアル・A/Bテスト用制作', 'apprex' ) ),
	array( 'name' => __( '広告運用代行', 'apprex' ), 'price' => __( '月額 5万円〜', 'apprex' ), 'desc' => __( 'Google/Meta 広告運用・クリエイティブ制作・月次レポート', 'apprex' ) ),
	array( 'name' => __( 'Webコンサルティング', 'apprex' ), 'price' => __( '月額 要相談', 'apprex' ), 'desc' => __( 'Web戦略・月1ミーティング・改善提案・KPI設計', 'apprex' ) ),
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
			<p><?php esc_html_e( '初期費用0円・月額制。AI技術を活用した効率的な制作プロセスで、高品質サイトをスピード公開します。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Plans', __( '基本プラン（初期費用0円・月額制）', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_hp_plans as $plan ) : ?>
					<div class="price-card<?php echo $plan['featured'] ? ' price-card--featured' : ''; ?> is-reveal">
						<?php if ( $plan['featured'] ) : ?><span class="ribbon"><?php esc_html_e( 'おすすめ', 'apprex' ); ?></span><?php endif; ?>
						<h3><?php echo esc_html( $plan['name'] ); ?></h3>
						<div class="price"><?php echo esc_html( $plan['price'] ); ?><small><?php echo is_numeric( str_replace( ',', '', $plan['price'] ) ) ? esc_html__( '円 / 月〜', 'apprex' ) : ''; ?></small></div>
						<p style="color:var(--color-muted);font-size:.95rem"><?php echo esc_html( $plan['desc'] ); ?></p>
						<a class="btn <?php echo $plan['featured'] ? 'btn--primary' : 'btn--ghost'; ?>" href="<?php echo esc_url( apprex_page_url( 'contact' ) ); ?>"><?php esc_html_e( 'お問い合わせ', 'apprex' ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Subscription', __( 'サブスクリプションオプション', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_hp_subs as $sub ) : ?>
					<div class="feature-card is-reveal">
						<h3><?php echo esc_html( $sub['name'] ); ?></h3>
						<div class="price" style="font-size:1.4rem;color:var(--color-primary)"><?php echo esc_html( $sub['price'] ); ?></div>
						<p><?php echo esc_html( $sub['desc'] ); ?></p>
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
