<?php
/**
 * リニューアル版トップ：資料請求フォーム。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="band" id="lead">
  <div class="wrap">
    <div>
      <img class="docimg" src="<?php echo esc_url( apprex_rn_img( '06_doc-cover.jpg' ) ); ?>" alt="アプリ開発の料金・事例ガイド（資料）" width="896" height="1200" decoding="async" loading="lazy">
      <h2>料金表と事例をまとめた資料を、無料でお送りします。</h2>
      <ul>
        <li>ノーコード／オリジナル開発の料金表</li>
        <li>業種別の導入事例と成果</li>
        <li>開発の流れと期間の目安</li>
        <li>補助金を使った導入方法</li>
      </ul>
    </div>
    <?php /* 送信は既存のフォーム基盤（REST /inquiry → 自動返信・通知・CRM・広告計測）を利用。 */ ?>
    <form class="formcard" id="leadForm" data-apprex-form data-type="document">
      <h3>無料で資料を受け取る</h3>
      <div class="seg" role="radiogroup" aria-label="ご用件" style="margin-top:12px">
        <label><input type="radio" name="kind" value="document" checked><span>資料がほしい</span></label>
        <label><input type="radio" name="kind" value="contact"><span>見積り相談したい</span></label>
      </div>
      <div class="fgrid">
        <input type="text" name="name" autocomplete="name" placeholder="お名前" aria-label="お名前" required>
        <input type="text" name="company" autocomplete="organization" placeholder="会社名" aria-label="会社名">
      </div>
      <div class="fgrid">
        <input type="email" name="email" autocomplete="email" inputmode="email" placeholder="メールアドレス" aria-label="メールアドレス" required>
        <select name="message" aria-label="作りたいアプリ">
          <option value="">作りたいアプリ（任意）</option><option>作りたいアプリ：店舗・会員アプリ</option><option>作りたいアプリ：予約アプリ</option><option>作りたいアプリ：EC・オーダー</option><option>作りたいアプリ：マッチングアプリ</option><option>作りたいアプリ：業務アプリ</option><option>作りたいアプリ：その他・未定</option>
        </select>
      </div>
      <button class="btn btn--cta btn--lg" type="submit" data-label-document="資料を受け取る（無料）" data-label-contact="見積りを相談する（無料）">資料を受け取る（無料）</button>
      <p class="fnote">1分で完了します。しつこい営業はしません。<?php if ( get_privacy_policy_url() ) : ?><br>送信により<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">プライバシーポリシー</a>に同意したものとします。<?php endif; ?></p>
      <div class="apprex-form__result" role="status" hidden></div>
    </form>
  </div>
</section>
