<?php
/**
 * カーメル：車両ページ 追加スペック（競合標準項目）
 * ---------------------------------------------------------------------------
 * 在庫（portfolio）編集画面に、競合中古車サイトで標準的な項目の入力欄を追加し、
 * 入力があれば車両詳細ページの「基本情報」に表示する。
 *   テキスト項目：保証期間 / 保証距離 / 乗車定員 / 燃費  → 基本情報の表に行を追加
 *   フラグ項目  ：ワンオーナー / 禁煙車 / 記録簿あり      → 基本情報の見出し下にバッジ表示
 * 空の項目は表示しない。保存は post_meta。固有名＋function_exists ガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_specx_fields' ) ) {
	function carmelx_specx_fields() {
		return array(
			'text' => array(
				'cx_hoshou_kikan' => array( '保証期間', '例：12ヶ月' ),
				'cx_hoshou_kyori' => array( '保証距離', '例：走行無制限 / 30,000km' ),
				'cx_teiin'        => array( '乗車定員', '例：5' ),
				'cx_nenpi'        => array( '燃費',     '例：18.0km/L（WLTC）' ),
			),
			'flag' => array(
				'cx_one_owner' => 'ワンオーナー',
				'cx_kinen'     => '禁煙車',
				'cx_kirokubo'  => '記録簿あり',
			),
		);
	}
}

/* ---- 編集画面：入力欄 ---- */
if ( ! function_exists( 'carmelx_specx_box' ) ) {
	function carmelx_specx_box( $post ) {
		wp_nonce_field( 'carmelx_specx_save', 'carmelx_specx_nonce' );
		$f = carmelx_specx_fields();
		echo '<div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px 18px;">';
		foreach ( $f['text'] as $k => $meta ) {
			$v = get_post_meta( $post->ID, $k, true );
			echo '<p style="margin:0;"><label for="' . esc_attr( $k ) . '" style="display:block;font-weight:600;margin-bottom:4px;">' . esc_html( $meta[0] ) . '</label>';
			echo '<input type="text" id="' . esc_attr( $k ) . '" name="carmelx_specx[' . esc_attr( $k ) . ']" value="' . esc_attr( (string) $v ) . '" placeholder="' . esc_attr( $meta[1] ) . '" style="width:100%;"></p>';
		}
		echo '</div>';
		echo '<p style="margin:12px 0 4px;font-weight:600;">チェック項目（安心材料）</p><div style="display:flex;flex-wrap:wrap;gap:14px;">';
		foreach ( $f['flag'] as $k => $label ) {
			$v = get_post_meta( $post->ID, $k, true );
			echo '<label style="display:inline-flex;align-items:center;gap:5px;"><input type="checkbox" name="carmelx_specx_flag[' . esc_attr( $k ) . ']" value="1"' . checked( $v, '1', false ) . '> ' . esc_html( $label ) . '</label>';
		}
		echo '</div>';
		echo '<p style="color:#888;font-size:12px;margin:10px 0 0;">※ 入力・チェックして「更新」すると、車両ページの基本情報に表示されます。空欄は表示されません。</p>';
	}
}

if ( ! function_exists( 'carmelx_specx_add_box' ) ) {
	function carmelx_specx_add_box() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_meta_box( 'carmelx_specx', '追加スペック（保証・定員・燃費・ワンオーナー等）', 'carmelx_specx_box', 'portfolio', 'normal', 'high' );
	}
	add_action( 'add_meta_boxes', 'carmelx_specx_add_box' );
}

if ( ! function_exists( 'carmelx_specx_save' ) ) {
	function carmelx_specx_save( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
		if ( ! isset( $_POST['carmelx_specx_nonce'] ) || ! wp_verify_nonce( $_POST['carmelx_specx_nonce'], 'carmelx_specx_save' ) ) { return; }
		if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$f = carmelx_specx_fields();
		$in = isset( $_POST['carmelx_specx'] ) && is_array( $_POST['carmelx_specx'] ) ? wp_unslash( $_POST['carmelx_specx'] ) : array();
		foreach ( $f['text'] as $k => $meta ) {
			$val = isset( $in[ $k ] ) ? sanitize_text_field( (string) $in[ $k ] ) : '';
			update_post_meta( $post_id, $k, $val );
		}
		$flags = isset( $_POST['carmelx_specx_flag'] ) && is_array( $_POST['carmelx_specx_flag'] ) ? $_POST['carmelx_specx_flag'] : array();
		foreach ( $f['flag'] as $k => $label ) {
			update_post_meta( $post_id, $k, ! empty( $flags[ $k ] ) ? '1' : '' );
		}
	}
	add_action( 'save_post_portfolio', 'carmelx_specx_save' );
}

/* ---- front：基本情報に差し込む ---- */
if ( ! function_exists( 'carmelx_specx_render' ) ) {
	function carmelx_specx_render() {
		if ( is_admin() || ! is_singular( 'portfolio' ) ) { return; }
		$pid = get_the_ID();
		$f = carmelx_specx_fields();

		$rows = array();
		$hk = trim( (string) get_post_meta( $pid, 'cx_hoshou_kikan', true ) );
		$hy = trim( (string) get_post_meta( $pid, 'cx_hoshou_kyori', true ) );
		if ( $hk !== '' || $hy !== '' ) {
			$rows['保証'] = trim( $hk . ( ( $hk !== '' && $hy !== '' ) ? '・' : '' ) . $hy );
		}
		$teiin = trim( (string) get_post_meta( $pid, 'cx_teiin', true ) );
		if ( $teiin !== '' ) { $rows['乗車定員'] = ( ctype_digit( $teiin ) ? $teiin . '名' : $teiin ); }
		$nenpi = trim( (string) get_post_meta( $pid, 'cx_nenpi', true ) );
		if ( $nenpi !== '' ) { $rows['燃費'] = $nenpi; }

		$badges = array();
		foreach ( $f['flag'] as $k => $label ) {
			if ( get_post_meta( $pid, $k, true ) === '1' ) { $badges[] = $label; }
		}

		if ( empty( $rows ) && empty( $badges ) ) { return; }

		$data = wp_json_encode( array( 'rows' => $rows, 'badges' => $badges ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		?>
<style id="carmel-specx-css">
.cmx-flags{display:flex;flex-wrap:wrap;gap:7px;margin:0 0 14px;}
.cmx-flag{display:inline-flex;align-items:center;gap:5px;background:#eef7f1;border:1px solid #cfe7d8;color:#1c7a3a;border-radius:999px;padding:5px 13px;font-size:12.5px;font-weight:800;}
.cmx-flag svg{width:13px;height:13px;}
</style>
<script id="carmel-specx-js">
(function(){
	var D = <?php echo $data; // phpcs:ignore ?>;
	function run(){
		/* 「基本情報」の見出しを正確に特定（上の小さいスペック表に混ざらないように） */
		var head=null;
		document.querySelectorAll('.vcex-icon-box-heading, .vcex-heading-inner, h2, h3').forEach(function(h){
			if(!head && h.textContent.trim()==='基本情報') head=h;
		});
		if(!head) return;
		var sec = head.closest('.vc_section') || head.closest('section') || head.parentElement;
		if(!sec || sec.getAttribute('data-cmx-specx')==='1') return;
		sec.setAttribute('data-cmx-specx','1');
		/* テキスト項目を基本情報の表に行追加（左右の表に振り分け） */
		var tables = sec.querySelectorAll('.c-table01');
		var keys = Object.keys(D.rows||{});
		if(tables.length && keys.length){
			keys.forEach(function(k,i){
				var t = tables[i % tables.length];
				var tb = (t.tBodies && t.tBodies[0]) ? t.tBodies[0] : t;
				var tr = document.createElement('tr');
				tr.innerHTML = '<th>'+k+'</th><td>'+String(D.rows[k]).replace(/</g,'&lt;')+'</td>';
				tb.appendChild(tr);
			});
		}
		/* フラグをバッジで「基本情報」見出しの直後に表示 */
		if((D.badges||[]).length){
			var check='<svg viewBox="0 0 24 24" fill="#2cac44"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>';
			var wrap=document.createElement('div'); wrap.className='cmx-flags';
			wrap.innerHTML = D.badges.map(function(b){ return '<span class="cmx-flag">'+check+String(b).replace(/</g,'&lt;')+'</span>'; }).join('');
			var hbox = head.closest('.vcex-icon-box') || head.parentElement;
			if(hbox && hbox.parentNode){ hbox.parentNode.insertBefore(wrap, hbox.nextSibling); }
			else { sec.insertBefore(wrap, sec.firstChild); }
		}
	}
	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', function(){ setTimeout(run,90); });
	else setTimeout(run,90);
})();
</script>
		<?php
	}
	add_action( 'wp_footer', 'carmelx_specx_render', 22 );
}
