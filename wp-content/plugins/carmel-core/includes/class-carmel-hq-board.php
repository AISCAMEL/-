<?php
/**
 * HQ cross-store kanban board.
 *
 * Shortcode [carmel_hq_board] (HQ only — carmel_manage_stores). Shows deals as
 * cards grouped into status columns for a chosen business type, with HTML5
 * drag-and-drop. Dropping a card onto a column calls Carmel_Deal_Status::change
 * via admin-ajax (nonce + cap verified), so all the usual side effects
 * (notifications, vehicle sync, audit log) fire.
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_HQ_Board {

	/** @var Carmel_HQ_Board|null */
	private static $instance = null;

	const SHORTCODE  = 'carmel_hq_board';
	const AJAX       = 'carmel_board_move';
	const NONCE      = 'carmel_board';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function register_hooks() {
		add_shortcode( self::SHORTCODE, array( $this, 'render' ) );
		add_action( 'wp_ajax_' . self::AJAX, array( $this, 'ajax_move' ) );
	}

	/** Ordered status columns per business type. */
	public static function columns( $type ) {
		$map = array(
			'loan'    => array( 'provisional', 'scored', 'screening', 'approved', 'rejected', 'matched', 'doc_prep', 'contracted', 'delivery_prep', 'delivered', 'after_support', 'closed' ),
			'buyback' => array( 'appraisal_request', 'appraising', 'quoted', 'bb_agreed', 'bb_declined', 'bb_doc_prep', 'bb_collected', 'bb_closed' ),
			'lease'   => array( 'lease_request', 'lease_screening', 'lease_contracted', 'lease_delivered', 'lease_active', 'lease_completed', 'lease_closed' ),
		);
		return isset( $map[ $type ] ) ? $map[ $type ] : $map['loan'];
	}

	/* --------------------------------------------------------------------- *
	 * AJAX move
	 * --------------------------------------------------------------------- */

	public function ajax_move() {
		check_ajax_referer( self::NONCE, 'nonce' );

		if ( ! current_user_can( 'carmel_manage_stores' ) ) {
			wp_send_json_error( array( 'message' => '権限がありません。' ), 403 );
		}

		$deal_id   = isset( $_POST['deal_id'] ) ? (int) $_POST['deal_id'] : 0;
		$to_status = isset( $_POST['to_status'] ) ? sanitize_key( wp_unslash( $_POST['to_status'] ) ) : '';

		// Validate target belongs to some known flow.
		$valid = array_merge( self::columns( 'loan' ), self::columns( 'buyback' ), self::columns( 'lease' ) );
		if ( ! $deal_id || ! in_array( $to_status, $valid, true ) ) {
			wp_send_json_error( array( 'message' => '不正なパラメータです。' ), 400 );
		}

		$result = Carmel_Deal_Status::change( $deal_id, $to_status );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ), 400 );
		}

		wp_send_json_success( array( 'deal_id' => $deal_id, 'to_status' => $to_status ) );
	}

	/* --------------------------------------------------------------------- *
	 * Render
	 * --------------------------------------------------------------------- */

	/**
	 * @return string
	 */
	public function render() {
		if ( ! is_user_logged_in() || ! current_user_can( 'carmel_manage_stores' ) ) {
			return '<p class="carmel-notice">カンバンボードを表示する権限がありません。</p>';
		}

		$type     = isset( $_GET['board_type'] ) ? sanitize_key( $_GET['board_type'] ) : 'loan';
		if ( ! in_array( $type, array( 'loan', 'buyback', 'lease' ), true ) ) {
			$type = 'loan';
		}
		$store_id = isset( $_GET['board_store'] ) ? (int) $_GET['board_store'] : 0;

		$meta_query = array( array( 'key' => 'deal_type', 'value' => $type ) );
		if ( $store_id ) {
			$meta_query[] = array( 'key' => 'store_id', 'value' => $store_id );
		}

		$deals = get_posts(
			array(
				'post_type'      => 'carmel_deal',
				'post_status'    => 'publish',
				'posts_per_page' => 300,
				'meta_query'     => $meta_query,
				'orderby'        => 'modified',
				'order'          => 'DESC',
			)
		);

		// Bucket deals by status.
		$buckets = array();
		foreach ( $deals as $deal ) {
			$s = get_post_meta( $deal->ID, 'deal_status', true );
			$buckets[ $s ][] = $deal;
		}

		$labels  = Carmel_MyPage::status_labels();
		$columns = self::columns( $type );

		ob_start();
		echo $this->styles(); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<div class="carmel-board">';
		echo $this->toolbar( $type, $store_id ); // phpcs:ignore WordPress.Security.EscapeOutput

		echo '<div class="carmel-board-cols">';
		foreach ( $columns as $status ) {
			$items = isset( $buckets[ $status ] ) ? $buckets[ $status ] : array();
			$label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;

			echo '<div class="carmel-col" data-status="' . esc_attr( $status ) . '">';
			echo '<div class="carmel-col-head">' . esc_html( $label ) . ' <span class="carmel-col-n">' . count( $items ) . '</span></div>';
			echo '<div class="carmel-col-body" data-status="' . esc_attr( $status ) . '">';
			foreach ( $items as $deal ) {
				$name  = get_post_meta( $deal->ID, 'applicant_name', true );
				$store = (int) get_post_meta( $deal->ID, 'store_id', true );
				$sname = $store ? get_the_title( $store ) : '';
				echo '<div class="carmel-card-mini" draggable="true" data-deal="' . (int) $deal->ID . '">';
				echo '<div class="carmel-mini-id">#' . (int) $deal->ID . '</div>';
				echo '<div class="carmel-mini-name">' . esc_html( $name ? $name : $deal->post_title ) . '</div>';
				if ( $sname ) {
					echo '<div class="carmel-mini-store">' . esc_html( $sname ) . '</div>';
				}
				echo '</div>';
			}
			echo '</div></div>';
		}
		echo '</div>'; // cols

		echo '<div class="carmel-board-msg" id="carmel-board-msg"></div>';
		echo '</div>'; // board

		echo $this->script(); // phpcs:ignore WordPress.Security.EscapeOutput
		return ob_get_clean();
	}

	private function toolbar( $type, $store_id ) {
		$base = remove_query_arg( array( 'board_type', 'board_store' ) );
		$tabs = array( 'loan' => 'ローン販売', 'buyback' => '車買取', 'lease' => '自社リース' );

		$out = '<div class="carmel-board-bar"><div class="carmel-tabs">';
		foreach ( $tabs as $key => $label ) {
			$url = esc_url( add_query_arg( 'board_type', $key, $base ) );
			$cls = ( $key === $type ) ? 'carmel-tab active' : 'carmel-tab';
			$out .= '<a class="' . $cls . '" href="' . $url . '">' . esc_html( $label ) . '</a>';
		}
		$out .= '</div>';

		// Store filter.
		$stores = get_posts( array( 'post_type' => 'carmel_store', 'post_status' => 'publish', 'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC' ) );
		if ( $stores ) {
			$out .= '<form method="get" class="carmel-store-filter"><input type="hidden" name="board_type" value="' . esc_attr( $type ) . '">';
			$out .= '<select name="board_store" onchange="this.form.submit()"><option value="0">全店</option>';
			foreach ( $stores as $st ) {
				$sel = selected( $store_id, $st->ID, false );
				$out .= '<option value="' . (int) $st->ID . '" ' . $sel . '>' . esc_html( $st->post_title ) . '</option>';
			}
			$out .= '</select></form>';
		}
		$out .= '</div>';
		return $out;
	}

	private function script() {
		$ajax  = esc_url( admin_url( 'admin-ajax.php' ) );
		$nonce = wp_create_nonce( self::NONCE );
		$action = self::AJAX;

		return <<<JS
<script>
(function(){
	var ajaxUrl = '{$ajax}', nonce = '{$nonce}', action = '{$action}';
	var dragId = null;
	var msg = document.getElementById('carmel-board-msg');

	function notify(text, ok){
		msg.textContent = text;
		msg.className = 'carmel-board-msg ' + (ok ? 'ok' : 'err');
		setTimeout(function(){ msg.textContent=''; msg.className='carmel-board-msg'; }, 4000);
	}

	document.querySelectorAll('.carmel-card-mini').forEach(function(card){
		card.addEventListener('dragstart', function(e){
			dragId = this.getAttribute('data-deal');
			e.dataTransfer.effectAllowed = 'move';
		});
	});

	document.querySelectorAll('.carmel-col-body').forEach(function(col){
		col.addEventListener('dragover', function(e){ e.preventDefault(); this.classList.add('over'); });
		col.addEventListener('dragleave', function(){ this.classList.remove('over'); });
		col.addEventListener('drop', function(e){
			e.preventDefault();
			this.classList.remove('over');
			var status = this.getAttribute('data-status');
			var card = document.querySelector('.carmel-card-mini[data-deal="'+dragId+'"]');
			if(!card || !dragId){ return; }
			var body = this;
			var params = new URLSearchParams();
			params.append('action', action);
			params.append('nonce', nonce);
			params.append('deal_id', dragId);
			params.append('to_status', status);
			fetch(ajaxUrl, {method:'POST', credentials:'same-origin', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: params.toString()})
				.then(function(r){ return r.json(); })
				.then(function(res){
					if(res && res.success){
						body.appendChild(card);
						notify('案件 #'+dragId+' を更新しました。', true);
					} else {
						notify((res && res.data && res.data.message) ? res.data.message : '更新に失敗しました。', false);
					}
				})
				.catch(function(){ notify('通信エラーが発生しました。', false); });
			dragId = null;
		});
	});
})();
</script>
JS;
	}

	private function styles() {
		return '<style>
.carmel-board{font-size:13px}
.carmel-board-bar{display:flex;gap:1em;align-items:center;flex-wrap:wrap;margin-bottom:1em}
.carmel-tabs{display:flex;gap:.3em}
.carmel-tab{padding:.4em 1em;border-radius:.4em;background:#eef0f4;color:#333;text-decoration:none}
.carmel-tab.active{background:#1a1a2e;color:#fff}
.carmel-store-filter select{padding:.35em;border:1px solid #ccc;border-radius:.3em}
.carmel-board-cols{display:flex;gap:.6em;overflow-x:auto;padding-bottom:1em;align-items:flex-start}
.carmel-col{flex:0 0 200px;background:#f4f6fb;border-radius:.5em;min-height:120px}
.carmel-col-head{padding:.6em .8em;font-weight:bold;border-bottom:2px solid #dfe3ea}
.carmel-col-n{background:#1a1a2e;color:#fff;border-radius:1em;padding:0 .6em;font-size:.8em;font-weight:normal}
.carmel-col-body{padding:.5em;min-height:80px}
.carmel-col-body.over{background:#e6effb;outline:2px dashed #2e86de}
.carmel-card-mini{background:#fff;border:1px solid #e0e3ea;border-radius:.4em;padding:.5em .6em;margin-bottom:.5em;cursor:grab;box-shadow:0 1px 2px rgba(0,0,0,.05)}
.carmel-card-mini:active{cursor:grabbing}
.carmel-mini-id{font-size:.78em;color:#888}
.carmel-mini-name{font-weight:bold}
.carmel-mini-store{font-size:.78em;color:#2e86de}
.carmel-board-msg{margin-top:.6em;padding:.4em .8em;border-radius:.3em}
.carmel-board-msg.ok{background:#e8f8f3;color:#0e6e58}
.carmel-board-msg.err{background:#fdecea;color:#a5281b}
.carmel-notice{padding:1em;background:#fdecea;border:1px solid #c0392b;border-radius:.4em}
</style>';
	}
}
