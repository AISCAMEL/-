<?php
/**
 * Template Name: 空白キャンバス（LPプラグイン用）
 *
 * ヘッダー/フッター/ナビを一切出さない真っさらなページ。Elementor 等のLP作成
 * プラグインで全画面を自由に組むためのキャンバス。SNS広告の計測タグは
 * wp_head/wp_footer 経由で自動的に出力される。
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
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'apprex-lp-canvas' ); ?>>
<?php wp_body_open(); ?>
<?php
while ( have_posts() ) :
	the_post();
	the_content();
endwhile;
?>
<?php wp_footer(); ?>
</body>
</html>
