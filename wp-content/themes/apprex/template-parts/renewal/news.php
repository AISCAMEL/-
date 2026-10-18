<?php
/**
 * リニューアル版トップ：新着ニュース・ブログ。
 *
 * 新着ニュース＝カテゴリー「news」（無ければ最新の投稿）、ブログ・コラム＝それ以外の最新投稿。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_news_cat = get_category_by_slug( 'news' );
$apprex_news     = new WP_Query(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 4,
		'cat'                 => $apprex_news_cat ? $apprex_news_cat->term_id : 0,
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
$apprex_cols = new WP_Query(
	array(
		'post_type'           => 'post',
		'posts_per_page'      => 3,
		'category__not_in'    => $apprex_news_cat ? array( $apprex_news_cat->term_id ) : array(),
		'post__not_in'        => $apprex_news_cat ? array() : wp_list_pluck( $apprex_news->posts, 'ID' ),
		'ignore_sticky_posts' => true,
		'no_found_rows'       => true,
	)
);
if ( ! $apprex_news->have_posts() && ! $apprex_cols->have_posts() ) {
	return;
}
$apprex_fallback = array( '08_way-original.jpg', '09_line-vs-app.jpg', '10_flow-consult.jpg' );
$apprex_blog_url = apprex_page_url( 'blog' );
?>
<section class="sec alt" id="news">
  <div class="wrap">
    <div class="nwrap">
      <?php if ( $apprex_news->have_posts() ) : ?>
      <div>
        <div class="nhead"><h2>新着ニュース</h2><a href="<?php echo esc_url( $apprex_news_cat ? get_category_link( $apprex_news_cat ) : $apprex_blog_url ); ?>">一覧を見る →</a></div>
        <ul class="nl">
          <?php
          while ( $apprex_news->have_posts() ) :
            $apprex_news->the_post();
            $apprex_c = get_the_category();
            ?>
          <li><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?></time><span class="ncat"><?php echo esc_html( $apprex_c ? $apprex_c[0]->name : 'お知らせ' ); ?></span><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
            <?php
          endwhile;
          wp_reset_postdata();
          ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ( $apprex_cols->have_posts() ) : ?>
      <div>
        <div class="nhead"><h2>ブログ・コラム</h2><a href="<?php echo esc_url( $apprex_blog_url ); ?>">一覧を見る →</a></div>
        <div class="ncards">
          <?php
          $apprex_i = 0;
          while ( $apprex_cols->have_posts() ) :
            $apprex_cols->the_post();
            $apprex_c = get_the_category();
            ?>
          <a class="ncard" href="<?php the_permalink(); ?>">
            <span class="nth">
              <?php if ( has_post_thumbnail() ) : ?>
                <?php the_post_thumbnail( 'apprex-card', array( 'alt' => '' ) ); ?>
              <?php else : ?>
                <img src="<?php echo esc_url( apprex_rn_img( $apprex_fallback[ $apprex_i % 3 ] ) ); ?>" alt="" width="1200" height="670" loading="lazy" decoding="async">
              <?php endif; ?>
            </span>
            <span class="nbd"><span class="ncat"><?php echo esc_html( $apprex_c ? $apprex_c[0]->name : 'コラム' ); ?></span><b><?php the_title(); ?></b><time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'Y.m.d' ) ); ?></time></span>
          </a>
            <?php
            $apprex_i++;
          endwhile;
          wp_reset_postdata();
          ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
