<?php
/** AIチャットウィジェット（フロント常駐）。挙動は assets/js/chat.js が制御。 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div id="ais-chat" class="fixed bottom-5 right-5 z-[60] print:hidden">
	<!-- 起動ボタン（案内係アバター） -->
	<button type="button" id="ais-chat-toggle" aria-label="AIコンシェルジュに相談する" aria-expanded="false"
		class="relative flex h-16 w-16 items-center justify-center rounded-full bg-white shadow-card-hover ring-1 ring-slate-200 transition hover:ring-brand-300">
		<span data-ais-chat-open class="grid h-14 w-14 place-items-center overflow-hidden rounded-full"><?php echo ais_chat_avatar( 'h-14 w-14' ); // phpcs:ignore ?></span>
		<span data-ais-chat-shut class="hidden text-ink-700"><?php echo ais_icon( 'close', 'h-6 w-6' ); // phpcs:ignore ?></span>
		<!-- オンライン（案内中）インジケータ -->
		<span data-ais-chat-online class="absolute right-1 top-1 h-3.5 w-3.5 rounded-full bg-emerald-500 ring-2 ring-white"></span>
	</button>

	<!-- パネル -->
	<div id="ais-chat-panel" class="absolute bottom-20 right-0 hidden w-[min(92vw,22rem)] origin-bottom-right overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card-hover">
		<div class="flex items-center gap-3 bg-ink-900 px-4 py-3 text-white">
			<span data-ais-chat-face class="grid h-10 w-10 place-items-center overflow-hidden rounded-full bg-white ring-1 ring-white/20"><?php echo ais_chat_avatar( 'h-10 w-10' ); // phpcs:ignore ?></span>
			<div class="min-w-0 leading-tight">
				<p class="flex items-center gap-1.5 whitespace-nowrap text-sm font-bold">AIコンシェルジュ
					<span class="h-1.5 w-1.5 flex-none rounded-full bg-emerald-400" title="オンライン"></span>
				</p>
				<p class="truncate text-[11px] text-slate-300"><?php echo esc_html( get_bloginfo( 'name' ) ); ?> ご案内</p>
			</div>
			<button type="button" data-ais-chat-mute aria-label="音声のオン／オフ" aria-pressed="false" class="ml-auto grid h-8 w-8 place-items-center rounded-md text-slate-300 hover:text-white">
				<span data-ais-voice-on><?php echo ais_icon( 'volume', 'h-5 w-5' ); // phpcs:ignore ?></span>
				<span data-ais-voice-off class="hidden"><?php echo ais_icon( 'mute', 'h-5 w-5' ); // phpcs:ignore ?></span>
			</button>
			<button type="button" data-ais-chat-close aria-label="閉じる" class="grid h-8 w-8 place-items-center rounded-md text-slate-300 hover:text-white"><?php echo ais_icon( 'close', 'h-5 w-5' ); // phpcs:ignore ?></button>
		</div>

		<?php $ais_op_video = get_theme_file_path( '/assets/media/operator.mp4' ); ?>
		<?php if ( file_exists( $ais_op_video ) ) : ?>
			<!-- オペレーター映像（ミュート・ループ。音声は読み上げで再生） -->
			<div data-ais-chat-stage class="relative bg-ink-900">
				<video data-ais-chat-video class="block h-40 w-full object-cover" muted loop playsinline preload="metadata"
					poster="<?php echo esc_url( get_theme_file_uri( '/assets/media/operator-poster.jpg' ) ); ?>">
					<source src="<?php echo esc_url( get_theme_file_uri( '/assets/media/operator.mp4' ) ); ?>" type="video/mp4">
				</video>
				<span class="pointer-events-none absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-black/45 px-2 py-1 text-[10px] font-semibold text-white backdrop-blur-sm">
					<span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>ご案内中
				</span>
			</div>
		<?php endif; ?>

		<div id="ais-chat-log" class="flex h-64 flex-col gap-3 overflow-y-auto bg-slate-50 px-4 py-4" aria-live="polite"></div>

		<form id="ais-chat-form" class="flex items-end gap-2 border-t border-slate-200 bg-white p-3">
			<label for="ais-chat-input" class="sr-only">メッセージ</label>
			<textarea id="ais-chat-input" rows="1" placeholder="ご質問・ご相談を入力…"
				class="max-h-28 w-full resize-none rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-500 focus-visible:ring-2 focus-visible:ring-brand-500"></textarea>
			<button type="submit" aria-label="送信" class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-brand-600 text-white transition hover:bg-brand-700 disabled:opacity-50">
				<?php echo ais_icon( 'arrow-right', 'h-5 w-5' ); // phpcs:ignore ?>
			</button>
		</form>
		<p class="border-t border-slate-100 bg-white px-4 py-2 text-[10px] leading-snug text-ink-400">AIによる自動応答です。詳しいご相談は<a href="<?php echo esc_url( ais_url( '/contact' ) ); ?>" class="text-brand-600 underline">お問い合わせ</a>へご案内します。</p>
	</div>
</div>
