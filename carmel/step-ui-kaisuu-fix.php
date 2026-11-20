<?php
/**
 * カーメル在庫：STEP UI「支払回数」セレクトの並び・選択肢を修正
 * ---------------------------------------------------------------------------
 * 症状 : 支払回数プルダウンの並びがバラバラ（60→72→84→48→36→24→12）で、
 *        120回までの選択肢が無い。
 * 対応 : #carmel_step_ui 内の「◯回」だけで構成された <select> を自動検出し、
 *        12〜120回（12刻み）の昇順に作り直す。現在の選択値は維持する。
 *        ＝既存STEP UIのHTMLを書き換えずJSだけで整える（id非依存）。
 *
 * 導入 : WPCode の PHP Snippet（Run Everywhere）。統合版にも内包済み。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_kaisuu_fix' );
add_action( 'admin_footer-post-new.php', 'carmel_kaisuu_fix' );

function carmel_kaisuu_fix() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}
	?>
	<script>
	(function ($) {
		'use strict';

		// 昇順・12刻み・120回まで
		var COUNTS = [12, 24, 36, 48, 60, 72, 84, 96, 108, 120];

		// 「◯回」だけで構成された select か判定（支払回数セレクトの検出）
		function isKaisuuSelect($sel) {
			var $opts = $sel.find('option');
			if ($opts.length < 2) return false;
			var kai = 0, other = 0;
			$opts.each(function () {
				var t = $.trim($(this).text());
				if (/^\d+\s*回$/.test(t)) kai++;
				else if (t !== '') other++; // 空（プレースホルダ）は許容
			});
			return kai >= 2 && other === 0;
		}

		function rebuild($sel) {
			var $first = $sel.find('option').first();
			var valHasKai = /回/.test(String($first.val())); // value が "60回" 形式か "60" 形式か
			var cur = String($sel.val() || '').replace(/[^0-9]/g, ''); // 現在値（数字）

			$sel.empty();
			COUNTS.forEach(function (n) {
				var val = valHasKai ? (n + '回') : String(n);
				$sel.append($('<option>').val(val).text(n + '回'));
			});

			// 選択値を復元（無ければ 60回）
			var want = cur || '60';
			var target = valHasKai ? (want + '回') : want;
			if (!$sel.find('option[value="' + target + '"]').length) {
				target = valHasKai ? '60回' : '60';
			}
			$sel.val(target).trigger('change');
		}

		$(function () {
			var ui = document.getElementById('carmel_step_ui');
			if (!ui) return;
			$(ui).find('select').each(function () {
				var $sel = $(this);
				if (isKaisuuSelect($sel)) rebuild($sel);
			});
		});

	})(jQuery);
	</script>
	<?php
}
