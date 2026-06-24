<?php
/**
 * カーメル在庫 STEP UI 修正一式（テスト用・統合スニペット）
 * ---------------------------------------------------------------------------
 *  STEP1-2 : 基本情報＋装備＋タイトルを ACF へ反映  (carmel_step_ui_acf_bridge)
 *  STEP3   : 見積もり明細パネル＋自動計算          (carmel_step3_estimate)
 *  STEP4   : 担当店舗→店舗情報を ACF へ反映        (carmel_step4_shop_bridge)
 *
 *  導入: WPCode →「+ スニペットを追加」→ コードタイプ「PHP Snippet」
 *        挿入位置「自動挿入・どこでも (Run Everywhere)」で本コードを貼り付け、有効化。
 *  注意: 旧スニペット（carmel_step1_autofill / 旧装備JS）は無効化してください。
 *        STEP3 の保存には acf-estimate-fields.json のインポートが必要です。
 * ---------------------------------------------------------------------------
 */


/* ===================== step-ui-acf-bridge.php ===================== */

/**
 * カーメル在庫：STEP UI ↔ ACF ブリッジ（統合版）
 * ---------------------------------------------------------------------------
 * 対象 : carmelonline.jp / カスタム投稿タイプ portfolio（在庫）の編集画面
 * 役割 : 「車両入力 STEP UI」(#carmel_step_ui) の入力を、実際に保存される
 *        ACF フィールドへリアルタイムに反映する。
 *
 *   1) 基本情報（STEP1）  : メーカー/車種/年式/走行/色/排気量/車検/ミッション/駆動
 *   2) タイトル自動生成    : 「信用回復ローン ◯◯ … 管理番号」
 *   3) 装備（STEP2）       : 表示専用チェック → 対応する ACF チェックボックス
 *   4) 既存レコードの初期反映: ACF の状態を STEP UI 側へ戻す（装備・基本情報）
 *
 * これまで別々だった2スニペット
 *   - carmel_step1_autofill（基本情報＋タイトル：PHP）
 *   - step-ui-equip-bridge.js（装備：JSスニペット）
 * を1本に統合。ヘルパー（setAcf/tickAcf）もトリガーも一本化している。
 *
 * 導入（WPCode）:
 *   コードタイプ「PHP Snippet」/ 挿入位置「自動挿入・どこでも(Run Everywhere)」
 *   で本ファイルの中身を貼り付けて有効化。
 *   旧スニペット2本（上記）は無効化してよい。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_step_ui_acf_bridge' );
add_action( 'admin_footer-post-new.php', 'carmel_step_ui_acf_bridge' );

function carmel_step_ui_acf_bridge() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}

	global $post;
	$kanri = ( $post && $post->ID ) ? get_post_meta( $post->ID, 'kanri_bango', true ) : '';
	?>
	<script>
	(function ($) {
		'use strict';

		var KANRI = <?php echo wp_json_encode( $kanri ); ?>;

		/* STEP1 入力ID → ACF data-name（テキスト/セレクト系） */
		var BASIC_MAP = {
			cs_maker:        'marker',
			cs_year:         'year',
			cs_mileage:      'mileage',
			cs_color:        'color',
			cs_displacement: 'displacement',
			cs_inspection:   'inspection',
			cs_mission:      'mission',
			cs_kudou:        'kudou'
			// 'type'（型式）は 車種＋グレード を結合して別途セット
		};

		/* STEP2 装備値 → ACF data-name（1択チェックボックス）
		   ※ ACF 側に対応のある装備のみ。未対応（Apple CarPlay / Android Auto /
		     レーンアシスト / ドラレコ / 革シート / パノラマルーフ / LEDヘッドライト
		     等）は意図的に未マッピング。 */
		var EQUIP_MAP = {
			'フルセグTV': 'nav5',
			'DVD再生': 'dvd',
			'Bluetooth': 'bluetooth',
			'自動ブレーキ': 'shoutotu',
			'クルーズコントロール': 'controll',
			'アダプティブクルーズ': 'controll',
			'バックカメラ': 'kamera3',
			'360度カメラ': 'kamera4',
			'コーナーセンサー': 'sensar',
			'シートヒーター': 'heater',
			'電動シート': 'seat',
			'サンルーフ': 'sunroof',
			'パワーバックドア': 'gate',
			'電動スライドドア': 'door',
			'スマートキー': 'smartkey',
			'アルミホイール': 'almi',
			'純正アルミ': 'almi',
			'ローダウン': 'down',
			'エアロパーツ': 'earo',
			'ETC': 'etc',
			'ETC2.0': 'etc'
		};

		/* data-name → 装備値（逆引き：初期反映用。最初に一致したものを採用） */
		var EQUIP_REVERSE = (function () {
			var r = {};
			Object.keys( EQUIP_MAP ).forEach( function ( label ) {
				var name = EQUIP_MAP[ label ];
				if ( ! ( name in r ) ) { r[ name ] = label; }
			} );
			return r;
		})();

		/* ------------------------------------------------------------------ */
		/* ヘルパー                                                            */
		/* ------------------------------------------------------------------ */

		function v( id ) {
			var el = document.getElementById( id );
			return el ? ( el.value || '' ).trim() : '';
		}

		/* テキスト/セレクト系 ACF へ値をセット（空は ACF 側も空に揃える） */
		function setAcf( fieldName, value ) {
			var $field = $( '.acf-field[data-name="' + fieldName + '"]' );
			if ( ! $field.length ) { return; }

			var $input = $field.find( 'input[type="text"], input[type="number"], input[type="url"], textarea' ).first();
			if ( $input.length ) {
				if ( $input.val() === value ) { return; }
				$input.val( value ).trigger( 'input' ).trigger( 'change' );
				return;
			}

			var $select = $field.find( 'select' ).first();
			if ( $select.length ) {
				if ( $select.val() === value ) { return; }
				$select.val( value ).trigger( 'change' );
				if ( $select.hasClass( 'select2-hidden-accessible' ) ) {
					$select.trigger( 'change.select2' );
				}
			}
		}

		/* チェックボックス系 ACF をオン/オフ */
		function tickAcf( dataName, on ) {
			var $field = $( '.acf-field[data-name="' + dataName + '"]' );
			if ( ! $field.length ) { return; }
			var $cb = $field.find( 'input[type="checkbox"]' ).first();
			if ( ! $cb.length || $cb.prop( 'checked' ) === on ) { return; }
			$cb.prop( 'checked', on );
			$cb.closest( 'label' ).toggleClass( 'selected', on );
			$cb.trigger( 'change' );
		}

		/* ------------------------------------------------------------------ */
		/* 同期処理                                                            */
		/* ------------------------------------------------------------------ */

		/* 基本情報（STEP1）→ ACF */
		function syncBasic() {
			Object.keys( BASIC_MAP ).forEach( function ( id ) {
				setAcf( BASIC_MAP[ id ], v( id ) );
			} );
			// 型式 = 車種＋グレード
			var model = v( 'cs_car_model' );
			var grade = v( 'cs_grade' );
			setAcf( 'type', grade ? ( model + ' ' + grade ) : model );
		}

		/* タイトル自動生成 */
		function buildTitle() {
			var parts = [ '信用回復ローン' ];
			if ( v( 'cs_maker' ) )     { parts.push( v( 'cs_maker' ) ); }
			if ( v( 'cs_car_model' ) ) { parts.push( v( 'cs_car_model' ) ); }
			if ( v( 'cs_grade' ) )     { parts.push( v( 'cs_grade' ) ); }
			if ( v( 'cs_year' ) )      { parts.push( v( 'cs_year' ) ); }
			if ( v( 'cs_color' ) )     { parts.push( v( 'cs_color' ) ); }
			if ( v( 'cs_mileage' ) ) {
				var n = parseInt( v( 'cs_mileage' ).replace( /[^0-9]/g, '' ), 10 );
				parts.push( isNaN( n ) ? v( 'cs_mileage' ) : n.toLocaleString() + 'km' );
			}
			if ( KANRI ) { parts.push( KANRI ); }
			return parts.join( ' ' );
		}

		function setTitle() {
			var title = buildTitle();
			if ( ! title || title === '信用回復ローン' ) { return; }
			var input = document.getElementById( 'title' );
			if ( ! input ) { return; }
			input.value = title;
			input.dispatchEvent( new Event( 'input',  { bubbles: true } ) );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			var ph = document.getElementById( 'title-prompt-text' );
			if ( ph ) { ph.style.display = 'none'; }
		}

		/* 装備（STEP2）→ ACF（全件） */
		function syncEquip() {
			$( '.cs-equip-check' ).each( function () {
				var name = EQUIP_MAP[ $.trim( $( this ).val() ) ];
				if ( name ) { tickAcf( name, $( this ).is( ':checked' ) ); }
			} );
		}

		/* まとめて同期（基本＋装備＋タイトル） */
		function syncAll() {
			syncBasic();
			syncEquip();
			setTitle();
		}

		/* ------------------------------------------------------------------ */
		/* 既存レコードの初期反映（ACF → STEP UI）                              */
		/* ------------------------------------------------------------------ */

		function prefillEquipFromAcf() {
			Object.keys( EQUIP_REVERSE ).forEach( function ( dataName ) {
				var $field = $( '.acf-field[data-name="' + dataName + '"]' );
				if ( ! $field.length ) { return; }
				if ( ! $field.find( 'input[type="checkbox"]' ).first().is( ':checked' ) ) { return; }
				var label = EQUIP_REVERSE[ dataName ];
				$( '.cs-equip-check' ).filter( function () {
					return $.trim( $( this ).val() ) === label;
				} ).prop( 'checked', true );
			} );
		}

		/* ------------------------------------------------------------------ */
		/* 初期化・イベント                                                    */
		/* ------------------------------------------------------------------ */

		$( function () {
			if ( ! document.getElementById( 'carmel_step_ui' ) ) { return; }

			// 既存装備を STEP2 に戻す
			prefillEquipFromAcf();

			// STEP1 → STEP2 ボタン（管理番号の注意喚起つき）
			document.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '[onclick*="csStep(2)"]' );
				if ( ! btn ) { return; }
				if ( ! KANRI ) {
					alert(
						'先に「下書き保存」を押して管理番号を発番してから「次へ」を押すと、' +
						'タイトルに管理番号が入ります。\n' +
						'（このまま進めると管理番号なしでタイトルが入ります）'
					);
				}
				setTimeout( function () {
					syncBasic();
					setTitle();
				}, 80 );
			}, true );

			// 装備チェック変更で即同期
			$( document ).on( 'change', '.cs-equip-check', syncEquip );

			// ステップ移動（次へ/戻る/ナビ）で取りこぼし防止のため全同期
			$( document ).on( 'click', '.cs-nav-btn, .cs-btn-next, .cs-btn-back', function () {
				setTimeout( syncAll, 60 );
			} );

			// 全リセットで装備 ACF もオフ
			$( document ).on( 'click', '#cs-reset-all-btn', function () {
				setTimeout( function () {
					Object.keys( EQUIP_REVERSE ).forEach( function ( dataName ) {
						tickAcf( dataName, false );
					} );
				}, 80 );
			} );

			// 保存直前に最終同期（STEP2以降で直した値の取りこぼし防止）
			$( '#post' ).on( 'submit', syncAll );
		} );

	})( jQuery );
	</script>
	<?php
}


/* ===================== step3-estimate.php ===================== */

/**
 * カーメル在庫：STEP3「見積もり明細」パネル（自動計算 → ACF反映）
 * ---------------------------------------------------------------------------
 * 対象 : carmelonline.jp / portfolio（在庫）編集画面の「車両入力 STEP UI」
 * 役割 : STEP3 に詳細な見積もり入力パネルを表示し、金額を入れると
 *          ・消費税（課税対象×10%）
 *          ・支払総額
 *          ・月々支払額（実質年率対応／0なら均等割り）
 *        をリアルタイム計算して、見積もり明細ACF（acf-estimate-fields.json で
 *        作成した est_* フィールド）へ書き込む。あわせて既存フィールド
 *          total（月々のお支払い） ← 月々支払額
 *          keihi（諸経費）         ← 諸費用合計（課税諸費用＋非課税＋消費税）
 *          recicle（リサイクル料） ← リサイクル預託金
 *        も更新する。
 *
 * 前提 : acf-estimate-fields.json を ACF にインポート済み（est_* フィールド）。
 *
 * 計算 :
 *   課税対象   = max(0, 本体 − 値引き) ＋ 課税諸費用合計
 *   消費税     = round(課税対象 × 10%)
 *   非課税合計 = 自動車税＋自賠責＋重量税＋印紙＋リサイクル
 *   支払総額   = 課税対象 ＋ 消費税 ＋ 非課税合計 − 下取り
 *   ローン元金 = max(0, 支払総額 − 頭金)
 *   月々       = 年率>0 → 元利均等(月利=年率/12, n=回数) / 年率=0 → ceil(元金/回数)
 *
 * 導入（WPCode）:
 *   コードタイプ「PHP Snippet」/ 挿入位置「自動挿入・どこでも(Run Everywhere)」。
 *   STEP3パネルの差し込み位置を指定したい場合は、STEP3のHTML内に
 *     <div id="cs-est-mount"></div>
 *   を置く。無ければ #carmel_step_ui の末尾に自動追加する。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_step3_estimate' );
add_action( 'admin_footer-post-new.php', 'carmel_step3_estimate' );

function carmel_step3_estimate() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}
	?>
	<style>
	#cs-est { margin-top:12px; border:1px solid #d9dee5; border-radius:8px; background:#fff; }
	#cs-est .cs-est-h { padding:10px 14px; background:#1f2d3d; color:#fff; border-radius:8px 8px 0 0; font-weight:700; }
	#cs-est .cs-est-grp { padding:6px 14px 2px; font-weight:700; color:#1f2d3d; border-top:1px solid #eef1f4; margin-top:4px; }
	#cs-est .cs-est-row { display:flex; align-items:center; gap:8px; padding:4px 14px; }
	#cs-est .cs-est-row label { flex:1 1 auto; font-size:13px; color:#333; }
	#cs-est .cs-est-row .cs-est-in { width:140px; text-align:right; padding:5px 8px; border:1px solid #c5ccd4; border-radius:5px; }
	#cs-est .cs-est-row .yen { width:1.5em; color:#666; font-size:12px; }
	#cs-est .cs-est-calc input { background:#f2f7ff; font-weight:700; border-color:#9cbdf0; }
	#cs-est .cs-est-total { padding:10px 14px; display:flex; justify-content:space-between; align-items:center;
		background:#fff7e6; border-top:2px solid #f0c36d; border-radius:0 0 8px 8px; }
	#cs-est .cs-est-total b { font-size:20px; color:#c0392b; }
	#cs-est .cs-est-note { padding:2px 14px 10px; font-size:11px; color:#888; }
	</style>
	<script>
	(function ($) {
		'use strict';

		// 入力項目（cs_est_<key> → ACF data-name est_<key>）
		var IN = [
			'honntai','nebiki','shitadori',
			'jidoshazei','jibaiseki','juuryouzei','inshi','recycle',
			'touroku_daiko','shako','nousha','kensa_daiko','seibi','hoshou',
			'atamakin','kaisuu','nenritsu'
		];
		// 自動計算（出力）項目
		var OUT = ['shouhizei','total','getsugaku'];

		var TAXABLE_FEES = ['touroku_daiko','shako','nousha','kensa_daiko','seibi','hoshou'];
		var NONTAX_FEES  = ['jidoshazei','jibaiseki','juuryouzei','inshi','recycle'];

		function yen(n){ return (isFinite(n)?Math.round(n):0).toLocaleString(); }

		function n(id){
			var el = document.getElementById('cs_est_'+id);
			if (!el) return 0;
			var v = parseFloat(String(el.value).replace(/[^0-9.\-]/g,''));
			return isNaN(v) ? 0 : v;
		}

		// テキスト/数値ACFへ反映（step-ui-acf-bridge.php と同じ流儀）
		function setAcf(dataName, value){
			var $f = $('.acf-field[data-name="'+dataName+'"]');
			if (!$f.length) return;
			var $i = $f.find('input[type="text"], input[type="number"], textarea').first();
			if (!$i.length) return;
			var s = (value===''||value===null||value===undefined) ? '' : String(value);
			if ($i.val() === s) return;
			$i.val(s).trigger('input').trigger('change');
		}

		function calc(){
			var honntai=n('honntai'), nebiki=n('nebiki'), shitadori=n('shitadori');
			var taxFees=0; TAXABLE_FEES.forEach(function(k){ taxFees+=n(k); });
			var nonTax=0;  NONTAX_FEES.forEach(function(k){ nonTax+=n(k); });

			var taxableBase = Math.max(0, honntai - nebiki) + taxFees;
			var shouhizei   = Math.round(taxableBase * 0.10);
			var total       = taxableBase + shouhizei + nonTax - shitadori;
			if (total < 0) total = 0;

			var principal = Math.max(0, total - n('atamakin'));
			var kaisuu    = Math.max(0, Math.floor(n('kaisuu')));
			var nenritsu  = n('nenritsu');
			var getsugaku = 0;
			if (kaisuu > 0){
				if (nenritsu > 0){
					var r = (nenritsu/100)/12;
					getsugaku = principal * r / (1 - Math.pow(1+r, -kaisuu));
				} else {
					getsugaku = principal / kaisuu;
				}
				getsugaku = Math.ceil(getsugaku);
			}

			// 出力欄へ反映
			setOut('shouhizei', shouhizei);
			setOut('total', total);
			setOut('getsugaku', getsugaku);

			// 合計表示
			$('#cs-est-total-val').text(yen(total));
			$('#cs-est-month-val').text(yen(getsugaku));

			// 見積もり明細ACF（est_*）へ
			IN.concat(OUT).forEach(function(k){
				setAcf('est_'+k, document.getElementById('cs_est_'+k) ? n(k) : '');
			});
			// 計算結果も est_* へ（n() は出力欄も読む）
			setAcf('est_shouhizei', shouhizei);
			setAcf('est_total', total);
			setAcf('est_getsugaku', getsugaku);

			// 既存フィールドへミラー
			setAcf('total',   yen(getsugaku) + '円');                 // 月々のお支払い
			setAcf('keihi',   yen(taxFees + nonTax + shouhizei) + '円'); // 諸経費合計
			setAcf('recicle', n('recycle') ? yen(n('recycle')) + '円' : '');
		}

		function setOut(id, val){
			var el = document.getElementById('cs_est_'+id);
			if (el && el.value !== String(val)) el.value = val;
		}

		// 既存レコード：ACF(est_*) の値をパネルへ初期反映
		function prefill(){
			IN.concat(OUT).forEach(function(k){
				var $f = $('.acf-field[data-name="est_'+k+'"]');
				if (!$f.length) return;
				var v = $f.find('input').first().val();
				var el = document.getElementById('cs_est_'+k);
				if (el && v !== '' && v != null) el.value = v;
			});
		}

		function row(id, label, append){
			return '<div class="cs-est-row">'+
				'<label for="cs_est_'+id+'">'+label+'</label>'+
				'<input type="number" inputmode="numeric" class="cs-est-in" id="cs_est_'+id+'">'+
				'<span class="yen">'+(append||'円')+'</span></div>';
		}
		function calcRow(id, label){
			return '<div class="cs-est-row cs-est-calc">'+
				'<label for="cs_est_'+id+'">'+label+'</label>'+
				'<input type="number" class="cs-est-in" id="cs_est_'+id+'" readonly>'+
				'<span class="yen">円</span></div>';
		}

		function html(){
			return '<div id="cs-est">'+
				'<div class="cs-est-h">📝 見積もり明細</div>'+

				'<div class="cs-est-grp">車両</div>'+
				row('honntai','車両本体価格')+
				row('nebiki','値引き')+
				row('shitadori','下取り価格')+

				'<div class="cs-est-grp">法定費用（非課税）</div>'+
				row('jidoshazei','自動車税（環境性能割・種別割）')+
				row('jibaiseki','自賠責保険料')+
				row('juuryouzei','自動車重量税')+
				row('inshi','登録時印紙代')+
				row('recycle','リサイクル預託金')+

				'<div class="cs-est-grp">諸費用（課税）</div>'+
				row('touroku_daiko','登録代行費用')+
				row('shako','車庫証明代行')+
				row('nousha','納車費用')+
				row('kensa_daiko','検査登録手続代行')+
				row('seibi','点検整備費用')+
				row('hoshou','保証料')+

				'<div class="cs-est-grp">計算結果（自動）</div>'+
				calcRow('shouhizei','消費税（課税対象×10%）')+
				calcRow('total','支払総額')+

				'<div class="cs-est-grp">ローン</div>'+
				row('atamakin','頭金')+
				row('kaisuu','支払回数','回')+
				row('nenritsu','実質年率（0で金利なし）','%')+
				calcRow('getsugaku','月々支払額')+

				'<div class="cs-est-total">'+
					'<span>月々 <b id="cs-est-month-val">0</b> 円</span>'+
					'<span>支払総額 <b id="cs-est-total-val">0</b> 円</span>'+
				'</div>'+
				'<div class="cs-est-note">※ 支払総額・消費税・月々支払額は自動計算です。'+
				'各金額を保存すると「見積もり明細」ACFと、月々＝total / 諸経費＝keihi / リサイクル料＝recicle に反映されます。</div>'+
			'</div>';
		}

		$(function(){
			var ui = document.getElementById('carmel_step_ui');
			if (!ui) return;

			var $mount = $('#cs-est-mount');
			if ($mount.length) $mount.html(html());
			else $(ui).append(html());

			prefill();
			calc();

			$(document).on('input change', '#cs-est input', calc);
			// 保存直前にも再計算（取りこぼし防止）
			$('#post').on('submit', calc);
		});

	})(jQuery);
	</script>
	<?php
}


/* ===================== step4-shop-bridge.php ===================== */

/**
 * カーメル在庫：STEP4「担当店舗」→ 店舗情報を在庫ACFへ自動反映
 * ---------------------------------------------------------------------------
 * 対象 : carmelonline.jp / portfolio（在庫）編集画面の「車両入力 STEP UI」STEP4(販売情報)
 * 症状 : STEP4 で担当店舗を選んでも、下の店舗系フィールド
 *          shop（販売店） / tel（電話番号） / line-link（LINEリンク） /
 *          contact-link（問い合わせリンク）
 *        に反映されず、通常の「更新」で保存されない。
 *
 * 対応 : 公開中の shop 投稿（店舗）の情報をサーバ側でまとめてJSへ出力し、
 *        担当店舗セレクトを変更した瞬間／「店舗情報取得」クリック時に、
 *        対応する在庫ACFへ反映する。ACFの実フィールドへ書くので、
 *        通常の「更新」でそのまま保存される。
 *
 * 担当店舗セレクトの検出:
 *   id/class に依存せず、#carmel_step_ui 内で
 *   「店舗を選択」プレースホルダを持つ <select>、または
 *   option の value/表示が shop 投稿と一致する <select> を自動検出。
 *
 * 導入（WPCode）:
 *   コードタイプ「PHP Snippet」/ 挿入位置「自動挿入・どこでも(Run Everywhere)」。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_step4_shop_bridge' );
add_action( 'admin_footer-post-new.php', 'carmel_step4_shop_bridge' );

function carmel_step4_shop_bridge() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}

	// 全店舗（shop投稿）の情報を取得
	$shops = get_posts( array(
		'post_type'   => 'shop',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'menu_order title',
		'order'       => 'ASC',
	) );

	$has_acf = function_exists( 'get_field' );
	$map = array();
	foreach ( $shops as $s ) {
		$get = function ( $key ) use ( $s, $has_acf ) {
			$v = $has_acf ? get_field( $key, $s->ID ) : get_post_meta( $s->ID, $key, true );
			return is_string( $v ) ? trim( $v ) : ( $v ? $v : '' );
		};
		$map[ (string) $s->ID ] = array(
			'name'    => $get( 'name' ) ?: $s->post_title,
			'tel'     => $get( 'tel' ),
			'line'    => $get( 'line_link' ),
			'contact' => $get( 'contact-link' ),
		);
	}
	?>
	<script>
	(function ($) {
		'use strict';

		var SHOPS = <?php echo wp_json_encode( $map ); ?>;

		// 店舗名 → 情報（表示名で引く逆引き）
		var SHOPS_BY_NAME = {};
		Object.keys( SHOPS ).forEach( function ( id ) {
			var nm = ( SHOPS[ id ].name || '' ).trim();
			if ( nm ) { SHOPS_BY_NAME[ nm ] = SHOPS[ id ]; }
		} );

		// テキスト/URL/textarea 系 ACF へ反映
		function setAcfText( dataName, value ) {
			var $f = $( '.acf-field[data-name="' + dataName + '"]' );
			if ( ! $f.length ) { return; }
			var $i = $f.find( 'input[type="text"], input[type="url"], input[type="tel"], textarea' ).first();
			if ( ! $i.length ) { return; }
			var s = ( value == null ) ? '' : String( value );
			if ( $i.val() === s ) { return; }
			$i.val( s ).trigger( 'input' ).trigger( 'change' );
		}

		// セレクト系 ACF（販売店 shop）へ反映：value 一致 → 無ければ表示名一致
		function setAcfSelect( dataName, optValue, optName ) {
			$( '.acf-field[data-name="' + dataName + '"]' ).each( function () {
				var $sel = $( this ).find( 'select' ).first();
				if ( ! $sel.length ) { return; }

				var matched = null;
				$sel.find( 'option' ).each( function () {
					var $o = $( this );
					if ( optValue && String( $o.val() ) === String( optValue ) ) { matched = $o.val(); return false; }
					if ( optName && $.trim( $o.text() ) === $.trim( optName ) ) { matched = $o.val(); }
				} );
				if ( matched === null ) { return; }
				if ( $sel.val() === matched ) { return; }
				$sel.val( matched ).trigger( 'change' );
				if ( $sel.hasClass( 'select2-hidden-accessible' ) ) {
					$sel.trigger( 'change.select2' );
				}
			} );
		}

		// 担当店舗の選択 → 在庫ACFへ一括反映
		function applyShop( val, text ) {
			var shop = SHOPS[ String( val ) ];
			if ( ! shop && text ) { shop = SHOPS_BY_NAME[ $.trim( text ) ]; }
			if ( ! shop ) { return; }

			setAcfSelect( 'shop', val, shop.name );   // 販売店
			setAcfText( 'tel', shop.tel );            // 電話番号
			setAcfText( 'line-link', shop.line );     // LINEリンク
			setAcfText( 'contact-link', shop.contact ); // 問い合わせリンク
		}

		// 担当店舗セレクトを検出（id非依存）
		function findShopSelect( ui ) {
			var $all = $( ui ).find( 'select' );

			// 1) 「店舗を選択」プレースホルダを持つ select
			var $byPlaceholder = $all.filter( function () {
				return $( this ).find( 'option' ).filter( function () {
					return /店舗を選択/.test( $( this ).text() );
				} ).length > 0;
			} );
			if ( $byPlaceholder.length ) { return $byPlaceholder.first(); }

			// 2) option の value/表示が shop 投稿と一致する select
			var ids = Object.keys( SHOPS );
			var names = ids.map( function ( id ) { return ( SHOPS[ id ].name || '' ).trim(); } );
			var $byMatch = $all.filter( function () {
				return $( this ).find( 'option' ).filter( function () {
					var v = String( $( this ).val() );
					var t = $.trim( $( this ).text() );
					return ids.indexOf( v ) !== -1 || names.indexOf( t ) !== -1;
				} ).length > 0;
			} );
			return $byMatch.first();
		}

		$( function () {
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ! ui || ! Object.keys( SHOPS ).length ) { return; }

			var $sel = findShopSelect( ui );
			if ( ! $sel.length ) { return; }

			function run() {
				var val = $sel.val();
				var text = $sel.find( 'option:selected' ).text();
				applyShop( val, text );
			}

			// 担当店舗を選んだ瞬間に反映
			$sel.on( 'change', run );

			// 「店舗情報取得」ボタンでも反映（id非依存・テキストで検出）
			$( ui ).find( 'button, a, input[type="button"]' ).filter( function () {
				return /店舗情報取得|店舗情報を取得|取得/.test( $( this ).text() || $( this ).val() || '' );
			} ).on( 'click', function () {
				setTimeout( run, 30 );
			} );

			// 既に選択済みなら初期反映（空の項目だけ埋める想定でも上書きはしない）
			if ( $sel.val() ) {
				// 既存レコードの手動編集を尊重し、ここでは自動上書きしない。
				// 必要なら次の1行を有効化して初期反映:
				// run();
			}
		} );

	})( jQuery );
	</script>
	<?php
}

