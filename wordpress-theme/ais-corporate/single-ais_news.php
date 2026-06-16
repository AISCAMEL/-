<?php
/** お知らせ・コラム 詳細（news/[slug]/page.tsx）。本文は管理画面で編集した内容を優先し、無ければデータ層を使用。 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$obj  = get_queried_object();
$slug = $obj ? $obj->post_name : '';
$item = ais_get_news( $slug );

if ( ! $item ) {
	echo '<section class="py-24"><div class="container"><p class="text-center text-ink-600">ページが見つかりませんでした。</p></div></section>';
	get_footer();
	return;
}

// 管理画面で本文が入力されていればそれを優先
$edited = '';
if ( have_posts() ) {
	the_post();
	$edited = trim( get_the_content() );
}
?>

<?php echo ais_page_hero( $item['category'], $item['title'] ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<article class="mx-auto max-w-3xl">
			<div class="flex items-center gap-3 border-b border-slate-200 pb-6">
				<time class="text-sm text-ink-500" datetime="<?php echo esc_attr( $item['date'] ); ?>"><?php echo esc_html( $item['date'] ); ?></time>
				<span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700"><?php echo esc_html( $item['category'] ); ?></span>
			</div>

			<?php if ( ! empty( $item['is_placeholder'] ) ) : ?>
				<p class="mt-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">※ この記事は構成確認用のサンプル（ダミー）です。</p>
			<?php endif; ?>

			<?php if ( $edited ) : ?>
				<div class="prose mt-8 max-w-none text-base leading-relaxed text-ink-700">
					<?php the_content(); ?>
				</div>
			<?php else : ?>
				<div class="mt-8 space-y-5">
					<?php foreach ( $item['body'] as $para ) : ?>
						<p class="text-base leading-relaxed text-ink-700"><?php echo esc_html( $para ); ?></p>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<div class="mt-12">
				<a href="<?php echo esc_url( ais_url( '/news' ) ); ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
					<?php echo ais_icon( 'arrow-right', 'h-4 w-4 rotate-180' ); // phpcs:ignore ?>
					お知らせ一覧に戻る
				</a>
			</div>
		</article>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
