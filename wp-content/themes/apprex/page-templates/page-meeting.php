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

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<section class="page-hero">
		<div class="container">
			<nav class="breadcrumbs">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a>
				<span> / </span><?php the_title(); ?>
			</nav>
			<h1><?php the_title(); ?></h1>
			<p><?php esc_html_e( 'Google Meet での無料オンライン相談をご予約いただけます。予約するとMeetの参加URL・招待・リマインダーが自動で届きます。', 'apprex' ); ?></p>
		</div>
	</section>

	<section class="section">
		<div class="container">
			<?php $apprex_meet_url = function_exists( 'apprex_meeting_url' ) ? apprex_meeting_url() : ''; ?>

			<?php if ( $apprex_meet_url ) : ?>
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
		</div>
	</section>
	<?php
endwhile;

get_footer();
