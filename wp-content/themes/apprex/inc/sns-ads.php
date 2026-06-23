<?php
/**
 * SNS広告連携（計測タグ／ピクセル ＋ コンバージョン計測 ＋ Meta Conversions API）。
 *
 * 対応プラットフォーム:
 *   - Meta（Facebook / Instagram）ピクセル ＋ Conversions API（サーバー送信）
 *   - X（旧Twitter）ピクセル
 *   - TikTok ピクセル
 *   - LINE Tag（LINE広告）
 *   - Google 広告（gtag / コンバージョンラベル）
 *
 * 計測の流れ:
 *   1. 全ページで PageView を自動計測（各SNSの基本タグを <head> に出力）。
 *   2. お問い合わせ等のフォーム送信完了で「リード（Lead）」を計測（クライアント側）。
 *   3. Meta は Conversions API でサーバー側からも Lead を送信（計測精度UP・重複排除付き）。
 *
 * 設定: 設定 → APPREX SNS広告連携。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 設定
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX SNS広告連携', 'APPREX SNS広告', 'manage_options', 'apprex-sns-ads', 'apprex_sns_ads_settings_page' );
} );

add_action( 'admin_init', function () {
	$fields = array(
		'apprex_px_meta'        => 'sanitize_text_field', // Meta Pixel ID（数字）。
		'apprex_px_meta_token'  => 'sanitize_text_field', // Meta CAPI アクセストークン。
		'apprex_px_meta_test'   => 'sanitize_text_field', // CAPI テストイベントコード（任意）。
		'apprex_px_x'           => 'sanitize_text_field', // X(Twitter) Pixel ID。
		'apprex_px_x_event'     => 'sanitize_text_field', // X コンバージョンイベントID（任意）。
		'apprex_px_tiktok'      => 'sanitize_text_field', // TikTok Pixel ID。
		'apprex_px_line'        => 'sanitize_text_field', // LINE Tag ID。
		'apprex_px_gads'        => 'sanitize_text_field', // Google 広告 ID（AW-XXXXXXXXX）。
		'apprex_px_gads_label'  => 'sanitize_text_field', // Google 広告 コンバージョンラベル。
		'apprex_px_enabled'     => 'absint',              // 全体ON/OFF。
	);
	foreach ( $fields as $opt => $cb ) {
		register_setting( 'apprex_sns_ads', $opt, array( 'sanitize_callback' => $cb ) );
	}
} );

function apprex_sns_opt( $k, $d = '' ) {
	$v = get_option( $k, '' );
	return '' !== $v ? $v : $d;
}

/** 計測が有効か（全体スイッチ）。プレビュー・管理画面・ログイン編集者では発火させない。 */
function apprex_sns_tracking_on() {
	if ( ! (int) get_option( 'apprex_px_enabled', 0 ) ) {
		return false;
	}
	if ( is_admin() || is_preview() || is_customize_preview() ) {
		return false;
	}
	return true;
}

function apprex_sns_ads_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>APPREX SNS広告連携</h1>
		<p>各SNS広告の「ピクセル（計測タグ）」を設置し、ページ閲覧とフォーム送信（リード）を自動計測します。IDを入れるだけで連携できます。</p>

		<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;margin:0 0 18px;max-width:860px;">
			<strong>各媒体の広告管理画面（出稿・ID取得はこちら）：</strong>
			<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:10px;">
				<a class="button" href="https://www.facebook.com/adsmanager/" target="_blank" rel="noopener">Meta広告 ↗</a>
				<a class="button" href="https://business.facebook.com/events_manager2" target="_blank" rel="noopener">Metaイベントマネージャ ↗</a>
				<a class="button" href="https://ads.twitter.com/" target="_blank" rel="noopener">X（Twitter）広告 ↗</a>
				<a class="button" href="https://ads.tiktok.com/" target="_blank" rel="noopener">TikTok広告 ↗</a>
				<a class="button" href="https://admanager.line.biz/" target="_blank" rel="noopener">LINE広告 ↗</a>
				<a class="button" href="https://ads.google.com/" target="_blank" rel="noopener">Google広告 ↗</a>
			</div>
		</div>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_sns_ads' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th>計測を有効にする</th>
					<td><label><input type="checkbox" name="apprex_px_enabled" value="1" <?php checked( 1, (int) get_option( 'apprex_px_enabled', 0 ) ); ?>> サイト全体で計測タグを出力する</label>
					<p class="description">OFFの間はタグを一切出力しません。設定が済んでからONにしてください。</p></td>
				</tr>

				<tr><th colspan="2">
					<h2 style="margin:.4em 0;">Meta（Facebook / Instagram）
						<a class="button button-small" href="https://business.facebook.com/events_manager2" target="_blank" rel="noopener">イベントマネージャを開く ↗</a>
						<a class="button button-small" href="https://www.facebook.com/adsmanager/" target="_blank" rel="noopener">広告マネージャを開く ↗</a>
					</h2>
				</th></tr>
				<tr>
					<th>Meta ピクセルID</th>
					<td><input type="text" name="apprex_px_meta" class="regular-text" value="<?php echo esc_attr( apprex_sns_opt( 'apprex_px_meta' ) ); ?>" placeholder="例：1234567890123456">
					<p class="description">Meta イベントマネージャ → データソース → ピクセル の「ID」（数字）。</p></td>
				</tr>
				<tr>
					<th>Meta Conversions API トークン</th>
					<td><input type="text" name="apprex_px_meta_token" class="regular-text" value="<?php echo esc_attr( apprex_sns_opt( 'apprex_px_meta_token' ) ); ?>" autocomplete="off" placeholder="EAAB…（任意・サーバー側計測）">
					<p class="description">設定すると、フォーム送信時にサーバーからもMetaへLeadを送信します（広告ブロック等の取りこぼしを補完。ブラウザ計測とは event_id で重複排除）。イベントマネージャ → 設定 → Conversions API → アクセストークンを生成。</p></td>
				</tr>
				<tr>
					<th>Meta テストイベントコード</th>
					<td><input type="text" name="apprex_px_meta_test" class="regular-text" value="<?php echo esc_attr( apprex_sns_opt( 'apprex_px_meta_test' ) ); ?>" placeholder="TEST12345（任意・動作確認時のみ）">
					<p class="description">CAPIの動作確認に使うコード。確認が済んだら空にしてください。</p></td>
				</tr>

				<tr><th colspan="2">
					<h2 style="margin:.4em 0;">X（旧Twitter）
						<a class="button button-small" href="https://ads.twitter.com/" target="_blank" rel="noopener">X広告マネージャを開く ↗</a>
					</h2>
				</th></tr>
				<tr>
					<th>X ピクセルID</th>
					<td><input type="text" name="apprex_px_x" class="regular-text" value="<?php echo esc_attr( apprex_sns_opt( 'apprex_px_x' ) ); ?>" placeholder="例：o1abc">
					<p class="description">X広告 → ツール → イベントマネージャーのタグID。</p></td>
				</tr>
				<tr>
					<th>X コンバージョンイベントID</th>
					<td><input type="text" name="apprex_px_x_event" class="regular-text" value="<?php echo esc_attr( apprex_sns_opt( 'apprex_px_x_event' ) ); ?>" placeholder="例：tw-o1abc-xxxxx（任意）">
					<p class="description">フォーム送信を計測したい場合のイベントID（任意）。</p></td>
				</tr>

				<tr><th colspan="2">
					<h2 style="margin:.4em 0;">TikTok
						<a class="button button-small" href="https://ads.tiktok.com/" target="_blank" rel="noopener">TikTok広告マネージャを開く ↗</a>
					</h2>
				</th></tr>
				<tr>
					<th>TikTok ピクセルID</th>
					<td><input type="text" name="apprex_px_tiktok" class="regular-text" value="<?php echo esc_attr( apprex_sns_opt( 'apprex_px_tiktok' ) ); ?>" placeholder="例：CABC1D2E3F…">
					<p class="description">TikTok広告マネージャー → アセット → イベント のピクセルID。</p></td>
				</tr>

				<tr><th colspan="2">
					<h2 style="margin:.4em 0;">LINE広告
						<a class="button button-small" href="https://admanager.line.biz/" target="_blank" rel="noopener">LINE広告マネージャを開く ↗</a>
					</h2>
				</th></tr>
				<tr>
					<th>LINE Tag ID</th>
					<td><input type="text" name="apprex_px_line" class="regular-text" value="<?php echo esc_attr( apprex_sns_opt( 'apprex_px_line' ) ); ?>" placeholder="例：00000000-0000-0000-0000-000000000000">
					<p class="description">LINE広告マネージャー → トラッキング（LINE Tag）のタグID。</p></td>
				</tr>

				<tr><th colspan="2">
					<h2 style="margin:.4em 0;">Google 広告
						<a class="button button-small" href="https://ads.google.com/" target="_blank" rel="noopener">Google広告を開く ↗</a>
					</h2>
				</th></tr>
				<tr>
					<th>Google 広告 ID</th>
					<td><input type="text" name="apprex_px_gads" class="regular-text" value="<?php echo esc_attr( apprex_sns_opt( 'apprex_px_gads' ) ); ?>" placeholder="AW-XXXXXXXXX">
					<p class="description">Google広告 → 設定 → Googleタグ の「AW-」から始まるID。</p></td>
				</tr>
				<tr>
					<th>Google 広告 コンバージョンラベル</th>
					<td><input type="text" name="apprex_px_gads_label" class="regular-text" value="<?php echo esc_attr( apprex_sns_opt( 'apprex_px_gads_label' ) ); ?>" placeholder="例：AbC-D_efGhIjKlMnOp">
					<p class="description">コンバージョンアクションの「ラベル」。フォーム送信時に計測します（任意）。</p></td>
				</tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>

		<hr>
		<h2>補足</h2>
		<ul style="list-style:disc;margin-left:1.4em;max-width:760px;">
			<li><strong>SNS広告ネットワーク枠（AdSense / Audience Network 等）</strong>は「設定 → APPREX 広告枠」で、種別を「広告タグ（コード）」にして貼り付けると、サイト内の各位置に表示できます。</li>
			<li><strong>SNSへの自動“出稿”（有料広告の自動入稿）</strong>は、各SNSの広告APIが審査・課金スコープを要するため本テーマでは行いません。記事・バナーのSNS自動投稿（オーガニック）は「APPREX SNS自動投稿」をご利用ください。</li>
		</ul>
	</div>
	<?php
}

/* =========================================================================
 * 基本タグ（PageView）— <head> 出力
 * ====================================================================== */
add_action( 'wp_head', function () {
	if ( ! apprex_sns_tracking_on() ) {
		return;
	}
	$meta   = apprex_sns_opt( 'apprex_px_meta' );
	$x      = apprex_sns_opt( 'apprex_px_x' );
	$tiktok = apprex_sns_opt( 'apprex_px_tiktok' );
	$line   = apprex_sns_opt( 'apprex_px_line' );
	$gads   = apprex_sns_opt( 'apprex_px_gads' );

	// --- Meta Pixel ---
	if ( $meta ) {
		?>
<!-- APPREX: Meta Pixel -->
<script>
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');
fbq('init', <?php echo wp_json_encode( $meta ); ?>);
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=<?php echo esc_attr( rawurlencode( $meta ) ); ?>&ev=PageView&noscript=1"/></noscript>
		<?php
	}

	// --- X (Twitter) Pixel ---
	if ( $x ) {
		?>
<!-- APPREX: X Pixel -->
<script>
!function(e,t,n,s,u,a){e.twq||(s=e.twq=function(){s.exe?s.exe.apply(s,arguments):s.queue.push(arguments)},s.version='1.1',s.queue=[],u=t.createElement(n),u.async=!0,u.src='https://static.ads-twitter.com/uwt.js',a=t.getElementsByTagName(n)[0],a.parentNode.insertBefore(u,a))}(window,document,'script');
twq('config', <?php echo wp_json_encode( $x ); ?>);
</script>
		<?php
	}

	// --- TikTok Pixel ---
	if ( $tiktok ) {
		?>
<!-- APPREX: TikTok Pixel -->
<script>
!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"];ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e};ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=d.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(<?php echo wp_json_encode( $tiktok ); ?>);ttq.page();}(window,document,'ttq');
</script>
		<?php
	}

	// --- LINE Tag ---
	if ( $line ) {
		?>
<!-- APPREX: LINE Tag -->
<script>
(function(g,d,o){g._ltq=g._ltq||[];g._lt=g._lt||function(){g._ltq.push(arguments)};var h=location.protocol==='https:'?'https://d.line-scdn.net':'http://d.line-scdn.net',s=d.createElement('script');s.async=1;s.src=h+'/n/line_tag/public/release/v1/lt.js';var t=d.getElementsByTagName('script')[0];t.parentNode.insertBefore(s,t)})(window,document);
_lt('init',{customerType:'lap',tagId:<?php echo wp_json_encode( $line ); ?>});
_lt('send','pv',[<?php echo wp_json_encode( $line ); ?>]);
</script>
<noscript><img height="1" width="1" style="display:none" src="https://tr.line.me/tag.gif?c_t=lap&t_id=<?php echo esc_attr( rawurlencode( $line ) ); ?>&e=pv&noscript=1"/></noscript>
		<?php
	}

	// --- Google 広告（gtag） ---
	if ( $gads ) {
		?>
<!-- APPREX: Google Ads -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr( $gads ); ?>"></script>
<script>
window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config', <?php echo wp_json_encode( $gads ); ?>);
</script>
		<?php
	}
}, 5 );

/* =========================================================================
 * コンバージョン計測（フォーム送信完了で発火）— フロント
 * ====================================================================== */
add_action( 'wp_footer', function () {
	if ( ! apprex_sns_tracking_on() ) {
		return;
	}
	$has = apprex_sns_opt( 'apprex_px_meta' ) || apprex_sns_opt( 'apprex_px_x' )
		|| apprex_sns_opt( 'apprex_px_tiktok' ) || apprex_sns_opt( 'apprex_px_line' )
		|| apprex_sns_opt( 'apprex_px_gads' );
	if ( ! $has ) {
		return;
	}
	$conf = array(
		'x_event'    => apprex_sns_opt( 'apprex_px_x_event' ),
		'line'       => apprex_sns_opt( 'apprex_px_line' ),
		'gads'       => apprex_sns_opt( 'apprex_px_gads' ),
		'gads_label' => apprex_sns_opt( 'apprex_px_gads_label' ),
	);
	?>
<!-- APPREX: SNS広告コンバージョン計測 -->
<script>
(function(){
	var C = <?php echo wp_json_encode( $conf ); ?>;
	document.addEventListener('apprex:lead', function(e){
		var d = (e && e.detail) || {};
		var eid = d.event_id || ('lead_' + Date.now());
		try { if (window.fbq) fbq('track', 'Lead', {}, { eventID: eid }); } catch(x){}
		try { if (window.twq && C.x_event) twq('event', C.x_event, {}); } catch(x){}
		try { if (window.ttq) ttq.track('SubmitForm', { content_name: d.type || 'lead' }); } catch(x){}
		try { if (window._lt && C.line) _lt('send','cv',{type:'Conversion'},[C.line]); } catch(x){}
		try {
			if (window.gtag && C.gads && C.gads_label) {
				gtag('event','conversion',{ send_to: C.gads + '/' + C.gads_label });
			}
		} catch(x){}
	});
})();
</script>
	<?php
}, 20 );

/* =========================================================================
 * Meta Conversions API（サーバー側 Lead 送信）
 * ====================================================================== */
add_action( 'apprex_inquiry_submitted', 'apprex_meta_capi_lead', 10, 3 );

/**
 * フォーム送信時に Meta Conversions API へ Lead を送信。
 *
 * @param int    $post_id 受付ID。
 * @param string $type    種別。
 * @param array  $fields  送信内容。
 */
function apprex_meta_capi_lead( $post_id, $type, $fields ) {
	$pixel = apprex_sns_opt( 'apprex_px_meta' );
	$token = apprex_sns_opt( 'apprex_px_meta_token' );
	if ( ! $pixel || ! $token || ! (int) get_option( 'apprex_px_enabled', 0 ) ) {
		return;
	}

	$email = isset( $fields['email'] ) ? strtolower( trim( $fields['email'] ) ) : '';
	$phone = isset( $fields['phone'] ) ? preg_replace( '/[^0-9]/', '', $fields['phone'] ) : '';

	$user_data = array();
	if ( $email ) {
		$user_data['em'] = array( hash( 'sha256', $email ) );
	}
	if ( $phone ) {
		$user_data['ph'] = array( hash( 'sha256', $phone ) );
	}
	// クライアントIP / UA（マッチング率向上）。
	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$user_data['client_ip_address'] = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}
	if ( ! empty( $_SERVER['HTTP_USER_AGENT'] ) ) {
		$user_data['client_user_agent'] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) );
	}
	// Meta Pixel クッキー（_fbp / _fbc）。
	if ( ! empty( $_COOKIE['_fbp'] ) ) {
		$user_data['fbp'] = sanitize_text_field( wp_unslash( $_COOKIE['_fbp'] ) );
	}
	if ( ! empty( $_COOKIE['_fbc'] ) ) {
		$user_data['fbc'] = sanitize_text_field( wp_unslash( $_COOKIE['_fbc'] ) );
	}

	$event = array(
		'event_name'       => 'Lead',
		'event_time'       => time(),
		'event_id'         => 'lead_' . $post_id, // ブラウザ側 eventID と一致 → 重複排除。
		'action_source'    => 'website',
		'event_source_url' => home_url( '/' ),
		'user_data'        => $user_data,
		'custom_data'      => array( 'content_name' => apprex_type_label( $type ) ),
	);

	$body = array( 'data' => array( $event ) );
	$test = apprex_sns_opt( 'apprex_px_meta_test' );
	if ( $test ) {
		$body['test_event_code'] = $test;
	}

	$url = 'https://graph.facebook.com/v19.0/' . rawurlencode( $pixel ) . '/events?access_token=' . rawurlencode( $token );
	wp_remote_post(
		$url,
		array(
			'timeout'  => 8,
			'blocking' => false, // フォーム応答を遅らせない。
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( $body ),
		)
	);
}
