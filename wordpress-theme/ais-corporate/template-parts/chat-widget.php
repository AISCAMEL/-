<?php
/** AIチャットウィジェット（フロント常駐）。挙動は assets/js/chat.js が制御。 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<div id="ais-chat" class="fixed bottom-5 right-5 z-[60] print:hidden">
	<!-- 起動ボタン -->
	<button type="button" id="ais-chat-toggle" aria-label="AIチャットを開く" aria-expanded="false"
		class="group flex h-14 w-14 items-center justify-center rounded-full bg-brand-600 text-white shadow-card-hover ring-1 ring-inset ring-white/20 transition hover:bg-brand-700">
		<span data-ais-chat-open><?php echo ais_icon( 'spark', 'h-7 w-7' ); // phpcs:ignore ?></span>
		<span data-ais-chat-shut class="hidden"><?php echo ais_icon( 'close', 'h-6 w-6' ); // phpcs:ignore ?></span>
	</button>

	<!-- パネル -->
	<div id="ais-chat-panel" class="absolute bottom-16 right-0 hidden w-[min(92vw,22rem)] origin-bottom-right overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-card-hover">
		<div class="flex items-center gap-3 bg-ink-900 px-4 py-3 text-white">
			<span class="grid h-9 w-9 place-items-center rounded-full bg-brand-600 text-white"><?php echo ais_icon( 'spark', 'h-5 w-5' ); // phpcs:ignore ?></span>
			<div class="leading-tight">
				<p class="text-sm font-bold">AIアシスタント</p>
				<p class="text-[11px] text-slate-300"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
			</div>
			<button type="button" data-ais-chat-close aria-label="閉じる" class="ml-auto grid h-8 w-8 place-items-center rounded-md text-slate-300 hover:text-white"><?php echo ais_icon( 'close', 'h-5 w-5' ); // phpcs:ignore ?></button>
		</div>

		<div id="ais-chat-log" class="flex h-80 flex-col gap-3 overflow-y-auto bg-slate-50 px-4 py-4" aria-live="polite"></div>

		<form id="ais-chat-form" class="flex items-end gap-2 border-t border-slate-200 bg-white p-3">
			<label for="ais-chat-input" class="sr-only">メッセージ</label>
			<textarea id="ais-chat-input" rows="1" placeholder="メッセージを入力…"
				class="max-h-28 w-full resize-none rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-500 focus-visible:ring-2 focus-visible:ring-brand-500"></textarea>
			<button type="submit" aria-label="送信" class="grid h-10 w-10 flex-none place-items-center rounded-xl bg-brand-600 text-white transition hover:bg-brand-700 disabled:opacity-50">
				<?php echo ais_icon( 'arrow-right', 'h-5 w-5' ); // phpcs:ignore ?>
			</button>
		</form>
		<p class="border-t border-slate-100 bg-white px-4 py-2 text-[10px] leading-snug text-ink-400">AIによる自動応答です。内容が不確実な場合は<a href="<?php echo esc_url( ais_url( '/contact' ) ); ?>" class="text-brand-600 underline">お問い合わせ</a>ください。</p>
	</div>
</div>
