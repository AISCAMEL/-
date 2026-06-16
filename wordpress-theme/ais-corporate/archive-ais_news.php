<?php
/** お知らせ・コラム 一覧（news/page.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$news = ais_news();
usort( $news, function ( $a, $b ) { return strcmp( $b['date'], $a['date'] ); } );
$has_placeholder = (bool) array_filter( $news, function ( $n ) { return ! empty( $n['is_placeholder'] ); } );
?>

<?php echo ais_page_hero( 'News & Column', 'お知らせ・コラム', '会社からのお知らせと、事業のヒントになるコラムをお届けします。' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php if ( $has_placeholder ) : ?>
			<p class="mb-8 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">※ 現在の記事は構成確認用のサンプル（ダミー）です。実際の記事に差し替えてください。</p>
		<?php endif; ?>
		<ul class="divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200">
			<?php foreach ( $news as $n ) : ?>
				<li>
					<a href="<?php echo esc_url( ais_url( '/news/' . $n['slug'] ) ); ?>" class="group flex flex-col gap-2 px-6 py-6 transition-colors hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-6">
						<div class="flex items-center gap-3 sm:w-56 sm:flex-none">
							<time class="text-sm text-ink-500" datetime="<?php echo esc_attr( $n['date'] ); ?>"><?php echo esc_html( $n['date'] ); ?></time>
							<span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700"><?php echo esc_html( $n['category'] ); ?></span>
						</div>
						<div class="flex flex-1 items-center justify-between gap-4">
							<span class="font-semibold text-ink-900 group-hover:text-brand-700"><?php echo esc_html( $n['title'] ); ?></span>
							<?php echo ais_icon( 'arrow-right', 'h-4 w-4 flex-none text-brand-600 transition-transform group-hover:translate-x-1' ); // phpcs:ignore ?>
						</div>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
