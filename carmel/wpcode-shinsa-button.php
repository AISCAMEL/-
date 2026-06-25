<?php
/**
 * カーメル：検索結果カードに「審査申込」ボタンを追加（別ページへ遷移）
 * ---------------------------------------------------------------------------
 * 各車カードのボタン列（.result-item__btns）に「審査申込」を1つ追加する。
 *   - 在庫お問い合わせ（forms.gle → ポップアップ）はそのまま
 *   - LINEで相談（lin.ee）はそのまま
 *   - 審査申込 … ポップアップではなく SHINSA_URL の別ページへ遷移
 *
 * 導入 : WPCode → 新規スニペット → コードタイプ「HTML Snippet」
 *        → 挿入方法「サイト全体のフッター(Site Wide Footer)」→ 保存・有効化。
 *        ※ ↓ SHINSA_URL を実際の「審査申込ページ」のURLに変更してください。
 * ---------------------------------------------------------------------------
 */
?>
<script>
(function () {
	// ★審査申込ページのURL（ここを変更してください）
	var SHINSA_URL = 'https://carmelonline.jp/loan_new2/';

	function ready(fn){ if(document.readyState!=='loading'){fn();}else{document.addEventListener('DOMContentLoaded',fn);} }
	ready(function () {
		document.querySelectorAll('.result-item__btns').forEach(function (box) {
			if (box.querySelector('.btn-shinsa')) return;          // 二重追加防止
			var a = document.createElement('a');
			a.className = 'btn-shinsa';
			a.href = SHINSA_URL;
			a.target = '_blank';
			a.rel = 'noopener';
			a.textContent = '審査申込';
			var line = box.querySelector('.btn-line');               // LINEの前に挿入
			if (line) { box.insertBefore(a, line); } else { box.appendChild(a); }
			box.classList.add('btns-3');
		});
	});
})();
</script>
<style>
/* ボタン3つ用にグリッドを調整 */
.result-item__btns.btns-3 { grid-template-columns:1fr 1fr 1fr !important; }
.result-item__btns .btn-shinsa {
	display:block; text-align:center; padding:10px; border-radius:8px;
	font-size:13px; font-weight:700; text-decoration:none;
	background:#e8500a; color:#fff;
}
.result-item__btns .btn-shinsa:hover { opacity:.9; }
@media(max-width:680px){ .result-item__btns.btns-3 { grid-template-columns:1fr !important; } }
</style>
