<?php
/**
 * リニューアル版トップ：AIスタジオ紹介。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sec alt" id="studio">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">AI Studio</span><h2>無料会員登録で、AIがアプリのデザインと仕様書を作ります</h2><p>登録すると使える「AIスタジオ」で、デザインも機能も、納得いくまで何度でも無料で作り直せます。満足したら、そのまま開発の相談へ進めます。</p></div>
    <ol class="st-flow" aria-label="相談から公開までの流れ"><li><span>1</span>無料会員登録</li><li><span>2</span>AIでデザイン</li><li><span>3</span>AIで仕様書・概算</li><li><span>4</span>開発を相談</li><li><span>5</span>オンラインミーティング</li><li><span>6</span>お見積り・ご入金</li><li><span>7</span>制作</li><li><span>8</span>公開</li></ol>
    <div class="st-grid">
      <div class="st-points">
        <div class="stp"><b>AIがアプリ画面を5つデザイン</b><p>業種と店名を入れるだけで、ホーム・メニュー・クーポン・予約・会員証の画面ができます。別案も選べます。</p></div>
        <div class="stp"><b>言葉で頼むだけで修正</b><p>「予約ボタンを大きく」「緑で高級感を」と書けば、AIがすぐに反映。ロゴや写真ものせられます。</p></div>
        <div class="stp"><b>仕様書と概算を自動で作成</b><p>機能を選ぶと、画面一覧・必要な素材・概算費用まで。ノーコードかオリジナル開発かも判定します。</p></div>
        <div class="stp"><b>相談から制作の進捗まで1か所で</b><p>作った内容のまま相談し、ミーティング予約、お見積り、ご入金、制作の進み具合までスタジオで確認できます。</p></div>
      </div>
      <div class="st-visual">
        <div class="sphone" style="--pc1:#0f3d2e;--pc2:#1f9d6b" aria-hidden="true">
          <span class="notch"></span>
          <div class="scr">
            <div class="sp-top"><b>炭火焼肉 はなび</b><small>飲食店</small></div>
            <div class="sp-hero"><small>WELCOME</small><b>ご来店ごとにポイントが貯まる</b></div>
            <div class="sp-grid"><div class="sp-btn"><i>ネ</i>ネット予約</div><div class="sp-btn"><i>お</i>お品書き</div><div class="sp-btn"><i>ク</i>クーポン</div><div class="sp-btn"><i>ス</i>スタンプ</div><div class="sp-btn"><i>お</i>お知らせ</div><div class="sp-btn"><i>ア</i>アクセス</div></div>
            <div class="sp-cpn">ドリンク1杯無料クーポン</div>
            <div class="sp-tab"><span>ホーム</span><span>クーポン</span><span>予約</span><span>会員証</span></div>
          </div>
        </div>
        <div class="st-bubble">AI：「緑で高級感を」を反映しました（版3）</div>
      </div>
    </div>
    <div class="st-cta">
      <a class="btn btn--cta btn--lg" href="<?php echo esc_url( apprex_studio_url() ); ?>">無料会員登録してAIスタジオを使う</a>
      <a class="btn btn--line btn--lg" href="<?php echo esc_url( apprex_demo_url( 'sample' ) ); ?>" target="_blank" rel="noopener">サンプルのデモアプリを触る</a>
      <span>登録は1分。相談するまで費用は一切かかりません。</span>
    </div>
  </div>
</section>
