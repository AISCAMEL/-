<?php
/**
 * リニューアル版トップ：相談から公開までの流れ。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sec" id="flow">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">How it works</span><h2>相談から公開までの流れ</h2><p>デザインと仕様はAIスタジオで先に作るので、ミーティングはすぐ具体的な話から始められます。</p></div>
    <ol class="st-flow" aria-label="相談から公開までの流れ"><li><span>1</span>無料会員登録</li><li><span>2</span>AIでデザイン</li><li><span>3</span>AIで仕様書・概算</li><li><span>4</span>開発を相談</li><li><span>5</span>オンラインミーティング</li><li><span>6</span>お見積り・ご入金</li><li><span>7</span>制作</li><li><span>8</span>公開</li></ol>
    <div class="steps">
      <div class="step"><div class="no">1</div><div>
        <div class="hdr"><h3>無料会員登録</h3><span class="when">1分</span></div>
        <p>メールアドレスで登録すると、AIスタジオが使えるようになります。</p>
        <div class="roles"><div class="role you"><b>お客様</b>名前とメールアドレスを入力</div><div class="role us"><b>APPREX</b>AIスタジオを用意</div></div>
      </div></div>
      <div class="step"><div class="no">2</div><div>
        <div class="hdr"><h3>AIでデザイン</h3><span class="when">何度でも無料</span></div>
        <p>業種・店名・色を入れると、AIがアプリの画面を5つ作ります。言葉で頼めば何度でも直せます。</p>
        <div class="roles"><div class="role you"><b>お客様</b>好みに合うまで修正を頼む</div><div class="role us"><b>APPREX</b>AIが画面を作成・修正</div></div>
      </div></div>
      <div class="step"><div class="no">3</div><div>
        <div class="hdr"><h3>AIで仕様書・概算</h3><span class="when">その場で</span></div>
        <p>欲しい機能を選ぶと、AIが仕様書と概算費用をまとめます。</p>
        <div class="roles"><div class="role you"><b>お客様</b>機能を選んで内容を確認</div><div class="role us"><b>APPREX</b>仕様書と概算を自動作成</div></div>
      </div></div>
      <div class="step"><div class="no">4</div><div>
        <div class="hdr"><h3>開発を相談</h3><span class="when">ボタンひとつ</span></div>
        <p>作ったデザインと仕様書をそのまま担当者に送ります。説明し直す必要はありません。</p>
        <div class="roles"><div class="role you"><b>お客様</b>進め方を選んで送信</div><div class="role us"><b>APPREX</b>内容を確認して準備</div></div>
      </div></div>
      <div class="step has-img"><div class="no">5</div><div>
        <div class="hdr"><h3>オンラインミーティング</h3><span class="when">60分</span></div>
        <p>デザインと仕様書を画面で見ながら、細かい要望を伺い、正式なお見積りをお出しします。</p>
        <div class="roles"><div class="role you"><b>お客様</b>空いている日時を選ぶ</div><div class="role us"><b>APPREX</b>要望を整理して見積り作成</div></div>
      </div><img class="sthumb" src="<?php echo esc_url( apprex_rn_img( '10_flow-consult.jpg' ) ); ?>" alt="オンラインミーティングの様子" width="1376" height="768" decoding="async" loading="lazy"></div>
      <div class="step"><div class="no">6</div><div>
        <div class="hdr"><h3>お見積り・ご入金</h3><span class="when">スタジオで完結</span></div>
        <p>届いたお見積りを確認して、クレジットカードか銀行振込でお支払いいただきます。</p>
        <div class="roles"><div class="role you"><b>お客様</b>内容に同意して支払う</div><div class="role us"><b>APPREX</b>入金を確認して制作開始</div></div>
      </div></div>
      <div class="step has-img"><div class="no">7</div><div>
        <div class="hdr"><h3>制作</h3><span class="when">ノーコード2週間〜／オリジナル1ヶ月〜</span></div>
        <p>デザインを仕上げて開発し、テスト版をお手元のスマホで確認していただきます。進み具合はスタジオでいつでも見られます。</p>
        <div class="roles"><div class="role you"><b>お客様</b>テスト版を確認して意見を伝える</div><div class="role us"><b>APPREX</b>開発・テスト・修正</div></div>
      </div><img class="sthumb" src="<?php echo esc_url( apprex_rn_img( '11_flow-test.jpg' ) ); ?>" alt="テスト版のアプリを確認する様子" width="1376" height="768" decoding="async" loading="lazy"></div>
      <div class="step has-img last"><div class="no">8</div><div>
        <div class="hdr"><h3>公開</h3><span class="when">ストア審査1〜2週間</span></div>
        <p>App Store と Google Play の審査を通して公開。公開後も配信や改善をサポートします。</p>
        <div class="roles"><div class="role you"><b>お客様</b>公開日を決める</div><div class="role us"><b>APPREX</b>申請・審査対応と公開後の運用</div></div>
      </div><img class="sthumb" src="<?php echo esc_url( apprex_rn_img( '12_flow-launch.jpg' ) ); ?>" alt="アプリの公開" width="1376" height="768" decoding="async" loading="lazy"></div>
    </div>
    <p class="manual-link"><a href="<?php echo esc_url( apprex_manual_url() ); ?>">画面ごとの操作は「アプリの作り方マニュアル」へ →</a></p>
    <div class="total"><span>公開までの目安：ノーコード <b>最短2週間</b></span><span>オリジナル開発 <b>最短1ヶ月〜</b></span><span>（ストア審査期間を含む）</span></div>
  </div>
</section>
