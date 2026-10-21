<?php
/**
 * Template Name: アプリの作り方マニュアル
 *
 * 本文は inc/manual.php の apprex_manual_sections()（チャットの知識と共通）。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

$apprex_secs   = apprex_manual_sections();
$apprex_groups = array(
	'studio' => array( 'AIスタジオの使い方', '無料会員登録から公開まで、画面ごとの操作をまとめました。' ),
	'guide'  => array( 'アプリづくりのガイド', '準備するもの、公開後の運用のコツ、困ったときの対処。' ),
);
?>
<div class="rn">
<section class="hero man-hero">
  <div class="wrap">
    <div>
      <span class="badge-top">作り方マニュアル</span>
      <h1>アプリの作り方を、<br><em>画面ごとに</em>やさしく。</h1>
      <p class="lead">AIスタジオでデザインと仕様書を作ってから、相談・制作・公開、そして公開後の運用まで。わからないところは、右下のAIガイドにそのまま聞けます。</p>
      <div class="ctas">
        <a class="btn btn--cta btn--lg" href="<?php echo esc_url( apprex_studio_url() ); ?>"><?php echo is_user_logged_in() ? 'AIスタジオを開く' : '無料会員登録してはじめる'; ?></a>
        <button type="button" class="btn btn--line btn--lg" data-ask="アプリの作り方の流れを教えて">AIガイドに聞く</button>
      </div>
    </div>
  </div>
</section>

<section class="sec man">
  <div class="wrap man-wrap">
    <nav class="man-toc" aria-label="もくじ">
      <?php foreach ( $apprex_groups as $apprex_g => $apprex_gl ) : ?>
        <b><?php echo esc_html( $apprex_gl[0] ); ?></b>
        <ol>
          <?php foreach ( $apprex_secs as $apprex_s ) : ?>
            <?php if ( $apprex_s['group'] === $apprex_g ) : ?>
              <li><a href="#<?php echo esc_attr( $apprex_s['id'] ); ?>"><?php echo esc_html( $apprex_s['title'] ); ?></a></li>
            <?php endif; ?>
          <?php endforeach; ?>
        </ol>
      <?php endforeach; ?>
    </nav>

    <div class="man-body">
      <?php foreach ( $apprex_groups as $apprex_g => $apprex_gl ) : ?>
        <div class="man-group">
          <span class="eyebrow"><?php echo 'studio' === $apprex_g ? 'AI Studio' : 'Guide'; ?></span>
          <h2><?php echo esc_html( $apprex_gl[0] ); ?></h2>
          <p class="man-glead"><?php echo esc_html( $apprex_gl[1] ); ?></p>
        </div>
        <?php
        $apprex_no = 0;
        foreach ( $apprex_secs as $apprex_s ) :
          if ( $apprex_s['group'] !== $apprex_g ) {
            continue;
          }
          $apprex_no++;
          ?>
          <article class="man-sec" id="<?php echo esc_attr( $apprex_s['id'] ); ?>">
            <header><span class="man-no num"><?php echo esc_html( sprintf( '%02d', $apprex_no ) ); ?></span><h3><?php echo esc_html( $apprex_s['title'] ); ?></h3></header>
            <p class="man-lead"><?php echo esc_html( $apprex_s['lead'] ); ?></p>
            <ol class="man-steps">
              <?php foreach ( $apprex_s['steps'] as $apprex_st ) : ?>
                <li><b><?php echo esc_html( $apprex_st[0] ); ?></b><span><?php echo esc_html( $apprex_st[1] ); ?></span></li>
              <?php endforeach; ?>
            </ol>
            <?php if ( $apprex_s['tips'] ) : ?>
              <div class="man-tips"><b>ポイント</b><ul>
                <?php foreach ( $apprex_s['tips'] as $apprex_t ) : ?>
                  <li><?php echo esc_html( $apprex_t ); ?></li>
                <?php endforeach; ?>
              </ul></div>
            <?php endif; ?>
            <?php
            $apprex_pv = function_exists( 'apprex_preview_app_links' ) ? array_filter( apprex_preview_app_links() ) : array();
            if ( $apprex_pv && in_array( $apprex_s['id'], array( 'prod' ), true ) ) :
              ?>
              <p class="man-pv"><b>プレビューアプリ（実機確認）：</b>
                <?php if ( ! empty( $apprex_pv['ios'] ) ) : ?><a href="<?php echo esc_url( $apprex_pv['ios'] ); ?>" target="_blank" rel="noopener">App Store</a><?php endif; ?>
                <?php if ( ! empty( $apprex_pv['android'] ) ) : ?><a href="<?php echo esc_url( $apprex_pv['android'] ); ?>" target="_blank" rel="noopener">Google Play</a><?php endif; ?>
              </p>
            <?php endif; ?>
            <div class="man-ask">
              <span>AIガイドに聞く：</span>
              <?php foreach ( $apprex_s['quick'] as $apprex_q ) : ?>
                <button type="button" data-ask="<?php echo esc_attr( $apprex_q ); ?>"><?php echo esc_html( $apprex_q ); ?></button>
              <?php endforeach; ?>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endforeach; ?>

      <div class="man-end">
        <h3>まずは、AIスタジオで作ってみましょう</h3>
        <p>登録・デザイン・仕様書・相談・ミーティングまで無料です。</p>
        <a class="btn btn--cta btn--lg" href="<?php echo esc_url( apprex_studio_url() ); ?>"><?php echo is_user_logged_in() ? 'AIスタジオを開く' : '無料会員登録してはじめる'; ?></a>
      </div>
    </div>
  </div>
</section>
</div>
<?php
// HowTo 構造化データ（AIスタジオの使い方）。
$apprex_howto = array(
	'@context' => 'https://schema.org',
	'@type'    => 'HowTo',
	'name'     => 'AIスタジオでアプリを作る方法',
	'step'     => array(),
);
foreach ( $apprex_secs as $apprex_s ) {
	if ( 'studio' === $apprex_s['group'] ) {
		$apprex_howto['step'][] = array(
			'@type' => 'HowToStep',
			'name'  => $apprex_s['title'],
			'text'  => $apprex_s['lead'],
			'url'   => apprex_manual_url( $apprex_s['id'] ),
		);
	}
}
echo '<script type="application/ld+json">' . wp_json_encode( $apprex_howto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";

get_footer();
