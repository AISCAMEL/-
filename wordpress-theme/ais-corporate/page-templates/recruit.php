<?php
/**
 * Template Name: 採用情報
 * ※ 待遇・勤務条件の詳細は応相談／placeholder です。確定情報に差し替えてください。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$positions = ais_recruit_positions();
$site = ais_site();
$persons = array(
	array( 'title' => '変化を前向きに楽しめる人', 'body' => '新しい仕組みや技術を「リスク」ではなく「成長の機会」と捉え、まず試してみられる方。' ),
	array( 'title' => 'お客様に最後まで寄り添える人', 'body' => '売って終わりにせず、構想から実行・成果まで伴走することにやりがいを感じる方。' ),
	array( 'title' => '領域を越えて学べる人', 'body' => '自動車からIT・WEBまで、幅広い事業に好奇心を持って取り組める方。' ),
);
$flow = array(
	array( 'title' => 'お問い合わせ・応募', 'body' => 'フォームから、希望職種を添えてご連絡ください。' ),
	array( 'title' => '面談・ヒアリング', 'body' => 'お互いの希望や条件をすり合わせます。' ),
	array( 'title' => '条件のご提示', 'body' => '雇用形態・待遇など、具体的な条件をご提示します。' ),
	array( 'title' => '参画・スタート', 'body' => 'ご納得のうえで、いっしょに事業を進めます。' ),
);
?>

<?php echo ais_page_hero( 'Recruit', '採用情報', 'クルマからデジタルまで、多角的に挑戦できるフィールドがあります。正社員から業務委託まで、革新・品質・信頼を軸に、一緒に成長してくれる仲間を募集します。' ); // phpcs:ignore ?>

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
		<?php echo ais_section_heading( '募集職種', 'Positions', esc_html( $site['name'] ) . 'が募集する職種です。当社との直接契約（正社員／業務委託）となります。', 'center' ); // phpcs:ignore ?>
		<div class="mx-auto mt-12 grid max-w-4xl gap-6 lg:grid-cols-2">
			<?php foreach ( $positions as $i => $pos ) : ?>
				<div class="reveal flex flex-col rounded-2xl border border-slate-200 bg-white p-7 shadow-card" style="transition-delay:<?php echo (int) ( $i * 70 ); ?>ms">
					<div class="flex items-start gap-4">
						<span class="grid h-12 w-12 flex-none place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100"><?php echo ais_icon( $pos['icon'], 'h-6 w-6' ); // phpcs:ignore ?></span>
						<div class="min-w-0">
							<div class="flex flex-wrap items-center gap-2">
								<h3 class="text-lg font-bold text-ink-900"><?php echo esc_html( $pos['title'] ); ?></h3>
								<span class="rounded-full bg-brand-600 px-2.5 py-0.5 text-[11px] font-bold text-white"><?php echo esc_html( $pos['type'] ); ?></span>
							</div>
							<?php if ( ! empty( $pos['brand'] ) ) : ?>
								<p class="mt-0.5 text-xs font-semibold tracking-wide text-brand-600">取扱ブランド：<?php echo esc_html( $pos['brand'] ); ?></p>
							<?php endif; ?>
							<p class="mt-0.5 text-xs text-ink-500">募集元：<?php echo esc_html( $site['name'] ); ?></p>
						</div>
					</div>

					<p class="mt-4 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $pos['summary'] ); ?></p>

					<ul class="mt-4 grid gap-2 sm:grid-cols-2">
						<?php foreach ( $pos['points'] as $pt ) : ?>
							<li class="flex items-start gap-2 text-sm text-ink-700">
								<?php echo ais_icon( 'check', 'mt-0.5 h-4 w-4 flex-none text-accent-600' ); // phpcs:ignore ?>
								<?php echo esc_html( $pt ); ?>
							</li>
						<?php endforeach; ?>
					</ul>

					<div class="mt-4 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
						<p class="text-xs font-bold text-ink-900">こんな方を歓迎</p>
						<p class="mt-1 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $pos['welcome'] ); ?></p>
					</div>

					<div class="mt-5">
						<?php echo ais_button( '/contact', 'この職種に応募・相談する', 'secondary', 'md' ); // phpcs:ignore ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<p class="mx-auto mt-8 max-w-3xl rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">※ 待遇・報酬・勤務条件などの詳細は応相談です（一部準備中）。確定情報が決まり次第、本ページを更新してください。気になる職種があれば、まずはお気軽にお問い合わせください。</p>
	</div>
</section>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '求める人物像', 'We Want', '私たちの価値観「革新・品質・信頼」に共感いただける方を求めています。', 'center' ); // phpcs:ignore ?>
		<div class="mt-12 grid gap-5 md:grid-cols-3">
			<?php foreach ( $persons as $i => $p ) : ?>
				<div class="reveal rounded-2xl border border-slate-200 bg-slate-50 p-7" style="transition-delay:<?php echo (int) ( $i * 80 ); ?>ms">
					<span class="text-3xl font-bold text-brand-100"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<h3 class="mt-1 text-base font-bold text-ink-900"><?php echo esc_html( $p['title'] ); ?></h3>
					<p class="mt-2 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $p['body'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '応募の流れ', 'Flow', 'お問い合わせから参画まで、ていねいにすり合わせます。', 'center' ); // phpcs:ignore ?>
		<ol class="mt-12 grid gap-4 md:grid-cols-4">
			<?php foreach ( $flow as $i => $s ) : ?>
				<li class="rounded-2xl border border-slate-200 bg-white p-5 shadow-card">
					<span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white"><?php echo (int) ( $i + 1 ); ?></span>
					<h3 class="mt-3 text-sm font-bold text-ink-900"><?php echo esc_html( $s['title'] ); ?></h3>
					<p class="mt-1.5 text-xs leading-relaxed text-ink-600"><?php echo esc_html( $s['body'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
		<div class="mt-10 text-center">
			<?php echo ais_button( '/contact', '応募・相談する' . ais_icon( 'arrow-right', 'h-4 w-4' ), 'primary', 'lg' ); // phpcs:ignore ?>
			<p class="mt-3 text-xs text-ink-400">お問い合わせフォームの本文に、希望職種（例：車買取 業務委託／クリエイター 等）をご記入ください。勤務地：福島県いわき市四倉町。</p>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
