<?php
/**
 * Template Name: 補助金で導入
 *
 * アプリ・ホームページの導入に使える補助金の案内。
 * APPREXはIT導入補助金の支援事業者ではないため、持続化補助金・自治体の補助金などを案内する。
 * 申請書の作成・提出はお客様（または専門家）が行い、APPREXは材料づくりと進め方のアドバイスを担う。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_programs = array(
	array(
		'tag'  => '使えることが多い',
		'name' => '小規模事業者持続化補助金',
		'desc' => '小さな会社・お店が、新しいお客様を増やす取り組み（販路開拓）にかかる費用の一部を補助する制度です。アプリやホームページの費用は、主に「ウェブサイト関連費」として扱われます。',
		'note' => '商工会・商工会議所に事業計画を確認してもらう手続きがあります。ホームページ・アプリの費用だけでは申請できないなどの決まりがあります。',
	),
	array(
		'tag'  => '地域ごとに探す',
		'name' => '都道府県・市区町村の補助金',
		'desc' => 'DX・IT導入・販路開拓・創業などを支援する、自治体独自の補助金です。国の制度より条件が合いやすい場合があります。',
		'note' => '地域によって、ある・ない、金額、受付期間が大きく違います。お店や会社の所在地から一緒に探します。',
	),
	array(
		'tag'  => 'オリジナル開発で',
		'name' => 'ものづくり補助金などの大型の補助金',
		'desc' => '新しいサービスを始めるためのシステム開発など、規模の大きな取り組みが対象になる制度です。オリジナル開発・マッチングアプリで検討できる場合があります。',
		'note' => '要件が細かく、事業計画の内容が審査されます。早めにご相談ください。',
	),
);

$apprex_rules = array(
	array( '補助金は「あとからもらう」お金です', '多くの補助金は、いったん費用を全額お支払いいただき、実績を報告したあとで補助金が振り込まれます。' ),
	array( '決まる前に契約すると対象外になります', '「交付決定」の通知より前に契約・発注した費用は、補助の対象になりません。APPREXは交付決定を待ってから契約・制作を始めます。' ),
	array( '申請しても採択されないことがあります', '審査があるため、必ず受け取れるわけではありません。採択されなかった場合の進め方も、事前にご相談します。' ),
	array( '受付期間・金額・条件は回ごとに変わります', '公募要領（募集の案内）で、そのときの条件を確認しながら進めます。' ),
);

$apprex_help = array(
	array( '使える制度の確認', '業種・会社の規模・所在地・作りたいものから、使えそうな制度をご案内します。' ),
	array( '計画の材料づくり', 'AIスタジオ（無料）で作る企画書・仕様書は、事業計画の「何を作り、どう使うか」をそのまま説明する材料になります。' ),
	array( '見積書のご用意', '申請に必要な見積書を、仕様書の内容に合わせてお出しします。' ),
	array( 'スケジュールの調整', '交付決定 → 契約 → 制作 → 実績報告の順番に合わせて、公開日を計画します。' ),
);

$apprex_flow = array(
	array( 'STEP 1', 'AIスタジオで企画書・仕様書', '作りたいアプリを形にします（無料）。' ),
	array( 'STEP 2', '使える制度を確認', 'オンラインミーティングで、制度と進め方を確認します。' ),
	array( 'STEP 3', '申請', '見積書と企画書をもとに、お客様が申請します。' ),
	array( 'STEP 4', '採択・交付決定', '交付決定の通知を待ちます。' ),
	array( 'STEP 5', '契約・制作・公開', '交付決定のあとで契約し、制作を始めます。' ),
	array( 'STEP 6', '実績報告・補助金の受け取り', '支払いの記録などを報告し、補助金が振り込まれます。' ),
);

$apprex_faqs = array(
	array( 'q' => 'IT導入補助金は使えますか？', 'a' => 'IT導入補助金は、事前に登録された支援事業者とITツールだけが対象です。APPREXは登録していないため、IT導入補助金ではお使いいただけません。持続化補助金や自治体の補助金など、ほかの制度をご案内します。' ),
	array( 'q' => '申請書を代わりに書いてもらえますか？', 'a' => '申請書の作成・提出は、お客様ご自身で行っていただきます。APPREXは、企画書・仕様書・見積書など申請の材料のご用意と、進め方のアドバイスを行います。書類づくりを任せたい場合は、商工会・商工会議所や行政書士などの専門家にご相談ください。' ),
	array( 'q' => 'どのくらい補助されますか？', 'a' => '制度と回ごとに、補助率・上限額・対象になる費用が変わります。ミーティングで、そのときの公募要領を見ながらご案内します。' ),
	array( 'q' => '補助金が決まるまで、待たないといけませんか？', 'a' => '交付決定より前に契約した費用は対象外になるため、補助金を使う場合は交付決定を待ってから契約します。その間も、AIスタジオでデザインや仕様書を作り込んでおけます。' ),
	array( 'q' => '月額利用料も補助の対象になりますか？', 'a' => '制度によって、対象になる期間や費用の種類が決まっています。公募要領で確認してご案内します。' ),
	array( 'q' => '相談に費用はかかりますか？', 'a' => '補助金のご相談、AIスタジオ、オンラインミーティングは無料です。' ),
);

while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero">
		<div class="container">
			<nav class="breadcrumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
			</nav>
			<h1><?php esc_html_e( '補助金でアプリ・ホームページを導入', 'apprex' ); ?></h1>
			<p><?php esc_html_e( '使える補助金のご案内から、申請に使う企画書・見積書のご用意、交付決定に合わせた制作スケジュールまでサポートします。ご相談は無料です。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Programs', __( '使える可能性がある補助金', 'apprex' ), __( 'お店・会社の規模や所在地、作りたいものによって使える制度が変わります。', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_programs as $p ) : ?>
					<div class="feature-card is-reveal">
						<span class="eyebrow"><?php echo esc_html( $p['tag'] ); ?></span>
						<h3><?php echo esc_html( $p['name'] ); ?></h3>
						<p><?php echo esc_html( $p['desc'] ); ?></p>
						<p class="agency-fit"><b><?php esc_html_e( '注意', 'apprex' ); ?></b><?php echo esc_html( $p['note'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
			<div class="callout-note mt-32">
				<b><?php esc_html_e( 'IT導入補助金について', 'apprex' ); ?></b>
				<p><?php esc_html_e( 'IT導入補助金は、事前に登録された支援事業者とITツールだけが対象です。APPREXは登録していないため、IT導入補助金ではお使いいただけません。', 'apprex' ); ?></p>
			</div>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Rules', __( '補助金を使う前に知っておきたいこと', 'apprex' ) ); ?>
			<div class="grid grid--2">
				<?php foreach ( $apprex_rules as $r ) : ?>
					<div class="feature-card is-reveal">
						<h3><?php echo esc_html( $r[0] ); ?></h3>
						<p><?php echo esc_html( $r[1] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Support', __( 'APPREXがお手伝いすること', 'apprex' ), __( '申請書の作成・提出はお客様が行います。APPREXは、申請の材料づくりと進め方をサポートします。', 'apprex' ) ); ?>
			<div class="grid grid--2">
				<?php foreach ( $apprex_help as $h ) : ?>
					<div class="feature-card is-reveal">
						<h3><?php echo esc_html( $h[0] ); ?></h3>
						<p><?php echo esc_html( $h[1] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Flow', __( '補助金を使うときの流れ', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_flow as $f ) : ?>
					<div class="feature-card is-reveal">
						<span class="eyebrow"><?php echo esc_html( $f[0] ); ?></span>
						<h3><?php echo esc_html( $f[1] ); ?></h3>
						<p><?php echo esc_html( $f[2] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'FAQ', __( '補助金のよくある質問', 'apprex' ) ); ?>
			<?php get_template_part( 'template-parts/faq-list', null, array( 'faqs' => $apprex_faqs ) ); ?>
			<div class="final-cta__actions mt-32">
				<a class="btn btn--primary btn--lg" href="<?php echo esc_url( apprex_studio_url() ); ?>"><?php esc_html_e( 'AIスタジオで企画書を作る（無料）', 'apprex' ); ?></a>
				<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( apprex_page_url( 'meeting' ) ); ?>"><?php esc_html_e( '補助金について相談する', 'apprex' ); ?></a>
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
