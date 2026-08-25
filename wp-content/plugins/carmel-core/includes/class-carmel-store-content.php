<?php
/**
 * Franchise-facing content: start guide, announcements, manuals, FAQ, promo.
 *
 * HQ authors entries as the carmel_content CPT (wp-admin or [carmel_hq_content]),
 * tagging each with a content_type. Franchises view them via
 * [carmel_store_content]. Features: keyword search, タグ, 確認(既読受領),
 * 動画埋め込み/複数添付, スタートガイド完了チェック(進捗), 限定公開(特定店舗のみ).
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Store_Content {

	/** @var Carmel_Store_Content|null */
	private static $instance = null;

	const SHORTCODE     = 'carmel_store_content';
	const ACK_ACTION    = 'carmel_content_ack';
	const GUIDE_ACTION  = 'carmel_guide_done';
	const NONCE         = 'carmel_content_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'carmel_store_dashboard_top', array( $this, 'render_dashboard_guide' ), 5 );
		add_action( 'carmel_store_dashboard_top', array( $this, 'render_dashboard_notices' ) );
		add_action( 'transition_post_status', array( $this, 'maybe_notify_on_publish' ), 10, 3 );
		add_action( 'admin_post_' . self::ACK_ACTION, array( $this, 'handle_ack' ) );
		add_action( 'admin_post_' . self::GUIDE_ACTION, array( $this, 'handle_guide_done' ) );
	}

	private function is_hq() {
		return current_user_can( 'carmel_manage_stores' );
	}

	private function current_store_id() {
		return (int) get_user_meta( get_current_user_id(), 'store_id', true );
	}

	private function can_view() {
		return is_user_logged_in() && ( current_user_can( 'carmel_change_deal_status' ) || $this->is_hq() );
	}

	/* --------------------------------------------------------------------- *
	 * 限定公開・検索フィルタ
	 * --------------------------------------------------------------------- */

	/** 限定公開：この店舗に見せてよいか（本部は常に可）。 */
	private function visible_to_store( $post_id ) {
		if ( $this->is_hq() ) {
			return true;
		}
		$ids = (string) get_post_meta( $post_id, 'visible_store_ids', true );
		if ( '' === trim( $ids ) ) {
			return true; // 指定なし＝全店公開。
		}
		$list = array_filter( array_map( 'intval', preg_split( '/[\s,]+/', $ids ) ) );
		return in_array( $this->current_store_id(), $list, true );
	}

	/** 取得結果を 限定公開＋キーワード で絞り込む。 */
	private function filter_items( array $items ) {
		$q   = isset( $_GET['cq'] ) ? sanitize_text_field( wp_unslash( $_GET['cq'] ) ) : '';
		$out = array();
		foreach ( $items as $p ) {
			if ( ! $this->visible_to_store( $p->ID ) ) {
				continue;
			}
			if ( '' !== $q ) {
				$hay = $p->post_title . ' ' . $p->post_content . ' '
					. get_post_meta( $p->ID, 'summary', true ) . ' ' . get_post_meta( $p->ID, 'content_tags', true );
				if ( false === mb_stripos( $hay, $q ) ) {
					continue;
				}
			}
			$out[] = $p;
		}
		return $out;
	}

	private function get_content( $type, $limit = 50 ) {
		$items = get_posts(
			array(
				'post_type'      => 'carmel_content',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => 'pinned',
				'orderby'        => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
				'meta_query'     => array( array( 'key' => 'content_type', 'value' => $type ) ),
			)
		);
		return $this->filter_items( $items );
	}

	private function get_guides( $limit = 50 ) {
		$items = get_posts(
			array(
				'post_type'      => 'carmel_content',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'meta_key'       => 'step_order',
				'orderby'        => array( 'meta_value_num' => 'ASC', 'date' => 'ASC' ),
				'meta_query'     => array( array( 'key' => 'content_type', 'value' => 'guide' ) ),
			)
		);
		// ガイドは検索で隠さず、限定公開のみ適用。
		return array_values( array_filter( $items, array( $this, 'visible_to_store' ) ) );
	}

	/* --------------------------------------------------------------------- *
	 * 共通：タグ・動画・添付
	 * --------------------------------------------------------------------- */

	private function tags_html( $post_id ) {
		$tags = (string) get_post_meta( $post_id, 'content_tags', true );
		if ( '' === trim( $tags ) ) {
			return '';
		}
		$out = '<div class="carmel-c-tags">';
		foreach ( array_filter( array_map( 'trim', preg_split( '/[,、]+/', $tags ) ) ) as $t ) {
			$url  = add_query_arg( 'cq', rawurlencode( $t ), remove_query_arg( 'cq' ) );
			$out .= '<a class="carmel-c-tag" href="' . esc_url( $url ) . '">#' . esc_html( $t ) . '</a>';
		}
		return $out . '</div>';
	}

	private function media_html( $post_id ) {
		$out   = '';
		$video = (string) get_post_meta( $post_id, 'video_url', true );
		if ( '' !== $video ) {
			$embed = wp_oembed_get( $video );
			$out  .= '<div class="carmel-c-video">' . ( $embed ? $embed : '<a href="' . esc_url( $video ) . '" target="_blank" rel="noopener">動画を見る</a>' ) . '</div>';
		}
		$att = (string) get_post_meta( $post_id, 'attachments', true );
		if ( '' !== trim( $att ) ) {
			$out .= '<ul class="carmel-c-atts">';
			foreach ( preg_split( '/\r\n|\r|\n/', trim( $att ) ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$parts = explode( '|', $line, 2 );
				$url   = esc_url( trim( $parts[0] ) );
				if ( '' === $url ) {
					continue;
				}
				$label = isset( $parts[1] ) && '' !== trim( $parts[1] ) ? trim( $parts[1] ) : wp_basename( $parts[0] );
				$out  .= '<li><a href="' . $url . '" target="_blank" rel="noopener">📎 ' . esc_html( $label ) . '</a></li>';
			}
			$out .= '</ul>';
		}
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * 確認（既読受領）
	 * --------------------------------------------------------------------- */

	private function acks( $post_id ) {
		$a = get_post_meta( $post_id, '_acks', true );
		return is_array( $a ) ? $a : array();
	}

	private function ack_html( $post_id ) {
		$store = $this->current_store_id();
		if ( $this->is_hq() || ! $store ) {
			return '';
		}
		$acks = $this->acks( $post_id );
		if ( isset( $acks[ $store ] ) ) {
			return '<span class="carmel-ack-done">✓ 確認済み（' . esc_html( mysql2date( 'Y-m-d', $acks[ $store ] ) ) . '）</span>';
		}
		$nonce = wp_create_nonce( self::ACK_ACTION . '_' . $post_id );
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-ack-form">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::ACK_ACTION ) . '">'
			. '<input type="hidden" name="post_id" value="' . (int) $post_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<button type="submit" class="carmel-btn carmel-btn-green">確認しました</button></form>';
	}

	public function handle_ack() {
		$post_id  = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/store-content' );
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::ACK_ACTION . '_' . $post_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		$store = $this->current_store_id();
		if ( ! current_user_can( 'carmel_change_deal_status' ) || ! $store || 'carmel_content' !== get_post_type( $post_id ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$acks           = $this->acks( $post_id );
		$acks[ $store ] = current_time( 'mysql' );
		update_post_meta( $post_id, '_acks', $acks );
		do_action( 'carmel_content_acked', $post_id, $store );
		wp_safe_redirect( $redirect );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * スタートガイド 完了チェック
	 * --------------------------------------------------------------------- */

	private function guide_done_ids() {
		$d = get_user_meta( get_current_user_id(), 'carmel_guide_done', true );
		return is_array( $d ) ? array_map( 'intval', $d ) : array();
	}

	public function handle_guide_done() {
		$gid      = isset( $_POST['guide_id'] ) ? (int) $_POST['guide_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/store-content' );
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::GUIDE_ACTION . '_' . $gid ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! $this->can_view() || 'carmel_content' !== get_post_type( $gid ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$done = $this->guide_done_ids();
		if ( in_array( $gid, $done, true ) ) {
			$done = array_values( array_diff( $done, array( $gid ) ) );
		} else {
			$done[] = $gid;
		}
		update_user_meta( get_current_user_id(), 'carmel_guide_done', $done );
		wp_safe_redirect( $redirect );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Dashboard (top of /store)
	 * --------------------------------------------------------------------- */

	public function render_dashboard_guide() {
		$guides = $this->get_guides();
		if ( empty( $guides ) ) {
			return;
		}
		$done = count( array_intersect( wp_list_pluck( $guides, 'ID' ), $this->guide_done_ids() ) );
		$slug = home_url( '/' . ltrim( apply_filters( 'carmel_store_content_page_slug', 'store-content' ), '/' ) );
		echo '<div class="carmel-guide-cta">';
		echo '<div class="carmel-guide-cta-main"><span class="carmel-guide-badge">はじめての方へ</span> ';
		echo '<strong>始め方マニュアル</strong>　進捗 ' . (int) $done . '/' . (int) count( $guides ) . ' ステップ完了</div>';
		echo '<a class="carmel-btn carmel-btn-purple" style="text-decoration:none;background:#6b4fbb;color:#fff;border-radius:.3em;padding:.5em 1.1em" href="' . esc_url( $slug ) . '">スタートガイドを開く</a>';
		echo '</div>';
	}

	public function render_dashboard_notices() {
		$notices = $this->get_content( 'notice', 5 );
		$notices = array_slice( $notices, 0, 3 );
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
		$q = isset( $_GET['cq'] ) ? sanitize_text_field( wp_unslash( $_GET['cq'] ) ) : '';

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-content">';

		// 検索バー。
		echo '<form method="get" class="carmel-c-search"><input type="text" name="cq" value="' . esc_attr( $q ) . '" placeholder="キーワード・タグで検索"><button type="submit" class="carmel-btn carmel-btn-purple">検索</button>';
		if ( '' !== $q ) {
			echo ' <a class="carmel-c-clear" href="' . esc_url( remove_query_arg( 'cq' ) ) . '">✕ クリア</a>';
		}
		echo '</form>';

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
		$done   = $this->guide_done_ids();
		$total  = count( $items );
		$done_n = count( array_intersect( wp_list_pluck( $items, 'ID' ), $done ) );
		$pct    = $total ? round( $done_n / $total * 100 ) : 0;
		$out   .= '<div class="carmel-guide-progress"><div class="carmel-guide-bar"><span style="width:' . (int) $pct . '%"></span></div>'
			. '<div class="carmel-guide-pct">' . (int) $done_n . '/' . (int) $total . ' 完了（' . (int) $pct . '%）</div></div>';
		$out   .= '<ol class="carmel-guide-list">';
		foreach ( $items as $i => $g ) {
			$summary = get_post_meta( $g->ID, 'summary', true );
			$is_done = in_array( (int) $g->ID, $done, true );
			$open    = ( 0 === $i && ! $is_done ) ? ' open' : '';
			$out    .= '<li class="carmel-guide-step' . ( $is_done ? ' done' : '' ) . '">';
			$out    .= '<details' . $open . '><summary><span class="carmel-step-no">' . ( $is_done ? '✓' : ( $i + 1 ) ) . '</span>'
				. '<span class="carmel-step-ttl">' . esc_html( get_the_title( $g->ID ) ) . '</span>';
			if ( $summary ) {
				$out .= '<span class="carmel-step-sum">' . esc_html( $summary ) . '</span>';
			}
			$out .= '</summary>';
			$out .= '<div class="carmel-guide-body">' . wp_kses_post( wpautop( get_post_field( 'post_content', $g->ID ) ) );
			$out .= $this->media_html( $g->ID );
			$nonce = wp_create_nonce( self::GUIDE_ACTION . '_' . $g->ID );
			$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-guide-done-form">'
				. '<input type="hidden" name="action" value="' . esc_attr( self::GUIDE_ACTION ) . '">'
				. '<input type="hidden" name="guide_id" value="' . (int) $g->ID . '">'
				. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
				. '<button type="submit" class="carmel-btn ' . ( $is_done ? 'carmel-btn-ghost' : 'carmel-btn-green' ) . '">' . ( $is_done ? '未完了に戻す' : '✓ 完了にする' ) . '</button></form>';
			$out .= '</div></details></li>';
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
			$out   .= $this->media_html( $n->ID );
			$out   .= $this->tags_html( $n->ID );
			$out   .= '<div class="carmel-c-foot">' . $this->ack_html( $n->ID ) . '</div>';
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
		foreach ( $items as $m ) {
			$file    = get_post_meta( $m->ID, 'file_url', true );
			$summary = get_post_meta( $m->ID, 'summary', true );
			$body    = (string) get_post_field( 'post_content', $m->ID );
			$out    .= '<article class="carmel-c-card"><div class="carmel-doc-head"><strong>' . esc_html( get_the_title( $m->ID ) ) . '</strong>';
			if ( $file ) {
				$out .= ' <a class="carmel-btn carmel-btn-blue" href="' . esc_url( $file ) . '" target="_blank" rel="noopener">開く</a>';
			}
			$out .= '</div>';
			if ( $summary ) {
				$out .= '<div class="carmel-doc-sum">' . esc_html( $summary ) . '</div>';
			}
			if ( '' !== trim( $body ) ) {
				$out .= '<div class="carmel-c-body">' . wp_kses_post( wpautop( $body ) ) . '</div>';
			}
			$out .= $this->media_html( $m->ID );
			$out .= $this->tags_html( $m->ID );
			$out .= '</article>';
		}
		return $out . '</section>';
	}

	private function faq_section() {
		$items = $this->get_content( 'faq' );
		$out   = '<section><h2>❓ よくある質問</h2>';
		if ( empty( $items ) ) {
			return $out . '<p>FAQはありません。</p></section>';
		}
		foreach ( $items as $f ) {
			$out .= '<details class="carmel-faq"><summary>' . esc_html( get_the_title( $f->ID ) ) . '</summary>';
			$out .= '<div class="carmel-faq-a">' . wp_kses_post( wpautop( get_post_field( 'post_content', $f->ID ) ) ) . $this->media_html( $f->ID ) . $this->tags_html( $f->ID ) . '</div></details>';
		}
		return $out . '</section>';
	}

	/* --------------------------------------------------------------------- *
	 * Publish broadcast（限定公開は対象店舗のみ）
	 * --------------------------------------------------------------------- */

	public function maybe_notify_on_publish( $new_status, $old_status, $post ) {
		if ( ! $post instanceof WP_Post || 'carmel_content' !== $post->post_type ) {
			return;
		}
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! get_post_meta( $post->ID, 'notify_stores', true ) ) {
			return;
		}
		// 限定公開なら指定店舗のみへ通知（フィルタで recipients を絞る）。
		$ids    = (string) get_post_meta( $post->ID, 'visible_store_ids', true );
		$filter = null;
		if ( '' !== trim( $ids ) ) {
			$store_ids = array_filter( array_map( 'intval', preg_split( '/[\s,]+/', $ids ) ) );
			$filter    = function ( $recipients, $audience ) use ( $store_ids ) {
				if ( 'all_stores' !== $audience ) {
					return $recipients;
				}
				return array_values( array_filter( $recipients, function ( $r ) use ( $store_ids ) {
					$sid = (int) get_user_meta( $r['id'], 'store_id', true );
					return in_array( $sid, $store_ids, true );
				} ) );
			};
			add_filter( 'carmel_notification_recipients', $filter, 10, 2 );
		}

		Carmel_Notifier::notify(
			'store_notice',
			array(
				'event_id' => 'store_notice:' . $post->ID,
				'vars'     => array( 'title' => get_the_title( $post->ID ), 'summary' => (string) get_post_meta( $post->ID, 'summary', true ) ),
			)
		);

		if ( $filter ) {
			remove_filter( 'carmel_notification_recipients', $filter, 10 );
		}
	}

	private function styles() {
		return '<style>
.carmel-content{font-size:14px;max-width:760px}
.carmel-content section{margin-bottom:2em}
.carmel-content h2{border-bottom:2px solid #e7e2ef;padding-bottom:.3em}
.carmel-c-search{display:flex;gap:.4em;margin:.4em 0 1.2em}
.carmel-c-search input{flex:1;border:1px solid #ccc;border-radius:.3em;padding:.5em}
.carmel-c-clear{align-self:center;color:#888;text-decoration:none;font-size:.85em}
.carmel-c-card{border:1px solid #e7e2ef;border-radius:12px;padding:1em 1.2em;margin:.8em 0;background:#fff}
.carmel-c-head h3{margin:.3em 0 0}
.carmel-doc-head{display:flex;align-items:center;gap:.6em;flex-wrap:wrap}
.carmel-pin{background:#c0392b;color:#fff;border-radius:.3em;padding:.05em .5em;font-size:.75em;font-weight:bold}
.carmel-notice-date{color:#9298a5;font-size:.85em}
.carmel-doc-sum{color:#7a7488;font-size:.85em;margin:.3em 0}
.carmel-c-video{margin:.6em 0}.carmel-c-video iframe{max-width:100%;border-radius:8px}
.carmel-c-atts{list-style:none;padding:0;margin:.4em 0}
.carmel-c-atts li{padding:.2em 0}
.carmel-c-tags{display:flex;gap:.3em;flex-wrap:wrap;margin:.4em 0}
.carmel-c-tag{font-size:.78em;color:#6b4fbb;background:#f1ecfb;border-radius:1em;padding:.1em .7em;text-decoration:none}
.carmel-c-foot{margin-top:.6em}
.carmel-ack-form{display:inline}
.carmel-ack-done{color:#0e6e58;font-weight:bold;font-size:.88em}
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
.carmel-guide-progress{margin:.4em 0 1em}
.carmel-guide-bar{height:8px;background:#ece6f5;border-radius:4px;overflow:hidden}
.carmel-guide-bar span{display:block;height:100%;background:#16a085}
.carmel-guide-pct{font-size:.82em;color:#666;margin-top:.3em}
.carmel-guide-list{list-style:none;padding:0;margin:0}
.carmel-guide-step{margin:.5em 0}
.carmel-guide-step details{border:1px solid #e7e2ef;border-radius:10px;background:#fff;padding:.2em .4em}
.carmel-guide-step.done details{border-color:#16a085;background:#f1fbf8}
.carmel-guide-step summary{cursor:pointer;display:flex;align-items:center;gap:.7em;padding:.7em .6em;flex-wrap:wrap}
.carmel-step-no{flex:0 0 auto;width:1.9em;height:1.9em;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:#6b4fbb;color:#fff;font-weight:bold;font-size:.9em}
.carmel-guide-step.done .carmel-step-no{background:#16a085}
.carmel-step-ttl{font-weight:700}
.carmel-step-sum{color:#7a7488;font-size:.85em;flex-basis:100%;padding-left:2.6em}
.carmel-guide-body{padding:.2em 1em 1em 2.6em;color:#46414f;line-height:1.85}
.carmel-guide-done-form{margin-top:.5em}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.45em .9em;color:#fff;cursor:pointer;font-size:.85em;text-decoration:none}
.carmel-btn-purple{background:#6b4fbb}.carmel-btn-blue{background:#2e86de}.carmel-btn-green{background:#16a085}.carmel-btn-ghost{background:#eef2fb;color:#2e86de}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
