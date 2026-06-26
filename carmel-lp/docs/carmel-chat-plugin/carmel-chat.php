<?php
/**
 * Plugin Name: Carmel Chat Widget
 * Description: カーメル相談AIのチャットウィジェットをサイト全ページの右下に表示します。管理画面（設定 → Carmel Chat）から接続先を入力するだけで使えます。
 * Version: 1.1.0
 * Author: Carmel
 * Requires at least: 5.0
 * Requires PHP: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // 直接アクセス禁止
}

define( 'CARMEL_CHAT_OPTION', 'carmel_chat_options' );

/**
 * 設定値を取得（管理画面の保存値 → 定数/フィルタの順でフォールバック）。
 */
function carmel_chat_get_options() {
	$saved    = get_option( CARMEL_CHAT_OPTION, array() );
	$defaults = array(
		'host'     => defined( 'CARMEL_CHAT_HOST' ) ? CARMEL_CHAT_HOST : '',
		'line_url' => '',
		'tel'      => '',
	);
	$opts = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	return apply_filters( 'carmel_chat_options', $opts );
}

/* ============================================================
 * フロント：フッターに埋め込みタグを出力
 * ========================================================== */
function carmel_chat_enqueue_footer() {
	$o    = carmel_chat_get_options();
	$host = untrailingslashit( trim( $o['host'] ) );
	if ( empty( $host ) ) {
		return; // 接続先未設定なら何も出さない
	}

	$src   = esc_url( $host . '/assets/embed.js' );
	$attrs = ' data-api-base="' . esc_attr( $host ) . '"';
	if ( ! empty( $o['line_url'] ) ) {
		$attrs .= ' data-line-url="' . esc_attr( $o['line_url'] ) . '"';
	}
	if ( ! empty( $o['tel'] ) ) {
		$attrs .= ' data-tel="' . esc_attr( $o['tel'] ) . '"';
	}
	echo '<script src="' . $src . '"' . $attrs . "></script>\n"; // phpcs:ignore
}
add_action( 'wp_footer', 'carmel_chat_enqueue_footer', 100 );

/* ============================================================
 * 管理画面：設定ページ（設定 → Carmel Chat）
 * ========================================================== */
function carmel_chat_admin_menu() {
	add_options_page(
		'Carmel Chat 設定',
		'Carmel Chat',
		'manage_options',
		'carmel-chat',
		'carmel_chat_settings_page'
	);
}
add_action( 'admin_menu', 'carmel_chat_admin_menu' );

function carmel_chat_register_settings() {
	register_setting( 'carmel_chat_group', CARMEL_CHAT_OPTION, 'carmel_chat_sanitize' );
}
add_action( 'admin_init', 'carmel_chat_register_settings' );

function carmel_chat_sanitize( $input ) {
	return array(
		'host'     => isset( $input['host'] ) ? esc_url_raw( trim( $input['host'] ) ) : '',
		'line_url' => isset( $input['line_url'] ) ? esc_url_raw( trim( $input['line_url'] ) ) : '',
		'tel'      => isset( $input['tel'] ) ? sanitize_text_field( $input['tel'] ) : '',
	);
}

function carmel_chat_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$o = carmel_chat_get_options();
	?>
	<div class="wrap">
		<h1>Carmel Chat 設定</h1>
		<p>チャットの「接続先ホスト」を入力して保存すると、サイトの右下にチャットが表示されます。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'carmel_chat_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="carmel_host">接続先ホスト（必須）</label></th>
					<td>
						<input name="<?php echo esc_attr( CARMEL_CHAT_OPTION ); ?>[host]" id="carmel_host"
							type="url" class="regular-text" placeholder="https://chat.example.com"
							value="<?php echo esc_attr( $o['host'] ); ?>" />
						<p class="description">チャットの頭脳（Nodeアプリ）を公開したURL。例: https://chat.example.com</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="carmel_line">LINEのURL（任意）</label></th>
					<td>
						<input name="<?php echo esc_attr( CARMEL_CHAT_OPTION ); ?>[line_url]" id="carmel_line"
							type="url" class="regular-text" placeholder="https://lin.ee/xxxx"
							value="<?php echo esc_attr( $o['line_url'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="carmel_tel">電話番号（任意）</label></th>
					<td>
						<input name="<?php echo esc_attr( CARMEL_CHAT_OPTION ); ?>[tel]" id="carmel_tel"
							type="text" class="regular-text" placeholder="050-1793-5554"
							value="<?php echo esc_attr( $o['tel'] ); ?>" />
					</td>
				</tr>
			</table>
			<?php submit_button( '保存する' ); ?>
		</form>
		<hr />
		<p><strong>状態:</strong>
			<?php echo $o['host'] ? '✅ 接続先が設定されています。サイト右下にチャットが表示されます。' : '⚠️ 接続先が未設定です。表示されません。'; ?>
		</p>
	</div>
	<?php
}
