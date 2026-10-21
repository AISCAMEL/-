<?php
/**
 * リニューアル版トップ：料金プラン。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sec" id="plans">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Plans</span><h2>進め方で選べる料金プラン</h2><p>AIスタジオでのデザイン・試作・仕様書づくりは無料。ノーコードは月額が同じで、登録を自分でするか、APPREXに任せるかで初期費用が変わります。独自機能はオリジナル開発プランで。</p></div>
    <div class="plans">
      <div class="plan">
        <span class="who">デザインは自分で作りたい方へ</span>
        <h3>セルフプラン</h3>
        <div class="fees"><div><small>初期費用</small><b>0<span>円</span></b></div><div><small>月額（税抜）</small><b>19,800<span>円〜</span></b></div></div>
        <div class="steps-mini">AIスタジオでデザイン・仕様書 → 相談・ミーティング → 自分で登録（APPREXがチェック） → 公開</div>
        <ul><li>AIスタジオ（デザイン・仕様書）を無料で利用</li><li>画像付きの制作マニュアル</li><li>登録内容のチェックと公開サポート</li><li>プッシュ通知は無制限</li></ul>
        <a class="btn btn--line btn--lg" href="<?php echo esc_url( apprex_studio_url() ); ?>">AIスタジオから始める</a>
      </div>
      <div class="plan rec">
        <span class="badge">いちばん人気</span>
        <span class="who">忙しい方・初めての方へ</span>
        <h3>おまかせプラン</h3>
        <div class="fees"><div><small>初期費用（制作代行）</small><b>150,000<span>円〜</span></b><em class="alt">AIスタジオで作った内容から制作<br>1からヒアリングして作る場合は 300,000円〜</em></div><div><small>月額（税抜）</small><b>19,800<span>円〜</span></b></div></div>
        <div class="steps-mini">AIスタジオでデザイン・仕様書（無料） → ミーティング → ご入金 → 制作・ストア申請 → 3ヶ月の運用サポート</div>
        <ul><li>企画・要件整理、画面デザイン、文章・バナー作成をまとめて代行</li><li>管理画面への登録と、App Store・Google Play の申請を代行</li><li>ストア掲載文の最適化（検索で見つけてもらいやすく）</li><li>公開後3ヶ月の運用サポート（お知らせ・プッシュ通知の作成と配信）</li><li>AIスタジオで要件が固まっているので、1から作るより安く・早く</li></ul>
        <a class="btn btn--cta btn--lg" href="#lead">おまかせで相談する</a>
      </div>
      <div class="plan plan-wide">
        <div>
          <span class="who">独自の機能・サービスを作りたい方へ</span>
          <h3>オリジナル開発プラン</h3>
          <p class="pw-text">ノーコードで収まらない仕組みを一から開発します。マッチングアプリは開発費300万円〜・月額保守7万円〜。その他は内容に合わせてお見積りします。</p>
        </div>
        <div class="pw-acts"><a class="btn btn--line btn--lg" href="#original">強みと事例を見る</a><a class="btn btn--cta btn--lg" href="#lead">無料で見積りを相談</a></div>
      </div>
    </div>
    <p class="pnote">税抜。月額は1年契約です。どちらのプランも、アプリストア登録費（一律55,000円）が別途かかります。独自機能のオリジナル開発・マッチングアプリは個別見積りです。</p>
  </div>
</section>
