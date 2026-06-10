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
			<?php apprex_section_head( 'About', __( 'APPREX のホームページ制作とは', 'apprex' ), __( 'AI とノーコードを組み合わせ、高品質なサイトを初期費用0円・月額制でスピード提供します。', 'apprex' ) ); ?>
			<div class="content-prose">
				<p><?php esc_html_e( '「制作費が高い」「公開まで時間がかかる」「更新が面倒」——そんなホームページの悩みを、APPREX が解決します。コーポレートサイト・サービスサイト・LP・採用ページ・EC まで、目的に合わせて最適な構成をご提案。公開後の更新・運用までワンストップで対応します。', 'apprex' ); ?></p>
			</div>

			<?php apprex_section_head( '', __( '対応できるサイト', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php
				$apprex_site_types = array(
					array( '🏢', 'コーポレートサイト', '会社の信頼を高める基本のサイト。会社概要・実績・お問い合わせを整備。' ),
					array( '🚀', 'サービス・LP', '商品やサービスの魅力を伝え、申込・問い合わせにつなげる訴求型ページ。' ),
					array( '🛒', 'ECサイト', '商品販売・オンライン決済・在庫管理に対応した売れるショップ。' ),
					array( '🧑‍💼', '採用サイト', '求職者に響くメッセージと応募導線で採用を強化。' ),
					array( '📰', 'オウンドメディア', 'ブログ・記事でSEO集客。AI記事生成にも対応。' ),
					array( '🔗', '既存サイトのリニューアル', '古いサイトを現代的なデザイン・スマホ対応に刷新。' ),
				);
				foreach ( $apprex_site_types as $t ) :
					?>
					<div class="feature-card is-reveal">
						<div class="icon" aria-hidden="true"><?php echo esc_html( $t[0] ); ?></div>
						<h3><?php echo esc_html( $t[1] ); ?></h3>
						<p><?php echo esc_html( $t[2] ); ?></p>
					</div>
					<?php
				endforeach;
				?>
			</div>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Flow', __( '制作の流れ', 'apprex' ), __( 'お申し込みから公開まで、最短スケジュールでご案内します。', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php
				$apprex_flow = array(
					array( 'STEP 1', 'ヒアリング', '目的・ターゲット・参考サイトをお伺いし、構成をご提案します。' ),
					array( 'STEP 2', 'デザイン・原稿', 'AIも活用してデザイン案と原稿を作成。スマホ表示も最適化。' ),
					array( 'STEP 3', '制作・実装', 'ご確認いただきながらページを制作。フォームや予約等も実装。' ),
					array( 'STEP 4', '公開', 'ドメイン・SSL設定を行い公開。最短スケジュールで対応します。' ),
					array( 'STEP 5', '運用・更新', '公開後の更新・改善・アクセス解析まで継続サポート。' ),
				);
				foreach ( $apprex_flow as $f ) :
					?>
					<div class="feature-card is-reveal">
						<span class="eyebrow"><?php echo esc_html( $f[0] ); ?></span>
						<h3><?php echo esc_html( $f[1] ); ?></h3>
						<p><?php echo esc_html( $f[2] ); ?></p>
					</div>
					<?php
				endforeach;
				?>
			</div>

			<div class="content-prose mt-32">
				<h2><?php esc_html_e( '月額プランに含まれるもの', 'apprex' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'レスポンシブ対応（PC・スマホ・タブレット）', 'apprex' ); ?></li>
					<li><?php esc_html_e( 'お問い合わせフォーム設置', 'apprex' ); ?></li>
					<li><?php esc_html_e( '常時SSL（https）化', 'apprex' ); ?></li>
					<li><?php esc_html_e( '基本的なSEO設定（タイトル・説明・構造化データ）', 'apprex' ); ?></li>
					<li><?php esc_html_e( '公開後の軽微な更新サポート', 'apprex' ); ?></li>
					<li><?php esc_html_e( 'サーバー・保守込み（月額制）', 'apprex' ); ?></li>
				</ul>
			</div>
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
