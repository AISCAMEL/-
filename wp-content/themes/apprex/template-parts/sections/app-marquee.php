<?php
/**
 * 流れるスライド（アプリ画面が自動で横スクロール）。
 * トップページの訴求＋アプリ画像を多めに見せるための帯。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_apps = array(
	'app-sample-taka.jpg'       => 'TAKA（マッチングアプリ）',
	'app-sample-golf-one.jpg'   => 'Golf One（ゴルフ予約）',
	'app-sample-legal-one.jpg'  => 'Legal One（法律相談）',
	'app-sample-lien-new.jpg'   => 'Lien（コミュニティ）',
	'app-sample-house-bank.jpg' => 'House Bank（不動産）',
	'app-sample-lien.jpg'       => 'Lien（旧版）',
);
?>
<section class="section app-marquee-sec" id="app-marquee">
	<div class="container">
		<?php apprex_section_head( 'Showcase', __( 'APPREX で生まれたアプリたち', 'apprex' ), __( 'ノーコードでここまで作れる。実際のアプリ画面をご覧ください。', 'apprex' ) ); ?>
	</div>
	<div class="marquee" aria-label="<?php esc_attr_e( 'アプリ画面のスライド', 'apprex' ); ?>">
		<div class="marquee__track">
			<?php
			// 2周分（シームレスループ用）。
			for ( $i = 0; $i < 2; $i++ ) :
				foreach ( $apprex_apps as $file => $label ) :
					?>
					<figure class="marquee__item">
						<img src="<?php echo esc_url( APPREX_URI . '/assets/images/' . $file ); ?>"
							alt="<?php echo esc_attr( $label ); ?>" loading="lazy" decoding="async"
							width="240" height="430">
						<figcaption><?php echo esc_html( $label ); ?></figcaption>
					</figure>
					<?php
				endforeach;
			endfor;
			?>
		</div>
	</div>
</section>
