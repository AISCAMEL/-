<?php
/** 汎用フォールバック（ブログ一覧・検索結果など） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
?>
<?php echo ais_page_hero( 'Blog', is_search() ? '検索結果' : 'お知らせ' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl">
			<?php if ( have_posts() ) : ?>
				<ul class="divide-y divide-slate-200 overflow-hidden rounded-2xl border border-slate-200">
					<?php while ( have_posts() ) : the_post(); ?>
						<li>
							<a href="<?php the_permalink(); ?>" class="group flex flex-col gap-2 px-6 py-6 transition-colors hover:bg-slate-50 sm:flex-row sm:items-center sm:gap-6">
								<time class="text-sm text-ink-500 sm:w-40 sm:flex-none"><?php echo esc_html( get_the_date() ); ?></time>
								<span class="flex-1 font-semibold text-ink-900 group-hover:text-brand-700"><?php the_title(); ?></span>
							</a>
						</li>
					<?php endwhile; ?>
				</ul>
				<div class="mt-8"><?php the_posts_pagination(); ?></div>
			<?php else : ?>
				<p class="text-ink-600">記事が見つかりませんでした。</p>
			<?php endif; ?>
		</div>
	</div>
</section>

<?php get_footer(); ?>
