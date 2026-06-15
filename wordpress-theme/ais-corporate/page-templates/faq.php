<?php
/**
 * Template Name: よくある質問
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
$faqs = ais_faqs();
$ld = array(
	'@context'   => 'https://schema.org',
	'@type'      => 'FAQPage',
	'mainEntity' => array_map( function ( $f ) {
		return array(
			'@type'          => 'Question',
			'name'           => $f['q'],
			'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $f['a'] ),
		);
	}, $faqs ),
);
echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
?>

<?php echo ais_page_hero( 'FAQ', 'よくある質問', 'お問い合わせ前の不安や疑問を解消できるよう、よくいただくご質問をまとめました。' ); // phpcs:ignore ?>

<section class="py-16 sm:py-24 bg-white text-ink-800">
	<div class="container">
		<div class="mx-auto max-w-3xl">
			<?php echo ais_accordion( $faqs ); // phpcs:ignore ?>
		</div>
	</div>
</section>

<?php echo ais_cta_banner(); // phpcs:ignore ?>

<?php get_footer(); ?>
