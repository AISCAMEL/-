<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * 管理画面メニュー登録
 */
add_action( 'admin_menu', 'carmel_cb_admin_menu' );
function carmel_cb_admin_menu() {
	add_menu_page(
		'チャットボット',
		'チャットボット',
		'manage_options',
		'carmel-cb',
		'carmel_cb_page_router',
		'dashicons-format-chat',
		58
	);
	add_submenu_page( 'carmel-cb', '基本設定', '基本設定', 'manage_options', 'carmel-cb', 'carmel_cb_page_router' );
	add_submenu_page( 'carmel-cb', '応答・人格', '応答・人格', 'manage_options', 'carmel-cb&tab=prompt', 'carmel_cb_page_router' );
	add_submenu_page( 'carmel-cb', '学習データ(FAQ)', '学習データ(FAQ)', 'manage_options', 'carmel-cb&tab=faq', 'carmel_cb_page_router' );
	add_submenu_page( 'carmel-cb', '申込・問い合わせ', '申込・問い合わせ', 'manage_options', 'carmel-cb&tab=leads', 'carmel_cb_page_router' );
	add_submenu_page( 'carmel-cb', '会話ログ', '会話ログ', 'manage_options', 'carmel-cb&tab=logs', 'carmel_cb_page_router' );
	add_submenu_page( 'carmel-cb', '見た目設定', '見た目設定', 'manage_options', 'carmel-cb&tab=appearance', 'carmel_cb_page_router' );
	add_submenu_page( 'carmel-cb', 'FAQ一括投入', 'FAQ一括投入', 'manage_options', 'carmel-cb&tab=faqimport', 'carmel_cb_page_router' );
}

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( strpos( $hook, 'carmel-cb' ) === false ) { return; }
	wp_enqueue_style( 'carmel-cb-admin', CARMEL_CB_URL . 'admin/admin.css', array(), CARMEL_CB_VERSION );
	wp_enqueue_media(); // 担当者アイコンをメディアから選ぶため
} );

/**
 * ページ振り分け＋POST処理
 */
function carmel_cb_page_router() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	carmel_cb_handle_post();

	$tab = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
	echo '<div class="wrap carmel-cb-wrap">';
	echo '<h1>🤖 カーメル AIチャットボット</h1>';
	carmel_cb_render_notices();
	carmel_cb_render_tabs( $tab );

	switch ( $tab ) {
		case 'prompt':     carmel_cb_view_prompt(); break;
		case 'faq':        carmel_cb_view_faq(); break;
		case 'faqimport':  carmel_cb_view_faqimport(); break;
		case 'leads':      carmel_cb_view_leads(); break;
		case 'logs':       carmel_cb_view_logs(); break;
		case 'appearance': carmel_cb_view_appearance(); break;
		default:           carmel_cb_view_general();
	}
	echo '</div>';
}

function carmel_cb_render_tabs( $active ) {
	$tabs = array(
		'general'    => '基本設定',
		'prompt'     => '応答・人格',
		'faq'        => '学習データ(FAQ)',
		'faqimport'  => 'FAQ一括投入',
		'leads'      => '申込・問い合わせ',
		'logs'       => '会話ログ',
		'appearance' => '見た目設定',
	);
	echo '<nav class="nav-tab-wrapper">';
	foreach ( $tabs as $key => $label ) {
		$url = admin_url( 'admin.php?page=carmel-cb' . ( $key === 'general' ? '' : '&tab=' . $key ) );
		$cls = ( $active === $key ) ? ' nav-tab-active' : '';
		printf( '<a href="%s" class="nav-tab%s">%s</a>', esc_url( $url ), esc_attr( $cls ), esc_html( $label ) );
	}
	echo '</nav>';
}

/**
 * 全フォームのPOSTを処理
 */
function carmel_cb_handle_post() {
	if ( empty( $_POST['carmel_cb_action'] ) ) { return; }
	check_admin_referer( 'carmel_cb_nonce' );
	$action = sanitize_key( $_POST['carmel_cb_action'] );

	switch ( $action ) {
		case 'save_general':
			$days_in = isset( $_POST['biz_days'] ) && is_array( $_POST['biz_days'] )
				? array_filter( array_map( 'intval', $_POST['biz_days'] ), function ( $d ) { return $d >= 0 && $d <= 6; } )
				: array();
			carmel_cb_update_settings( array(
				'enabled'         => isset( $_POST['enabled'] ) ? 1 : 0,
				'api_key'         => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
				'model'           => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
				'max_tokens'      => max( 100, min( 2000, (int) ( $_POST['max_tokens'] ?? 500 ) ) ),
				'log_enabled'     => isset( $_POST['log_enabled'] ) ? 1 : 0,
				'handoff_enabled' => isset( $_POST['handoff_enabled'] ) ? 1 : 0,
				'biz_start'       => max( 0, min( 23, (int) ( $_POST['biz_start'] ?? 10 ) ) ),
				'biz_end'         => max( 1, min( 24, (int) ( $_POST['biz_end'] ?? 19 ) ) ),
				'biz_days'        => $days_in ? implode( ',', $days_in ) : '0,1,2,3,4,5,6',
				'notify_email'    => sanitize_email( wp_unslash( $_POST['notify_email'] ?? '' ) ),
				'operator_name'       => sanitize_text_field( wp_unslash( $_POST['operator_name'] ?? '担当者' ) ),
				'operator_avatar_url' => esc_url_raw( wp_unslash( $_POST['operator_avatar_url'] ?? '' ) ),
				'chat_start_notify'   => isset( $_POST['chat_start_notify'] ) ? 1 : 0,
				'intake_on'           => isset( $_POST['intake_on'] ) ? 1 : 0,
				'intake_msg'          => sanitize_textarea_field( wp_unslash( $_POST['intake_msg'] ?? '' ) ),
				'intake_after_msg'    => sanitize_textarea_field( wp_unslash( $_POST['intake_after_msg'] ?? '' ) ),
				'handoff_wait_msg' => sanitize_textarea_field( wp_unslash( $_POST['handoff_wait_msg'] ?? '' ) ),
				'handoff_busy_sec' => max( 3, min( 120, (int) ( $_POST['handoff_busy_sec'] ?? 15 ) ) ),
				'handoff_busy_msg' => sanitize_textarea_field( wp_unslash( $_POST['handoff_busy_msg'] ?? '' ) ),
				'handoff_wait_sec' => max( 5, min( 300, (int) ( $_POST['handoff_wait_sec'] ?? 45 ) ) ),
				'handoff_offhours_msg' => sanitize_textarea_field( wp_unslash( $_POST['handoff_offhours_msg'] ?? '' ) ),
				'slack_webhook'   => esc_url_raw( wp_unslash( $_POST['slack_webhook'] ?? '' ) ),
				'slack_bot_token' => sanitize_text_field( wp_unslash( $_POST['slack_bot_token'] ?? '' ) ),
				'slack_channel'   => sanitize_text_field( wp_unslash( $_POST['slack_channel'] ?? '' ) ),
				'lw_client_id'       => sanitize_text_field( wp_unslash( $_POST['lw_client_id'] ?? '' ) ),
				'lw_client_secret'   => sanitize_text_field( wp_unslash( $_POST['lw_client_secret'] ?? '' ) ),
				'lw_service_account' => sanitize_text_field( wp_unslash( $_POST['lw_service_account'] ?? '' ) ),
				'lw_private_key'     => trim( (string) wp_unslash( $_POST['lw_private_key'] ?? '' ) ),
				'lw_bot_id'          => sanitize_text_field( wp_unslash( $_POST['lw_bot_id'] ?? '' ) ),
				'lw_channel_id'      => sanitize_text_field( wp_unslash( $_POST['lw_channel_id'] ?? '' ) ),
			) );
			carmel_cb_notice( '基本設定を保存しました。' );
			break;

		case 'slack_test':
			carmel_cb_run_slack_test();
			break;

		case 'save_prompt':
			carmel_cb_update_settings( array(
				'system_prompt' => sanitize_textarea_field( wp_unslash( $_POST['system_prompt'] ?? '' ) ),
			) );
			carmel_cb_notice( '応答・人格を保存しました。' );
			break;

		case 'save_appearance':
			carmel_cb_update_settings( array(
				'bot_name'      => sanitize_text_field( wp_unslash( $_POST['bot_name'] ?? '' ) ),
				'welcome_msg'   => sanitize_textarea_field( wp_unslash( $_POST['welcome_msg'] ?? '' ) ),
				'auto_open_sec' => max( 0, min( 120, (int) ( $_POST['auto_open_sec'] ?? 10 ) ) ),
				'primary_color' => sanitize_hex_color( wp_unslash( $_POST['primary_color'] ?? '#0b5cab' ) ),
				'line_url'      => esc_url_raw( wp_unslash( $_POST['line_url'] ?? '' ) ),
				'stock_page_url'=> esc_url_raw( wp_unslash( $_POST['stock_page_url'] ?? '' ) ),
				'tel'             => sanitize_text_field( wp_unslash( $_POST['tel'] ?? '' ) ),
				'apply_page_id'   => (int) ( $_POST['apply_page_id'] ?? 0 ),
				'contact_page_id' => (int) ( $_POST['contact_page_id'] ?? 0 ),
				'apply_url'       => esc_url_raw( wp_unslash( $_POST['apply_url'] ?? '' ) ),
				'contact_url'     => esc_url_raw( wp_unslash( $_POST['contact_url'] ?? '' ) ),
				'cta_mode'        => ( ( $_POST['cta_mode'] ?? 'form' ) === 'link' ) ? 'link' : 'form',
				'apply_form_on'   => isset( $_POST['apply_form_on'] ) ? 1 : 0,
				'contact_form_on' => isset( $_POST['contact_form_on'] ) ? 1 : 0,
				'consent_text'    => sanitize_textarea_field( wp_unslash( $_POST['consent_text'] ?? '' ) ),
				'consent_url'     => esc_url_raw( wp_unslash( $_POST['consent_url'] ?? '' ) ),
			) );
			carmel_cb_notice( '見た目設定を保存しました。' );
			break;

		case 'delete_lead':
			carmel_cb_delete_lead( (int) ( $_POST['lead_id'] ?? 0 ) );
			carmel_cb_notice( '受付データを削除しました。' );
			break;

		case 'save_faq':
			$faq_id   = (int) ( $_POST['faq_id'] ?? 0 );
			$question = sanitize_textarea_field( wp_unslash( $_POST['question'] ?? '' ) );
			// 新規追加時のみ重複チェック（「重複でも追加」未チェックなら止める）
			if ( $faq_id === 0 && empty( $_POST['force_dup'] ) ) {
				$dup = carmel_cb_find_similar_faq( $question );
				if ( $dup ) {
					carmel_cb_notice(
						'似た学習データが既にあります → 「' . mb_substr( $dup->question, 0, 50 ) . '」（#' . $dup->id . '）。'
						. '重複を避けるため、内容を修正するか既存FAQを編集してください。どうしても追加する場合は「似たFAQがあっても追加する」にチェックして再度お試しください。',
						'error'
					);
					break;
				}
			}
			carmel_cb_save_faq( array(
				'id'       => $faq_id,
				'question' => $question,
				'answer'   => sanitize_textarea_field( wp_unslash( $_POST['answer'] ?? '' ) ),
				'keywords' => sanitize_text_field( wp_unslash( $_POST['keywords'] ?? '' ) ),
				'enabled'  => isset( $_POST['faq_enabled'] ),
			) );
			carmel_cb_notice( 'FAQ（学習データ）を保存しました。' );
			break;

		case 'delete_faq':
			carmel_cb_delete_faq( (int) ( $_POST['faq_id'] ?? 0 ) );
			carmel_cb_notice( 'FAQを削除しました。' );
			break;

		case 'add_faq_from_log':
			$q = sanitize_textarea_field( wp_unslash( $_POST['q'] ?? '' ) );
			$a = sanitize_textarea_field( wp_unslash( $_POST['a'] ?? '' ) );
			if ( $q === '' || $a === '' ) {
				carmel_cb_notice( '質問・回答が空のため追加しませんでした。', 'warning' );
				break;
			}
			if ( empty( $_POST['force_dup'] ) ) {
				$dup = carmel_cb_find_similar_faq( $q );
				if ( $dup ) {
					carmel_cb_notice(
						'似た学習データが既にあります → 「' . mb_substr( $dup->question, 0, 50 ) . '」（#' . $dup->id . '）。'
						. '重複を避けるため、内容を修正してから追加してください。どうしても追加する場合は「似たFAQがあっても追加する」にチェックしてください。',
						'error'
					);
					break;
				}
			}
			carmel_cb_save_faq( array(
				'question' => $q,
				'answer'   => $a,
				'keywords' => sanitize_text_field( wp_unslash( $_POST['k'] ?? '' ) ),
				'enabled'  => true,
			) );
			carmel_cb_notice( '会話からFAQに追加しました（学習データ(FAQ)で確認できます）。' );
			break;

		case 'import_faq':
			$r = carmel_cb_handle_import();
			carmel_cb_notice( sprintf(
				'一括投入：%d行を読み込み → %d件を追加しました%s。',
				isset( $r['read'] ) ? $r['read'] : 0,
				$r['added'],
				$r['replaced'] ? '（※既存FAQは全削除しました）' : ''
			) );
			break;

		case 'import_site':
			$r = carmel_cb_handle_site_import();
			carmel_cb_notice( sprintf( 'サイト取り込み：%dページから%d件の学習データを追加しました。', $r['pages'], $r['added'] ) );
			break;
	}
}

/**
 * サイトの固定ページ本文を学習データ(FAQ)へ取り込む。
 * スクレイピングではなくWPのDBから直接読むため正確。長文はチャンク分割。
 */
function carmel_cb_handle_site_import() {
	$ids = ( isset( $_POST['import_pages'] ) && is_array( $_POST['import_pages'] ) )
		? array_map( 'intval', $_POST['import_pages'] ) : array();
	$pages = 0; $added = 0;
	foreach ( $ids as $id ) {
		$p = get_post( $id );
		if ( ! $p || $p->post_status !== 'publish' ) { continue; }
		$text = wp_strip_all_tags( apply_filters( 'the_content', $p->post_content ) );
		$text = trim( preg_replace( '/\n{2,}/u', "\n", preg_replace( '/[ \t]+/u', ' ', (string) $text ) ) );
		if ( $text === '' ) { continue; }
		$pages++;
		$chunks = carmel_cb_chunk_text( $text, 500, 8 );
		foreach ( $chunks as $i => $chunk ) {
			carmel_cb_save_faq( array(
				'question' => $p->post_title . ( count( $chunks ) > 1 ? '（' . ( $i + 1 ) . '）' : '' ),
				'answer'   => $chunk,
				'keywords' => $p->post_title,
				'enabled'  => true,
			) );
			$added++;
		}
	}
	return array( 'pages' => $pages, 'added' => $added );
}

/** 文字列を約 $size 文字ずつ最大 $max 個に分割。 */
function carmel_cb_chunk_text( $text, $size, $max ) {
	$out = array();
	$len = mb_strlen( $text );
	for ( $i = 0; $i < $len && count( $out ) < $max; $i += $size ) {
		$out[] = trim( mb_substr( $text, $i, $size ) );
	}
	return array_values( array_filter( $out, function ( $c ) { return $c !== ''; } ) );
}

/**
 * FAQ一括投入の処理（テキスト貼り付け／CSV・TSVファイル）。
 * 1行 = 質問[タブ or カンマ]回答[タブ or カンマ]キーワード(任意)。
 */
function carmel_cb_handle_import() {
	$raw = (string) wp_unslash( $_POST['bulk_text'] ?? '' );

	if ( ! empty( $_FILES['bulk_file']['tmp_name'] ) && is_uploaded_file( $_FILES['bulk_file']['tmp_name'] ) ) {
		$content = file_get_contents( $_FILES['bulk_file']['tmp_name'] );
		if ( $content !== false ) {
			$raw .= ( $raw !== '' ? "\n" : '' ) . $content;
		}
	}

	$raw = preg_replace( '/^\xEF\xBB\xBF/', '', $raw ); // BOM除去
	$raw = str_replace( "\r\n", "\n", $raw );
	$raw = str_replace( "\r", "\n", $raw );
	if ( trim( $raw ) === '' ) { return array( 'added' => 0, 'read' => 0, 'replaced' => false ); }

	$replace = ! empty( $_POST['replace_all'] );
	if ( $replace ) { carmel_cb_delete_all_faqs(); }

	// 区切り文字を自動判定（タブがあればTSV、無ければCSV）
	$delim = ( strpos( $raw, "\t" ) !== false ) ? "\t" : ',';

	// 引用符・改行入りセルに正しく対応するため fgetcsv で解析
	$fh = fopen( 'php://temp', 'r+' );
	fwrite( $fh, $raw );
	rewind( $fh );

	$rows = array();
	while ( ( $cols = fgetcsv( $fh, 0, $delim ) ) !== false ) {
		if ( $cols === array( null ) ) { continue; } // 空行
		$rows[] = $cols;
	}
	fclose( $fh );
	$read = count( $rows );

	// 列のマッピング：見出し行（質問/回答/キーワード/ジャンル等）があれば名前で対応づけ
	$map = array( 'q' => 0, 'a' => 1, 'k' => 2, 'g' => -1 );
	if ( ! empty( $rows ) ) {
		$head = array_map( function ( $x ) { return mb_strtolower( trim( (string) $x ) ); }, $rows[0] );
		$find = function ( $cands ) use ( $head ) {
			foreach ( $head as $i => $h ) { if ( in_array( $h, $cands, true ) ) { return $i; } }
			return -1;
		};
		$qi = $find( array( '質問', 'question', 'q' ) );
		$ai = $find( array( '回答', 'answer', 'a', '答え' ) );
		$ki = $find( array( 'キーワード', 'keyword', 'keywords', 'タグ', 'tag' ) );
		$gi = $find( array( 'ジャンル', 'カテゴリ', 'カテゴリー', 'category', 'genre' ) );
		if ( $qi !== -1 && $ai !== -1 ) {
			$map = array( 'q' => $qi, 'a' => $ai, 'k' => $ki, 'g' => $gi );
			array_shift( $rows ); // 見出し行を除外
		} elseif ( in_array( mb_strtolower( trim( (string) ( $rows[0][0] ?? '' ) ) ), array( '質問', 'question', 'q' ), true ) ) {
			array_shift( $rows ); // 単純な見出し行のみ除外
		}
	}

	$added = 0;
	foreach ( $rows as $cols ) {
		$q = trim( (string) ( $cols[ $map['q'] ] ?? '' ) );
		$a = trim( (string) ( $cols[ $map['a'] ] ?? '' ) );
		$k = ( $map['k'] >= 0 ) ? trim( (string) ( $cols[ $map['k'] ] ?? '' ) ) : '';
		if ( $map['g'] >= 0 ) {
			$g = trim( (string) ( $cols[ $map['g'] ] ?? '' ) );
			if ( $g !== '' ) { $k = ( $k !== '' ) ? ( $g . ', ' . $k ) : $g; } // ジャンルをキーワードに加える
		}
		if ( $q === '' || $a === '' ) { continue; }
		carmel_cb_save_faq( array(
			'question' => sanitize_textarea_field( $q ),
			'answer'   => sanitize_textarea_field( $a ),
			'keywords' => sanitize_text_field( $k ),
			'enabled'  => true,
		) );
		$added++;
	}

	return array( 'added' => $added, 'read' => $read, 'replaced' => $replace );
}

/** FAQ一括投入の画面 */
function carmel_cb_view_faqimport() {
	$count = count( carmel_cb_get_faqs( false ) );
	?>
	<div class="ccb-card">
		<h2>FAQ一括投入（AIにまとめて学習）</h2>
		<p>スプレッドシート（Excel / Googleスプレッドシート）で <strong>「質問」「回答」「キーワード(任意)」</strong> の列を作り、
		その範囲をコピーして下の枠に貼り付けて「一括投入する」を押すと、まとめて登録できます。<br>
		<span class="description">1行＝1つのQ&A。スプレッドシートからのコピペは自動でタブ区切りになります。CSV（カンマ区切り）も可。<br>
		<strong>1行目に見出し（例：ジャンル／質問／回答／キーワード）を付けると、列の順番が違っても自動で正しく振り分けます。</strong>「ジャンル（カテゴリ）」列があれば、その語もキーワードに加えて検索精度を高めます。</span></p>

		<form method="post" enctype="multipart/form-data">
			<?php wp_nonce_field( 'carmel_cb_nonce' ); ?>
			<input type="hidden" name="carmel_cb_action" value="import_faq">

			<p><strong>① 貼り付けで投入</strong></p>
			<textarea name="bulk_text" rows="12" class="large-text code" placeholder="頭金は必要ですか？	頭金の有無は審査内容により異なります。月々のご希望額をお聞きしご案内します。	頭金,予算
他社で断られたけど大丈夫？	他社で難しかった方のご相談も多くいただいています。まずはご相談ください。	審査,他社"></textarea>

			<p style="margin-top:14px;"><strong>② または CSVファイルで投入</strong>
				<input type="file" name="bulk_file" accept=".csv,.tsv,.txt">
				<span class="description">列の順番：質問, 回答, キーワード（UTF-8推奨）</span>
			</p>

			<p style="margin-top:10px;">
				<label><input type="checkbox" name="replace_all" value="1"> 既存のFAQをすべて削除してから投入する（全入れ替え）</label>
				<br><span class="description">チェックしないと「追加」されます。現在の登録件数：<strong><?php echo (int) $count; ?>件</strong></span>
			</p>

			<?php submit_button( '一括投入する' ); ?>
		</form>
		<hr>
		<p class="description">投入後は「学習データ(FAQ)」タブで内容を確認・編集・削除できます。</p>
	</div>

	<div class="ccb-card">
		<h2>サイトのページから取り込む（自動学習）</h2>
		<p>ホームページの<strong>固定ページ</strong>の内容を、そのままAIの学習データに取り込みます（スクレイピング不要・WordPressから直接読み取り）。<br>
		<span class="description">会社概要・ローンのご案内・よくある質問など、AIに覚えさせたいページを選んで取り込んでください。長い記事は自動で分割します。取り込み後は「学習データ(FAQ)」で編集できます。</span></p>
		<form method="post">
			<?php wp_nonce_field( 'carmel_cb_nonce' ); ?>
			<input type="hidden" name="carmel_cb_action" value="import_site">
			<div style="max-height:320px;overflow:auto;border:1px solid #e3e8ee;border-radius:6px;padding:10px;background:#fff;">
				<?php
				$pages = get_pages( array( 'sort_column' => 'menu_order,post_title', 'number' => 300 ) );
				if ( empty( $pages ) ) {
					echo '<p class="description">公開中の固定ページが見つかりませんでした。</p>';
				} else {
					foreach ( $pages as $pg ) {
						printf(
							'<label style="display:block;margin:3px 0;"><input type="checkbox" name="import_pages[]" value="%d"> %s</label>',
							(int) $pg->ID,
							esc_html( $pg->post_title )
						);
					}
				}
				?>
			</div>
			<p class="description" style="margin-top:6px;">※ 取り込みは「追加」です。古い内容が変わったら、再取り込み前に「学習データ(FAQ)」で古い行を消すか、一括投入の「全入れ替え」をご利用ください。</p>
			<?php submit_button( '選択したページを取り込む' ); ?>
		</form>
	</div>
	<?php
}

function carmel_cb_notice( $msg, $type = 'success' ) {
	$cls = in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? 'notice-' . $type : 'notice-success';
	// admin_notices フックは既に発火済みのため使わず、ページ内で直接描画する（確実に表示させる）
	if ( ! isset( $GLOBALS['carmel_cb_notices'] ) ) { $GLOBALS['carmel_cb_notices'] = array(); }
	$GLOBALS['carmel_cb_notices'][] = array( $msg, $cls );
}

/** 蓄積した通知をページ上部に描画する。 */
function carmel_cb_render_notices() {
	if ( empty( $GLOBALS['carmel_cb_notices'] ) ) { return; }
	foreach ( $GLOBALS['carmel_cb_notices'] as $n ) {
		echo '<div class="notice ' . esc_attr( $n[1] ) . ' is-dismissible"><p>' . esc_html( $n[0] ) . '</p></div>';
	}
	$GLOBALS['carmel_cb_notices'] = array();
}

function carmel_cb_form_open( $action ) {
	echo '<form method="post">';
	wp_nonce_field( 'carmel_cb_nonce' );
	echo '<input type="hidden" name="carmel_cb_action" value="' . esc_attr( $action ) . '">';
}

/* =========================================================
 * 各タブのビュー
 * ========================================================= */

/** 基本設定 */
function carmel_cb_view_general() {
	$s = carmel_cb_get_settings();
	$models = carmel_cb_model_choices();
	?>
	<div class="ccb-card">
		<?php carmel_cb_form_open( 'save_general' ); ?>
		<table class="form-table">
			<tr>
				<th>チャットボットを有効化</th>
				<td><label><input type="checkbox" name="enabled" <?php checked( $s['enabled'], 1 ); ?>> サイトに表示する</label></td>
			</tr>
			<tr>
				<th>OpenRouter APIキー</th>
				<td>
					<input type="password" name="api_key" value="<?php echo esc_attr( $s['api_key'] ); ?>" class="regular-text" autocomplete="off" placeholder="sk-or-v1-...">
					<p class="description">openrouter.ai の Keys ページで発行したキーを貼り付けてください。</p>
				</td>
			</tr>
			<tr>
				<th>使用モデル</th>
				<td>
					<select name="model">
						<?php foreach ( $models as $val => $label ) : ?>
							<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $s['model'], $val ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description">コスト最優先なら一番上の Gemini 2.0 Flash を推奨。</p>
				</td>
			</tr>
			<tr>
				<th>最大応答トークン</th>
				<td><input type="number" name="max_tokens" value="<?php echo esc_attr( $s['max_tokens'] ); ?>" min="100" max="2000" step="50"> <span class="description">短いほど安く速い（目安500）</span></td>
			</tr>
			<tr>
				<th>会話ログを保存</th>
				<td><label><input type="checkbox" name="log_enabled" <?php checked( $s['log_enabled'], 1 ); ?>> 会話を記録して改善に使う</label></td>
			</tr>
			<tr><th colspan="2"><hr><strong>有人対応（担当者へ通知）</strong></th></tr>
			<tr>
				<th>有人対応を有効化</th>
				<td><label><input type="checkbox" name="handoff_enabled" <?php checked( $s['handoff_enabled'] ?? 1, 1 ); ?>> 「担当者に相談」ボタンを表示する</label></td>
			</tr>
			<tr>
				<th>チャット開始を通知</th>
				<td>
					<label><input type="checkbox" name="chat_start_notify" <?php checked( $s['chat_start_notify'] ?? 1, 1 ); ?>> 訪問者がチャットを始めたら Slack（通知先）へお知らせする</label>
					<p class="description">お客様が最初のメッセージを送った時点で、Slack（Bot設定済みならそのチャンネル。未設定ならWebhook）やLINE WORKSに「チャットが始まりました」と通知します。1セッション1回のみ。</p>
					<?php $csd = get_option( 'carmel_cb_chatstart_debug' ); if ( $csd ) : ?>
						<p class="description" style="background:#f6f7f7;border-left:3px solid #72aee6;padding:6px 10px;margin-top:6px">
							<strong>診断（直近のチャット開始通知）：</strong><br><?php echo esc_html( $csd ); ?>
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th>開始時にお名前・メール確認</th>
					<td>
						<label><input type="checkbox" name="intake_on" <?php checked( $s['intake_on'] ?? 1, 1 ); ?>> チャット開始時に、まずお名前とメールアドレスを入力してもらう</label>
						<p class="description">「誰から問い合わせが来たか」が分かるよう、会話の前にお名前・メールを確認します。入力後に「本日はどのようなご用件でしょうか？」へ進みます。Slack（Bot設定済み）にはお客様情報がスレッド冒頭に表示されます。</p>
						<p style="margin:8px 0 4px"><label>案内メッセージ（入力前）</label></p>
						<textarea name="intake_msg" rows="2" class="large-text"><?php echo esc_textarea( $s['intake_msg'] ?? '' ); ?></textarea>
						<p style="margin:8px 0 4px"><label>入力後のメッセージ（<code>{name}</code> はお名前に置き換わります）</label></p>
						<textarea name="intake_after_msg" rows="2" class="large-text"><?php echo esc_textarea( $s['intake_after_msg'] ?? '' ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th>営業時間</th>
				<td>
					<input type="number" name="biz_start" value="<?php echo esc_attr( $s['biz_start'] ?? 10 ); ?>" min="0" max="23" class="small-text"> 時
					〜
					<input type="number" name="biz_end" value="<?php echo esc_attr( $s['biz_end'] ?? 19 ); ?>" min="1" max="24" class="small-text"> 時
					<span class="description">（WordPressのタイムゾーン設定に従います）</span>
				</td>
			</tr>
			<tr>
				<th>営業曜日</th>
				<td>
					<?php $days = explode( ',', (string) ( $s['biz_days'] ?? '0,1,2,3,4,5,6' ) ); $names = array( '日','月','火','水','木','金','土' ); ?>
					<?php foreach ( $names as $i => $nm ) : ?>
						<label style="margin-right:8px"><input type="checkbox" name="biz_days[]" value="<?php echo $i; ?>" <?php checked( in_array( (string) $i, $days, true ) ); ?>> <?php echo esc_html( $nm ); ?></label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th>現在の判定（確認用）</th>
				<td>
					<?php
					$now_within = carmel_cb_within_hours( $s );
					$tz_str     = wp_timezone_string();
					$now_disp   = current_time( 'Y-m-d H:i' );
					$dow_names  = array( '日','月','火','水','木','金','土' );
					$dow_now    = $dow_names[ (int) current_time( 'w' ) ];
					?>
					<p style="margin:0 0 6px">
						<strong style="color:<?php echo $now_within ? '#1c7a3a' : '#b32d2e'; ?>">
							ただいま：<?php echo $now_within ? '営業時間内' : '営業時間外'; ?>
						</strong>
					</p>
					<p class="description" style="margin:0">
						WordPressの現在時刻：<code><?php echo esc_html( $now_disp ); ?>（<?php echo esc_html( $dow_now ); ?>）</code>／
						タイムゾーン：<code><?php echo esc_html( $tz_str ? $tz_str : '未設定(UTC)' ); ?></code><br>
						ここが日本時間とズレている場合は <a href="<?php echo esc_url( admin_url( 'options-general.php' ) ); ?>">設定 → 一般 → タイムゾーン</a> を <strong>「東京」</strong>にしてください（これがUTCのままだと、営業時間内でも「時間外」と判定されます）。
					</p>
				</td>
			</tr>
			<tr>
				<th>通知先メール</th>
				<td><input type="email" name="notify_email" value="<?php echo esc_attr( $s['notify_email'] ?? '' ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>（空なら管理者メール）"></td>
			</tr>
			<tr>
				<th>担当者の表示名</th>
				<td>
					<input type="text" name="operator_name" value="<?php echo esc_attr( $s['operator_name'] ?? '担当者' ); ?>" class="regular-text" placeholder="担当者">
					<p class="description">チャットで担当者の返信に表示される名前（例：スタッフ／山田 など）。</p>
				</td>
			</tr>
			<tr>
				<th>担当者アイコン画像</th>
				<td>
					<?php $op_av = $s['operator_avatar_url'] ?? ''; ?>
					<p style="margin:0 0 8px">
						<button type="button" class="button" id="ccb-pick-avatar">🖼 メディアから選択</button>
						<button type="button" class="button-link" id="ccb-clear-avatar" style="margin-left:10px;color:#b32d2e;<?php echo $op_av ? '' : 'display:none'; ?>">画像を外す</button>
					</p>
					<span id="ccb-avatar-preview-wrap" style="<?php echo $op_av ? '' : 'display:none'; ?>">
						<img id="ccb-avatar-preview" src="<?php echo esc_url( $op_av ); ?>" alt="" style="width:56px;height:56px;border-radius:50%;object-fit:cover;border:1px solid #ddd;vertical-align:middle">
					</span>
					<input type="url" name="operator_avatar_url" id="ccb-avatar-url" value="<?php echo esc_attr( $op_av ); ?>" class="regular-text" placeholder="（メディアから選択するか、画像URLを貼り付け）" style="margin-top:8px">
					<p class="description">
						「メディアから選択」を押して写真を選ぶと、確実に設定できます（URLの手入力・貼り間違いを防げます）。空欄なら人物アイコン（🧑‍💼）を表示します。
					</p>
					<script>
					jQuery(function($){
						var frame;
						$('#ccb-pick-avatar').on('click', function(e){
							e.preventDefault();
							if (frame) { frame.open(); return; }
							frame = wp.media({ title:'担当者アイコンを選択', button:{ text:'この画像を使う' }, multiple:false, library:{ type:'image' } });
							frame.on('select', function(){
								var a = frame.state().get('selection').first().toJSON();
								var url = (a.sizes && a.sizes.medium) ? a.sizes.medium.url : ((a.sizes && a.sizes.thumbnail) ? a.sizes.thumbnail.url : a.url);
								$('#ccb-avatar-url').val(url);
								$('#ccb-avatar-preview').attr('src', url);
								$('#ccb-avatar-preview-wrap').show();
								$('#ccb-clear-avatar').show();
							});
							frame.open();
						});
						$('#ccb-clear-avatar').on('click', function(e){
							e.preventDefault();
							$('#ccb-avatar-url').val('');
							$('#ccb-avatar-preview-wrap').hide();
							$(this).hide();
						});
						$('#ccb-avatar-url').on('input', function(){
							var v = $(this).val();
							if (v) { $('#ccb-avatar-preview').attr('src', v); $('#ccb-avatar-preview-wrap').show(); $('#ccb-clear-avatar').show(); }
							else { $('#ccb-avatar-preview-wrap').hide(); $('#ccb-clear-avatar').hide(); }
						});
					});
					</script>
				</td>
			</tr>
			<tr>
				<th>つなぐ間の案内</th>
				<td>
					<input type="text" name="handoff_wait_msg" value="<?php echo esc_attr( $s['handoff_wait_msg'] ?? '' ); ?>" class="large-text">
					<p class="description">「担当者に相談」を押した直後に表示する待機メッセージ。</p>
				</td>
			</tr>
			<tr>
				<th>混雑案内までの秒数</th>
				<td>
					<input type="number" name="handoff_busy_sec" value="<?php echo esc_attr( $s['handoff_busy_sec'] ?? 15 ); ?>" min="3" max="120" class="small-text"> 秒
					<p class="description">この秒数つながらなければ、下の「混雑時の案内」を表示します（既定15秒）。</p>
					<input type="text" name="handoff_busy_msg" value="<?php echo esc_attr( $s['handoff_busy_msg'] ?? '' ); ?>" class="large-text" style="margin-top:6px">
					<p class="description">混雑時の案内メッセージ。</p>
				</td>
			</tr>
			<tr>
				<th>フォーム切替までの秒数</th>
				<td>
					<input type="number" name="handoff_wait_sec" value="<?php echo esc_attr( $s['handoff_wait_sec'] ?? 45 ); ?>" min="5" max="300" class="small-text"> 秒
					<p class="description">最終的にここまで担当者につながらなければ、連絡先フォーム（折り返し）に切り替えます（既定45秒）。</p>
				</td>
			</tr>
			<tr>
				<th>営業時間外の案内</th>
				<td>
					<textarea name="handoff_offhours_msg" rows="3" class="large-text"><?php echo esc_textarea( $s['handoff_offhours_msg'] ?? '' ); ?></textarea>
					<p class="description">営業時間外に「担当者に相談」を押したときの案内。担当者にはつながず、<strong>AIが自動で対応を続けます</strong>（希望者だけ「翌営業日の折り返し」を選べます）。上の営業時間・営業曜日の<strong>外</strong>のときに表示されます。</p>
				</td>
			</tr>
			<tr>
				<th>Slack通知（任意・一方向）</th>
				<td>
					<input type="url" name="slack_webhook" value="<?php echo esc_attr( $s['slack_webhook'] ?? '' ); ?>" class="regular-text" placeholder="https://hooks.slack.com/services/...">
					<p class="description">Slackの「Incoming Webhook」URL。メールに加えてSlackにも通知します（任意）。</p>
				</td>
			</tr>

			<tr><th colspan="2"><hr><strong>Slack 双方向（担当者がSlackで返信→チャットに表示）</strong></th></tr>
			<tr>
				<th>Slack Botトークン</th>
				<td>
					<input type="password" name="slack_bot_token" value="<?php echo esc_attr( $s['slack_bot_token'] ?? '' ); ?>" class="regular-text" autocomplete="off" placeholder="xoxb-...">
					<p class="description">権限 <code>chat:write</code> と <code>channels:history</code>（プライベートは <code>groups:history</code>）。Botを対象チャンネルに招待してください。設定すると営業時間内はライブ対応になります。</p>
				</td>
			</tr>
			<tr>
				<th>SlackチャンネルID</th>
				<td>
					<input type="text" name="slack_channel" value="<?php echo esc_attr( $s['slack_channel'] ?? '' ); ?>" class="regular-text" placeholder="C0123ABCD">
					<p class="description"><strong>大事：</strong>入力後に「保存する」→ 下の「Slack接続をテスト」で確認できます。うまく行かない時は、Slackで対象チャンネルを開き <code>/invite @Bot名</code> でBotを招待してください。</p>
				</td>
			</tr>

			<tr><th colspan="2"><hr><strong>LINE WORKS 通知（任意）</strong></th></tr>
			<tr><th>Client ID</th><td><input type="text" name="lw_client_id" value="<?php echo esc_attr( $s['lw_client_id'] ?? '' ); ?>" class="regular-text"></td></tr>
			<tr><th>Client Secret</th><td><input type="password" name="lw_client_secret" value="<?php echo esc_attr( $s['lw_client_secret'] ?? '' ); ?>" class="regular-text" autocomplete="off"></td></tr>
			<tr><th>Service Account</th><td><input type="text" name="lw_service_account" value="<?php echo esc_attr( $s['lw_service_account'] ?? '' ); ?>" class="regular-text" placeholder="xxxx.serviceaccount@example"></td></tr>
			<tr><th>Bot ID</th><td><input type="text" name="lw_bot_id" value="<?php echo esc_attr( $s['lw_bot_id'] ?? '' ); ?>" class="regular-text"></td></tr>
			<tr><th>送信先トークルームID</th><td><input type="text" name="lw_channel_id" value="<?php echo esc_attr( $s['lw_channel_id'] ?? '' ); ?>" class="regular-text"></td></tr>
			<tr>
				<th>秘密鍵(PEM)</th>
				<td>
					<textarea name="lw_private_key" rows="4" class="large-text code" placeholder="-----BEGIN PRIVATE KEY-----&#10;...&#10;-----END PRIVATE KEY-----"><?php echo esc_textarea( $s['lw_private_key'] ?? '' ); ?></textarea>
					<p class="description">LINE WORKS Developer Console で発行したサービスアカウントの秘密鍵。全項目を入れると、担当者希望時にLINE WORKSへ通知します。</p>
				</td>
			</tr>
		</table>
		<?php submit_button( '保存する' ); ?>
		</form>

		<hr>
		<h3 style="margin:8px 0">Slack接続テスト</h3>
		<p class="description" style="margin:0 0 8px">保存済みのBotトークン・チャンネルIDで、実際にテスト投稿してみます。失敗した場合は原因（招待漏れ・権限不足など）を表示します。</p>
		<form method="post">
			<?php wp_nonce_field( 'carmel_cb_nonce' ); ?>
			<input type="hidden" name="carmel_cb_action" value="slack_test">
			<?php submit_button( 'Slack接続をテスト', 'secondary', 'submit', false ); ?>
		</form>
	</div>
	<?php
}

/** 応答・人格 */
function carmel_cb_view_prompt() {
	$s = carmel_cb_get_settings();
	?>
	<div class="ccb-card">
		<p>ここでボットの<strong>性格・話し方・守るべきルール</strong>を決めます。下のFAQ（学習データ）と合わせて使われます。</p>
		<?php carmel_cb_form_open( 'save_prompt' ); ?>
		<textarea name="system_prompt" rows="20" class="large-text code"><?php echo esc_textarea( $s['system_prompt'] ); ?></textarea>
		<p class="description">※ 金利や審査可否を断定させない指示は、コンプライアンス上そのまま残すことを推奨します。</p>
		<?php submit_button( '保存する' ); ?>
		</form>
	</div>
	<?php
}

/** 学習データ FAQ */
function carmel_cb_view_faq() {
	$faqs = carmel_cb_get_faqs();
	$edit = null;
	if ( isset( $_GET['edit'] ) ) {
		foreach ( $faqs as $f ) { if ( (int) $f->id === (int) $_GET['edit'] ) { $edit = $f; } }
	}
	?>
	<div class="ccb-card">
		<h2><?php echo $edit ? 'FAQを編集' : 'FAQを追加'; ?></h2>
		<p class="description">ここに追加した内容がボットの「知識」になります。質問に近いFAQが自動で参照され、回答の根拠になります。</p>
		<?php carmel_cb_form_open( 'save_faq' ); ?>
		<input type="hidden" name="faq_id" value="<?php echo esc_attr( $edit->id ?? 0 ); ?>">
		<table class="form-table">
			<tr>
				<th>質問</th>
				<td><textarea name="question" rows="2" class="large-text" required><?php echo esc_textarea( $edit->question ?? '' ); ?></textarea></td>
			</tr>
			<tr>
				<th>回答</th>
				<td><textarea name="answer" rows="4" class="large-text" required><?php echo esc_textarea( $edit->answer ?? '' ); ?></textarea></td>
			</tr>
			<tr>
				<th>キーワード</th>
				<td>
					<input type="text" name="keywords" value="<?php echo esc_attr( $edit->keywords ?? '' ); ?>" class="large-text" placeholder="審査, 頭金, 納車（カンマ区切り）">
					<p class="description">この語が質問に含まれると、このFAQが優先的に参照されます。</p>
				</td>
			</tr>
			<tr>
				<th>有効</th>
				<td><label><input type="checkbox" name="faq_enabled" <?php checked( $edit->enabled ?? 1, 1 ); ?>> このFAQを使う</label></td>
			</tr>
			<?php if ( ! $edit ) : ?>
			<tr>
				<th>重複時</th>
				<td><label><input type="checkbox" name="force_dup" value="1"> 似たFAQがあっても追加する</label>
				<p class="description">通常は似た学習データがあると警告して止めます。あえて追加する場合のみチェック。</p></td>
			</tr>
			<?php endif; ?>
		</table>
		<?php submit_button( $edit ? '更新する' : '追加する' ); ?>
		<?php if ( $edit ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=carmel-cb&tab=faq' ) ); ?>" class="button">キャンセル</a>
		<?php endif; ?>
		</form>
	</div>

	<div class="ccb-card">
		<h2>登録済みFAQ（<?php echo count( $faqs ); ?>件）</h2>
		<table class="widefat striped">
			<thead><tr><th>質問</th><th>キーワード</th><th>状態</th><th>操作</th></tr></thead>
			<tbody>
			<?php if ( empty( $faqs ) ) : ?>
				<tr><td colspan="4">まだFAQがありません。上のフォームから追加してください。</td></tr>
			<?php else : foreach ( $faqs as $f ) : ?>
				<tr>
					<td><?php echo esc_html( wp_trim_words( $f->question, 20 ) ); ?></td>
					<td><?php echo esc_html( $f->keywords ); ?></td>
					<td><?php echo $f->enabled ? '<span style="color:#2a7">有効</span>' : '<span style="color:#999">無効</span>'; ?></td>
					<td>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=carmel-cb&tab=faq&edit=' . $f->id ) ); ?>" class="button button-small">編集</a>
						<form method="post" style="display:inline" onsubmit="return confirm('削除しますか？');">
							<?php wp_nonce_field( 'carmel_cb_nonce' ); ?>
							<input type="hidden" name="carmel_cb_action" value="delete_faq">
							<input type="hidden" name="faq_id" value="<?php echo esc_attr( $f->id ); ?>">
							<button class="button button-small button-link-delete">削除</button>
						</form>
					</td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/** 会話ログ */
/** 申込・お問い合わせ（チャット内フォームの受付一覧） */
function carmel_cb_view_leads() {
	$leads = carmel_cb_get_leads( 200 );
	?>
	<div class="ccb-card">
		<h2>申込・お問い合わせ</h2>
		<p class="description">チャット内フォーム（かんたん審査・お問い合わせ）から届いたお客様の連絡先です。届いた内容はメール／Slack／LINE WORKS にも自動通知されます。</p>
		<?php if ( empty( $leads ) ) : ?>
			<p>まだ受付はありません。</p>
		<?php else : ?>
			<table class="widefat striped" style="margin-top:10px">
				<thead>
					<tr>
						<th style="width:130px">受付日時</th>
						<th style="width:70px">種別</th>
						<th>お名前</th>
						<th>ご連絡先</th>
						<th>ご希望・ご相談</th>
						<th style="width:60px"></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $leads as $l ) :
						$is_apply = ( $l->type === 'apply' );
						$contact  = trim( $l->tel . ( $l->tel && $l->email ? ' / ' : '' ) . $l->email );
						$detail   = trim( (string) $l->wish . ( $l->wish && $l->note ? "\n" : '' ) . (string) $l->note );
					?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'y/m/d H:i', $l->created_at ) ); ?></td>
						<td><span style="display:inline-block;padding:2px 8px;border-radius:999px;color:#fff;font-size:11px;font-weight:700;background:<?php echo $is_apply ? '#0b5cab' : '#5a6b7b'; ?>"><?php echo $is_apply ? '審査' : '問合'; ?></span></td>
						<td><?php echo esc_html( $l->name ); ?></td>
						<td><?php echo nl2br( esc_html( $contact ) ); ?></td>
						<td><?php echo nl2br( esc_html( $detail ) ); ?><?php if ( $l->page ) : ?><br><a href="<?php echo esc_url( $l->page ); ?>" target="_blank" rel="noopener" class="description" style="font-size:11px">受付ページ↗</a><?php endif; ?></td>
						<td>
							<form method="post" onsubmit="return confirm('この受付データを削除しますか？');" style="margin:0">
								<?php wp_nonce_field( 'carmel_cb_nonce' ); ?>
								<input type="hidden" name="carmel_cb_action" value="delete_lead">
								<input type="hidden" name="lead_id" value="<?php echo (int) $l->id; ?>">
								<button type="submit" class="button-link delete" style="color:#b32d2e">削除</button>
							</form>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<p class="description" style="margin-top:12px">※ 受け方（チャット内フォーム／外部ページ）や表示ボタンは <a href="<?php echo esc_url( admin_url( 'admin.php?page=carmel-cb&tab=appearance' ) ); ?>">見た目設定</a> で変更できます。通知先メールは <a href="<?php echo esc_url( admin_url( 'admin.php?page=carmel-cb' ) ); ?>">基本設定</a>。</p>
	</div>
	<?php
}

function carmel_cb_view_logs() {
	$view = isset( $_GET['session'] ) ? sanitize_text_field( $_GET['session'] ) : '';
	if ( $view ) {
		$msgs = carmel_cb_get_session_messages( $view );
		echo '<div class="ccb-card"><a href="' . esc_url( admin_url( 'admin.php?page=carmel-cb&tab=logs' ) ) . '" class="button">← 一覧へ戻る</a>';
		echo '<h2>会話内容</h2>';
		echo '<p class="description">お客様の質問とAIの回答を、そのまま「学習データ(FAQ)」に追加できます（内容は編集してから追加可）。</p>';
		echo '<div class="ccb-log-thread">';
		$arr = is_array( $msgs ) ? array_values( $msgs ) : array();
		foreach ( $arr as $idx => $m ) {
			$who = $m->role === 'user' ? 'お客様' : 'AI';
			$cls = $m->role === 'user' ? 'user' : 'bot';
			echo '<div class="ccb-log-msg ' . esc_attr( $cls ) . '"><strong>' . esc_html( $who ) . '</strong><span>' . esc_html( $m->content ) . '</span><em>' . esc_html( $m->created_at ) . '</em></div>';

			// お客様の発話 → 直後のAI回答 を FAQ化するフォーム
			if ( $m->role === 'user' ) {
				$ans = '';
				for ( $j = $idx + 1; $j < count( $arr ); $j++ ) {
					if ( $arr[ $j ]->role === 'assistant' ) { $ans = $arr[ $j ]->content; break; }
				}
				if ( $ans !== '' ) {
					echo '<form method="post" class="ccb-log2faq">';
					wp_nonce_field( 'carmel_cb_nonce' );
					echo '<input type="hidden" name="carmel_cb_action" value="add_faq_from_log">';
					echo '<details><summary>＋ この質問と回答をFAQに追加</summary>';
					echo '<label>質問</label><textarea name="q" rows="2" class="large-text">' . esc_textarea( $m->content ) . '</textarea>';
					echo '<label>回答</label><textarea name="a" rows="3" class="large-text">' . esc_textarea( $ans ) . '</textarea>';
					echo '<label>キーワード(任意)</label><input type="text" name="k" class="large-text" placeholder="審査, 頭金 など">';
					echo '<p><label><input type="checkbox" name="force_dup" value="1"> 似たFAQがあっても追加する</label></p>';
					echo '<p><button class="button button-primary">FAQに追加する</button></p>';
					echo '</details></form>';
				}
			}
		}
		echo '</div></div>';
		return;
	}

	$sessions = carmel_cb_get_log_sessions( 100 );
	?>
	<div class="ccb-card">
		<h2>会話セッション一覧</h2>
		<p class="description">お客様がどんな質問をしているかを確認し、FAQやプロンプトの改善に役立ててください。</p>
		<table class="widefat striped">
			<thead><tr><th>開始日時</th><th>メッセージ数</th><th>操作</th></tr></thead>
			<tbody>
			<?php if ( empty( $sessions ) ) : ?>
				<tr><td colspan="3">まだ会話ログがありません。</td></tr>
			<?php else : foreach ( $sessions as $sx ) : ?>
				<tr>
					<td><?php echo esc_html( $sx->started ); ?></td>
					<td><?php echo (int) $sx->msgs; ?></td>
					<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=carmel-cb&tab=logs&session=' . urlencode( $sx->session_id ) ) ); ?>" class="button button-small">表示</a></td>
				</tr>
			<?php endforeach; endif; ?>
			</tbody>
		</table>
	</div>
	<?php
}

/** 見た目設定 */
function carmel_cb_view_appearance() {
	$s = carmel_cb_get_settings();
	?>
	<div class="ccb-card">
		<?php carmel_cb_form_open( 'save_appearance' ); ?>
		<table class="form-table">
			<tr>
				<th>ボット名</th>
				<td><input type="text" name="bot_name" value="<?php echo esc_attr( $s['bot_name'] ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th>最初のあいさつ</th>
				<td><textarea name="welcome_msg" rows="3" class="large-text"><?php echo esc_textarea( $s['welcome_msg'] ); ?></textarea></td>
			</tr>
			<tr>
				<th>チャットの開き方</th>
				<td>
					<input type="hidden" name="auto_open_sec" value="0">
					<p class="description"><strong>右下アイコンをタップした時だけ開きます</strong>（時間経過での自動オープンは廃止しました）。</p>
				</td>
			</tr>
			<tr>
				<th>テーマカラー</th>
				<td><input type="color" name="primary_color" value="<?php echo esc_attr( $s['primary_color'] ); ?>"></td>
			</tr>
			<tr>
				<th>LINE相談URL</th>
				<td><input type="url" name="line_url" value="<?php echo esc_attr( $s['line_url'] ); ?>" class="regular-text"></td>
			</tr>
			<tr>
				<th>在庫一覧ページURL</th>
				<td>
					<input type="url" name="stock_page_url" value="<?php echo esc_attr( $s['stock_page_url'] ?? '' ); ?>" class="regular-text" placeholder="https://carmelonline.jp/search/">
					<p class="description">在庫にピッタリが無いとき、AIがこのページを案内します。</p>
				</td>
			</tr>
			<tr>
				<th>電話番号（任意）</th>
				<td>
					<input type="text" name="tel" value="<?php echo esc_attr( $s['tel'] ?? '' ); ?>" class="regular-text" placeholder="050-1793-5554">
					<p class="description">入力すると、CTA時に「電話で相談」ボタンが表示されます。空欄ならLINEのみ。</p>
				</td>
			</tr>
			<tr>
				<th style="padding-top:22px"><strong>CTA（審査・問い合わせ）の受け方</strong></th>
				<td style="padding-top:22px">
					<?php $cta_mode = ( $s['cta_mode'] ?? 'form' ) === 'link' ? 'link' : 'form'; ?>
					<label style="display:block;margin-bottom:6px">
						<input type="radio" name="cta_mode" value="form" <?php checked( $cta_mode, 'form' ); ?>>
						<strong>チャット内フォームで受け付ける（推奨）</strong> ― お客様は離脱せず、その場で氏名・連絡先を送信。担当者に自動通知されます。
					</label>
					<label style="display:block">
						<input type="radio" name="cta_mode" value="link" <?php checked( $cta_mode, 'link' ); ?>>
						外部ページに飛ばす（従来）― 下の「ページID / URL」の固定ページを新しいタブで開きます。
					</label>
					<div style="margin-top:10px">
						<label style="margin-right:16px"><input type="checkbox" name="apply_form_on" value="1" <?php checked( ! empty( $s['apply_form_on'] ) ); ?>> 「📝 かんたん審査」ボタンを出す</label>
						<label><input type="checkbox" name="contact_form_on" value="1" <?php checked( ! empty( $s['contact_form_on'] ) ); ?>> 「✉️ お問い合わせ」ボタンを出す</label>
						<p class="description">※「チャット内フォーム」を選んだときの表示ボタンです。送信内容は<strong>「申込・問い合わせ」タブ</strong>と、担当者通知先（基本設定のメール / Slack / LINE WORKS）に届きます。</p>
					</div>
				</td>
			</tr>
			<tr>
				<th>同意文（フォーム下）</th>
				<td>
					<input type="text" name="consent_text" value="<?php echo esc_attr( $s['consent_text'] ?? '' ); ?>" class="large-text">
					<input type="url" name="consent_url" value="<?php echo esc_attr( $s['consent_url'] ?? '' ); ?>" class="regular-text" placeholder="プライバシーポリシーURL（任意）" style="margin-top:6px">
					<p class="description">フォーム送信前のチェックに表示します。空欄にすると同意チェックを省略します。</p>
				</td>
			</tr>
			<tr>
				<th>審査申込ページID</th>
				<td>
					<input type="number" name="apply_page_id" value="<?php echo esc_attr( $s['apply_page_id'] ?? 0 ); ?>" class="small-text" min="0">
					<p class="description">「外部ページに飛ばす」を選んだ場合の飛び先（固定ページのID）。編集URLの <code>post=○○○○</code> の数字。0で非表示。</p>
				</td>
			</tr>
			<tr>
				<th>お問い合わせページID</th>
				<td>
					<input type="number" name="contact_page_id" value="<?php echo esc_attr( $s['contact_page_id'] ?? 0 ); ?>" class="small-text" min="0">
					<p class="description">CTAの「お問い合わせ」ボタンの飛び先（固定ページのID）。0で非表示。</p>
				</td>
			</tr>
			<tr>
				<th>URL直指定（任意）</th>
				<td>
					<input type="url" name="apply_url" value="<?php echo esc_attr( $s['apply_url'] ?? '' ); ?>" class="regular-text" placeholder="審査申込URL（IDが使えない場合）"><br>
					<input type="url" name="contact_url" value="<?php echo esc_attr( $s['contact_url'] ?? '' ); ?>" class="regular-text" placeholder="お問い合わせURL（IDが使えない場合）" style="margin-top:6px">
					<p class="description">上のページIDが使えない場合のみ、URLを直接指定できます。</p>
				</td>
			</tr>
		</table>
		<?php submit_button( '保存する' ); ?>
		</form>
	</div>

	<?php
	// 共有リンク・埋め込みコード
	$link_url = function_exists( 'carmel_cb_standalone_url' ) ? carmel_cb_standalone_url() : home_url( '/?carmel_chat=1' );
	$iframe   = '<iframe src="' . esc_url( $link_url ) . '" style="width:100%;max-width:420px;height:640px;border:0;border-radius:16px;" title="' . esc_attr( $s['bot_name'] ) . '"></iframe>';
	?>
	<div class="ccb-card">
		<h2>共有リンク・埋め込み</h2>
		<p class="description">チャットを「直リンク」で共有したり、ページや外部サイトに「埋め込み」できます。</p>

		<table class="form-table">
			<tr>
				<th>① 直リンク（共有用URL）</th>
				<td>
					<input type="text" readonly onclick="this.select()" value="<?php echo esc_attr( $link_url ); ?>" class="large-text code">
					<p class="description">
						このURLを開くと<strong>チャットだけが全画面</strong>で開きます。LINE・メール・QRコード・SNSプロフィール等でそのまま配布できます。
						<a href="<?php echo esc_url( $link_url ); ?>" target="_blank" rel="noopener">別タブで開いて確認 →</a>
					</p>
				</td>
			</tr>
			<tr>
				<th>② 埋め込み（ショートコード）</th>
				<td>
					<input type="text" readonly onclick="this.select()" value="[carmel_chat]" class="regular-text code">
					<p class="description">
						固定ページや投稿の本文にこのショートコードを貼ると、その場所にチャットが表示されます。<br>
						高さ・幅も指定できます：<code>[carmel_chat height="720" width="100%"]</code>
					</p>
				</td>
			</tr>
			<tr>
				<th>③ 外部サイトに貼る（iframe）</th>
				<td>
					<textarea readonly onclick="this.select()" rows="3" class="large-text code"><?php echo esc_textarea( $iframe ); ?></textarea>
					<p class="description">他社サイトやブログなど、WordPress以外のページにはこの <code>&lt;iframe&gt;</code> タグを貼り付けてください。</p>
				</td>
			</tr>
			<tr>
				<th>④ 他サイトに“浮くボタン”で設置（おすすめ）</th>
				<td>
					<?php $embed_js = function_exists( 'carmel_cb_embed_script_url' ) ? carmel_cb_embed_script_url() : home_url( '/?carmel_chat=embed' ); ?>
					<textarea readonly onclick="this.select()" rows="2" class="large-text code">&lt;script src="<?php echo esc_url( $embed_js ); ?>" defer&gt;&lt;/script&gt;</textarea>
					<p class="description">
						この<strong>スクリプト1行</strong>を他サイトのHTML（フッター等）に貼るだけで、そのサイトの右下に相談ボタンが出て、<strong>ページ内でチャットが開きます</strong>（iフレーム不使用なので「真っ白」になりません）。<br>
						WordPressなら「HTMLブロック」やヘッダー/フッター挿入プラグイン、その他サイトなら&lt;/body&gt;直前に貼ってください。
					</p>
				</td>
			</tr>
		</table>
	</div>
	<?php
}
