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
	const PIN_ACTION      = 'carmel_comm_pin';
	const LIKE_ACTION     = 'carmel_comm_like';
	const BEST_ACTION     = 'carmel_comm_best';
	const NONCE           = 'carmel_comm_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** トピックのカテゴリ（フィルタで調整可能）。 */
	public static function categories() {
		return apply_filters(
			'carmel_community_categories',
			array(
				'announce' => 'お知らせ',
				'question' => '質問',
				'case'     => '事例共有',
				'sales'    => '販売・在庫',
				'chat'     => '雑談',
			)
		);
	}

	public static function category_label( $key ) {
		$c = self::categories();
		return isset( $c[ $key ] ) ? $c[ $key ] : 'その他';
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );

		// 組み込みコミュニティ掲示板（CARMEL内・bbPress非依存）。
		add_action( 'init', array( $this, 'register_cpt' ) );
		add_shortcode( self::BOARD_SHORTCODE, array( $this, 'render_board' ) );
		add_action( 'admin_post_' . self::NEW_ACTION, array( $this, 'handle_new_topic' ) );
		add_action( 'admin_post_' . self::REPLY_ACTION, array( $this, 'handle_reply' ) );
		add_action( 'admin_post_' . self::PIN_ACTION, array( $this, 'handle_pin' ) );
		add_action( 'wp_ajax_carmel_comm_mention', array( $this, 'ajax_mention' ) );
		add_action( 'admin_post_' . self::LIKE_ACTION, array( $this, 'handle_like' ) );
		add_action( 'admin_post_' . self::BEST_ACTION, array( $this, 'handle_best' ) );

		// 新着トピック・返信の通知ルーティング／文面。
		add_filter( 'carmel_routing_table', array( $this, 'add_routing' ) );
		add_filter( 'carmel_notification_message', array( $this, 'add_message' ), 10, 3 );
	}

	public function add_routing( $table ) {
		// 新着トピックは本部へ（モデレート・把握用）。
		$table['community_new_topic'] = array(
			array( 'audience' => 'hq', 'channel' => 'lineworks', 'fallback' => 'mail' ),
		);
		// 返信は投稿者本人へ（recipient_id で指定）。
		$table['community_reply'] = array(
			array( 'audience' => 'customer', 'channel' => 'proline', 'fallback' => 'mail' ),
		);
		// メンションされた本人へ（recipient_id で指定）。
		$table['community_mention'] = array(
			array( 'audience' => 'customer', 'channel' => 'proline', 'fallback' => 'mail' ),
		);
		// いいねされた本人へ（recipient_id で指定）。
		$table['community_like'] = array(
			array( 'audience' => 'customer', 'channel' => 'proline', 'fallback' => 'mail' ),
		);
		return $table;
	}

	public function add_message( $message, $event_type, $context ) {
		$vars = isset( $context['vars'] ) ? (array) $context['vars'] : array();
		if ( 'community_new_topic' === $event_type ) {
			$title = isset( $vars['title'] ) ? $vars['title'] : '';
			$by    = isset( $vars['author'] ) ? $vars['author'] : '';
			$message['subject'] = 'コミュニティ：新着トピック';
			$message['body']    = "新しいトピックが投稿されました。\n「" . $title . '」（投稿者：' . $by . '）';
		} elseif ( 'community_reply' === $event_type ) {
			$title = isset( $vars['title'] ) ? $vars['title'] : '';
			$by    = isset( $vars['author'] ) ? $vars['author'] : '';
			$message['subject'] = 'コミュニティ：あなたのトピックに返信';
			$message['body']    = '「' . $title . '」に ' . $by . ' さんから返信がありました。コミュニティでご確認ください。';
		} elseif ( 'community_mention' === $event_type ) {
			$title = isset( $vars['title'] ) ? $vars['title'] : '';
			$by    = isset( $vars['author'] ) ? $vars['author'] : '';
			$message['subject'] = 'コミュニティ：あなたへのメンション';
			$message['body']    = $by . ' さんがコミュニティ「' . $title . '」であなたにメンションしました。';
		} elseif ( 'community_like' === $event_type ) {
			$title = isset( $vars['title'] ) ? $vars['title'] : '';
			$by    = isset( $vars['author'] ) ? $vars['author'] : '';
			$message['subject'] = 'コミュニティ：👍がつきました';
			$message['body']    = $by . ' さんがコミュニティ「' . $title . '」のあなたの投稿にいいねしました。';
		}
		return $message;
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

	/**
	 * 添付画像をアップロードして添付ファイルIDを返す（画像のみ・任意）。
	 *
	 * @param int $parent_id 親投稿ID。
	 * @return int 添付ID（無し/失敗時0）。
	 */
	private function handle_image_upload( $parent_id ) {
		if ( empty( $_FILES['image']['name'] ) ) {
			return 0;
		}
		$check = wp_check_filetype( $_FILES['image']['name'], array( 'jpg|jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp' ) );
		if ( ! $check['type'] ) {
			return 0;
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		// ロール未更新の環境でも添付できるよう、本処理中のみ upload_files を許可。
		$grant = null;
		if ( ! current_user_can( 'upload_files' ) ) {
			$grant = function ( $allcaps ) {
				$allcaps['upload_files'] = true;
				return $allcaps;
			};
			add_filter( 'user_has_cap', $grant );
		}
		$att_id = media_handle_upload( 'image', $parent_id );
		if ( $grant ) {
			remove_filter( 'user_has_cap', $grant );
		}
		return is_wp_error( $att_id ) ? 0 : (int) $att_id;
	}

	/**
	 * 本文中の @ユーザー名 を抽出し、該当ユーザーへ通知する。
	 *
	 * @param string $body
	 * @param int    $topic_id
	 */
	private function notify_mentions( $body, $topic_id ) {
		if ( ! preg_match_all( '/@([A-Za-z0-9_\-\.]{2,60})/', $body, $m ) ) {
			return;
		}
		$me   = get_current_user_id();
		$seen = array();
		foreach ( array_unique( $m[1] ) as $login ) {
			$user = get_user_by( 'login', $login );
			if ( ! $user || (int) $user->ID === $me || isset( $seen[ $user->ID ] ) ) {
				continue;
			}
			$seen[ $user->ID ] = true;
			Carmel_Notifier::notify(
				'community_mention',
				array(
					'event_id'     => 'community_mention:' . $topic_id . ':' . $user->ID . ':' . time(),
					'recipient_id' => (int) $user->ID,
					'vars'         => array( 'title' => get_the_title( $topic_id ), 'author' => wp_get_current_user()->display_name ),
				)
			);
		}
	}

	/** @ユーザー名 を強調表示に変換（エスケープ後のHTMLを返す）。 */
	private function format_text( $text ) {
		$text = esc_html( $text );
		$text = preg_replace( '/@([A-Za-z0-9_\-\.]{2,60})/', '<span class="carmel-mention">@$1</span>', $text );
		return nl2br( $text );
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
		echo $this->mention_js(); // phpcs:ignore WordPress.Security.EscapeOutput
		return ob_get_clean();
	}

	/**
	 * メンション候補をユーザー名で検索（AJAX・ログイン必須）。
	 */
	public function ajax_mention() {
		if ( ! is_user_logged_in() ) {
			wp_send_json( array() );
		}
		check_ajax_referer( self::NONCE, 'nonce' );
		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		if ( strlen( $term ) < 1 ) {
			wp_send_json( array() );
		}
		$users = get_users(
			array(
				'search'         => '*' . $term . '*',
				'search_columns' => array( 'user_login', 'display_name', 'user_nicename' ),
				'number'         => 8,
				'fields'         => array( 'user_login', 'display_name' ),
			)
		);
		$out = array();
		foreach ( $users as $u ) {
			$out[] = array( 'login' => $u->user_login, 'name' => $u->display_name );
		}
		wp_send_json( $out );
	}

	/** @メンション候補のサジェストUI（コミュニティのtextarea向け）。 */
	private function mention_js() {
		$nonce = wp_create_nonce( self::NONCE );
		ob_start();
		?>
<style>
.carmel-mention-box{position:absolute;z-index:50;background:#fff;border:1px solid #ddd2f5;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,.12);min-width:200px;max-height:220px;overflow:auto}
.carmel-mention-box div{padding:.45em .8em;cursor:pointer;font-size:.9em}
.carmel-mention-box div:hover,.carmel-mention-box div.on{background:#f1ecfb}
.carmel-mention-box small{color:#9298a5}
</style>
<script>
(function(){
	var ajax='<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>',nonce='<?php echo esc_js( $nonce ); ?>';
	var box=null,active=-1,items=[],target=null,timer=null;
	function close(){if(box){box.remove();box=null;}active=-1;items=[];}
	function tokenAt(ta){
		var v=ta.value.slice(0,ta.selectionStart);var m=v.match(/@([A-Za-z0-9_\-\.]*)$/);return m?m[1]:null;
	}
	function place(ta){
		var r=ta.getBoundingClientRect();
		box.style.left=(window.scrollX+r.left)+'px';
		box.style.top=(window.scrollY+r.bottom+2)+'px';
	}
	function render(){
		if(!box){box=document.createElement('div');box.className='carmel-mention-box';document.body.appendChild(box);}
		box.innerHTML='';
		items.forEach(function(it,i){
			var d=document.createElement('div');d.className=(i===active?'on':'');
			d.innerHTML='@'+it.login+' <small>'+(it.name||'')+'</small>';
			d.addEventListener('mousedown',function(e){e.preventDefault();pick(i);});
			box.appendChild(d);
		});
		place(target);
	}
	function pick(i){
		var it=items[i];if(!it||!target)return;
		var s=target.selectionStart,v=target.value;
		var before=v.slice(0,s).replace(/@([A-Za-z0-9_\-\.]*)$/,'@'+it.login+' ');
		target.value=before+v.slice(s);target.focus();close();
	}
	function search(ta,term){
		clearTimeout(timer);
		timer=setTimeout(function(){
			fetch(ajax+'?action=carmel_comm_mention&nonce='+nonce+'&term='+encodeURIComponent(term))
				.then(function(r){return r.json();}).then(function(list){
					items=list||[];active=items.length?0:-1;if(items.length){target=ta;render();}else close();
				}).catch(close);
		},180);
	}
	document.querySelectorAll('.carmel-comm textarea').forEach(function(ta){
		ta.addEventListener('input',function(){var t=tokenAt(ta);if(t===null){close();return;}target=ta;search(ta,t);});
		ta.addEventListener('keydown',function(e){
			if(!box)return;
			if(e.key==='ArrowDown'){active=(active+1)%items.length;render();e.preventDefault();}
			else if(e.key==='ArrowUp'){active=(active-1+items.length)%items.length;render();e.preventDefault();}
			else if(e.key==='Enter'&&active>=0){pick(active);e.preventDefault();}
			else if(e.key==='Escape'){close();}
		});
		ta.addEventListener('blur',function(){setTimeout(close,150);});
	});
})();
</script>
		<?php
		return ob_get_clean();
	}

	/** トピック一覧＋新規投稿フォーム。 */
	private function render_topic_list() {
		$cat = isset( $_GET['cat'] ) ? sanitize_key( $_GET['cat'] ) : '';

		$meta_query = array( 'relation' => 'AND' );
		if ( $cat && isset( self::categories()[ $cat ] ) ) {
			$meta_query[] = array( 'key' => 'category', 'value' => $cat );
		}
		$topics = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 80,
				// ピン留め優先 → 更新日。
				'meta_key'       => 'pinned',
				'orderby'        => array( 'meta_value_num' => 'DESC', 'modified' => 'DESC' ),
				'meta_query'     => count( $meta_query ) > 1 ? $meta_query : array(),
			)
		);

		$out  = '<div class="carmel-comm"><h2>💬 コミュニティ</h2>';
		$out .= '<p class="carmel-comm-lead">加盟店・本部・ユーザーの情報交換の場です。気軽にご質問・共有ください。</p>';

		// 新規トピック。
		$nonce = wp_create_nonce( self::NEW_ACTION );
		$out  .= '<details class="carmel-comm-new"><summary>＋ 新しいトピックを投稿</summary>';
		$out  .= '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::NEW_ACTION ) . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<input type="text" name="title" placeholder="タイトル" required>'
			. '<select name="category">';
		foreach ( self::categories() as $k => $label ) {
			$out .= '<option value="' . esc_attr( $k ) . '">' . esc_html( $label ) . '</option>';
		}
		$out .= '</select>'
			. '<textarea name="body" rows="4" placeholder="内容（@ユーザー名 でメンションできます）" required></textarea>'
			. '<label class="carmel-comm-file">画像を添付（任意）<input type="file" name="image" accept="image/*"></label>'
			. '<button type="submit" class="carmel-btn carmel-btn-purple">投稿する</button></form></details>';

		// 人気のトピック（いいね数ランキング）。
		$out .= $this->ranking_block();

		// カテゴリ絞り込みタブ。
		$base = remove_query_arg( array( 'carmel_comm', 'cat' ) );
		$out .= '<div class="carmel-comm-cats"><a class="' . ( '' === $cat ? 'on' : '' ) . '" href="' . esc_url( $base ) . '">すべて</a>';
		foreach ( self::categories() as $k => $label ) {
			$url  = add_query_arg( 'cat', $k, $base );
			$out .= '<a class="' . ( $cat === $k ? 'on' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		$out .= '</div>';

		if ( empty( $topics ) ) {
			return $out . '<p>まだ投稿はありません。最初のトピックを投稿してみましょう。</p></div>';
		}

		$out .= '<ul class="carmel-comm-list">';
		foreach ( $topics as $t ) {
			$author  = get_the_author_meta( 'display_name', $t->post_author );
			$replies = get_comments_number( $t->ID );
			$tcat    = (string) get_post_meta( $t->ID, 'category', true );
			$pinned  = $this->is_pinned( $t->ID );
			$link    = add_query_arg( 'topic', $t->ID, remove_query_arg( array( 'carmel_comm', 'cat' ) ) );
			$out    .= '<li>';
			if ( $pinned ) {
				$out .= '<span class="carmel-comm-pin">📌 固定</span> ';
			}
			if ( $tcat ) {
				$out .= '<span class="carmel-comm-cat">' . esc_html( self::category_label( $tcat ) ) . '</span> ';
			}
			if ( (int) get_post_meta( $t->ID, 'best_answer', true ) ) {
				$out .= '<span class="carmel-comm-solved">✔ 解決済</span> ';
			}
			$out .= '<a class="carmel-comm-ttl" href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $t->ID ) ) . '</a>'
				. '<div class="carmel-comm-meta">' . esc_html( $author ) . '・' . esc_html( get_the_date( 'Y-m-d', $t->ID ) )
				. '・返信 ' . (int) $replies . '</div></li>';
		}
		$out .= '</ul></div>';
		return $out;
	}

	private function is_pinned( $topic_id ) {
		return in_array( (string) get_post_meta( $topic_id, 'pinned', true ), array( '1', 'yes', 'true' ), true );
	}

	/** いいね数ランキング（上位5・count>0）。 */
	private function ranking_block() {
		$top = get_posts(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'publish',
				'posts_per_page' => 5,
				'meta_key'       => '_carmel_like_count',
				'orderby'        => 'meta_value_num',
				'order'          => 'DESC',
				'meta_query'     => array(
					array( 'key' => '_carmel_like_count', 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ),
				),
			)
		);
		if ( empty( $top ) ) {
			return '';
		}
		$out = '<div class="carmel-rank"><h3>🔥 人気のトピック</h3><ol class="carmel-rank-list">';
		foreach ( $top as $t ) {
			$likes = (int) get_post_meta( $t->ID, '_carmel_like_count', true );
			$link  = add_query_arg( 'topic', $t->ID, remove_query_arg( array( 'carmel_comm', 'cat' ) ) );
			$out  .= '<li><a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( $t->ID ) ) . '</a> <span class="carmel-rank-likes">👍' . $likes . '</span></li>';
		}
		$out .= '</ol></div>';
		return $out;
	}

	/** 単一トピック（本文＋返信＋返信フォーム）。 */
	private function render_topic( $topic_id ) {
		$post    = get_post( $topic_id );
		$author  = get_the_author_meta( 'display_name', $post->post_author );
		$back    = remove_query_arg( array( 'topic', 'carmel_comm' ) );

		$out  = '<div class="carmel-comm"><a class="carmel-comm-back" href="' . esc_url( $back ) . '">← 一覧へ戻る</a>';
		$out .= '<article class="carmel-comm-topic">';
		$out .= '<div class="carmel-comm-topbar">';
		$tcat = (string) get_post_meta( $topic_id, 'category', true );
		if ( $this->is_pinned( $topic_id ) ) {
			$out .= '<span class="carmel-comm-pin">📌 固定</span> ';
		}
		if ( $tcat ) {
			$out .= '<span class="carmel-comm-cat">' . esc_html( self::category_label( $tcat ) ) . '</span>';
		}
		$out .= $this->pin_button( $topic_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		$out .= '</div>';
		$out .= '<h2>' . esc_html( get_the_title( $topic_id ) ) . '</h2>';
		$out .= '<div class="carmel-comm-meta">' . esc_html( $author ) . '・' . esc_html( get_the_date( 'Y-m-d', $topic_id ) ) . '</div>';
		if ( has_post_thumbnail( $topic_id ) ) {
			$out .= '<div class="carmel-comm-img">' . get_the_post_thumbnail( $topic_id, 'medium' ) . '</div>';
		}
		$out .= '<div class="carmel-comm-body">' . $this->format_text( $post->post_content ) . '</div>';
		$out .= '<div class="carmel-comm-react">' . $this->like_button( 'topic', $topic_id ) . '</div></article>';

		// 返信一覧（ベストアンサーを先頭に）。
		$comments = get_comments( array( 'post_id' => $topic_id, 'status' => 'approve', 'order' => 'ASC' ) );
		$best     = (int) get_post_meta( $topic_id, 'best_answer', true );
		if ( $best ) {
			usort(
				$comments,
				function ( $a, $b ) use ( $best ) {
					$ab = ( (int) $a->comment_ID === $best ) ? 0 : 1;
					$bb = ( (int) $b->comment_ID === $best ) ? 0 : 1;
					if ( $ab === $bb ) {
						return strcmp( $a->comment_date, $b->comment_date );
					}
					return $ab - $bb;
				}
			);
		}
		$out .= '<h3 class="carmel-comm-reph">返信（' . count( $comments ) . '）</h3>';
		if ( $comments ) {
			$out .= '<ul class="carmel-comm-replies">';
			foreach ( $comments as $c ) {
				$cid     = (int) $c->comment_ID;
				$img     = (int) get_comment_meta( $cid, 'carmel_image', true );
				$imghtml = $img ? '<div class="carmel-comm-img">' . wp_get_attachment_image( $img, 'medium' ) . '</div>' : '';
				$is_best = ( $best === $cid );
				$out    .= '<li class="' . ( $is_best ? 'carmel-best' : '' ) . '">';
				if ( $is_best ) {
					$out .= '<div class="carmel-best-badge">✔ ベストアンサー</div>';
				}
				$out .= '<div class="carmel-comm-meta">' . esc_html( $c->comment_author ) . '・' . esc_html( mysql2date( 'Y-m-d H:i', $c->comment_date ) ) . '</div>'
					. $imghtml
					. '<div class="carmel-comm-rbody">' . $this->format_text( $c->comment_content ) . '</div>'
					. '<div class="carmel-comm-react">' . $this->like_button( 'reply', $cid ) . $this->best_button( $topic_id, $cid ) . '</div>'
					. '</li>';
			}
			$out .= '</ul>';
		} else {
			$out .= '<p>まだ返信はありません。</p>';
		}

		// 返信フォーム。
		$nonce = wp_create_nonce( self::REPLY_ACTION . '_' . $topic_id );
		$out  .= '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-comm-replyform">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::REPLY_ACTION ) . '">'
			. '<input type="hidden" name="topic_id" value="' . (int) $topic_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<textarea name="body" rows="3" placeholder="返信を書く（@ユーザー名 でメンション）" required></textarea>'
			. '<label class="carmel-comm-file">画像を添付（任意）<input type="file" name="image" accept="image/*"></label>'
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
		$cat   = isset( $_POST['category'] ) ? sanitize_key( $_POST['category'] ) : '';
		if ( ! isset( self::categories()[ $cat ] ) ) {
			$cat = 'chat';
		}
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
				'meta_input'   => array( 'category' => $cat, 'pinned' => 0 ),
			)
		);
		if ( is_wp_error( $id ) || ! $id ) {
			wp_safe_redirect( add_query_arg( 'carmel_comm', 'err', $redirect ) );
			exit;
		}
		// 添付画像（任意）。
		$att = $this->handle_image_upload( (int) $id );
		if ( $att ) {
			set_post_thumbnail( (int) $id, $att );
		}

		do_action( 'carmel_community_topic_created', (int) $id );

		// 本部へ新着通知。
		Carmel_Notifier::notify(
			'community_new_topic',
			array(
				'event_id' => 'community_new_topic:' . (int) $id,
				'vars'     => array( 'title' => $title, 'author' => wp_get_current_user()->display_name ),
			)
		);

		// メンション通知。
		$this->notify_mentions( $body, (int) $id );

		wp_safe_redirect( add_query_arg( array( 'topic' => (int) $id, 'carmel_comm' => 'new_ok' ), remove_query_arg( 'carmel_comm', $redirect ) ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * いいね / ベストアンサー
	 * --------------------------------------------------------------------- */

	/** いいねした人のID配列（topic=投稿メタ / reply=コメントメタ）。 */
	private function likers( $ctype, $id ) {
		$v = ( 'reply' === $ctype ) ? get_comment_meta( $id, '_carmel_likes', true ) : get_post_meta( $id, '_carmel_likes', true );
		return is_array( $v ) ? array_map( 'intval', $v ) : array();
	}

	private function like_button( $ctype, $id ) {
		if ( ! $this->can_use_community() ) {
			return '';
		}
		$likers = $this->likers( $ctype, $id );
		$on     = in_array( get_current_user_id(), $likers, true );
		$nonce  = wp_create_nonce( self::LIKE_ACTION . '_' . $ctype . '_' . $id );
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-like-form">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::LIKE_ACTION ) . '">'
			. '<input type="hidden" name="ctype" value="' . esc_attr( $ctype ) . '">'
			. '<input type="hidden" name="id" value="' . (int) $id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<button type="submit" class="carmel-like-btn' . ( $on ? ' on' : '' ) . '">👍 <span>' . count( $likers ) . '</span></button></form>';
	}

	public function handle_like() {
		$ctype    = isset( $_POST['ctype'] ) ? sanitize_key( $_POST['ctype'] ) : '';
		$id       = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		if ( ! in_array( $ctype, array( 'topic', 'reply' ), true ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::LIKE_ACTION . '_' . $ctype . '_' . $id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! $this->can_use_community() ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$uid    = get_current_user_id();
		$likers = $this->likers( $ctype, $id );
		$added  = false;
		if ( in_array( $uid, $likers, true ) ) {
			$likers = array_values( array_diff( $likers, array( $uid ) ) );
		} else {
			$likers[] = $uid;
			$added    = true;
		}
		if ( 'reply' === $ctype ) {
			$comment  = get_comment( $id );
			$topic_id = (int) $comment->comment_post_ID;
			update_comment_meta( $id, '_carmel_likes', $likers );
			$author_id = (int) $comment->user_id;
		} else {
			$topic_id = $id;
			update_post_meta( $id, '_carmel_likes', $likers );
			update_post_meta( $id, '_carmel_like_count', count( $likers ) ); // ランキング用。
			$author_id = (int) get_post_field( 'post_author', $id );
		}

		// いいねされた本人へ通知（新規いいね・自分以外）。
		if ( $added && $author_id && $author_id !== $uid ) {
			Carmel_Notifier::notify(
				'community_like',
				array(
					'event_id'     => 'community_like:' . $ctype . ':' . $id . ':' . $uid,
					'recipient_id' => $author_id,
					'vars'         => array( 'title' => get_the_title( $topic_id ), 'author' => wp_get_current_user()->display_name ),
				)
			);
		}

		wp_safe_redirect( add_query_arg( 'topic', $topic_id, remove_query_arg( 'carmel_comm', $redirect ) ) );
		exit;
	}

	/** ベストアンサーに設定できるか（トピック投稿者 or 本部）。 */
	private function can_set_best( $topic_id ) {
		return current_user_can( 'carmel_manage_stores' ) || (int) get_post_field( 'post_author', $topic_id ) === get_current_user_id();
	}

	private function best_button( $topic_id, $comment_id ) {
		if ( ! $this->can_set_best( $topic_id ) ) {
			return '';
		}
		$current = (int) get_post_meta( $topic_id, 'best_answer', true );
		$is_best = ( $current === (int) $comment_id );
		$nonce   = wp_create_nonce( self::BEST_ACTION . '_' . $comment_id );
		$label   = $is_best ? 'ベスト解除' : 'ベストアンサーにする';
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-best-form">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::BEST_ACTION ) . '">'
			. '<input type="hidden" name="comment_id" value="' . (int) $comment_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<button type="submit" class="carmel-best-btn">' . esc_html( $label ) . '</button></form>';
	}

	public function handle_best() {
		$comment_id = isset( $_POST['comment_id'] ) ? (int) $_POST['comment_id'] : 0;
		$redirect   = wp_get_referer() ? wp_get_referer() : home_url( '/' );
		$comment    = $comment_id ? get_comment( $comment_id ) : null;
		if ( ! $comment ) {
			wp_die( esc_html__( '返信が見つかりません。', 'carmel-core' ), '', array( 'response' => 404 ) );
		}
		$topic_id = (int) $comment->comment_post_ID;
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::BEST_ACTION . '_' . $comment_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! $this->can_set_best( $topic_id ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$current = (int) get_post_meta( $topic_id, 'best_answer', true );
		update_post_meta( $topic_id, 'best_answer', $current === $comment_id ? 0 : $comment_id );
		wp_safe_redirect( add_query_arg( 'topic', $topic_id, remove_query_arg( 'carmel_comm', $redirect ) ) );
		exit;
	}

	/** 本部向けのピン留めトグルボタン（HQ以外には何も出さない）。 */
	private function pin_button( $topic_id ) {
		if ( ! current_user_can( 'carmel_manage_stores' ) ) {
			return '';
		}
		$pinned = $this->is_pinned( $topic_id );
		$nonce  = wp_create_nonce( self::PIN_ACTION . '_' . $topic_id );
		$label  = $pinned ? '固定を解除' : '上部に固定';
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-comm-pinform">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::PIN_ACTION ) . '">'
			. '<input type="hidden" name="topic_id" value="' . (int) $topic_id . '">'
			. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">'
			. '<button type="submit" class="carmel-btn carmel-btn-ghost">' . esc_html( $label ) . '</button></form>';
	}

	public function handle_pin() {
		$topic_id = isset( $_POST['topic_id'] ) ? (int) $_POST['topic_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		if ( ! current_user_can( 'carmel_manage_stores' ) ) {
			wp_die( esc_html__( '固定は本部のみ可能です。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::PIN_ACTION . '_' . $topic_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( self::CPT !== get_post_type( $topic_id ) ) {
			wp_die( esc_html__( 'トピックが見つかりません。', 'carmel-core' ), '', array( 'response' => 404 ) );
		}
		update_post_meta( $topic_id, 'pinned', $this->is_pinned( $topic_id ) ? 0 : 1 );
		wp_safe_redirect( add_query_arg( array( 'topic' => $topic_id, 'carmel_comm' => 'pin_ok' ), remove_query_arg( 'carmel_comm', $redirect ) ) );
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
		$user       = wp_get_current_user();
		$comment_id = wp_insert_comment(
			array(
				'comment_post_ID'      => $topic_id,
				'comment_content'      => $body,
				'user_id'              => $user->ID,
				'comment_author'       => $user->display_name,
				'comment_author_email' => $user->user_email,
				'comment_approved'     => 1,
			)
		);

		// 添付画像（任意）→ コメントメタに保存。
		$att = $this->handle_image_upload( $topic_id );
		if ( $att && $comment_id ) {
			update_comment_meta( (int) $comment_id, 'carmel_image', $att );
		}

		do_action( 'carmel_community_reply_created', $topic_id, $user->ID );

		// メンション通知。
		$this->notify_mentions( $body, $topic_id );

		// トピック投稿者へ返信通知（自分の返信は除く）。
		$author_id = (int) get_post_field( 'post_author', $topic_id );
		if ( $author_id && $author_id !== (int) $user->ID ) {
			Carmel_Notifier::notify(
				'community_reply',
				array(
					'event_id'     => 'community_reply:' . $topic_id . ':' . time(),
					'recipient_id' => $author_id,
					'vars'         => array( 'title' => get_the_title( $topic_id ), 'author' => $user->display_name ),
				)
			);
		}

		wp_safe_redirect( add_query_arg( array( 'topic' => $topic_id, 'carmel_comm' => 'reply_ok' ), remove_query_arg( 'carmel_comm', $redirect ) ) );
		exit;
	}

	private function board_banner() {
		$key = isset( $_GET['carmel_comm'] ) ? sanitize_key( $_GET['carmel_comm'] ) : '';
		$map = array(
			'new_ok'   => array( 'success', 'トピックを投稿しました。' ),
			'reply_ok' => array( 'success', '返信を投稿しました。' ),
			'pin_ok'   => array( 'success', '固定状態を更新しました。' ),
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
.carmel-comm-new input,.carmel-comm-new textarea,.carmel-comm-new select,.carmel-comm-replyform textarea{width:100%;border:1px solid #ccc;border-radius:.3em;padding:.5em;margin:.3em 0}
.carmel-comm-cats{display:flex;gap:.4em;flex-wrap:wrap;margin:.8em 0}
.carmel-comm-cats a{font-size:.85em;text-decoration:none;color:#6b4fbb;border:1px solid #ddd2f5;border-radius:1em;padding:.2em .9em}
.carmel-comm-cats a.on{background:#6b4fbb;color:#fff;border-color:#6b4fbb}
.carmel-comm-pin{background:#e67e22;color:#fff;border-radius:.3em;padding:.05em .5em;font-size:.78em}
.carmel-comm-cat{background:#eee9fb;color:#6b4fbb;border-radius:.3em;padding:.05em .5em;font-size:.8em}
.carmel-comm-topbar{display:flex;gap:.5em;align-items:center;flex-wrap:wrap;margin-bottom:.3em}
.carmel-comm-pinform{margin:0 0 0 auto}
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
.carmel-comm-img{margin:.5em 0}
.carmel-comm-img img{max-width:100%;height:auto;border-radius:8px}
.carmel-comm-file{display:block;font-size:.82em;color:#7a7488;margin:.3em 0}
.carmel-mention{color:#5b2a86;font-weight:700}
.carmel-comm-replyform{margin-top:1em}
.carmel-comm-react{display:flex;gap:.5em;align-items:center;margin-top:.5em}
.carmel-like-form,.carmel-best-form{margin:0}
.carmel-like-btn{border:1px solid #ddd2f5;background:#fff;border-radius:1em;padding:.2em .8em;cursor:pointer;font-size:.85em}
.carmel-like-btn.on{background:#f1ecfb;border-color:#6b4fbb;color:#5b2a86;font-weight:700}
.carmel-best-btn{border:1px solid #16a085;background:#fff;color:#0e6e58;border-radius:1em;padding:.2em .8em;cursor:pointer;font-size:.82em}
.carmel-best{border:2px solid #16a085!important;background:#f1fbf8}
.carmel-best-badge{color:#0e6e58;font-weight:700;font-size:.85em;margin-bottom:.2em}
.carmel-comm-solved{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085;border-radius:.3em;padding:.05em .5em;font-size:.78em}
.carmel-rank{background:#fff7ed;border:1px solid #f3d9b8;border-radius:10px;padding:.6em 1em;margin:1em 0}
.carmel-rank h3{margin:0 0 .4em}
.carmel-rank-list{margin:0;padding-left:1.4em}
.carmel-rank-list li{padding:.2em 0}
.carmel-rank-list a{text-decoration:none;color:#5b2a86;font-weight:600}
.carmel-rank-likes{color:#e67e22;font-size:.85em}
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
