<?php
/**
 * Template Name: 全国SEO LP（HP型・ボリューム版）
 *
 * ヘッダー/フッター付きの“HP型”ランディングページ。全国対応キーワード・47都道府県・
 * FAQ構造化データ・多セクション構成で、検索流入を狙う（インデックス対象）。
 * 固定ページのタイトル＝H1、抜粋＝meta description になります。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_contact  = home_url( '/contact/' );
$apprex_estimate = home_url( '/estimate/' );
$apprex_meeting  = function_exists( 'apprex_meeting_url' ) ? apprex_meeting_url() : $apprex_contact;

$apprex_title = get_the_title();
$apprex_lead  = has_excerpt() ? get_the_excerpt() : '全国どこからでも、ノーコードでiOS/Androidアプリを開発。初期費用0円・月額19,800円〜、最短2週間で公開。オンラインで完結するので、47都道府県どこでも対応します。';

/* 特徴 */
$apprex_feats = array(
	array( '🗾', '全国どこでも対応', '打ち合わせ・制作・運用はすべてオンラインで完結。地方・都市部を問わず、全国47都道府県のお客様に対応します。' ),
	array( '💰', '初期費用0円・月額制', '開発費0円、月額19,800円〜。まとまった初期投資なしで、アプリ・ホームページを始められます。' ),
	array( '⚡', '最短2週間で公開', 'ノーコード基盤＋専任サポートで、企画から公開まで最短2週間。スピーディに集客を始められます。' ),
	array( '🔧', 'プログラミング不要', '専門知識は不要。管理画面から更新でき、公開後の運用もかんたんです。' ),
	array( '🔔', 'プッシュ通知無制限', 'アプリのプッシュ通知は回数無制限。再来店・リピート促進に直結します。' ),
	array( '🤝', '専任サポート', '導入から運用まで担当者が伴走。初めての方でも安心して進められます。' ),
);

/* 機能 */
$apprex_functions = array( '予約管理', '会員管理', 'EC・モバイルオーダー', '電子スタンプカード', 'クーポン配信', 'プッシュ通知', '多店舗対応', '決済・サブスク', '問い合わせフォーム', 'アクセス解析', '電子カタログ', 'SNS連携' );

/* 依頼の流れ */
$apprex_flow = array(
	array( 'お問い合わせ', 'フォームからご相談内容を送信（全国どこからでもOK）' ),
	array( 'オンライン相談・お見積り', 'Web会議でヒアリングし、最適なプランをご提案' ),
	array( 'ご契約・制作開始', '内容にご納得いただいてから着手。最短2週間で公開' ),
	array( '公開・運用サポート', '公開後も継続してサポート・改善' ),
);

/* FAQ（構造化データにも使用） */
$apprex_faqs = array(
	array( '地方でも対応してもらえますか？', 'はい。打ち合わせから制作・運用までオンラインで完結するため、全国47都道府県どこからでもご依頼いただけます。訪問は不要です。' ),
	array( '費用はどのくらいですか？', 'アプリ開発は初期費用0円・月額19,800円〜、ホームページ制作は月額9,800円〜です。内容により個別お見積りとなる場合もあります。' ),
	array( 'どのくらいで公開できますか？', '最短2週間で公開可能です。要件やボリュームにより前後します。' ),
	array( 'プログラミングの知識は必要ですか？', '不要です。ノーコードで制作し、公開後は管理画面から簡単に更新できます。' ),
	array( 'どんな業種に対応していますか？', '飲食・美容・小売・士業・教育・不動産・BtoBなど、業種を問わず対応しています。マッチングアプリ等の個別開発も可能です。' ),
);

/* 全国対応エリア（47都道府県） */
$apprex_prefs = array(
	'北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
	'茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
	'新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県',
	'岐阜県', '静岡県', '愛知県', '三重県',
	'滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県',
	'鳥取県', '島根県', '岡山県', '広島県', '山口県',
	'徳島県', '香川県', '愛媛県', '高知県',
	'福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
);
?>

<article class="nlp">
	<section class="page-hero nlp-hero">
		<div class="container">
			<span class="nlp-badge">🗾 全国47都道府県 対応</span>
			<h1><?php echo esc_html( $apprex_title ); ?></h1>
			<p><?php echo esc_html( $apprex_lead ); ?></p>
			<div class="nlp-hero__cta">
				<a class="btn btn--cta" href="<?php echo esc_url( $apprex_meeting ); ?>"><?php esc_html_e( '無料でオンライン相談', 'apprex' ); ?></a>
				<a class="btn btn--ghost" href="<?php echo esc_url( $apprex_estimate ); ?>"><?php esc_html_e( '料金をシミュレーション', 'apprex' ); ?></a>
			</div>
		</div>
	</section>

	<?php if ( trim( get_the_content() ) ) : ?>
		<section class="section"><div class="container content-prose"><?php the_content(); ?></div></section>
	<?php endif; ?>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Features', '全国のお客様に選ばれる理由', 'オンライン完結だから、地域を問わず同じ品質・同じ価格でご提供します。' ); ?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_feats as $f ) : ?>
					<div class="feature-card is-reveal">
						<div class="feature-card__icon" aria-hidden="true"><?php echo esc_html( $f[0] ); ?></div>
						<h3><?php echo esc_html( $f[1] ); ?></h3>
						<p><?php echo esc_html( $f[2] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Functions', '搭載できる主な機能', '必要な機能を組み合わせて、業種に合わせたアプリ・サイトを構築できます。' ); ?>
			<ul class="nlp-funcs">
				<?php foreach ( $apprex_functions as $fn ) : ?>
					<li><?php echo esc_html( $fn ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Price', '料金の目安', '初期費用0円・月額制。まずは概算をシミュレーションできます。' ); ?>
			<div class="grid grid--2" style="max-width:760px;margin-inline:auto;">
				<div class="price-card is-reveal">
					<h3><?php esc_html_e( 'アプリ開発', 'apprex' ); ?></h3>
					<div class="price">19,800<small><?php esc_html_e( '円 / 月〜（税抜）', 'apprex' ); ?></small></div>
					<ul class="feats"><li>初期費用0円</li><li>プッシュ通知無制限</li><li>iOS/Android対応</li></ul>
					<a class="btn btn--ghost" href="<?php echo esc_url( $apprex_estimate ); ?>"><?php esc_html_e( '見積もりする', 'apprex' ); ?></a>
				</div>
				<div class="price-card is-reveal">
					<h3><?php esc_html_e( 'ホームページ制作', 'apprex' ); ?></h3>
					<div class="price">9,800<small><?php esc_html_e( '円 / 月〜（税抜）', 'apprex' ); ?></small></div>
					<ul class="feats"><li>初期費用0円</li><li>スマホ・SEO対応</li><li>更新サポート込み</li></ul>
					<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/hp-creation/' ) ); ?>"><?php esc_html_e( '詳しく見る', 'apprex' ); ?></a>
				</div>
			</div>
			<p class="plan-note"><?php esc_html_e( '※ 表示価格はすべて税抜です。マッチングアプリ等の個別開発は要相談。', 'apprex' ); ?></p>
		</div>
	</section>

	<?php
	$apprex_cases = get_posts( array( 'post_type' => 'case', 'post_status' => 'publish', 'posts_per_page' => 3, 'fields' => 'ids' ) );
	if ( $apprex_cases ) :
		?>
		<section class="section">
			<div class="container">
				<?php apprex_section_head( 'Cases', '全国の導入事例', '様々な業種・地域で導入いただいています。' ); ?>
				<div class="grid grid--3">
					<?php foreach ( $apprex_cases as $cid ) : ?>
						<a class="case-card is-reveal" href="<?php echo esc_url( get_permalink( $cid ) ); ?>">
							<?php echo get_the_post_thumbnail( $cid, 'apprex-card', array( 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<h3><?php echo esc_html( get_the_title( $cid ) ); ?></h3>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Flow', 'ご依頼の流れ', 'オンラインで完結。全国どこからでも同じ流れで進みます。' ); ?>
			<div class="flow-steps">
				<?php foreach ( $apprex_flow as $i => $step ) : ?>
					<div class="flow-step is-reveal">
						<span class="flow-step__no"><?php echo (int) ( $i + 1 ); ?></span>
						<p><?php echo esc_html( $step[0] ); ?><br><small><?php echo esc_html( $step[1] ); ?></small></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Area', '全国対応エリア', '下記すべての地域でご依頼いただけます（オンライン対応）。' ); ?>
			<ul class="nlp-area">
				<?php foreach ( $apprex_prefs as $pref ) : ?>
					<li><?php echo esc_html( $pref ); ?></li>
				<?php endforeach; ?>
			</ul>
			<p class="plan-note"><?php esc_html_e( '北海道から沖縄まで、全国47都道府県のアプリ開発・ホームページ制作に対応しています。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'FAQ', 'よくあるご質問' ); ?>
			<div class="nlp-faq">
				<?php foreach ( $apprex_faqs as $qa ) : ?>
					<details class="nlp-faq__item">
						<summary><?php echo esc_html( $qa[0] ); ?></summary>
						<p><?php echo esc_html( $qa[1] ); ?></p>
					</details>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section nlp-cta">
		<div class="container" style="text-align:center;">
			<h2><?php esc_html_e( 'まずは無料でご相談ください', 'apprex' ); ?></h2>
			<p><?php esc_html_e( '全国どこからでもオンラインで対応。しつこい営業はいたしません。', 'apprex' ); ?></p>
			<div class="nlp-hero__cta" style="justify-content:center;">
				<a class="btn btn--cta" href="<?php echo esc_url( $apprex_meeting ); ?>"><?php esc_html_e( '無料でオンライン相談', 'apprex' ); ?></a>
				<a class="btn btn--ghost" href="<?php echo esc_url( $apprex_contact ); ?>"><?php esc_html_e( 'お問い合わせ', 'apprex' ); ?></a>
			</div>
		</div>
	</section>
</article>

<?php
// 構造化データ（FAQPage ＋ Service：全国対応）。
$apprex_faq_ld = array();
foreach ( $apprex_faqs as $qa ) {
	$apprex_faq_ld[] = array(
		'@type'          => 'Question',
		'name'           => $qa[0],
		'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $qa[1] ),
	);
}
$apprex_ld = array(
	'@context' => 'https://schema.org',
	'@graph'   => array(
		array(
			'@type'           => 'Service',
			'name'            => wp_strip_all_tags( $apprex_title ),
			'serviceType'     => 'アプリ開発・ホームページ制作',
			'provider'        => array( '@type' => 'Organization', 'name' => '合同会社アイズ（APPREX）' ),
			'areaServed'      => array( '@type' => 'Country', 'name' => '日本' ),
			'url'             => get_permalink(),
			'description'     => wp_strip_all_tags( $apprex_lead ),
		),
		array(
			'@type'      => 'FAQPage',
			'mainEntity' => $apprex_faq_ld,
		),
	),
);
echo '<script type="application/ld+json">' . wp_json_encode( $apprex_ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';

get_footer();
