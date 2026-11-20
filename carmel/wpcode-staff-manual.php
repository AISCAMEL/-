<?php
/**
 * カーメル：スタッフ手順マニュアル（各管理画面に埋め込み・管理画面で編集可能）
 * ---------------------------------------------------------------------------
 * ・在庫一覧／在庫の編集・新規／店舗にふりわけ の各画面に、折りたたみ式の
 *   「スタッフ手順」パネルをステップ番号付きで自動表示。
 * ・手順の文章は 在庫 → サブメニュー「手順マニュアル」から、コード無しで
 *   追加・編集・削除できる（wp_options に保存）。
 * ・初期状態でも使えるよう、代表的な手順を既定値として同梱。
 *
 * 導入 : WPCode → PHP Snippet → 自動挿入／あらゆる場所で実行 → 優先順位 1 → 有効化。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* 対応画面：key => 表示名 */
if ( ! function_exists( 'carmelx_manual_screens' ) ) {
	function carmelx_manual_screens() {
		return array(
			'portfolio_list' => '在庫一覧',
			'portfolio_edit' => '在庫の編集・新規',
			'shop_checklist' => '店舗にふりわけ',
		);
	}
}

/* 既定の手順（初期値。管理画面で保存すると上書きされる） */
if ( ! function_exists( 'carmelx_manual_defaults' ) ) {
	function carmelx_manual_defaults() {
		return array(
			'portfolio_list' => array(
				'title' => '在庫の店舗ふりわけ 手順',
				'steps' => array(
					array( 'head' => '未設定の在庫を絞り込む', 'body' => '上の「店舗で絞り込み」で「未設定のみ」を選ぶと、まだ店舗が決まっていない在庫だけ表示されます。' ),
					array( 'head' => '車両を選ぶ', 'body' => '割り当てたい車両の左のチェックボックスにチェックを入れます（複数OK）。' ),
					array( 'head' => '店舗を選んで適用', 'body' => '上の「一括操作」から「店舗に割当：◯◯店」を選び、「適用」を押します。' ),
					array( 'head' => '確認', 'body' => '「店舗」列に選んだ店舗名が入れば完了です。電話・LINE・問い合わせも自動で店舗に合わせて設定されます。' ),
				),
			),
			'shop_checklist' => array(
				'title' => '店舗にふりわけ（チェックリスト）手順',
				'steps' => array(
					array( 'head' => '表示を絞る', 'body' => '「表示」を「未設定のみ」にすると、店舗未設定の在庫だけ出ます。' ),
					array( 'head' => 'チェックで選ぶ', 'body' => 'サムネ付きの一覧から、割り当てたい車両にチェック。「全選択」も使えます。' ),
					array( 'head' => '店舗を選ぶ', 'body' => '上の「割り当てる店舗」で店舗を選択します。' ),
					array( 'head' => '割り当てる', 'body' => '「選択した在庫をこの店舗に割り当てる」を押すと完了。「◯台を割り当てました」と表示されます。' ),
				),
			),
			'portfolio_edit' => array(
				'title' => '在庫の入力 手順',
				'steps' => array(
					array( 'head' => '基本情報を入力', 'body' => 'メーカー・年式・走行距離・車検など、STEP1の基本情報を入力します。' ),
					array( 'head' => '装備を選ぶ', 'body' => 'STEP2で装備（ナビ・安全装備など）をチェックします。' ),
					array( 'head' => '追加の基本情報', 'body' => '「追加の基本情報（法定整備・寒冷地仕様・状態・保証内容）」も入力すると、詳細ページにアイコン付きで表示されます。' ),
					array( 'head' => '担当店舗', 'body' => 'STEP4で担当店舗を選ぶと、電話・LINE・問い合わせが自動で入ります。' ),
					array( 'head' => '更新（保存）', 'body' => '右上の「更新」を押して保存します。' ),
				),
			),
		);
	}
}

/* 保存済み手順を全部取得 */
if ( ! function_exists( 'carmelx_manual_get_all' ) ) {
	function carmelx_manual_get_all() {
		$v = get_option( 'carmelx_manuals', array() );
		return is_array( $v ) ? $v : array();
	}
}

/* 1画面ぶんの手順（保存が無ければ既定値） */
if ( ! function_exists( 'carmelx_manual_get' ) ) {
	function carmelx_manual_get( $key ) {
		$all = carmelx_manual_get_all();
		if ( isset( $all[ $key ] ) && is_array( $all[ $key ] ) ) { return $all[ $key ]; }
		$def = carmelx_manual_defaults();
		return isset( $def[ $key ] ) ? $def[ $key ] : array( 'title' => '', 'steps' => array() );
	}
}

/* 現在の管理画面 → 手順キー */
if ( ! function_exists( 'carmelx_manual_current_key' ) ) {
	function carmelx_manual_current_key() {
		if ( ! function_exists( 'get_current_screen' ) ) { return ''; }
		$s = get_current_screen();
		if ( ! $s ) { return ''; }
		$id = $s->id;
		if ( 'edit-portfolio' === $id ) { return 'portfolio_list'; }
		if ( 'portfolio' === $id ) { return 'portfolio_edit'; }
		if ( false !== strpos( $id, 'carmel-shop-checklist' ) ) { return 'shop_checklist'; }
		return '';
	}
}

/* ========== 各画面に手順パネルを埋め込み ========== */
add_action( 'admin_notices', function () {
	$key = carmelx_manual_current_key();
	if ( ! $key ) { return; }
	$m = carmelx_manual_get( $key );
	if ( empty( $m['steps'] ) ) { return; }

	$edit_url = admin_url( 'edit.php?post_type=portfolio&page=carmel-staff-manual&mkey=' . rawurlencode( $key ) );

	echo '<div id="carmel-manual-box" data-mkey="' . esc_attr( $key ) . '" style="margin:14px 0;background:#fff;border:1px solid #cfe3d3;border-left:5px solid #2cac44;border-radius:8px;padding:0;overflow:hidden;">';
	echo '<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#f3faf5;">';
	echo '<span style="font-size:18px;">📖</span>';
	echo '<strong style="font-size:14px;color:#1c7a3a;flex:1;">スタッフ手順：' . esc_html( $m['title'] ? $m['title'] : carmelx_manual_screens()[ $key ] ) . '</strong>';
	if ( current_user_can( 'manage_options' ) ) {
		echo '<a href="' . esc_url( $edit_url ) . '" style="font-size:12px;">手順を編集</a>';
	}
	echo '<button type="button" id="carmel-manual-toggle" class="button button-small">開く</button>';
	echo '</div>';

	echo '<div id="carmel-manual-body" style="padding:6px 18px 14px;">';
	echo '<ol style="margin:8px 0 0;padding-left:22px;line-height:1.8;">';
	foreach ( $m['steps'] as $st ) {
		$h = isset( $st['head'] ) ? $st['head'] : '';
		$b = isset( $st['body'] ) ? $st['body'] : '';
		echo '<li style="margin-bottom:8px;">';
		if ( '' !== $h ) { echo '<strong>' . esc_html( $h ) . '</strong>'; }
		if ( '' !== trim( (string) $b ) ) { echo '<div style="color:#333;">' . nl2br( wp_kses_post( $b ) ) . '</div>'; }
		echo '</li>';
	}
	echo '</ol></div>';
	echo '</div>';

	echo '<script>(function(){var box=document.getElementById("carmel-manual-box");if(!box)return;var btn=document.getElementById("carmel-manual-toggle"),body=document.getElementById("carmel-manual-body");var K="carmel_manual_open_"+box.getAttribute("data-mkey");function set(o){body.style.display=o?"block":"none";btn.textContent=o?"閉じる":"開く";try{localStorage.setItem(K,o?"1":"0");}catch(e){}}var s=null;try{s=localStorage.getItem(K);}catch(e){}set(s===null?true:s==="1");btn.addEventListener("click",function(){set(body.style.display==="none");});})();</script>';
} );

/* ========== 管理画面：手順マニュアル 編集ページ ========== */
add_action( 'admin_menu', function () {
	if ( ! post_type_exists( 'portfolio' ) ) { return; }
	add_submenu_page(
		'edit.php?post_type=portfolio',
		'手順マニュアル',
		'手順マニュアル',
		'manage_options',
		'carmel-staff-manual',
		'carmelx_manual_edit_page'
	);
} );

if ( ! function_exists( 'carmelx_manual_edit_page' ) ) {
	function carmelx_manual_edit_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$screens = carmelx_manual_screens();
		$keys    = array_keys( $screens );
		$key     = isset( $_GET['mkey'] ) ? sanitize_text_field( wp_unslash( $_GET['mkey'] ) ) : $keys[0];
		if ( ! isset( $screens[ $key ] ) ) { $key = $keys[0]; }
		$msg = '';

		// 保存
		if ( ! empty( $_POST['carmel_manual_save'] ) && check_admin_referer( 'carmel_manual', 'carmel_manual_nonce' ) ) {
			$title  = isset( $_POST['manual_title'] ) ? sanitize_text_field( wp_unslash( $_POST['manual_title'] ) ) : '';
			$heads  = isset( $_POST['head'] ) ? (array) $_POST['head'] : array();
			$bodies = isset( $_POST['body'] ) ? (array) $_POST['body'] : array();
			$steps  = array();
			foreach ( $heads as $i => $h ) {
				$h = sanitize_text_field( wp_unslash( $h ) );
				$b = isset( $bodies[ $i ] ) ? wp_kses_post( wp_unslash( $bodies[ $i ] ) ) : '';
				if ( '' === $h && '' === trim( (string) $b ) ) { continue; }
				$steps[] = array( 'head' => $h, 'body' => $b );
			}
			$all         = carmelx_manual_get_all();
			$all[ $key ] = array( 'title' => $title, 'steps' => $steps );
			update_option( 'carmelx_manuals', $all );
			$msg = '保存しました。';
		}

		$m = carmelx_manual_get( $key );

		echo '<div class="wrap"><h1>スタッフ手順マニュアル</h1>';
		echo '<p>各管理画面の上部に表示される「スタッフ手順」を編集できます。画面を選んで、ステップを追加・編集してください。</p>';
		if ( $msg ) { echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>'; }

		// 画面切替タブ
		echo '<h2 class="nav-tab-wrapper">';
		foreach ( $screens as $k => $label ) {
			$url = admin_url( 'edit.php?post_type=portfolio&page=carmel-staff-manual&mkey=' . rawurlencode( $k ) );
			$cls = ( $k === $key ) ? ' nav-tab-active' : '';
			echo '<a class="nav-tab' . $cls . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		echo '</h2>';

		echo '<form method="post" style="max-width:820px;margin-top:16px;">';
		wp_nonce_field( 'carmel_manual', 'carmel_manual_nonce' );

		echo '<table class="form-table"><tr><th><label>マニュアル見出し</label></th><td>';
		echo '<input type="text" name="manual_title" value="' . esc_attr( $m['title'] ) . '" class="regular-text" style="width:100%;max-width:520px;" placeholder="例：在庫の店舗ふりわけ 手順">';
		echo '</td></tr></table>';

		echo '<h3>ステップ</h3>';
		echo '<div id="carmel-steps">';
		$steps = ! empty( $m['steps'] ) ? $m['steps'] : array( array( 'head' => '', 'body' => '' ) );
		foreach ( $steps as $st ) {
			carmelx_manual_row( isset( $st['head'] ) ? $st['head'] : '', isset( $st['body'] ) ? $st['body'] : '' );
		}
		echo '</div>';

		echo '<p><button type="button" class="button" id="carmel-add-step">＋ ステップを追加</button></p>';
		echo '<p style="margin-top:16px;"><button type="submit" name="carmel_manual_save" value="1" class="button button-primary button-hero">保存する</button></p>';
		echo '</form>';

		// 行テンプレート＋追加/削除JS
		echo '<template id="carmel-step-tpl">';
		carmelx_manual_row( '', '' );
		echo '</template>';
		echo '<script>(function(){
			var wrap=document.getElementById("carmel-steps");
			var add=document.getElementById("carmel-add-step");
			var tpl=document.getElementById("carmel-step-tpl");
			function bindDel(sc){sc.querySelectorAll(".carmel-step-del").forEach(function(b){b.onclick=function(){var r=b.closest(".carmel-step");if(r)r.remove();};});}
			bindDel(wrap);
			add.addEventListener("click",function(){var n=tpl.content.cloneNode(true);wrap.appendChild(n);bindDel(wrap);});
		})();</script>';

		echo '</div>';
	}
}

/* ステップ入力行（1行） */
if ( ! function_exists( 'carmelx_manual_row' ) ) {
	function carmelx_manual_row( $head, $body ) {
		echo '<div class="carmel-step" style="border:1px solid #dcdcde;border-radius:6px;padding:12px 14px;margin-bottom:10px;background:#fff;">';
		echo '<p style="margin:0 0 8px;"><input type="text" name="head[]" value="' . esc_attr( $head ) . '" class="regular-text" style="width:100%;max-width:520px;" placeholder="見出し（例：車両を選ぶ）"></p>';
		echo '<p style="margin:0;"><textarea name="body[]" rows="2" style="width:100%;" placeholder="説明（例：割り当てたい車両にチェックを入れます）">' . esc_textarea( $body ) . '</textarea></p>';
		echo '<p style="margin:8px 0 0;text-align:right;"><button type="button" class="button-link carmel-step-del" style="color:#b32d2e;">この行を削除</button></p>';
		echo '</div>';
	}
}
