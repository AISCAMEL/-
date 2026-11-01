<?php
/**
 * カーメル：車両詳細ページ UI 整理
 * ---------------------------------------------------------------------------
 * - 「LINEで在庫確認はこちら」ボタンを非表示
 * - 「このクルマに関するお問い合わせ」セクションを非表示
 *   （スティッキーバー carmel-cta-bar + ローン概算ボックスCTAで代替済み）
 * - 空白ブロック非表示
 * - 保証／法定点検／法定整備／寒冷地仕様／状態・付属品／保証内容 を
 *   アイコンバッジ化してローン概算ボックス内（.carmel-lg__warranty-slots）に表示
 *   ※ 以前は JS でページ内テキストを走査していたため重複やノイズ
 *     （「保証内容 なし」等）が出ていた。ここではサーバー側で post_meta を
 *     直接読み、重複除去して確実に生成する。
 *
 * 導入 : WPCode → 新規 PHP Snippet → Run Everywhere → 有効化
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 詳細ページのバッジ用HTMLをサーバー側で組み立てる。
 * post_meta を直接読むので、テンプレの [cf_value name="..."] と同じ値を確実に取得。
 */
if ( ! function_exists( 'carmelx_detail_badges_html' ) ) {
	function carmelx_detail_badges_html( $post_id ) {
		if ( ! $post_id ) { return ''; }

		$ic = array(
			// 保証（盾）
			'shield' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#2cac44"><path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4zm-1 14l-4-4 1.41-1.41L11 12.17l6.59-6.58L19 7l-8 8z"/></svg>',
			// 点検（チェック）
			'check'  => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#2cac44"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>',
			// 法定整備（レンチ）
			'wrench' => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#2cac44"><path d="M22.7 19l-9.1-9.1c.9-2.3.4-5-1.5-6.9-2-2-5-2.4-7.4-1.3L9 6 6 9 1.6 4.7C.4 7.1.9 10.1 2.9 12.1c1.9 1.9 4.6 2.4 6.9 1.5l9.1 9.1c.4.4 1 .4 1.4 0l2.3-2.3c.5-.4.5-1.1.1-1.4z"/></svg>',
			// 寒冷地仕様（雪の結晶）
			'snow'   => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#2cac44"><path d="M22 11h-4.17l3.24-3.24-1.41-1.42L15 11h-2V9l4.66-4.66-1.42-1.41L13 6.17V2h-2v4.17L7.76 2.93 6.34 4.34 11 9v2H9L4.34 6.34 2.93 7.76 6.17 11H2v2h4.17l-3.24 3.24 1.41 1.42L9 13h2v2l-4.66 4.66 1.42 1.41L11 17.83V22h2v-4.17l3.24 3.24 1.42-1.41L13 15v-2h2l4.66 4.66 1.41-1.42L17.83 13H22z"/></svg>',
			// 状態・付属品（クリップボード）
			'clip'   => '<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="#2cac44"><path d="M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm-2 14l-4-4 1.41-1.41L10 14.17l6.59-6.59L18 9l-8 8z"/></svg>',
		);

		// key => [アイコン, ラベル接頭辞, ラベル省略キーワード]
		// 値にキーワードが含まれていれば値をそのまま、無ければ「接頭辞：値」で表示。
		$rows = array(
			array( 'hoshou2',   'shield', '保証',     '保証' ), // 例: 1年保証付き
			array( 'tenken',    'check',  '法定点検', '点検' ), // 例: 車検整備付き
			array( 'seibi',     'wrench', '法定整備', '整備' ),
			array( 'kanreichi', 'snow',   '寒冷地',   '寒冷' ),
			array( 'joutai',    'clip',   '状態',     '状態' ),
			array( 'hoshou',    'shield', '保証',     '保証' ), // 保証内容（hoshou2 と重複時は除外）
		);

		// 空扱い（バッジを出さない）値
		$skip = array( '', '—', '−', '－', '-', 'なし', '無', '無し', '×', '未', '無効', 'none', '—（空）', '（空）' );

		$badges = array();
		$seen   = array();
		foreach ( $rows as $r ) {
			list( $key, $icon, $prefix, $kw ) = $r;
			$v = get_post_meta( $post_id, $key, true );
			if ( is_array( $v ) ) { $v = implode( '・', array_filter( $v ) ); }
			$v = trim( (string) $v );
			if ( $v === '' || in_array( $v, $skip, true ) ) { continue; }

			$text = ( mb_strpos( $v, $kw ) === false ) ? ( $prefix . '：' . $v ) : $v;
			if ( isset( $seen[ $text ] ) ) { continue; }
			$seen[ $text ] = 1;

			$badges[] = '<span class="carmel-lg__warranty-badge">' . $ic[ $icon ] . ' ' . esc_html( $text ) . '</span>';
		}

		return implode( '', $badges );
	}
}

add_action( 'wp_footer', function () {
	if ( ! is_singular( 'portfolio' ) ) { return; }

	$badges = carmelx_detail_badges_html( get_the_ID() );
	$badges_js = wp_json_encode( $badges, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	echo '<script>' .
		'document.addEventListener("DOMContentLoaded",function(){' .

			/* LINEで在庫確認ボタンを非表示 */
			'document.querySelectorAll("a").forEach(function(a){' .
				'if(a.textContent.trim().indexOf("LINEで在庫確認")!==-1){' .
					'a.style.setProperty("display","none","important");' .
				'}' .
			'});' .

			/* このクルマに関するお問い合わせ セクションを非表示 */
			'document.querySelectorAll("*").forEach(function(el){' .
				'var t=el.textContent.trim();' .
				'if(t==="このクルマに関するお問い合わせ"){' .
					'var w=el.parentElement;' .
					'while(w&&w.tagName!=="BODY"&&w.tagName!=="MAIN"&&w.tagName!=="ARTICLE"){' .
						'var bw=parseFloat(window.getComputedStyle(w).borderTopWidth)||0;' .
						'if(bw>1){break;}' .
						'w=w.parentElement;' .
					'}' .
					'if(w&&w.tagName!=="BODY"&&w.tagName!=="MAIN"&&w.tagName!=="ARTICLE"){' .
						'w.style.setProperty("display","none","important");' .
					'}' .
				'}' .
			'});' .

			/* 「あなたに合うクルマの売買をサポートします」バナーを非表示 */
			'document.querySelectorAll("*").forEach(function(el){' .
				'if(el.textContent.trim().indexOf("売買をサポートします")===-1){return;}' .
				'if(el.children.length>2){return;}' .
				'var w=el.parentElement;' .
				'while(w&&w.tagName!=="BODY"&&w.tagName!=="MAIN"&&w.tagName!=="ARTICLE"){' .
					'var tag=w.tagName;var cls=typeof w.className==="string"?w.className:"";' .
					'if(tag==="SECTION"||cls.indexOf("elementor-section")!==-1||cls.indexOf("wp-block")!==-1){break;}' .
					'w=w.parentElement;' .
				'}' .
				'if(w&&w.tagName!=="BODY"&&w.tagName!=="MAIN"&&w.tagName!=="ARTICLE"){' .
					'w.style.setProperty("display","none","important");' .
				'}' .
			'});' .

			/* 空の枠（黒枠など）を非表示 */
			'document.querySelectorAll("body.single-portfolio *").forEach(function(el){' .
				'var skip=["BODY","MAIN","ARTICLE","HEADER","FOOTER","SECTION","NAV","SCRIPT","STYLE","NOSCRIPT","TABLE","TR","TD","TH"];' .
				'if(skip.indexOf(el.tagName)!==-1){return;}' .
				'var txt=el.textContent.replace(/[ ​\s]+/g,"");' .
				'if(txt!==""){return;}' .
				'if(el.querySelector("img,video,iframe,svg,canvas,input,select,textarea")){return;}' .
				'var s=window.getComputedStyle(el);' .
				'if(s.display==="none"||s.visibility==="hidden"){return;}' .
				'var bw=parseFloat(s.borderTopWidth)||0;' .
				'if(el.offsetWidth>10&&el.offsetHeight>0&&bw>0){el.style.setProperty("display","none","important");}' .
			'});' .

			/* 保証・点検・法定整備・寒冷地・状態 をアイコンバッジ化して概算ボックスに表示 */
			'(function(){' .
				'var html=' . $badges_js . ';' .
				'if(!html){return;}' .
				'var slots=document.querySelector(".carmel-lg__warranty-slots");' .
				'if(!slots){return;}' .
				'slots.innerHTML=html;' . /* サーバー側で重複除去済み。既存内容を置換して確実に */
			'})();' .

		'});' .
	'</script>';
} );

add_action( 'wp_head', function () {
	if ( ! is_singular( 'portfolio' ) ) { return; }
	echo '<style>' .
		'.carmel-lg{margin:10px 0 6px !important;}' .
		'.wp-block-group:empty,.elementor-widget-container:empty{display:none !important;}' .
		/* 空の購入プラン枠（vc_custom_carmelplan）を直接非表示 */
		'.vc_custom_carmelplan{display:none !important;}' .
	'</style>';
} );
