<?php
/** 事業 詳細（services/[slug]/page.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();

$obj     = get_queried_object();
$slug    = $obj ? $obj->post_name : '';
$service = ais_get_service( $slug );

if ( ! $service ) {
	echo '<section class="py-24"><div class="container"><p class="text-center text-ink-600">ページが見つかりませんでした。</p></div></section>';
	get_footer();
	return;
}

$workflow = ais_home_workflow();
$same_group = array_values( array_filter( ais_services(), function ( $s ) use ( $service ) {
	return $s['group'] === $service['group'] && $s['slug'] !== $service['slug'];
} ) );
if ( empty( $same_group ) ) {
	$same_group = array_slice( array_values( array_filter( ais_services(), function ( $s ) use ( $service ) {
		return $s['slug'] !== $service['slug'];
	} ) ), 0, 3 );
}
?>

<?php echo ais_page_hero( ! empty( $service['brand'] ) ? $service['brand'] : $service['tagline'], $service['name'], $service['summary'] ); // phpcs:ignore ?>

<?php if ( ! empty( $service['external_url'] ) || ! empty( $service['coming_soon'] ) ) : ?>
	<div class="border-b border-slate-200 bg-slate-50">
		<div class="mx-auto flex max-w-6xl flex-wrap items-center gap-3 px-4 py-4 sm:px-6">
			<?php if ( ! empty( $service['coming_soon'] ) ) : ?>
				<span class="inline-flex items-center gap-1.5 rounded-full bg-accent-50 px-3 py-1 text-xs font-bold text-accent-700 ring-1 ring-inset ring-accent-200">リリース準備中</span>
			<?php endif; ?>
			<?php if ( ! empty( $service['external_url'] ) ) : ?>
				<a href="<?php echo esc_url( $service['external_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
					公式サイトを見る
					<?php echo ais_icon( 'arrow-right', 'h-4 w-4' ); // phpcs:ignore ?>
				</a>
			<?php endif; ?>
		</div>
	</div>
<?php endif; ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( 'こんな企業・事業者の方へ', 'For You', 'ひとつでも当てはまれば、お力になれる可能性があります。' ); // phpcs:ignore ?>
		<div class="mt-10 grid gap-4 sm:grid-cols-3">
			<?php foreach ( $service['audience'] as $a ) : ?>
				<div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-5">
					<?php echo ais_icon( 'check', 'mt-0.5 h-5 w-5 flex-none text-accent-600' ); // phpcs:ignore ?>
					<p class="text-sm font-medium leading-relaxed text-ink-800"><?php echo esc_html( $a ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '主な支援メニュー', 'Menu', '課題に合わせて、必要なメニューを組み合わせてご提供します。' ); // phpcs:ignore ?>
		<div class="mt-10 grid gap-5 md:grid-cols-2">
			<?php foreach ( $service['offerings'] as $i => $o ) : ?>
				<div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-card">
					<span class="text-sm font-bold text-brand-300"><?php echo esc_html( str_pad( (string) ( $i + 1 ), 2, '0', STR_PAD_LEFT ) ); ?></span>
					<h3 class="mt-1 text-lg font-bold text-ink-900"><?php echo esc_html( $o['title'] ); ?></h3>
					<p class="mt-2 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $o['description'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( 'ご相談から支援開始までの流れ', 'Flow', '', 'center' ); // phpcs:ignore ?>
		<ol class="mt-10 grid gap-4 md:grid-cols-5">
			<?php foreach ( $workflow['steps'] as $i => $step ) : ?>
				<li class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
					<span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white"><?php echo (int) ( $i + 1 ); ?></span>
					<h3 class="mt-3 text-sm font-bold text-ink-900"><?php echo esc_html( $step['title'] ); ?></h3>
					<p class="mt-1.5 text-xs leading-relaxed text-ink-600"><?php echo esc_html( $step['body'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
		<div class="mt-10 text-center">
			<?php echo ais_button( '/contact', 'この内容で相談する' . ais_icon( 'arrow-right', 'h-4 w-4' ), 'primary', 'lg' ); // phpcs:ignore ?>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( 'その他のサービス', 'Other Services' ); // phpcs:ignore ?>
		<div class="mt-8 grid gap-5 sm:grid-cols-2">
			<?php foreach ( $same_group as $o ) : ?>
				<a href="<?php echo esc_url( ais_url( '/services/' . $o['slug'] ) ); ?>" class="group flex items-center gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-card transition-all hover:-translate-y-0.5 hover:shadow-card-hover">
					<span class="grid h-12 w-12 flex-none place-items-center rounded-xl bg-brand-50 text-brand-600">
						<?php echo ais_icon( $o['icon'], 'h-6 w-6' ); // phpcs:ignore ?>
					</span>
					<div class="flex-1">
						<?php if ( ! empty( $o['brand'] ) ) : ?>
							<p class="text-xs font-semibold text-brand-600"><?php echo esc_html( $o['brand'] ); ?></p>
						<?php endif; ?>
						<h3 class="font-bold text-ink-900"><?php echo esc_html( $o['name'] ); ?></h3>
						<p class="text-sm text-ink-500"><?php echo esc_html( $o['tagline'] ); ?></p>
					</div>
					<?php echo ais_icon( 'arrow-right', 'h-5 w-5 text-brand-600 transition-transform group-hover:translate-x-1' ); // phpcs:ignore ?>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
