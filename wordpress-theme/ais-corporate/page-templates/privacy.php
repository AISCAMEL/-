<?php
/**
 * Template Name: プライバシーポリシー
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$site  = ais_site();
$email = $site['email'];
$sections = array(
	array( '1. 事業者情報', $site['name'] . "（以下「当社」といいます）は、当社が運営するウェブサイトおよび提供するサービスにおける個人情報の取り扱いについて、以下のとおりプライバシーポリシー（以下「本ポリシー」といいます）を定めます。\n所在地：" . $site['address'] . "\n連絡先：" . $email ),
	array( '2. 個人情報の取得について', '当社は、お問い合わせ・お見積り・サービス提供にあたり、適法かつ公正な手段により、お名前・会社名・メールアドレスなど、必要な範囲で個人情報を取得します。' ),
	array( '3. 個人情報の利用目的', "取得した個人情報は、次の目的の範囲内で利用します。\n・お問い合わせ・ご相談への対応\n・サービスのご提供、ご案内、お見積りの作成\n・契約の履行および履行に関するご連絡\n・サービスの品質向上、新サービスのご案内\n・上記に付随する業務の遂行" ),
	array( '4. 個人情報の第三者提供', "当社は、次のいずれかに該当する場合を除き、あらかじめご本人の同意を得ることなく、個人情報を第三者に提供しません。\n・法令に基づく場合\n・人の生命、身体または財産の保護のために必要があり、本人の同意を得ることが困難な場合\n・サービス提供に必要な範囲で業務委託先に取り扱いを委託する場合（この場合、当社は委託先を適切に監督します）" ),
	array( '5. 個人情報の管理・安全管理措置', '当社は、個人情報の漏えい・滅失・毀損の防止その他の安全管理のため、必要かつ適切な措置を講じます。本サイトの送信フォーム等では、必要に応じて通信の暗号化（SSL/TLS）を行います。' ),
	array( '6. アクセス解析・Cookieについて', '当社のウェブサイトでは、利用状況の把握やサービス改善のために、Cookieを利用したアクセス解析ツールを使用する場合があります。これにより個人を特定できる情報を取得することはありません。ブラウザの設定によりCookieを無効化することも可能です。' ),
	array( '7. 開示・訂正・利用停止等の請求', 'ご本人からの個人情報の開示・訂正・追加・削除・利用停止等のご請求に対し、ご本人であることを確認のうえ、法令に従い適切に対応します。ご請求は ' . $email . ' までご連絡ください。' ),
	array( '8. 本ポリシーの変更', '当社は、法令の改正やサービス内容の変更等に応じて、本ポリシーを予告なく変更することがあります。変更後の本ポリシーは、本ページに掲載した時点から効力を生じるものとします。' ),
	array( '9. お問い合わせ窓口', '本ポリシーおよび個人情報の取り扱いに関するお問い合わせは、' . $email . ' までご連絡ください。' ),
);
?>

<?php echo ais_page_hero( 'Privacy Policy', 'プライバシーポリシー' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl">
			<div class="space-y-8">
				<?php foreach ( $sections as $s ) : ?>
					<section>
						<h2 class="text-lg font-bold text-ink-900"><?php echo esc_html( $s[0] ); ?></h2>
						<p class="mt-2 whitespace-pre-line text-sm leading-relaxed text-ink-600"><?php echo esc_html( $s[1] ); ?></p>
					</section>
				<?php endforeach; ?>
			</div>
			<div class="mt-10 border-t border-slate-200 pt-6 text-sm text-ink-500">
				<p>制定日：2026年6月15日</p>
				<p class="mt-1"><?php echo esc_html( $site['name'] ); ?></p>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>
