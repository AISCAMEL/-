<?php
/**
 * 広告LP（LPtools）で、古い表記の画像スライドの代わりに入れる HTML スライド。
 *
 * LP のページはテーマのCSSを読まないことがあるため、スタイルはブロックの中の <style>（.apx-s 配下だけ）に持つ。
 * 差し替えるスライドの定義は apprex_lp_slide_defs()。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** スライド共通のCSS（.apx-s の中だけに効く）。 */
function apprex_lp_slide_css() {
	return '.apx-s{position:relative;overflow:hidden;margin:0;background:radial-gradient(120% 80% at 100% 0%,#2a56c6 0%,rgba(42,86,198,0) 55%),radial-gradient(90% 60% at 0% 100%,rgba(255,107,31,.32) 0%,rgba(255,107,31,0) 60%),linear-gradient(160deg,#0a1a3a 0%,#102a5c 100%);color:#fff;padding:clamp(44px,9vw,76px) clamp(18px,5vw,48px);font-family:"Noto Sans JP","Hiragino Kaku Gothic ProN","Yu Gothic",sans-serif;line-height:1.75;text-align:center;box-sizing:border-box}'
		. '.apx-s *{box-sizing:border-box}'
		. '.apx-s:before{content:"";position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:30px 30px;-webkit-mask-image:linear-gradient(180deg,#000,transparent 85%);mask-image:linear-gradient(180deg,#000,transparent 85%);pointer-events:none}'
		. '.apx-s>*{position:relative}'
		. '.apx-s .apx-lb{display:inline-block;border:1px solid rgba(255,207,110,.6);color:#ffcf6e;border-radius:999px;padding:4px 14px;font-size:12px;font-weight:700;letter-spacing:.12em;margin:0 0 16px}'
		. '.apx-s h2,.apx-s h2.wp-block-heading{color:#fff!important;font-size:clamp(26px,6.6vw,42px)!important;line-height:1.35!important;margin:0 0 14px!important;font-weight:900!important;letter-spacing:.02em;border:0!important;padding:0!important;background:none!important;box-shadow:none!important;border-radius:0!important;text-align:center!important}'
		. '.apx-s h2:before,.apx-s h2:after{display:none}'
		. '.apx-s h2 em{font-style:normal;background:linear-gradient(90deg,#ffd27a,#ff8a3d);-webkit-background-clip:text;background-clip:text;color:transparent}'
		. '.apx-s p{background:none;border:0}'
		. '.apx-s .apx-tx{color:#d4def5;font-size:clamp(14px,3.7vw,16.5px);max-width:34em;margin:0 auto}'
		. '.apx-s .apx-chips{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;margin:18px auto 0;max-width:640px}'
		. '.apx-s .apx-chips span{background:rgba(255,255,255,.1);border:1px solid rgba(255,255,255,.12);border-radius:999px;padding:6px 14px;font-size:13px;font-weight:700}'
		. '.apx-s .apx-chips span b{color:#ffcf6e;margin-right:6px}'
		. '.apx-s .apx-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:24px auto 0;max-width:660px}'
		. '.apx-s .apx-card{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:18px;padding:18px 12px}'
		. '.apx-s .apx-card small{display:block;color:#a9badf;font-size:12.5px;font-weight:700}'
		. '.apx-s .apx-card b{display:block;font-size:clamp(24px,6vw,32px);font-weight:900;color:#fff;line-height:1.2;margin-top:6px;font-family:"Outfit","Noto Sans JP",sans-serif}'
		. '.apx-s .apx-card b span{font-size:.48em;margin-left:2px;font-family:"Noto Sans JP",sans-serif}'
		. '.apx-s .apx-card em{display:block;font-style:normal;color:#c3d0ee;font-size:11.5px;margin-top:6px}'
		. '.apx-s .apx-card.hl{background:linear-gradient(160deg,#ff7a2f,#e5520c);border-color:transparent;box-shadow:0 16px 34px rgba(255,107,31,.38);transform:translateY(-4px)}'
		. '.apx-s .apx-card.hl small,.apx-s .apx-card.hl em{color:#ffe6d3}'
		. '.apx-s .apx-steps{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin:24px auto 0;max-width:700px;text-align:left}'
		. '.apx-s .apx-step{background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:16px;padding:16px 14px}'
		. '.apx-s .apx-step i{font-style:normal;display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:30px;padding:0 10px;border-radius:999px;background:#ff6b1f;color:#fff;font-weight:800;font-size:12.5px;margin-bottom:8px}'
		. '.apx-s .apx-step b{display:block;font-size:15.5px}'
		. '.apx-s .apx-step span{display:block;color:#c3d0ee;font-size:12.5px;line-height:1.6;margin-top:2px}'
		. '.apx-s .apx-cta{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin:28px 0 0}'
		. '.apx-s .apx-cta a{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-width:220px;padding:16px 28px;border-radius:999px;font-weight:800;text-decoration:none;font-size:16px;line-height:1.3}'
		. '.apx-s .apx-cta a.p{background:linear-gradient(180deg,#ff7a2f,#e5520c);color:#fff;box-shadow:0 12px 26px rgba(255,107,31,.42)}'
		. '.apx-s .apx-cta a.p:after{content:"→"}'
		. '.apx-s .apx-cta a.s{background:rgba(255,255,255,.06);color:#fff;border:1.5px solid rgba(255,255,255,.55)}'
		. '.apx-s .apx-note{color:#9fb2dd;font-size:11.5px;margin:16px auto 0;max-width:38em}'
		. '.apx-s .apx-dev{position:relative;width:min(240px,64%);margin:30px auto 0}'
		. '.apx-s .apx-push{position:absolute;z-index:2;left:-20%;top:-14px;width:76%;background:#fff;color:#0e1830;border-radius:14px;padding:9px 11px;font-size:11px;text-align:left;box-shadow:0 14px 30px rgba(0,0,0,.35);line-height:1.45}'
		. '.apx-s .apx-push b{display:block;font-size:10px;color:#ff6b1f}'
		. '.apx-s .apx-phone{border-radius:34px;background:#0d0f14;padding:9px;box-shadow:0 30px 60px rgba(0,0,0,.45);transform:rotate(3deg)}'
		. '.apx-s .apx-scr{border-radius:26px;overflow:hidden;background:#f3f5fb;color:#0e1830;text-align:left;min-height:330px}'
		. '.apx-s .apx-scr .t{background:linear-gradient(160deg,#ff8a3d,#ff5e62);color:#fff;padding:26px 14px 16px;font-weight:800;font-size:14px}'
		. '.apx-s .apx-scr .t.m{background:linear-gradient(160deg,#3b6cff,#6a3bff)}'
		. '.apx-s .apx-scr .t small{display:block;font-size:10px;opacity:.85;font-weight:700}'
		. '.apx-s .apx-scr .g{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;padding:10px}'
		. '.apx-s .apx-scr .g span{background:#fff;border-radius:10px;padding:12px 2px;font-size:10px;font-weight:700;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.06)}'
		. '.apx-s .apx-scr .g span:before{content:"";display:block;width:22px;height:22px;border-radius:7px;margin:0 auto 5px;background:linear-gradient(135deg,#ffb36b,#ff6b1f)}'
		. '.apx-s .apx-scr .c{margin:2px 10px 10px;background:#fff;border-radius:12px;padding:10px;font-size:11px;font-weight:700;border-left:4px solid #ff6b1f}'
		. '.apx-s .apx-scr .c small{display:block;color:#8a95ad;font-weight:500;font-size:10px}'
		. '.apx-s .apx-scr .pf{margin:10px;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.08)}'
		. '.apx-s .apx-scr .pf i{display:block;height:140px;background:linear-gradient(160deg,#c7d6ff,#8aa8ff)}'
		. '.apx-s .apx-scr .pf div{padding:10px;font-size:11px;font-weight:700}'
		. '.apx-s .apx-scr .pf div small{display:block;color:#8a95ad;font-weight:500}'
		. '.apx-s .apx-scr .lk{display:flex;justify-content:center;gap:22px;padding:4px 0 12px}'
		. '.apx-s .apx-scr .lk span{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff;box-shadow:0 4px 10px rgba(0,0,0,.1);font-size:16px;color:#8a95ad}'
		. '.apx-s .apx-scr .lk span.h{background:#ff4d6d;color:#fff}'
		. '.apx-s .apx-tbl{width:100%;max-width:660px;margin:22px auto 0;border-collapse:separate;border-spacing:0;font-size:13.5px;background:#fff;color:#0e1830;border-radius:16px;overflow:hidden;box-shadow:0 20px 40px rgba(0,0,0,.3)}'
		. '.apx-s .apx-tbl th,.apx-s .apx-tbl td{padding:12px 10px;border:0;border-top:1px solid #e3e8f2;text-align:center;vertical-align:middle}'
		. '.apx-s .apx-tbl thead th{border-top:0;background:#eef2fb;font-size:12.5px;color:#4a5672}'
		. '.apx-s .apx-tbl thead th.a{background:#ff6b1f;color:#fff}'
		. '.apx-s .apx-tbl td:first-child{text-align:left;font-weight:700;color:#0b1d3f;white-space:nowrap}'
		. '.apx-s .apx-tbl td.a{background:#fff6ef;font-weight:800;color:#c2410c}'
		. '@media(max-width:480px){.apx-s .apx-cta a{width:100%}.apx-s .apx-push{left:-10%}.apx-s .apx-card{padding:14px 6px;border-radius:14px}.apx-s .apx-card small{font-size:11px}.apx-s .apx-card b{font-size:21px}.apx-s .apx-card em{font-size:10px}}';
}

/**
 * スライド1枚（wp:html ブロック）。
 *
 * @param array $a label / title / text / chips / cards / steps / table / note / cta / device。
 * @return string
 */
function apprex_lp_slide_html( $a ) {
	$e = 'esc_html';
	$h = '<section class="apx-s">';
	$h .= '<p class="apx-lb">' . $e( $a['label'] ) . '</p>';
	$h .= '<h2>' . wp_kses( $a['title'], array( 'br' => array(), 'em' => array() ) ) . '</h2>';
	if ( ! empty( $a['text'] ) ) {
		$h .= '<p class="apx-tx">' . $e( $a['text'] ) . '</p>';
	}
	if ( ! empty( $a['cards'] ) ) {
		$h .= '<div class="apx-cards">';
		foreach ( $a['cards'] as $c ) {
			$h .= '<div class="apx-card' . ( ! empty( $c[3] ) ? ' hl' : '' ) . '"><small>' . $e( $c[0] ) . '</small><b>' . $e( $c[1] ) . '<span>' . $e( $c[2] ) . '</span></b>' . ( ! empty( $c[4] ) ? '<em>' . $e( $c[4] ) . '</em>' : '' ) . '</div>';
		}
		$h .= '</div>';
	}
	if ( ! empty( $a['steps'] ) ) {
		$h .= '<div class="apx-steps">';
		foreach ( $a['steps'] as $s ) {
			$h .= '<div class="apx-step"><i>' . $e( $s[0] ) . '</i><b>' . $e( $s[1] ) . '</b><span>' . $e( $s[2] ) . '</span></div>';
		}
		$h .= '</div>';
	}
	if ( ! empty( $a['table'] ) ) {
		$h .= '<table class="apx-tbl"><thead><tr><th></th><th>一般的なスクラッチ開発会社</th><th class="a">APPREX</th></tr></thead><tbody>';
		foreach ( $a['table'] as $r ) {
			$h .= '<tr><td>' . $e( $r[0] ) . '</td><td>' . $e( $r[1] ) . '</td><td class="a">' . $e( $r[2] ) . '</td></tr>';
		}
		$h .= '</tbody></table>';
	}
	if ( ! empty( $a['chips'] ) ) {
		$h .= '<p class="apx-chips">';
		foreach ( $a['chips'] as $c ) {
			$h .= '<span><b>✓</b>' . $e( $c ) . '</span>';
		}
		$h .= '</p>';
	}
	if ( ! empty( $a['device'] ) ) {
		$h .= '<div class="apx-dev" aria-hidden="true">';
		if ( 'matching' === $a['device'] ) {
			$h .= '<div class="apx-push"><b>マッチングしました</b>佐藤さんとメッセージできます</div><div class="apx-phone"><div class="apx-scr"><div class="t m"><small>Skill Match</small>あなたへのおすすめ</div><div class="pf"><i></i><div>佐藤 さん ／ Webデザイナー<small>経験7年・即日対応・★4.9</small></div></div><div class="lk"><span>✕</span><span class="h">♥</span></div></div></div>';
		} else {
			$h .= '<div class="apx-push"><b>あなたのお店</b>本日限定のクーポンが届きました</div><div class="apx-phone"><div class="apx-scr"><div class="t"><small>WELCOME</small>ご来店ごとにポイントが貯まる</div><div class="g"><span>予約</span><span>メニュー</span><span>クーポン</span><span>スタンプ</span><span>会員証</span><span>お知らせ</span></div><div class="c">ドリンク1杯無料<small>アプリ会員限定クーポン</small></div></div></div>';
		}
		$h .= '</div>';
	}
	if ( ! empty( $a['note'] ) ) {
		$h .= '<p class="apx-note">' . $e( $a['note'] ) . '</p>';
	}
	if ( ! empty( $a['cta'] ) ) {
		$h .= '<p class="apx-cta">';
		foreach ( $a['cta'] as $i => $c ) {
			$h .= '<a class="' . ( 0 === $i ? 'p' : 's' ) . '" href="' . esc_url( home_url( $c[1] ) ) . '">' . $e( $c[0] ) . '</a>';
		}
		$h .= '</p>';
	}
	$h .= '</section>';
	return "<!-- wp:html -->\n<style>" . apprex_lp_slide_css() . "</style>\n" . $h . "\n<!-- /wp:html -->";
}

/**
 * 差し替えるスライド：LPのスラッグ => [ 画像のファイル名 => [ 前の版の見出しラベル, スライドの中身 ] ]。
 *
 * @return array
 */
function apprex_lp_slide_defs() {
	$cta  = array( array( '無料で相談する', '/meeting/' ), array( '資料を請求する', '/document/' ) );
	$ctam = array( array( '無料で相談する', '/meeting/' ), array( '概算見積りをもらう', '/contact/' ) );
	return array(
		'app-development' => array(
			'ai-lp-slide-jPSLSHYI' => array( 'APPREX ／ 自社アプリ', array( 'label' => 'LINE集客の次の一手', 'title' => 'LINE集客の次は、<br><em>自社アプリ。</em>', 'text' => 'プッシュ通知で常連さんにまっすぐ届く、あなたのお店だけのアプリ。プログラミング不要、最短2週間で公開できます。', 'chips' => array( '予約', '会員証', 'EC', 'クーポン', 'スタンプカード', 'プッシュ通知 無制限' ), 'device' => 'shop', 'cta' => $cta ) ),
			'ai-lp-slide-yYKb6xFf' => array( 'Solution 解決策 3', array( 'label' => 'Solution 03', 'title' => '最短<em>2週間</em>で公開。<br>制作も申請もおまかせ。', 'text' => 'デザイン・仕様書づくりからストア申請まで、APPREXが行います。プログラミングの知識は必要ありません。', 'steps' => array( array( '1', '無料相談', 'ご要望をオンラインで伺います' ), array( '2', 'デザイン・仕様書', 'アプリの画面と仕様を無料で作成' ), array( '3', '制作・ストア申請', 'APPREXが制作して申請まで' ), array( '4', '公開', '最短2週間でお客様のスマホへ' ) ) ) ),
			'ai-lp-slide-trJyo8fW' => array( 'Offer ご提案', array( 'label' => 'Offer', 'title' => 'まずは無料で、<br>あなたのアプリの<em>形</em>を。', 'cards' => array( array( 'デザイン・仕様書', '0', '円', false, 'AIで作成・何度でも修正' ), array( '初期費用', 'お見積り', '', false, 'AIスタジオから作るとお得' ), array( '月額', '19,800', '円〜', true, 'プッシュ通知は無制限' ) ), 'chips' => array( '30日間の無料体験', 'クレジットカード登録不要', '予約・会員・EC・クーポンを標準搭載' ), 'note' => '税抜。初期費用・アプリストア登録費はお見積りでご案内します。', 'cta' => $cta ) ),
			'ai-lp-slide-MqZ2nn5W' => array( 'Schedule 公開までの目安', array( 'label' => 'Schedule', 'title' => '次の販促に、<br><em>間に合わせるなら今。</em>', 'text' => '季節のキャンペーンや新メニューの告知にアプリを使いたい方は、早めのご相談がおすすめです。', 'steps' => array( array( '今日', '無料相談', 'やりたいことを伺います' ), array( '1週目', 'デザインを確定', '画面と機能を決めます' ), array( '2週目', '制作・ストア申請', 'APPREXが進めます' ), array( '最短2週間', '公開・配信開始', 'プッシュ通知で集客' ) ) ) ),
			'ai-lp-slide-DTAs3gMm' => array( 'Action', array( 'label' => 'Next Action', 'title' => 'LINEだけに頼る集客から、<br><em>育てる自社アプリへ。</em>', 'text' => 'まずは無料相談で、あなたのお店に合うアプリの使い方を一緒に確認しましょう。', 'chips' => array( '相談は無料', 'オンラインで完結', '全国対応' ), 'cta' => $cta ) ),
		),
		'matching-app'    => array(
			'ai-lp-slide-XCVMEQ2B' => array( 'APPREX ／ マッチングアプリ開発', array( 'label' => 'Matching App Development', 'title' => 'マッチングアプリを、<br><em>3ヶ月〜</em>で。', 'text' => 'Flutterによるスクラッチ開発で、iOS・Android対応のマッチングアプリを企画から運用まで支援します。', 'chips' => array( 'iOS・Android同時開発', '管理画面つき', 'AWS無料提供', '運用・保守まで伴走' ), 'device' => 'matching', 'cta' => $ctam ) ),
			'ai-lp-slide-zLTAd2Mf' => array( 'Solution 解決策 2', array( 'label' => 'Solution 02', 'title' => 'Flutterで、<br><em>iOS・Android</em>を同時に開発', 'text' => 'iPhone向けとAndroid向けを1つのコードで効率よく開発します。', 'cards' => array( array( '対応OS', 'iOS', '・Android', false ), array( '開発期間の目安', '3ヶ月', '〜', true ), array( '開発費', 'お見積り', '', false ) ) ) ),
			'ai-lp-slide-Zsqaudlu' => array( 'Solution 解決策 3', array( 'label' => 'Solution 03', 'title' => 'AWSを無料提供。<br><em>運用・保守まで</em>安心。', 'text' => 'サーバはAWSを無料で提供します。公開後の運用・保守も、内容に合わせたプランでご相談いただけます。決済はiOS・Androidのアプリ内課金、またはSquareなどの外部決済に対応します。', 'chips' => array( 'AWS無料提供', '運用・保守まで伴走', 'アプリ内課金・外部決済' ) ) ),
			'ai-lp-slide-aSLygU2R' => array( '比較表 2', array( 'label' => 'Comparison 02', 'title' => '公開した<em>あと</em>まで、<br>比べてください。', 'table' => array( array( '運用・保守', '別契約になる場合がある', '保守プランで公開後も伴走' ), array( 'サーバ費用', '別途かかりやすい', 'AWSを無料提供' ), array( '課金・決済', '仕様により追加相談', 'アプリ内課金・外部決済に対応' ), array( 'カスタマイズ', '自由度は高い', 'スクラッチ開発で自由度が高い' ) ) ) ),
			'ai-lp-slide-JFjVg1K5' => array( '比較表 1', array( 'label' => 'Comparison', 'title' => '一般的な開発会社と<br><em>APPREXの比較</em>', 'table' => array( array( '初期費用の目安', '高額になりやすい', 'APPREXの基盤で抑えて個別見積り' ), array( '両OS対応', '別々に作る場合がある', 'Flutterで両OS' ), array( '納期', '数ヶ月以上かかることも', '3ヶ月〜が目安' ) ) ) ),
			'ai-lp-slide-RYRLUEzM' => array( 'Action', array( 'label' => 'Next Action', 'title' => 'アイデアを、<br><em>事業になるアプリ</em>へ。', 'text' => '新規事業・アプリの刷新を、APPREXが企画から開発・保守まで伴走します。まずは無料相談で、アイデアを形にする方法を整理しましょう。', 'cards' => array( array( '開発費', 'お見積り', '', false ), array( '月額保守・サーバ', 'お見積り', '', false ), array( '制作期間', '3ヶ月', '〜', true ) ), 'note' => '最低利用期間1年契約（税抜）。', 'cta' => array( array( '無料相談・見積りを依頼する', '/meeting/' ) ) ) ),
		),
	);
}
