<?php
/**
 * Franchise-facing content: announcements, manuals/materials, and FAQ.
 *
 * HQ authors entries as the carmel_content CPT (wp-admin), tagging each with a
 * content_type (notice / manual / faq). Franchises view them via the
 * [carmel_store_content] shortcode on /store, and the latest pinned notices are
 * surfaced on the store dashboard. Publishing a notice flagged "notify" sends a
 * broadcast to all franchise users through the notification orchestrator.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Store_Content {

	/** @var Carmel_Store_Content|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_store_content';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		// スタートガイドへの導線をダッシュボード最上部に。
		add_action( 'carmel_store_dashboard_top', array( $this, 'render_dashboard_guide' ), 5 );
		// Surface latest notices at the top of the store dashboard.
		add_action( 'carmel_store_dashboard_top', array( $this, 'render_dashboard_notices' ) );
		// Broadcast a notice to franchises when published with the notify flag.
		add_action( 'transition_post_status', array( $this, 'maybe_notify_on_publish' ), 10, 3 );
	}

	/**
	 * Whether the current user may view franchise content.
	 *
	 * @return bool
	 */
	private function can_view() {
		return is_user_logged_in() && (
			current_user_can( 'carmel_change_deal_status' ) || current_user_can( 'carmel_manage_stores' )
		);
	}

	/**
	 * Query content of a given type (pinned first, newest first).
	 *
	 * @param string $type
	 * @param int    $limit
	 * @return WP_Post[]
	 */
	private function get_content( $type, $limit = 50 ) {
		return get_posts(
			array(
				'post_type'      => 'carmel_content',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => 'pinned',
				'orderby'        => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'content_type', 'value' => $type ),
				),
			)
		);
	}

	/**
	 * スタートガイド（始め方）を step_order 順で取得。
	 *
	 * @param int $limit
	 * @return WP_Post[]
	 */
	private function get_guides( $limit = 50 ) {
		return get_posts(
			array(
				'post_type'      => 'carmel_content',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => 'step_order',
				'orderby'        => array( 'meta_value_num' => 'ASC', 'date' => 'ASC' ),
				'meta_query'     => array(
					array( 'key' => 'content_type', 'value' => 'guide' ),
				),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * Dashboard guide pointer (top of /store)
	 * --------------------------------------------------------------------- */

	public function render_dashboard_guide() {
		$guides = $this->get_guides();
		if ( empty( $guides ) ) {
			return;
		}
		$slug = home_url( '/' . ltrim( apply_filters( 'carmel_store_content_page_slug', 'store-content' ), '/' ) );
		echo '<div class="carmel-guide-cta">';
		echo '<div class="carmel-guide-cta-main"><span class="carmel-guide-badge">はじめての方へ</span> ';
		echo '<strong>始め方マニュアル（スタートガイド）</strong>　全' . (int) count( $guides ) . 'ステップで基本操作をご案内します。</div>';
		echo '<a class="carmel-btn carmel-btn-purple" style="text-decoration:none;background:#6b4fbb;color:#fff;border-radius:.3em;padding:.5em 1.1em" href="' . esc_url( $slug ) . '">スタートガイドを開く</a>';
		echo '</div>';
	}

	/* --------------------------------------------------------------------- *
	 * Dashboard notices (top of /store)
	 * --------------------------------------------------------------------- */

	public function render_dashboard_notices() {
		$notices = $this->get_content( 'notice', 3 );
		if ( empty( $notices ) ) {
			return;
		}
		echo '<div class="carmel-notices">';
		echo '<h3>📢 本部からのお知らせ</h3><ul class="carmel-notice-list">';
		foreach ( $notices as $n ) {
			$pinned  = get_post_meta( $n->ID, 'pinned', true );
			$summary = get_post_meta( $n->ID, 'summary', true );
			echo '<li>';
			if ( $pinned ) {
				echo '<span class="carmel-pin">重要</span> ';
			}
			echo '<span class="carmel-notice-date">' . esc_html( get_the_date( 'Y-m-d', $n->ID ) ) . '</span> ';
			echo '<strong>' . esc_html( get_the_title( $n->ID ) ) . '</strong>';
			if ( $summary ) {
				echo '<span class="carmel-notice-sum"> — ' . esc_html( $summary ) . '</span>';
			}
			echo '</li>';
		}
		echo '</ul></div>';
	}

	/* --------------------------------------------------------------------- *
	 * Full content page [carmel_store_content]
	 * --------------------------------------------------------------------- */

	public function render() {
		if ( ! $this->can_view() ) {
			return '<p class="carmel-notice">加盟店コンテンツを表示する権限がありません。</p>';
		}

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-content">';

		echo $this->guide_section();   // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->notices_section(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->manuals_section(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->faq_section();     // phpcs:ignore WordPress.Security.EscapeOutput

		echo '</div>';
		return ob_get_clean();
	}

	private function guide_section() {
		$items = $this->get_guides();
		$out   = '<section class="carmel-guide-sec"><h2>🚀 始め方マニュアル（スタートガイド）</h2>';
		if ( empty( $items ) ) {
			return $out . '<p>スタートガイドはありません。</p></section>';
		}
		$out .= '<p class="carmel-guide-lead">基本操作をステップ順にまとめています。上から順にご確認ください。</p>';
		$out .= '<ol class="carmel-guide-list">';
		foreach ( $items as $i => $g ) {
			$summary = get_post_meta( $g->ID, 'summary', true );
			$open    = ( 0 === $i ) ? ' open' : '';
			$out    .= '<li class="carmel-guide-step">';
			$out    .= '<details' . $open . '><summary><span class="carmel-step-no">' . ( $i + 1 ) . '</span>'
				. '<span class="carmel-step-ttl">' . esc_html( get_the_title( $g->ID ) ) . '</span>';
			if ( $summary ) {
				$out .= '<span class="carmel-step-sum">' . esc_html( $summary ) . '</span>';
			}
			$out .= '</summary>';
			$out .= '<div class="carmel-guide-body">' . wp_kses_post( wpautop( get_post_field( 'post_content', $g->ID ) ) ) . '</div>';
			$out .= '</details></li>';
		}
		$out .= '</ol></section>';
		return $out;
	}

	private function notices_section() {
		$items = $this->get_content( 'notice' );
		$out   = '<section><h2>📢 お知らせ</h2>';
		if ( empty( $items ) ) {
			return $out . '<p>お知らせはありません。</p></section>';
		}
		foreach ( $items as $n ) {
			$pinned = get_post_meta( $n->ID, 'pinned', true );
			$out   .= '<article class="carmel-c-card">';
			$out   .= '<div class="carmel-c-head">';
			if ( $pinned ) {
				$out .= '<span class="carmel-pin">重要</span> ';
			}
			$out   .= '<span class="carmel-notice-date">' . esc_html( get_the_date( 'Y-m-d', $n->ID ) ) . '</span>';
			$out   .= '<h3>' . esc_html( get_the_title( $n->ID ) ) . '</h3></div>';
			$out   .= '<div class="carmel-c-body">' . wp_kses_post( wpautop( get_post_field( 'post_content', $n->ID ) ) ) . '</div>';
			$out   .= '</article>';
		}
		return $out . '</section>';
	}

	private function manuals_section() {
		$items = $this->get_content( 'manual' );
		$out   = '<section><h2>📚 マニュアル・資料</h2>';
		if ( empty( $items ) ) {
			return $out . '<p>資料はありません。</p></section>';
		}
		$out .= '<ul class="carmel-doc-list">';
		foreach ( $items as $m ) {
			$file    = get_post_meta( $m->ID, 'file_url', true );
			$summary = get_post_meta( $m->ID, 'summary', true );
			$out    .= '<li><div class="carmel-doc-main"><strong>' . esc_html( get_the_title( $m->ID ) ) . '</strong>';
			if ( $summary ) {
				$out .= '<span class="carmel-doc-sum">' . esc_html( $summary ) . '</span>';
			}
			$out .= '</div>';
			if ( $file ) {
				$out .= '<a class="carmel-btn carmel-btn-blue" href="' . esc_url( $file ) . '" target="_blank" rel="noopener">開く</a>';
			}
			$out .= '</li>';
		}
		return $out . '</ul></section>';
	}

	private function faq_section() {
		$items = $this->get_content( 'faq' );
		$out   = '<section><h2>❓ よくある質問</h2>';
		if ( empty( $items ) ) {
			return $out . '<p>FAQはありません。</p></section>';
		}
		foreach ( $items as $f ) {
			$out .= '<details class="carmel-faq"><summary>' . esc_html( get_the_title( $f->ID ) ) . '</summary>';
			$out .= '<div class="carmel-faq-a">' . wp_kses_post( wpautop( get_post_field( 'post_content', $f->ID ) ) ) . '</div></details>';
		}
		return $out . '</section>';
	}

	/* --------------------------------------------------------------------- *
	 * Publish broadcast
	 * --------------------------------------------------------------------- */

	/**
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function maybe_notify_on_publish( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || 'carmel_content' !== $post->post_type ) {
			return;
		}
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return; // only on first publish
		}
		if ( ! get_post_meta( $post->ID, 'notify_stores', true ) ) {
			return;
		}

		Carmel_Notifier::notify(
			'store_notice',
			array(
				'event_id' => 'store_notice:' . $post->ID,
				'vars'     => array(
					'title'   => get_the_title( $post->ID ),
					'summary' => (string) get_post_meta( $post->ID, 'summary', true ),
				),
			)
		);
	}

	private function styles() {
		return '<style>
.carmel-content{font-size:14px;max-width:760px}
.carmel-content section{margin-bottom:2em}
.carmel-content h2{border-bottom:2px solid var(--carmel-line,#e7e2ef);padding-bottom:.3em}
.carmel-c-card{border:1px solid #e7e2ef;border-radius:12px;padding:1em 1.2em;margin:.8em 0;background:#fff}
.carmel-c-head h3{margin:.3em 0 0}
.carmel-pin{background:#c0392b;color:#fff;border-radius:.3em;padding:.05em .5em;font-size:.75em;font-weight:bold}
.carmel-notice-date{color:#9298a5;font-size:.85em}
.carmel-doc-list{list-style:none;padding:0;margin:0}
.carmel-doc-list li{display:flex;align-items:center;justify-content:space-between;gap:1em;border:1px solid #e7e2ef;border-radius:10px;padding:.8em 1em;margin:.6em 0;background:#fff}
.carmel-doc-main{display:flex;flex-direction:column;gap:.2em}
.carmel-doc-sum{color:#7a7488;font-size:.85em}
.carmel-faq{border:1px solid #e7e2ef;border-radius:10px;padding:.4em 1em;margin:.5em 0;background:#fff}
.carmel-faq summary{cursor:pointer;font-weight:700;padding:.5em 0}
.carmel-faq-a{color:#46414f;padding:.2em 0 .6em}
.carmel-notices{background:#f6f2fb;border:1px solid #e7e2ef;border-radius:12px;padding:1em 1.2em;margin:1em 0}
.carmel-notices h3{margin:0 0 .5em}
.carmel-notice-list{list-style:none;padding:0;margin:0}
.carmel-notice-list li{padding:.3em 0;border-top:1px solid #ece6f5}
.carmel-notice-list li:first-child{border-top:0}
.carmel-notice-sum{color:#7a7488}
.carmel-guide-cta{display:flex;align-items:center;justify-content:space-between;gap:1em;flex-wrap:wrap;background:#f1ecfb;border:1px solid #ddd2f5;border-radius:12px;padding:.9em 1.2em;margin:1em 0}
.carmel-guide-badge{background:#6b4fbb;color:#fff;border-radius:.3em;padding:.1em .6em;font-size:.78em;font-weight:bold}
.carmel-guide-sec .carmel-guide-lead{color:#7a7488;font-size:.9em}
.carmel-guide-list{list-style:none;counter-reset:none;padding:0;margin:0}
.carmel-guide-step{margin:.5em 0}
.carmel-guide-step details{border:1px solid #e7e2ef;border-radius:10px;background:#fff;padding:.2em .4em}
.carmel-guide-step summary{cursor:pointer;display:flex;align-items:center;gap:.7em;padding:.7em .6em;flex-wrap:wrap}
.carmel-step-no{flex:0 0 auto;width:1.9em;height:1.9em;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:#6b4fbb;color:#fff;font-weight:bold;font-size:.9em}
.carmel-step-ttl{font-weight:700}
.carmel-step-sum{color:#7a7488;font-size:.85em;flex-basis:100%;padding-left:2.6em}
.carmel-guide-body{padding:.2em 1em 1em 2.6em;color:#46414f;line-height:1.85}
</style>';
	}
}
