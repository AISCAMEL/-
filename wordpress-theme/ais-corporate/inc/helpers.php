<?php
/**
 * 表示ヘルパー（Next.js 版の UI コンポーネントを PHP 関数に移植）。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** 内部パス（"/about" 等）を WordPress の URL に変換 */
function ais_url( $path = '/' ) {
	if ( preg_match( '~^(https?:|mailto:|tel:|#)~', $path ) ) {
		return $path;
	}
	return home_url( $path );
}

/** アイコン SVG を出力（Icon.tsx を移植） */
function ais_icon( $name, $class = 'h-6 w-6' ) {
	$paths = array(
		'car'         => '<path d="M5 11l1.5-4.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11"/><path d="M3 11h18v5a1 1 0 0 1-1 1h-1a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H4a1 1 0 0 1-1-1v-5z"/><path d="M7 14h.01M17 14h.01"/>',
		'app'         => '<rect x="7" y="3" width="10" height="18" rx="2.5"/><path d="M11 18h2"/>',
		'gps'         => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
		'store'       => '<path d="M3.5 9l1.4-4.2A1 1 0 0 1 5.85 4h12.3a1 1 0 0 1 .95.8L20.5 9"/><path d="M5 9v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V9"/><path d="M9.5 19v-4.5a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1V19"/>',
		'tag'         => '<path d="M4 13l7-7a2 2 0 0 1 1.5-.6l4.5.3.3 4.5A2 2 0 0 1 18 11.8l-7 7a2 2 0 0 1-2.8 0L4 14.6a2 2 0 0 1 0-1.6z"/><circle cx="14.5" cy="9.5" r="1.1"/>',
		'key'         => '<circle cx="8" cy="8" r="3.5"/><path d="M10.5 10.5L20 20"/><path d="M16 16l2.2-2.2M18.5 18.5l2-2"/>',
		'shield'      => '<path d="M12 3l7 3v5c0 4.5-3 7.6-7 9-4-1.4-7-4.5-7-9V6l7-3z"/><path d="M9 11.5l2 2 4-4"/>',
		'truck'       => '<path d="M3 7h10v8H3z"/><path d="M13 10h4l3 3v2h-7z"/><circle cx="7" cy="17" r="1.6"/><circle cx="17.5" cy="17" r="1.6"/>',
		'rocket'      => '<path d="M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2"/><path d="M9 13c-1.6.8-3 2.4-3 2.4S7.6 17 8.4 15.4"/><path d="M14.5 4.5C18 3 21 6 19.5 9.5L14 15l-5-5 5.5-5.5z"/><circle cx="15" cy="9" r="1.3"/>',
		'code'        => '<path d="M8 8l-4 4 4 4"/><path d="M16 8l4 4-4 4"/><path d="M13 6l-2 12"/>',
		'arrow-right' => '<path d="M5 12h14"/><path d="M13 6l6 6-6 6"/>',
		'check'       => '<path d="M5 12l4 4 10-10"/>',
		'chevron-down'=> '<path d="M6 9l6 6 6-6"/>',
		'menu'        => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',
		'close'       => '<path d="M6 6l12 12"/><path d="M18 6L6 18"/>',
		'mail'        => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
		'phone'       => '<path d="M5 4h3l2 5-2 1a11 11 0 0 0 5 5l1-2 5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
		'spark'       => '<path d="M12 3l2 5 5 2-5 2-2 5-2-5-5-2 5-2 2-5z"/>',
		'volume'      => '<path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16 8.5a4 4 0 0 1 0 7"/><path d="M18.5 6a7 7 0 0 1 0 12"/>',
		'mute'        => '<path d="M4 9v6h4l5 4V5L8 9H4z"/><path d="M16 9l5 6"/><path d="M21 9l-5 6"/>',
	);
	$inner = isset( $paths[ $name ] ) ? $paths[ $name ] : '';
	return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="' . esc_attr( $class ) . '">' . $inner . '</svg>';
}

/** AIチャットの案内係アバター。写真（assets/media/operator-avatar.jpg）があれば優先、無ければSVG。 */
function ais_chat_avatar( $class = 'h-9 w-9' ) {
	if ( function_exists( 'get_theme_file_path' ) && file_exists( get_theme_file_path( '/assets/media/operator-avatar.jpg' ) ) ) {
		return '<img src="' . esc_url( get_theme_file_uri( '/assets/media/operator-avatar.jpg' ) ) . '" alt="AIコンシェルジュ" loading="lazy" decoding="async" class="' . esc_attr( $class ) . ' object-cover">';
	}
	return ais_chat_avatar_svg( $class );
}

/** 案内係（女性）アバターの SVG 版（写真が無い場合のフォールバック） */
function ais_chat_avatar_svg( $class = 'h-9 w-9' ) {
	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="AIコンシェルジュ">'
		. '<circle cx="24" cy="24" r="24" fill="#e8f0fe"/>'
		. '<path d="M7 48 C7 38 14.5 33.5 24 33.5 C33.5 33.5 41 38 41 48 Z" fill="#2563eb"/>'
		. '<path d="M19.5 30 h9 v5 h-9 z" fill="#f1c39f"/>'
		. '<path d="M11.5 27 C11 14 37 14 36.5 27 C36.2 31 34.5 34 33.2 35.5 L33.2 23 C33.2 23 31 17.5 24 17.5 C17 17.5 14.8 23 14.8 23 L14.8 35.5 C13.5 34 11.8 31 11.5 27 Z" fill="#4b4660"/>'
		. '<ellipse cx="24" cy="23" rx="8.6" ry="9.6" fill="#f6cca9"/>'
		. '<path d="M15.2 21.5 C16 14.5 32 14.5 32.8 21.5 C30 18.8 27 18 24 18 C21 18 18 18.8 15.2 21.5 Z" fill="#4b4660"/>'
		. '<circle cx="20.6" cy="23" r="1.15" fill="#2b2b35"/>'
		. '<circle cx="27.4" cy="23" r="1.15" fill="#2b2b35"/>'
		. '<path d="M21.4 27 Q24 29.4 26.6 27" fill="none" stroke="#c06a4f" stroke-width="1.4" stroke-linecap="round"/>'
		. '<path d="M14 24 Q24 9 34 24" fill="none" stroke="#1f2937" stroke-width="2.1" stroke-linecap="round"/>'
		. '<rect x="12.3" y="23" width="3.4" height="5" rx="1.7" fill="#1f2937"/>'
		. '<rect x="32.3" y="23" width="3.4" height="5" rx="1.7" fill="#1f2937"/>'
		. '<path d="M14 27.5 Q12.5 33 19 33" fill="none" stroke="#1f2937" stroke-width="1.6" stroke-linecap="round"/>'
		. '<circle cx="19.4" cy="33" r="1.5" fill="#1f2937"/>'
		. '</svg>';
}

/** ボタン */
function ais_button( $href, $label, $variant = 'primary', $size = 'md', $extra_class = '', $attrs = array() ) {
	$base = 'inline-flex items-center justify-center gap-2 rounded-full font-semibold transition-all duration-200 focus-visible:ring-2 focus-visible:ring-offset-2 disabled:opacity-60 disabled:pointer-events-none';
	$variants = array(
		'primary'   => 'bg-brand-600 text-white shadow-card hover:bg-brand-700 hover:shadow-card-hover focus-visible:ring-brand-500',
		'secondary' => 'bg-white text-brand-700 ring-1 ring-inset ring-brand-200 hover:bg-brand-50 focus-visible:ring-brand-500',
		'ghost'     => 'bg-white/10 text-white ring-1 ring-inset ring-white/30 hover:bg-white/20 focus-visible:ring-white',
	);
	$sizes = array( 'md' => 'px-5 py-2.5 text-sm', 'lg' => 'px-7 py-3.5 text-base' );
	$cls   = trim( $base . ' ' . $variants[ $variant ] . ' ' . $sizes[ $size ] . ' ' . $extra_class );
	$attr_str = '';
	foreach ( $attrs as $k => $v ) {
		$attr_str .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
	}
	return '<a href="' . esc_url( ais_url( $href ) ) . '" class="' . esc_attr( $cls ) . '"' . $attr_str . '>' . $label . '</a>';
}

/** セクション見出し（SectionHeading） */
function ais_section_heading( $title, $eyebrow = '', $lead = '', $align = 'left', $invert = false ) {
	$wrap = 'max-w-3xl' . ( 'center' === $align ? ' mx-auto text-center' : '' );
	$h2   = 'mt-3 text-2xl sm:text-3xl md:text-4xl leading-tight ' . ( $invert ? 'text-white' : 'text-ink-900' );
	ob_start(); ?>
	<div class="<?php echo esc_attr( $wrap ); ?>">
		<?php if ( $eyebrow ) : ?>
			<span class="eyebrow <?php echo $invert ? 'text-accent-400 before:bg-accent-400' : ''; ?>"><?php echo esc_html( $eyebrow ); ?></span>
		<?php endif; ?>
		<h2 class="<?php echo esc_attr( $h2 ); ?>"><?php echo wp_kses_post( $title ); ?></h2>
		<?php if ( $lead ) : ?>
			<p class="mt-4 text-base leading-relaxed <?php echo $invert ? 'text-slate-300' : 'text-ink-600'; ?>"><?php echo esc_html( $lead ); ?></p>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** 下層ページ共通ヘッダー（PageHero） */
function ais_page_hero( $eyebrow, $title, $lead = '' ) {
	ob_start(); ?>
	<section class="relative overflow-hidden bg-ink-900 text-white">
		<div class="pointer-events-none absolute inset-0 opacity-80" aria-hidden="true"
			style="background:radial-gradient(60% 80% at 85% 0%, rgba(6,182,212,0.16), transparent 60%), radial-gradient(50% 80% at 0% 100%, rgba(37,99,235,0.22), transparent 55%)"></div>
		<div class="container relative">
			<div class="max-w-3xl py-16 sm:py-20">
				<span class="eyebrow text-accent-400 before:bg-accent-400"><?php echo esc_html( $eyebrow ); ?></span>
				<h1 class="mt-4 text-3xl font-bold leading-tight sm:text-4xl md:text-5xl"><?php echo esc_html( $title ); ?></h1>
				<?php if ( $lead ) : ?>
					<p class="mt-5 text-base leading-relaxed text-slate-300 sm:text-lg"><?php echo esc_html( $lead ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

/** FAQ アコーディオン（Accordion.tsx／main.js が開閉を制御） */
function ais_accordion( $items ) {
	ob_start(); ?>
	<div class="divide-y divide-slate-200 rounded-2xl border border-slate-200 bg-white" data-ais-accordion>
		<?php foreach ( $items as $i => $item ) : $open = ( 0 === $i ); ?>
			<div data-ais-acc-item>
				<h3>
					<button type="button" data-ais-acc-trigger aria-expanded="<?php echo $open ? 'true' : 'false'; ?>" class="flex w-full items-center justify-between gap-4 px-5 py-5 text-left sm:px-6">
						<span class="flex items-start gap-3 text-base font-semibold text-ink-900">
							<span class="mt-0.5 text-brand-600">Q.</span>
							<?php echo esc_html( $item['q'] ); ?>
						</span>
						<span data-ais-acc-icon class="<?php echo $open ? 'rotate-180' : ''; ?> flex-none text-brand-600 transition-transform duration-200">
							<?php echo ais_icon( 'chevron-down', 'h-5 w-5' ); // phpcs:ignore ?>
						</span>
					</button>
				</h3>
				<div data-ais-acc-panel class="grid transition-all duration-200 <?php echo $open ? 'grid-rows-[1fr] opacity-100' : 'grid-rows-[0fr] opacity-0'; ?>">
					<div class="overflow-hidden">
						<p class="flex gap-3 px-5 pb-6 text-sm leading-relaxed text-ink-600 sm:px-6">
							<span class="font-semibold text-accent-600">A.</span>
							<?php echo esc_html( $item['a'] ); ?>
						</p>
					</div>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/** 最終CTAバナー（CtaBanner） */
function ais_cta_banner() {
	$site = ais_site();
	ob_start(); ?>
	<section class="relative overflow-hidden bg-ink-900 py-16 sm:py-20">
		<div class="pointer-events-none absolute inset-0 opacity-70" aria-hidden="true"
			style="background:radial-gradient(60% 80% at 80% 0%, rgba(6,182,212,0.18), transparent 60%), radial-gradient(50% 70% at 0% 100%, rgba(37,99,235,0.22), transparent 55%)"></div>
		<div class="container relative">
			<div class="mx-auto max-w-3xl text-center">
				<span class="eyebrow mx-auto justify-center text-accent-400 before:bg-accent-400">Contact</span>
				<h2 class="mt-4 text-2xl font-bold text-white sm:text-3xl md:text-4xl">まずは、現状をお聞かせください。</h2>
				<p class="mx-auto mt-4 max-w-2xl text-base leading-relaxed text-slate-300">「何から始めればいいか分からない」段階のご相談も歓迎です。法人・個人事業主どちらも対応。初回のご相談・お見積りは無料、<?php echo esc_html( $site['reply_target'] ); ?>にご返信します。</p>
				<div class="mt-8 flex flex-col items-center justify-center gap-3 sm:flex-row">
					<?php echo ais_button( '/contact', '無料で相談する', 'primary', 'lg' ); // phpcs:ignore ?>
					<?php echo ais_button( '/services', 'サービスを見る', 'ghost', 'lg' ); // phpcs:ignore ?>
				</div>
				<div class="mt-6 flex flex-wrap items-center justify-center gap-x-6 gap-y-2 text-sm text-slate-300">
					<span class="text-slate-400">ご相談はこちらから：</span>
					<a href="<?php echo esc_url( ais_url( '/contact' ) ); ?>" class="inline-flex items-center gap-1.5 font-semibold text-white hover:text-accent-400"><?php echo ais_icon( 'mail', 'h-4 w-4' ); // phpcs:ignore ?>お問い合わせフォーム</a>
					<a href="mailto:<?php echo esc_attr( $site['email'] ); ?>" class="inline-flex items-center gap-1.5 font-semibold text-white hover:text-accent-400"><?php echo ais_icon( 'mail', 'h-4 w-4' ); // phpcs:ignore ?>メール</a>
					<a href="<?php echo esc_url( ais_url( '/contact' ) ); ?>" data-ais-open-chat class="inline-flex items-center gap-1.5 font-semibold text-white hover:text-accent-400"><?php echo ais_icon( 'spark', 'h-4 w-4' ); // phpcs:ignore ?>AIチャットで相談</a>
				</div>
			</div>
		</div>
	</section>
	<?php
	return ob_get_clean();
}
