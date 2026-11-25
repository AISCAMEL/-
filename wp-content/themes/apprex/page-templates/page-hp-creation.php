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

$apprex_hp_plans = array();
foreach ( apprex_hp_plans() as $apprex_k => $apprex_p ) {
	$apprex_hp_plans[] = array( 'name' => $apprex_p[0], 'price' => number_format( $apprex_p[1] ), 'desc' => $apprex_p[2], 'featured' => 'standard' === $apprex_k );
}
$apprex_hp_plans[] = array( 'name' => 'Enterprise', 'price' => __( '要相談', 'apprex' ), 'desc' => __( '大規模・カスタム開発対応', 'apprex' ), 'featured' => false );
$apprex_hp_hear = '#hearing';
$apprex_hp_meet = apprex_page_url( 'meeting' );

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
			</nav>
			<h1><?php the_title(); ?></h1>
			<p><?php esc_html_e( '制作費0円・月額9,800円〜。ご要望をお伺いして、提案書・企画・デザインまでプロがご提案します。公開後の更新・運用もおまかせください。', 'apprex' ); ?></p>
			<p style="margin-top:18px;display:flex;gap:10px;flex-wrap:wrap">
				<a class="btn btn--primary" href="<?php echo esc_url( $apprex_hp_hear ); ?>"><?php esc_html_e( '無料でヒアリングを申し込む', 'apprex' ); ?></a>
				<a class="btn btn--ghost" href="<?php echo esc_url( $apprex_hp_meet ); ?>"><?php esc_html_e( 'オンラインで相談する', 'apprex' ); ?></a>
			</p>
			<p style="font-size:.9rem;opacity:.85;margin-top:8px"><?php esc_html_e( '提案書まで無料。ご契約は内容にご納得いただいてからです（契約期間3年）。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'About', __( 'APPREX のホームページ制作とは', 'apprex' ), __( 'ヒアリングしてプロが提案。制作費0円・月額制で、作ったあとの運用まで続けてお手伝いします。', 'apprex' ) ); ?>
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
			<?php apprex_section_head( 'Flow', __( 'ご契約までの流れ', 'apprex' ), __( 'まずはご要望をお聞かせください。提案書までは無料です。', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php
				$apprex_flow = array(
					array( 'STEP 1', 'ヒアリング（無料）', '下のフォームか、オンラインで、目的・お客様・ほしいページ・参考サイトをお伺いします。' ),
					array( 'STEP 2', '提案書・企画・デザイン（無料）', 'ご要望をもとに、企画・ページ構成・デザイン案をまとめた提案書をお作りします。' ),
					array( 'STEP 3', '月額のお見積り', '提案にご納得いただけたら、内容に合う月額プランでお見積りします。制作費は0円です。' ),
					array( 'STEP 4', 'ご契約（3年）', 'お見積りの内容でご契約。契約期間は3年（36か月）です。' ),
					array( 'STEP 5', '制作・公開', 'ご確認いただきながら制作し、ドメイン・SSLを設定して公開します。' ),
					array( 'STEP 6', '運用・更新', '公開後の更新・改善まで、月額の中で続けてサポートします。' ),
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
			<?php apprex_section_head( 'Plans', __( '月額プラン（制作費0円・3年契約）', 'apprex' ), __( '金額は月額・税抜です。どのプランが合うかは、提案書でご提案します。', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_hp_plans as $plan ) : ?>
					<div class="price-card<?php echo $plan['featured'] ? ' price-card--featured' : ''; ?> is-reveal">
						<?php if ( $plan['featured'] ) : ?><span class="ribbon"><?php esc_html_e( 'おすすめ', 'apprex' ); ?></span><?php endif; ?>
						<h3><?php echo esc_html( $plan['name'] ); ?></h3>
						<div class="price"><?php echo esc_html( $plan['price'] ); ?><small><?php echo is_numeric( str_replace( ',', '', $plan['price'] ) ) ? esc_html__( '円 / 月〜', 'apprex' ) : ''; ?></small></div>
						<p style="color:var(--color-muted);font-size:.95rem"><?php echo esc_html( $plan['desc'] ); ?></p>
						<a class="btn <?php echo $plan['featured'] ? 'btn--primary' : 'btn--ghost'; ?>" href="<?php echo esc_url( $apprex_hp_hear ); ?>"><?php esc_html_e( 'ヒアリングを申し込む', 'apprex' ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Contract', __( 'ご契約について', 'apprex' ) ); ?>
			<?php $apprex_ex = apprex_hp_plans()['standard'][1]; ?>
			<div class="content-prose">
				<table>
					<tbody>
						<tr><th><?php esc_html_e( '制作費', 'apprex' ); ?></th><td><?php esc_html_e( '0円', 'apprex' ); ?></td></tr>
						<tr><th><?php esc_html_e( '月額', 'apprex' ); ?></th><td><?php esc_html_e( 'Light 9,800円／Standard 19,800円／Premium 39,800円（税抜）。サーバー・保守・更新サポートを含みます。', 'apprex' ); ?></td></tr>
						<tr><th><?php esc_html_e( '契約期間', 'apprex' ); ?></th><td><?php echo esc_html( sprintf( '3年（%dか月）', apprex_hp_term_months() ) ); ?></td></tr>
						<tr><th><?php esc_html_e( '途中で解約するとき', 'apprex' ); ?></th><td><?php esc_html_e( '解約金として「残りの月数 × 月額」をお支払いいただきます。', 'apprex' ); ?><br>
							<small><?php echo esc_html( sprintf( '例：Standard（月額%s円）を12か月で解約 → 残り%dか月 × %s円 ＝ %s円', number_format( $apprex_ex ), apprex_hp_term_months() - 12, number_format( $apprex_ex ), number_format( apprex_hp_penalty( $apprex_ex, 12 ) ) ) ); ?></small></td></tr>
						<tr><th><?php esc_html_e( '3年たったあと', 'apprex' ); ?></th><td><?php esc_html_e( '続けるか・どのプランにするかは、ご契約の際に契約書でご案内します。', 'apprex' ); ?></td></tr>
					</tbody>
				</table>
				<p><small><?php esc_html_e( '制作費をいただかない代わりに、3年間の月額で費用をいただく仕組みです。提案書・お見積りまでは無料で、ご契約は内容にご納得いただいてからです。', 'apprex' ); ?></small></p>
			</div>
		</div>
	</section>

	<section class="section" id="hearing">
		<div class="container" style="max-width:760px">
			<?php apprex_section_head( 'Hearing', __( '無料でヒアリングを申し込む', 'apprex' ), __( 'わかる範囲で大丈夫です。担当者が内容を確認して、2営業日以内にご連絡します。', 'apprex' ) ); ?>
			<?php apprex_render_form( 'hp' ); ?>
			<p style="margin-top:14px;text-align:center"><a href="<?php echo esc_url( $apprex_hp_meet ); ?>"><?php esc_html_e( '文字で書くのが大変な方は、オンライン相談（無料）をご予約ください →', 'apprex' ); ?></a></p>
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
