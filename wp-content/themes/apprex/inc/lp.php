<?php
/**
 * 広告用ランディングページ（LP）。
 *
 * - 固定ページテンプレート「LP（広告用・1カラム）」で、ヘッダー/フッター無しの
 *   縦1枚LPを表示。SNS広告の計測タグ（ピクセル/CAPI）とフォームは自動で組み込まれる。
 * - サブドメイン（例：lp.aiscompany.jp）でアクセスされた場合、指定したLP固定ページを
 *   そのサブドメインのトップとしてネイティブ表示する（計測そのまま）。
 *
 * 設定：設定 → APPREX 広告LP。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 設定（サブドメイン → LP割り当て）
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX 広告LP', 'APPREX 広告LP', 'manage_options', 'apprex-lp', 'apprex_lp_settings_page' );
} );
add_action( 'admin_init', function () {
	register_setting( 'apprex_lp', 'apprex_lp_host', array( 'sanitize_callback' => 'apprex_lp_sanitize_host' ) );
	register_setting( 'apprex_lp', 'apprex_lp_page', array( 'sanitize_callback' => 'absint' ) );
} );

/** ホスト名のサニタイズ（小文字・スキーム/パス除去）。 */
function apprex_lp_sanitize_host( $v ) {
	$v = strtolower( trim( (string) $v ) );
	$v = preg_replace( '#^https?://#', '', $v );
	$v = preg_replace( '#/.*$#', '', $v );
	return preg_replace( '/[^a-z0-9.\-]/', '', $v );
}

/** 設定されたLPサブドメイン。 */
function apprex_lp_host() {
	return (string) get_option( 'apprex_lp_host', '' );
}

/** 現在のリクエストがLPサブドメインか。 */
function apprex_lp_is_host() {
	$host = apprex_lp_host();
	if ( '' === $host || empty( $_SERVER['HTTP_HOST'] ) ) {
		return false;
	}
	$req = strtolower( preg_replace( '/:\d+$/', '', sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) ) );
	return $req === $host;
}

function apprex_lp_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$host  = apprex_lp_host();
	$pages = get_pages( array( 'sort_column' => 'post_modified', 'sort_order' => 'desc' ) );
	$site_host = wp_parse_url( home_url(), PHP_URL_HOST );
	?>
	<div class="wrap">
		<h1>APPREX 広告LP（サブドメイン表示）</h1>
		<p>広告用LPを専用サブドメイン（例：<code>lp.<?php echo esc_html( $site_host ); ?></code>）のトップページとして表示します。SNS広告の計測タグもそのまま有効です。</p>

		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_lp' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th>LPサブドメイン</th>
					<td><input type="text" name="apprex_lp_host" class="regular-text" value="<?php echo esc_attr( $host ); ?>" placeholder="lp.<?php echo esc_attr( $site_host ); ?>">
					<p class="description">広告の遷移先にするサブドメイン（http:// は不要）。DNSとサーバー設定（下記）を済ませてから入力してください。</p></td>
				</tr>
				<tr>
					<th>トップに表示するLP</th>
					<td>
						<select name="apprex_lp_page">
							<option value="0"><?php esc_html_e( '— 選択 —', 'apprex' ); ?></option>
							<?php foreach ( $pages as $p ) : ?>
								<option value="<?php echo (int) $p->ID; ?>" <?php selected( (int) get_option( 'apprex_lp_page', 0 ), $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">テンプレート「LP（広告用・1カラム）」で作成した固定ページを選んでください。サブドメインのトップ（<code>/</code>）でこのLPが表示されます。</p>
					</td>
				</tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2>設定手順</h2>
		<ol style="max-width:820px;line-height:1.9;">
			<li><strong>LPページを作る：</strong>「固定ページ → 新規追加」で本文を作成し、ページ属性のテンプレートで<strong>「LP（広告用・1カラム）」</strong>を選択して公開。</li>
			<li><strong>DNS設定：</strong>ドメイン管理画面で <code>lp</code> のレコードを追加。
				<ul style="list-style:disc;margin:6px 0 6px 1.4em;">
					<li>同じサーバーのIPに向ける <code>A</code> レコード（例：<code>lp → 〇〇.〇〇.〇〇.〇〇</code>）、または</li>
					<li>サーバー指定の <code>CNAME</code>（例：<code>lp → <?php echo esc_html( $site_host ); ?></code>）。</li>
				</ul>
			</li>
			<li><strong>サーバー設定：</strong>レンタルサーバーの管理画面で <code>lp.<?php echo esc_html( $site_host ); ?></code> を「サブドメイン（このWordPressと同じ公開フォルダ）」として追加し、SSL（無料SSL）を発行。</li>
			<li><strong>本ページで設定：</strong>上の「LPサブドメイン」と「トップに表示するLP」を入力して保存。</li>
			<li><strong>広告のリンク先：</strong>各SNS広告の遷移先URLを <code>https://lp.<?php echo esc_html( $site_host ); ?>/</code> にする。</li>
		</ol>
		<p class="description" style="max-width:820px;">※ サーバーによって「サブドメイン追加」「独自SSL」の名称は異なります（エックスサーバー＝サブドメイン設定／無料独自SSL、ConoHa WING＝ドメイン／無料SSL 等）。ご利用のサーバー名を教えていただければ、その画面に合わせた手順をご案内します。</p>
	</div>
	<?php
}

/* =========================================================================
 * LP固定ページの簡易設定（メタボックス）
 * ====================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_lp_meta', '広告LP 設定', 'apprex_lp_metabox', 'page', 'side', 'default' );
} );

function apprex_lp_metabox( $post ) {
	wp_nonce_field( 'apprex_lp_meta', 'apprex_lp_meta_nonce' );
	$cta   = get_post_meta( $post->ID, '_apprex_lp_cta', true );
	$form  = get_post_meta( $post->ID, '_apprex_lp_form', true );
	$phone = get_post_meta( $post->ID, '_apprex_lp_phone', true );
	$form  = $form ? $form : 'contact';
	?>
	<p style="color:#666;">テンプレートが「LP（広告用・1カラム）」のとき有効です。</p>
	<p><label><strong>CTAボタン文言</strong><br>
		<input type="text" name="apprex_lp_cta" value="<?php echo esc_attr( $cta ); ?>" class="widefat" placeholder="まずは無料で相談する"></label></p>
	<p><label><strong>フォーム種別</strong><br>
		<select name="apprex_lp_form" class="widefat">
			<?php
			$opts = array( 'contact' => 'お問い合わせ', 'document' => '資料請求', 'trial' => '無料体験申込', 'none' => 'フォームなし（CTAはお問い合わせページへ）' );
			foreach ( $opts as $k => $lbl ) {
				printf( '<option value="%s" %s>%s</option>', esc_attr( $k ), selected( $form, $k, false ), esc_html( $lbl ) );
			}
			?>
		</select></label></p>
	<p><label><strong>電話番号（任意）</strong><br>
		<input type="text" name="apprex_lp_phone" value="<?php echo esc_attr( $phone ); ?>" class="widefat" placeholder="03-0000-0000"></label></p>
	<?php
}

add_action( 'save_post_page', function ( $post_id ) {
	if ( ! isset( $_POST['apprex_lp_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_lp_meta_nonce'] ) ), 'apprex_lp_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_page', $post_id ) ) {
		return;
	}
	update_post_meta( $post_id, '_apprex_lp_cta', sanitize_text_field( wp_unslash( $_POST['apprex_lp_cta'] ?? '' ) ) );
	$form = sanitize_key( wp_unslash( $_POST['apprex_lp_form'] ?? 'contact' ) );
	update_post_meta( $post_id, '_apprex_lp_form', in_array( $form, array( 'contact', 'document', 'trial', 'none' ), true ) ? $form : 'contact' );
	update_post_meta( $post_id, '_apprex_lp_phone', sanitize_text_field( wp_unslash( $_POST['apprex_lp_phone'] ?? '' ) ) );
} );

/* =========================================================================
 * サブドメイン → LP ルーティング
 * ====================================================================== */

// LPサブドメインでは正規化リダイレクト（本体ホストへの跳ね返り）を抑止。
add_filter( 'redirect_canonical', function ( $redirect ) {
	return apprex_lp_is_host() ? false : $redirect;
} );

// REST URL をLPサブドメイン自身に向ける（フォーム送信を同一オリジンにしてCORS回避）。
add_filter( 'rest_url', function ( $url ) {
	if ( apprex_lp_is_host() && ! empty( $_SERVER['HTTP_HOST'] ) ) {
		$host = strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) );
		$url  = preg_replace( '#^(https?://)[^/]+#', '${1}' . $host, $url );
	}
	return $url;
} );

// LPサブドメインのトップ（/）で、指定LPページを直接描画する。
add_action( 'template_redirect', function () {
	if ( ! apprex_lp_is_host() ) {
		return;
	}
	// ルート（/）のみ対象。wp-admin / REST / 個別URLには干渉しない。
	$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/', PHP_URL_PATH );
	if ( '/' !== (string) $path && '' !== (string) $path ) {
		return;
	}
	$lp_id = (int) get_option( 'apprex_lp_page', 0 );
	if ( ! $lp_id || 'publish' !== get_post_status( $lp_id ) ) {
		return;
	}
	apprex_lp_render_page( $lp_id );
	exit;
}, 0 );

/* =========================================================================
 * LP描画（テンプレート・ルーティング共通）
 * ====================================================================== */

/**
 * LP固定ページ1枚を、完結したHTMLドキュメントとして出力する。
 *
 * @param int $post_id LP固定ページID。
 */
function apprex_lp_render_page( $post_id ) {
	global $post;
	$post = get_post( $post_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	setup_postdata( $post );

	$cta   = get_post_meta( $post_id, '_apprex_lp_cta', true );
	$cta   = $cta ? $cta : 'まずは無料で相談する';
	$form  = get_post_meta( $post_id, '_apprex_lp_form', true );
	$form  = $form ? $form : 'contact';
	$phone = get_post_meta( $post_id, '_apprex_lp_phone', true );
	$lead  = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : '';
	$logo  = APPREX_URI . '/assets/images/apprex-logo.png';
	$has_form = ( 'none' !== $form );
	$cta_href = $has_form ? '#lp-form' : home_url( '/contact/' );
	?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'apprex-lp-body' ); ?>>
<?php wp_body_open(); ?>
<div class="lp-bar">
	<div class="lp-bar__inner">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="lp-bar__logo"><img src="<?php echo esc_url( $logo ); ?>" alt="APPREX" width="150" height="40"></a>
		<?php if ( $phone ) : ?>
			<a class="lp-bar__tel" href="tel:<?php echo esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ); ?>">☎ <?php echo esc_html( $phone ); ?></a>
		<?php endif; ?>
	</div>
</div>

<main class="lp-main">
	<section class="lp-hero">
		<div class="lp-container">
			<h1 class="lp-hero__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h1>
			<?php if ( $lead ) : ?>
				<p class="lp-hero__lead"><?php echo esc_html( $lead ); ?></p>
			<?php endif; ?>
			<a class="btn btn--cta lp-hero__cta" href="<?php echo esc_url( $cta_href ); ?>"><?php echo esc_html( $cta ); ?></a>
		</div>
	</section>

	<section class="lp-content">
		<div class="lp-container content-prose">
			<?php the_content(); ?>
		</div>
	</section>

	<?php if ( $has_form ) : ?>
		<section class="lp-form-section" id="lp-form">
			<div class="lp-container">
				<h2 class="lp-form__title"><?php echo esc_html( $cta ); ?></h2>
				<p class="lp-form__note">下記フォームよりお気軽にお問い合わせください。担当者より折り返しご連絡いたします。</p>
				<?php apprex_render_form( $form ); ?>
			</div>
		</section>
	<?php endif; ?>
</main>

<footer class="lp-footer">
	<div class="lp-container">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> APPREX by 合同会社アイズ</p>
		<p><a href="<?php echo esc_url( apprex_page_url( 'company' ) ); ?>" target="_blank" rel="noopener">運営会社</a>　/　<a href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener">公式サイト</a></p>
	</div>
</footer>

<a class="lp-stickycta" href="<?php echo esc_url( $cta_href ); ?>"><?php echo esc_html( $cta ); ?></a>

<?php wp_footer(); ?>
</body>
</html>
	<?php
	wp_reset_postdata();
}
