<?php
/**
 * Template Name: 採用情報
 * ※ 募集要項・待遇は placeholder です。確定情報に差し替えてください。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$phil = ais_philosophy();
$persons = array(
	array( 'title' => '変化を前向きに楽しめる人', 'body' => '新しい仕組みや技術を「リスク」ではなく「成長の機会」と捉え、まず試してみられる方。' ),
	array( 'title' => 'お客様に最後まで寄り添える人', 'body' => '売って終わりにせず、構想から実行・成果まで伴走することにやりがいを感じる方。' ),
	array( 'title' => '領域を越えて学べる人', 'body' => '自動車からIT・WEBまで、幅広い事業に好奇心を持って取り組める方。' ),
);
?>

<?php echo ais_page_hero( 'Recruit', '採用情報', 'クルマからデジタルまで、多角的に挑戦できるフィールドがあります。革新・品質・信頼を軸に、一緒に成長してくれる仲間を募集します。' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl">
			<?php echo ais_section_heading( 'アイズで働くということ', 'Message' ); // phpcs:ignore ?>
			<div class="mt-6 space-y-5 text-base leading-relaxed text-ink-700">
				<p>合同会社アイズは、自動車事業（販売・買取・オンライン販売・セキュリティ・レッカー）を主軸に、IT・WEB事業、FC事業まで多角的に展開しています。だからこそ、ひとつの職種にとどまらず、幅広い経験を積めるのが私たちの環境です。</p>
				<p>大切にしているのは「Always Innovation Solutions＝常に、新しい解決策を」という姿勢。変化を楽しみ、お客様と地域の成長に伴走できる方を歓迎します。</p>
			</div>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '求める人物像', 'We Want', '私たちの価値観「革新・品質・信頼」に共感いただける方を求めています。', 'center' ); // phpcs:ignore ?>
		<div class="mt-12 grid gap-5 md:grid-cols-3">
			<?php foreach ( $persons as $i => $p ) : ?>
				<div class="reveal rounded-2xl border border-slate-200 bg-white p-7 shadow-card" style="transition-delay:<?php echo (int) ( $i * 80 ); ?>ms">
					<span class="text-3xl font-bold text-brand-100"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<h3 class="mt-1 text-base font-bold text-ink-900"><?php echo esc_html( $p['title'] ); ?></h3>
					<p class="mt-2 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $p['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '募集要項', 'Requirements' ); // phpcs:ignore ?>
		<p class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">※ 募集職種・待遇・勤務地などの詳細は準備中です（placeholder）。確定情報に差し替えてください。</p>
		<dl class="mt-8 overflow-hidden rounded-2xl border border-slate-200">
			<?php
			$rows = array(
				array( '募集職種', '営業・店舗運営／IT・WEB（アプリ・サイト制作）／カスタマー対応 など（※準備中）' ),
				array( '雇用形態', '正社員・アルバイト 等（※準備中）' ),
				array( '勤務地', '福島県いわき市四倉町（※準備中）' ),
				array( '応募方法', 'お問い合わせフォームより、希望職種を添えてご連絡ください。' ),
			);
			foreach ( $rows as $i => $r ) :
				?>
				<div class="grid grid-cols-1 gap-1 px-6 py-5 sm:grid-cols-4 sm:gap-4 <?php echo 0 !== $i ? 'border-t border-slate-100' : ''; ?>">
					<dt class="text-sm font-bold text-ink-900"><?php echo esc_html( $r[0] ); ?></dt>
					<dd class="text-sm leading-relaxed text-ink-600 sm:col-span-3"><?php echo esc_html( $r[1] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
		<div class="mt-10 text-center">
			<?php echo ais_button( '/contact', '応募・相談する' . ais_icon( 'arrow-right', 'h-4 w-4' ), 'primary', 'lg' ); // phpcs:ignore ?>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
