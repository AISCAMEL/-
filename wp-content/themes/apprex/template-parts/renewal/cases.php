<?php
/**
 * リニューアル版トップ：制作事例。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<section class="sec alt" id="cases">
  <div class="wrap">
    <div class="sh"><span class="eyebrow">Results</span><h2>導入企業の成果</h2></div>
    <div class="real">
      <div class="real-ph"><img src="<?php echo esc_url( apprex_rn_img( 'real-zensin.webp' ) ); ?>" alt="ZENSIN様の公式アプリのホーム画面" width="925" height="2000" loading="lazy" decoding="async"></div>
      <div class="real-txt">
        <span class="real-tag">実際の制作事例</span>
        <h3>ZENSIN様 公式アプリ</h3>
        <p>「魅せ方で価値を変える」を掲げる企業の公式アプリです。ブランドカラーのオレンジで画面全体を統一し、よく使う6つの機能にホーム画面からすぐ届く構成にしました。</p>
        <div class="real-feat" aria-label="使っている機能"><span>スタンプ</span><span>予約</span><span>お問い合わせ</span><span>クーポン</span><span>ポイント</span><span>友達紹介</span><span>NEWS</span><span>カタログ</span><span>動画</span></div>
        <ul class="real-pt"><li>トップに大きなメインビジュアルを置き、会社の世界観を最初に伝える</li><li>大きなボタンで、どの世代でも迷わず押せる</li><li>下のタブからNEWS・カタログ・動画にいつでも移動できる</li></ul>
        <a class="btn btn--cta" href="<?php echo esc_url( apprex_studio_url() ); ?>">このようなアプリをAIスタジオで作る</a>
      </div>
    </div>
    <?php
    // 管理画面「導入事例」に登録された事例（アイキャッチ＝アプリ画面のスクリーンショット）を表示。
    $apprex_cases = new WP_Query(
      array(
        'post_type'      => 'case',
        'posts_per_page' => 10,
        'orderby'        => array( 'menu_order' => 'ASC', 'date' => 'DESC' ),
        'meta_query'     => array( array( 'key' => '_thumbnail_id', 'compare' => 'EXISTS' ) ),
        'no_found_rows'  => true,
      )
    );
    if ( $apprex_cases->have_posts() ) :
      ?>
    <h3 class="gal-h">ほかにも、こんなアプリを作っています</h3>
    <div class="gal">
      <?php
      while ( $apprex_cases->have_posts() ) :
        $apprex_cases->the_post();
        $apprex_ind = apprex_field( 'case_industry' );
        ?>
      <figure class="gal-i">
        <a href="<?php the_permalink(); ?>"><?php the_post_thumbnail( 'large', array( 'class' => 'mk-img', 'alt' => esc_attr( get_the_title() . 'のアプリ画面' ) ) ); ?></a>
        <figcaption><b><?php the_title(); ?></b><?php if ( $apprex_ind ) : ?><span><?php echo esc_html( $apprex_ind ); ?></span><?php endif; ?></figcaption>
      </figure>
        <?php
      endwhile;
      wp_reset_postdata();
      ?>
    </div>
    <?php endif; ?>
    <div style="text-align:center;margin-top:18px"><a class="btn btn--line btn--lg" href="<?php echo esc_url( get_post_type_archive_link( 'case' ) ); ?>">制作事例をもっと見る</a></div>
  </div>
</section>
