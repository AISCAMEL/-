<?php
/**
 * リニューアル版トップ：ヒーロー。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="hero">
  <div class="wrap">
    <div>
      <span class="badge-top rise">ノーコードからオリジナル開発まで ／ 全国オンライン対応</span>
      <h1 class="rise d1">アプリ開発を、<br><em>格安・短納期</em>で。<br>オリジナルアプリも。</h1>
      <p class="lead rise d2">月額19,800円〜のノーコードアプリから、自社専用のオリジナルアプリ（iOS／Android同時開発）まで。APPREXの開発基盤を使うので、同じアプリを低コスト・短期間でつくれます。</p>
      <div class="chips rise d3">
        <span class="chip">デザイン・仕様書はAIで無料</span>
        <span class="chip">月額 19,800円〜</span>
        <span class="chip">最短 2週間で公開</span>
        <span class="chip">ストア申請まで代行</span>
        <span class="chip">オリジナル開発に強い</span>
      </div>
      <div class="ctas rise d3">
        <a class="btn btn--cta btn--lg pulse" href="#lead">料金がわかる資料をもらう</a>
        <a class="btn btn--line btn--lg" href="<?php echo esc_url( apprex_studio_url() ); ?>">無料会員登録でAIデザイン</a>
      </div>
    </div>
    <div style="min-width:0">
      <div class="stage stage--img">
        <div class="hero-img"><img src="<?php echo esc_url( apprex_rn_img( '01_hero-phones.jpg' ) ); ?>" alt="飲食店アプリとマッチングアプリの画面イメージ" width="1200" height="896" decoding="async" loading="eager" fetchpriority="high"></div>
        <div class="notif"><b>CAFE MOMO</b>本日限定！ドリンク半額クーポンが届きました</div>
      </div>
    </div>
  </div>
</section>
