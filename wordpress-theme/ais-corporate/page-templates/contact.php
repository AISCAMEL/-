<?php
/**
 * Template Name: お問い合わせ
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$site = ais_site();
$sent = isset( $_GET['sent'] ) ? sanitize_text_field( wp_unslash( $_GET['sent'] ) ) : '';

$points = array(
	array( '相談・見積りは無料', '初回のご相談、お見積りは無料です。' ),
	array( '法人・個人どちらもOK', '個人のお客様から法人まで、お気軽にご相談ください。' ),
	array( '返信目安：' . $site['reply_target'], '内容によりお時間をいただく場合があります。' ),
	array( 'まとまっていなくてOK', '課題の整理からお手伝いします。' ),
);
$examples = array(
	'国産車の購入や乗り換えを相談したい（カーメル）',
	'車・トラック・農機具・アルミを買取してほしい（BUYMO）',
	'新車をオンラインで注文して自宅で受け取りたい（CARSHICO）',
	'車のGPS・遠隔停止セキュリティを相談したい（天護）',
	'ノーコードでアプリを作りたい（APPREX）',
	'サブスクでホームページを作りたい（WEB crews）',
);
$subjects = array(
	'自動車販売（カーメル）', '自動車買取（BUYMO）', 'オンライン車販売（CARSHICO）',
	'車両セキュリティ（天護 TENGO）', 'レッカー・カーレスキュー', 'IT事業・アプリ開発（APPREX）',
	'WEB制作（WEB crews）', 'AIオペレーター24（準備中）', 'FC事業（加盟のご相談）', 'その他・どれか分からない',
);
$input_base = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-ink-900 placeholder:text-ink-400 focus:border-brand-500 focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-0';
?>

<?php echo ais_page_hero( 'Contact', 'まずは、お気軽にご相談ください', '「何から始めればいいか分からない」段階のご相談も歓迎です。無理な売り込みはいたしません。' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="grid gap-12 lg:grid-cols-12">
			<div class="lg:col-span-5">
				<h2 class="text-xl font-bold text-ink-900">初回のご相談について</h2>
				<ul class="mt-5 space-y-3">
					<?php foreach ( $points as $pt ) : ?>
						<li class="flex items-start gap-3">
							<?php echo ais_icon( 'check', 'mt-0.5 h-5 w-5 flex-none text-accent-600' ); // phpcs:ignore ?>
							<span>
								<span class="block text-sm font-semibold text-ink-900"><?php echo esc_html( $pt[0] ); ?></span>
								<span class="block text-sm text-ink-600"><?php echo esc_html( $pt[1] ); ?></span>
							</span>
						</li>
					<?php endforeach; ?>
				</ul>

				<div class="mt-8 rounded-2xl border border-slate-200 bg-slate-50 p-6">
					<h3 class="text-sm font-bold text-ink-900">こんなご相談を承っています</h3>
					<ul class="mt-3 space-y-2">
						<?php foreach ( $examples as $e ) : ?>
							<li class="flex items-start gap-2 text-sm text-ink-600">
								<span class="mt-1.5 h-1.5 w-1.5 flex-none rounded-full bg-brand-500"></span>
								<?php echo esc_html( $e ); ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>

				<div class="mt-8 space-y-3 text-sm">
					<a href="mailto:<?php echo esc_attr( $site['email'] ); ?>" class="flex items-center gap-3 text-ink-700 hover:text-brand-700">
						<?php echo ais_icon( 'mail', 'h-5 w-5 text-brand-600' ); // phpcs:ignore ?>
						<?php echo esc_html( $site['email'] ); ?>
					</a>
					<p class="flex items-center gap-3 text-ink-700">
						<?php echo ais_icon( 'phone', 'h-5 w-5 text-brand-600' ); // phpcs:ignore ?>
						<?php echo esc_html( $site['tel'] ); ?>（<?php echo esc_html( $site['tel_hours'] ); ?>）
					</p>
				</div>
			</div>

			<div class="lg:col-span-7">
				<div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-card sm:p-8">
					<?php if ( '1' === $sent ) : ?>
						<div class="rounded-2xl border border-brand-200 bg-brand-50 p-8 text-center">
							<span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-brand-600 text-white">
								<?php echo ais_icon( 'check', 'h-6 w-6' ); // phpcs:ignore ?>
							</span>
							<h2 class="mt-4 text-xl font-bold text-ink-900">送信ありがとうございます</h2>
							<p class="mt-2 text-sm text-ink-600">内容を確認のうえ、<?php echo esc_html( $site['reply_target'] ); ?>にご返信いたします。</p>
						</div>
					<?php else : ?>
						<?php if ( 'error' === $sent ) : ?>
							<p class="mb-5 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">送信に失敗しました。必須項目をご確認のうえ、もう一度お試しください。</p>
						<?php endif; ?>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" class="space-y-5">
							<input type="hidden" name="action" value="ais_contact">
							<?php wp_nonce_field( 'ais_contact', 'ais_contact_nonce' ); ?>
							<!-- ハニーポット（人間には非表示） -->
							<div class="hidden" aria-hidden="true">
								<label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
							</div>

							<div class="grid gap-5 sm:grid-cols-2">
								<div>
									<label for="name" class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-ink-900">お名前 <span class="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold text-white">必須</span></label>
									<input id="name" name="name" required class="<?php echo esc_attr( $input_base ); ?>" placeholder="山田 太郎">
								</div>
								<div>
									<label for="company" class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-ink-900">会社名・屋号 <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-ink-500">任意</span></label>
									<input id="company" name="company" class="<?php echo esc_attr( $input_base ); ?>" placeholder="株式会社○○（任意）">
								</div>
							</div>

							<div class="grid gap-5 sm:grid-cols-2">
								<div>
									<label for="email" class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-ink-900">メールアドレス <span class="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold text-white">必須</span></label>
									<input id="email" name="email" type="email" required class="<?php echo esc_attr( $input_base ); ?>" placeholder="example@example.com">
								</div>
								<div>
									<label for="tel" class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-ink-900">電話番号 <span class="rounded bg-slate-200 px-1.5 py-0.5 text-[10px] font-bold text-ink-500">任意</span></label>
									<input id="tel" name="tel" type="tel" class="<?php echo esc_attr( $input_base ); ?>" placeholder="000-0000-0000（任意）">
								</div>
							</div>

							<div>
								<label for="subject" class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-ink-900">ご相談の種類 <span class="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold text-white">必須</span></label>
								<select id="subject" name="subject" required class="<?php echo esc_attr( $input_base ); ?>">
									<option value="" disabled selected>選択してください</option>
									<?php foreach ( $subjects as $s ) : ?>
										<option value="<?php echo esc_attr( $s ); ?>"><?php echo esc_html( $s ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div>
								<label for="message" class="mb-1.5 flex items-center gap-2 text-sm font-semibold text-ink-900">ご相談内容 <span class="rounded bg-brand-600 px-1.5 py-0.5 text-[10px] font-bold text-white">必須</span></label>
								<textarea id="message" name="message" required rows="6" class="<?php echo esc_attr( $input_base ); ?>" placeholder="現状の課題や、相談したいことをご記入ください。まとまっていなくても問題ありません。"></textarea>
							</div>

							<label class="flex items-start gap-2 text-sm text-ink-600">
								<input type="checkbox" required class="mt-1 h-4 w-4 rounded border-slate-300">
								<span><a href="<?php echo esc_url( ais_url( '/privacy' ) ); ?>" class="text-brand-700 underline">プライバシーポリシー</a>に同意します</span>
							</label>

							<button type="submit" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-brand-600 px-7 py-4 text-base font-semibold text-white shadow-card transition-all hover:bg-brand-700 hover:shadow-card-hover focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 sm:w-auto">
								この内容で送信する
								<?php echo ais_icon( 'arrow-right', 'h-4 w-4' ); // phpcs:ignore ?>
							</button>
						</form>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>
