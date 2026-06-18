<?php
/**
 * Template Name: パートナー募集ページ (Partner)
 *
 * 取次販売・紹介パートナー / OEM販売パートナー募集。
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
			<p><?php esc_html_e( 'アプリ市場の拡大を、紹介ビジネスの機会に。紹介するだけで継続収益が積み上がる、APPREXのパートナー制度です。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Partner', __( 'パートナー（取次販売・紹介）とは', 'apprex' ) ); ?>
			<div class="content-prose">
				<p><?php esc_html_e( 'APPREXを必要とするお客様をご紹介・お取次ぎいただくと、成約に応じて継続的な報酬をお受け取りいただけます。在庫・開発・サポートは当社が担うため、リスクなく始められます。', 'apprex' ); ?></p>
			</div>

			<?php apprex_section_head( '', __( '報酬について', 'apprex' ) ); ?>
			<div class="partner-reward">
				<div class="partner-reward__item">
					<div class="partner-reward__num">10<small>%</small></div>
					<div class="partner-reward__label"><?php esc_html_e( '取次報酬', 'apprex' ); ?></div>
					<p style="font-size:.85rem;color:var(--color-muted)"><?php esc_html_e( '成約時の初期費用に対して', 'apprex' ); ?></p>
				</div>
				<div class="partner-reward__item">
					<div class="partner-reward__num">10<small>%</small></div>
					<div class="partner-reward__label"><?php esc_html_e( '継続報酬', 'apprex' ); ?></div>
					<p style="font-size:.85rem;color:var(--color-muted)"><?php esc_html_e( '月額利用料に対して継続的に', 'apprex' ); ?></p>
				</div>
			</div>
			<p class="plan-note"><?php esc_html_e( '※ 報酬率・条件はプラン・取次形態により異なります。詳細はお申し込み後にご案内します。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'For You', __( 'こんな方におすすめ', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php
				$apprex_for = array(
					array( '🤝', '代理店・紹介ビジネス', '既存の顧客基盤を活かして、紹介報酬を得たい方。' ),
					array( '💻', 'Web制作・広告会社', '制作メニューにアプリを加え、提案の幅を広げたい方。' ),
					array( '📣', 'SNS集客・インフルエンサー', '発信力を活かして、継続収益を作りたい方。' ),
				);
				foreach ( $apprex_for as $f ) :
					?>
					<div class="feature-card is-reveal">
						<div class="icon" aria-hidden="true"><?php echo esc_html( $f[0] ); ?></div>
						<h3><?php echo esc_html( $f[1] ); ?></h3>
						<p><?php echo esc_html( $f[2] ); ?></p>
					</div>
					<?php
				endforeach;
				?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Flow', __( '取次販売の流れ', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php
				$apprex_pflow = array(
					array( 'STEP 1', 'パートナー登録', '下記フォームから登録（無料）。' ),
					array( 'STEP 2', 'ご紹介・お取次ぎ', '見込み客をご紹介。商談は当社が対応も可能。' ),
					array( 'STEP 3', '成約・報酬', '成約に応じて取次・継続報酬をお支払い。' ),
				);
				foreach ( $apprex_pflow as $p ) :
					?>
					<div class="feature-card is-reveal">
						<span class="eyebrow"><?php echo esc_html( $p[0] ); ?></span>
						<h3><?php echo esc_html( $p[1] ); ?></h3>
						<p><?php echo esc_html( $p[2] ); ?></p>
					</div>
					<?php
				endforeach;
				?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Terms', __( 'パートナー契約の概要', 'apprex' ), __( '主な契約条件です。詳細はお申し込み後に契約書でご案内します。', 'apprex' ) ); ?>
			<div class="partner-terms">
				<table>
					<tbody>
						<?php
						$apprex_terms = array(
							array( '契約形態', '取次・紹介パートナー（販売代理）。OEM販売パートナーも別途ご相談可能。' ),
							array( '取次報酬', '成約時の初期費用に対して10%（目安・プランにより変動）。' ),
							array( '継続報酬', '月額利用料に対して継続的に10%（目安・契約継続中）。' ),
							array( '報酬の支払い', '月末締め・翌月末払い（銀行振込）。※条件は契約書に準ずる。' ),
							array( '契約期間', '1年単位（自動更新）。中途解約は事前通知により可。' ),
							array( 'パートナーの役割', '見込み客のご紹介・お取次ぎ。商談・サポートは当社が対応も可能。' ),
							array( '当社の役割', '開発・サポート・請求管理・成約後の運用を担当。' ),
							array( '禁止事項', '虚偽説明・誇大広告・当社ブランドを毀損する行為等。' ),
							array( '費用・ノルマ', '加盟金・月額費用・販売ノルマなし（無料で開始）。' ),
						);
						foreach ( $apprex_terms as $t ) :
							?>
							<tr><th><?php echo esc_html( $t[0] ); ?></th><td><?php echo esc_html( $t[1] ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p class="plan-note"><?php esc_html_e( '※ 報酬率・支払い条件・契約期間などの具体的条件は、事業形態に応じて個別にご案内します（要確認事項）。', 'apprex' ); ?></p>
			</div>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container content-prose">
			<?php apprex_section_head( 'Apply', __( 'パートナー登録（無料）', 'apprex' ) ); ?>
			<?php if ( trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php endif; ?>
			<?php apprex_render_form( 'partner' ); ?>
		</div>
	</section>
	<?php
endwhile;

get_template_part( 'template-parts/final-cta' );
get_footer();
