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
<section class="sec" id="cost">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Cost</span><h2>費用の目安</h2><p>はじめから総額がわかるように、主なプランを並べました。</p></div>
    <div class="tbl cost-tbl">
      <table>
        <thead><tr><th>プラン</th><th>初期費用</th><th>月額</th><th>公開まで</th><th class="hl">こんな企業に</th></tr></thead>
        <tbody>
          <tr><td><b>ノーコード トライアル</b></td><td><span class="num">15</span>万円</td><td><span class="num">19,800</span>円</td><td>最短2週間</td><td class="hl">まず小さく始めたい</td></tr>
          <tr><td><b>ノーコード スタート</b></td><td><span class="num">15</span>万円</td><td><span class="num">39,800</span>円</td><td>最短2週間</td><td class="hl">カタログ・クーポンで集客</td></tr>
          <tr><td><b>ノーコード ビジネス</b></td><td><span class="num">15</span>万円</td><td><span class="num">59,800</span>円</td><td>最短1ヶ月</td><td class="hl">多店舗の展開・運用</td></tr>
          <tr><td><b>カスタマイズ</b></td><td>お見積もり</td><td>お見積もり</td><td>内容による</td><td class="hl">基盤に機能を足したい・変えたい</td></tr>
          <tr><td><b>フルスクラッチ</b></td><td>要相談</td><td>要相談</td><td>3ヶ月〜</td><td class="hl">ゼロから独自のサービスを作りたい</td></tr>
          <tr><td><b>マッチングアプリ</b></td><td><span class="num">300</span>万円〜</td><td>保守 <span class="num">7</span>万円〜</td><td>3ヶ月〜</td><td class="hl">仲介ビジネスを始めたい</td></tr>
        </tbody>
      </table>
    </div>
    <p class="cap">税抜。ノーコードの初期費用は、APPREXの基盤で作れる範囲の場合です。基盤のカスタマイズは内容と最初のロットにより別途お見積もり、フルスクラッチは用途に応じてご相談です。ホームページ制作（月額9,800円〜・初期費用0円）やオプションは、仕様書をもとにご提案します。アプリストア登録費（一律55,000円）が別途かかります。詳しい内訳は資料に掲載しています。</p>
    <div class="ph-ask">
      <div class="ph-ask-t"><b>「うちの場合はいくら？」</b><span>業種とやりたいことを送るだけ。担当者が、あなたに合うプランと金額を2営業日以内にお送りします（無料）。</span></div>
      <div class="ph-ask-b">
        <a class="btn btn--cta" href="<?php echo esc_url( apprex_page_url( 'estimate' ) ); ?>">無料で見積りを受け取る</a>
        <a class="btn ph-ghost" href="<?php echo esc_url( apprex_studio_url() ); ?>">AIスタジオで無料で作る</a>
      </div>
    </div>
  </div>
</section>
