<?php
/**
 * Community (bbPress) + learning content (Notion) links.
 *
 * Per the constraints, learning content lives in Notion (external link, not
 * built into WP) and the community uses bbPress. This module surfaces those
 * via the [carmel_learning] shortcode: it shows the current user's store Notion
 * link plus the community forum link. Access follows the matrix — learning
 * content is for store/HQ staff (not customers); the community is open to all
 * logged-in roles.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Community {

	/** @var Carmel_Community|null */
	private static $instance = null;

	const SHORTCODE       = 'carmel_learning';
	const BOARD_SHORTCODE = 'carmel_community';
	const CPT             = 'carmel_community';
	const NEW_ACTION      = 'carmel_comm_new';
	const REPLY_ACTION    = 'carmel_comm_reply';
	const NONCE           = 'carmel_comm_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );

		// 組み込みコミュニティ掲示板（CARMEL内・bbPress非依存）。
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_shortcode( self::BOARD_SHORTCODE, array( $this, 'render_board' ) );
		add_action( 'admin_post_' . self::NEW_ACTION, array( $this, 'handle_new_topic' ) );
		add_action( 'admin_post_' . self::REPLY_ACTION, array( $this, 'handle_reply' ) );
	}

	/**
	 * コミュニティ投稿用CPT（非公開・管理画面で本部がモデレート可）。
	 */
	public function register_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'          => array(
					'name'          => 'コミュニティ',
					'singular_name' => 'コミュニティ投稿',
					'menu_name'     => 'コミュニティ',
				),
				'public'          => false,
				'show_ui'         => true,
				'show_in_menu'    => true,
				'menu_icon'       => 'dashicons-format-chat',
				'supports'        => array( 'title', 'editor', 'comments', 'author' ),
				'capability_type' => 'post',
				'has_archive'     => false,
				'exclude_from_search' => true,
			)
		);
	}

	/**
	 * コミュニティを利用できるロール。既定はログインユーザー全員。
	 *
	 * @return bool
	 */
	private function can_use_community() {
		return (bool) apply_filters( 'carmel_community_can_use', is_user_logged_in() );
	}

	/** Community (bbPress) URL from settings, if configured. */
	private function community_url() {
		$url = defined( 'CARMEL_COMMUNITY_URL' ) ? CARMEL_COMMUNITY_URL : get_option( 'carmel_community_url', '' );
		if ( '' === $url && function_exists( 'bbp_get_forums_url' ) ) {
			$url = bbp_get_forums_url();
		}
		return $url;
	}

	/**
	 * Notion learning URL for the current user's store
	 * (falls back to a global option).
	 */
	private function notion_url() {
		$store_id = (int) get_user_meta( get_current_user_id(), 'store_id', true );
		$url      = $store_id ? (string) get_post_meta( $store_id, 'notion_url', true ) : '';
		if ( '' === $url ) {
			$url = defined( 'CARMEL_NOTION_URL' ) ? CARMEL_NOTION_URL : get_option( 'carmel_notion_url', '' );
		}
		return $url;
	}

	/**
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return '<p class="carmel-notice">ログインするとご利用いただけます。</p>';
		}

		$is_staff   = current_user_can( 'carmel_change_deal_status' ) || current_user_can( 'carmel_manage_stores' );
		$notion     = $this->notion_url();
		$community  = $this->community_url();

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-learning"><div class="carmel-learn-grid">';

		// Learning content (staff/HQ only).
		if ( $is_staff ) {
			echo '<div class="carmel-learn-card">';
			echo '<h3>📘 学習コンテンツ</h3>';
			if ( $notion ) {
				echo '<p>マニュアル・研修資料（Notion）をご確認いただけます。</p>';
				echo '<a class="carmel-learn-btn" href="' . esc_url( $notion ) . '" target="_blank" rel="noopener">学習コンテンツを開く</a>';
			} else {
				echo '<p class="carmel-muted">学習コンテンツのリンクが未設定です。本部にお問い合わせください。</p>';
			}
			echo '</div>';
		}

		// Community (all logged-in roles).
		echo '<div class="carmel-learn-card">';
		echo '<h3>💬 コミュニティ</h3>';
		if ( $community ) {
			echo '<p>加盟店・本部の情報交換フォーラムです。</p>';
			echo '<a class="carmel-learn-btn" href="' . esc_url( $community ) . '" target="_blank" rel="noopener">コミュニティを開く</a>';
		} else {
			echo '<p class="carmel-muted">コミュニティのリンクが未設定です。</p>';
		}
		echo '</div>';

		echo '</div></div>';
		return ob_get_clean();
	}

	/* --------------------------------------------------------------------- *
	 * 組み込みコミュニティ掲示板 [carmel_community]
	 * --------------------------------------------------------------------- */

	public function render_board() {
		if ( ! $this->can_use_community() ) {
			return '<p class="carmel-notice">ログインするとコミュニティをご利用いただけます。</p>';
		}
		$topic_id = isset( $_GET['topic'] ) ? (int) $_GET['topic'] : 0;

		ob_start();
		echo $this->board_styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->board_banner(); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $topic_id && self::CPT === get_post_type( $topic_id ) ) {
			echo $this->render_topic( $topic_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		} else {
			echo $this->render_topic_list(); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		return ob_get_clean();
	}

	/** トピック一覧＋新規投稿フォーム。 */
	private function render_topic_list() {
		$topics = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		$out  = '<div class="carmel-comm"><h2>💬 コミュニティ</h2>';
		$out .= '<p class="carmel-comm-lead">加盟店・本部・ユーザーの情報交換の場です。気軽にご質問・共有ください。</p>';

		// 新規トピック。
		$nonce = wp_create_nonce( self::NEW_ACTION );
		$out  .= '<details class="carmel-comm-new"><summary>＋ 新しいトピックを投稿</summary>';
		$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::NEW_ACTION ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<input type="text" name="title" placeholder="タイトル" required>'
			. '<textarea name="body" rows="4" placeholder="内容" required></textarea>'
			. '<button type="submit" class="carmel-btn carmel-btn-purple">投稿する</button></form></details>';

		if ( empty( $topics ) ) {
			return $out . '<p>まだ投稿はありません。最初のトピックを投稿してみましょう。</p></div>';
		}

		$out .= '<ul class="carmel-comm-list">';
		foreach ( $topics as $t ) {
			$author  = get_the_author_meta( 'display_name', $t->post_author );
			$replies = get_comments_number( $t->ID );
			$link    = add_query_arg( 'topic', $t->ID, remove_query_arg( array( 'carmel_comm' ) ) );
			$out    .= '<li><a class="carmel-comm-ttl" href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $t->ID ) ) . '</a>'
				. '<div class="carmel-comm-meta">' . esc_html( $author ) . '・' . esc_html( get_the_date( 'Y-m-d', $t->ID ) )
				. '・返信 ' . (int) $replies . '</div></li>';
		}
		$out .= '</ul></div>';
		return $out;
	}

	/** 単一トピック（本文＋返信＋返信フォーム）。 */
	private function render_topic( $topic_id ) {
		$post    = get_post( $topic_id );
		$author  = get_the_author_meta( 'display_name', $post->post_author );
		$back    = remove_query_arg( array( 'topic', 'carmel_comm' ) );

		$out  = '<div class="carmel-comm"><a class="carmel-comm-back" href="' . esc_url( $back ) . '">← 一覧へ戻る</a>';
		$out .= '<article class="carmel-comm-topic"><h2>' . esc_html( get_the_title( $topic_id ) ) . '</h2>';
		$out .= '<div class="carmel-comm-meta">' . esc_html( $author ) . '・' . esc_html( get_the_date( 'Y-m-d', $topic_id ) ) . '</div>';
		$out .= '<div class="carmel-comm-body">' . wp_kses_post( wpautop( $post->post_content ) ) . '</div></article>';

		// 返信一覧。
		$comments = get_comments( array( 'post_id' => $topic_id, 'status' => 'approve', 'order' => 'ASC' ) );
		$out     .= '<h3 class="carmel-comm-reph">返信（' . count( $comments ) . '）</h3>';
		if ( $comments ) {
			$out .= '<ul class="carmel-comm-replies">';
			foreach ( $comments as $c ) {
				$out .= '<li><div class="carmel-comm-meta">' . esc_html( $c->comment_author ) . '・' . esc_html( mysql2date( 'Y-m-d H:i', $c->comment_date ) ) . '</div>'
					. '<div class="carmel-comm-rbody">' . nl2br( esc_html( $c->comment_content ) ) . '</div></li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<p>まだ返信はありません。</p>';
		}

		// 返信フォーム。
		$nonce = wp_create_nonce( self::REPLY_ACTION . '_' . $topic_id );
		$out  .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-comm-replyform">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::REPLY_ACTION ) . '">'
			. '<input type="hidden" name="topic_id" value="' . (int) $topic_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<textarea name="body" rows="3" placeholder="返信を書く" required></textarea>'
			. '<button type="submit" class="carmel-btn carmel-btn-purple">返信する</button></form>';

		$out .= '</div>';
		return $out;
	}

	public function handle_new_topic() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		if ( ! $this->can_use_community() ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::NEW_ACTION ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$body  = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		if ( '' === $title || '' === $body ) {
			wp_safe_redirect( add_query_arg( 'carmel_comm', 'err', $redirect ) );
			exit;
		}
		$id = wp_insert_post(
			array(
				'post_type'    => self::CPT,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $body,
				'post_author'  => get_current_user_id(),
			)
		);
		if ( is_wp_error( $id ) || ! $id ) {
			wp_safe_redirect( add_query_arg( 'carmel_comm', 'err', $redirect ) );
			exit;
		}
		do_action( 'carmel_community_topic_created', (int) $id );
		wp_safe_redirect( add_query_arg( array( 'topic' => (int) $id, 'carmel_comm' => 'new_ok' ), remove_query_arg( 'carmel_comm', $redirect ) ) );
		exit;
	}

	public function handle_reply() {
		$topic_id = isset( $_POST['topic_id'] ) ? (int) $_POST['topic_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		if ( ! $this->can_use_community() ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::REPLY_ACTION . '_' . $topic_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( self::CPT !== get_post_type( $topic_id ) ) {
			wp_die( esc_html__( 'トピックが見つかりません。', 'carmel-core' ), '', array( 'response' => 404 ) );
		}
		$body = isset( $_POST['body'] ) ? sanitize_textarea_field( wp_unslash( $_POST['body'] ) ) : '';
		if ( '' === $body ) {
			wp_safe_redirect( add_query_arg( array( 'topic' => $topic_id, 'carmel_comm' => 'err' ), $redirect ) );
			exit;
		}
		$user = wp_get_current_user();
		wp_insert_comment(
			array(
				'comment_post_ID'      => $topic_id,
				'comment_content'      => $body,
				'user_id'              => $user->ID,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_approved'     => 1,
			)
		);
		do_action( 'carmel_community_reply_created', $topic_id, $user->ID );
		wp_safe_redirect( add_query_arg( array( 'topic' => $topic_id, 'carmel_comm' => 'reply_ok' ), remove_query_arg( 'carmel_comm', $redirect ) ) );
		exit;
	}

	private function board_banner() {
		$key = isset( $_GET['carmel_comm'] ) ? sanitize_key( $_GET['carmel_comm'] ) : '';
		$map = array(
			'new_ok'   => array( 'success', 'トピックを投稿しました。' ),
			'reply_ok' => array( 'success', '返信を投稿しました。' ),
			'err'      => array( 'error', '投稿できませんでした。入力をご確認ください。' ),
		);
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $key ][0] ) . '">' . esc_html( $map[ $key ][1] ) . '</div>';
	}

	private function board_styles() {
		return '<style>
.carmel-comm{font-size:14px;max-width:760px}
.carmel-comm-lead{color:#7a7488}
.carmel-comm-new{border:1px solid #e7e2ef;border-radius:10px;padding:.5em 1em;margin:1em 0;background:#fff}
.carmel-comm-new summary{cursor:pointer;font-weight:700;padding:.3em 0}
.carmel-comm-new input,.carmel-comm-new textarea,.carmel-comm-replyform textarea{width:100%;border:1px solid #ccc;border-radius:.3em;padding:.5em;margin:.3em 0}
.carmel-comm-list{list-style:none;padding:0;margin:1em 0}
.carmel-comm-list li{border-top:1px solid #ece6f5;padding:.7em 0}
.carmel-comm-ttl{font-weight:700;font-size:1.05em;text-decoration:none;color:#5b2a86}
.carmel-comm-meta{color:#9298a5;font-size:.82em;margin-top:.2em}
.carmel-comm-back{display:inline-block;margin:.4em 0;color:#6b4fbb;text-decoration:none}
.carmel-comm-topic{border:1px solid #e7e2ef;border-radius:12px;padding:1em 1.2em;background:#fff;margin:.4em 0 1em}
.carmel-comm-body{line-height:1.85;margin-top:.5em}
.carmel-comm-reph{border-bottom:2px solid #e7e2ef;padding-bottom:.3em}
.carmel-comm-replies{list-style:none;padding:0;margin:0}
.carmel-comm-replies li{border:1px solid #eef0f4;border-radius:10px;padding:.7em 1em;margin:.5em 0;background:#fff}
.carmel-comm-rbody{margin-top:.3em;line-height:1.7}
.carmel-comm-replyform{margin-top:1em}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.5em 1.1em;color:#fff;cursor:pointer;font-size:.9em;text-decoration:none}
.carmel-btn-purple{background:#6b4fbb}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#f4f6fb;border:1px solid #cdd2dc;border-radius:.4em}
</style>';
	}

	private function styles() {
		return '<style>
.carmel-learning{font-size:14px}
.carmel-learn-grid{display:flex;gap:1em;flex-wrap:wrap}
.carmel-learn-card{flex:1;min-width:240px;border:1px solid #e0e3ea;border-radius:.6em;padding:1.2em;background:#fff}
.carmel-learn-card h3{margin:0 0 .5em}
.carmel-learn-btn{display:inline-block;margin-top:.5em;background:#2e86de;color:#fff;text-decoration:none;border-radius:.4em;padding:.5em 1.1em}
.carmel-muted{color:#888}
.carmel-notice{padding:1em;background:#f4f6fb;border:1px solid #cdd2dc;border-radius:.4em}
</style>';
	}
}
