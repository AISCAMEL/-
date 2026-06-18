<?php
/**
 * Customer document uploads + gated downloads.
 *
 * Customers submit identity / income documents from /mypage via the
 * [carmel_upload] shortcode. Files are stored in a protected uploads subdir
 * (deny-all .htaccess) and are never served by guessable URL — downloads go
 * through a permission-checked admin-post endpoint (§10). Access is limited to
 * the deal's customer, its store, and HQ.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Documents {

	/** @var Carmel_Documents|null */
	private static $instance = null;

	const SHORTCODE        = 'carmel_upload';
	const UPLOAD_ACTION    = 'carmel_doc_upload';
	const DOWNLOAD_ACTION  = 'carmel_doc_download';
	const NONCE            = 'carmel_doc_nonce';
	const SECURE_SUBDIR    = 'carmel-secure';
	const MAX_BYTES        = 10485760; // 10MB

	/** Deal id carried into the upload_dir filter. */
	private $upload_deal_id = 0;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Allowed upload types. */
	public static function allowed_types() {
		return array(
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'pdf'      => 'application/pdf',
		);
	}

	/** Document categories the customer can pick. */
	public static function categories() {
		return array(
			'identity' => '本人確認書類',
			'income'   => '収入証明',
			'other'    => 'その他',
		);
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::UPLOAD_ACTION, array( $this, 'handle_upload' ) );
		add_action( 'admin_post_' . self::DOWNLOAD_ACTION, array( $this, 'handle_download' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Access control
	 * --------------------------------------------------------------------- */

	/**
	 * Whether the current user may access a deal's documents.
	 *
	 * @param int $deal_id
	 * @return bool
	 */
	private function can_access_deal( $deal_id ) {
		if ( ! is_user_logged_in() || 'carmel_deal' !== get_post_type( $deal_id ) ) {
			return false;
		}
		if ( current_user_can( 'carmel_manage_stores' ) ) {
			return true; // HQ
		}
		$uid = get_current_user_id();

		// Owning customer.
		if ( (int) get_post_meta( $deal_id, 'customer_id', true ) === $uid ) {
			return true;
		}
		// Staff/owner of the deal's store.
		$my_store   = (int) get_user_meta( $uid, 'store_id', true );
		$deal_store = (int) get_post_meta( $deal_id, 'store_id', true );
		return $my_store && $my_store === $deal_store && current_user_can( 'carmel_change_deal_status' );
	}

	/* --------------------------------------------------------------------- *
	 * Upload
	 * --------------------------------------------------------------------- */

	public function handle_upload() {
		$deal_id  = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$category = isset( $_POST['category'] ) ? sanitize_key( $_POST['category'] ) : 'other';
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/mypage' );

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::UPLOAD_ACTION . '_' . $deal_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! $this->can_access_deal( $deal_id ) ) {
			wp_die( esc_html__( 'この案件に書類をアップロードする権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		if ( empty( $_FILES['carmel_file']['name'] ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_up', 'nofile', $redirect ) );
			exit;
		}

		// Validate type & size before handing to WP.
		$file  = $_FILES['carmel_file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$check = wp_check_filetype( $file['name'], self::allowed_types() );
		if ( ! $check['type'] ) {
			wp_safe_redirect( add_query_arg( 'carmel_up', 'badtype', $redirect ) );
			exit;
		}
		if ( (int) $file['size'] > self::MAX_BYTES ) {
			wp_safe_redirect( add_query_arg( 'carmel_up', 'toobig', $redirect ) );
			exit;
		}

		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Redirect the upload into the protected subdir for this deal.
		$this->upload_deal_id = $deal_id;
		add_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
				'mimes'     => self::allowed_types(),
			)
		);
		remove_filter( 'upload_dir', array( $this, 'filter_upload_dir' ) );
		$this->upload_deal_id = 0;

		if ( isset( $result['error'] ) || empty( $result['file'] ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_up', 'err', $redirect ) );
			exit;
		}

		// Record a carmel_document (store the path, not a public URL).
		wp_insert_post(
			array(
				'post_type'   => 'carmel_document',
				'post_status' => 'publish',
				'post_title'  => sprintf( '#%d %s / %s', $deal_id, self::category_label( $category ), wp_basename( $result['file'] ) ),
				'meta_input'  => array(
					'deal_id'      => $deal_id,
					'doc_type'     => 'customer_upload',
					'category'     => $category,
					'file_path'    => $result['file'],
					'uploaded_by'  => get_current_user_id(),
					'generated_at' => current_time( 'mysql' ),
				),
			)
		);

		do_action( 'carmel_document_uploaded', $deal_id, $category );
		wp_safe_redirect( add_query_arg( 'carmel_up', 'ok', $redirect ) );
		exit;
	}

	/**
	 * Point uploads at uploads/carmel-secure/{deal_id} and harden it.
	 *
	 * @param array $dirs
	 * @return array
	 */
	public function filter_upload_dir( $dirs ) {
		$sub = '/' . self::SECURE_SUBDIR . '/' . (int) $this->upload_deal_id;
		$dirs['subdir'] = $sub;
		$dirs['path']   = $dirs['basedir'] . $sub;
		$dirs['url']    = $dirs['baseurl'] . $sub; // not used for serving
		$this->harden_dir( $dirs['basedir'] . '/' . self::SECURE_SUBDIR );
		return $dirs;
	}

	/**
	 * Ensure the secure root denies direct web access.
	 */
	private function harden_dir( $root ) {
		if ( ! file_exists( $root ) ) {
			wp_mkdir_p( $root );
		}
		$ht = $root . '/.htaccess';
		if ( ! file_exists( $ht ) ) {
			@file_put_contents( $ht, "Require all denied\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore
		}
		$idx = $root . '/index.html';
		if ( ! file_exists( $idx ) ) {
			@file_put_contents( $idx, '' ); // phpcs:ignore
		}
	}

	/* --------------------------------------------------------------------- *
	 * Gated download
	 * --------------------------------------------------------------------- */

	public function handle_download() {
		$doc_id = isset( $_GET['doc'] ) ? (int) $_GET['doc'] : 0;

		if ( ! wp_verify_nonce( isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '', self::DOWNLOAD_ACTION . '_' . $doc_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( 'carmel_document' !== get_post_type( $doc_id ) ) {
			wp_die( esc_html__( '書類が見つかりません。', 'carmel-core' ), '', array( 'response' => 404 ) );
		}

		$deal_id = (int) get_post_meta( $doc_id, 'deal_id', true );
		if ( ! $this->can_access_deal( $deal_id ) ) {
			wp_die( esc_html__( 'この書類を閲覧する権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		$path = (string) get_post_meta( $doc_id, 'file_path', true );
		if ( '' === $path || ! file_exists( $path ) ) {
			wp_die( esc_html__( 'ファイルが存在しません。', 'carmel-core' ), '', array( 'response' => 404 ) );
		}

		// Confine to the secure dir (defence-in-depth against path tricks).
		$uploads = wp_upload_dir();
		$secure  = wp_normalize_path( $uploads['basedir'] . '/' . self::SECURE_SUBDIR );
		if ( 0 !== strpos( wp_normalize_path( $path ), $secure ) ) {
			wp_die( esc_html__( '不正なパスです。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}

		$type = wp_check_filetype( $path, self::allowed_types() );
		nocache_headers();
		header( 'Content-Type: ' . ( $type['type'] ? $type['type'] : 'application/octet-stream' ) );
		header( 'Content-Disposition: inline; filename="' . wp_basename( $path ) . '"' );
		header( 'Content-Length: ' . filesize( $path ) );
		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		exit;
	}

	/**
	 * Build a nonce-protected download URL for a document.
	 *
	 * @param int $doc_id
	 * @return string
	 */
	public static function download_url( $doc_id ) {
		return wp_nonce_url(
			admin_url( 'admin-post.php?action=' . self::DOWNLOAD_ACTION . '&doc=' . (int) $doc_id ),
			self::DOWNLOAD_ACTION . '_' . (int) $doc_id
		);
	}

	/* --------------------------------------------------------------------- *
	 * Render (customer)
	 * --------------------------------------------------------------------- */

	/**
	 * Render upload forms + uploaded list for the current customer's deals.
	 *
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() ) {
			return '<p class="carmel-notice">ログインすると書類をアップロードできます。</p>';
		}

		$deals = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'meta_query'     => array(
					array( 'key' => 'customer_id', 'value' => get_current_user_id() ),
				),
			)
		);

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-upload"><h2>書類のご提出</h2>';

		if ( empty( $deals ) ) {
			echo '<p>対象の案件がありません。</p></div>';
			return ob_get_clean();
		}

		foreach ( $deals as $deal ) {
			echo '<div class="carmel-up-card">';
			echo '<h3>案件 #' . (int) $deal->ID . '</h3>';
			echo $this->upload_form( $deal->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo $this->uploaded_list( $deal->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
			echo '</div>';
		}

		echo '</div>';
		return ob_get_clean();
	}

	private function upload_form( $deal_id ) {
		$nonce = wp_create_nonce( self::UPLOAD_ACTION . '_' . $deal_id );
		$out   = '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-up-form">';
		$out  .= '<input type="hidden" name="action" value="' . esc_attr( self::UPLOAD_ACTION ) . '">';
		$out  .= '<input type="hidden" name="deal_id" value="' . (int) $deal_id . '">';
		$out  .= '<input type="hidden" name="' . esc_attr( self::NONCE ) . '" value="' . esc_attr( $nonce ) . '">';
		$out  .= '<select name="category">';
		foreach ( self::categories() as $key => $label ) {
			$out .= '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		}
		$out  .= '</select> ';
		$out  .= '<input type="file" name="carmel_file" accept=".jpg,.jpeg,.png,.pdf" required> ';
		$out  .= '<button type="submit" class="carmel-btn">アップロード</button>';
		$out  .= '<p class="carmel-hint">JPG / PNG / PDF・最大10MB</p>';
		$out  .= '</form>';
		return $out;
	}

	private function uploaded_list( $deal_id ) {
		$docs = get_posts(
			array(
				'post_type'      => 'carmel_document',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => 'deal_id', 'value' => (int) $deal_id ),
					array( 'key' => 'doc_type', 'value' => 'customer_upload' ),
				),
				'orderby'        => 'date',
				'order'          => 'DESC',
			)
		);
		if ( empty( $docs ) ) {
			return '<p class="carmel-hint">まだ書類はありません。</p>';
		}
		$out = '<ul class="carmel-up-list">';
		foreach ( $docs as $doc ) {
			$cat = self::category_label( get_post_meta( $doc->ID, 'category', true ) );
			$out .= '<li><span class="carmel-cat">' . esc_html( $cat ) . '</span> '
				. '<a href="' . esc_url( self::download_url( $doc->ID ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $doc->ID ) ) . '</a></li>';
		}
		$out .= '</ul>';
		return $out;
	}

	private function banner() {
		$msg = isset( $_GET['carmel_up'] ) ? sanitize_key( $_GET['carmel_up'] ) : '';
		$map = array(
			'ok'      => array( 'success', '書類をアップロードしました。' ),
			'nofile'  => array( 'error', 'ファイルが選択されていません。' ),
			'badtype' => array( 'error', '対応形式は JPG / PNG / PDF です。' ),
			'toobig'  => array( 'error', 'ファイルサイズが大きすぎます（最大10MB）。' ),
			'err'     => array( 'error', 'アップロードに失敗しました。' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return '';
		}
		return '<div class="carmel-banner carmel-banner-' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] ) . '</div>';
	}

	private static function category_label( $key ) {
		$cats = self::categories();
		return isset( $cats[ $key ] ) ? $cats[ $key ] : 'その他';
	}

	private function styles() {
		return '<style>
.carmel-upload{font-size:14px;max-width:640px}
.carmel-up-card{border:1px solid #e0e3ea;border-radius:.6em;padding:1em 1.2em;margin:1em 0;background:#fff}
.carmel-up-card h3{margin:.2em 0 .6em}
.carmel-up-form{display:flex;flex-wrap:wrap;gap:.5em;align-items:center}
.carmel-up-form select,.carmel-up-form input[type=file]{border:1px solid #ccc;border-radius:.3em;padding:.3em}
.carmel-btn{border:0;border-radius:.3em;padding:.45em 1em;background:#2e86de;color:#fff;cursor:pointer}
.carmel-hint{font-size:.8em;color:#888;width:100%;margin:.3em 0 0}
.carmel-up-list{list-style:none;padding:0;margin:.8em 0 0}
.carmel-up-list li{padding:.3em 0;border-top:1px solid #f0f1f5}
.carmel-cat{display:inline-block;background:#eef2fb;color:#2e86de;border-radius:.3em;padding:.1em .6em;font-size:.8em;margin-right:.5em}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#f4f6fb;border:1px solid #cdd2dc;border-radius:.4em}
</style>';
	}
}
