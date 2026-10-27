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
			</nav>
			<h1><?php the_title(); ?></h1>
			<p><?php esc_html_e( 'デザインと仕様書は、AIスタジオで無料で作れます。アプリは月額19,800円〜、ホームページは月額9,800円〜。初期費用やオプションは、AIスタジオで作った内容をもとにお見積りします。', 'apprex' ); ?></p>
		</div>
	</section>

	<?php get_template_part( 'template-parts/price-hero' ); ?>

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
							array( '基本機能', '◯', '◯', '◯' ),
							array( '電子カタログ機能', '—', '◯', '◯' ),
							array( '多店舗の展開・運用（店舗一覧・店舗ごとの配信）', '—', '—', '◯（20店舗まで込み）' ),
							array( 'プッシュ通知', '無制限', '無制限', '無制限' ),
							array( '決済・サブスク決済', '可能', '可能', '可能' ),
							array( 'ダウンロード数課金', 'なし', 'なし', 'なし' ),
							array( '最低利用期間', '1年契約', '1年契約', '1年契約' ),
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
								<?php
								// 金額の行は表に並べず、下の見積りボタンにまとめる。
								if ( preg_match( '/見積|相談/u', $v ) ) {
									continue;
								}
								?>
								<tr><th><?php echo esc_html( $k ); ?></th><td><?php echo esc_html( $v ); ?></td></tr>
							<?php endforeach; ?>
						</table>
						<p class="quote-card__ask"><?php esc_html_e( '費用', 'apprex' ); ?> <?php echo apprex_ask_pill(); // phpcs:ignore ?></p>
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
				<p><?php esc_html_e( '（注1）初期費用には、制作・ストア申請と、サーバー・管理画面（コントロールパネル）の設定を含みます。アプリストアの登録費用もあわせてお見積りします。', 'apprex' ); ?></p>
				<p><?php esc_html_e( '（注2）AIスタジオ（無料）で作ったデザイン・仕様書から制作すると、1からヒアリングして作るより初期費用を抑えられます。金額は内容に合わせてお見積りします。', 'apprex' ); ?></p>
				<p><?php esc_html_e( '（注3）基本機能＝スタートの電子カタログ機能・ビジネスの多店舗機能を除く全機能。', 'apprex' ); ?></p>
				<?php if ( function_exists( 'apprex_rn_enabled' ) && apprex_rn_enabled() ) : ?>
					<p><?php esc_html_e( '補助金を使った導入もご相談いただけます。', 'apprex' ); ?> <a href="<?php echo esc_url( apprex_page_url( 'subsidy' ) ); ?>"><?php esc_html_e( '補助金で導入', 'apprex' ); ?></a></p>
				<?php endif; ?>
				<p><?php esc_html_e( '店舗業に限らずどんな業種・どんなアプリでも開発可能。ダウンロード数による制限・課金なし、プッシュ通知無制限、ビジネスプランは20店舗まで月額に含まれます（21店舗目からはお見積り）。', 'apprex' ); ?></p>
			</div>
		</div>
	</section>

	<?php
	$apprex_estimate_url = home_url( '/estimate/' );
	$apprex_contact_url  = home_url( '/contact/' );
	?>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Subscription', __( 'サブスク制作プラン（月額・継続サポート）', 'apprex' ), __( '毎月の更新・改善をまるごとお任せ。制作から運用まで月額で完結します。', 'apprex' ) ); ?>
			<div class="grid grid--2" style="max-width:760px;margin-inline:auto">
				<div class="price-card is-reveal">
					<h3><?php esc_html_e( 'ベーシック', 'apprex' ); ?></h3>
					<div class="price"><small><?php esc_html_e( '月額', 'apprex' ); ?></small>50,000<small><?php esc_html_e( '円〜（税抜）', 'apprex' ); ?></small></div>
					<ul class="feats">
						<li><?php esc_html_e( '月次の更新・修正対応', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'コンテンツ追加（小規模）', 'apprex' ); ?></li>
						<li><?php esc_html_e( '基本SEO・表示崩れ対応', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'メールサポート', 'apprex' ); ?></li>
					</ul>
					<a class="btn btn--ghost" href="<?php echo esc_url( $apprex_estimate_url ); ?>"><?php esc_html_e( '見積もり・申込', 'apprex' ); ?></a>
				</div>
				<div class="price-card price-card--featured is-reveal">
					<span class="ribbon"><?php esc_html_e( 'おすすめ', 'apprex' ); ?></span>
					<h3><?php esc_html_e( 'スタンダード', 'apprex' ); ?></h3>
					<div class="price price--ask"><?php echo apprex_ask_pill(); // phpcs:ignore ?></div>
					<ul class="feats">
						<li><?php esc_html_e( 'ベーシックの全内容', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'コンテンツ追加（中規模・LP含む）', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'アクセス解析レポート（月次）', 'apprex' ); ?></li>
						<li><?php esc_html_e( '構造化データ・LLMO最適化', 'apprex' ); ?></li>
						<li><?php esc_html_e( '優先サポート', 'apprex' ); ?></li>
					</ul>
					<a class="btn btn--cta" href="<?php echo esc_url( $apprex_estimate_url ); ?>"><?php esc_html_e( '見積もり・申込', 'apprex' ); ?></a>
				</div>
			</div>
			<p class="plan-note"><?php esc_html_e( '※ 表示価格はすべて税抜です。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Option', __( 'オプション単品', 'apprex' ), __( '必要な施策だけを単品で。組み合わせは自由です。', 'apprex' ) ); ?>
			<?php
			$apprex_options = array(
				array(
					'name' => 'AIMEO（AIで運用するMEO対策）',
					'desc' => 'Googleマップ・ローカル検索の集客を、AIで簡単運用。投稿・口コミ返信・説明文の下書きをAIが作り、担当者が確認して運用します。',
					'rows' => array(
						array( 'ライト', '月額15,000円〜', 'AIで口コミ返信・基本情報の最適化' ),
						array( 'スタンダード', 'お見積り', 'AIで毎週の投稿運用・写真追加・順位レポート' ),
						array( 'プロ', 'お見積り', '複数店舗・競合分析・改善提案' ),
					),
				),
				array(
					'name' => 'LLMO対策（AI検索最適化）',
					'desc' => 'ChatGPT等のAI検索に引用されやすい構造へ最適化。',
					'rows' => array(
						array( '継続', '月額20,000円〜', '月次の改善・追記運用' ),
						array( 'スポット', 'お見積り', '初回診断＋最適化（一回）' ),
					),
				),
				array(
					'name' => 'HP構造化データSEO',
					'desc' => '検索エンジンに正しく伝わる構造化マークアップを実装。',
					'rows' => array(
						array( '基本実装', '30,000円〜', '主要ページの構造化データ実装（一回）' ),
						array( 'フル実装', 'お見積り', '全ページ＋リッチリザルト対応（一回）' ),
					),
				),
				array(
					'name' => 'バナー制作',
					'desc' => 'SNS・広告・サイト用のバナーをプロがデザイン。',
					'rows' => array(
						array( '1点', '5,000円〜', '通常バナー1点' ),
						array( 'セット', 'お見積り', 'まとめてのご依頼はお得になります' ),
					),
				),
			);
			?>
			<div class="grid grid--2">
				<?php foreach ( $apprex_options as $opt ) : ?>
					<div class="quote-card is-reveal">
						<h3><?php echo esc_html( $opt['name'] ); ?></h3>
						<p class="quote-card__lead"><?php echo esc_html( $opt['desc'] ); ?></p>
						<table class="opt-table">
							<?php foreach ( $opt['rows'] as $row ) : ?>
								<tr>
									<th><?php echo esc_html( $row[0] ); ?></th>
									<td class="opt-price"><?php echo 'お見積り' === $row[1] ? apprex_ask_pill( '見積り' ) : esc_html( $row[1] ); // phpcs:ignore ?></td>
									<td><?php echo esc_html( $row[2] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</table>
						<a class="btn btn--ghost btn--block" href="<?php echo esc_url( $apprex_contact_url ); ?>"><?php esc_html_e( 'この内容で相談する', 'apprex' ); ?></a>
					</div>
				<?php endforeach; ?>
			</div>
			<p class="plan-note"><?php esc_html_e( '※ 表示価格はすべて税抜です。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Package', __( 'おすすめパッケージ', 'apprex' ), __( '人気の施策をまとめてお得に。月額49,800円〜。内容に合わせてお見積りします。', 'apprex' ) ); ?>
			<div class="pricing">
				<div class="price-card is-reveal">
					<h3><?php esc_html_e( '集客強化セット', 'apprex' ); ?></h3>
					<div class="price"><small><?php esc_html_e( '月額', 'apprex' ); ?></small>49,800<small><?php esc_html_e( '円〜（税抜）', 'apprex' ); ?></small></div>
					<ul class="feats">
						<li><?php esc_html_e( 'AIMEO（スタンダード）', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'バナー制作（毎月5点）', 'apprex' ); ?></li>
						<li><?php esc_html_e( '月次レポート', 'apprex' ); ?></li>
					</ul>
					<a class="btn btn--ghost" href="<?php echo esc_url( $apprex_contact_url ); ?>"><?php esc_html_e( 'このセットで相談する', 'apprex' ); ?></a>
				</div>
				<div class="price-card price-card--featured is-reveal">
					<span class="ribbon"><?php esc_html_e( 'おすすめ', 'apprex' ); ?></span>
					<h3><?php esc_html_e( 'AI時代の集客フルセット', 'apprex' ); ?></h3>
					<div class="price price--ask"><?php echo apprex_ask_pill(); // phpcs:ignore ?></div>
					<ul class="feats">
						<li><?php esc_html_e( 'AIMEO（プロ）', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'LLMO対策（継続）', 'apprex' ); ?></li>
						<li><?php esc_html_e( '構造化データSEO', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'バナー制作（毎月10点）', 'apprex' ); ?></li>
					</ul>
					<a class="btn btn--cta" href="<?php echo esc_url( $apprex_contact_url ); ?>"><?php esc_html_e( 'このセットで相談する', 'apprex' ); ?></a>
				</div>
				<div class="price-card is-reveal">
					<h3><?php esc_html_e( '制作＋集客セット', 'apprex' ); ?></h3>
					<div class="price price--ask"><?php echo apprex_ask_pill(); // phpcs:ignore ?></div>
					<ul class="feats">
						<li><?php esc_html_e( 'サブスク制作（スタンダード）', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'AIMEO（スタンダード）', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'バナー制作（毎月5点）', 'apprex' ); ?></li>
					</ul>
					<a class="btn btn--ghost" href="<?php echo esc_url( $apprex_contact_url ); ?>"><?php esc_html_e( 'このセットで相談する', 'apprex' ); ?></a>
				</div>
			</div>
			<p class="price-note"><?php esc_html_e( '※ 表示価格はすべて税抜です。内容はご相談に応じて調整可能です。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Flow', __( 'ご依頼の流れ', 'apprex' ), __( 'お問い合わせから運用開始まで、最短でスムーズに進みます。', 'apprex' ) ); ?>
			<div class="flow-steps">
				<div class="flow-step is-reveal">
					<span class="flow-step__no">1</span>
					<p><?php esc_html_e( 'お問い合わせ', 'apprex' ); ?><br><small><?php esc_html_e( 'フォームからご相談内容を送信', 'apprex' ); ?></small></p>
				</div>
				<div class="flow-step is-reveal">
					<span class="flow-step__no">2</span>
					<p><?php esc_html_e( 'ヒアリング・お見積り', 'apprex' ); ?><br><small><?php esc_html_e( 'ご要望を確認し最適なプランをご提案', 'apprex' ); ?></small></p>
				</div>
				<div class="flow-step is-reveal">
					<span class="flow-step__no">3</span>
					<p><?php esc_html_e( 'ご契約・制作開始', 'apprex' ); ?><br><small><?php esc_html_e( '内容にご納得いただいてから着手', 'apprex' ); ?></small></p>
				</div>
				<div class="flow-step is-reveal">
					<span class="flow-step__no">4</span>
					<p><?php esc_html_e( '納品・運用サポート', 'apprex' ); ?><br><small><?php esc_html_e( '公開後も継続して改善をサポート', 'apprex' ); ?></small></p>
				</div>
			</div>
			<p class="plan-note"><?php esc_html_e( 'クレジットカード登録不要。まずはお気軽にご相談ください。', 'apprex' ); ?></p>
			<div style="text-align:center;margin-top:24px">
				<a class="btn btn--cta" href="<?php echo esc_url( $apprex_contact_url ); ?>"><?php esc_html_e( '無料で相談する', 'apprex' ); ?></a>
			</div>
		</div>
	</section>

	<?php get_template_part( 'template-parts/quote-steps' ); ?>

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
