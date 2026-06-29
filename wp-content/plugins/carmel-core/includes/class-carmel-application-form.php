<?php
/**
 * Built-in structured application form.
 *
 * Shortcode [carmel_application_form] renders a self-contained, plugin-free
 * application form for the public site (carmelonline.jp). Submissions post to
 * admin-post.php (priv + nopriv) with a nonce + honeypot and flow straight into
 * Carmel_Application_Intake::process() — i.e. account auto-provision + deal
 * creation + intake notification.
 *
 * Attributes:
 *   type   loan|buyback|lease — preset the business type (hides the selector)
 *   thanks URL to redirect to on success (optional; default: back with banner)
 *
 * Examples:
 *   [carmel_application_form]                       全業務（種別を選択）
 *   [carmel_application_form type="loan"]           ローン専用フォーム
 *   [carmel_application_form type="buyback" thanks="/thanks"]
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Application_Form {

	/** @var Carmel_Application_Form|null */
	private static $instance = null;

	const SHORTCODE = 'carmel_application_form';
	const ACTION    = 'carmel_application_submit';
	const NONCE     = 'carmel_application_form';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_submit' ) );
		add_action( 'admin_post_nopriv_' . self::ACTION, array( $this, 'handle_submit' ) );
	}

	/* --------------------------------------------------------------------- *
	 * Submission
	 * --------------------------------------------------------------------- */

	public function handle_submit() {
		$redirect = wp_get_referer() ? wp_get_referer() : home_url( '/' );

		// Nonce.
		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '', self::ACTION ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_app', 'err', $redirect ) );
			exit;
		}

		// Honeypot: bots fill this hidden field; humans never see it.
		if ( ! empty( $_POST['carmel_hp'] ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_app', 'ok', $redirect ) ); // pretend success
			exit;
		}

		$data = array(
			'source'    => 'form',
			'name'      => isset( $_POST['carmel_name'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_name'] ) ) : '',
			'email'     => isset( $_POST['carmel_email'] ) ? sanitize_email( wp_unslash( $_POST['carmel_email'] ) ) : '',
			'phone'     => isset( $_POST['carmel_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_phone'] ) ) : '',
			'deal_type' => isset( $_POST['carmel_deal_type'] ) ? sanitize_key( wp_unslash( $_POST['carmel_deal_type'] ) ) : 'loan',
			'address'   => isset( $_POST['carmel_address'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_address'] ) ) : '',
			'message'   => isset( $_POST['carmel_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['carmel_message'] ) ) : '',
		);

		// 事前審査/在庫からの引き継ぎ（希望条件）。案件メタへ extra_ として保存。
		if ( ! empty( $_POST['carmel_extra'] ) && is_array( $_POST['carmel_extra'] ) ) {
			$extra = array();
			foreach ( wp_unslash( $_POST['carmel_extra'] ) as $k => $v ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$extra[ sanitize_key( $k ) ] = sanitize_text_field( $v );
			}
			if ( $extra ) {
				$data['extra'] = $extra;
			}
		}

		$thanks = isset( $_POST['carmel_thanks'] ) ? esc_url_raw( wp_unslash( $_POST['carmel_thanks'] ) ) : '';

		$result = Carmel_Application_Intake::process( $data );

		if ( is_wp_error( $result ) ) {
			wp_safe_redirect( add_query_arg( 'carmel_app', 'invalid', $redirect ) );
			exit;
		}

		if ( $thanks ) {
			wp_safe_redirect( $thanks );
			exit;
		}
		wp_safe_redirect( add_query_arg( 'carmel_app', 'ok', $redirect ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * Render
	 * --------------------------------------------------------------------- */

	/**
	 * @param array $atts
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'type'   => '',      // loan|buyback|lease or empty for selector
				'thanks' => '',
				'title'  => 'お申込み・お問い合わせ',
			),
			$atts,
			self::SHORTCODE
		);

		$preset = in_array( $atts['type'], array( 'loan', 'buyback', 'lease' ), true ) ? $atts['type'] : '';

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput

		echo '<form class="carmel-appform" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<h3>' . esc_html( $atts['title'] ) . '</h3>';
		echo '<input type="hidden" name="action" value="' . esc_attr( self::ACTION ) . '">';
		echo wp_nonce_field( self::ACTION, self::NONCE, true, false ); // phpcs:ignore WordPress.Security.EscapeOutput
		if ( $atts['thanks'] ) {
			echo '<input type="hidden" name="carmel_thanks" value="' . esc_url( $atts['thanks'] ) . '">';
		}

		// Honeypot (visually hidden).
		echo '<div class="carmel-hp" aria-hidden="true"><label>この欄は入力しないでください<input type="text" name="carmel_hp" tabindex="-1" autocomplete="off"></label></div>';

		// Business type.
		if ( $preset ) {
			echo '<input type="hidden" name="carmel_deal_type" value="' . esc_attr( $preset ) . '">';
		} else {
			echo '<label class="carmel-f">ご希望の種別<span class="req">*</span>'
				. '<select name="carmel_deal_type" required>'
				. '<option value="loan">ローンで購入</option>'
				. '<option value="buyback">クルマを売る（買取査定）</option>'
				. '<option value="lease">自社リース</option>'
				. '</select></label>';
		}

		// 事前審査/在庫からの引き継ぎ（GET）→ hidden で intake へ、希望条件を表示。
		echo $this->prefill_block(); // phpcs:ignore WordPress.Security.EscapeOutput

		echo '<label class="carmel-f">お名前<span class="req">*</span><input type="text" name="carmel_name" required></label>';
		echo '<label class="carmel-f">メールアドレス<span class="req">*</span><input type="email" name="carmel_email" required></label>';
		echo '<label class="carmel-f">電話番号<input type="tel" name="carmel_phone"></label>';
		echo '<label class="carmel-f">ご住所（納車先）<input type="text" name="carmel_address" placeholder="例）東京都港区…"></label>';
		echo '<label class="carmel-f">ご要望・ご質問<textarea name="carmel_message" rows="4"></textarea></label>';

		echo '<button type="submit" class="carmel-appform-btn">送信する</button>';
		echo '<p class="carmel-appform-note">送信すると、マイページのログイン情報をメール／LINEでお送りします。</p>';
		echo '</form>';

		return ob_get_clean();
	}

	/** 事前審査/在庫詳細からの希望条件（GET）を hidden＋表示。 */
	private function prefill_block() {
		$price   = isset( $_GET['price'] ) ? (int) $_GET['price'] : 0;
		$down    = isset( $_GET['down'] ) ? (int) $_GET['down'] : 0;
		$months  = isset( $_GET['months'] ) ? (int) $_GET['months'] : 0;
		$vehicle = isset( $_GET['vehicle'] ) ? (int) $_GET['vehicle'] : 0;
		if ( ! $price && ! $down && ! $months && ! $vehicle ) {
			return '';
		}
		$out  = '';
		$rows = array();
		if ( $vehicle && 'carmel_vehicle' === get_post_type( $vehicle ) ) {
			$out .= '<input type="hidden" name="carmel_extra[vehicle_id]" value="' . (int) $vehicle . '">';
			$car  = trim( get_post_meta( $vehicle, 'maker', true ) . ' ' . get_post_meta( $vehicle, 'model', true ) );
			if ( $car ) {
				$rows[] = '車両：' . esc_html( $car );
			}
		}
		if ( $price ) {
			$out .= '<input type="hidden" name="carmel_extra[loan_price]" value="' . (int) $price . '">';
			$rows[] = '車両価格：¥' . number_format( $price );
		}
		if ( $down ) {
			$out .= '<input type="hidden" name="carmel_extra[loan_down]" value="' . (int) $down . '">';
			$rows[] = '頭金：¥' . number_format( $down );
		}
		if ( $months ) {
			$out .= '<input type="hidden" name="carmel_extra[loan_months]" value="' . (int) $months . '">';
			$rows[] = '回数：' . (int) $months . '回';
		}
		if ( $rows ) {
			$out .= '<div class="carmel-appform-prefill"><strong>ご希望条件</strong>　' . implode( '／', $rows ) . '</div>';
		}
		return $out;
	}

	private function banner() {
		$msg = isset( $_GET['carmel_app'] ) ? sanitize_key( $_GET['carmel_app'] ) : '';
		$map = array(
			'ok'      => array( 'success', 'お申込みを受け付けました。マイページのご案内をお送りしました。' ),
			'invalid' => array( 'error', 'お名前と有効なメールアドレスをご入力ください。' ),
			'err'     => array( 'error', '送信に失敗しました。お手数ですが再度お試しください。' ),
		);
		if ( ! isset( $map[ $msg ] ) ) {
			return '';
		}
		return '<div class="carmel-appform-banner carmel-appform-' . esc_attr( $map[ $msg ][0] ) . '">' . esc_html( $map[ $msg ][1] ) . '</div>';
	}

	private function styles() {
		return '<style>
.carmel-appform{max-width:520px;font-size:14px;display:flex;flex-direction:column;gap:.8em;border:1px solid #e0e3ea;border-radius:.6em;padding:1.4em;background:#fff}
.carmel-appform h3{margin:0 0 .3em}
.carmel-f{display:flex;flex-direction:column;gap:.3em;font-weight:bold}
.carmel-f input,.carmel-f select,.carmel-f textarea{font-weight:normal;border:1px solid #ccc;border-radius:.4em;padding:.6em}
.carmel-f .req{color:#c0392b;margin-left:.2em}
.carmel-appform-btn{background:#2e86de;color:#fff;border:0;border-radius:.4em;padding:.8em;font-size:1em;cursor:pointer}
.carmel-appform-note{font-size:.8em;color:#888;margin:0}
.carmel-hp{position:absolute;left:-9999px;height:0;overflow:hidden}
.carmel-appform-banner{max-width:520px;padding:.8em 1em;border-radius:.4em;margin-bottom:1em}
.carmel-appform-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-appform-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-appform-prefill{background:#f1ecfb;border:1px solid #ddd2f5;border-radius:.4em;padding:.6em .8em;font-size:.88em;color:#46414f}
</style>';
	}
}
