<?php
/**
 * デモアプリ（全画面・テーマのヘッダー/フッターなし）。inc/demo.php から読み込む。
 *
 * @package APPREX
 *
 * @var array $apprex_demo cfg / name / color / icon / manifest。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$apprex_v = APPREX_VERSION;
?>
<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?php echo esc_html( $apprex_demo['name'] ); ?>（デモアプリ）｜APPREX</title>
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="<?php echo esc_attr( $apprex_demo['color'] ); ?>">
<link rel="manifest" href="<?php echo esc_url( $apprex_demo['manifest'] ); ?>">
<link rel="icon" href="<?php echo esc_url( $apprex_demo['icon'] ); ?>">
<link rel="apple-touch-icon" href="<?php echo esc_url( $apprex_demo['icon'] ); ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?php echo esc_attr( mb_substr( $apprex_demo['name'], 0, 12 ) ); ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+JP:wght@400;500;700;800&family=Zen+Kaku+Gothic+New:wght@500;700;900&family=Zen+Old+Mincho:wght@700&family=M+PLUS+Rounded+1c:wght@700;800&family=Outfit:wght@600;800&display=swap">
<link rel="stylesheet" href="<?php echo esc_url( APPREX_URI . '/assets/css/app-demo.css?ver=' . $apprex_v ); ?>">
</head>
<body>
<div id="demoRoot" class="dwrap"><noscript>デモアプリを使うには JavaScript を有効にしてください。</noscript></div>
<script>window.APPREX_DEMO=<?php echo wp_json_encode( $apprex_demo['cfg'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP ); ?>;</script>
<script src="<?php echo esc_url( APPREX_URI . '/assets/js/studio-data.js?ver=' . $apprex_v ); ?>"></script>
<script src="<?php echo esc_url( APPREX_URI . '/assets/js/vendor/qrcode.js?ver=2.0.4' ); ?>"></script>
<script src="<?php echo esc_url( APPREX_URI . '/assets/js/app-demo.js?ver=' . $apprex_v ); ?>"></script>
</body>
</html>
