<?php
/**
 * リニューアル版トップ：費用の目安。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<?php
$apprex_ask  = function_exists( 'apprex_ask_pill' ) ? apprex_ask_pill() : '無料で見積り';
$apprex_rows = array(
	array( 'ノーコード トライアル', '<span class="num">19,800</span>円〜／月', '最短2週間', 'まず小さく始めたい' ),
	array( 'ノーコード スタート', $apprex_ask, '最短2週間', 'カタログ・クーポンで集客' ),
	array( 'ノーコード ビジネス', $apprex_ask, '最短1ヶ月', '多店舗の展開・運用' ),
	array( 'ホームページ', '<span class="num">9,800</span>円〜／月<small>AIプランナーで制作費0円</small>', '最短2週間', '検索から新しいお客様を' ),
	array( 'オリジナル開発・マッチング', $apprex_ask, '3ヶ月〜', '独自機能・仲介ビジネス' ),
);
?>
<section class="sec" id="cost">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Cost</span><h2>費用の目安</h2><p>デザインと仕様書は、AIスタジオで無料。あなたの場合の金額は、作りたい内容を伺って無料でお見積りします。</p></div>
    <div class="tbl cost-tbl">
      <table>
        <thead><tr><th>プラン</th><th>料金（税抜）</th><th>公開まで</th><th class="hl">こんな企業に</th></tr></thead>
        <tbody>
          <?php foreach ( $apprex_rows as $r ) : ?>
            <tr><td><b><?php echo esc_html( $r[0] ); ?></b></td><td class="cost-p"><?php echo wp_kses_post( $r[1] ); ?></td><td><?php echo esc_html( $r[2] ); ?></td><td class="hl"><?php echo esc_html( $r[3] ); ?></td></tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="ph-ask">
      <div class="ph-ask-t"><b>「うちの場合はいくら？」</b><span>業種とやりたいことを送るだけ。担当者が、あなたに合うプランと金額を2営業日以内にお送りします（無料）。</span></div>
      <div class="ph-ask-b">
        <a class="btn btn--cta" href="<?php echo esc_url( apprex_page_url( 'estimate' ) ); ?>">無料で見積りを受け取る</a>
        <a class="btn ph-ghost" href="<?php echo esc_url( apprex_studio_url() ); ?>">AIスタジオで無料で作る</a>
      </div>
    </div>
  </div>
</section>
