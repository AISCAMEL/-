<?php
/**
 * Template Name: プライバシーポリシー (Privacy)
 *
 * 本文が空ならテーマの標準のプライバシーポリシーを表示する。
 * 管理画面で本文を書いた場合は、その本文を表示する。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_pp = array(
	array(
		'1. 基本方針',
		array( '合同会社アイズ（以下「当社」）は、APPREX（アプリックス）をはじめとする当社のサービスにおいて、お客様の個人情報を適切に取り扱い、保護することを大切な責務と考え、個人情報の保護に関する法律その他の法令を守り、以下のとおり取り扱います。' ),
	),
	array(
		'2. 取得する情報',
		array(
			'お問い合わせ・資料請求・見積もり・各種お申し込みでご入力いただく、お名前・会社名・メールアドレス・電話番号・ご相談内容など',
			'AIスタジオなどの会員登録でご登録いただく情報と、AIスタジオで入力・作成された内容（業種・店名・デザイン・仕様書など）',
			'LINEでログイン・ご予約いただいた場合の、LINEのユーザー識別子・表示名・プロフィール画像',
			'ご契約・ご請求・お支払いに関する情報',
			'当サイトの閲覧に伴うアクセス情報（Cookie、IPアドレス、ブラウザの種類、閲覧したページ、紹介元の代理店コードなど）',
		),
	),
	array(
		'3. 利用目的',
		array(
			'お問い合わせへの回答と、ご連絡のため',
			'アプリ・ホームページの制作、運用、サポートなど、当社サービスを提供するため',
			'お見積り、ご契約、ご請求の手続きのため',
			'オンライン相談・説明会のご予約の管理と、リマインドなどのご案内のため',
			'当社のサービス・キャンペーンのご案内（メール・LINE）のため。ご案内はいつでも停止できます',
			'AIを使ったデザイン・仕様書などの作成のため',
			'代理店からのご紹介の確認と、代理店への報酬の計算のため',
			'サービスの改善、利用状況の分析、広告の効果測定のため',
			'不正な利用を防ぐため',
		),
	),
	array(
		'4. 第三者への提供',
		array( '次の場合を除き、ご本人の同意なく個人情報を第三者に提供しません。法令に基づく場合／人の生命・身体・財産の保護のために必要で、ご本人の同意を得ることが難しい場合／国の機関などの法令の定める事務に協力する必要がある場合。なお、代理店からのご紹介でお問い合わせいただいた場合、ご紹介の状況（お申し込み・ご契約の有無など）を、その代理店にお知らせすることがあります。' ),
	),
	array(
		'5. 業務の委託と外部サービス',
		array(
			'当社は、利用目的の達成に必要な範囲で、個人情報の取り扱いを外部の事業者に委託することがあります。その場合は、適切な事業者を選び、必要な監督を行います。',
			'主に次のような外部サービスを利用しています：LINE（LINE公式アカウント・LINEログイン）、Google（カレンダー・Meet・広告など）、Meta・TikTokなどの広告計測、AIサービス（入力内容からデザインや文章を作成する処理）、決済・請求書のサービス、メール配信のサービス。',
			'これらの事業者には、外国にある事業者が含まれる場合があります。',
		),
	),
	array(
		'6. Cookie などについて',
		array( '当サイトでは、ログインの維持、紹介元の代理店コードの記録（90日間）、アクセスの分析、広告の効果測定のためにCookieなどを使っています。Cookieはブラウザの設定で無効にできますが、一部の機能が使えなくなることがあります。' ),
	),
	array(
		'7. 安全管理',
		array( '個人情報の漏えい・滅失・き損を防ぐため、アクセスの制限、通信の暗号化など、必要かつ適切な安全管理の措置を行います。' ),
	),
	array(
		'8. 開示・訂正・利用停止などのご請求',
		array( 'ご本人から、個人情報の開示・訂正・追加・削除・利用停止などのご請求があった場合は、ご本人であることを確認したうえで、法令に従って対応します。下記の窓口までご連絡ください。' ),
	),
	array(
		'9. メール・LINEのご案内の停止',
		array( 'メールでのご案内は、メールの末尾にある配信停止のリンクから停止できます。LINEでのご案内は、公式アカウントをブロックすると届かなくなります。' ),
	),
	array(
		'10. このポリシーの変更',
		array( '法令の改正やサービスの変更に合わせて、このポリシーを変更することがあります。変更後の内容は、このページに掲載した時点から有効になります。' ),
	),
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
		</div>
	</section>

	<section class="section">
		<div class="container content-prose">
			<?php if ( trim( get_the_content() ) ) : ?>
				<?php the_content(); ?>
			<?php else : ?>
				<?php foreach ( $apprex_pp as $apprex_sec ) : ?>
					<h2><?php echo esc_html( $apprex_sec[0] ); ?></h2>
					<?php if ( count( $apprex_sec[1] ) > 1 && '3. 利用目的' !== $apprex_sec[0] && '5. 業務の委託と外部サービス' !== $apprex_sec[0] ) : ?>
						<ul class="agency-list">
							<?php foreach ( $apprex_sec[1] as $apprex_li ) : ?>
								<li><?php echo esc_html( $apprex_li ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php elseif ( '3. 利用目的' === $apprex_sec[0] ) : ?>
						<ol>
							<?php foreach ( $apprex_sec[1] as $apprex_li ) : ?>
								<li><?php echo esc_html( $apprex_li ); ?></li>
							<?php endforeach; ?>
						</ol>
					<?php else : ?>
						<?php foreach ( $apprex_sec[1] as $apprex_p ) : ?>
							<p><?php echo esc_html( $apprex_p ); ?></p>
						<?php endforeach; ?>
					<?php endif; ?>
				<?php endforeach; ?>

				<h2><?php esc_html_e( '11. お問い合わせ窓口', 'apprex' ); ?></h2>
				<p>
					<?php esc_html_e( '合同会社アイズ　個人情報のお問い合わせ窓口', 'apprex' ); ?><br>
					<?php esc_html_e( '所在地：福島県いわき市四倉町細谷字大町1番', 'apprex' ); ?><br>
					<?php esc_html_e( '代表者：吉田 一平', 'apprex' ); ?><br>
					<?php echo esc_html( 'メール：' . apprex_contact_email() ); ?><br>
					<?php esc_html_e( '受付時間：平日 10:00〜18:00', 'apprex' ); ?>
				</p>
				<p><?php esc_html_e( '2026年10月25日 制定', 'apprex' ); ?></p>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
