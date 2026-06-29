<?php
/**
 * 会員ページ（マイページ）への再訪導線。
 *
 * 節目（納車完了）に、お客様へ「会員ページで車検・保険・お手続きを確認」案内を
 * 送る。LINE（プロライン/公式）→メールのフォールバックで届き、リンクは LIFF会員
 * ログインURL（`carmel_member_page_url`）があればワンタップ、無ければマイページ。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Member_Nudge {

	/** @var Carmel_Member_Nudge|null */
	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_filter( 'carmel_routing_table', array( $this, 'add_routing' ) );
		add_filter( 'carmel_notification_message', array( $this, 'add_message' ), 10, 3 );
		add_action( 'carmel_deal_status_changed', array( $this, 'on_status_changed' ), 10, 3 );
	}

	/** 会員ページURL（LIFF会員ログイン優先・無ければマイページ）。 */
	public static function member_url() {
		$u = get_option( 'carmel_member_page_url', '' );
		return $u ? $u : home_url( '/' . ltrim( apply_filters( 'carmel_mypage_slug', 'mypage' ), '/' ) );
	}

	/** 再訪導線を送る節目ステータス（フィルタで調整可）。 */
	private static function nudge_statuses() {
		return apply_filters( 'carmel_member_nudge_statuses', array( 'delivered', 'lease_delivered' ) );
	}

	public function on_status_changed( $deal_id, $new, $old ) {
		if ( ! in_array( $new, self::nudge_statuses(), true ) ) {
			return;
		}
		Carmel_Notifier::notify(
			'mypage_invite',
			array(
				'event_id' => 'mypage_invite:' . (int) $deal_id . ':' . $new,
				'deal_id'  => (int) $deal_id,
				'vars'     => array(
					'name' => get_post_meta( $deal_id, 'applicant_name', true ),
					'url'  => self::member_url(),
				),
			)
		);
	}

	public function add_routing( $table ) {
		$table['mypage_invite'] = array(
			array( 'audience' => 'customer', 'channel' => 'proline', 'fallback' => 'mail' ),
		);
		return $table;
	}

	public function add_message( $message, $event_type, $context ) {
		if ( 'mypage_invite' === $event_type ) {
			$vars = isset( $context['vars'] ) ? (array) $context['vars'] : array();
			$name = isset( $vars['name'] ) ? $vars['name'] : 'お客様';
			$url  = isset( $vars['url'] ) ? $vars['url'] : self::member_url();
			$message['subject'] = '会員ページのご案内';
			$message['body']    = $name . " 様\nこの度はご納車おめでとうございます。\n車検・保険・各種お手続きやアフターサポートは会員ページからご確認いただけます。\n" . $url;
		} elseif ( in_array( $event_type, array( 'inspection_notice', 'insurance_notice', 'maintenance_notice' ), true ) ) {
			// 車検・保険・点検の案内に会員ページURLを付加（ご予約・確認導線）。
			$body = isset( $message['body'] ) ? (string) $message['body'] : '';
			if ( false === strpos( $body, self::member_url() ) ) {
				$message['body'] = rtrim( $body ) . "\n会員ページ：" . self::member_url();
			}
		}
		return $message;
	}
}
