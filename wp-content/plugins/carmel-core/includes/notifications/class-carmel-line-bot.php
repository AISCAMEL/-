<?php
/**
 * LINE 公式アカウント Webhook 受信 ＝ AI自動応答ボット。
 *
 * リッチメニュー併用の公式アカウントに来たメッセージへ自動応答する。
 *   POST /wp-json/carmel/v1/line-webhook
 *   - X-Line-Signature を チャネルシークレットで HMAC-SHA256 検証
 *   - text メッセージ → 応答を解決して reply API で返信
 *       1) AIエンドポイント（carmel_line_ai_endpoint・GAS/LLM）が設定済みなら委譲
 *       2) 未設定なら 組み込みFAQ（キーワード一致・フィルタで編集可）
 *       3) 審査/申込ワード → 審査フォーム(LIFF)・在庫へ誘導（クイックリプライ）
 *       4) いずれも無ければ既定の案内
 *   - follow（友だち追加）→ ウェルカム＋導線
 *
 * 設定：CARMEL_LINE_CHANNEL_SECRET / carmel_line_channel_secret（署名検証）
 *        CARMEL_LINE_CHANNEL_TOKEN  / carmel_line_channel_token（返信に使用）
 *        carmel_line_form_url（審査フォームのLIFF URL）, carmel_line_ai_endpoint（任意）
 *
 * @package CarmelCore
 */

defined( 'ABSPATH' ) || exit;

class Carmel_LINE_Bot {

	/** @var Carmel_LINE_Bot|null */
	private static $instance = null;

	const REST_NAMESPACE = 'carmel/v1';
	const REST_ROUTE     = '/line-webhook';
	const REPLY_ENDPOINT = 'https://api.line.me/v2/bot/message/reply';

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	const STATE_TTL    = 1800; // 会話状態の保持（秒）
	const START_DATA   = 'carmel_inquiry_start';

	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		// LINE 反響リードの通知（本部へ）。
		add_filter( 'carmel_routing_table', array( $this, 'add_routing' ) );
		add_filter( 'carmel_notification_message', array( $this, 'add_message' ), 10, 3 );
	}

	public function add_routing( $table ) {
		$table['line_lead'] = array(
			array( 'audience' => 'store', 'channel' => 'lineworks', 'fallback' => 'mail' ),
			array( 'audience' => 'hq', 'channel' => 'lineworks', 'fallback' => 'mail' ),
		);
		return $table;
	}

	public function add_message( $message, $event_type, $context ) {
		if ( 'line_lead' === $event_type ) {
			$vars = isset( $context['vars'] ) ? (array) $context['vars'] : array();
			$message['subject'] = 'LINEから新規反響';
			$message['body']    = "LINEのチャットから反響が入りました。\n氏名："
				. ( isset( $vars['name'] ) ? $vars['name'] : '' )
				. "\n電話：" . ( isset( $vars['phone'] ) ? $vars['phone'] : '' )
				. "\nエリア：" . ( isset( $vars['area'] ) ? $vars['area'] : '' )
				. "\n内容：" . ( isset( $vars['message'] ) ? $vars['message'] : '' );
		}
		return $message;
	}

	private function secret() {
		return defined( 'CARMEL_LINE_CHANNEL_SECRET' ) ? CARMEL_LINE_CHANNEL_SECRET : get_option( 'carmel_line_channel_secret', '' );
	}

	private function token() {
		return defined( 'CARMEL_LINE_CHANNEL_TOKEN' ) ? CARMEL_LINE_CHANNEL_TOKEN : get_option( 'carmel_line_channel_token', '' );
	}

	private function form_url() {
		$u = get_option( 'carmel_line_form_url', '' );
		return $u ? $u : home_url( '/' . ltrim( apply_filters( 'carmel_apply_page_slug', 'apply' ), '/' ) );
	}

	private function inventory_url() {
		return home_url( '/' . ltrim( apply_filters( 'carmel_inventory_page_slug', 'inventory' ), '/' ) );
	}

	private function ai_endpoint() {
		return (string) get_option( 'carmel_line_ai_endpoint', '' );
	}

	/* --------------------------------------------------------------------- *
	 * REST
	 * --------------------------------------------------------------------- */

	public function register_rest_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => array( $this, 'verify_signature' ),
			)
		);
	}

	/**
	 * X-Line-Signature を検証（チャネルシークレット未設定なら拒否＝オープン回避）。
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function verify_signature( $request ) {
		$secret = $this->secret();
		if ( '' === (string) $secret ) {
			return false;
		}
		$provided = $request->get_header( 'x-line-signature' );
		if ( ! $provided ) {
			return false;
		}
		$expected = base64_encode( hash_hmac( 'sha256', $request->get_body(), $secret, true ) );
		return hash_equals( $expected, (string) $provided );
	}

	/**
	 * Webhook 本処理。常に 200 を返す（検証イベントにも対応）。
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$body   = $request->get_json_params();
		$events = isset( $body['events'] ) && is_array( $body['events'] ) ? $body['events'] : array();

		foreach ( $events as $event ) {
			$type        = isset( $event['type'] ) ? $event['type'] : '';
			$reply_token = isset( $event['replyToken'] ) ? $event['replyToken'] : '';
			$user_id     = isset( $event['source']['userId'] ) ? $event['source']['userId'] : '';

			if ( 'message' === $type && isset( $event['message']['type'] ) && 'text' === $event['message']['type'] ) {
				$text = isset( $event['message']['text'] ) ? (string) $event['message']['text'] : '';
				// 会話型ヒアリング中なら最優先で処理。
				$conv = $this->advance_conversation( $user_id, $text );
				$reply = ( null !== $conv ) ? $conv : $this->resolve_reply( $text, $user_id );
				$this->reply( $reply_token, $reply );
				do_action( 'carmel_line_message', $user_id, $text );
			} elseif ( 'follow' === $type ) {
				$this->reply( $reply_token, $this->welcome_message() );
				do_action( 'carmel_line_follow', $user_id );
			} elseif ( 'postback' === $type ) {
				$data = isset( $event['postback']['data'] ) ? (string) $event['postback']['data'] : '';
				if ( self::START_DATA === $data ) {
					$this->reply( $reply_token, $this->start_conversation( $user_id ) );
				} else {
					$conv  = $this->advance_conversation( $user_id, $data );
					$reply = ( null !== $conv ) ? $conv : $this->resolve_reply( $data, $user_id );
					$this->reply( $reply_token, $reply );
				}
				do_action( 'carmel_line_postback', $user_id, $data );
			}
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	/* --------------------------------------------------------------------- *
	 * 応答の解決
	 * --------------------------------------------------------------------- */

	/**
	 * 受信テキストから返信メッセージ配列を作る。
	 *
	 * @param string $text
	 * @param string $user_id
	 * @return array LINE messages（最大5件）
	 */
	private function resolve_reply( $text, $user_id ) {
		$text = trim( $text );

		// 1) 審査/申込ワード → フォーム誘導（クイックリプライ）。
		if ( $this->matches( $text, array( '審査', '申込', '申し込み', 'ローン', '与信', 'シミュレーション' ) ) ) {
			return array( $this->guidance_message( 'ご希望ありがとうございます。下記から審査申込フォームへお進みください。' ) );
		}
		// 在庫ワード → 在庫カード（Flex）で提示。
		if ( $this->matches( $text, array( '在庫', '車', 'クルマ', '探', '見たい' ) ) ) {
			$cars = $this->recommend_vehicles( 0, 6 );
			if ( ! empty( $cars ) ) {
				return array(
					array( 'type' => 'text', 'text' => 'おすすめの在庫をご案内します。気になるお車は「詳細を見る」からどうぞ。' ),
					$this->flex_inventory( $cars, '在庫のご案内' ),
				);
			}
			return array( $this->text_with_quickreply( '現在ご案内できる在庫がありません。条件をお知らせください。', $this->menu_specs() ) );
		}

		// 2) AIエンドポイント（GAS/LLM）に委譲。
		$ai = $this->ask_ai( $text, $user_id );
		if ( '' !== $ai ) {
			return array( array( 'type' => 'text', 'text' => mb_substr( $ai, 0, 5000 ) ) );
		}

		// 3) 組み込みFAQ。
		foreach ( self::faqs() as $faq ) {
			if ( $this->matches( $text, $faq['keywords'] ) ) {
				return array( array( 'type' => 'text', 'text' => $faq['answer'] ) );
			}
		}

		// 4) 既定の案内。
		return array( $this->text_with_quickreply(
			apply_filters( 'carmel_line_default_reply', "お問い合わせありがとうございます。担当者が確認しご連絡します。\nお急ぎの場合は下記メニューもご利用ください。" ),
			$this->menu_specs()
		) );
	}

	/** AIエンドポイントに問い合わせ、返答テキストを得る（未設定/失敗時は空）。 */
	private function ask_ai( $text, $user_id ) {
		$endpoint = $this->ai_endpoint();
		if ( '' === $endpoint ) {
			return '';
		}
		$res = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 20,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( apply_filters( 'carmel_line_ai_payload', array(
					'message' => $text,
					'user_id' => $user_id,
					'context' => 'carmel-line-bot',
				), $text, $user_id ) ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return '';
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return '';
		}
		$json = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $json ) && isset( $json['reply'] ) ) {
			return (string) $json['reply'];
		}
		return '';
	}

	/** キーワードのいずれかを含むか。 */
	private function matches( $text, array $keywords ) {
		foreach ( $keywords as $k ) {
			if ( '' !== $k && false !== mb_stripos( $text, $k ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * 組み込みFAQ（フィルタで編集可）。
	 *
	 * @return array<int,array{keywords:array,answer:string}>
	 */
	public static function faqs() {
		return apply_filters(
			'carmel_line_faqs',
			array(
				array( 'keywords' => array( '営業時間', '何時', '定休' ), 'answer' => '営業時間・定休日は店舗により異なります。詳しくは公式サイトをご確認ください。' ),
				array( 'keywords' => array( '場所', '住所', 'アクセス', 'どこ' ), 'answer' => 'お近くの加盟店をご案内します。ご希望のエリアを教えてください。' ),
				array( 'keywords' => array( '見積', '価格', '値段', 'いくら' ), 'answer' => 'お見積りは在庫・ご希望条件により異なります。審査申込・お問い合わせから無料でご案内します。' ),
				array( 'keywords' => array( '保証', 'アフター' ), 'answer' => '保証プランをご用意しています（内容は車両・プランにより異なります）。詳細は担当よりご案内します。' ),
				array( 'keywords' => array( '納車', '陸送', '配送' ), 'answer' => '全国陸送に対応しています。陸送費は店舗〜納車先の距離で自動計算します。' ),
			)
		);
	}

	/* --------------------------------------------------------------------- *
	 * 会話型ヒアリング（氏名→電話→内容 → 反響起票）
	 * --------------------------------------------------------------------- */

	private function state_key( $user_id ) {
		return 'carmel_line_' . md5( (string) $user_id );
	}

	private function start_conversation( $user_id ) {
		if ( '' === (string) $user_id ) {
			return array( $this->guidance_message( 'ご相談ありがとうございます。フォームからお進みください。' ) );
		}
		set_transient( $this->state_key( $user_id ), array( 'step' => 'name', 'data' => array() ), self::STATE_TTL );
		return array( array( 'type' => 'text', 'text' => "ご相談ありがとうございます。\nお名前（フルネーム）を教えてください。（中止する場合は「キャンセル」）" ) );
	}

	/**
	 * 会話中なら次の質問へ進める。会話中でなければ null（通常応答へ）。
	 *
	 * @param string $user_id
	 * @param string $text
	 * @return array|null  返信メッセージ群、または null
	 */
	private function advance_conversation( $user_id, $text ) {
		if ( '' === (string) $user_id ) {
			return null;
		}
		$state = get_transient( $this->state_key( $user_id ) );
		if ( ! is_array( $state ) || empty( $state['step'] ) ) {
			return null;
		}
		$text = trim( $text );

		// 中止。
		if ( $this->matches( $text, array( 'キャンセル', 'やめる', '中止' ) ) ) {
			delete_transient( $this->state_key( $user_id ) );
			return array( array( 'type' => 'text', 'text' => '承知しました。受付を中止しました。' ) );
		}

		$data = isset( $state['data'] ) ? (array) $state['data'] : array();

		switch ( $state['step'] ) {
			case 'name':
				$data['name']  = sanitize_text_field( $text );
				$state['step'] = 'phone';
				$state['data'] = $data;
				set_transient( $this->state_key( $user_id ), $state, self::STATE_TTL );
				return array( array( 'type' => 'text', 'text' => 'ありがとうございます。ご連絡先（電話番号）を教えてください。' ) );

			case 'phone':
				$data['phone'] = sanitize_text_field( $text );
				$state['step'] = 'area';
				$state['data'] = $data;
				set_transient( $this->state_key( $user_id ), $state, self::STATE_TTL );
				return array( $this->text_with_quickreply(
					'ご希望のエリア（お住まい／納車先）を選んでください。',
					$this->area_specs()
				) );

			case 'area':
				$area = preg_replace( '/^area:/', '', $text );
				$data['area']  = sanitize_text_field( $area );
				$state['step'] = 'message';
				$state['data'] = $data;
				set_transient( $this->state_key( $user_id ), $state, self::STATE_TTL );
				return array( array( 'type' => 'text', 'text' => 'ご希望・ご質問の内容を教えてください。（ご希望の車種・予算・年式など）' ) );

			case 'message':
				$data['message'] = sanitize_textarea_field( $text );
				delete_transient( $this->state_key( $user_id ) );
				$store_id = $this->create_lead( $user_id, $data );

				$messages = array( array( 'type' => 'text', 'text' => "ありがとうございます。受け付けました。\n担当よりLINEまたはお電話でご連絡します。" ) );
				// 車種候補（担当店の在庫を優先して提示）。
				$cars = $this->recommend_vehicles( $store_id, 6 );
				if ( ! empty( $cars ) ) {
					$messages[] = $this->flex_inventory( $cars, 'ご希望に近い在庫のご案内' );
				} else {
					$messages[] = $this->text_with_quickreply( '在庫からもお探しいただけます。', $this->menu_specs() );
				}
				return $messages;
		}
		return null;
	}

	/** エリア一覧（クイックリプライ用・フィルタで編集可）。 */
	public static function regions() {
		return apply_filters(
			'carmel_line_regions',
			array( '北海道', '東北', '関東', '中部', '近畿', '中国・四国', '九州・沖縄', 'その他' )
		);
	}

	private function area_specs() {
		$out = array();
		foreach ( self::regions() as $r ) {
			$out[] = array( 'postback', $r, 'area:' . $r );
		}
		return $out;
	}

	/**
	 * LINE 反響を carmel_support として記録し、エリアで担当加盟店へ割当・通知。
	 *
	 * @param string $user_id
	 * @param array  $data [ name, phone, area, message ]
	 * @return int 割り当てた担当店 store_id（無ければ0）
	 */
	private function create_lead( $user_id, array $data ) {
		$name  = isset( $data['name'] ) ? $data['name'] : '';
		$area  = isset( $data['area'] ) ? $data['area'] : '';
		$store = $this->route_store_for_area( $area );

		$post_id = wp_insert_post(
			array(
				'post_type'   => 'carmel_support',
				'post_status' => 'publish',
				'post_title'  => 'LINE反響：' . ( $name ? $name : substr( (string) $user_id, 0, 12 ) ),
				'meta_input'  => array(
					'support_type'    => 'line_inquiry',
					'line_user_id'    => sanitize_text_field( (string) $user_id ),
					'applicant_name'  => $name,
					'applicant_phone' => isset( $data['phone'] ) ? $data['phone'] : '',
					'area'            => $area,
					'store_id'        => (int) $store,
					'message'         => isset( $data['message'] ) ? $data['message'] : '',
					'created_at'      => current_time( 'mysql' ),
				),
			)
		);

		// 本部へ＋担当店が決まればその店舗へも通知。
		$ctx = array(
			'event_id' => 'line_lead:' . ( is_wp_error( $post_id ) ? md5( $user_id . microtime() ) : (int) $post_id ),
			'vars'     => array(
				'name'    => $name,
				'phone'   => isset( $data['phone'] ) ? $data['phone'] : '',
				'area'    => $area,
				'message' => isset( $data['message'] ) ? $data['message'] : '',
			),
		);
		if ( $store ) {
			$ctx['store_id'] = (int) $store;
		}
		Carmel_Notifier::notify( 'line_lead', $ctx );

		do_action( 'carmel_line_lead_created', is_wp_error( $post_id ) ? 0 : (int) $post_id, $user_id, $data, $store );
		return (int) $store;
	}

	/**
	 * エリア → 担当加盟店 store_id を解決。
	 * `carmel_area_store_map`（option/filter）の area=>store_id（配列なら先頭）を参照。
	 *
	 * @param string $area
	 * @return int store_id（無ければ0）
	 */
	private function route_store_for_area( $area ) {
		if ( '' === (string) $area ) {
			return 0;
		}
		$map = apply_filters( 'carmel_area_store_map', get_option( 'carmel_area_store_map', array() ), $area );
		if ( ! is_array( $map ) || ! isset( $map[ $area ] ) ) {
			return 0;
		}
		$val = $map[ $area ];
		if ( is_array( $val ) ) {
			$val = reset( $val ); // 複数店舗が紐づく場合は先頭（運用で調整可）。
		}
		return (int) $val;
	}

	/* --------------------------------------------------------------------- *
	 * 在庫レコメンド（Flex Message）
	 * --------------------------------------------------------------------- */

	/**
	 * 公開・販売可能な在庫を取得（担当店があれば優先し、不足分を全体で補完）。
	 *
	 * @param int $store_id 優先する店舗（0=指定なし）
	 * @param int $limit
	 * @return WP_Post[]
	 */
	private function recommend_vehicles( $store_id = 0, $limit = 6 ) {
		$sellable = class_exists( 'Carmel_Inventory' ) ? Carmel_Inventory::sellable_statuses() : array( '販売中', '商談中' );
		$base = array(
			'post_type'      => 'carmel_vehicle',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => array(
				'relation' => 'AND',
				array( 'key' => 'published', 'value' => array( '1', 'yes', 'true' ), 'compare' => 'IN' ),
				array( 'key' => 'vehicle_status', 'value' => $sellable, 'compare' => 'IN' ),
			),
		);

		$cars = array();
		if ( $store_id ) {
			$q = $base;
			$q['meta_query'][] = array( 'key' => 'store_id', 'value' => (int) $store_id );
			$cars = get_posts( $q );
		}
		if ( count( $cars ) < $limit ) {
			$exclude = wp_list_pluck( $cars, 'ID' );
			$q = $base;
			$q['posts_per_page'] = $limit - count( $cars );
			if ( $exclude ) {
				$q['post__not_in'] = $exclude;
			}
			$cars = array_merge( $cars, get_posts( $q ) );
		}
		return $cars;
	}

	/**
	 * 在庫カードの Flex（carousel）メッセージを作る。
	 *
	 * @param WP_Post[] $vehicles
	 * @param string    $alt
	 * @return array LINE message（type=flex）
	 */
	private function flex_inventory( array $vehicles, $alt = '在庫のご案内' ) {
		$inv_url = $this->inventory_url();
		$bubbles = array();
		foreach ( array_slice( $vehicles, 0, 10 ) as $v ) {
			$maker = (string) get_post_meta( $v->ID, 'maker', true );
			$model = (string) get_post_meta( $v->ID, 'model', true );
			$title = trim( $maker . ' ' . $model );
			$title = '' !== $title ? $title : get_the_title( $v->ID );
			$price = (float) get_post_meta( $v->ID, 'price', true );
			$year  = get_post_meta( $v->ID, 'year', true );
			$mile  = get_post_meta( $v->ID, 'mileage', true );
			$img   = has_post_thumbnail( $v->ID ) ? get_the_post_thumbnail_url( $v->ID, 'large' ) : '';
			$url   = add_query_arg( 'vehicle', (int) $v->ID, $inv_url );

			$spec = array();
			if ( $year ) {
				$spec[] = $year . '年';
			}
			if ( '' !== (string) $mile ) {
				$spec[] = number_format( (float) $mile ) . 'km';
			}

			$body = array(
				array( 'type' => 'text', 'text' => mb_substr( $title, 0, 40 ), 'weight' => 'bold', 'size' => 'md', 'wrap' => true ),
				array( 'type' => 'text', 'text' => '¥' . number_format( $price ) . '（税込）', 'size' => 'sm', 'color' => '#6b4fbb', 'margin' => 'sm' ),
			);
			if ( $spec ) {
				$body[] = array( 'type' => 'text', 'text' => implode( '｜', $spec ), 'size' => 'xs', 'color' => '#888888', 'margin' => 'sm' );
			}

			$bubble = array(
				'type' => 'bubble',
				'size' => 'micro',
				'body' => array( 'type' => 'box', 'layout' => 'vertical', 'contents' => $body ),
				'footer' => array(
					'type'     => 'box',
					'layout'   => 'vertical',
					'contents' => array(
						array( 'type' => 'button', 'style' => 'primary', 'color' => '#6b4fbb', 'height' => 'sm',
							'action' => array( 'type' => 'uri', 'label' => '詳細を見る', 'uri' => $url ) ),
					),
				),
			);
			// 画像は https のみ有効。あればヒーローに。
			if ( $img && 0 === strpos( $img, 'https://' ) ) {
				$bubble['hero'] = array(
					'type' => 'image', 'url' => $img, 'size' => 'full',
					'aspectRatio' => '20:13', 'aspectMode' => 'cover',
					'action' => array( 'type' => 'uri', 'uri' => $url ),
				);
				$bubble['size'] = 'kilo';
			}
			$bubbles[] = $bubble;
		}

		// 末尾に「もっと見る」バブル。
		$bubbles[] = array(
			'type' => 'bubble', 'size' => 'micro',
			'body' => array( 'type' => 'box', 'layout' => 'vertical', 'justifyContent' => 'center', 'contents' => array(
				array( 'type' => 'button', 'style' => 'secondary', 'height' => 'sm',
					'action' => array( 'type' => 'uri', 'label' => '在庫一覧へ', 'uri' => $inv_url ) ),
			) ),
		);

		return array(
			'type'     => 'flex',
			'altText'  => $alt,
			'contents' => array( 'type' => 'carousel', 'contents' => $bubbles ),
		);
	}

	/* --------------------------------------------------------------------- *
	 * メッセージ生成・返信
	 * --------------------------------------------------------------------- */

	/** 標準メニュー（URI＋チャット相談postback）の quickReply 仕様。 */
	private function menu_specs() {
		return array(
			array( 'uri', '在庫を見る', $this->inventory_url() ),
			array( 'uri', '審査申込', $this->form_url() ),
			array( 'postback', 'チャットで相談', self::START_DATA ),
		);
	}

	private function welcome_message() {
		return $this->text_with_quickreply(
			apply_filters( 'carmel_line_welcome', "友だち追加ありがとうございます！\nカーメルです。お車探し・審査申込・お問い合わせをこちらで承ります。" ),
			$this->menu_specs()
		);
	}

	private function guidance_message( $lead ) {
		return $this->text_with_quickreply(
			$lead,
			array(
				array( 'uri', '審査フォームを開く', $this->form_url() ),
				array( 'uri', '在庫を見る', $this->inventory_url() ),
				array( 'postback', 'チャットで相談', self::START_DATA ),
			)
		);
	}

	/**
	 * テキスト＋クイックリプライ。
	 *
	 * @param string $text
	 * @param array  $specs [ [type('uri'|'postback'), label, value], ... ]
	 * @return array
	 */
	private function text_with_quickreply( $text, array $specs ) {
		$items = array();
		foreach ( $specs as $s ) {
			$type  = isset( $s[0] ) ? $s[0] : 'uri';
			$label = isset( $s[1] ) ? mb_substr( $s[1], 0, 20 ) : '';
			$value = isset( $s[2] ) ? $s[2] : '';
			if ( 'postback' === $type ) {
				$action = array( 'type' => 'postback', 'label' => $label, 'data' => $value, 'displayText' => $label );
			} else {
				$action = array( 'type' => 'uri', 'label' => $label, 'uri' => $value );
			}
			$items[] = array( 'type' => 'action', 'action' => $action );
		}
		$msg = array( 'type' => 'text', 'text' => $text );
		if ( $items ) {
			$msg['quickReply'] = array( 'items' => array_slice( $items, 0, 13 ) );
		}
		return $msg;
	}

	/**
	 * reply API で返信。
	 *
	 * @param string $reply_token
	 * @param array  $messages 単一 or 複数（messages配列）
	 */
	private function reply( $reply_token, $messages ) {
		$token = $this->token();
		if ( '' === (string) $token || '' === (string) $reply_token ) {
			return;
		}
		// 単一メッセージは配列に包む。
		if ( isset( $messages['type'] ) ) {
			$messages = array( $messages );
		}
		$messages = array_slice( $messages, 0, 5 );

		wp_remote_post(
			self::REPLY_ENDPOINT,
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( array( 'replyToken' => $reply_token, 'messages' => array_values( $messages ) ) ),
			)
		);
	}
}
