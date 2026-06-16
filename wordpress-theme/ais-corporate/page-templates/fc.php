<?php
/**
 * Template Name: FC加盟募集
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$fc = ais_get_service( 'fc' );
$merits = array(
	array( 'icon' => 'spark', 'title' => '確立されたブランド', 'body' => '自動車販売「カーメル」・買取「BUYMO」のブランドと仕組みを活用し、ゼロからの立ち上げよりも早く事業を始められます。' ),
	array( 'icon' => 'shield', 'title' => '開業から運営までサポート', 'body' => '開業準備・集客・運営のノウハウを本部が提供。未経験の分野でも、伴走しながら進められます。' ),
	array( 'icon' => 'car', 'title' => '販売・買取の両輪', 'body' => '販売（カーメル）と買取（BUYMO）を組み合わせ、仕入れから販売まで一貫した収益機会を狙えます。' ),
	array( 'icon' => 'store', 'title' => '多角化・新規参入に', 'body' => '既存事業への追加や、独立開業の柱として。自動車事業への参入・拡大をご検討の方に適しています。' ),
);
$brands = array( ais_get_service( 'carmel' ), ais_get_service( 'buymo' ) );
$steps = array(
	array( 'title' => 'お問い合わせ', 'body' => 'フォームから加盟のご相談をお寄せください。' ),
	array( 'title' => 'ご説明・面談', 'body' => '事業内容・条件・サポート体制を詳しくご説明します。' ),
	array( 'title' => '加盟のご契約', 'body' => '内容にご納得いただけましたら契約・準備を進めます。' ),
	array( 'title' => '開業準備・研修', 'body' => '本部のノウハウ提供と研修で、開業に向け準備します。' ),
	array( 'title' => '開業・運営サポート', 'body' => '開業後も運営をサポートし、事業の定着・成長を支えます。' ),
);
?>

<?php echo ais_page_hero( 'Franchise', 'FC加盟募集', '自動車販売「カーメル」・買取「BUYMO」のフランチャイズ加盟を募集しています。未経験でも、開業から運営まで本部がサポート。自動車事業への新規参入・多角化を一緒に進めましょう。' ); // phpcs:ignore ?>

<?php if ( $fc && ! empty( $fc['external_url'] ) ) : ?>
	<div class="border-b border-slate-200 bg-slate-50">
		<div class="container flex flex-wrap items-center gap-3 py-4">
			<span class="text-sm text-ink-600">FC専用サイトもご用意しています。</span>
			<a href="<?php echo esc_url( $fc['external_url'] ); ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">
				FC専用サイトを見る <?php echo ais_icon( 'arrow-right', 'h-4 w-4' ); // phpcs:ignore ?>
			</a>
		</div>
	</div>
<?php endif; ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '加盟のメリット', 'Merits', 'ブランド・仕組み・サポートを活かして、自動車事業をスムーズに。', 'center' ); // phpcs:ignore ?>
		<div class="mt-12 grid gap-5 sm:grid-cols-2">
			<?php foreach ( $merits as $i => $m ) : ?>
				<div class="reveal flex gap-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-card" style="transition-delay:<?php echo (int) ( $i * 70 ); ?>ms">
					<span class="grid h-12 w-12 flex-none place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100"><?php echo ais_icon( $m['icon'], 'h-6 w-6' ); // phpcs:ignore ?></span>
					<div>
						<h3 class="text-base font-bold text-ink-900"><?php echo esc_html( $m['title'] ); ?></h3>
						<p class="mt-1.5 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $m['body'] ); ?></p>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-slate-50 text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '募集ブランド', 'Brands', '販売と買取、2つのブランドで加盟を募集しています。', 'center' ); // phpcs:ignore ?>
		<div class="mt-12 grid gap-5 sm:grid-cols-2">
			<?php foreach ( $brands as $b ) : if ( ! $b ) { continue; } ?>
				<div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-card">
					<div class="flex items-center gap-3">
						<span class="grid h-12 w-12 flex-none place-items-center rounded-2xl bg-brand-50 text-brand-600 ring-1 ring-inset ring-brand-100"><?php echo ais_icon( $b['icon'], 'h-6 w-6' ); // phpcs:ignore ?></span>
						<div>
							<p class="text-xs font-semibold tracking-wide text-brand-600"><?php echo esc_html( $b['brand'] ); ?></p>
							<h3 class="text-lg font-bold text-ink-900"><?php echo esc_html( $b['name'] ); ?></h3>
						</div>
					</div>
					<p class="mt-4 text-sm leading-relaxed text-ink-600"><?php echo esc_html( $b['summary'] ); ?></p>
					<a href="<?php echo esc_url( ais_url( '/services/' . $b['slug'] ) ); ?>" class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-700 hover:text-brand-800">ブランド詳細 <?php echo ais_icon( 'arrow-right', 'h-4 w-4' ); // phpcs:ignore ?></a>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<?php echo ais_section_heading( '加盟までの流れ', 'Flow', 'お問い合わせから開業・運営サポートまで、本部が伴走します。', 'center' ); // phpcs:ignore ?>
		<ol class="mt-12 grid gap-4 md:grid-cols-5">
			<?php foreach ( $steps as $i => $s ) : ?>
				<li class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
					<span class="inline-flex h-9 w-9 items-center justify-center rounded-full bg-brand-600 text-sm font-bold text-white"><?php echo (int) ( $i + 1 ); ?></span>
					<h3 class="mt-3 text-sm font-bold text-ink-900"><?php echo esc_html( $s['title'] ); ?></h3>
					<p class="mt-1.5 text-xs leading-relaxed text-ink-600"><?php echo esc_html( $s['body'] ); ?></p>
				</li>
			<?php endforeach; ?>
		</ol>
		<div class="mt-12 text-center">
			<?php echo ais_button( '/contact', 'FC加盟について相談する' . ais_icon( 'arrow-right', 'h-4 w-4' ), 'primary', 'lg' ); // phpcs:ignore ?>
			<p class="mt-3 text-xs text-ink-400">お問い合わせフォームの「ご相談の種類」で「FC事業（加盟のご相談）」をお選びください。</p>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
