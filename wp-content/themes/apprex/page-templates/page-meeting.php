<?php
/**
 * Template Name: ミーティング予約ページ (Meeting)
 *
 * Assign to the "meeting" page. オンライン相談予約＋リマインダー。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( function_exists( 'apprex_mb_enabled' ) && apprex_mb_enabled() ) {
	wp_enqueue_style( 'apprex-meeting', APPREX_URI . '/assets/css/meeting.css', array(), APPREX_VERSION );
}
get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero">
		<div class="container">
			<nav class="breadcrumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
			</nav>
			<h1><?php the_title(); ?></h1>
			<p><?php esc_html_e( 'Google Meet での無料オンライン相談をご予約いただけます。空いている日時を選ぶだけで、その場で予約が確定します。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php $apprex_meet_url = (string) get_option( 'apprex_meeting_url', 'https://calendar.app.google/6xgkopT97tSwsHb76' ); ?>

			<?php if ( function_exists( 'apprex_mb_enabled' ) && apprex_mb_enabled() ) : ?>
				<?php apprex_mb_booking_ui(); ?>
				<div class="content-prose" style="margin-top:28px">
					<?php if ( trim( get_the_content() ) ) : ?>
						<?php the_content(); ?>
					<?php endif; ?>
					<h2><?php esc_html_e( 'ご予約のあと', 'apprex' ); ?></h2>
					<ol>
						<li><?php esc_html_e( '確認のメール（LINEから予約した方はLINEにも）が届きます。', 'apprex' ); ?></li>
						<li><?php esc_html_e( '前日と1時間前に、参加URLつきのお知らせが届きます。', 'apprex' ); ?></li>
						<li><?php esc_html_e( '当日は、Google Meet の参加URLを押して参加してください。日時の変更は、確認メールへの返信でお知らせください。', 'apprex' ); ?></li>
					</ol>
				</div>
			<?php elseif ( $apprex_meet_url ) : ?>
				<div class="meet-book is-reveal">
					<div class="meet-book__icon" aria-hidden="true">📅</div>
					<h2 class="meet-book__title"><?php esc_html_e( 'Google Meet で無料オンライン相談', 'apprex' ); ?></h2>
					<p class="meet-book__lead"><?php esc_html_e( '下のボタンから空いている枠を選ぶだけ。予約すると、Googleカレンダーから Google Meet の参加URL・招待メール・リマインダーが自動で届きます。', 'apprex' ); ?></p>
					<a class="btn btn--primary btn--lg" href="<?php echo esc_url( $apprex_meet_url ); ?>" target="_blank" rel="noopener">
						<?php esc_html_e( '空き枠を見て予約する（Google Meet）', 'apprex' ); ?>
					</a>
					<ul class="meet-book__steps">
						<li><span><?php esc_html_e( '1', 'apprex' ); ?></span><?php esc_html_e( '希望の日時を選ぶ', 'apprex' ); ?></li>
						<li><span><?php esc_html_e( '2', 'apprex' ); ?></span><?php esc_html_e( 'お名前・メールを入力', 'apprex' ); ?></li>
						<li><span><?php esc_html_e( '3', 'apprex' ); ?></span><?php esc_html_e( 'Meet URL が自動発行・リマインダーも自動', 'apprex' ); ?></li>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! ( function_exists( 'apprex_mb_enabled' ) && apprex_mb_enabled() ) ) : ?>
			<div class="content-prose">
				<?php if ( trim( get_the_content() ) ) : ?>
					<?php the_content(); ?>
				<?php endif; ?>

				<h2><?php esc_html_e( 'ご予約のやり方', 'apprex' ); ?></h2>
				<ol>
					<li><?php esc_html_e( '上のカレンダーから、ご希望の日時（空き枠）を選びます。', 'apprex' ); ?></li>
					<li><?php esc_html_e( 'お名前とメールアドレスを入力して「予約」を押します。', 'apprex' ); ?></li>
					<li><?php esc_html_e( 'ご登録のメールに、Google Meet の参加URL・カレンダー招待・リマインダーが自動で届きます。', 'apprex' ); ?></li>
					<li><?php esc_html_e( '当日は、届いたメール内の Meet URL をクリックして参加してください。', 'apprex' ); ?></li>
				</ol>
				<?php $apprex_line = function_exists( 'apprex_line_url' ) ? apprex_line_url() : ''; ?>
				<p>
					<?php esc_html_e( '空いている枠が見つからない場合や、ご不明点がある場合は、お気軽にご連絡ください。', 'apprex' ); ?>
					<?php if ( $apprex_line ) : ?>
						<br><a class="btn btn--line" href="<?php echo esc_url( $apprex_line ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'LINEで相談する', 'apprex' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
			<?php endif; ?>
		</div>
	</section>
	<?php
endwhile;

get_footer();
