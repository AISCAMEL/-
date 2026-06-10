<?php
/**
 * Template Name: 見積もり〜発注ページ (Estimate to Order)
 *
 * Interactive estimate calculator that flows into an order submission.
 * Pricing/markup is rendered by assets/js/estimate.js from APPREX_PRICING.
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
			<p><?php esc_html_e( 'サービスとプランを選ぶだけで概算が表示され、そのままお見積り＆お申し込み（発注）まで完結できます。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<div class="estimate" id="apprex-estimate">

				<div class="estimate__steps">
					<!-- Step 1: service -->
					<div class="estimate__block">
						<h3 class="estimate__h"><span>1</span><?php esc_html_e( 'サービスを選択', 'apprex' ); ?></h3>
						<div class="estimate__choices" id="est-services"></div>
					</div>

					<!-- Step 2: plan -->
					<div class="estimate__block">
						<h3 class="estimate__h"><span>2</span><?php esc_html_e( 'プランを選択', 'apprex' ); ?></h3>
						<div class="estimate__choices" id="est-plans"></div>
					</div>

					<!-- Step 3: options -->
					<div class="estimate__block">
						<h3 class="estimate__h"><span>3</span><?php esc_html_e( 'オプション（任意）', 'apprex' ); ?></h3>
						<div class="estimate__options" id="est-options"></div>
						<p class="estimate__hint" style="margin-top:12px"><?php esc_html_e( '追加項目（バナー制作・構造化・MEO・LLMO・LINE構築・API連動・基幹連携・補助金サポート等）はすべて別途見積もりです。発注時の「ご要望」欄にご記入いただくか、お問い合わせください。', 'apprex' ); ?></p>
					</div>
				</div>

				<!-- Sticky summary + order -->
				<aside class="estimate__summary" id="est-summary">
					<h3><?php esc_html_e( 'お見積り（概算・税抜）', 'apprex' ); ?></h3>
					<div class="estimate__total" id="est-total">
						<p class="estimate__hint"><?php esc_html_e( 'サービスとプランを選択してください。', 'apprex' ); ?></p>
					</div>

					<form class="estimate__order" id="est-order" hidden>
						<h4><?php esc_html_e( 'この内容で申し込む', 'apprex' ); ?></h4>
						<label><?php esc_html_e( 'お名前', 'apprex' ); ?> <span>*</span>
							<input type="text" name="name" required>
						</label>
						<label><?php esc_html_e( '会社名', 'apprex' ); ?>
							<input type="text" name="company">
						</label>
						<label><?php esc_html_e( 'メールアドレス', 'apprex' ); ?> <span>*</span>
							<input type="email" name="email" required>
						</label>
						<label><?php esc_html_e( 'ご要望（任意）', 'apprex' ); ?>
							<textarea name="message" rows="3"></textarea>
						</label>
						<button type="submit" class="btn btn--primary btn--block"><?php esc_html_e( 'この内容で発注する', 'apprex' ); ?></button>
						<p class="estimate__note"><?php esc_html_e( '※ 発注後、担当者より2営業日以内にご連絡します。最低契約期間・初期費用などの条件は事前にご確認ください。', 'apprex' ); ?></p>
					</form>

					<div class="estimate__done" id="est-done" hidden></div>
				</aside>

			</div>
		</div>
	</section>
	<?php
endwhile;

get_template_part( 'template-parts/final-cta' );
get_footer();
