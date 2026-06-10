<?php
/**
 * Template Name: 料金プランページ (Pricing)
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
			<p><?php esc_html_e( '開発費0円・月額制。初期設定費は今月キャンペーンで0円。まずは30日間無料でお試しください。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php get_template_part( 'template-parts/pricing-table' ); ?>
			<?php if ( trim( get_the_content() ) ) : ?>
				<div class="content-prose mt-32"><?php the_content(); ?></div>
			<?php endif; ?>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Compare', __( 'アプリ開発プラン 比較表', 'apprex' ) ); ?>
			<div class="compare-wrap">
				<table class="compare-table">
					<thead>
						<tr>
							<th><?php esc_html_e( '項目', 'apprex' ); ?></th>
							<th><?php esc_html_e( 'トライアル', 'apprex' ); ?></th>
							<th class="is-feat"><?php esc_html_e( 'スタート', 'apprex' ); ?></th>
							<th><?php esc_html_e( 'ビジネス', 'apprex' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$apprex_rows = array(
							array( '月額（税抜）', '19,800円', '39,800円', '59,800円' ),
							array( '初期設定費（今月）', '0円（通常10万）', '0円（通常30万）', '0円（通常50万）' ),
							array( '開発費用', '0円', '0円', '0円' ),
							array( '基本機能', '◯', '◯', '◯' ),
							array( '電子カタログ機能', '—', '◯', '◯' ),
							array( '多店舗機能（無制限）', '—', '—', '◯' ),
							array( 'プッシュ通知', '無制限', '無制限', '無制限' ),
							array( '決済・サブスク決済', '可能', '可能', '可能' ),
							array( 'ダウンロード数課金', 'なし', 'なし', 'なし' ),
							array( '最低利用期間', '1年契約', '1年契約', '1年契約' ),
							array( '制作代行（オプション）', '10万円', '10万円', '20万円' ),
						);
						foreach ( $apprex_rows as $r ) :
							?>
							<tr>
								<th><?php echo esc_html( $r[0] ); ?></th>
								<td><?php echo esc_html( $r[1] ); ?></td>
								<td class="is-feat"><?php echo esc_html( $r[2] ); ?></td>
								<td><?php echo esc_html( $r[3] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<section class="section section--soft" id="quote-plans">
		<div class="container">
			<?php apprex_section_head( 'Advanced', __( '個別見積プラン（要相談）', 'apprex' ), __( 'マッチングアプリ・フルスクラッチ・ソース買取まで、あらゆるアプリに対応します。', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php foreach ( apprex_quote_plans() as $q ) : ?>
					<div class="quote-card is-reveal">
						<h3><?php echo esc_html( $q['label'] ); ?></h3>
						<p class="quote-card__lead"><?php echo esc_html( $q['lead'] ); ?></p>
						<table class="quote-card__table">
							<?php foreach ( $q['rows'] as $k => $v ) : ?>
								<tr><th><?php echo esc_html( $k ); ?></th><td><?php echo esc_html( $v ); ?></td></tr>
							<?php endforeach; ?>
						</table>
						<?php if ( ! empty( $q['examples'] ) ) : ?>
							<div class="quote-card__tags">
								<?php foreach ( $q['examples'] as $ex ) : ?>
									<span class="tag"><?php echo esc_html( $ex ); ?></span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<a class="btn btn--ghost btn--block" href="<?php echo esc_url( apprex_page_url( 'contact' ) ); ?>"><?php esc_html_e( 'この内容で相談する', 'apprex' ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="content-prose mt-32" style="font-size:.85rem;color:var(--color-muted)">
				<p><?php esc_html_e( '（注1）初期設定費＝サーバー利用料・管理画面（コントロールパネル）の設定費用・管理費用、AppStore/GooglePlay 登録代行費を含みます。', 'apprex' ); ?></p>
				<p><?php esc_html_e( '（注2）キャンペーン期間中、制作代行は基本10メニューまで10万円。それ以上はページ数・内容により別途見積。自社制作の場合は制作代行費用は不要（Zoom説明可）。', 'apprex' ); ?></p>
				<p><?php esc_html_e( '（注3）基本機能＝スタートの電子カタログ機能・ビジネスの多店舗機能を除く全機能。', 'apprex' ); ?></p>
				<p><?php esc_html_e( '店舗業に限らずどんな業種・どんなアプリでも開発可能。ダウンロード数による制限・課金なし、プッシュ通知無制限、多店舗追加も無制限・無課金。', 'apprex' ); ?></p>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'FAQ', __( '料金に関するよくある質問', 'apprex' ) ); ?>
			<?php get_template_part( 'template-parts/faq-list' ); ?>
		</div>
	</section>
	<?php
endwhile;

get_template_part( 'template-parts/final-cta' );
get_footer();
