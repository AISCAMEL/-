<?php
/**
 * AIS コーポレートTOP 固定ページテンプレート
 * テーマのヘッダー/フッターを使わず、独自の全幅レイアウトで描画します。
 * （プラグイン互換のため wp_head() / wp_footer() は呼び出します）
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="icon" type="image/svg+xml" href="<?php echo esc_url( AIS_CORP_URL . 'assets/img/favicon.svg' ); ?>" />
<?php wp_head(); ?>
</head>
<body <?php body_class( 'ais-corp-page' ); ?>>
<?php
echo ais_corp_site_header();
echo ais_corp_sections();
echo ais_corp_site_footer();
wp_footer();
?>
</body>
</html>
