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
	array( 'icon' => '⚡', 'title' => __( 'ノーコード開発', 'apprex' ), 'desc' => __( 'プログラミング知識不要で、ドラッグ&ドロップで簡単にアプリを作成できます。', 'apprex' ) ),
	array( 'icon' => '💴', 'title' => __( '高性能、低価格', 'apprex' ), 'desc' => __( '従来の開発費用の1/10以下で、全業種・どんなアプリでも高品質に開発できます。', 'apprex' ) ),
	array( 'icon' => '🚀', 'title' => __( 'スピード開発', 'apprex' ), 'desc' => __( 'スピード公開対応（最短2週間）。従来の開発期間を大幅に短縮します。', 'apprex' ) ),
	array( 'icon' => '📱', 'title' => __( 'iOS / Android 対応', 'apprex' ), 'desc' => __( 'iOSとAndroidの両方に対応。一度の開発で両プラットフォームに配信できます。', 'apprex' ) ),
	array( 'icon' => '📊', 'title' => __( '分析機能', 'apprex' ), 'desc' => __( 'ユーザー行動を詳細に分析。データに基づいた改善が可能です。', 'apprex' ) ),
	array( 'icon' => '🤝', 'title' => __( '充実サポート', 'apprex' ), 'desc' => __( '専任スタッフが開発から運用までしっかりサポートします。', 'apprex' ) ),
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
