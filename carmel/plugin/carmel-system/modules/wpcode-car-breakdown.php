<?php
/**
 * カーメル：お支払総額の内訳（本体＋諸費用の明細）
 * ---------------------------------------------------------------------------
 * 各車の見積メタ（est_*）を読み、総額の内訳を開閉表示。ローン枠の直後に差し込む。
 *   車両本体価格 / 税金・保険料 / 登録・諸費用 / 消費税 → お支払総額(est_total)
 * 値が入っている項目だけ表示。est_total が無ければ何も出さない。
 * 値はサーバー側(post_meta)で読むため正確。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'carmelx_bd_yen' ) ) {
	function carmelx_bd_yen( $pid, $key ) {
		$v = get_post_meta( $pid, $key, true );
		if ( $v === '' || $v === null ) { return null; }
		$n = (int) preg_replace( '/[^0-9\-]/', '', (string) $v );
		return $n;
	}
}

if ( ! function_exists( 'carmelx_breakdown_render' ) ) {
	function carmelx_breakdown_render() {
		if ( is_admin() || ! is_singular( 'portfolio' ) ) { return; }
		$pid   = get_the_ID();
		$total = carmelx_bd_yen( $pid, 'est_total' );
		$hon   = carmelx_bd_yen( $pid, 'est_honntai' );
		if ( ! $total || $total <= 0 ) { return; }

		$groups = array(
			'車両本体価格' => array(
				array( 'est_honntai', '車両本体価格' ),
				array( 'est_nebiki', '値引き', true ),   // マイナス
				array( 'est_shitadori', '下取り', true ), // マイナス
			),
			'税金・保険料' => array(
				array( 'est_jidoshazei', '自動車税' ),
				array( 'est_jibaiseki', '自賠責保険料' ),
				array( 'est_juuryouzei', '重量税' ),
				array( 'est_inshi', '印紙代' ),
				array( 'est_recycle', 'リサイクル預託金' ),
			),
			'登録・諸費用' => array(
				array( 'est_touroku_daiko', '登録代行費用' ),
				array( 'est_shako', '車庫証明費用' ),
				array( 'est_nousha', '納車費用' ),
				array( 'est_kensa_daiko', '検査代行費用' ),
				array( 'est_seibi', '整備費用' ),
				array( 'est_hoshou', '保証費用' ),
				array( 'est_shouhizei', '消費税' ),
			),
		);

		// 行HTMLを組み立て（値のある行のみ）
		$rows_html = '';
		$shown_sum = 0;
		foreach ( $groups as $gname => $items ) {
			$g = '';
			foreach ( $items as $it ) {
				$val   = carmelx_bd_yen( $pid, $it[0] );
				$minus = isset( $it[2] ) && $it[2];
				if ( $val === null || $val === 0 ) { continue; }
				$disp = $minus ? ( '−' . number_format( abs( $val ) ) ) : number_format( $val );
				$shown_sum += $minus ? -abs( $val ) : $val;
				$g .= '<tr><th>' . esc_html( $it[1] ) . '</th><td>' . $disp . '<small>円</small></td></tr>';
			}
			if ( $g !== '' ) {
				$rows_html .= '<tr class="cmx-bd__gh"><th colspan="2">' . esc_html( $gname ) . '</th></tr>' . $g;
			}
		}

		// 内訳に出ていない差額（端数/未分類）を「その他諸費用」として調整
		$diff = $total - $shown_sum;
		if ( $diff !== 0 && abs( $diff ) >= 100 ) {
			$rows_html .= '<tr><th>その他諸費用</th><td>' . ( $diff < 0 ? '−' : '' ) . number_format( abs( $diff ) ) . '<small>円</small></td></tr>';
		}

		$shohi  = ( $hon && $total ) ? max( 0, $total - $hon ) : null;
		?>
<style id="carmel-bd-css">
.cmx-bd{border:1px solid #d7e4dc;border-radius:12px;background:#fff;margin:14px 0 4px;overflow:hidden;}
.cmx-bd>summary{list-style:none;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:10px;
	padding:13px 16px;font-size:13.5px;font-weight:800;color:#1c7a3a;background:#f3faf5;}
.cmx-bd>summary::-webkit-details-marker{display:none;}
.cmx-bd>summary .cmx-bd__t{display:flex;align-items:center;gap:8px;}
.cmx-bd>summary svg{width:16px;height:16px;fill:#2cac44;transition:.2s;}
.cmx-bd[open]>summary svg{transform:rotate(180deg);}
.cmx-bd>summary .cmx-bd__sum{font-size:12px;color:#667;}
.cmx-bd>summary .cmx-bd__sum b{color:#17331f;font-size:14px;}
.cmx-bd__wrap{padding:6px 14px 14px;}
.cmx-bd__note{font-size:11.5px;color:#8a929c;margin:2px 0 10px;line-height:1.5;}
.cmx-bd table{width:100%;border-collapse:collapse;}
.cmx-bd th,.cmx-bd td{padding:9px 6px;font-size:13px;border-bottom:1px solid #eef1f3;text-align:left;vertical-align:middle;}
.cmx-bd td{text-align:right;font-weight:700;color:#222;white-space:nowrap;}
.cmx-bd td small{font-size:10px;color:#999;font-weight:700;margin-left:2px;}
.cmx-bd th{color:#444;font-weight:600;}
.cmx-bd__gh th{background:#f6f8f7;color:#1c7a3a;font-weight:800;font-size:12px;letter-spacing:.02em;border-bottom:1px solid #e3eae6;}
.cmx-bd__tot th,.cmx-bd__tot td{border-top:2px solid #2cac44;border-bottom:none;padding-top:12px;font-size:15px;}
.cmx-bd__tot th{color:#17331f;font-weight:900;}
.cmx-bd__tot td{color:#1c7a3a;font-weight:900;font-size:17px;}
.cmx-bd__sub th,.cmx-bd__sub td{border-bottom:none;color:#667;font-weight:700;padding-top:3px;}
</style>
<div id="cmx-bd-src" style="display:none;">
	<details class="cmx-bd">
		<summary>
			<span class="cmx-bd__t"><svg viewBox="0 0 24 24"><path d="M7 10l5 5 5-5z"/></svg>お支払総額の内訳を見る</span>
			<span class="cmx-bd__sum">総額 <b><?php echo number_format( $total ); ?>円</b></span>
		</summary>
		<div class="cmx-bd__wrap">
			<p class="cmx-bd__note">表示価格に含まれる費用の内訳です（管轄地域・登録条件により実費が前後する場合があります）。</p>
			<table>
				<?php echo $rows_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>
				<?php if ( $shohi !== null ) : ?>
				<tr class="cmx-bd__sub"><th>諸費用 合計</th><td><?php echo number_format( $shohi ); ?><small>円</small></td></tr>
				<?php endif; ?>
				<tr class="cmx-bd__tot"><th>お支払総額</th><td><?php echo number_format( $total ); ?><small>円</small></td></tr>
			</table>
		</div>
	</details>
</div>
<script id="carmel-bd-js">
(function(){
	function run(){
		var src=document.getElementById('cmx-bd-src'); if(!src) return; var node=src.firstElementChild||src.querySelector('.cmx-bd'); if(!node) return;
		var lg=document.querySelector('.carmel-lg');
		if(lg&&lg.parentNode){ lg.parentNode.insertBefore(node, lg.nextSibling); }
		else { var sec=document.querySelector('.single-portfolio .vc_section, article .vc_section'); if(sec&&sec.parentNode){ sec.parentNode.insertBefore(node, sec.nextSibling); } else { return; } }
		src.remove();
	}
	if(document.readyState==='loading') document.addEventListener('DOMContentLoaded',function(){ setTimeout(run,110); });
	else setTimeout(run,110);
})();
</script>
		<?php
	}
	add_action( 'wp_footer', 'carmelx_breakdown_render', 21 );
}
