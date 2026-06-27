<?php
/**
 * 公開「加盟店（店舗）ページ」。
 *
 * ショートコード [carmel_store_profile] を公開ページ（例 /stores）に設置：
 *   - ?store=ID なし … 加盟店ディレクトリ（公開店舗の一覧＋在庫数＋地図リンク）
 *   - ?store=ID あり … 店舗紹介（名称・住所・紹介文・地図）＋その店の公開在庫一覧
 * 一般公開（未ログインでも閲覧可）。内部情報（原価・オーナーID・会費等）は出さない。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Store_Profile {

	/** @var Carmel_Store_Profile|null */
	private static $instance = null;

	const SHORTCODE      = 'carmel_store_profile';
	const HQ_SHORTCODE   = 'carmel_hq_reviews';
	const REVIEW_ACTION  = 'carmel_store_review';
	const MOD_ACTION     = 'carmel_review_moderate';
	const COMMENT_TYPE   = 'carmel_review';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_shortcode( self::HQ_SHORTCODE, array( $this, 'render_hq_reviews' ) );
		add_action( 'wp_head', array( $this, 'seo_head' ) );
		add_action( 'admin_post_' . self::REVIEW_ACTION, array( $this, 'handle_review' ) );
		add_action( 'admin_post_nopriv_' . self::REVIEW_ACTION, array( $this, 'handle_review' ) );
		add_action( 'admin_post_' . self::MOD_ACTION, array( $this, 'handle_moderate' ) );
	}

	/* --------------------------------------------------------------------- *
	 * 本部レビュー承認UI（フロント）
	 * --------------------------------------------------------------------- */

	public function render_hq_reviews() {
		if ( ! is_user_logged_in() || ! current_user_can( 'carmel_manage_stores' ) ) {
			return '<p class="carmel-notice">レビュー承認を表示する権限がありません。</p>';
		}
		$pending = get_comments(
			array(
				'status'  => 'hold',
				'type'    => self::COMMENT_TYPE,
				'number'  => 100,
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			)
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->mod_banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-sp"><h2>店舗レビュー承認</h2>';
		if ( empty( $pending ) ) {
			echo '<p>承認待ちのレビューはありません。</p></div>';
			return ob_get_clean();
		}
		echo '<table class="carmel-rev-table"><thead><tr><th>店舗</th><th>評価</th><th>投稿者</th><th>内容</th><th>日時</th><th>操作</th></tr></thead><tbody>';
		foreach ( $pending as $c ) {
			$store = (int) $c->comment_post_ID;
			$sname = $store ? ( get_post_meta( $store, 'store_name', true ) ?: get_the_title( $store ) ) : '—';
			$rt    = (int) get_comment_meta( $c->comment_ID, 'rating', true );
			echo '<tr>';
			echo '<td><a href="' . esc_url( self::url( $store ) ) . '" target="_blank" rel="noopener">' . esc_html( $sname ) . '</a></td>';
			echo '<td>' . $this->stars( $rt ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<td>' . esc_html( $c->comment_author ) . '</td>';
			echo '<td class="carmel-rev-body">' . esc_html( mb_strimwidth( $c->comment_content, 0, 80, '…' ) ) . '</td>';
			echo '<td>' . esc_html( mysql2date( 'm/d H:i', $c->comment_date ) ) . '</td>';
			echo '<td class="carmel-rev-ops">' . $this->mod_buttons( (int) $c->comment_ID ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</tr>';
		}
		echo '</tbody></table></div>';
		return ob_get_clean();
	}

	private function mod_buttons( $cid ) {
		$out = '';
		foreach ( array( 'approve' => array( '承認', 'carmel-btn-purple' ), 'reject' => array( '却下', 'carmel-btn-red' ) ) as $op => $b ) {
			$nonce = wp_create_nonce( self::MOD_ACTION . '_' . $op . '_' . $cid );
			$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;margin:0 .2em 0 0">'
				. '<input type="hidden" name="action" value="' . esc_attr( self::MOD_ACTION ) . '">'
				. '<input type="hidden" name="cid" value="' . (int) $cid . '">'
				. '<input type="hidden" name="op" value="' . esc_attr( $op ) . '">'
				. '<input type="hidden" name="carmel_mod_nonce" value="' . esc_attr( $nonce ) . '">'
				. '<button type="submit" class="carmel-btn ' . esc_attr( $b[1] ) . '" style="padding:.3em .8em;font-size:.82em">' . esc_html( $b[0] ) . '</button></form>';
		}
		return $out;
	}

	public function handle_moderate() {
		if ( ! current_user_can( 'carmel_manage_stores' ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$cid = isset( $_POST['cid'] ) ? (int) $_POST['cid'] : 0;
		$op  = isset( $_POST['op'] ) ? sanitize_key( $_POST['op'] ) : '';
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/hq' );
		if ( ! wp_verify_nonce( isset( $_POST['carmel_mod_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_mod_nonce'] ) ) : '', self::MOD_ACTION . '_' . $op . '_' . $cid ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		$comment = get_comment( $cid );
		if ( ! $comment || self::COMMENT_TYPE !== $comment->comment_type ) {
			wp_die( esc_html__( '対象が不正です。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( 'approve' === $op ) {
			wp_set_comment_status( $cid, 'approve' );
		} elseif ( 'reject' === $op ) {
			wp_set_comment_status( $cid, 'trash' );
		}
		do_action( 'carmel_store_review_moderated', $cid, $op );
		wp_safe_redirect( add_query_arg( 'carmel_mod', 'ok', $redirect ) );
		exit;
	}

	private function mod_banner() {
		return ( isset( $_GET['carmel_mod'] ) && 'ok' === $_GET['carmel_mod'] )
			? '<div class="carmel-sp-revbanner">レビューを更新しました。</div>'
			: '';
	}

	/** 店舗ページのスラッグ（フィルタ可）。 */
	public static function page_slug() {
		return ltrim( apply_filters( 'carmel_store_profile_page_slug', 'stores' ), '/' );
	}

	/** 指定店舗の公開ページURL。 */
	public static function url( $store_id ) {
		return add_query_arg( 'store', (int) $store_id, home_url( '/' . self::page_slug() ) );
	}

	private function inventory_url() {
		return home_url( '/' . ltrim( apply_filters( 'carmel_inventory_page_slug', 'inventory' ), '/' ) );
	}

	private function maps_api_key() {
		return defined( 'CARMEL_MAPS_API_KEY' ) ? CARMEL_MAPS_API_KEY : get_option( 'carmel_maps_api_key', '' );
	}

	/** 販売可能な在庫ステータス。 */
	private function sellable() {
		return class_exists( 'Carmel_Inventory' ) ? Carmel_Inventory::sellable_statuses() : array( '販売中', '商談中' );
	}

	/* --------------------------------------------------------------------- *
	 * ルーティング
	 * --------------------------------------------------------------------- */

	public function render() {
		$store_id = isset( $_GET['store'] ) ? (int) $_GET['store'] : 0;
		if ( $store_id && 'carmel_store' === get_post_type( $store_id ) && 'publish' === get_post_status( $store_id ) ) {
			return $this->render_single( $store_id );
		}
		return $this->render_directory();
	}

	/* --------------------------------------------------------------------- *
	 * ディレクトリ（加盟店一覧）
	 * --------------------------------------------------------------------- */

	private function render_directory() {
		$area    = isset( $_GET['area'] ) ? sanitize_text_field( wp_unslash( $_GET['area'] ) ) : '';
		$service = isset( $_GET['service'] ) ? sanitize_key( $_GET['service'] ) : '';

		$meta = array( 'relation' => 'AND' );
		if ( '' !== $area ) {
			$meta[] = array( 'key' => 'store_area', 'value' => $area );
		}
		if ( in_array( $service, array( 'loan', 'buyback', 'lease' ), true ) ) {
			// ACF checkbox は配列をシリアライズ保存 → 値を LIKE で包含一致。
			$meta[] = array( 'key' => 'store_services', 'value' => '"' . $service . '"', 'compare' => 'LIKE' );
		}

		$stores = get_posts(
			array(
				'post_type'      => 'carmel_store',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'meta_query'     => count( $meta ) > 1 ? $meta : array(),
			)
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-sp"><h2>加盟店一覧</h2>';
		echo $this->directory_filter( $area, $service ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( empty( $stores ) ) {
			echo '<p>条件に合う加盟店が見つかりませんでした。</p></div>';
			return ob_get_clean();
		}
		echo '<div class="carmel-sp-grid">';
		foreach ( $stores as $s ) {
			$name  = $this->store_name( $s );
			$addr  = (string) get_post_meta( $s->ID, 'store_address', true );
			$count = $this->stock_count( $s->ID );
			echo '<a class="carmel-sp-card" href="' . esc_url( self::url( $s->ID ) ) . '">';
			echo '<div class="carmel-sp-name">' . esc_html( $name ) . '</div>';
			if ( $addr ) {
				echo '<div class="carmel-sp-addr">' . esc_html( $addr ) . '</div>';
			}
			echo '<div class="carmel-sp-stock">公開在庫 ' . (int) $count . ' 台</div>';
			echo '</a>';
		}
		echo '</div></div>';
		return ob_get_clean();
	}

	/** エリア・取扱種別の絞り込みフォーム。 */
	private function directory_filter( $area, $service ) {
		$regions  = class_exists( 'Carmel_LINE_Bot' ) ? Carmel_LINE_Bot::regions() : array( '北海道', '東北', '関東', '中部', '近畿', '中国・四国', '九州・沖縄', 'その他' );
		$services = array( 'loan' => 'ローン販売', 'buyback' => '車買取', 'lease' => '自社リース' );

		$out  = '<form method="get" class="carmel-sp-filter">';
		$out .= '<select name="area"><option value="">エリア（すべて）</option>';
		foreach ( $regions as $r ) {
			$out .= '<option value="' . esc_attr( $r ) . '"' . selected( $area, $r, false ) . '>' . esc_html( $r ) . '</option>';
		}
		$out .= '</select>';
		$out .= '<select name="service"><option value="">取扱（すべて）</option>';
		foreach ( $services as $k => $label ) {
			$out .= '<option value="' . esc_attr( $k ) . '"' . selected( $service, $k, false ) . '>' . esc_html( $label ) . '</option>';
		}
		$out .= '</select>';
		$out .= '<button type="submit" class="carmel-btn carmel-btn-purple">絞り込む</button>';
		$out .= '</form>';
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * 店舗ページ（個別）
	 * --------------------------------------------------------------------- */

	private function render_single( $store_id ) {
		$store = get_post( $store_id );
		$name  = $this->store_name( $store );
		$addr  = (string) get_post_meta( $store_id, 'store_address', true );
		$tel   = (string) get_post_meta( $store_id, 'store_tel', true );
		$hours = (string) get_post_meta( $store_id, 'store_hours', true );
		$desc  = (string) get_post_field( 'post_content', $store_id );

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-sp">';
		echo '<p class="carmel-sp-back"><a href="' . esc_url( home_url( '/' . self::page_slug() ) ) . '">← 加盟店一覧</a></p>';
		echo '<div class="carmel-sp-head"><h2>' . esc_html( $name ) . '</h2>';
		if ( class_exists( 'Carmel_Store_Follow' ) ) {
			echo '<div class="carmel-sp-follow">' . Carmel_Store_Follow::instance()->follow_button( $store_id ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo '</div>';

		// 店舗情報。
		echo '<table class="carmel-sp-info">';
		if ( $addr ) {
			echo '<tr><th>住所</th><td>' . esc_html( $addr ) . '</td></tr>';
		}
		if ( $tel ) {
			echo '<tr><th>電話</th><td>' . esc_html( $tel ) . '</td></tr>';
		}
		if ( $hours ) {
			echo '<tr><th>営業時間</th><td>' . esc_html( $hours ) . '</td></tr>';
		}
		echo '</table>';

		if ( $desc ) {
			echo '<div class="carmel-sp-desc">' . wp_kses_post( wpautop( $desc ) ) . '</div>';
		}

		// 実績。
		echo $this->stats_section( $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput

		// 地図。
		echo $this->map( $addr, $name ); // phpcs:ignore WordPress.Security.EscapeOutput

		// スタッフ紹介。
		echo $this->staff_section( $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput

		// この店舗の公開在庫。
		$cars = $this->stock( $store_id, 12 );
		echo '<h3>取扱在庫</h3>';
		if ( empty( $cars ) ) {
			echo '<p>現在公開中の在庫はありません。</p>';
		} else {
			echo '<div class="carmel-sp-cars">';
			foreach ( $cars as $car ) {
				echo $this->car_card( $car ); // phpcs:ignore WordPress.Security.EscapeOutput
			}
			echo '</div>';
			echo '<p><a class="carmel-btn carmel-btn-purple" href="' . esc_url( add_query_arg( 'store_id', (int) $store_id, $this->inventory_url() ) ) . '">この店舗の在庫をもっと見る →</a></p>';
		}

		// 問い合わせ導線。
		$apply = home_url( '/' . ltrim( apply_filters( 'carmel_apply_page_slug', 'apply' ), '/' ) );
		echo '<div class="carmel-sp-cta"><a class="carmel-btn carmel-btn-blue" href="' . esc_url( $apply ) . '">この店舗に相談・お問い合わせ</a></div>';

		// レビュー。
		echo $this->reviews_section( $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput

		echo '</div>';
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- *
	 * 実績
	 * --------------------------------------------------------------------- */

	private function won_statuses() {
		if ( class_exists( 'Carmel_Reports' ) ) {
			return Carmel_Reports::WON;
		}
		return array( 'contracted', 'delivered', 'closed', 'bb_agreed', 'bb_collected', 'bb_closed', 'lease_contracted', 'lease_delivered', 'lease_completed', 'lease_closed' );
	}

	private function stats_section( $store_id ) {
		$ids = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array( array( 'key' => 'store_id', 'value' => (int) $store_id ) ),
			)
		);
		$won = $this->won_statuses();
		$deals = count( $ids );
		$closed = 0;
		foreach ( $ids as $id ) {
			if ( in_array( get_post_meta( $id, 'deal_status', true ), $won, true ) ) {
				$closed++;
			}
		}
		$stock = $this->stock_count( $store_id );

		$cards = array(
			array( '公開在庫', $stock . ' 台' ),
			array( '成約実績', $closed . ' 件' ),
			array( '取扱案件', $deals . ' 件' ),
		);
		$out = '<div class="carmel-sp-stats">';
		foreach ( $cards as $c ) {
			$out .= '<div class="carmel-sp-stat"><div class="carmel-sp-stat-num">' . esc_html( $c[1] ) . '</div><div class="carmel-sp-stat-lbl">' . esc_html( $c[0] ) . '</div></div>';
		}
		$out .= '</div>';
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * スタッフ紹介
	 * --------------------------------------------------------------------- */

	private function staff_section( $store_id ) {
		if ( ! apply_filters( 'carmel_store_show_staff', true, $store_id ) ) {
			return '';
		}
		$users = get_users(
			array(
				'role__in'   => array( 'store_owner', 'store_staff' ),
				'meta_key'   => 'store_id',
				'meta_value' => (int) $store_id,
				'number'     => 20,
			)
		);
		if ( empty( $users ) ) {
			return '';
		}
		$out = '<h3>スタッフ紹介</h3><div class="carmel-sp-staff">';
		foreach ( $users as $u ) {
			$role = in_array( 'store_owner', (array) $u->roles, true ) ? '店長' : 'スタッフ';
			$out .= '<div class="carmel-sp-member">' . get_avatar( $u->ID, 56, '', '', array( 'class' => 'carmel-sp-ava' ) )
				. '<div class="carmel-sp-mname">' . esc_html( $u->display_name ) . '</div>'
				. '<div class="carmel-sp-mrole">' . esc_html( $role ) . '</div></div>';
		}
		$out .= '</div>';
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * レビュー
	 * --------------------------------------------------------------------- */

	private function reviews_section( $store_id ) {
		$reviews = get_comments(
			array(
				'post_id' => (int) $store_id,
				'status'  => 'approve',
				'type'    => self::COMMENT_TYPE,
				'number'  => 30,
				'orderby' => 'comment_date',
				'order'   => 'DESC',
			)
		);

		$out = '<h3>お客様の声・レビュー</h3>';

		// 平均評価。
		if ( ! empty( $reviews ) ) {
			$sum = 0;
			$n   = 0;
			foreach ( $reviews as $r ) {
				$rt = (int) get_comment_meta( $r->comment_ID, 'rating', true );
				if ( $rt >= 1 && $rt <= 5 ) {
					$sum += $rt;
					$n++;
				}
			}
			if ( $n ) {
				$avg = round( $sum / $n, 1 );
				$out .= '<div class="carmel-sp-avg">' . $this->stars( (int) round( $avg ) ) . ' <strong>' . esc_html( $avg ) . '</strong> / 5（' . (int) $n . '件）</div>';
			}
		}

		if ( empty( $reviews ) ) {
			$out .= '<p class="carmel-sp-norev">まだレビューはありません。最初の口コミをお寄せください。</p>';
		} else {
			$out .= '<ul class="carmel-sp-reviews">';
			foreach ( $reviews as $r ) {
				$rt = (int) get_comment_meta( $r->comment_ID, 'rating', true );
				$out .= '<li><div class="carmel-sp-rhead">' . $this->stars( $rt ) . ' <span class="carmel-sp-rauthor">' . esc_html( $r->comment_author ? $r->comment_author : '匿名' ) . '</span> <span class="carmel-sp-rdate">' . esc_html( mysql2date( 'Y-m-d', $r->comment_date ) ) . '</span></div>'
					. '<div class="carmel-sp-rbody">' . nl2br( esc_html( $r->comment_content ) ) . '</div></li>';
			}
			$out .= '</ul>';
		}

		$out .= $this->review_form( $store_id );
		return $out;
	}

	private function stars( $n ) {
		$n = max( 0, min( 5, (int) $n ) );
		return '<span class="carmel-sp-stars">' . str_repeat( '★', $n ) . str_repeat( '☆', 5 - $n ) . '</span>';
	}

	private function review_form( $store_id ) {
		$nonce = wp_create_nonce( self::REVIEW_ACTION . '_' . $store_id );
		$name  = is_user_logged_in() ? wp_get_current_user()->display_name : '';
		$msg   = isset( $_GET['carmel_rev'] ) ? sanitize_key( $_GET['carmel_rev'] ) : '';
		$banner = '';
		if ( 'ok' === $msg ) {
			$banner = '<div class="carmel-sp-revbanner">レビューを受け付けました。承認後に掲載されます。ありがとうございました。</div>';
		} elseif ( 'err' === $msg ) {
			$banner = '<div class="carmel-sp-revbanner err">入力内容をご確認ください（評価と本文は必須）。</div>';
		}

		$out  = '<details class="carmel-sp-revform"><summary>口コミを投稿する</summary>' . $banner;
		$out .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::REVIEW_ACTION ) . '">'
			. '<input type="hidden" name="store_id" value="' . (int) $store_id . '">'
			. '<input type="hidden" name="carmel_rev_nonce" value="' . esc_attr( $nonce ) . '">'
			// ハニーポット（スパム対策）。
			. '<input type="text" name="carmel_hp" value="" style="display:none" tabindex="-1" autocomplete="off">'
			. '<label>お名前 <input type="text" name="rev_name" value="' . esc_attr( $name ) . '" placeholder="匿名可"></label>'
			. '<label>評価 <select name="rev_rating"><option value="5">★★★★★</option><option value="4">★★★★☆</option><option value="3">★★★☆☆</option><option value="2">★★☆☆☆</option><option value="1">★☆☆☆☆</option></select></label>'
			. '<textarea name="rev_body" rows="3" placeholder="ご利用の感想をお書きください" required></textarea>'
			. '<button type="submit" class="carmel-btn carmel-btn-purple">投稿する</button>'
			. '<p class="carmel-sp-revnote">投稿は本部の承認後に掲載されます。</p>'
			. '</form></details>';
		return $out;
	}

	public function handle_review() {
		$store_id = isset( $_POST['store_id'] ) ? (int) $_POST['store_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' . self::page_slug() );

		if ( ! wp_verify_nonce( isset( $_POST['carmel_rev_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_rev_nonce'] ) ) : '', self::REVIEW_ACTION . '_' . $store_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		// ハニーポット or 不正。
		$rating = isset( $_POST['rev_rating'] ) ? (int) $_POST['rev_rating'] : 0;
		$body   = isset( $_POST['rev_body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['rev_body'] ) ) : '';
		if ( ! empty( $_POST['carmel_hp'] ) || 'carmel_store' !== get_post_type( $store_id ) || $rating < 1 || $rating > 5 || '' === $body ) {
			wp_safe_redirect( add_query_arg( 'carmel_rev', 'err', self::url( $store_id ) ) );
			exit;
		}
		$name = isset( $_POST['rev_name'] ) ? sanitize_text_field( wp_unslash( $_POST['rev_name'] ) ) : '';
		$name = $name ? $name : '匿名';

		$cid = wp_insert_comment(
			array(
				'comment_post_ID'  => $store_id,
				'comment_content'  => $body,
				'comment_author'   => $name,
				'comment_type'     => self::COMMENT_TYPE,
				'user_id'          => get_current_user_id(),
				'comment_approved' => 0, // 承認待ち（本部がモデレート）。
			)
		);
		if ( $cid ) {
			update_comment_meta( $cid, 'rating', $rating );
			do_action( 'carmel_store_review_submitted', $store_id, (int) $cid );
		}
		wp_safe_redirect( add_query_arg( 'carmel_rev', 'ok', self::url( $store_id ) ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * 補助
	 * --------------------------------------------------------------------- */

	private function store_name( $store ) {
		$n = (string) get_post_meta( $store->ID, 'store_name', true );
		return $n ? $n : get_the_title( $store );
	}

	/** 店舗の公開在庫を取得。 */
	private function stock( $store_id, $limit = 12 ) {
		return get_posts(
			array(
				'post_type'      => 'carmel_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'date',
				'order'          => 'DESC',
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'store_id', 'value' => (int) $store_id ),
					array( 'key' => 'published', 'value' => array( '1', 'yes', 'true' ), 'compare' => 'IN' ),
					array( 'key' => 'vehicle_status', 'value' => $this->sellable(), 'compare' => 'IN' ),
				),
			)
		);
	}

	private function stock_count( $store_id ) {
		return count( get_posts( array_merge(
			array( 'fields' => 'ids' ),
			array(
				'post_type'      => 'carmel_vehicle',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'store_id', 'value' => (int) $store_id ),
					array( 'key' => 'published', 'value' => array( '1', 'yes', 'true' ), 'compare' => 'IN' ),
					array( 'key' => 'vehicle_status', 'value' => $this->sellable(), 'compare' => 'IN' ),
				),
			)
		) ) );
	}

	private function car_card( $car ) {
		$maker = (string) get_post_meta( $car->ID, 'maker', true );
		$model = (string) get_post_meta( $car->ID, 'model', true );
		$title = trim( $maker . ' ' . $model );
		$title = '' !== $title ? $title : get_the_title( $car );
		$price = (float) get_post_meta( $car->ID, 'price', true );
		$url   = add_query_arg( 'vehicle', (int) $car->ID, $this->inventory_url() );
		$thumb = has_post_thumbnail( $car->ID )
			? get_the_post_thumbnail( $car->ID, 'medium', array( 'class' => 'carmel-sp-car-img' ) )
			: '<div class="carmel-sp-car-noimg">NO IMAGE</div>';

		return '<a class="carmel-sp-car" href="' . esc_url( $url ) . '">'
			. '<div class="carmel-sp-car-thumb">' . $thumb . '</div>'
			. '<div class="carmel-sp-car-name">' . esc_html( $title ) . '</div>'
			. '<div class="carmel-sp-car-price">¥' . esc_html( number_format( $price ) ) . '</div></a>';
	}

	private function map( $address, $name ) {
		if ( '' === $address ) {
			return '';
		}
		$key = $this->maps_api_key();
		$out = '<div class="carmel-sp-map">';
		if ( $key ) {
			$src = 'https://www.google.com/maps/embed/v1/place?key=' . rawurlencode( $key ) . '&q=' . rawurlencode( $address );
			$out .= '<iframe loading="lazy" referrerpolicy="no-referrer-when-downgrade" src="' . esc_url( $src ) . '"></iframe>';
		}
		$out .= '<p><a href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $address ) ) . '" target="_blank" rel="noopener">🗺 Googleマップで開く</a></p></div>';
		return $out;
	}

	/* --------------------------------------------------------------------- *
	 * SEO（店舗ページの AutoDealer 構造化データ）
	 * --------------------------------------------------------------------- */

	public function seo_head() {
		$store_id = isset( $_GET['store'] ) ? (int) $_GET['store'] : 0;
		if ( ! $store_id || 'carmel_store' !== get_post_type( $store_id ) || 'publish' !== get_post_status( $store_id ) ) {
			return;
		}
		$store = get_post( $store_id );
		$name  = $this->store_name( $store );
		$addr  = (string) get_post_meta( $store_id, 'store_address', true );
		$tel   = (string) get_post_meta( $store_id, 'store_tel', true );

		$ld = array(
			'@context' => 'https://schema.org',
			'@type'    => 'AutoDealer',
			'name'     => $name,
			'url'      => self::url( $store_id ),
		);
		if ( $addr ) {
			$ld['address'] = $addr;
		}
		if ( $tel ) {
			$ld['telephone'] = $tel;
		}
		echo "\n<!-- Carmel store SEO -->\n";
		echo '<script type="application/ld+json">' . wp_json_encode( $ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "</script>\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<meta property="og:type" content="business.business">' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $name ) . '">' . "\n";
		echo '<meta property="og:url" content="' . esc_url( self::url( $store_id ) ) . '">' . "\n";
	}

	private function styles() {
		return '<style>
.carmel-sp{font-size:14px;max-width:880px}
.carmel-sp-back a{color:#6b4fbb;text-decoration:none}
.carmel-sp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1em;margin-top:1em}
.carmel-sp-card{display:block;border:1px solid #e7e2ef;border-radius:12px;padding:1em;background:#fff;text-decoration:none;color:#1a1a2e}
.carmel-sp-card:hover{border-color:#6b4fbb}
.carmel-sp-name{font-weight:bold;font-size:1.05em}
.carmel-sp-addr{color:#7a7488;font-size:.85em;margin:.3em 0}
.carmel-sp-stock{color:#6b4fbb;font-size:.85em}
.carmel-sp-filter{display:flex;gap:.5em;flex-wrap:wrap;margin:.8em 0}
.carmel-sp-filter select{border:1px solid #ccc;border-radius:.3em;padding:.45em}
.carmel-sp-info{border-collapse:collapse;margin:.6em 0}
.carmel-sp-info th,.carmel-sp-info td{border:1px solid #eef0f4;padding:.5em .7em;text-align:left;font-size:.92em}
.carmel-sp-info th{background:#f4f6fb;white-space:nowrap}
.carmel-sp-desc{line-height:1.85;margin:.8em 0}
.carmel-sp-map{margin:1em 0}
.carmel-sp-map iframe{width:100%;height:280px;border:0;border-radius:10px}
.carmel-sp-cars{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:.8em;margin:.6em 0}
.carmel-sp-car{display:block;border:1px solid #e7e2ef;border-radius:10px;overflow:hidden;background:#fff;text-decoration:none;color:#1a1a2e}
.carmel-sp-car-thumb{aspect-ratio:4/3;background:#f4f6fb;display:flex;align-items:center;justify-content:center;overflow:hidden}
.carmel-sp-car-img{width:100%;height:100%;object-fit:cover}
.carmel-sp-car-noimg{color:#aab;font-size:.8em}
.carmel-sp-car-name{padding:.5em .6em 0;font-size:.88em;font-weight:600}
.carmel-sp-car-price{padding:0 .6em .6em;color:#6b4fbb;font-weight:bold}
.carmel-sp-cta{margin:1.4em 0}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.6em 1.2em;color:#fff;text-decoration:none;cursor:pointer}
.carmel-btn-purple{background:#6b4fbb}.carmel-btn-blue{background:#2e86de}.carmel-btn-red{background:#c0392b}.carmel-btn-ghost{background:#eef2fb;color:#2e86de}
.carmel-sp-head{display:flex;align-items:center;justify-content:space-between;gap:1em;flex-wrap:wrap}
.carmel-sp-follow{flex:0 0 auto}
.carmel-rev-table{width:100%;border-collapse:collapse;margin-top:.6em}
.carmel-rev-table th,.carmel-rev-table td{border:1px solid #e7e2ef;padding:.5em .6em;text-align:left;font-size:.88em}
.carmel-rev-table th{background:#f4f6fb}
.carmel-rev-body{max-width:260px}
.carmel-rev-ops{white-space:nowrap}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
.carmel-sp-stats{display:flex;gap:.7em;flex-wrap:wrap;margin:1em 0}
.carmel-sp-stat{border:1px solid #e7e2ef;border-radius:.6em;padding:.7em 1.1em;min-width:100px;text-align:center;background:#fff}
.carmel-sp-stat-num{font-size:1.4em;font-weight:bold;color:#6b4fbb}
.carmel-sp-stat-lbl{font-size:.78em;color:#666}
.carmel-sp-staff{display:flex;gap:1em;flex-wrap:wrap;margin:.6em 0}
.carmel-sp-member{text-align:center;width:90px}
.carmel-sp-ava{border-radius:50%}
.carmel-sp-mname{font-size:.85em;font-weight:600;margin-top:.3em}
.carmel-sp-mrole{font-size:.75em;color:#888}
.carmel-sp-stars{color:#e6a100;letter-spacing:.05em}
.carmel-sp-avg{font-size:1.05em;margin:.3em 0}
.carmel-sp-reviews{list-style:none;padding:0;margin:.6em 0}
.carmel-sp-reviews li{border-top:1px solid #ece6f5;padding:.7em 0}
.carmel-sp-rauthor{font-weight:600;margin-left:.4em}
.carmel-sp-rdate{color:#9298a5;font-size:.82em;margin-left:.4em}
.carmel-sp-rbody{margin-top:.3em;line-height:1.7}
.carmel-sp-norev{color:#888}
.carmel-sp-revform{margin:1em 0;border:1px solid #e7e2ef;border-radius:10px;padding:.5em 1em;background:#fff}
.carmel-sp-revform summary{cursor:pointer;font-weight:700}
.carmel-sp-revform label{display:block;font-size:.85em;color:#555;margin:.4em 0}
.carmel-sp-revform input[type=text],.carmel-sp-revform select,.carmel-sp-revform textarea{width:100%;border:1px solid #ccc;border-radius:.3em;padding:.45em;margin-top:.2em}
.carmel-sp-revnote{font-size:.78em;color:#888}
.carmel-sp-revbanner{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085;border-radius:.4em;padding:.6em 1em;margin:.5em 0}
.carmel-sp-revbanner.err{background:#fdecea;color:#a5281b;border-color:#c0392b}
</style>';
	}
}
