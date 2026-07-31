<?php
/**
 * 本部：加盟店向けコンテンツの作成・編集（フロントエンド）。
 *
 * ショートコード [carmel_hq_content]（`carmel_manage_stores`＝本部のみ）を /hq に
 * 設置すると、wp-admin を開かずに加盟店コンテンツ（carmel_content）を作成・編集・
 * 公開・削除できる。公開した内容は加盟店の [carmel_store_content]（/store-content）に
 * 表示され、「加盟店へ通知」を付けると全加盟店へ一斉通知される。
 *
 * 種別：スタートガイド(guide) / お知らせ(notice) / マニュアル(manual) /
 *       FAQ(faq) / 販促ツール(promo)
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_HQ_Content {

	/** @var Carmel_HQ_Content|null */
	private static $instance = null;

	const SHORTCODE    = 'carmel_hq_content';
	const SAVE_ACTION  = 'carmel_hq_content_save';
	const DEL_ACTION   = 'carmel_hq_content_delete';
	const NONCE        = 'carmel_hq_content_nonce';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::SAVE_ACTION, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::DEL_ACTION, array( $this, 'handle_delete' ) );
	}

	/** 種別 => ラベル。 */
	public static function types() {
		return array(
			'guide'  => 'スタートガイド（始め方）',
			'notice' => 'お知らせ',
			'manual' => 'マニュアル・資料',
			'faq'    => 'FAQ',
			'promo'  => '販促ツール',
		);
	}

	private function can_manage() {
		return is_user_logged_in() && current_user_can( 'carmel_manage_stores' );
	}

	/* --------------------------------------------------------------------- *
	 * 保存（作成 / 更新）
	 * --------------------------------------------------------------------- */

	public function handle_save() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::SAVE_ACTION ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/hq' );

		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		$type    = isset( $_POST['content_type'] ) ? sanitize_key( $_POST['content_type'] ) : 'notice';
		if ( ! isset( self::types()[ $type ] ) ) {
			$type = 'notice';
		}
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$summary = isset( $_POST['summary'] ) ? sanitize_text_field( wp_unslash( $_POST['summary'] ) ) : '';
		$body    = isset( $_POST['body'] ) ? wp_kses_post( wp_unslash( $_POST['body'] ) ) : '';
		$file    = isset( $_POST['file_url'] ) ? esc_url_raw( wp_unslash( $_POST['file_url'] ) ) : '';
		$pinned  = ! empty( $_POST['pinned'] ) ? 1 : 0;
		$notify  = ! empty( $_POST['notify_stores'] ) ? 1 : 0;
		$step    = isset( $_POST['step_order'] ) ? (int) $_POST['step_order'] : 0;

		if ( '' === $title ) {
			wp_safe_redirect( add_query_arg( 'carmel_hc', 'err', $redirect ) );
			exit;
		}

		$meta = array(
			'content_type'  => $type,
			'summary'       => $summary,
			'file_url'      => $file,
			'pinned'        => $pinned,
			'notify_stores' => $notify,
			'step_order'    => $step,
		);

		// 更新（既存の carmel_content のみ）。
		if ( $post_id && 'carmel_content' === get_post_type( $post_id ) ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_title'   => $title,
					'post_content' => $body,
				)
			);
			foreach ( $meta as $k => $v ) {
				update_post_meta( $post_id, $k, $v );
			}
			$id = $post_id;
		} else {
			$id = wp_insert_post(
				array(
					'post_type'    => 'carmel_content',
					'post_status'  => 'publish',
					'post_title'   => $title,
					'post_content' => $body,
					'post_author'  => get_current_user_id(),
					'meta_input'   => $meta,
				)
			);
		}

		if ( is_wp_error( $id ) || ! $id ) {
			wp_safe_redirect( add_query_arg( 'carmel_hc', 'err', $redirect ) );
			exit;
		}

		// 「加盟店へ通知」が ON なら全加盟店へ一斉通知（冪等キーで重複抑止）。
		if ( $notify ) {
			Carmel_Notifier::notify(
				'store_notice',
				array(
					'event_id' => 'store_notice:' . (int) $id,
					'vars'     => array( 'title' => $title, 'summary' => $summary ),
				)
			);
		}

		do_action( 'carmel_hq_content_saved', (int) $id );
		wp_safe_redirect( add_query_arg( 'carmel_hc', $post_id ? 'updated' : 'created', remove_query_arg( array( 'edit', 'carmel_hc' ), $redirect ) ) );
		exit;
	}

	public function handle_delete() {
		if ( ! $this->can_manage() ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::DEL_ACTION . '_' . $post_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/hq' );
		if ( 'carmel_content' === get_post_type( $post_id ) ) {
			wp_trash_post( $post_id );
		}
		wp_safe_redirect( add_query_arg( 'carmel_hc', 'deleted', remove_query_arg( array( 'edit', 'carmel_hc' ), $redirect ) ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * 画面
	 * --------------------------------------------------------------------- */

	public function render() {
		if ( ! $this->can_manage() ) {
			return '<p class="carmel-notice">コンテンツ管理を表示する権限がありません。</p>';
		}

		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0;
		$editing = ( $edit_id && 'carmel_content' === get_post_type( $edit_id ) ) ? $edit_id : 0;

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-hc"><h2>加盟店コンテンツの作成</h2>';
		echo '<p class="carmel-hc-lead">公開すると加盟店ページ（' . esc_html( $this->store_content_label() ) . '）に表示されます。「加盟店へ通知」を付けると全加盟店へ一斉通知します。</p>';

		echo $this->form( $editing ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->list_table(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';
		return ob_get_clean();
	}

	private function store_content_label() {
		return '/' . ltrim( apply_filters( 'carmel_store_content_page_slug', 'store-content' ), '/' );
	}

	private function form( $editing ) {
		$g = function ( $key, $default = '' ) use ( $editing ) {
			if ( ! $editing ) {
				return $default;
			}
			return get_post_meta( $editing, $key, true );
		};
		$title = $editing ? get_the_title( $editing ) : '';
		$body  = $editing ? get_post_field( 'post_content', $editing ) : '';
		$type  = $editing ? (string) get_post_meta( $editing, 'content_type', true ) : 'notice';
		$nonce = wp_create_nonce( self::SAVE_ACTION );

		ob_start();
		?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="carmel-hc-form">
	<input type="hidden" name="action" value="<?php echo esc_attr( self::SAVE_ACTION ); ?>">
	<input type="hidden" name="<?php echo esc_attr( self::NONCE ); ?>" value="<?php echo esc_attr( $nonce ); ?>">
	<input type="hidden" name="post_id" value="<?php echo (int) $editing; ?>">
	<h3><?php echo $editing ? '編集' : '新規作成'; ?></h3>
	<div class="carmel-hc-row">
		<label>種別
			<select name="content_type">
				<?php foreach ( self::types() as $k => $label ) : ?>
				<option value="<?php echo esc_attr( $k ); ?>"<?php selected( $type, $k ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>表示順（ガイド用）<input type="number" name="step_order" value="<?php echo esc_attr( $g( 'step_order', '0' ) ); ?>" style="max-width:90px"></label>
	</div>
	<label class="carmel-hc-block">タイトル<input type="text" name="title" value="<?php echo esc_attr( $title ); ?>" required></label>
	<label class="carmel-hc-block">概要（一覧表示用）<input type="text" name="summary" value="<?php echo esc_attr( $g( 'summary' ) ); ?>"></label>
	<label class="carmel-hc-block">本文<textarea name="body" rows="6"><?php echo esc_textarea( $body ); ?></textarea></label>
	<label class="carmel-hc-block">添付ファイルURL（資料DL用）<input type="url" name="file_url" value="<?php echo esc_attr( $g( 'file_url' ) ); ?>" placeholder="https://..."></label>
	<div class="carmel-hc-row">
		<label class="carmel-hc-check"><input type="checkbox" name="pinned" value="1"<?php checked( in_array( (string) $g( 'pinned' ), array( '1', 'yes', 'true' ), true ) ); ?>> 重要（上部に固定）</label>
		<label class="carmel-hc-check"><input type="checkbox" name="notify_stores" value="1"> 加盟店へ通知する</label>
	</div>
	<div class="carmel-hc-actions">
		<button type="submit" class="carmel-btn carmel-btn-purple"><?php echo $editing ? '更新する' : '公開する'; ?></button>
		<?php if ( $editing ) : ?>
		<a class="carmel-btn carmel-btn-ghost" href="<?php echo esc_url( remove_query_arg( array( 'edit', 'carmel_hc' ) ) ); ?>">新規作成に戻る</a>
		<?php endif; ?>
	</div>
	<p class="carmel-hint">本文はHTML可（許可タグのみ）。「加盟店へ通知」は更新時、同じ記事では二重送信されません。</p>
</form>
		<?php
		return ob_get_clean();
	}

	private function list_table() {
		$items = get_posts(
			array(
				'post_type'      => 'carmel_content',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);
		$out = '<h3>公開中のコンテンツ</h3>';
		if ( empty( $items ) ) {
			return $out . '<p class="carmel-hint">まだコンテンツはありません。</p>';
		}
		$types = self::types();
		$out  .= '<table class="carmel-table"><thead><tr><th>種別</th><th>タイトル</th><th>固定</th><th>更新日</th><th>操作</th></tr></thead><tbody>';
		foreach ( $items as $p ) {
			$type   = (string) get_post_meta( $p->ID, 'content_type', true );
			$pinned = in_array( (string) get_post_meta( $p->ID, 'pinned', true ), array( '1', 'yes', 'true' ), true );
			$edit   = add_query_arg( 'edit', $p->ID, remove_query_arg( array( 'carmel_hc' ) ) );
			$del    = wp_create_nonce( self::DEL_ACTION . '_' . $p->ID );
			$out   .= '<tr>';
			$out   .= '<td>' . esc_html( isset( $types[ $type ] ) ? $types[ $type ] : $type ) . '</td>';
			$out   .= '<td>' . esc_html( get_the_title( $p->ID ) ) . '</td>';
			$out   .= '<td>' . ( $pinned ? '📌' : '' ) . '</td>';
			$out   .= '<td>' . esc_html( get_the_modified_date( 'Y-m-d', $p->ID ) ) . '</td>';
			$out   .= '<td class="carmel-hc-ops">';
			$out   .= '<a class="carmel-btn carmel-btn-blue" href="' . esc_url( $edit ) . '">編集</a>';
			$out   .= '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'このコンテンツを削除しますか？\');">'
				. '<input type="hidden" name="action" value="' . esc_attr( self::DEL_ACTION ) . '">'
				. '<input type="hidden" name="post_id" value="' . (int) $p->ID . '">'
				. '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $del ) . '">'
				. '<button type="submit" class="carmel-btn carmel-btn-red">削除</button></form>';
			$out   .= '</td></tr>';
		}
		$out .= '</tbody></table>';
		return $out;
	}

	private function banner() {
		$key = isset( $_GET['carmel_hc'] ) ? sanitize_key( $_GET['carmel_hc'] ) : '';
		$map = array(
			'created' => array( 'success', 'コンテンツを公開しました。加盟店ページに表示されます。' ),
			'updated' => array( 'success', 'コンテンツを更新しました。' ),
			'deleted' => array( 'success', 'コンテンツを削除しました。' ),
			'err'     => array( 'error', '保存できませんでした。タイトルは必須です。' ),
		);
		if ( ! isset( $map[ $key ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $key ][0] ) . '">' . esc_html( $map[ $key ][1] ) . '</div>';
	}

	private function styles() {
		return '<style>
.carmel-hc{font-size:14px;max-width:760px}
.carmel-hc-lead{color:#666}
.carmel-hc-form{border:1px solid #e0e3ea;border-radius:.6em;padding:1.1em 1.3em;margin:1em 0;background:#fff}
.carmel-hc-form h3{margin:.1em 0 .7em}
.carmel-hc-row{display:flex;gap:1em;flex-wrap:wrap;margin-bottom:.6em}
.carmel-hc-row label{display:flex;flex-direction:column;font-size:.82em;color:#555;gap:.2em}
.carmel-hc-block{display:block;font-size:.82em;color:#555;margin-bottom:.6em}
.carmel-hc-form input[type=text],.carmel-hc-form input[type=url],.carmel-hc-form input[type=number],.carmel-hc-form select,.carmel-hc-form textarea{width:100%;border:1px solid #ccc;border-radius:.3em;padding:.45em}
.carmel-hc-row label select,.carmel-hc-row label input{width:auto}
.carmel-hc-check{flex-direction:row!important;align-items:center;gap:.4em;font-size:.9em}
.carmel-hc-actions{display:flex;gap:.5em;margin:.4em 0}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.5em 1.1em;color:#fff;cursor:pointer;font-size:.9em;text-decoration:none}
.carmel-btn-purple{background:#6b4fbb}.carmel-btn-blue{background:#2e86de}.carmel-btn-red{background:#c0392b}.carmel-btn-ghost{background:#eef2fb;color:#2e86de}
.carmel-table{width:100%;border-collapse:collapse;margin-top:.6em}
.carmel-table th,.carmel-table td{border:1px solid #e0e3ea;padding:.5em .6em;text-align:left;font-size:.9em}
.carmel-table th{background:#f4f6fb}
.carmel-hc-ops{display:flex;gap:.4em;flex-wrap:wrap}
.carmel-hc-ops form{margin:0}
.carmel-hint{font-size:.82em;color:#888}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
