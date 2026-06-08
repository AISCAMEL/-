<?php
/**
 * 07. Cases — latest case studies from the CPT, spec §6/§8.
 *
 * Falls back to the spec's reference table (§7) when no cases are registered
 * yet, so the section never renders empty during build.
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_cases = new WP_Query(
	array(
		'post_type'           => 'case',
		'posts_per_page'      => 6,
		'no_found_rows'       => true,
		'ignore_sticky_posts' => true,
	)
);
?>
<section class="section" id="cases">
	<div class="container">
		<?php apprex_section_head( 'Cases', __( '9業種8,000社以上の導入実績', 'apprex' ), __( '業種を問わず、成果につながるアプリを実現しています。', 'apprex' ) ); ?>

		<?php if ( $apprex_cases->have_posts() ) : ?>
			<div class="grid grid--3">
				<?php
				while ( $apprex_cases->have_posts() ) :
					$apprex_cases->the_post();
					get_template_part( 'template-parts/case-card' );
				endwhile;
				wp_reset_postdata();
				?>
			</div>
		<?php else : ?>
			<?php
			// Placeholder grid from spec §7 until the CPT is populated.
			$apprex_seed = array(
				array( 'industry' => __( 'アパレルブランド', 'apprex' ), 'metric' => __( '売上+150%', 'apprex' ), 'duration' => __( '2週間', 'apprex' ) ),
				array( 'industry' => __( 'イタリアンレストラン', 'apprex' ), 'metric' => __( '人件費-30%', 'apprex' ), 'duration' => __( '1週間', 'apprex' ) ),
				array( 'industry' => __( 'フィットネスジム', 'apprex' ), 'metric' => __( '継続率+40%', 'apprex' ), 'duration' => __( '2週間', 'apprex' ) ),
				array( 'industry' => __( 'オンライン学習塾', 'apprex' ), 'metric' => __( '学習時間+50%', 'apprex' ), 'duration' => __( '3週間', 'apprex' ) ),
				array( 'industry' => __( '美容サロン', 'apprex' ), 'metric' => __( 'キャンセル-80%', 'apprex' ), 'duration' => __( '10日間', 'apprex' ) ),
				array( 'industry' => __( '不動産会社', 'apprex' ), 'metric' => __( '問合せ+70%', 'apprex' ), 'duration' => __( '3週間', 'apprex' ) ),
			);
			?>
			<div class="grid grid--3">
				<?php foreach ( $apprex_seed as $seed ) : ?>
					<div class="case-card is-reveal">
						<span class="case-card__thumb" aria-hidden="true"></span>
						<div class="case-card__body">
							<span class="case-card__industry"><?php echo esc_html( $seed['industry'] ); ?></span>
							<h3 class="case-card__title"><?php echo esc_html( $seed['industry'] ); ?>の導入事例</h3>
							<div class="case-card__metrics">
								<span class="tag"><?php echo esc_html( $seed['metric'] ); ?></span>
								<span class="tag tag--accent"><?php echo esc_html( $seed['duration'] ); ?></span>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="text-center mt-32 is-reveal">
			<a class="btn btn--ghost" href="<?php echo esc_url( home_url( '/cases' ) ); ?>"><?php esc_html_e( '導入事例一覧へ', 'apprex' ); ?></a>
		</div>
	</div>
</section>
