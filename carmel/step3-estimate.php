<?php
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
	/* 「見積もり明細」ACFボックスを非表示（入力はDOMに残るので保存はされる） */
	#acf-group_carmel_estimate { display:none !important; }
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

			// 見積もりに数字が入っているか（全項目0なら未入力とみなす）
			var hasInput = false;
			IN.forEach(function(k){ if (n(k) > 0) hasInput = true; });

			// 見積もり明細ACF（est_*）へ ※未入力なら触らない（既存値を消さない）
			if (hasInput) {
				IN.concat(OUT).forEach(function(k){
					setAcf('est_'+k, document.getElementById('cs_est_'+k) ? n(k) : '');
				});
				setAcf('est_shouhizei', shouhizei);
				setAcf('est_total', total);
				setAcf('est_getsugaku', getsugaku);
			}

			// 既存フィールドへミラー ※0/空のときは上書きしない（手入力の月額等を保護）
			if (getsugaku > 0) { setAcf('total', yen(getsugaku) + '円'); }      // 月々のお支払い
			var feesTotal = taxFees + nonTax + shouhizei;
			if (feesTotal > 0) { setAcf('keihi', yen(feesTotal) + '円'); }       // 諸経費合計
			if (n('recycle') > 0) { setAcf('recicle', yen(n('recycle')) + '円'); } // リサイクル料
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
