<?php
/**
 * フッター（Footer.tsx を移植）。
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
$ais_site = ais_site();
?>
</main><!-- #main -->

<footer class="border-t border-ink-700 bg-ink-900 text-slate-300">
	<div class="container py-14">
		<div class="grid gap-10 md:grid-cols-2 lg:grid-cols-4">
			<div>
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="flex items-center gap-2.5">
					<img src="<?php echo esc_url( get_theme_file_uri( '/assets/img/logo-mark.png' ) ); ?>" alt="" class="h-9 w-auto" width="204" height="176">
					<span class="flex flex-col leading-none">
						<span class="text-base font-bold text-white"><?php echo esc_html( $ais_site['name'] ); ?></span>
						<span class="text-[10px] tracking-wider text-brand-300"><?php echo esc_html( $ais_site['tagline'] ); ?></span>
					</span>
				</a>
				<p class="mt-4 text-sm leading-relaxed text-slate-400">自動車販売「カーメル」・買取「BUYMO」・オンライン車販売「CARSHICO」・車両セキュリティ「天護」・レッカーを主軸に、IT事業「APPREX」、サブスクWeb制作「WEB crews」、FC事業を展開しています。</p>
			</div>

			<?php foreach ( ais_footer_nav() as $col ) : ?>
				<nav aria-label="<?php echo esc_attr( $col['title'] ); ?>">
					<h2 class="text-sm font-semibold text-white"><?php echo esc_html( $col['title'] ); ?></h2>
					<ul class="mt-4 space-y-2.5">
						<?php foreach ( $col['items'] as $item ) : ?>
							<li>
								<a href="<?php echo esc_url( ais_url( $item['href'] ) ); ?>" class="text-sm text-slate-400 transition-colors hover:text-white"><?php echo esc_html( $item['label'] ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
			<?php endforeach; ?>
		</div>

		<div class="mt-12 flex flex-col items-start justify-between gap-4 border-t border-ink-700 pt-6 sm:flex-row sm:items-center">
			<p class="text-xs text-slate-500">© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $ais_site['name'] ); ?> (<?php echo esc_html( $ais_site['name_en'] ); ?>). All rights reserved.</p>
			<div class="flex gap-5 text-xs text-slate-500">
				<a href="<?php echo esc_url( ais_url( '/privacy' ) ); ?>" class="hover:text-white">プライバシーポリシー</a>
				<a href="<?php echo esc_url( ais_url( '/contact' ) ); ?>" class="hover:text-white">お問い合わせ</a>
			</div>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
