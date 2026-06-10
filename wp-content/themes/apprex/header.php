<?php
/**
 * Site header & global navigation.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="screen-reader-text" href="#main"><?php esc_html_e( 'コンテンツへスキップ', 'apprex' ); ?></a>

<div class="campaign-bar">
	<?php echo esc_html( apprex_campaign_text() ); ?>
</div>

<header class="site-header">
	<div class="container site-header__inner">
		<div class="site-branding">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<a class="site-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<img class="logo-image" src="<?php echo esc_url( APPREX_URI . '/assets/images/apprex-logo.png' ); ?>" alt="APPREX" width="160" height="44">
				</a>
			<?php endif; ?>
		</div>

		<nav class="main-nav" aria-label="<?php esc_attr_e( 'グローバルナビ', 'apprex' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'menu',
					'fallback_cb'    => 'apprex_primary_menu_fallback',
					'depth'          => 1,
				)
			);
			?>
		</nav>

		<div class="header-actions">
			<a class="btn btn--primary" href="<?php echo esc_url( apprex_page_url( 'free-trial' ) ); ?>">
				<?php esc_html_e( '無料体験を始める', 'apprex' ); ?>
			</a>
		</div>

		<button class="nav-toggle" aria-expanded="false" aria-controls="mobile-drawer" aria-label="<?php esc_attr_e( 'メニューを開閉', 'apprex' ); ?>">
			<span></span><span></span><span></span>
		</button>
	</div>

	<div class="mobile-drawer" id="mobile-drawer">
		<?php
		wp_nav_menu(
			array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'menu',
				'fallback_cb'    => 'apprex_primary_menu_fallback',
				'depth'          => 1,
			)
		);
		?>
		<a class="btn btn--primary btn--block" href="<?php echo esc_url( apprex_page_url( 'free-trial' ) ); ?>">
			<?php esc_html_e( '無料体験を始める', 'apprex' ); ?>
		</a>
	</div>
</header>

<main id="main">
