<?php
/**
 * 商談（案件）タイムライン。
 *
 * ショートコード [carmel_deal_timeline]（`?deal=ID`）。案件のステータス履歴
 * （_carmel_status_history）と担当者メモ（_carmel_memos）を時系列で表示する。
 * 閲覧は本部／担当加盟店／本人（顧客）。メモ追加は本部・担当店のみ。
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_Deal_Timeline {

	/** @var Carmel_Deal_Timeline|null */
	private static $instance = null;

	const SHORTCODE   = 'carmel_deal_timeline';
	const MEMO_ACTION = 'carmel_deal_memo';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'admin_post_' . self::MEMO_ACTION, array( $this, 'handle_memo' ) );
	}

	/** タイムラインページURL。 */
	public static function url( $deal_id ) {
		$slug = ltrim( apply_filters( 'carmel_deal_timeline_page_slug', 'deal' ), '/' );
		return add_query_arg( 'deal', (int) $deal_id, home_url( '/' . $slug ) );
	}

	/* --------------------------------------------------------------------- *
	 * アクセス制御
	 * --------------------------------------------------------------------- */

	private function can_view( $deal_id ) {
		if ( ! is_user_logged_in() || 'carmel_deal' !== get_post_type( $deal_id ) ) {
			return false;
		}
		if ( current_user_can( 'carmel_manage_stores' ) ) {
			return true;
		}
		$uid = get_current_user_id();
		if ( (int) get_post_meta( $deal_id, 'customer_id', true ) === $uid ) {
			return true;
		}
		return $this->is_store_of_deal( $deal_id );
	}

	private function is_store_of_deal( $deal_id ) {
		if ( ! current_user_can( 'carmel_change_deal_status' ) ) {
			return false;
		}
		$my = (int) get_user_meta( get_current_user_id(), 'store_id', true );
		return $my && $my === (int) get_post_meta( $deal_id, 'store_id', true );
	}

	private function can_memo( $deal_id ) {
		return current_user_can( 'carmel_manage_stores' ) || $this->is_store_of_deal( $deal_id );
	}

	/* --------------------------------------------------------------------- *
	 * メモ追加
	 * --------------------------------------------------------------------- */

	public function handle_memo() {
		$deal_id  = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$redirect = wp_get_referer() ? wp_get_referer() : self::url( $deal_id );
		if ( ! wp_verify_nonce( isset( $_POST['carmel_memo_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['carmel_memo_nonce'] ) ) : '', self::MEMO_ACTION . '_' . $deal_id ) ) {
			wp_die( esc_html__( '不正なリクエストです。', 'carmel-core' ), '', array( 'response' => 400 ) );
		}
		if ( ! $this->can_memo( $deal_id ) ) {
			wp_die( esc_html__( '権限がありません。', 'carmel-core' ), '', array( 'response' => 403 ) );
		}
		$text = isset( $_POST['memo'] ) ? sanitize_textarea_field( wp_unslash( $_POST['memo'] ) ) : '';
		if ( '' === $text ) {
			wp_safe_redirect( add_query_arg( 'carmel_tl', 'err', $redirect ) );
			exit;
		}
		$memos = get_post_meta( $deal_id, '_carmel_memos', true );
		if ( ! is_array( $memos ) ) {
			$memos = array();
		}
		$memos[] = array( 'text' => $text, 'user_id' => get_current_user_id(), 'time' => current_time( 'mysql' ) );
		update_post_meta( $deal_id, '_carmel_memos', $memos );

		// メモも「活動」とみなし停滞アラートをリセット。
		update_post_meta( $deal_id, '_deal_activity_at', current_time( 'mysql' ) );
		delete_post_meta( $deal_id, '_stale_alerted' );

		do_action( 'carmel_deal_memo_added', $deal_id );
		wp_safe_redirect( add_query_arg( 'carmel_tl', 'ok', $redirect ) );
		exit;
	}

	/* --------------------------------------------------------------------- *
	 * 表示
	 * --------------------------------------------------------------------- */

	public function render( $atts ) {
		$atts    = shortcode_atts( array( 'id' => 0 ), $atts, self::SHORTCODE );
		$deal_id = $atts['id'] ? (int) $atts['id'] : ( isset( $_GET['deal'] ) ? (int) $_GET['deal'] : 0 );

		if ( ! $deal_id || ! $this->can_view( $deal_id ) ) {
			return '<p class="carmel-notice">このタイムラインを表示する権限がありません。</p>';
		}

		$events = $this->build_events( $deal_id );
		$name   = (string) get_post_meta( $deal_id, 'applicant_name', true );

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo $this->banner(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-tl"><h2>商談タイムライン　#' . (int) $deal_id . '　' . esc_html( $name ) . '</h2>';

		if ( $this->can_memo( $deal_id ) ) {
			echo $this->memo_form( $deal_id ); // phpcs:ignore WordPress.Security.EscapeOutput
		}

		if ( empty( $events ) ) {
			echo '<p>履歴はまだありません。</p></div>';
			return ob_get_clean();
		}

		echo '<ul class="carmel-tl-list">';
		foreach ( $events as $e ) {
			echo '<li class="carmel-tl-item carmel-tl-' . esc_attr( $e['type'] ) . '">';
			echo '<span class="carmel-tl-icon">' . esc_html( $e['icon'] ) . '</span>';
			echo '<div class="carmel-tl-body"><div class="carmel-tl-when">' . esc_html( mysql2date( 'Y-m-d H:i', $e['time'] ) ) . '</div>';
			echo '<div class="carmel-tl-text">' . wp_kses_post( $e['text'] ) . '</div>';
			if ( ! empty( $e['by'] ) ) {
				echo '<div class="carmel-tl-by">' . esc_html( $e['by'] ) . '</div>';
			}
			echo '</div></li>';
		}
		echo '</ul></div>';
		return ob_get_clean();
	}

	/**
	 * ステータス履歴＋メモ＋起票を統合し、新しい順に並べる。
	 *
	 * @param int $deal_id
	 * @return array
	 */
	private function build_events( $deal_id ) {
		$events = array();
		$labels = class_exists( 'Carmel_MyPage' ) ? Carmel_MyPage::status_labels() : array();

		// 起票。
		$events[] = array(
			'type' => 'create',
			'icon' => '🟢',
			'time' => get_post_field( 'post_date', $deal_id ),
			'text' => '商談を起票',
			'by'   => '',
		);

		// ステータス履歴。
		$history = get_post_meta( $deal_id, '_carmel_status_history', true );
		if ( is_array( $history ) ) {
			foreach ( $history as $h ) {
				$from = isset( $h['from'] ) ? $h['from'] : '';
				$to   = isset( $h['to'] ) ? $h['to'] : '';
				$fl   = isset( $labels[ $from ] ) ? $labels[ $from ] : $from;
				$tl   = isset( $labels[ $to ] ) ? $labels[ $to ] : $to;
				$text = 'ステータス変更：' . ( $fl ? esc_html( $fl ) . ' → ' : '' ) . '<strong>' . esc_html( $tl ) . '</strong>';
				if ( ! empty( $h['note'] ) ) {
					$text .= '<br><span class="carmel-tl-note">' . esc_html( $h['note'] ) . '</span>';
				}
				$events[] = array(
					'type' => 'status',
					'icon' => '📦',
					'time' => isset( $h['time'] ) ? $h['time'] : '',
					'text' => $text,
					'by'   => $this->actor( $h ),
				);
			}
		}

		// メモ。
		$memos = get_post_meta( $deal_id, '_carmel_memos', true );
		if ( is_array( $memos ) ) {
			foreach ( $memos as $m ) {
				$events[] = array(
					'type' => 'memo',
					'icon' => '📝',
					'time' => isset( $m['time'] ) ? $m['time'] : '',
					'text' => nl2br( esc_html( isset( $m['text'] ) ? $m['text'] : '' ) ),
					'by'   => ! empty( $m['user_id'] ) ? get_the_author_meta( 'display_name', $m['user_id'] ) : '',
				);
			}
		}

		// 新しい順。
		usort( $events, function ( $a, $b ) {
			return strcmp( (string) $b['time'], (string) $a['time'] );
		} );
		return $events;
	}

	private function actor( $h ) {
		if ( ! empty( $h['system'] ) ) {
			return 'システム';
		}
		if ( ! empty( $h['user_id'] ) ) {
			return get_the_author_meta( 'display_name', $h['user_id'] );
		}
		return '';
	}

	private function memo_form( $deal_id ) {
		$nonce = wp_create_nonce( self::MEMO_ACTION . '_' . $deal_id );
		return '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="carmel-tl-memo">'
			. '<input type="hidden" name="action" value="' . esc_attr( self::MEMO_ACTION ) . '">'
			. '<input type="hidden" name="deal_id" value="' . (int) $deal_id . '">'
			. '<input type="hidden" name="carmel_memo_nonce" value="' . esc_attr( $nonce ) . '">'
			. '<textarea name="memo" rows="2" placeholder="対応メモを追加（来店日・電話内容・次回約束など）"></textarea>'
			. '<button type="submit" class="carmel-btn carmel-btn-purple">メモを追加</button></form>';
	}

	private function banner() {
		$m = isset( $_GET['carmel_tl'] ) ? sanitize_key( $_GET['carmel_tl'] ) : '';
		if ( 'ok' === $m ) {
			return '<div class="carmel-banner carmel-banner-success">メモを追加しました。</div>';
		}
		if ( 'err' === $m ) {
			return '<div class="carmel-banner carmel-banner-error">内容を入力してください。</div>';
		}
		return '';
	}

	private function styles() {
		return '<style>
.carmel-tl{font-size:14px;max-width:640px}
.carmel-tl-memo{display:flex;flex-direction:column;gap:.4em;margin:.6em 0 1.2em}
.carmel-tl-memo textarea{border:1px solid #ccc;border-radius:.4em;padding:.5em}
.carmel-btn{display:inline-block;border:0;border-radius:.3em;padding:.5em 1.1em;color:#fff;cursor:pointer;font-size:.9em;align-self:flex-start}
.carmel-btn-purple{background:#6b4fbb}
.carmel-tl-list{list-style:none;padding:0;margin:0;position:relative}
.carmel-tl-list:before{content:"";position:absolute;left:13px;top:.4em;bottom:.4em;width:2px;background:#e7e2ef}
.carmel-tl-item{display:flex;gap:.7em;position:relative;padding:.5em 0}
.carmel-tl-icon{flex:0 0 auto;width:28px;height:28px;border-radius:50%;background:#fff;border:2px solid #e7e2ef;display:flex;align-items:center;justify-content:center;font-size:.85em;z-index:1}
.carmel-tl-memo .carmel-tl-icon{border-color:#6b4fbb}
.carmel-tl-body{background:#fff;border:1px solid #e7e2ef;border-radius:10px;padding:.5em .8em;flex:1}
.carmel-tl-when{font-size:.78em;color:#9298a5}
.carmel-tl-text{margin:.2em 0;line-height:1.6}
.carmel-tl-note{color:#7a7488;font-size:.9em}
.carmel-tl-by{font-size:.78em;color:#888}
.carmel-tl-memo .carmel-tl-body,.carmel-tl-item.carmel-tl-memo .carmel-tl-body{background:#faf9fc}
.carmel-banner{padding:.7em 1em;border-radius:.4em;margin:1em 0}
.carmel-banner-success{background:#e8f8f3;color:#0e6e58;border:1px solid #16a085}
.carmel-banner-error{background:#fdecea;color:#a5281b;border:1px solid #c0392b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
