<?php
/**
 * ヘッダー（Header.tsx を移植）。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$ais_site = ais_site();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-md focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-white">本文へスキップ</a>

<header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/90 backdrop-blur">
	<div class="container flex h-16 items-center justify-between gap-4">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-2" aria-label="<?php echo esc_attr( $ais_site['name'] . ' ホーム' ); ?>">
			<span class="grid h-9 w-9 place-items-center rounded-lg bg-brand-600 font-bold text-white">A</span>
			<span class="flex flex-col leading-none">
				<span class="text-base font-bold tracking-tight text-ink-900"><?php echo esc_html( $ais_site['name'] ); ?></span>
				<span class="text-[10px] font-medium tracking-wider text-brand-600"><?php echo esc_html( $ais_site['tagline'] ); ?></span>
			</span>
		</a>

		<nav class="hidden items-center gap-1 lg:flex" aria-label="メインナビゲーション">
			<?php foreach ( ais_main_nav() as $item ) : $has_children = ! empty( $item['children'] ); ?>
				<div class="group relative">
					<a href="<?php echo esc_url( ais_url( $item['href'] ) ); ?>" class="flex items-center gap-1 rounded-md px-3 py-2 text-sm font-medium text-ink-700 transition-colors hover:text-brand-700">
						<?php echo esc_html( $item['label'] ); ?>
						<?php if ( $has_children ) { echo ais_icon( 'chevron-down', 'h-3.5 w-3.5' ); } // phpcs:ignore ?>
					</a>
					<?php if ( $has_children ) : ?>
						<div class="invisible absolute left-0 top-full w-72 pt-2 opacity-0 transition-all group-hover:visible group-hover:opacity-100">
							<div class="rounded-xl border border-slate-200 bg-white p-2 shadow-card">
								<?php foreach ( $item['children'] as $c ) : ?>
									<a href="<?php echo esc_url( ais_url( $c['href'] ) ); ?>" class="block rounded-lg px-3 py-2.5 hover:bg-brand-50">
										<span class="block text-sm font-semibold text-ink-900"><?php echo esc_html( $c['label'] ); ?></span>
										<?php if ( ! empty( $c['description'] ) ) : ?>
											<span class="block text-xs text-ink-500"><?php echo esc_html( $c['description'] ); ?></span>
										<?php endif; ?>
									</a>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</nav>

		<div class="flex items-center gap-2">
			<?php echo ais_button( '/contact', '無料相談', 'primary', 'md', 'hidden sm:inline-flex' ); // phpcs:ignore ?>
			<button type="button" class="grid h-10 w-10 place-items-center rounded-md text-ink-700 lg:hidden" data-ais-menu-open aria-label="メニューを開く">
				<?php echo ais_icon( 'menu', 'h-6 w-6' ); // phpcs:ignore ?>
			</button>
		</div>
	</div>

	<!-- モバイルメニュー -->
	<div class="fixed inset-0 z-50 hidden lg:hidden" data-ais-menu>
		<div class="absolute inset-0 bg-ink-900/50" data-ais-menu-close aria-hidden="true"></div>
		<div class="absolute right-0 top-0 flex h-full w-80 max-w-[85%] flex-col bg-white shadow-xl">
			<div class="flex h-16 items-center justify-between border-b border-slate-200 px-5">
				<span class="font-bold text-ink-900">メニュー</span>
				<button type="button" class="grid h-10 w-10 place-items-center rounded-md text-ink-700" data-ais-menu-close aria-label="メニューを閉じる">
					<?php echo ais_icon( 'close', 'h-6 w-6' ); // phpcs:ignore ?>
				</button>
			</div>
			<nav class="flex-1 overflow-y-auto px-3 py-4" aria-label="モバイルナビゲーション">
				<?php foreach ( ais_main_nav() as $item ) : ?>
					<div class="py-1">
						<a href="<?php echo esc_url( ais_url( $item['href'] ) ); ?>" class="block rounded-lg px-3 py-2.5 text-base font-semibold text-ink-900 hover:bg-brand-50"><?php echo esc_html( $item['label'] ); ?></a>
						<?php if ( ! empty( $item['children'] ) ) : ?>
							<div class="ml-3 border-l border-slate-200 pl-2">
								<?php foreach ( $item['children'] as $c ) : ?>
									<a href="<?php echo esc_url( ais_url( $c['href'] ) ); ?>" class="block rounded-lg px-3 py-2 text-sm text-ink-600 hover:bg-brand-50"><?php echo esc_html( $c['label'] ); ?></a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</nav>
			<div class="border-t border-slate-200 p-4">
				<?php echo ais_button( '/contact', '無料で相談する', 'primary', 'lg', 'w-full' ); // phpcs:ignore ?>
			</div>
		</div>
	</div>
</header>

<main id="main">
