<?php
/** 実績 詳細（works/[slug]/page.tsx） */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$obj  = get_queried_object();
$slug = $obj ? $obj->post_name : '';
$work = ais_get_work( $slug );

if ( ! $work ) {
	echo '<section class="py-24"><div class="container"><p class="text-center text-ink-600">ページが見つかりませんでした。</p></div></section>';
	get_footer();
	return;
}

$body_sections = array(
	array( '背景・ご相談内容', '（プレースホルダー）お客様の事業背景と、ご相談に至った経緯を記載します。' ),
	array( '課題', '（プレースホルダー）解決すべきだった具体的な課題を記載します。' ),
	array( 'アイズの支援内容', '（プレースホルダー）実施した支援・施策の内容を記載します。' ),
	array( '成果', '（プレースホルダー）定量・定性の成果を記載します。数値はダミーです。' ),
);
?>

<?php echo ais_page_hero( $work['category_label'], $work['title'], $work['summary'] ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl">
			<?php if ( ! empty( $work['is_placeholder'] ) ) : ?>
				<p class="mb-8 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">※ この事例は構成確認用のサンプル（ダミー）です。実際の実績に差し替えてください。</p>
			<?php endif; ?>

			<div class="grid gap-4 sm:grid-cols-3">
				<div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
					<p class="text-xs font-semibold text-ink-500">領域</p>
					<p class="mt-1 font-bold text-ink-900"><?php echo esc_html( $work['category_label'] ); ?></p>
				</div>
				<div class="rounded-xl border border-slate-200 bg-slate-50 p-5 sm:col-span-2">
					<p class="text-xs font-semibold text-ink-500">主な成果</p>
					<p class="mt-1 inline-flex items-center gap-2 font-bold text-accent-600">
						<?php echo ais_icon( 'spark', 'h-4 w-4' ); // phpcs:ignore ?>
						<?php echo esc_html( $work['result'] ); ?>
					</p>
				</div>
			</div>

			<div class="mt-10 space-y-8">
				<?php foreach ( $body_sections as $s ) : ?>
					<section>
						<h2 class="border-l-4 border-brand-600 pl-3 text-xl font-bold text-ink-900"><?php echo esc_html( $s[0] ); ?></h2>
						<p class="mt-3 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $s[1] ); ?></p>
					</section>
				<?php endforeach; ?>
			</div>

			<div class="mt-12">
				<a href="<?php echo esc_url( ais_url( '/works' ) ); ?>" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
					<?php echo ais_icon( 'arrow-right', 'h-4 w-4 rotate-180' ); // phpcs:ignore ?>
					実績一覧に戻る
				</a>
			</div>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
