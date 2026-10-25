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

$apprex_mode = function_exists( 'apprex_studio_mode' ) ? apprex_studio_mode() : 'app';
$apprex_here = apprex_studio_url( $apprex_mode );
$apprex_copy = array(
	'app'      => array(
		'badge' => '無料会員登録で使える／相談まで費用0円',
		'h1'    => 'AIが、あなたのアプリの<br><em>デザインと仕様書</em>を作ります。',
		'lead'  => '業種と店名を入れるだけで、アプリの画面を5つデザイン。機能を選べば、企画書・仕様書・画面構成図と概算費用までその場でできあがります。満足したら、そのまま開発の相談とオンラインミーティングへ。',
		'pts'   => array( 'アプリの画面デザインを5画面まとめて作成。言葉で頼むだけで何度でも修正', '企画書・仕様書・画面構成図と概算費用を自動で作成', 'スマホで触れるデモアプリで、動きを確かめられる', '相談・ミーティング予約・お見積り・制作の進捗まで、ここで完結', '作った内容をそのまま形にすると、初期費用は15万円〜（1から作る場合の半分）' ),
		'btn'   => '無料で登録してAIスタジオを使う',
		'flow'  => array( '無料会員登録', 'AIでデザイン', 'AIで仕様書・概算', '開発を相談', 'オンラインミーティング', 'お見積り・ご入金', '制作', '公開' ),
		'brand' => 'AIスタジオ',
	),
	'hp'       => array(
		'badge' => 'AIプランナー／制作費0円',
		'h1'    => 'AIプランナーで作れば、<br><em>制作費0円。</em>月々だけ。',
		'lead'  => '店名・業種・要望を書くだけで、AIプランナーがページ構成・文章・色を考えて、実際に見られるホームページを作ります。そのまま制作に進めて、制作費はかかりません。お支払いは月額9,800円〜だけです。',
		'pts'   => array( 'トップ・サービス・会社概要・お問い合わせまで、見られる試作をすぐに作成', 'キャッチコピー・紹介文・よくある質問まで、業種に合わせて文章を作成', '「スタッフ紹介を追加」のように頼むだけで作り直し。写真もAIで作れる', 'サイトマップ・必要な機能・検索で見つけてほしい言葉をまとめた仕様書つき', 'AIプランナーで作ったホームページは制作費0円。お支払いは月額9,800円〜だけ' ),
		'btn'   => '無料で登録してAIプランナーを使う',
		'flow'  => array( '無料会員登録', 'AIプランナーで作る', 'サイトマップ・仕様書', '制作を相談', 'オンラインミーティング', 'お見積り・ご契約', '制作', '公開' ),
		'brand' => 'AIプランナー ホームページ',
	),
	'matching' => array(
		'badge' => 'マッチングアプリ版／無料会員登録で使える',
		'h1'    => 'AIが、あなたの<br><em>マッチングアプリの試作</em>を作ります。',
		'lead'  => '作りたいサービスを書くだけで、AIが画面・機能・データまで考えて、スマホで触れる試作と仕様定義書を作ります。マッチング以外のオリジナルアプリも作れます。',
		'pts'   => array( '画面をタップして流れを確かめられる試作を作成（スマホでも）', '機能一覧・画面ごとの仕様・データ項目をまとめた仕様定義書つき', '「決済を入れて」「管理者の画面を追加」のように頼むだけで作り直し', '概算の目安（費用・期間）もその場でわかる', 'マッチングアプリは開発費300万円〜・3ヶ月〜' ),
		'btn'   => '無料で登録してアプリの試作を作る',
		'flow'  => array( '無料会員登録', 'AIで試作', '仕様定義書・概算', '開発を相談', 'オンラインミーティング', 'お見積り・ご契約', '開発', '公開' ),
		'brand' => 'AIスタジオ マッチングアプリ',
	),
);
$apprex_c = $apprex_copy[ $apprex_mode ];

if ( ! is_user_logged_in() ) :
	$apprex_err = isset( $GLOBALS['apprex_login_error'] ) ? $GLOBALS['apprex_login_error'] : '';
	?>
<div class="rn">
<section class="hero studio-entry">
  <div class="wrap">
    <div>
      <span class="badge-top"><?php echo esc_html( $apprex_c['badge'] ); ?></span>
      <h1><?php echo wp_kses( $apprex_c['h1'], array( 'br' => array(), 'em' => array() ) ); ?></h1>
      <p class="lead"><?php echo esc_html( $apprex_c['lead'] ); ?></p>
      <ul class="entry-pts">
        <?php foreach ( $apprex_c['pts'] as $apprex_pt ) : ?><li><?php echo esc_html( $apprex_pt ); ?></li><?php endforeach; ?>
      </ul>
      <p class="studio-switch">ほかのAIスタジオ：<?php foreach ( array( 'app' => 'アプリ', 'hp' => 'ホームページ', 'matching' => 'マッチングアプリ' ) as $apprex_k => $apprex_l ) : ?><?php if ( $apprex_k !== $apprex_mode ) : ?><a href="<?php echo esc_url( apprex_studio_url( $apprex_k ) ); ?>"><?php echo esc_html( $apprex_l ); ?></a><?php endif; ?><?php endforeach; ?></p>
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
        <button class="btn btn--cta btn--lg" type="submit"><?php echo esc_html( $apprex_c['btn'] ); ?></button>
        <p class="fnote">登録・デザイン・相談・ミーティングまで無料です。<?php if ( get_privacy_policy_url() ) : ?><br>登録により<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>">プライバシーポリシー</a>に同意したものとします。<?php endif; ?></p>
        <div class="reg-err" role="alert" hidden></div>
      </form>
      <details class="loginbox"<?php echo $apprex_err ? ' open' : ''; ?>>
        <summary>登録済みの方はこちら（ログイン）</summary>
        <?php if ( $apprex_err ) : ?><p class="reg-err"><?php echo esc_html( $apprex_err ); ?></p><?php endif; ?>
        <form method="post" action="<?php echo esc_url( $apprex_here ); ?>">
          <input type="hidden" name="apprex_member_login" value="1">
          <input type="hidden" name="apprex_login_nonce" value="<?php echo esc_attr( wp_create_nonce( 'apprex_member_login' ) ); ?>">
          <input type="hidden" name="apprex_redirect" value="<?php echo esc_url( $apprex_here ); ?>">
          <input type="hidden" name="rememberme" value="1">
          <div class="fgrid">
            <input type="text" name="log" autocomplete="username" placeholder="メールアドレス" aria-label="メールアドレス" required>
            <input type="password" name="pwd" autocomplete="current-password" placeholder="パスワード" aria-label="パスワード" required>
          </div>
          <button class="btn btn--line btn--lg" type="submit">ログイン</button>
          <p class="fnote"><a href="<?php echo esc_url( wp_lostpassword_url( $apprex_here ) ); ?>">パスワードをお忘れの方</a></p>
        </form>
      </details>
    </div>
  </div>
</section>
<section class="sec">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">How it works</span><h2>相談から公開までの流れ</h2></div>
    <ol class="st-flow" aria-label="相談から公開までの流れ"><?php foreach ( $apprex_c['flow'] as $apprex_i => $apprex_f ) : ?><li><span><?php echo (int) $apprex_i + 1; ?></span><?php echo esc_html( $apprex_f ); ?></li><?php endforeach; ?></ol>
  </div>
</section>
</div>
	<?php
else :
	$apprex_user = wp_get_current_user();
	?>
<div class="rn-studio">
  <header class="top"><div class="top-in">
    <div class="brand">APP<b>REX</b><small><?php echo esc_html( $apprex_c['brand'] ); ?></small></div>
    <div class="top-r">
      <span class="saved" id="savedAt" aria-live="polite"></span>
      <span class="user"><?php echo esc_html( $apprex_user->display_name ); ?> 様（無料会員）</span>
      <?php if ( 'app' === $apprex_mode ) : ?><button type="button" id="reset">デザインを最初から</button><?php endif; ?>
      <a href="<?php echo esc_url( apprex_mypage_url() ); ?>">マイページ</a>
      <a href="<?php echo esc_url( wp_logout_url( $apprex_here ) ); ?>">ログアウト</a>
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
