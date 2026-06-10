<?php
/**
 * 流れるスライド（アプリ画面が自動横スクロール）。
 *
 * 導入事例（case）のアイキャッチ画像があればそれを使用（管理画面で追加した
 * 実アプリ画面が自動で流れます）。無ければ同梱のサンプル画像にフォールバック。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 1) 事例CPTのアイキャッチを収集。
$apprex_items = array();
$apprex_cases = new WP_Query(
	array(
		'post_type'           => 'case',
		'posts_per_page'      => 12,
		'meta_key'            => '_thumbnail_id',
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	)
);
if ( $apprex_cases->have_posts() ) {
	while ( $apprex_cases->have_posts() ) :
		$apprex_cases->the_post();
		if ( has_post_thumbnail() ) {
			$apprex_items[] = array(
				'img'   => get_the_post_thumbnail_url( null, 'apprex-card' ),
				'label' => get_the_title(),
				'link'  => get_permalink(),
			);
		}
	endwhile;
	wp_reset_postdata();
}

// 2) フォールバック（同梱サンプル）。
if ( count( $apprex_items ) < 3 ) {
	$apprex_fallback = array(
		'app-sample-taka.jpg'       => 'TAKA（マッチング）',
		'app-sample-golf-one.jpg'   => 'Golf One（予約）',
		'app-sample-legal-one.jpg'  => 'Legal One（士業）',
		'app-sample-lien-new.jpg'   => 'Lien（コミュニティ）',
		'app-sample-house-bank.jpg' => 'House Bank（不動産）',
		'app-sample-lien.jpg'       => 'Lien',
	);
	$apprex_items = array();
	foreach ( $apprex_fallback as $file => $label ) {
		$apprex_items[] = array(
			'img'   => APPREX_URI . '/assets/images/' . $file,
			'label' => $label,
			'link'  => home_url( '/cases/' ),
		);
	}
}
?>
<section class="section app-marquee-sec" id="app-marquee">
	<div class="container">
		<?php apprex_section_head( 'Showcase', __( 'APPREX で生まれたアプリたち', 'apprex' ), __( 'ノーコードでここまで作れる。実際のアプリ画面をご覧ください。', 'apprex' ) ); ?>
	</div>
	<div class="marquee" aria-label="<?php esc_attr_e( 'アプリ画面のスライド', 'apprex' ); ?>">
		<div class="marquee__track">
			<?php
			for ( $i = 0; $i < 2; $i++ ) :
				foreach ( $apprex_items as $item ) :
					?>
					<a class="marquee__item" href="<?php echo esc_url( $item['link'] ); ?>">
						<img src="<?php echo esc_url( $item['img'] ); ?>" alt="<?php echo esc_attr( $item['label'] ); ?>" loading="lazy" decoding="async" width="240" height="430">
						<span class="marquee__cap"><?php echo esc_html( $item['label'] ); ?></span>
					</a>
					<?php
				endforeach;
			endfor;
			?>
		</div>
	</div>
</section>
