<?php
/**
 * お見積りまでの3ステップ（無料）。料金ページの終わりなどで使う。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_qs_app = function_exists( 'apprex_studio_url' ) ? apprex_studio_url() : apprex_page_url( 'estimate' );
$apprex_qs     = array(
	array( '1', 'AIスタジオで形にする', '業種と店名を入れるだけで、画面と仕様書をAIが作ります。会員登録は無料です。' ),
	array( '2', '見積りを依頼する', '作った内容のまま、ボタンひとつで担当者へ送れます。AIスタジオを使わず、条件を選んで依頼することもできます。' ),
	array( '3', '正式なお見積りが届く', '担当者が内容を確認し、2営業日以内にお見積りをお送りします。必要ならオンラインでご説明します。' ),
);
?>
<section class="section section--soft qs-sec">
	<div class="container">
		<?php apprex_section_head( 'Estimate', __( 'お見積りまで、かんたん3ステップ', 'apprex' ), __( 'お見積りは無料。依頼したからといって、お申し込みが必要になることはありません。', 'apprex' ) ); ?>
		<ol class="qs-steps">
			<?php foreach ( $apprex_qs as $s ) : ?>
				<li class="is-reveal"><span><?php echo esc_html( $s[0] ); ?></span><b><?php echo esc_html( $s[1] ); ?></b><p><?php echo esc_html( $s[2] ); ?></p></li>
			<?php endforeach; ?>
		</ol>
		<div class="qs-acts">
			<a class="btn btn--cta btn--lg" href="<?php echo esc_url( $apprex_qs_app ); ?>"><?php esc_html_e( 'AIスタジオで無料で作る', 'apprex' ); ?></a>
			<a class="btn btn--ghost btn--lg" href="<?php echo esc_url( apprex_page_url( 'estimate' ) ); ?>"><?php esc_html_e( '条件を選んで見積りを依頼', 'apprex' ); ?></a>
		</div>
	</div>
</section>
