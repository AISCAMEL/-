<?php
/**
 * Template Name: 代理店募集ページ (Partner)
 *
 * 紹介代理店・販売代理店・OEM代理店の募集。報酬・加盟金の金額はページに出さず、
 * LINEで予約する説明会（inc/seminar.php）でご説明する。
 * 代理店コード（?ref=）から来たお問い合わせ・会員登録・発注には紹介元が記録される（inc/agency.php）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_sem_url  = function_exists( 'apprex_seminar_url' ) ? apprex_seminar_url() : '#apply';
$apprex_sem_list = function_exists( 'apprex_seminar_upcoming' ) ? apprex_seminar_upcoming( 3 ) : array();

$apprex_types = array(
	array(
		'name' => '紹介代理店',
		'who'  => 'お客様を紹介するだけ',
		'desc' => '見込みのお客様をAPPREXにご紹介ください。商談・契約・制作・サポートはAPPREXが行います。営業の経験がなくても始められます。',
		'fit'  => '顧客を持つ士業・コンサル・地域の事業者、SNSで発信している方',
		'key'  => 'introducer',
	),
	array(
		'name' => '販売代理店',
		'who'  => '商談から契約まで',
		'desc' => '代理店がお客様と商談し、契約まで進めます。制作・ストア申請・公開後のサポートはAPPREXが担当します。紹介代理店より報酬が大きくなります。',
		'fit'  => 'Web制作会社・広告代理店・IT販売店・営業会社',
		'key'  => 'reseller',
	),
	array(
		'name' => 'OEM代理店',
		'who'  => '自社ブランドで販売',
		'desc' => '代理店の名前で、アプリ制作サービスとして販売できます。制作はAPPREXの基盤で行います。条件は事業内容に合わせて個別にご相談します。',
		'fit'  => '自社サービスにアプリ制作を加えたい制作会社・システム会社',
		'key'  => 'oem',
	),
);

$apprex_roles = array(
	array( '見込み客のご紹介', '代理店', '代理店', '代理店' ),
	array( '商談・お見積り', 'APPREX', '代理店（APPREXが同席も可）', '代理店' ),
	array( '契約・請求', 'APPREX', 'APPREX', '代理店' ),
	array( '制作・ストア申請', 'APPREX', 'APPREX', 'APPREX' ),
	array( '公開後のサポート', 'APPREX', 'APPREX', '代理店（APPREXが後方支援）' ),
);

$apprex_why = array(
	array( '01', 'その場で「アプリの形」を見せられる', 'AIスタジオ（無料）で、お客様の業種と店名を入れるだけで画面デザインと仕様書ができます。スマホで触れるデモアプリも出せるので、説明より早く伝わります。' ),
	array( '02', '料金がわかりやすい', 'AIスタジオで作った内容から制作して、初期費用15万円〜・月額19,800円〜。見積もりページで概算がすぐ出せます。' ),
	array( '03', '続くほど積み上がる報酬', '初期費用だけでなく、月額利用料からも報酬をお支払いするストック型です。お客様が使い続けるほど積み上がります。' ),
	array( '04', 'ホームページ・オプションも提案できる', '仕様書ができると、ホームページ制作やオプション、集客の施策もAIスタジオが提案します。1件の商談で提案の幅が広がります。' ),
	array( '05', '補助金の案内ができる', '持続化補助金や自治体の補助金など、使える制度の案内ページがあります。お客様の「費用が心配」に答えやすくなります。' ),
	array( '06', '制作とサポートはAPPREXが担当', '代理店は開発の知識がなくても大丈夫です。制作・ストア申請・公開後の運用はAPPREXが行います。' ),
);

$apprex_give = array(
	array( '代理店コード', 'あなた専用のURL（?ref=コード）をお渡しします。そのURLから届いたお問い合わせ・会員登録・発注は、あなたの紹介として記録されます。' ),
	array( '営業資料・料金表', 'サービス資料、料金表、業種別の提案例をお渡しします。' ),
	array( 'デモの見せ方', 'AIスタジオとデモアプリを使った商談の進め方を、オンラインでご説明します。' ),
	array( '商談の同席', '販売代理店の大きな商談には、APPREXの担当者がオンラインで同席できます。' ),
	array( '代理店専用ページ', 'ログインすると、紹介URL・QRコード、ご紹介したお客様の状況、毎月の報酬の明細、営業資料をいつでも確認できます。商談中のお客様の登録もここからできます。' ),
);

$apprex_flow = array(
	array( 'STEP 1', 'LINEで説明会を予約', 'LINEの予約ページから、ご都合の良い日程を選ぶだけ（無料・オンライン）。参加URLは前日と1時間前にLINEで届きます。' ),
	array( 'STEP 2', '説明会に参加', '3つの代理店の違い、報酬のしくみと加盟金、AIスタジオを使った商談の進め方をご説明します。質問もその場でどうぞ。' ),
	array( 'STEP 3', 'お申し込み・面談', '説明会のあと、希望する代理店の種類を選んでお申し込みください。事業内容を伺い、条件をご案内します。' ),
	array( 'STEP 4', '代理店契約', '条件に合意いただけたら、代理店契約を結びます。' ),
	array( 'STEP 5', '導入研修・代理店コード', 'オンラインの導入研修のあと、専用URL・営業ツール一式・代理店専用ページをお渡しします。' ),
	array( 'STEP 6', 'ご紹介・報酬のお支払い', 'お客様にAIスタジオを見せながらご提案ください。成約に応じて、初期費用と月額利用料から報酬をお支払いします。' ),
);

$apprex_terms = array(
	array( '代理店の種類', '紹介代理店／販売代理店／OEM代理店' ),
	array( '報酬', '初期費用と月額利用料からお支払いするストック型。月額分は、ご紹介したお客様が契約を続けているあいだ毎月お支払いします。報酬率は代理店の種類ごとに決まっていて、説明会でご説明します。' ),
	array( '報酬の対象', 'ノーコードアプリ・ホームページ・AIMEOなどの初期費用（制作費を含む）と月額利用料（税抜）。アプリ登録費・オプションは対象外。オリジナル開発・マッチングアプリは成約のときに個別に決めます。' ),
	array( '加盟金', '代理店の種類ごとに決まっています（契約時に1回）。導入研修・営業ツール一式・代理店専用ページを含みます。金額は説明会でご説明します。' ),
	array( '報酬の支払い', '月末締め・翌月末払い（銀行振込）。詳しくは代理店契約書に定めます。' ),
	array( '契約期間', '1年単位（自動更新）。' ),
	array( '月額費用・ノルマ', '月額費用・販売ノルマはありません。' ),
	array( '取扱い商品', 'ノーコードアプリ（AIスタジオから制作）、ホームページ制作、オプション、オリジナル開発・マッチングアプリ（APPREXが見積り）。' ),
	array( '禁止事項', '事実と違う説明・誇大な広告、APPREXの名前や信用を傷つける行為など。' ),
);

$apprex_faqs = array(
	array( 'q' => '個人でも代理店になれますか？', 'a' => 'はい。紹介代理店は、個人事業主やSNSで発信している方も歓迎です。販売代理店・OEM代理店は、面談で事業内容を伺ってからご案内します。' ),
	array( 'q' => '営業やアプリの知識がなくても大丈夫ですか？', 'a' => '大丈夫です。紹介代理店はお客様をつなぐだけで、商談はAPPREXが行います。AIスタジオを見せるだけでアプリの形が伝わるので、専門知識がなくても紹介しやすくなっています。' ),
	array( 'q' => '報酬率はいくらですか？', 'a' => '代理店の種類ごとに決まっていて、説明会（無料・オンライン）でくわしくご説明します。初期費用だけでなく月額利用料からもお支払いするストック型で、月額分はお客様が契約を続けているあいだ毎月お支払いします。' ),
	array( 'q' => '説明会はどうやって参加しますか？', 'a' => 'LINEの予約ページから日程を選ぶだけです。オンライン開催・参加無料で、参加URLは前日と1時間前にLINEでお送りします。ご都合の合う日程がないときは、次回のご案内を受け取れます。' ),
	array( 'q' => '報酬はいつまで受け取れますか？', 'a' => 'ご紹介したお客様が契約を続けているあいだ、月額利用料に応じた報酬をお支払いします。詳しくは代理店契約書に定めます。' ),
	array( 'q' => '紹介したことはどうやって記録されますか？', 'a' => '代理店ごとに専用のURL（代理店コード）をお渡しします。そのURLから届いたお問い合わせ・AIスタジオの会員登録・発注は、紹介元として記録されます。' ),
	array( 'q' => 'お客様に補助金の説明はできますか？', 'a' => '補助金のご案内ページを用意しています。使える制度の確認や申請の進め方は、APPREXがお客様に直接ご案内します。' ),
	array( 'q' => '加盟金はかかりますか？', 'a' => '代理店の種類ごとに決まっていて、契約時に1回お支払いいただきます。導入研修・営業ツール一式・代理店専用ページを含みます。金額は説明会でご説明します。月額費用や販売ノルマはありません。' ),
);

while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero">
		<div class="container">
			<nav class="breadcrumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
			</nav>
			<h1><?php esc_html_e( '代理店募集', 'apprex' ); ?></h1>
			<p><?php esc_html_e( 'AIスタジオで、お客様のアプリの形をその場で見せられるから売りやすい。アプリとホームページの代理店（紹介・販売・OEM）を募集しています。ストック型の報酬が毎月積み上がります。報酬・加盟金は、LINEで予約できる説明会（無料・オンライン）でご説明します。', 'apprex' ); ?></p>
			<div class="partner-hero-cta">
				<a class="btn btn--line btn--lg" href="<?php echo esc_url( $apprex_sem_url ); ?>"><?php esc_html_e( 'LINEで説明会を予約する', 'apprex' ); ?></a>
				<a class="btn btn--ghost" href="#seminar"><?php esc_html_e( '説明会の内容を見る', 'apprex' ); ?></a>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Types', __( '3つの代理店から選べます', 'apprex' ), __( 'どこまで関わるかで選べます。迷ったら、説明会でいっしょに決めましょう。', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_types as $t ) : ?>
					<div class="feature-card agency-type is-reveal">
						<span class="eyebrow"><?php echo esc_html( $t['who'] ); ?></span>
						<h3><?php echo esc_html( $t['name'] ); ?></h3>
						<p><?php echo esc_html( $t['desc'] ); ?></p>
						<p class="agency-fit"><b><?php esc_html_e( '向いている方', 'apprex' ); ?></b><?php echo esc_html( $t['fit'] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="compare-wrap mt-32">
				<table class="compare-table agency-roles">
					<thead>
						<tr><th><?php esc_html_e( 'だれが担当するか', 'apprex' ); ?></th><th><?php esc_html_e( '紹介代理店', 'apprex' ); ?></th><th class="is-feat"><?php esc_html_e( '販売代理店', 'apprex' ); ?></th><th><?php esc_html_e( 'OEM代理店', 'apprex' ); ?></th></tr>
					</thead>
					<tbody>
						<?php foreach ( $apprex_roles as $r ) : ?>
							<tr><th><?php echo esc_html( $r[0] ); ?></th><td><?php echo esc_html( $r[1] ); ?></td><td class="is-feat"><?php echo esc_html( $r[2] ); ?></td><td><?php echo esc_html( $r[3] ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<section class="section section--soft" id="seminar">
		<span id="reward"></span>
		<div class="container">
			<?php apprex_section_head( 'Seminar', __( '報酬・加盟金は、説明会でご説明します', 'apprex' ), __( '無料・オンラインの代理店説明会を開いています。LINEで日程を選ぶだけで予約でき、参加URLもLINEに届きます。', 'apprex' ) ); ?>
			<div class="grid grid--2">
				<div class="feature-card">
					<h3><?php esc_html_e( '説明会でわかること', 'apprex' ); ?></h3>
					<ul class="agency-list">
						<li><?php esc_html_e( '紹介・販売・OEM、3つの代理店の違いと選び方', 'apprex' ); ?></li>
						<li><?php esc_html_e( '報酬のしくみ（初期費用＋月額のストック型）と報酬率', 'apprex' ); ?></li>
						<li><?php esc_html_e( '加盟金と、それに含まれるもの（導入研修・営業ツール一式・代理店専用ページ）', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'AIスタジオとデモアプリを使った商談の進め方', 'apprex' ); ?></li>
						<li><?php esc_html_e( 'お申し込みから活動を始めるまでの流れ', 'apprex' ); ?></li>
					</ul>
				</div>
				<div class="feature-card seminar-card">
					<h3><?php esc_html_e( '次回の説明会', 'apprex' ); ?></h3>
					<?php if ( $apprex_sem_list ) : ?>
						<ul class="seminar-dates">
							<?php foreach ( $apprex_sem_list as $apprex_s ) : ?>
								<li><b><?php echo esc_html( apprex_seminar_when( $apprex_s ) ); ?></b><span><?php echo esc_html( $apprex_s['title'] ); ?></span></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p><?php esc_html_e( '次回の日程は準備中です。LINEの予約ページから「次回の案内」を受け取れます。', 'apprex' ); ?></p>
					<?php endif; ?>
					<p class="seminar-meta"><?php esc_html_e( 'オンライン開催・参加無料・約60分', 'apprex' ); ?></p>
					<a class="btn btn--line btn--block" href="<?php echo esc_url( $apprex_sem_url ); ?>"><?php esc_html_e( 'LINEで説明会を予約する', 'apprex' ); ?></a>
				</div>
			</div>
			<p class="plan-note"><?php esc_html_e( '※ 月額費用・販売ノルマはありません。条件は代理店契約書に定めた内容が優先されます。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Why APPREX', __( 'APPREXが売りやすい理由', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_why as $w ) : ?>
					<div class="feature-card is-reveal">
						<span class="eyebrow"><?php echo esc_html( $w[0] ); ?></span>
						<h3><?php echo esc_html( $w[1] ); ?></h3>
						<p><?php echo esc_html( $w[2] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Support', __( '代理店にお渡しするもの', 'apprex' ) ); ?>
			<div class="grid grid--2">
				<?php foreach ( $apprex_give as $g ) : ?>
					<div class="feature-card is-reveal">
						<h3><?php echo esc_html( $g[0] ); ?></h3>
						<p><?php echo esc_html( $g[1] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section section--soft">
		<div class="container">
			<?php apprex_section_head( 'Flow', __( '代理店になるまでの流れ', 'apprex' ) ); ?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_flow as $p ) : ?>
					<div class="feature-card is-reveal">
						<span class="eyebrow"><?php echo esc_html( $p[0] ); ?></span>
						<h3><?php echo esc_html( $p[1] ); ?></h3>
						<p><?php echo esc_html( $p[2] ); ?></p>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php apprex_section_head( 'Terms', __( '代理店契約の概要', 'apprex' ), __( '主な条件です。報酬率・加盟金の金額は説明会でご説明し、代理店契約書でお決めします。', 'apprex' ) ); ?>
			<div class="partner-terms">
				<table>
					<tbody>
						<?php foreach ( $apprex_terms as $t ) : ?>
							<tr><th><?php echo esc_html( $t[0] ); ?></th><td><?php echo esc_html( $t[1] ); ?></td></tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</section>

	<section class="section section--soft" id="faq">
		<div class="container">
			<?php apprex_section_head( 'FAQ', __( '代理店についてのよくある質問', 'apprex' ) ); ?>
			<?php get_template_part( 'template-parts/faq-list', null, array( 'faqs' => $apprex_faqs ) ); ?>
		</div>
	</section>

	<section class="section" id="apply">
		<div class="container content-prose">
			<?php apprex_section_head( 'Apply', __( '代理店のお申し込み', 'apprex' ), __( '説明会に参加された方は、こちらからお申し込みください。LINEを使っていない方・先に個別で相談したい方もこちらからどうぞ。', 'apprex' ) ); ?>
			<div class="callout seminar-callout">
				<p><?php esc_html_e( 'まだ説明会に参加していない方は、先に説明会（無料・オンライン）がおすすめです。', 'apprex' ); ?></p>
				<a class="btn btn--line" href="<?php echo esc_url( $apprex_sem_url ); ?>"><?php esc_html_e( 'LINEで説明会を予約する', 'apprex' ); ?></a>
			</div>
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
