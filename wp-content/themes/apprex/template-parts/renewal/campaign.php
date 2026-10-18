<?php
/**
 * リニューアル版トップ：キャンペーン。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="camp" id="campaign">
  <div class="wrap">
    <img class="camp-img" src="<?php echo esc_url( apprex_rn_img( '02_hero-owner.jpg' ) ); ?>" alt="スマホで自店のアプリを確認する店舗オーナー" width="1200" height="896" decoding="async" loading="lazy">
    <div>
      <span class="ctag">今月のキャンペーン</span>
      <h2>AIスタジオから相談すると、おまかせ制作が半額に。</h2>
      <p>AIスタジオでデザインと仕様書を作って相談した方は、おまかせプランの制作代行費が 300,000円 → <b>150,000円</b>。初期設定費も0円です。</p>
      <small>毎月先着10社まで。アプリストア登録費は対象外です。</small>
    </div>
    <div class="acts">
      <a class="btn btn--cta btn--lg" href="<?php echo esc_url( apprex_studio_url() ); ?>">無料会員登録してはじめる</a>
      <a class="btn btn--line" href="#lead">資料で詳しく見る</a>
    </div>
  </div>
</section>
