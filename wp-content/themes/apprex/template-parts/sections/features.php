<?php
/**
 * 05. Features — 6 strengths as icon cards, spec §6/§7.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_features = array(
	array( 'icon' => '⚡', 'title' => __( 'ノーコード開発', 'apprex' ), 'desc' => __( 'プログラミング知識不要。ドラッグ＆ドロップでアプリを構築できます。', 'apprex' ) ),
	array( 'icon' => '💴', 'title' => __( '圧倒的な低価格', 'apprex' ), 'desc' => __( '従来の開発コストの1/10。月額19,800円〜で本格的なアプリを運用。', 'apprex' ) ),
	array( 'icon' => '🚀', 'title' => __( 'スピード開発', 'apprex' ), 'desc' => __( '最短2週間で公開。ビジネスチャンスを逃しません。', 'apprex' ) ),
	array( 'icon' => '🎨', 'title' => __( 'カスタマイズ自由', 'apprex' ), 'desc' => __( '業種・用途に合わせて柔軟にレイアウトと機能を調整できます。', 'apprex' ) ),
	array( 'icon' => '📱', 'title' => __( 'マルチプラットフォーム対応', 'apprex' ), 'desc' => __( 'iOS / Android の両方に1つの管理画面から対応します。', 'apprex' ) ),
	array( 'icon' => '🔒', 'title' => __( 'セキュリティ万全', 'apprex' ), 'desc' => __( '稼働率99.9%保証。堅牢なインフラで安心して運用いただけます。', 'apprex' ) ),
);
?>
<section class="section" id="features">
	<div class="container">
		<?php apprex_section_head( 'Features', __( 'APPREX が選ばれる6つの理由', 'apprex' ), __( 'ビジネスを加速させる、APPREX ならではの強み。', 'apprex' ) ); ?>
		<div class="grid grid--3">
			<?php foreach ( $apprex_features as $feature ) : ?>
				<div class="feature-card is-reveal">
					<div class="icon" aria-hidden="true"><?php echo esc_html( $feature['icon'] ); ?></div>
					<h3><?php echo esc_html( $feature['title'] ); ?></h3>
					<p><?php echo esc_html( $feature['desc'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
		<div class="text-center mt-32 is-reveal">
			<a class="btn btn--ghost" href="<?php echo esc_url( apprex_page_url( 'features' ) ); ?>"><?php esc_html_e( '特徴の詳細へ', 'apprex' ); ?></a>
		</div>
	</div>
</section>
