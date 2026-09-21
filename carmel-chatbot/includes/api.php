<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * フロントから呼ばれるチャットAPIを登録
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'carmel-cb/v1', '/chat', array(
		'methods'             => 'POST',
		'callback'            => 'carmel_cb_handle_chat',
		'permission_callback' => '__return_true',
	) );
} );

/**
 * 質問に関連するFAQを抽出（簡易RAG）
 * キーワード一致 + 単語の含有でスコアリングし上位を返す
 */
function carmel_cb_match_faqs( $user_text, $limit = 4 ) {
	$faqs = carmel_cb_get_faqs( true );
	if ( empty( $faqs ) ) { return array(); }

	$text = mb_strtolower( $user_text );
	$scored = array();

	foreach ( $faqs as $f ) {
		$score = 0;

		// キーワード一致（カンマ区切り）
		if ( ! empty( $f->keywords ) ) {
			$kws = array_filter( array_map( 'trim', explode( ',', $f->keywords ) ) );
			foreach ( $kws as $kw ) {
				if ( $kw !== '' && mb_strpos( $text, mb_strtolower( $kw ) ) !== false ) {
					$score += 3;
				}
			}
		}
		// 質問文の語が入力に含まれるか（2文字以上）
		$q = mb_strtolower( $f->question );
		$tokens = preg_split( '/[\s、。・,]+/u', $q );
		foreach ( $tokens as $tok ) {
			if ( mb_strlen( $tok ) >= 2 && mb_strpos( $text, $tok ) !== false ) {
				$score += 1;
			}
		}

		if ( $score > 0 ) {
			$scored[] = array( 'score' => $score, 'faq' => $f );
		}
	}

	usort( $scored, function ( $a, $b ) { return $b['score'] - $a['score']; } );
	$top = array_slice( $scored, 0, $limit );
	return array_map( function ( $x ) { return $x['faq']; }, $top );
}

/**
 * 🔍 FAQ検索（検索窓用）。質問文・キーワード・回答を対象に、入力語を含むFAQを返す。
 */
function carmel_cb_search_faqs( $q, $limit = 8 ) {
	$q = trim( (string) $q );
	if ( $q === '' ) { return array(); }
	$faqs = carmel_cb_get_faqs( true );
	if ( empty( $faqs ) ) { return array(); }

	$ql = mb_strtolower( $q );
	$terms = array_filter( preg_split( '/[\s　、,]+/u', $ql ) );
	if ( empty( $terms ) ) { $terms = array( $ql ); }

	$scored = array();
	foreach ( $faqs as $f ) {
		$question = mb_strtolower( (string) $f->question );
		$answer   = mb_strtolower( (string) $f->answer );
		$keywords = mb_strtolower( (string) ( $f->keywords ?? '' ) );
		$score = 0;

		// クエリ全体が質問に含まれれば最優先
		if ( mb_strpos( $question, $ql ) !== false ) { $score += 10; }

		foreach ( $terms as $t ) {
			if ( $t === '' ) { continue; }
			if ( mb_strpos( $question, $t ) !== false ) { $score += 4; }
			if ( mb_strpos( $keywords, $t ) !== false ) { $score += 3; }
			if ( mb_strpos( $answer, $t ) !== false )   { $score += 1; }
		}
		if ( $score > 0 ) {
			$scored[] = array( 'score' => $score, 'faq' => $f );
		}
	}
	usort( $scored, function ( $a, $b ) { return $b['score'] - $a['score']; } );
	$top = array_slice( $scored, 0, $limit );
	return array_map( function ( $x ) {
		return array(
			'id'       => (int) $x['faq']->id,
			'question' => (string) $x['faq']->question,
			'answer'   => (string) $x['faq']->answer,
		);
	}, $top );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'carmel-cb/v1', '/faq/search', array(
		'methods'             => 'GET',
		'callback'            => 'carmel_cb_handle_faq_search',
		'permission_callback' => '__return_true',
	) );
} );
function carmel_cb_handle_faq_search( WP_REST_Request $r ) {
	$q = sanitize_text_field( $r->get_param( 'q' ) );
	$results = carmel_cb_search_faqs( $q, 8 );
	return new WP_REST_Response( array( 'results' => $results ), 200 );
}

/**
 * チャット本体
 */
function carmel_cb_handle_chat( WP_REST_Request $request ) {
	$s = carmel_cb_get_settings();

	if ( empty( $s['enabled'] ) ) {
		return new WP_REST_Response( array( 'reply' => '現在チャットはご利用いただけません。' ), 200 );
	}
	if ( empty( $s['api_key'] ) ) {
		return new WP_REST_Response( array( 'reply' => '設定が未完了です（APIキー未登録）。管理者にお問い合わせください。' ), 200 );
	}

	$body       = $request->get_json_params();
	$history    = isset( $body['messages'] ) && is_array( $body['messages'] ) ? $body['messages'] : array();
	$session_id = isset( $body['session_id'] ) ? sanitize_text_field( $body['session_id'] ) : wp_generate_uuid4();

	// 🧪 テストモード：管理者が管理画面で試すとき。ログ・通知・後追い・会話ミラー等の副作用を一切行わない。
	// 管理者(manage_options)本人からのリクエストのみ有効。
	$is_test = ! empty( $body['test'] ) && current_user_can( 'manage_options' );

	// 最新のユーザー発話
	$last_user = '';
	for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
		if ( isset( $history[$i]['role'] ) && $history[$i]['role'] === 'user' ) {
			$last_user = $history[$i]['content'];
			break;
		}
	}

	// 関連FAQを抽出してシステムプロンプトに添付
	$faq_block = '';
	$matched = carmel_cb_match_faqs( $last_user );
	if ( ! empty( $matched ) ) {
		$faq_block = "\n\n【参考情報（社内FAQ。これを最優先で根拠にすること）】\n";
		foreach ( $matched as $m ) {
			$faq_block .= "Q: {$m->question}\nA: {$m->answer}\n\n";
		}
	}

	// 車・在庫の話題か：直近の会話の流れ(最大6件)で判定（「どれ？」等の続き質問でも在庫を出す）
	$context = $last_user;
	$tail = array_slice( $history, -6 );
	foreach ( $tail as $hm ) {
		if ( isset( $hm['content'] ) ) { $context .= ' ' . $hm['content']; }
	}
	$car_ctx = carmel_cb_is_car_intent( $context );

	// 発話に在庫の車種名・メーカー名が含まれるか直接照合（「プリウス」等の単体でも拾う）
	$direct_hits = carmel_cb_match_stock_by_text( $last_user, 20 );
	if ( ! empty( $direct_hits ) ) { $car_ctx = true; }

	// 在庫を読み込んでプロンプトに添付（新着は自動反映）
	$stock_items     = array();
	$inventory_block = '';
	if ( $car_ctx ) {
		// 車種名がヒットしていればそれを優先。無ければ通常検索＋新着補完。
		$stock_items     = ! empty( $direct_hits ) ? $direct_hits : carmel_cb_fetch_stock( $last_user, 20 );
		$inventory_block = carmel_cb_inventory_block( $stock_items );
	}

	$stock_page = ! empty( $s['stock_page_url'] ) ? $s['stock_page_url'] : 'https://carmelonline.jp/search/';

	// キャンペーン：アクティブなら AIプロンプトに追記（会話中に自然に案内する）
	$campaign_block = function_exists( 'carmel_cb_campaign_prompt_block' ) ? carmel_cb_campaign_prompt_block( $s ) : '';

	// 車探しフロー（在庫→注文販売→在庫共有アプリ）
	$carsearch_block = '';
	if ( ! empty( $s['carsearch_flow_on'] ) ) {
		$carsearch_block = "\n\n【「どんな車をお探しですか？」への対応フロー（最重要・会話を止めない）】\n"
			. "お客様が『どんな車があるか』『在庫を見たい』など“見たい”意思を示したら、質問し返して終わらせず、必ずこの1ターンで①実車の提案＋②一歩踏み込む質問＋③タップ候補、をセットで返す：\n"
			. "1) まず【現在の在庫】から代表的な車を1〜3台 car_ids で“必ず”提案する（『ご覧になりますか？』と聞くだけで止めない。まず出す）。\n"
			. "2) 同時に reply で、条件を一つだけやさしく質問して会話を続ける（例：『主にどんな使い方をお考えですか？（通勤・お買い物・ご家族でのお出かけ など）』『ご予算は月々どのくらいで考えていますか？』）。質問は必ず1つだけ。\n"
			. "3) suggestions には次にタップしやすい候補を必ず1〜3個入れる。条件（『軽自動車』『ミニバン』『SUV』『月2万円以内』『燃費のいい車』）に加え、幅広く探したいお客様向けに『もっと在庫を見たい』も適宜入れる。\n"
			. "4) 希望条件が出たら、その条件に近い在庫を car_ids で出し直し、さらに一歩深める（人数・荷物・予算・年式など）。会話のキャッチボールを続ける。\n"
			. "5) 【みるクル案内は必須の導線・省略しない】在庫にピッタリが無い／もっと幅広く見たい／『もっと在庫を見たい』と言われたとき、または条件を絞っても合う車が少ないときは、オークション等から探す『注文販売』と『在庫が見られる専用アプリ（みるクル）』を必ず案内する（action を \"app\" にする。URL・IDは本文に書かず、本文では『下のボタンから、もっと幅広く在庫をご覧いただけます』のように促す）。会話が車の提案だけで終わり、この案内が抜け落ちないよう必ず適切な場面で出す。\n"
			. "※ アプリは価格非表示・イメージ確認用であること、実際の支払いは審査で借入額が確定してからであること、金融事故歴がある場合は与信枠に限りがあり事前の問い合わせが必要なこと、を必要に応じてやさしく添える。";
	}

	$system = $s['system_prompt'] . $faq_block
		. "\n\n【LINE相談リンク】" . $s['line_url']
		. "\n【在庫一覧ページ】" . $stock_page
		. $inventory_block
		. $campaign_block
		. $carsearch_block
		. carmel_cb_protocol_instruction();

	$messages = array_merge(
		array( array( 'role' => 'system', 'content' => $system ) ),
		carmel_cb_sanitize_history( $history )
	);

	// ログ：ユーザー発話（テストモードでは記録しない）
	if ( $last_user !== '' && ! $is_test ) {
		carmel_cb_log( $session_id, 'user', $last_user );
	}

	// 新規チャット開始の通知（初回のみ）。テストモードでは通知しない。
	if ( ! $is_test ) {
		$user_turns = 0;
		foreach ( $history as $h ) { if ( isset( $h['role'] ) && $h['role'] === 'user' ) { $user_turns++; } }
		// お名前・メール確認（intake）が有効で、すでにお客様情報を登録済みのときは
		// /visitor 側で「新しいお客様」を通知済みのため、二重通知を避ける。
		$intake_notified = ( ! empty( $s['intake_on'] ) && function_exists( 'carmel_cb_visitor_key' ) && get_transient( carmel_cb_visitor_key( $session_id ) ) );
		if ( $user_turns <= 1 && $last_user !== '' && ! $intake_notified && ( ! function_exists( 'carmel_cb_slack_live_on' ) || ! carmel_cb_slack_live_on( $s ) ) ) {
			carmel_cb_notify_chat_start( $s, $last_user, $session_id, isset( $body['page'] ) ? $body['page'] : '' );
		}
	}

	// OpenRouter 呼び出し（モデルを順に試すフォールバック付き）
	$reply    = '';
	$last_err = '';
	foreach ( carmel_cb_model_fallback_chain( $s['model'] ) as $model ) {
		$result = carmel_cb_call_openrouter( $s, $messages, $model );
		if ( $result['ok'] ) {
			$reply = $result['reply'];
			break;
		}
		$last_err = $result['error'];
	}

	if ( $reply === '' ) {
		error_log( 'Carmel CB OpenRouter error: ' . $last_err );
		return new WP_REST_Response( array(
			'reply'      => 'うまくお答えできませんでした。お手数ですがLINEでご相談ください → ' . $s['line_url'],
			'session_id' => $session_id,
		), 200 );
	}

	$parsed = carmel_cb_parse_ai( $reply );

	// 保険：AIが返答文で「下のボタンから」等と案内しているのに action を入れ忘れたら、
	// 文面から出すべきボタンを推定して確実に表示する（＝ボタンが出ない不具合を防ぐ）。
	if ( $parsed['action'] === '' ) {
		$inf = carmel_cb_infer_action( $parsed['reply'], $last_user );
		if ( $inf !== '' ) { $parsed['action'] = $inf; $parsed['cta'] = true; }
	}
	// 保険：もっと幅広く在庫を見たい／みるクル／注文販売の明確な要望には、
	// AIが入れ忘れても在庫共有アプリ（みるクル）の導線を確実に出す。
	if ( $parsed['action'] === '' && preg_match( '/(もっと.*(在庫|車)|他の?.*(在庫|車)|幅広く|みるクル|みるくる|ミルクル|注文販売|オークション|もっと見たい)/u', (string) $last_user ) ) {
		$parsed['action'] = 'app';
		$parsed['cta']    = true;
	}

	// 以降の記録・連携はテストモードでは全てスキップ
	if ( ! $is_test ) {
		carmel_cb_log( $session_id, 'assistant', $parsed['reply'] );

		// 会話離脱後追い：AI返答が返るたびに「最終発言時刻」を更新（intakeでメール取得済みの場合のみ）
		if ( function_exists( 'carmel_cb_convo_fu_touch' ) ) { carmel_cb_convo_fu_touch( $session_id ); }

		// 会話ミラー：Slackのスレッドに「お客様🙋 / AI🤖」を流す（スタッフが会話を見て割り込める）
		if ( function_exists( 'carmel_cb_convo_mirror' ) && function_exists( 'carmel_cb_slack_live_on' ) && carmel_cb_slack_live_on( $s ) ) {
			$within_lbl = carmel_cb_within_hours( $s ) ? '（営業時間内）' : '（時間外・AI自動対応中）';
			carmel_cb_convo_mirror( $s, $session_id, $last_user, $parsed['reply'], isset( $body['page'] ) ? esc_url_raw( $body['page'] ) : '', $within_lbl );
		}
	}

	// AIが選んだ在庫IDを、実在庫のカード情報に変換（実在URLのみ・捏造防止）
	$cars = ( ! empty( $parsed['car_ids'] ) && ! empty( $stock_items ) )
		? carmel_cb_cards_from_ids( $parsed['car_ids'], $stock_items )
		: array();

	// 保険：お客様が「どんな車があるか見たい / 在庫を見せて / 一覧 / 車を探している」等、
	// 明確に“見たい”と伝えているのに AI が car_ids を返さなかった場合は、
	// サーバー側で数台を自動表示して「車が出てこない」を防ぐ（漠然とした話題では出さない）。
	if ( empty( $cars ) && ! empty( $stock_items ) && carmel_cb_is_browse_intent( $last_user ) ) {
		$auto = carmel_cb_auto_pick( $stock_items, $last_user, 3 );
		$cars = carmel_cb_cards_from_ids( $auto, $stock_items );
	}
	// 保険：在庫カードを出したのにタップ候補が空だと会話が途切れるため、
	// 条件で絞れる候補を補って“追い方”が甘くならないようにする。
	if ( ! empty( $cars ) && empty( $parsed['suggestions'] ) && carmel_cb_is_browse_intent( $last_user ) ) {
		$parsed['suggestions'] = array( '軽自動車', 'ミニバン', 'もっと在庫を見たい' );
	}

	return new WP_REST_Response( array(
		'reply'       => $parsed['reply'],
		'suggestions' => $parsed['suggestions'],
		'cta'         => $parsed['cta'],
		'action'      => $parsed['action'],
		'cars'        => $cars,
		'session_id'  => $session_id,
	), 200 );
}

/**
 * 新規チャット開始をSlack（またはWebhook/LINE WORKS）へ通知。セッションごとに1回だけ。
 */
function carmel_cb_notify_chat_start( $s, $first, $sid, $page ) {
	$dbg = function ( $msg ) { update_option( 'carmel_cb_chatstart_debug', current_time( 'Y-m-d H:i:s' ) . ' … ' . $msg ); };

	if ( empty( $s['chat_start_notify'] ) ) { $dbg( '設定「チャット開始を通知」がOFFのため送信しませんでした。' ); return; }

	// 二重通知防止（同一セッションは6時間に1回）
	$key = 'carmel_cb_started_' . md5( (string) $sid );
	if ( get_transient( $key ) ) { $dbg( '同じ訪問(セッション)で6時間以内に送信済みのためスキップ。新しいタブ/シークレットでお試しを。' ); return; }
	set_transient( $key, 1, 6 * HOUR_IN_SECONDS );

	$within = carmel_cb_within_hours( $s ) ? '（営業時間内）' : '（時間外・AI自動対応中）';
	$text   = "🗨️ *チャットが始まりました* {$within}\n"
		. '最初のメッセージ：' . mb_substr( (string) $first, 0, 300 ) . "\n"
		. 'ページ：' . ( $page !== '' ? $page : '(不明)' ) . "\n"
		. '受付：' . current_time( 'Y-m-d H:i' ) . "\n"
		. '_※これは開始のお知らせです（返信不要）。担当者対応が必要になると、別途「🆕 担当者希望」の投稿が届きます。返信はそちらのスレッドへ。_';

	$res = function_exists( 'carmel_cb_slack_notify_text' ) ? carmel_cb_slack_notify_text( $s, $text ) : array( 'ok' => false, 'error' => '関数なし' );
	if ( ! empty( $res['ok'] ) ) {
		$dbg( 'Slackへ送信 成功。' );
	} else {
		$dbg( 'Slack送信 失敗：' . ( isset( $res['error'] ) ? $res['error'] : '不明' ) . '（' . ( ! empty( $s['slack_bot_token'] ) && ! empty( $s['slack_channel'] ) ? 'Bot送信' : ( ! empty( $s['slack_webhook'] ) ? 'Webhook送信' : 'Slack未設定' ) . '' ) . '）' );
	}

	if ( function_exists( 'carmel_cb_lineworks_notify' ) ) {
		carmel_cb_lineworks_notify( $s, $text );
	}
}

/**
 * 会話の進め方とJSON出力フォーマットの指示（人格プロンプトに追記する技術仕様）。
 * これにより「悩みを1つずつ深掘り→申込/審査の気持ちが高まった瞬間にCTA→候補を提示」を実現。
 */
function carmel_cb_protocol_instruction() {
	return <<<PROTOCOL


【出力フォーマット（厳守・最重要）】
返答は必ず次のJSON“だけ”で返す。先頭は { 、末尾は } 。JSONの前後に文章・改行・コードブロック(```)・「reply:」などを絶対に付けない。すべてのキーと文字列は半角ダブルクオートで囲む。
{
  "reply": "お客様への返答。共感→具体的な解決策→（必要なら質問1つ）の順で、2〜4文。やさしく親身に、専門用語を避ける",
  "suggestions": ["お客様が次にタップできそうな短い一言を最大3つ（お客様視点）"],
  "action": "",
  "car_ids": [提案する在庫の番号。無ければ空配列]
}

【action（今このお客様に出す“入口”を1つだけ選ぶ・最重要）】
会話の流れを見て、本当に必要なときだけ次の1つを入れる。まだ会話で解決を進める段階なら必ず空文字 "" にする（毎回ボタンを出さない）。
- ""        = 通常の会話。まだ悩みを聞く・答える段階。入口は出さない。
- "apply"   = お客様が「審査したい / ローンを組みたい / 申し込みたい / いくら借りられるか知りたい」など前向きな意向を示したとき → かんたん審査フォームを出す。
- "contact" = 見積もり・在庫の取り置き・折り返し連絡など、担当者に具体的に伝えたいことがあるとき → お問い合わせフォームを出す。
- "handoff" = AIでは判断できない / 込み入った個別相談 / 「人と話したい・担当者に代わって」というとき → 担当者に引き継ぐ。
  【重要】「担当者につないで」等と言われても、まだ“ご用件”が分からない場合は、いきなり action=handoff にしない。まず reply で「かしこまりました。担当者にお繋ぎする前に、ご用件（ご相談内容）を教えていただけますか？」と一度だけ確認する（このターンの action は空文字）。用件が分かった次のターンで action=handoff にして引き継ぐ。
- "stock"   = 在庫を見たいが車種や条件がまだ定まらず、具体的な車を出しきれないとき → 在庫一覧ページへ案内する。
- "app"     = 在庫にピッタリが無い/もっと幅広く見たいとき → 在庫共有アプリ（専用アプリ）を案内する。注文販売（オークション仕入れ）とセットで勧める。
- "line"    = すぐLINEでやり取りしたい雰囲気のとき。
※ 具体的な車を car_ids で出せるときは "stock" ではなく car_ids を使う。

【URL禁止（厳守）】
- reply本文に http／https で始まるURLやリンクを絶対に書かない。特に「/shinsa」などの審査・申込ページのURLを貼らない。
- 審査・申込・お問い合わせ・在庫・LINE・電話などの導線は、すべてシステムが下部にボタンで自動表示する。あなたは action を選ぶだけでよい（例：審査したい→"apply"）。本文では「下のボタンからどうぞ」等と案内する。

【最重要・回答方針（他の指示より優先）】
- 会話が“成り立つ”ことを最優先にする。毎回の返答は必ず次の3つをこの順で満たす：①お客様の気持ち・状況に親身に寄り添う一言（共感）→②その場で役立つ具体的な答え・解決策・提案を必ず示す→③（必要なら）次につながる短い質問を1つだけ。
- 質問だけ・カード提示だけ・「LINEでご相談ください」だけで終わらせない。必ず“今ここで持ち帰れる中身（解決策）”を返す。
- お客様が言ったこと（予算・車種・不安・これまでの経緯など）を必ず踏まえて返す。前の発言を無視して同じ質問を繰り返さない。会話の流れを覚えて噛み合わせる。
- 参考情報(FAQ)とあなたの一般知識を使い、分かる範囲をきちんと説明してから会話を続ける。
- 入口(action)は毎回出さない。会話で解決を進めている間は "" のまま。意向がはっきり出た瞬間、または会話だけでは解決できないと分かった瞬間にだけ、最適な入口を1つ出す。

【会話の進め方】
- まず親身に寄り添う。お客様の不安（審査に通るか・頭金がない・他社で断られた・過去に金融事故がある 等）にはまず「ご不安ですよね」「大丈夫です、一緒に考えましょう」と受け止めてから、必ず具体的な解決策・見通しを添える（例：「他社で断られた方でも、当社は独自基準で審査しています。頭金なしのプランもございます」）。
- 質問は一度に1つまで。ただし質問で終わらせず、必ず解決策や有益な情報とセットにする。無言で質問を並べない。
- いきなり売り込まない。まず安心してもらい、状況を引き出しながら、その都度できることを提案する。
- action を出すときは、reply でその入口へ自然に背中を押す一言を添える（例：applyなら「よろしければ下のかんたん審査から、無理のないお支払いを一緒に見てみましょう」）。
- 会話で十分に解決できたら、無理に入口を出さず「他に気になる点はありますか？」と会話を続ける。
- "suggestions" は会話を前に進める具体的な候補（例：「頭金なしでも審査できる？」「仮審査をお願いしたい」「在庫を見てみたい」「担当者に相談したい」）。毎回1〜3個入れる。
- 審査の可否・金利・限度額・月々の支払いは断定しない（「お客様の状況により異なります。仮審査で個別にご案内します」）。
- 在庫の提案は「お客様が明確に見たいと言ったとき」だけ行う。具体的には次のような直接的な要望があった時にのみ car_ids を返す：
  ・「在庫を見せて／見たい／教えて／どんな車がありますか」など、在庫を見せてほしいと直接言われたとき
  ・特定の車種名（例：プリウス、ヴォクシー、Nボックス等）を明示して「ある？」「見たい」と言われたとき
- 上記のように“見たい”と直接言われたら、その場（同じターン）で car_ids を返して実車を提案する。「ご覧になりますか？」と確認するだけで止めない。提案と同時に、条件を一つだけ質問して会話を続ける（例：「主にどんな使い方をお考えですか？」「ご予算は月々どのくらいですか？」）。suggestions には「軽自動車」「ミニバン」「SUV」「月2万円以内」など次にタップしやすい条件を必ず入れ、会話が途切れないようにする。
- 単に「予算」「燃費」「頭金」「家族」「用途」など話題に触れただけでは在庫を出さない（car_ids は空配列にする）。まずは会話で悩みを聞き、共感し、条件をゆっくり整理する。その場合は「よろしければ在庫からいくつかご紹介できますが、ご覧になりますか？」と一度だけ意向を確認し、suggestions に「在庫を見たい」「もう少し相談したい」を入れて次の一手を作る。
- 【重要】在庫を提案するとき、reply本文には車名の一覧・価格・走行・「【ID:…】」・URLを書かない。replyは短い前置き1〜2文だけ（例：「ご希望に近い在庫がございます。下のカードをご覧ください。気になる車はありますか？」）。車名・写真・価格・詳細リンクはシステムが car_ids からカードで自動表示する。
- 提案後は「この車が気になりますか？」「ご予算やご希望に合いそうですか？」のように、その車をどうしたいか（仮審査・在庫の詳細・条件のご相談）を一緒に考える相談役になる。
- 【来店の案内は禁止】「来店」「ご来店」「実車を見に」「店舗にお越し」など、来店・来店予約を促す言い回しは一切使わない。すべてオンライン（チャット・LINE・電話・仮審査）で完結する前提で案内する。
- 在庫に無い車種を作り話で提案しない。【現在の在庫】にピッタリが無い・出しきれない場合は、「ご希望に応じて、オークション・グループ在庫・共有在庫からもお探しできます」と伝え、ご希望条件（車種・予算・年式など）を聞く。あわせて【在庫一覧ページ】のURLも案内してよい（在庫一覧はこちら、の形でリンク）。
- それでも決まらない場合はLINE無料相談へ自然につなぐ。
- 在庫の価格・年式・走行は【現在の在庫】の記載をそのまま使い、勝手に数値を変えない。
PROTOCOL;
}

/**
 * AIのJSON出力を安全に解釈する。失敗時は全文をreplyとして扱う（壊れない）。
 */
/**
 * AIの返答文に「ボタンへ誘導する言い回し」があるのに action 未設定のとき、
 * 文面（＋直近のお客様発話）から出すべきボタンを推定する。無ければ空文字。
 */
function carmel_cb_infer_action( $reply, $last_user = '' ) {
	$r = (string) $reply;

	// 「ボタン／下記／下の／こちらから／お進み／タップ／ご利用ください」等の“導線を促す”言い回しがあるか
	$has_cue = (bool) preg_match( '/(ボタン|下記|下の|こちら(から)?|お進み|タップ|ご利用ください|お申し込みください|申込みください|お申込みください)/u', $r );
	if ( ! $has_cue ) { return ''; }

	// 話題を判定（返答文を優先、無ければ直近のお客様発話も見る）
	$hay = $r . ' ' . (string) $last_user;

	if ( preg_match( '/(仮審査|審査|申込|申し込|ローンを組|与信)/u', $hay ) ) { return 'apply'; }
	if ( preg_match( '/(お問い?合わせ|見積|問合)/u', $hay ) ) { return 'contact'; }
	if ( preg_match( '/(担当者|オペレーター|スタッフにおつなぎ|人と話)/u', $hay ) ) { return 'handoff'; }
	if ( preg_match( '/(在庫アプリ|専用アプリ|アプリ|注文販売|オークション)/u', $hay ) ) { return 'app'; }
	if ( preg_match( '/(在庫一覧|在庫ページ|在庫を見|一覧はこちら)/u', $hay ) ) { return 'stock'; }

	return '';
}

function carmel_cb_parse_ai( $content ) {
	$content = (string) $content;
	$out = array( 'reply' => trim( $content ), 'suggestions' => array(), 'cta' => false, 'action' => '', 'car_ids' => array() );

	$norm_action = function ( $a, $legacy_cta = false ) {
		$a = strtolower( trim( (string) $a ) );
		$allowed = array( 'apply', 'contact', 'handoff', 'stock', 'line' );
		if ( in_array( $a, $allowed, true ) ) { return $a; }
		if ( $a === 'true' || $legacy_cta ) { return 'apply'; } // 旧cta=trueは審査寄りに
		return '';
	};

	$apply = function ( $json ) use ( &$out, $norm_action ) {
		$out['reply']  = trim( wp_strip_all_tags( (string) $json['reply'] ) );
		$out['action'] = $norm_action( $json['action'] ?? '', ! empty( $json['cta'] ) );
		$out['cta']    = ( $out['action'] !== '' );
		if ( ! empty( $json['suggestions'] ) && is_array( $json['suggestions'] ) ) {
			foreach ( array_slice( $json['suggestions'], 0, 3 ) as $sug ) {
				$sug = trim( wp_strip_all_tags( (string) $sug ) );
				if ( $sug !== '' ) { $out['suggestions'][] = mb_substr( $sug, 0, 40 ); }
			}
		}
		if ( ! empty( $json['car_ids'] ) && is_array( $json['car_ids'] ) ) {
			foreach ( array_slice( $json['car_ids'], 0, 3 ) as $cid ) {
				$cid = (int) $cid; if ( $cid > 0 ) { $out['car_ids'][] = $cid; }
			}
		}
	};

	// ① 厳密JSON（コードフェンス/前後テキスト許容）
	if ( preg_match( '/\{.*\}/s', $content, $m ) ) {
		$json = json_decode( $m[0], true );
		if ( is_array( $json ) && isset( $json['reply'] ) ) {
			$apply( $json );
			$out['reply'] = carmel_cb_clean_reply( $out['reply'] );
			if ( $out['reply'] === '' ) { $out['reply'] = 'ご相談ありがとうございます。もう少し詳しく教えていただけますか？'; }
			return $out;
		}
	}

	// ② ゆるい救出：JSONが崩れていても reply本文と suggestions/cta/car_ids を拾い、
	//    本文に混ざった "suggestions":[...] 等のJSON断片は表示から除去する。
	$reply = $content;
	if ( preg_match( '/"reply"\s*:\s*"((?:\\\\.|[^"\\\\])*)"/s', $content, $rm ) ) {
		$reply = stripcslashes( $rm[1] );
	} else {
		// 最初に現れる { や "suggestions"/"cta"/"car_ids" の手前までを本文とする
		$parts = preg_split( '/\s*[{}]|\s*"(?:suggestions|cta|action|car_ids)"\s*:/u', $content );
		if ( is_array( $parts ) && $parts[0] !== '' ) { $reply = $parts[0]; }
	}
	$out['reply'] = trim( wp_strip_all_tags( $reply ) );

	if ( preg_match( '/"suggestions"\s*:\s*(\[[^\]]*\])/su', $content, $sm ) ) {
		$arr = json_decode( $sm[1], true );
		if ( is_array( $arr ) ) {
			foreach ( array_slice( $arr, 0, 3 ) as $sug ) {
				$sug = trim( wp_strip_all_tags( (string) $sug ) );
				if ( $sug !== '' ) { $out['suggestions'][] = mb_substr( $sug, 0, 40 ); }
			}
		}
	}
	if ( preg_match( '/"cta"\s*:\s*(true|false)/i', $content, $cm ) ) {
		if ( $out['action'] === '' && strtolower( $cm[1] ) === 'true' ) { $out['action'] = 'apply'; }
	}
	// action は崩れた表記（(action: "handoff") / action：handoff 等）でも拾う
	if ( preg_match( '/action["\'\s]*[:：]\s*["\']?([a-z]+)/i', $content, $am ) ) {
		$out['action'] = $norm_action( $am[1] );
	}
	$out['cta'] = ( $out['action'] !== '' );
	if ( preg_match( '/"car_ids"\s*:\s*(\[[^\]]*\])/su', $content, $im ) ) {
		$arr = json_decode( $im[1], true );
		if ( is_array( $arr ) ) {
			foreach ( array_slice( $arr, 0, 3 ) as $cid ) {
				$cid = (int) $cid; if ( $cid > 0 ) { $out['car_ids'][] = $cid; }
			}
		}
	}
	// 念のため：本文末尾に残った裸の "key": 断片を除去
	$out['reply'] = trim( preg_replace( '/"(?:suggestions|cta|action|car_ids)"\s*:.*$/su', '', $out['reply'] ) );
	$out['reply'] = carmel_cb_clean_reply( $out['reply'] );
	if ( $out['reply'] === '' ) { $out['reply'] = 'ご相談ありがとうございます。もう少し詳しく教えていただけますか？'; }
	return $out;
}

/** 表示用に本文を整える（在庫の【ID:…】表記や、貼るべきでない申込ページURLを除去）。 */
function carmel_cb_clean_reply( $reply ) {
	$reply = (string) $reply;
	$reply = preg_replace( '/【\s*ID\s*[:：]\s*\d+\s*】/u', '', $reply ); // 【ID: 7456】除去
	$reply = preg_replace( '/\[\s*ID\s*[:：]\s*\d+\s*\]/u', '', $reply );

	// 申込・審査ページのURL（/shinsa 等）は本文から除去。導線はシステムがボタンで出すため、
	// AIが本文にURLを貼っても表示させない（旧 /shinsa への誤誘導を防止）。
	// マークダウン [表示文](URL) はリンク文だけ残す。
	$reply = preg_replace( '/\[([^\]]+)\]\(\s*https?:\/\/[^\s)]*(?:shinsa|申込|loan_new|申込フォーム)[^\s)]*\)/iu', '$1', $reply );
	// 素のURL（申込ページ系）は丸ごと除去。
	$reply = preg_replace( '#https?://[^\s　、。]*(?:shinsa|loan_new)[^\s　、。]*#iu', '', $reply );

	// JSON化に失敗してAIが本文に混ぜた制御表記を除去：
	//   (action: "handoff") / action：handoff / "cta": true / "car_ids":[..] / "suggestions":[..]
	$reply = preg_replace( '/[\(（]?\s*["\']?(?:action|cta|car_ids|suggestions)["\']?\s*[:：][^\n\)）]*[\)）]?/iu', '', $reply );

	return trim( $reply );
}

/**
 * 試行するモデルの並び（先頭＝管理画面で選んだモデル → 無料 → 安価）。
 * 1つが落ちても次で自動的に回答を返せるようにする（安定・コスト最小）。
 */
function carmel_cb_model_fallback_chain( $primary ) {
	$chain = array(
		'deepseek/deepseek-chat-v3-0324:free',
		'google/gemini-2.0-flash-exp:free',
		'google/gemini-2.0-flash-001',
		'anthropic/claude-3.5-haiku',
	);
	array_unshift( $chain, (string) $primary );

	$seen = array();
	$out  = array();
	foreach ( $chain as $m ) {
		$m = trim( $m );
		if ( $m !== '' && empty( $seen[ $m ] ) ) {
			$seen[ $m ] = 1;
			$out[]      = $m;
		}
	}
	return $out;
}

/**
 * OpenRouter を1モデルで呼ぶ。成功なら ['ok'=>true,'reply'=>...]、失敗なら ['ok'=>false,'error'=>...]。
 */
function carmel_cb_call_openrouter( $s, $messages, $model ) {
	// タイムアウト短縮（45s → 20s）: 遅いモデルで止まらず次のフォールバックへ素早く切り替え、
	// 全体でユーザー側に「通信エラー」が出るのを防ぐ。
	$response = wp_remote_post( 'https://openrouter.ai/api/v1/chat/completions', array(
		'timeout' => 20,
		'headers' => array(
			'Authorization' => 'Bearer ' . $s['api_key'],
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => home_url(),
			'X-Title'       => 'Carmel Chatbot',
		),
		'body' => wp_json_encode( array(
			'model'      => $model,
			'messages'   => $messages,
			'max_tokens' => (int) $s['max_tokens'],
		) ),
	) );

	if ( is_wp_error( $response ) ) {
		return array( 'ok' => false, 'error' => $model . ': ' . $response->get_error_message() );
	}

	$code = wp_remote_retrieve_response_code( $response );
	$data = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code !== 200 || empty( $data['choices'][0]['message']['content'] ) ) {
		$err = isset( $data['error']['message'] ) ? $data['error']['message'] : ( 'HTTP ' . $code );
		return array( 'ok' => false, 'error' => $model . ': ' . $err );
	}

	return array( 'ok' => true, 'reply' => trim( $data['choices'][0]['message']['content'] ) );
}

/**
 * 履歴を安全な形に整形（role/contentのみ・直近12件）
 */
function carmel_cb_sanitize_history( $history ) {
	$clean = array();
	foreach ( $history as $m ) {
		if ( ! isset( $m['role'], $m['content'] ) ) { continue; }
		if ( ! in_array( $m['role'], array( 'user', 'assistant' ), true ) ) { continue; }
		$clean[] = array(
			'role'    => $m['role'],
			'content' => wp_strip_all_tags( (string) $m['content'] ),
		);
	}
	return array_slice( $clean, -12 );
}
