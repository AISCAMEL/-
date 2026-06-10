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

				<div class="meet-or"><span><?php esc_html_e( 'ご都合の合う枠が無い場合は、ご希望日時をお送りください', 'apprex' ); ?></span></div>
			<?php endif; ?>

			<div class="content-prose">
				<?php if ( trim( get_the_content() ) ) : ?>
					<?php the_content(); ?>
				<?php endif; ?>
				<?php apprex_render_form( 'meeting' ); ?>
			</div>
		</div>
	</section>
	<?php
endwhile;

get_footer();
