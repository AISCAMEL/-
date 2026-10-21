<?php
/**
 * AIMEO：AIで運用するMEO（Googleビジネスプロフィール／Googleマップ対策）。
 *
 * 管理画面「AIMEO」に店舗を登録すると、AIが次の下書きを作る。担当者は確認して
 * Googleビジネスプロフィールに貼るだけ（Googleへの自動投稿はしない）。
 * - 投稿（最新情報）／口コミの返信／ビジネスの説明文（750字まで）
 * - 口コミのお願い文（特典なし）／写真の撮影リスト／プロフィールの点検
 * - 毎週、投稿の下書きを自動で作ってメールで届ける（店舗ごとにオン・オフ）
 *
 * Googleのガイドラインに合わせ、入力にない事実・数字・「No.1」などは作らない、
 * 特典と引き換えの口コミ依頼はしない、キーワードを詰め込まない。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 店舗の入力項目（key => [ラベル, 種類, 説明]）。 */
function apprex_aimeo_fields() {
	return array(
		'name'     => array( '店名（Googleに出ている名前）', 'text', '' ),
		'category' => array( '業種・メインカテゴリ', 'text', '例：焼肉店、美容室、内科' ),
		'area'     => array( '地域（市区町村・駅など）', 'text', '例：いわき市平、いわき駅' ),
		'address'  => array( '住所', 'text', '' ),
		'phone'    => array( '電話番号', 'text', '' ),
		'hours'    => array( '営業時間・定休日', 'textarea', '' ),
		'url'      => array( 'ホームページ・予約・アプリのURL', 'text', '' ),
		'strength' => array( '強み・こだわり', 'textarea', '事実だけを書いてください。AIはここにないことを書きません。' ),
		'menu'     => array( '主なメニュー・サービス・価格', 'textarea', '' ),
		'news'     => array( '今月のお知らせ・キャンペーン', 'textarea', '投稿の材料になります。' ),
		'keywords' => array( '見つけてほしい言葉', 'text', '例：いわき 焼肉 個室, 平 ランチ（カンマ区切り）' ),
		'tone'     => array( '文章の雰囲気', 'select', '' ),
		'email'    => array( '投稿案の送り先メール', 'text', '空なら通知先メールに送ります。お店の方に直接送ることもできます。' ),
		'auto'     => array( '毎週、投稿の下書きを自動で作ってメールで送る', 'checkbox', '' ),
	);
}

/** 店舗の情報。 */
function apprex_aimeo_get( $post_id ) {
	$v = get_post_meta( $post_id, 'apprex_meo', true );
	return is_array( $v ) ? $v : array();
}

/* -------------------------------------------------------------------------
 * 管理画面：店舗（投稿タイプ）
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	register_post_type(
		'apprex_meo',
		array(
			'labels'        => array(
				'name'          => 'AIMEO',
				'singular_name' => 'AIMEOの店舗',
				'add_new'       => '店舗を追加',
				'add_new_item'  => 'AIMEOの店舗を追加',
				'edit_item'     => 'AIMEOの店舗',
				'all_items'     => '店舗の一覧',
				'menu_name'     => 'AIMEO',
			),
			'public'        => false,
			'show_ui'       => true,
			'show_in_menu'  => true,
			'menu_position' => 26,
			'menu_icon'     => 'dashicons-location-alt',
			'supports'      => array( 'title' ),
			'capability_type' => 'post',
		)
	);
} );

add_filter( 'enter_title_here', function ( $text, $post ) {
	return 'apprex_meo' === $post->post_type ? '管理用の名前（例：炭火焼肉はなび 平店）' : $text;
}, 10, 2 );

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_aimeo_ai', 'AIで作る（Googleビジネスプロフィールに貼るだけ）', 'apprex_aimeo_ai_box', 'apprex_meo', 'normal', 'high' );
	add_meta_box( 'apprex_aimeo_info', 'お店の情報', 'apprex_aimeo_info_box', 'apprex_meo', 'normal', 'default' );
	add_meta_box( 'apprex_aimeo_howto', 'AIMEOの使い方', 'apprex_aimeo_howto_box', 'apprex_meo', 'side', 'default' );
} );

/** お店の情報。 */
function apprex_aimeo_info_box( $post ) {
	$v = apprex_aimeo_get( $post->ID );
	wp_nonce_field( 'apprex_aimeo_save', 'apprex_aimeo_nonce' );
	echo '<table class="form-table" role="presentation">';
	foreach ( apprex_aimeo_fields() as $k => $f ) {
		$val = isset( $v[ $k ] ) ? $v[ $k ] : '';
		echo '<tr><th><label for="aimeo-' . esc_attr( $k ) . '">' . esc_html( $f[0] ) . '</label></th><td>';
		if ( 'textarea' === $f[1] ) {
			echo '<textarea id="aimeo-' . esc_attr( $k ) . '" name="apprex_meo[' . esc_attr( $k ) . ']" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
		} elseif ( 'select' === $f[1] ) {
			echo '<select id="aimeo-' . esc_attr( $k ) . '" name="apprex_meo[' . esc_attr( $k ) . ']">';
			foreach ( array( 'polite' => 'ていねい', 'friendly' => '親しみやすい', 'calm' => '落ち着いた・高級感' ) as $tk => $tl ) {
				echo '<option value="' . esc_attr( $tk ) . '"' . selected( $val, $tk, false ) . '>' . esc_html( $tl ) . '</option>';
			}
			echo '</select>';
		} elseif ( 'checkbox' === $f[1] ) {
			echo '<label><input type="checkbox" id="aimeo-' . esc_attr( $k ) . '" name="apprex_meo[' . esc_attr( $k ) . ']" value="1"' . checked( $val, '1', false ) . '> オン（毎週月曜の朝）</label>';
		} else {
			echo '<input type="text" id="aimeo-' . esc_attr( $k ) . '" name="apprex_meo[' . esc_attr( $k ) . ']" value="' . esc_attr( $val ) . '" class="regular-text">';
		}
		if ( $f[2] ) {
			echo '<p class="description">' . esc_html( $f[2] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</table>';
}

add_action( 'save_post_apprex_meo', function ( $post_id ) {
	if ( ! isset( $_POST['apprex_aimeo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_aimeo_nonce'] ) ), 'apprex_aimeo_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in  = isset( $_POST['apprex_meo'] ) ? (array) wp_unslash( $_POST['apprex_meo'] ) : array();
	$out = array();
	foreach ( apprex_aimeo_fields() as $k => $f ) {
		$raw = isset( $in[ $k ] ) ? $in[ $k ] : '';
		if ( 'textarea' === $f[1] ) {
			$out[ $k ] = sanitize_textarea_field( $raw );
		} elseif ( 'checkbox' === $f[1] ) {
			$out[ $k ] = $raw ? '1' : '';
		} elseif ( 'email' === $k ) {
			$out[ $k ] = sanitize_email( $raw );
		} elseif ( 'url' === $k ) {
			$out[ $k ] = esc_url_raw( $raw );
		} else {
			$out[ $k ] = sanitize_text_field( $raw );
		}
	}
	update_post_meta( $post_id, 'apprex_meo', $out );
} );

/** AIの作業の一覧（key => [ボタン名, 説明]）。 */
function apprex_aimeo_tasks() {
	return array(
		'posts'  => array( '投稿を4本作る', 'Googleの「最新情報」に載せる投稿の下書き。今月のお知らせとキーワードを自然に入れます。' ),
		'reply'  => array( '口コミの返信を作る', '届いた口コミを貼ると、お礼と改善の気持ちが伝わる返信を作ります。' ),
		'desc'   => array( 'ビジネスの説明文を作る', 'プロフィールの「ビジネスの説明」（750字まで）。' ),
		'ask'    => array( '口コミのお願い文を作る', 'お店のPOP・SNS・LINEで使える、口コミをお願いする短い文。特典と引き換えにはしません。' ),
		'photos' => array( '写真の撮影リストを作る', 'プロフィールに足すと良い写真と、撮り方のコツ。' ),
		'check'  => array( 'プロフィールを点検する', '入力内容から、足りないところと直し方を一覧にします。' ),
	);
}

/** 使い方。 */
function apprex_aimeo_howto_box() {
	echo '<ol style="margin:0 0 0 18px;font-size:13px;line-height:1.7">';
	echo '<li>「お店の情報」を入れて「更新」</li>';
	echo '<li>「AIで作る」のボタンを押す</li>';
	echo '<li>できた文章を確認して、Googleビジネスプロフィールに貼る</li>';
	echo '<li>毎週の自動作成をオンにすると、月曜の朝に投稿案がメールで届きます</li>';
	echo '</ol><p style="font-size:12px;color:#646970">AIは「お店の情報」にないことは書きません。料金や日付は、貼る前に必ず確認してください。</p>';
}

/** AIで作るボックス。 */
function apprex_aimeo_ai_box( $post ) {
	$tasks = apprex_aimeo_tasks();
	$log   = get_post_meta( $post->ID, 'apprex_meo_log', true );
	$log   = is_array( $log ) ? $log : array();
	$ready = '' !== apprex_openrouter_key();
	?>
	<div id="aimeo" data-id="<?php echo esc_attr( $post->ID ); ?>" data-rest="<?php echo esc_url( rest_url( 'apprex/v1/aimeo' ) ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'wp_rest' ) ); ?>">
		<?php if ( ! $ready ) : ?>
			<p class="notice notice-warning inline" style="padding:8px">OpenRouterのAPIキーが未設定です（設定 → APPREX チャット）。</p>
		<?php endif; ?>
		<?php if ( 'publish' !== $post->post_status && 'private' !== $post->post_status ) : ?>
			<p><b>先に下の「お店の情報」を入れて「公開」（保存）してください。</b></p>
		<?php endif; ?>
		<div class="aimeo-btns">
			<?php foreach ( $tasks as $k => $t ) : ?>
				<button type="button" class="button<?php echo 'posts' === $k ? ' button-primary' : ''; ?>" data-task="<?php echo esc_attr( $k ); ?>" title="<?php echo esc_attr( $t[1] ); ?>"><?php echo esc_html( $t[0] ); ?></button>
			<?php endforeach; ?>
		</div>
		<div class="aimeo-review" hidden>
			<p><label>届いた口コミ（そのまま貼る）<br><textarea rows="4" class="large-text" id="aimeoReview"></textarea></label></p>
			<p><label>星の数 <select id="aimeoStars"><option>5</option><option>4</option><option>3</option><option>2</option><option>1</option></select></label>
			<button type="button" class="button button-primary" data-task="reply" data-go="1">返信を作る</button></p>
		</div>
		<p class="aimeo-msg" role="status"></p>
		<div class="aimeo-out"></div>
		<?php if ( $log ) : ?>
			<details class="aimeo-log"><summary>これまでに作ったもの（新しい順・<?php echo count( $log ); ?>件）</summary>
				<?php foreach ( array_reverse( $log ) as $row ) : ?>
					<div class="aimeo-past"><b><?php echo esc_html( wp_date( 'n/j H:i', (int) $row['t'] ) . '　' . ( isset( $tasks[ $row['task'] ] ) ? $tasks[ $row['task'] ][0] : $row['task'] ) ); ?></b>
					<pre><?php echo esc_html( apprex_aimeo_plain( $row['task'], $row['out'] ) ); ?></pre></div>
				<?php endforeach; ?>
			</details>
		<?php endif; ?>
	</div>
	<style>
		#aimeo .aimeo-btns{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 10px}
		#aimeo .aimeo-card{border:1px solid #dcdcde;border-radius:6px;padding:10px 12px;margin:8px 0;background:#fff}
		#aimeo .aimeo-card h4{margin:0 0 6px;font-size:13px}
		#aimeo .aimeo-card pre,#aimeo .aimeo-past pre{white-space:pre-wrap;font-family:inherit;font-size:13px;line-height:1.7;margin:0;background:#f6f7f7;padding:8px;border-radius:4px}
		#aimeo .aimeo-meta{font-size:12px;color:#646970;margin-top:6px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
		#aimeo .aimeo-log{margin-top:12px}
		#aimeo .aimeo-past{margin:8px 0}
	</style>
	<script>
	(function(){
		var box=document.getElementById('aimeo'); if(!box) return;
		var out=box.querySelector('.aimeo-out'), msg=box.querySelector('.aimeo-msg'), rv=box.querySelector('.aimeo-review');
		function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}
		function card(title,text,meta){ return '<div class="aimeo-card"><h4>'+esc(title)+'</h4><pre>'+esc(text)+'</pre><div class="aimeo-meta"><span>'+String(text).length+'文字</span>'+(meta?'<span>'+esc(meta)+'</span>':'')+'<button type="button" class="button button-small" data-copy>コピー</button></div></div>'; }
		function show(task,o){
			var h='';
			if(task==='posts'){ (o.posts||[]).forEach(function(p,i){ h+=card('投稿 '+(i+1)+'：'+(p.title||''),p.body||'','ボタン：'+(p.button||'詳細')+(p.photo?'／写真：'+p.photo:'')); }); }
			else if(task==='reply'||task==='desc'){ h=card(task==='reply'?'口コミの返信':'ビジネスの説明文',o.text||'',task==='desc'?'750字まで':''); }
			else if(task==='ask'){ h=card('お店のPOP・レジ横',o.pop||'')+card('SNS',o.sns||'')+card('LINE・メール',o.line||''); }
			else { (o.items||[]).forEach(function(it){ h+=card(it.title||'',(it.how||'')+(it.why?'\n（理由）'+it.why:''),''); }); if(task==='check'&&typeof o.score==='number'){ h='<p><b>入力の充実度：'+o.score+'点</b>（100点満点・入力内容から計算）</p>'+h; } }
			out.innerHTML=h||'<p>結果が空でした。もう一度お試しください。</p>';
		}
		box.addEventListener('click',function(e){
			var c=e.target.closest('[data-copy]'); if(c){ var t=c.closest('.aimeo-card').querySelector('pre').textContent; navigator.clipboard.writeText(t).then(function(){c.textContent='コピーしました';setTimeout(function(){c.textContent='コピー';},1500);}); return; }
			var b=e.target.closest('[data-task]'); if(!b) return;
			var task=b.getAttribute('data-task');
			if(task==='reply'&&!b.hasAttribute('data-go')){ rv.hidden=false; document.getElementById('aimeoReview').focus(); return; }
			var body={id:+box.dataset.id,task:task};
			if(task==='reply'){ body.review=document.getElementById('aimeoReview').value; body.stars=+document.getElementById('aimeoStars').value; if(!body.review.trim()){ msg.textContent='口コミを貼ってください。'; return; } }
			msg.textContent='AIが作っています…（10〜30秒）'; out.innerHTML='';
			box.querySelectorAll('[data-task]').forEach(function(x){x.disabled=true;});
			fetch(box.dataset.rest,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':box.dataset.nonce},body:JSON.stringify(body)})
			.then(function(r){return r.json();}).then(function(r){ if(r&&r.ok){ msg.textContent='できました。確認してから、Googleビジネスプロフィールに貼ってください。'; show(task,r.out); } else { msg.textContent=(r&&r.message)||'作れませんでした。'; } })
			.catch(function(){ msg.textContent='通信エラーです。'; })
			.finally(function(){ box.querySelectorAll('[data-task]').forEach(function(x){x.disabled=false;}); });
		});
	})();
	</script>
	<?php
}

/* 一覧の列。 */
add_filter( 'manage_apprex_meo_posts_columns', function ( $cols ) {
	return array(
		'cb'        => $cols['cb'],
		'title'     => '店舗',
		'aimeo_area' => '地域',
		'aimeo_auto' => '毎週の自動作成',
		'aimeo_last' => '最後に作った日',
	);
} );
add_action( 'manage_apprex_meo_posts_custom_column', function ( $col, $post_id ) {
	$v = apprex_aimeo_get( $post_id );
	if ( 'aimeo_area' === $col ) {
		echo esc_html( isset( $v['area'] ) ? $v['area'] : '' );
	} elseif ( 'aimeo_auto' === $col ) {
		echo ! empty( $v['auto'] ) ? 'オン' : '—';
	} elseif ( 'aimeo_last' === $col ) {
		$log = get_post_meta( $post_id, 'apprex_meo_log', true );
		echo is_array( $log ) && $log ? esc_html( wp_date( 'Y/n/j', (int) end( $log )['t'] ) ) : '—';
	}
}, 10, 2 );

/* -------------------------------------------------------------------------
 * AI
 * ---------------------------------------------------------------------- */
add_action( 'rest_api_init', function () {
	register_rest_route(
		'apprex/v1',
		'/aimeo',
		array(
			'methods'             => 'POST',
			'callback'            => 'apprex_aimeo_rest',
			'permission_callback' => function ( WP_REST_Request $r ) {
				$id = (int) $r->get_param( 'id' );
				return $id && 'apprex_meo' === get_post_type( $id ) && current_user_can( 'edit_post', $id );
			},
		)
	);
} );

/** REST：AIで作る。 */
function apprex_aimeo_rest( WP_REST_Request $request ) {
	$id   = (int) $request->get_param( 'id' );
	$task = sanitize_key( (string) $request->get_param( 'task' ) );
	$res  = apprex_aimeo_generate(
		$id,
		$task,
		array(
			'review' => sanitize_textarea_field( (string) $request->get_param( 'review' ) ),
			'stars'  => (int) $request->get_param( 'stars' ),
		)
	);
	if ( is_wp_error( $res ) ) {
		return rest_ensure_response( array( 'ok' => false, 'message' => $res->get_error_message() ) );
	}
	return rest_ensure_response( array( 'ok' => true, 'out' => $res ) );
}

/** 入力の充実度（0〜100）と、足りない項目。 */
function apprex_aimeo_score( $v ) {
	$w = array( 'name' => 10, 'category' => 10, 'area' => 10, 'address' => 10, 'phone' => 10, 'hours' => 10, 'url' => 10, 'strength' => 15, 'menu' => 10, 'keywords' => 5 );
	$s = 0;
	$miss = array();
	foreach ( $w as $k => $pt ) {
		if ( ! empty( $v[ $k ] ) ) {
			$s += $pt;
		} else {
			$miss[] = apprex_aimeo_fields()[ $k ][0];
		}
	}
	return array( $s, $miss );
}

/**
 * AIで下書きを作り、履歴に残す。
 *
 * @param int    $id    店舗ID。
 * @param string $task  apprex_aimeo_tasks() のキー。
 * @param array  $extra review / stars / count。
 * @return array|WP_Error
 */
function apprex_aimeo_generate( $id, $task, $extra = array() ) {
	if ( ! isset( apprex_aimeo_tasks()[ $task ] ) ) {
		return new WP_Error( 'bad_task', '作るものの指定が正しくありません。' );
	}
	$v = apprex_aimeo_get( $id );
	if ( empty( $v['name'] ) ) {
		return new WP_Error( 'no_info', '先に「お店の情報」の店名を入れて保存してください。' );
	}
	if ( '' === apprex_openrouter_key() ) {
		return new WP_Error( 'no_key', 'OpenRouterのAPIキーが未設定です（設定 → APPREX チャット）。' );
	}
	$tones = array( 'polite' => 'ていねいで誠実', 'friendly' => '親しみやすく明るい', 'calm' => '落ち着いた上品な' );
	$tone  = isset( $tones[ isset( $v['tone'] ) ? $v['tone'] : '' ] ) ? $tones[ $v['tone'] ] : $tones['polite'];
	$info  = array();
	foreach ( apprex_aimeo_fields() as $k => $f ) {
		if ( in_array( $k, array( 'email', 'auto', 'tone' ), true ) || empty( $v[ $k ] ) ) {
			continue;
		}
		$info[] = $f[0] . '：' . $v[ $k ];
	}
	$rules = "あなたは日本の店舗のGoogleビジネスプロフィール（MEO）を運用するプロです。必ず次を守ってください。\n"
		. "・下の「お店の情報」に書かれた事実だけを使う。書かれていない数字・価格・受賞歴・実績・「No.1」「地域最安」などは作らない\n"
		. "・見つけてほしい言葉は自然に1〜2回まで。言葉の詰め込みはしない\n"
		. "・特典や割引と引き換えに口コミを頼まない（Googleのポリシー違反）\n"
		. "・電話番号やURLは本文に入れない（Googleの機能で表示されるため）\n"
		. "・文章の雰囲気：{$tone}。絵文字は使わない。読みやすい短い文\n"
		. "・出力はJSONだけ（説明やコードブロックは不要）\n";
	$now   = wp_date( 'Y年n月' );
	$ask   = '';
	$max   = 1400;
	switch ( $task ) {
		case 'posts':
			$n   = isset( $extra['count'] ) ? max( 1, min( 4, (int) $extra['count'] ) ) : 4;
			$ask = "今は{$now}です。Googleビジネスプロフィールの「最新情報」に載せる投稿を{$n}本作ってください。季節・今月のお知らせ・強み・メニューから、毎回違う切り口にします。各本文は150〜300字。"
				. '形式：{"posts":[{"title":"管理用の短い見出し","body":"本文","button":"予約|オンライン注文|購入|詳細|登録|電話 のどれか","photo":"合う写真の例（短く）"}]}';
			$max = 1800;
			break;
		case 'reply':
			$stars = max( 1, min( 5, (int) $extra['stars'] ) );
			if ( '' === trim( (string) $extra['review'] ) ) {
				return new WP_Error( 'no_review', '口コミを貼ってください。' );
			}
			$ask = "次の口コミ（星{$stars}）への、お店からの返信を作ってください。100〜250字。お礼から始め、口コミの具体的な内容にふれる。星3以下なら言い訳せずにお詫びと改善を伝え、詳しくはお店に直接連絡してほしいと添える。お客様の個人情報や来店日時は書かない。"
				. "\n口コミ：「" . mb_substr( (string) $extra['review'], 0, 1500 ) . '」'
				. "\n" . '形式：{"text":"返信文"}';
			break;
		case 'desc':
			$ask = 'Googleビジネスプロフィールの「ビジネスの説明」を作ってください。600〜740字（750字を超えない）。何のお店か・どこにあるか・強み・主なメニュー・どんな人におすすめかを入れる。セール情報や期間限定の内容は入れない。形式：{"text":"説明文"}';
			break;
		case 'ask':
			$ask = '満足したお客様に、Googleマップで口コミを書いてもらうお願い文を3種類作ってください。特典・割引・プレゼントは約束しない。「良い口コミだけ」をお願いしない。形式：{"pop":"レジ横のPOP用（60字以内）","sns":"SNS投稿用（120字以内）","line":"LINE・メール用（150字以内）"}';
			break;
		case 'photos':
			$ask = 'このお店のGoogleビジネスプロフィールに足すと良い写真を8つ挙げ、それぞれ撮り方のコツを書いてください。外観・店内・商品やサービス・スタッフ・雰囲気が分かるように。形式：{"items":[{"title":"撮るもの","how":"撮り方のコツ（60字以内）","why":"なぜ効くか（40字以内）"}]}';
			break;
		case 'check':
			$ask = 'このお店のGoogleビジネスプロフィールで、見直すと良い点を6つ挙げてください（カテゴリ・説明文・営業時間・写真・投稿・口コミ返信・商品／サービス・属性など）。入力が足りない項目があれば最初に挙げる。形式：{"items":[{"title":"見直す点","how":"具体的な直し方（80字以内）","why":"なぜ大事か（40字以内）"}]}';
			break;
	}
	$res = apprex_studio_complete(
		array(
			array( 'role' => 'system', 'content' => $rules ),
			array( 'role' => 'user', 'content' => "お店の情報：\n" . implode( "\n", $info ) . "\n\n" . $ask ),
		),
		array( 'temperature' => 'reply' === $task ? 0.5 : 0.7, 'max_tokens' => $max )
	);
	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$out = apprex_studio_json( $res );
	if ( ! $out ) {
		return new WP_Error( 'bad_json', 'AIの返事を読み取れませんでした。もう一度お試しください。' );
	}
	$out = apprex_aimeo_clean( $task, $out );
	if ( 'check' === $task ) {
		list( $score ) = apprex_aimeo_score( $v );
		$out['score']  = $score;
	}
	$log   = get_post_meta( $id, 'apprex_meo_log', true );
	$log   = is_array( $log ) ? $log : array();
	$log[] = array( 't' => time(), 'task' => $task, 'out' => $out );
	update_post_meta( $id, 'apprex_meo_log', array_slice( $log, -30 ) );
	return $out;
}

/** AIの出力を、決まった形・長さに整える。 */
function apprex_aimeo_clean( $task, $o ) {
	$t = function ( $s, $n ) {
		return mb_substr( sanitize_textarea_field( (string) $s ), 0, $n );
	};
	switch ( $task ) {
		case 'posts':
			$btn   = array( '予約', 'オンライン注文', '購入', '詳細', '登録', '電話' );
			$posts = array();
			foreach ( array_slice( isset( $o['posts'] ) && is_array( $o['posts'] ) ? $o['posts'] : array(), 0, 4 ) as $p ) {
				$b       = isset( $p['button'] ) && in_array( $p['button'], $btn, true ) ? $p['button'] : '詳細';
				$posts[] = array( 'title' => $t( isset( $p['title'] ) ? $p['title'] : '', 40 ), 'body' => $t( isset( $p['body'] ) ? $p['body'] : '', 1500 ), 'button' => $b, 'photo' => $t( isset( $p['photo'] ) ? $p['photo'] : '', 60 ) );
			}
			return array( 'posts' => $posts );
		case 'reply':
			return array( 'text' => $t( isset( $o['text'] ) ? $o['text'] : '', 1000 ) );
		case 'desc':
			return array( 'text' => $t( isset( $o['text'] ) ? $o['text'] : '', 750 ) );
		case 'ask':
			return array( 'pop' => $t( isset( $o['pop'] ) ? $o['pop'] : '', 80 ), 'sns' => $t( isset( $o['sns'] ) ? $o['sns'] : '', 200 ), 'line' => $t( isset( $o['line'] ) ? $o['line'] : '', 250 ) );
		default:
			$items = array();
			foreach ( array_slice( isset( $o['items'] ) && is_array( $o['items'] ) ? $o['items'] : array(), 0, 10 ) as $it ) {
				$items[] = array( 'title' => $t( isset( $it['title'] ) ? $it['title'] : '', 40 ), 'how' => $t( isset( $it['how'] ) ? $it['how'] : '', 160 ), 'why' => $t( isset( $it['why'] ) ? $it['why'] : '', 80 ) );
			}
			return array( 'items' => $items );
	}
}

/** 履歴・メール用のテキスト。 */
function apprex_aimeo_plain( $task, $o ) {
	$lines = array();
	if ( 'posts' === $task ) {
		foreach ( (array) ( isset( $o['posts'] ) ? $o['posts'] : array() ) as $i => $p ) {
			$lines[] = '■ 投稿' . ( $i + 1 ) . '：' . $p['title'] . "\n" . $p['body'] . "\n（ボタン：" . $p['button'] . ( $p['photo'] ? '／写真：' . $p['photo'] : '' ) . '）';
		}
	} elseif ( 'ask' === $task ) {
		$lines[] = '■ POP：' . $o['pop'];
		$lines[] = '■ SNS：' . $o['sns'];
		$lines[] = '■ LINE・メール：' . $o['line'];
	} elseif ( isset( $o['text'] ) ) {
		$lines[] = $o['text'];
	} else {
		foreach ( (array) ( isset( $o['items'] ) ? $o['items'] : array() ) as $it ) {
			$lines[] = '■ ' . $it['title'] . '：' . $it['how'];
		}
	}
	return implode( "\n\n", $lines );
}

/* -------------------------------------------------------------------------
 * 毎週の自動作成（月曜の朝・自動作成オンの店舗だけ）
 * ---------------------------------------------------------------------- */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'apprex_aimeo_weekly' ) ) {
		$next = strtotime( 'next monday 08:00', current_time( 'timestamp' ) ) - (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
		wp_schedule_event( $next, 'weekly', 'apprex_aimeo_weekly' );
	}
} );

add_action( 'apprex_aimeo_weekly', 'apprex_aimeo_weekly_run' );

/** 自動作成オンの店舗に、今週の投稿案を2本作ってメールで送る。 */
function apprex_aimeo_weekly_run() {
	$ids = get_posts( array( 'post_type' => 'apprex_meo', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids' ) );
	foreach ( $ids as $id ) {
		$v = apprex_aimeo_get( $id );
		if ( empty( $v['auto'] ) ) {
			continue;
		}
		$out = apprex_aimeo_generate( $id, 'posts', array( 'count' => 2 ) );
		if ( is_wp_error( $out ) ) {
			continue;
		}
		$to      = ! empty( $v['email'] ) && is_email( $v['email'] ) ? $v['email'] : apprex_notify_to();
		$subject = '【AIMEO】今週のGoogle投稿案 — ' . $v['name'];
		$body    = $v['name'] . " ご担当者様\n\n今週のGoogleビジネスプロフィール（最新情報）の投稿案です。内容と日付・価格を確認して、Googleビジネスプロフィールの「投稿を追加」から貼ってください。\n\n" . apprex_aimeo_plain( 'posts', $out ) . "\n\n※ AIが「お店の情報」をもとに作った下書きです。";
		$html    = function_exists( 'apprex_render_email' ) ? apprex_render_email( $subject, $body, array( 'heading' => '今週のGoogle投稿案' ) ) : nl2br( esc_html( $body ) );
		wp_mail( $to, $subject, $html, function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
}
