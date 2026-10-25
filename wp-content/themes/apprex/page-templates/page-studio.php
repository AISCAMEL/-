<?php
/**
 * Template Name: AIスタジオ（会員）
 *
 * ログイン前：無料会員登録・ログイン画面。ログイン後：AIスタジオ本体（assets/js/studio.js）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

if ( ! is_user_logged_in() ) :
	$apprex_err = isset( $GLOBALS['apprex_login_error'] ) ? $GLOBALS['apprex_login_error'] : '';
	?>
<div class="rn">
<section class="hero studio-entry">
  <div class="wrap">
    <div>
      <span class="badge-top">無料会員登録で使える／相談まで費用0円</span>
      <h1>AIが、あなたのアプリの<br><em>デザインと仕様書</em>を作ります。</h1>
      <p class="lead">業種と店名を入れるだけで、アプリの画面を5つデザイン。機能を選べば、企画書・仕様書・画面構成図と概算費用までその場でできあがります。満足したら、そのまま開発の相談とオンラインミーティングへ。</p>
      <ul class="entry-pts">
        <li>アプリの画面デザインを5画面まとめて作成。言葉で頼むだけで何度でも修正</li>
        <li>企画書・仕様書・画面構成図と概算費用を自動で作成</li>
        <li>ホームページもAIで試作。ページ構成と文章まで作ります</li>
        <li>相談・ミーティング予約・お見積り・制作の進捗まで、ここで完結</li>
        <li>作った内容をそのまま形にすると、初期費用は15万円〜（1から作る場合の半分）</li>
      </ul>
    </div>
    <div class="entry-forms">
      <form class="formcard" id="studioReg" novalidate>
        <h3>無料会員登録（1分）</h3>
        <div class="fgrid">
          <input type="text" name="company" autocomplete="organization" placeholder="会社名・店名" aria-label="会社名・店名">
          <input type="text" name="name" autocomplete="name" placeholder="お名前" aria-label="お名前" required>
        </div>
        <div class="fgrid">
          <input type="email" name="email" autocomplete="email" inputmode="email" placeholder="メールアドレス" aria-label="メールアドレス" required>
          <input type="password" name="password" autocomplete="new-password" placeholder="パスワード（8文字以上）" aria-label="パスワード（8文字以上）" minlength="8" required>
        </div>
        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" class="hp-field">
        <button class="btn btn--cta btn--lg" type="submit">無料で登録してAIスタジオを使う</button>
        <p class="fnote">登録・デザイン・相談・ミーティングまで無料です。<?php if ( get_privacy_policy_url() ) : ?><br>登録により<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">プライバシーポリシー</a>に同意したものとします。<?php endif; ?></p>
        <div class="reg-err" role="alert" hidden></div>
      </form>
      <details class="loginbox"<?php echo $apprex_err ? ' open' : ''; ?>>
        <summary>登録済みの方はこちら（ログイン）</summary>
        <?php if ( $apprex_err ) : ?><p class="reg-err"><?php echo esc_html( $apprex_err ); ?></p><?php endif; ?>
        <form method="post" action="<?php echo esc_url( apprex_studio_url() ); ?>">
          <input type="hidden" name="apprex_member_login" value="1">
          <input type="hidden" name="apprex_login_nonce" value="<?php echo esc_attr( wp_create_nonce( 'apprex_member_login' ) ); ?>">
          <input type="hidden" name="apprex_redirect" value="<?php echo esc_url( apprex_studio_url() ); ?>">
          <input type="hidden" name="rememberme" value="1">
          <div class="fgrid">
            <input type="text" name="log" autocomplete="username" placeholder="メールアドレス" aria-label="メールアドレス" required>
            <input type="password" name="pwd" autocomplete="current-password" placeholder="パスワード" aria-label="パスワード" required>
          </div>
          <button class="btn btn--line btn--lg" type="submit">ログイン</button>
          <p class="fnote"><a href="<?php echo esc_url( wp_lostpassword_url( apprex_studio_url() ) ); ?>">パスワードをお忘れの方</a></p>
        </form>
      </details>
    </div>
  </div>
</section>
<section class="sec">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">How it works</span><h2>相談から公開までの流れ</h2></div>
    <ol class="st-flow" aria-label="相談から公開までの流れ"><li><span>1</span>無料会員登録</li><li><span>2</span>AIでデザイン</li><li><span>3</span>AIで仕様書・概算</li><li><span>4</span>開発を相談</li><li><span>5</span>オンラインミーティング</li><li><span>6</span>お見積り・ご入金</li><li><span>7</span>制作</li><li><span>8</span>公開</li></ol>
  </div>
</section>
</div>
	<?php
else :
	$apprex_user = wp_get_current_user();
	?>
<div class="rn-studio">
  <header class="top"><div class="top-in">
    <div class="brand">APP<b>REX</b><small>AIスタジオ</small></div>
    <div class="top-r">
      <span class="saved" id="savedAt" aria-live="polite"></span>
      <span class="user"><?php echo esc_html( $apprex_user->display_name ); ?> 様（無料会員）</span>
      <button type="button" id="reset">デザインを最初から</button>
      <a href="<?php echo esc_url( apprex_mypage_url() ); ?>">マイページ</a>
      <a href="<?php echo esc_url( wp_logout_url( apprex_studio_url() ) ); ?>">ログアウト</a>
    </div>
  </div></header>
  <nav class="prog" aria-label="進み具合"><ol id="prog"></ol></nav>
  <div class="app">
    <aside class="side" id="side" aria-label="工程"></aside>
    <div class="main" id="stMain"><noscript>AIスタジオを使うには JavaScript を有効にしてください。</noscript></div>
  </div>
</div>
	<?php
endif;

get_footer();
