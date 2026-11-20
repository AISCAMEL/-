<?php
/**
 * カーメル：AIアシスト（在庫登録の時短＋GMB投稿文づくり）
 * ---------------------------------------------------------------------------
 * 在庫（portfolio）編集画面に「AIアシスト」ボックスを追加。
 *   ① GMB投稿文：車両情報からGoogleビジネスプロフィール用のきれいな投稿文を
 *      AIが作成（確認してコピー→投稿）。
 *   ② スペック推定：型式／車台番号を入れると、メーカー・車種・年式目安・排気量・
 *      ボディ・ミッション・駆動・乗車定員・燃費目安などをAIが推定して下書き表示。
 *      「追加スペック」欄（定員・燃費・保証）はワンクリックで反映。
 *
 * ・写真はスタッフが実車をアップ（AIは生成しません）。推定は“目安”＝必ず確認を。
 * ・Claude APIキーは「AI診断・デザイン」の設定を共用。固有名＋function_existsガード。
 * ---------------------------------------------------------------------------
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/* ---- メタボックス ---- */
if ( ! function_exists( 'carmelx_aiin_box' ) ) {
	function carmelx_aiin_box( $post ) {
		$has_key = function_exists( 'carmelx_ai_opts' ) && carmelx_ai_opts()['key'] !== '';
		wp_nonce_field( 'carmelx_aiin', 'carmelx_aiin_nonce' );
		?>
		<div id="carmel-aiin" data-pid="<?php echo (int) $post->ID; ?>">
			<?php if ( ! $has_key ) : ?>
				<p style="color:#b32d2e;">AIを使うには、先に <strong>ツール →（設定）AI診断・デザイン</strong> でClaude APIキーを設定してください。</p>
			<?php endif; ?>

			<p style="font-weight:700;margin:4px 0;">① 型式・車台番号からスペック推定（下書き）</p>
			<p style="margin:0 0 6px;">
				<input type="text" id="aiin-kata" placeholder="型式（例：DBA-NF15）" style="width:48%;">
				<input type="text" id="aiin-vin" placeholder="車台番号（任意）" style="width:48%;">
			</p>
			<p style="margin:0 0 6px;"><input type="text" id="aiin-grade" placeholder="グレード等の補足（任意）" style="width:100%;"></p>
			<button type="button" class="button" id="aiin-spec-btn" <?php disabled( ! $has_key ); ?>>AIで推定する</button>
			<div id="aiin-spec-out" style="margin-top:8px;"></div>

			<hr style="margin:14px 0;">

			<p style="font-weight:700;margin:4px 0;">② GMB（Googleビジネス）投稿文を作成</p>
			<p style="color:#888;font-size:12px;margin:0 0 6px;">この車両の情報からきれいな投稿文を作ります。内容を確認・編集してからGoogleに投稿してください。</p>
			<button type="button" class="button button-primary" id="aiin-gmb-btn" <?php disabled( ! $has_key ); ?>>GMB投稿文をAIで作成</button>
			<div id="aiin-gmb-out" style="margin-top:8px;"></div>
		</div>
		<script>
		(function(){
			var box=document.getElementById('carmel-aiin'); if(!box) return;
			var pid=box.getAttribute('data-pid');
			var nonce=document.getElementById('carmelx_aiin_nonce').value;
			function post(action, data, cb){
				var body=new URLSearchParams(Object.assign({action:action, _n:nonce, pid:pid}, data));
				fetch(ajaxurl,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:body.toString()})
					.then(function(r){return r.json();}).then(cb).catch(function(){ cb({success:false,data:'通信エラー'}); });
			}
			/* ① スペック推定 */
			var sb=document.getElementById('aiin-spec-btn');
			if(sb) sb.addEventListener('click',function(){
				var out=document.getElementById('aiin-spec-out'); out.innerHTML='推定中…';
				sb.disabled=true;
				post('carmel_aiin_spec',{kata:document.getElementById('aiin-kata').value,vin:document.getElementById('aiin-vin').value,grade:document.getElementById('aiin-grade').value},function(res){
					sb.disabled=false;
					if(!res||!res.success){ out.innerHTML='<span style="color:#b32d2e;">'+((res&&res.data)||'失敗しました')+'</span>'; return; }
					var d=res.data||{};
					var rows=Object.keys(d).filter(function(k){return k!=='_fill';}).map(function(k){ return '<tr><th style="text-align:left;padding:3px 8px;white-space:nowrap;">'+k+'</th><td style="padding:3px 8px;">'+(d[k]||'')+'</td></tr>'; }).join('');
					out.innerHTML='<div style="background:#f6f8f7;border:1px solid #dde6e0;border-radius:6px;padding:8px;"><div style="font-size:12px;color:#888;margin-bottom:4px;">AI推定（目安です。必ず確認・修正してください）</div><table style="font-size:13px;">'+rows+'</table>'
						+'<button type="button" class="button button-small" id="aiin-fill">追加スペック欄（定員・燃費・保証）に反映</button></div>';
					var fb=document.getElementById('aiin-fill');
					if(fb) fb.addEventListener('click',function(){
						var f=d._fill||{};
						function setv(name,val){ if(val==null||val==='')return; var el=document.querySelector('[name="carmelx_specx['+name+']"]'); if(el){ el.value=val; el.style.background='#fffbe6'; } }
						setv('cx_teiin', f.teiin); setv('cx_nenpi', f.nenpi); setv('cx_hoshou_kikan', f.hoshou_kikan); setv('cx_hoshou_kyori', f.hoshou_kyori);
						fb.textContent='反映しました（更新で保存）';
					});
				});
			});
			/* ② GMB投稿文 */
			var gb=document.getElementById('aiin-gmb-btn');
			if(gb) gb.addEventListener('click',function(){
				var out=document.getElementById('aiin-gmb-out'); out.innerHTML='作成中…'; gb.disabled=true;
				post('carmel_aiin_gmb',{},function(res){
					gb.disabled=false;
					if(!res||!res.success){ out.innerHTML='<span style="color:#b32d2e;">'+((res&&res.data)||'失敗しました')+'</span>'; return; }
					out.innerHTML='<textarea id="aiin-gmb-text" style="width:100%;height:200px;font-size:13px;">'+(res.data||'')+'</textarea><p><button type="button" class="button" id="aiin-copy">コピー</button> <span style="color:#888;font-size:12px;">確認・編集してからGoogleビジネスに投稿してください。</span></p>';
					var cp=document.getElementById('aiin-copy');
					if(cp) cp.addEventListener('click',function(){ var t=document.getElementById('aiin-gmb-text'); t.select(); try{document.execCommand('copy');}catch(e){} cp.textContent='コピーしました'; });
				});
			});
		})();
		</script>
		<?php
	}
}

if ( ! function_exists( 'carmelx_aiin_add_box' ) ) {
	function carmelx_aiin_add_box() {
		if ( ! post_type_exists( 'portfolio' ) ) { return; }
		add_meta_box( 'carmelx_aiin', 'AIアシスト（自動入力・GMB投稿文）', 'carmelx_aiin_box', 'portfolio', 'side', 'high' );
	}
	add_action( 'add_meta_boxes', 'carmelx_aiin_add_box' );
}

/* ---- 共通：権限・nonceチェック ---- */
if ( ! function_exists( 'carmelx_aiin_guard' ) ) {
	function carmelx_aiin_guard() {
		if ( ! current_user_can( 'edit_posts' ) ) { wp_send_json_error( '権限がありません' ); }
		if ( ! isset( $_POST['_n'] ) || ! wp_verify_nonce( $_POST['_n'], 'carmelx_aiin' ) ) { wp_send_json_error( '認証エラー' ); }
		if ( ! function_exists( 'carmelx_ai_call' ) ) { wp_send_json_error( 'AIモジュールが無効です' ); }
	}
}

/* ---- ① スペック推定 ---- */
if ( ! function_exists( 'carmelx_aiin_spec' ) ) {
	function carmelx_aiin_spec() {
		carmelx_aiin_guard();
		$kata  = sanitize_text_field( wp_unslash( $_POST['kata'] ?? '' ) );
		$vin   = sanitize_text_field( wp_unslash( $_POST['vin'] ?? '' ) );
		$grade = sanitize_text_field( wp_unslash( $_POST['grade'] ?? '' ) );
		if ( '' === $kata && '' === $vin ) { wp_send_json_error( '型式か車台番号を入力してください' ); }

		$sys = 'あなたは日本の中古車の専門家です。与えられた型式（または車台番号）から、その車のおおよそのスペックを推定し、JSONのみで返してください（説明文やコードフェンスは不要）。キーは日本語の表示名、値は文字列。推定が不確かな項目は「要確認」と入れる。最後に "_fill" キーを付け、{"teiin":"5","nenpi":"18.0km/L","hoshou_kikan":"","hoshou_kyori":""} の形で数値だけの反映用も入れる。返すトップレベルのキー例：メーカー, 車種, 年式目安, 排気量, ボディタイプ, ミッション, 駆動方式, 乗車定員, 燃費目安, 標準的な装備, _fill';
		$u = '型式: ' . ( $kata !== '' ? $kata : '(なし)' ) . "\n車台番号: " . ( $vin !== '' ? $vin : '(なし)' ) . "\n補足: " . ( $grade !== '' ? $grade : '(なし)' );
		$r = carmelx_ai_call( $sys, $u, 1200 );
		if ( empty( $r['ok'] ) ) { wp_send_json_error( $r['msg'] ); }
		$text = trim( preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', trim( $r['text'] ) ) );
		$data = json_decode( $text, true );
		if ( ! is_array( $data ) ) { wp_send_json_error( 'AIの返答を解釈できませんでした。もう一度お試しください。' ); }
		wp_send_json_success( $data );
	}
	add_action( 'wp_ajax_carmel_aiin_spec', 'carmelx_aiin_spec' );
}

/* ---- ② GMB投稿文 ---- */
if ( ! function_exists( 'carmelx_aiin_gmb' ) ) {
	function carmelx_aiin_gmb() {
		carmelx_aiin_guard();
		$pid = (int) ( $_POST['pid'] ?? 0 );
		if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) { wp_send_json_error( '対象の車両が不正です' ); }

		$title = get_the_title( $pid );
		$url   = get_permalink( $pid );
		$total = (int) preg_replace( '/[^0-9]/', '', (string) get_post_meta( $pid, 'est_total', true ) );
		$total_man = $total > 0 ? number_format( $total / 10000, 1 ) . '万円' : '';
		$shop  = (string) get_post_meta( $pid, 'shop', true );
		$kanri = (string) get_post_meta( $pid, 'kanri_bango', true );

		$facts = "車両: {$title}\n";
		if ( $total_man ) { $facts .= "支払総額: {$total_man}（諸費用込・金利別）\n"; }
		if ( $shop ) { $facts .= "販売店: {$shop}\n"; }
		if ( $kanri ) { $facts .= "管理番号: {$kanri}\n"; }
		$facts .= "ページURL: {$url}\n";

		$sys = 'あなたは中古車販売店カーメルのSNS担当です。Googleビジネスプロフィール（GMB）の投稿文を日本語で作成してください。'
			. '構成：先頭に【新着入荷】等の短い見出し＋絵文字、車名・年式・走行・支払総額、やわらかい一言（信用回復ローン・他社で断られた方も相談OK・頭金なし相談可などカーメルの強み）、最後に「詳しくはこちら→URL」と、ハッシュタグ3〜5個。'
			. '誇大表現や「必ず通る」等は書かない。1200文字以内。投稿にそのまま使える完成文のみを出力（説明や注釈は不要）。';
		$r = carmelx_ai_call( $sys, $facts, 1200 );
		if ( empty( $r['ok'] ) ) { wp_send_json_error( $r['msg'] ); }
		wp_send_json_success( trim( $r['text'] ) );
	}
	add_action( 'wp_ajax_carmel_aiin_gmb', 'carmelx_aiin_gmb' );
}

/* sentinel */
if ( ! function_exists( 'carmelx_aiin_render' ) ) {
	function carmelx_aiin_render() { /* marker for module manifest */ }
}
