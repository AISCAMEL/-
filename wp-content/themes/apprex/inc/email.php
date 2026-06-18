<?php
/**
 * HTML email templating + test-send / preview admin tools.
 *
 * 文面（コピー）は forms.php がプレーンテキストで定義し、本ファイルが
 * ブランド付きのレスポンシブ HTML シェルに包んで送ります。
 *  - apprex_render_email()      … プレーン本文 → 完成 HTML メール
 *  - apprex_email_wrap()        … 任意の HTML 本文 → 完成 HTML メール
 *  - 管理画面「APPREX メール」   … テスト送信＋全メールのプレビュー
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * テンプレート・ヘルパー
 * ---------------------------------------------------------------------- */

/** メールのブランドカラー。 */
function apprex_email_color() {
	return '#2563eb';
}

/** 件名の先頭【…】を外して見出し用テキストにする。 */
function apprex_email_heading_from_subject( $subject ) {
	return trim( preg_replace( '/^【[^】]*】/u', '', (string) $subject ) );
}

/** 既知の内部 URL を分かりやすいボタン文言にマッピング。 */
function apprex_email_button_label( $url ) {
	$map = array(
		'/free-trial'  => '無料体験を始める',
		'/estimate'    => 'かんたん見積り（1分）',
		'/cases'       => '導入事例を見る',
		'/hp-creation' => 'ホームページ制作を見る',
		'calendar.app.google' => 'Google Meet で予約する',
		'lin.ee'       => 'LINE で相談する',
	);
	foreach ( $map as $needle => $label ) {
		if ( false !== strpos( $url, $needle ) ) {
			return $label;
		}
	}
	return '詳しく見る';
}

/** CTA ボタン（メール安全な table ベース）。 */
function apprex_email_button( $url, $label = '' ) {
	$label = $label ? $label : apprex_email_button_label( $url );
	$color = apprex_email_color();
	return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 22px;"><tr>'
		. '<td align="center" style="border-radius:8px;background:' . $color . ';">'
		. '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" '
		. 'style="display:inline-block;padding:13px 28px;font-size:15px;font-weight:bold;color:#ffffff;'
		. 'text-decoration:none;border-radius:8px;font-family:sans-serif;">'
		. esc_html( $label ) . ' &rarr;</a></td></tr></table>';
}

/** エスケープ済みテキスト中の URL をリンク化。 */
function apprex_email_linkify( $escaped ) {
	return preg_replace_callback(
		'#(https?://[^\s<]+)#',
		function ( $m ) {
			return '<a href="' . esc_url( $m[1] ) . '" style="color:' . apprex_email_color() . ';">' . esc_html( $m[1] ) . '</a>';
		},
		$escaped
	);
}

/**
 * プレーン本文をブランド HTML 本文に変換。
 * 単独行の URL はボタンに、区切り線（──/—/-）は <hr> に変換。
 *
 * @param string $text プレーンテキスト。
 * @return string HTML。
 */
function apprex_text_to_html( $text ) {
	$text  = str_replace( "\r\n", "\n", (string) $text );
	$lines = explode( "\n", $text );
	$html  = '';
	$para  = array();

	$flush = function () use ( &$para, &$html ) {
		if ( ! empty( $para ) ) {
			$html .= '<p style="margin:0 0 16px;line-height:1.85;color:#333333;font-size:15px;">'
				. implode( '<br>', $para ) . '</p>';
			$para = array();
		}
	};

	foreach ( $lines as $line ) {
		$trim = trim( $line );
		if ( '' === $trim ) {
			$flush();
			continue;
		}
		if ( preg_match( '#^https?://\S+$#', $trim ) ) {
			$flush();
			$html .= apprex_email_button( $trim );
			continue;
		}
		if ( preg_match( '/^[\x{2500}\x{2014}\-]{3,}$/u', $trim ) ) {
			$flush();
			$html .= '<hr style="border:none;border-top:1px solid #e5e7eb;margin:22px 0;">';
			continue;
		}
		$para[] = apprex_email_linkify( esc_html( $line ) );
	}
	$flush();
	return $html;
}

/**
 * HTML 本文をブランドシェル（ヘッダー・カード・フッター）で包む。
 *
 * @param string $subject      件名（プレビュー/preheader 用）。
 * @param string $content_html 本文 HTML。
 * @param array  $args         { heading, preheader, unsub_url }。
 * @return string 完成した HTML メール。
 */
function apprex_email_wrap( $subject, $content_html, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'heading'   => '',
			'preheader' => '',
			'unsub_url' => '',
		)
	);

	$color   = apprex_email_color();
	$site    = home_url( '/' );
	$line    = function_exists( 'apprex_line_url' ) ? apprex_line_url() : '';
	$heading = $args['heading'];
	$pre     = $args['preheader'] ? $args['preheader'] : wp_strip_all_tags( $subject );

	$head_html = $heading
		? '<h1 style="margin:0 0 18px;font-size:20px;line-height:1.5;color:#111827;font-weight:bold;">' . esc_html( $heading ) . '</h1>'
		: '';

	// フッター（会社情報）
	$email   = function_exists( 'apprex_contact_email' ) ? apprex_contact_email() : get_option( 'admin_email' );
	$footer  = '<p style="margin:0 0 6px;font-weight:bold;color:#374151;">APPREX（アプリックス）</p>';
	$footer .= '<p style="margin:0 0 4px;color:#6b7280;font-size:12px;line-height:1.7;">'
		. 'クラウド型ノーコードアプリ開発プラットフォーム<br>'
		. '<strong>合同会社アイズ</strong><br>'
		. '受付：平日 10:00〜18:00（チャット・メール・オンライン相談）<br>'
		. 'メール：<a href="mailto:' . esc_attr( $email ) . '" style="color:' . $color . ';text-decoration:none;">' . esc_html( $email ) . '</a></p>';
	$links = array();
	$links[] = '<a href="' . esc_url( $site ) . '" style="color:' . $color . ';text-decoration:none;">サイトを見る</a>';
	if ( $line ) {
		$links[] = '<a href="' . esc_url( $line ) . '" style="color:' . $color . ';text-decoration:none;">LINEで相談</a>';
	}
	if ( $args['unsub_url'] ) {
		$links[] = '<a href="' . esc_url( $args['unsub_url'] ) . '" style="color:#9ca3af;text-decoration:underline;">配信停止</a>';
	}
	$footer .= '<p style="margin:8px 0 0;font-size:12px;">' . implode( '&nbsp;&nbsp;|&nbsp;&nbsp;', $links ) . '</p>';

	$html  = '<!doctype html><html lang="ja"><head><meta charset="utf-8">';
	$html .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
	$html .= '<meta name="color-scheme" content="light"><title>' . esc_html( $subject ) . '</title></head>';
	$html .= '<body style="margin:0;padding:0;background:#f3f4f6;">';
	// preheader（受信トレイのプレビュー文・非表示）
	$html .= '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . esc_html( $pre ) . '</div>';
	$html .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f3f4f6;padding:24px 12px;">';
	$html .= '<tr><td align="center">';
	$html .= '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:100%;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08);">';
	// ヘッダーバー
	$html .= '<tr><td style="background:' . $color . ';padding:22px 32px;">';
	$html .= '<span style="font-size:22px;font-weight:bold;letter-spacing:1px;color:#ffffff;font-family:sans-serif;">APPREX</span>';
	$html .= '<span style="display:block;margin-top:2px;font-size:12px;color:#dbeafe;">ノーコードアプリ開発プラットフォーム</span>';
	$html .= '</td></tr>';
	// 本文
	$html .= '<tr><td style="padding:30px 32px 8px;font-family:sans-serif;">' . $head_html . $content_html . '</td></tr>';
	// フッター
	$html .= '<tr><td style="padding:20px 32px 26px;border-top:1px solid #f0f0f0;font-family:sans-serif;">' . $footer . '</td></tr>';
	$html .= '</table></td></tr></table></body></html>';

	return $html;
}

/**
 * プレーン本文を受け取り、完成 HTML メールを返す便利関数。
 *
 * @param string $subject    件名。
 * @param string $plain_body プレーン本文（{name} は置換済み想定）。
 * @param array  $args       apprex_email_wrap() の引数。
 * @return string
 */
function apprex_render_email( $subject, $plain_body, $args = array() ) {
	return apprex_email_wrap( $subject, apprex_text_to_html( $plain_body ), $args );
}

/**
 * 管理者通知メールの本文（HTMLテーブル）。
 *
 * @param string $type_label 種別ラベル。
 * @param array  $fields     送信内容。
 * @param int    $post_id    投稿ID（0なら管理リンク無し）。
 * @return string
 */
function apprex_admin_notify_html( $type_label, $fields, $post_id = 0 ) {
	$rows = array(
		'種別'   => $type_label,
		'お名前' => isset( $fields['name'] ) ? $fields['name'] : '',
		'会社名' => ! empty( $fields['company'] ) ? $fields['company'] : '（未入力）',
		'メール' => isset( $fields['email'] ) ? $fields['email'] : '',
		'電話'   => ! empty( $fields['phone'] ) ? $fields['phone'] : '（未入力）',
	);
	if ( ! empty( $fields['meeting_at'] ) ) {
		$rows['ご希望日時'] = wp_date( 'Y-m-d H:i', (int) $fields['meeting_at'] );
	}
	$rows['内容'] = ! empty( $fields['message'] ) ? $fields['message'] : '（なし）';

	$html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;font-size:14px;">';
	foreach ( $rows as $k => $v ) {
		$html .= '<tr>'
			. '<th align="left" style="padding:10px 12px;background:#f8fafc;border:1px solid #e5e7eb;width:110px;color:#374151;white-space:nowrap;vertical-align:top;">' . esc_html( $k ) . '</th>'
			. '<td style="padding:10px 12px;border:1px solid #e5e7eb;color:#111827;line-height:1.7;">' . nl2br( esc_html( (string) $v ) ) . '</td>'
			. '</tr>';
	}
	$html .= '</table>';

	if ( $post_id ) {
		$html .= apprex_email_button( admin_url( 'post.php?post=' . $post_id . '&action=edit' ), '管理画面で開く' );
	}
	return $html;
}

/* -------------------------------------------------------------------------
 * 文面の上書き（管理画面で編集した内容を適用）
 * ---------------------------------------------------------------------- */

/** 保存済みの文面上書き（連想配列）。 */
function apprex_mail_overrides() {
	$o = get_option( 'apprex_mail_overrides', array() );
	return is_array( $o ) ? $o : array();
}

/**
 * 指定キーの上書き文面を返す（無ければ null）。
 *
 * @param string $key 例 autoreply.contact / step.contact.1 / reminder.before_1d。
 * @return array|null { subject, body }
 */
function apprex_mail_override( $key ) {
	$o = apprex_mail_overrides();
	return ( isset( $o[ $key ] ) && is_array( $o[ $key ] ) ) ? $o[ $key ] : null;
}

/** ステップメールに上書きを反映。 */
function apprex_apply_step_overrides( $steps, $type ) {
	foreach ( $steps as $offset => $mail ) {
		$ov = apprex_mail_override( "step.$type.$offset" );
		if ( $ov ) {
			if ( ! empty( $ov['subject'] ) ) {
				$steps[ $offset ]['subject'] = $ov['subject'];
			}
			if ( ! empty( $ov['body'] ) ) {
				$steps[ $offset ]['body'] = $ov['body'];
			}
		}
	}
	return $steps;
}
add_filter( 'apprex_step_mails', 'apprex_apply_step_overrides', 10, 2 );

/** ミーティングリマインダーに上書きを反映。 */
function apprex_apply_reminder_overrides( $reminders ) {
	foreach ( $reminders as $k => $r ) {
		$ov = apprex_mail_override( "reminder.$k" );
		if ( $ov ) {
			if ( ! empty( $ov['subject'] ) ) {
				$reminders[ $k ]['subject'] = $ov['subject'];
			}
			if ( ! empty( $ov['body'] ) ) {
				$reminders[ $k ]['body'] = $ov['body'];
			}
		}
	}
	return $reminders;
}
add_filter( 'apprex_meeting_reminders', 'apprex_apply_reminder_overrides', 10, 1 );

/* -------------------------------------------------------------------------
 * 管理画面：メール文面の編集
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'options-general.php',
		'APPREX メール文面の編集',
		'APPREX メール文面',
		'manage_options',
		'apprex-mail-content',
		'apprex_mail_content_page'
	);
} );

/** 保存可能なキーか検証。 */
function apprex_mail_key_allowed( $key ) {
	return (bool) preg_match( '/^(autoreply\.[a-z]+|step\.[a-z]+\.\d+|reminder\.[a-z0-9_]+)$/', $key );
}

/** 文面の保存ハンドラ。 */
add_action( 'admin_post_apprex_save_mail_content', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_mail_content' );

	$in  = ( isset( $_POST['m'] ) && is_array( $_POST['m'] ) ) ? wp_unslash( $_POST['m'] ) : array();
	$out = array();
	foreach ( $in as $key => $pair ) {
		$key = (string) $key;
		if ( ! apprex_mail_key_allowed( $key ) || ! is_array( $pair ) ) {
			continue;
		}
		$subject = isset( $pair['subject'] ) ? sanitize_text_field( $pair['subject'] ) : '';
		$body    = isset( $pair['body'] ) ? sanitize_textarea_field( $pair['body'] ) : '';
		if ( '' !== $subject || '' !== $body ) {
			$out[ $key ] = array( 'subject' => $subject, 'body' => $body );
		}
	}
	update_option( 'apprex_mail_overrides', $out );
	wp_safe_redirect( add_query_arg( 'apprex_saved', '1', admin_url( 'options-general.php?page=apprex-mail-content' ) ) );
	exit;
} );

/** 全文面を初期状態に戻す。 */
add_action( 'admin_post_apprex_reset_mail_content', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_mail_content' );
	delete_option( 'apprex_mail_overrides' );
	wp_safe_redirect( add_query_arg( 'apprex_saved', 'reset', admin_url( 'options-general.php?page=apprex-mail-content' ) ) );
	exit;
} );

/** 1項目分の編集フィールドを描画。 */
function apprex_mail_edit_field( $key, $label, $subject, $body, $note = '' ) {
	echo '<tr><th scope="row" style="vertical-align:top;width:120px;">' . esc_html( $label ) . '</th><td>';
	echo '<input type="text" name="m[' . esc_attr( $key ) . '][subject]" class="large-text" value="' . esc_attr( $subject ) . '" placeholder="件名" style="margin-bottom:6px;">';
	echo '<textarea name="m[' . esc_attr( $key ) . '][body]" rows="6" class="large-text code" placeholder="本文">' . esc_textarea( $body ) . '</textarea>';
	if ( $note ) {
		echo '<p class="description">' . esc_html( $note ) . '</p>';
	}
	echo '</td></tr>';
}

/** メール文面編集ページ本体。 */
function apprex_mail_content_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$labels = apprex_mail_types();
	?>
	<div class="wrap">
		<h1>APPREX メール文面の編集</h1>

		<?php if ( isset( $_GET['apprex_saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>
			<?php echo ( 'reset' === $_GET['apprex_saved'] ) ? '全ての文面を初期状態に戻しました。' : '文面を保存しました。'; ?>
			</p></div>
		<?php endif; ?>

		<p>件名・本文を自由に編集できます。差し込みタグ <code>{name}</code> はお客様名に置き換わります。
		編集後は「<strong>設定 &gt; APPREX メール</strong>」のプレビュー／テスト送信で見た目を確認できます。<br>
		ある項目を<strong>空欄にして保存すると、その項目だけ初期文面に戻ります</strong>。</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="apprex_save_mail_content">
			<?php wp_nonce_field( 'apprex_mail_content' ); ?>

			<h2>自動返信（申込直後）</h2>
			<table class="form-table" role="presentation"><tbody>
			<?php
			$ar_types = array( 'contact', 'document', 'trial', 'meeting', 'partner' );
			foreach ( $ar_types as $t ) {
				list( $s, $b ) = apprex_autoreply_message( $t, array( 'name' => '{name}' ) );
				$note = '{name} が使えます';
				if ( 'document' === $t ) {
					$note .= ' / {download_url}（資料DLリンク）';
				}
				if ( 'meeting' === $t ) {
					$note .= ' / {meeting_at}（予約日時）';
				}
				apprex_mail_edit_field( 'autoreply.' . $t, ( isset( $labels[ $t ] ) ? $labels[ $t ] : $t ), $s, $b, $note );
			}
			?>
			</tbody></table>

			<h2>ステップメール（フォロー配信）</h2>
			<?php
			$drip = array(
				'contact'  => 'お問い合わせ',
				'document' => '資料請求',
				'trial'    => '無料体験',
				'estimate' => '見積・発注',
			);
			foreach ( $drip as $k => $lbl ) {
				echo '<h3 style="border-left:4px solid #2563eb;padding-left:8px;">' . esc_html( $lbl ) . '</h3>';
				echo '<table class="form-table" role="presentation"><tbody>';
				$steps = apprex_step_mails( $k );
				ksort( $steps );
				foreach ( $steps as $offset => $mail ) {
					apprex_mail_edit_field( "step.$k.$offset", apprex_offset_label( $offset ), $mail['subject'], $mail['body'], '{name} が使えます' );
				}
				echo '</tbody></table>';
			}
			?>

			<h2>ミーティング リマインダー</h2>
			<table class="form-table" role="presentation"><tbody>
			<?php
			$rlabel = array(
				'before_1d' => '前日',
				'before_1h' => '1時間前',
				'after_1d'  => '翌日フォロー',
			);
			foreach ( apprex_meeting_reminders() as $rk => $r ) {
				$lbl = isset( $rlabel[ $rk ] ) ? $rlabel[ $rk ] : $rk;
				apprex_mail_edit_field( "reminder.$rk", $lbl, $r['subject'], $r['body'], '{name} {when}（予約日時）が使えます' );
			}
			?>
			</tbody></table>

			<?php submit_button( '文面を保存する' ); ?>
		</form>

		<hr>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('編集した全ての文面を初期状態に戻します。よろしいですか？');">
			<input type="hidden" name="action" value="apprex_reset_mail_content">
			<?php wp_nonce_field( 'apprex_mail_content' ); ?>
			<button type="submit" class="button button-secondary">全ての文面を初期状態に戻す</button>
			<span class="description">（編集内容を破棄し、最初の文面に戻します）</span>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 管理画面：テスト送信 ＋ プレビュー
 * ---------------------------------------------------------------------- */

add_action( 'admin_menu', function () {
	add_submenu_page(
		'options-general.php',
		'APPREX メール（テスト/プレビュー）',
		'APPREX メール',
		'manage_options',
		'apprex-mail',
		'apprex_mail_admin_page'
	);
} );

/** プレビュー/テストで使うサンプル送信内容。 */
function apprex_sample_fields( $type, $to = '' ) {
	$fields = array(
		'name'    => 'テスト 太郎',
		'company' => '株式会社テスト',
		'email'   => $to ? $to : 'test@example.com',
		'phone'   => '03-1234-5678',
		'message' => 'これはテスト送信です。資料・料金・導入事例について詳しく知りたいです。',
	);
	if ( 'meeting' === $type ) {
		$fields['meeting_at'] = time() + 2 * DAY_IN_SECONDS;
	}
	return $fields;
}

/** プレビュー対象の種別一覧。 */
function apprex_mail_types() {
	return array(
		'contact'  => 'お問い合わせ',
		'document' => '資料請求',
		'trial'    => '無料体験',
		'meeting'  => 'ミーティング予約',
		'partner'  => 'パートナー',
		'estimate' => '見積もり・発注',
	);
}

/** ドリップ配列のキー（contact/document/trial/estimate）。meeting/partner は contact にフォールバック。 */
function apprex_drip_key_for( $type ) {
	$has = array( 'contact', 'document', 'trial', 'estimate' );
	return in_array( $type, $has, true ) ? $type : 'contact';
}

/**
 * テスト送信ハンドラ。自動返信＋管理者通知＋ステップ1通目を指定アドレスへ送る。
 */
add_action( 'admin_post_apprex_test_mail', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_test_mail' );

	$to   = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
	$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'contact';
	$back = admin_url( 'options-general.php?page=apprex-mail' );

	if ( ! is_email( $to ) ) {
		wp_safe_redirect( add_query_arg( 'apprex_test', 'bademail', $back ) );
		exit;
	}

	$fields  = apprex_sample_fields( $type, $to );
	$headers = apprex_mail_headers();
	$sent    = 0;

	// 1) お客様向け自動返信
	list( $subject, $body ) = apprex_autoreply_message( $type, $fields );
	$html = apprex_render_email( $subject, $body, array( 'heading' => apprex_email_heading_from_subject( $subject ) ) );
	if ( wp_mail( $to, '[テスト] ' . $subject, $html, $headers ) ) {
		$sent++;
	}

	// 2) 管理者通知
	$label    = apprex_mail_types();
	$tlabel   = isset( $label[ $type ] ) ? $label[ $type ] : 'お問い合わせ';
	$nsubject = sprintf( '[APPREX] %s #%s — %s', $tlabel, '0000', $fields['name'] );
	$nhtml    = apprex_email_wrap( $nsubject, apprex_admin_notify_html( $tlabel, $fields, 0 ), array( 'heading' => '新しい' . $tlabel . 'が届きました' ) );
	if ( wp_mail( $to, '[テスト] ' . $nsubject, $nhtml, $headers ) ) {
		$sent++;
	}

	// 3) ステップメール 1通目（あれば）
	$steps = apprex_step_mails( apprex_drip_key_for( $type ) );
	ksort( $steps );
	$first = reset( $steps );
	if ( $first ) {
		$sbody = str_replace( '{name}', $fields['name'], $first['body'] );
		$shtml = apprex_render_email( $first['subject'], $sbody, array( 'heading' => apprex_email_heading_from_subject( $first['subject'] ) ) );
		if ( wp_mail( $to, '[テスト/ステップ1] ' . $first['subject'], $shtml, $headers ) ) {
			$sent++;
		}
	}

	wp_safe_redirect( add_query_arg( array( 'apprex_test' => 'sent', 'n' => $sent ), $back ) );
	exit;
} );

/**
 * 1通だけテスト送信するハンドラ（プレビュー横の「この1通をテスト送信」用）。
 * part: autoreply / admin / step / reminder。step は id=オフセット(分)、reminder は id=キー。
 */
add_action( 'admin_post_apprex_test_mail_one', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_test_one' );

	$to   = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
	$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'contact';
	$part = isset( $_POST['part'] ) ? sanitize_key( wp_unslash( $_POST['part'] ) ) : 'autoreply';
	$id   = isset( $_POST['id'] ) ? sanitize_text_field( wp_unslash( $_POST['id'] ) ) : '';
	$back = admin_url( 'options-general.php?page=apprex-mail' );

	if ( ! is_email( $to ) ) {
		wp_safe_redirect( add_query_arg( 'apprex_test', 'bademail', $back ) );
		exit;
	}

	$fields  = apprex_sample_fields( $type, $to );
	$headers = apprex_mail_headers();
	$subject = '';
	$html    = '';

	switch ( $part ) {
		case 'admin':
			$labels  = apprex_mail_types();
			$tlabel  = isset( $labels[ $type ] ) ? $labels[ $type ] : 'お問い合わせ';
			$subject = sprintf( '[APPREX] %s #%s — %s', $tlabel, '0000', $fields['name'] );
			$html    = apprex_email_wrap( $subject, apprex_admin_notify_html( $tlabel, $fields, 0 ), array( 'heading' => '新しい' . $tlabel . 'が届きました' ) );
			break;

		case 'step':
			$steps  = apprex_step_mails( apprex_drip_key_for( $type ) );
			$offset = (int) $id;
			if ( isset( $steps[ $offset ] ) ) {
				$m       = $steps[ $offset ];
				$body    = str_replace( '{name}', $fields['name'], $m['body'] );
				$subject = $m['subject'];
				$html    = apprex_render_email( $subject, $body, array( 'heading' => apprex_email_heading_from_subject( $subject ) ) );
			}
			break;

		case 'reminder':
			$reminders = apprex_meeting_reminders();
			if ( isset( $reminders[ $id ] ) ) {
				$r       = $reminders[ $id ];
				$when    = wp_date( 'Y年n月j日(D) H:i', (int) $fields['meeting_at'] );
				$body    = str_replace( array( '{name}', '{when}' ), array( $fields['name'], $when ), $r['body'] );
				$subject = $r['subject'];
				$html    = apprex_render_email( $subject, $body, array( 'heading' => apprex_email_heading_from_subject( $subject ) ) );
			}
			break;

		case 'autoreply':
		default:
			list( $subject, $body ) = apprex_autoreply_message( $type, $fields );
			$html = apprex_render_email( $subject, $body, array( 'heading' => apprex_email_heading_from_subject( $subject ) ) );
			break;
	}

	if ( $subject && $html && wp_mail( $to, '[テスト] ' . $subject, $html, $headers ) ) {
		wp_safe_redirect( add_query_arg( array( 'apprex_test' => 'sent', 'n' => 1 ), $back ) );
		exit;
	}
	wp_safe_redirect( add_query_arg( 'apprex_test', 'failed', $back ) );
	exit;
} );

/** オフセット（分）を人が読めるラベルに（10分後 / 1時間後 / 1日後）。 */
function apprex_offset_label( $minutes ) {
	$minutes = (int) $minutes;
	if ( $minutes < 60 ) {
		return $minutes . '分後';
	}
	if ( 0 === $minutes % 1440 ) {
		return ( $minutes / 1440 ) . '日後';
	}
	if ( 0 === $minutes % 60 ) {
		return ( $minutes / 60 ) . '時間後';
	}
	return $minutes . '分後';
}

/** ステップメール配信タイミング（種別→オフセット分）。 */
function apprex_drip_timing( $type ) {
	$steps = apprex_step_mails( apprex_drip_key_for( $type ) );
	ksort( $steps );
	$rows = array();
	foreach ( $steps as $offset => $mail ) {
		$rows[ $offset ] = $mail['subject'];
	}
	return $rows;
}

/** テストモード（日→分）切替。 */
add_action( 'admin_post_apprex_drip_testmode', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_drip_tools' );
	$mode = isset( $_POST['mode'] ) && '1' === $_POST['mode'] ? 1 : 0;
	update_option( 'apprex_drip_test_mode', $mode );
	wp_safe_redirect( add_query_arg( 'apprex_test', 'mode', admin_url( 'options-general.php?page=apprex-mail' ) ) );
	exit;
} );

/** 今すぐ配信処理を実行（cronを待たずに送る）。 */
add_action( 'admin_post_apprex_drip_run_now', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_drip_tools' );
	if ( function_exists( 'apprex_process_dripmail' ) ) {
		apprex_process_dripmail();
	}
	wp_safe_redirect( add_query_arg( 'apprex_test', 'ran', admin_url( 'options-general.php?page=apprex-mail' ) ) );
	exit;
} );

/** テスト購読を開始（自分のアドレスをステップ配信に登録）。 */
add_action( 'admin_post_apprex_drip_test_start', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( '権限がありません。' );
	}
	check_admin_referer( 'apprex_drip_tools' );

	$to   = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
	$type = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'contact';
	$back = admin_url( 'options-general.php?page=apprex-mail' );

	if ( ! is_email( $to ) ) {
		wp_safe_redirect( add_query_arg( 'apprex_test', 'bademail', $back ) );
		exit;
	}

	$dtype   = apprex_drip_key_for( $type );
	$post_id = wp_insert_post(
		array(
			'post_type'   => 'apprex_inquiry',
			'post_status' => 'publish',
			'post_title'  => '[テスト購読] ' . $to . '（' . $dtype . '）',
		)
	);
	if ( $post_id && function_exists( 'apprex_enroll_drip' ) ) {
		apprex_enroll_drip( $post_id, $dtype, $to, 'テスト 太郎' );
		// 即時に1通目が出るよう、テストモードもONにしておく。
		update_option( 'apprex_drip_test_mode', 1 );
	}
	wp_safe_redirect( add_query_arg( 'apprex_test', 'started', $back ) );
	exit;
} );

/** 管理ページ本体（テスト送信フォーム＋プレビュー）。 */
function apprex_mail_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$types   = apprex_mail_types();
	$default = wp_get_current_user()->user_email;
	?>
	<div class="wrap">
		<h1>APPREX メール（テスト送信 / プレビュー）</h1>
		<p><a href="<?php echo esc_url( admin_url( 'options-general.php?page=apprex-mail-content' ) ); ?>" class="button">✏️ メール文面を編集する</a> ← 件名・本文を編集したい場合はこちら</p>

		<?php if ( isset( $_GET['apprex_test'] ) ) : ?>
			<?php if ( 'sent' === $_GET['apprex_test'] ) : ?>
				<div class="notice notice-success is-dismissible"><p>テストメールを <strong><?php echo (int) ( $_GET['n'] ?? 0 ); ?>通</strong> 送信しました。受信トレイ（迷惑メールも）をご確認ください。</p></div>
			<?php elseif ( 'bademail' === $_GET['apprex_test'] ) : ?>
				<div class="notice notice-error is-dismissible"><p>メールアドレスが正しくありません。</p></div>
			<?php elseif ( 'started' === $_GET['apprex_test'] ) : ?>
				<div class="notice notice-success is-dismissible"><p>テスト購読を開始しました（テストモードON）。下の「今すぐ配信処理を実行」を押すたびに、経過分数に応じてステップメールが順番に届きます。</p></div>
			<?php elseif ( 'ran' === $_GET['apprex_test'] ) : ?>
				<div class="notice notice-success is-dismissible"><p>配信処理を実行しました。期限が来ているステップメールを送信しました（受信トレイをご確認ください）。</p></div>
			<?php elseif ( 'mode' === $_GET['apprex_test'] ) : ?>
				<div class="notice notice-success is-dismissible"><p>テストモードを切り替えました。</p></div>
			<?php elseif ( 'failed' === $_GET['apprex_test'] ) : ?>
				<div class="notice notice-error is-dismissible"><p>テストメールを送信できませんでした。送信設定（SMTP等）をご確認ください。</p></div>
			<?php endif; ?>
		<?php endif; ?>

		<?php $test_mode = (int) get_option( 'apprex_drip_test_mode', 0 ); ?>
		<?php if ( $test_mode ) : ?>
			<div class="notice notice-warning"><p>⚠️ <strong>ステップメール テストモードが ON</strong> です（「日」を「分」に圧縮中）。本番運用前に必ず OFF に戻してください。</p></div>
		<?php endif; ?>

		<h2>テスト送信（まとめて）</h2>
		<p>指定アドレスへ「自動返信 ＋ 管理者通知 ＋ ステップ1通目」をまとめて送り、実際に届くか・デザインを確認できます。<br>
		<strong>メールを1通ずつ個別に送りたい場合</strong>は、下の「メール内容プレビュー」の各メール下にある「この1通をテスト送信」をご利用ください。</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0 0 8px;">
			<input type="hidden" name="action" value="apprex_test_mail">
			<?php wp_nonce_field( 'apprex_test_mail' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><label for="apprex_test_to">送信先メール</label></th>
					<td><input type="email" id="apprex_test_to" name="to" class="regular-text" value="<?php echo esc_attr( $default ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_test_type">種別</label></th>
					<td><select id="apprex_test_type" name="type">
						<?php foreach ( $types as $k => $label ) : ?>
							<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></td>
				</tr>
			</tbody></table>
			<?php submit_button( 'テスト送信する' ); ?>
		</form>

		<hr>
		<h2>ステップメールの配信テスト</h2>
		<p>実際のステップメール（フォロー配信）が「どの順番・どの間隔で届くか」を体感テストできます。<br>
		テストモードを使うと <strong>「◯日後」→「◯分後」</strong> に圧縮されるので、数分で全ステップを確認できます。</p>

		<table class="form-table" role="presentation"><tbody>
			<tr>
				<th scope="row">テストモード</th>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
						<input type="hidden" name="action" value="apprex_drip_testmode">
						<?php wp_nonce_field( 'apprex_drip_tools' ); ?>
						<input type="hidden" name="mode" value="<?php echo $test_mode ? '0' : '1'; ?>">
						<strong style="margin-right:8px;"><?php echo $test_mode ? '現在：ON（分単位）' : '現在：OFF（本番・日単位）'; ?></strong>
						<button type="submit" class="button"><?php echo $test_mode ? 'OFFにする（本番に戻す）' : 'ONにする（日→分に圧縮）'; ?></button>
					</form>
				</td>
			</tr>
			<tr>
				<th scope="row">テスト購読を開始</th>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
						<input type="hidden" name="action" value="apprex_drip_test_start">
						<?php wp_nonce_field( 'apprex_drip_tools' ); ?>
						<input type="email" name="to" class="regular-text" value="<?php echo esc_attr( $default ); ?>" placeholder="送信先メール" required style="margin:0;">
						<select name="type">
							<?php foreach ( $types as $k => $label ) : ?>
								<?php if ( 'meeting' === $k ) { continue; } ?>
								<option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
						<button type="submit" class="button button-primary">この内容でテスト購読を開始</button>
					</form>
					<p class="description">開始するとテストモードが自動でONになり、すぐ下の「今すぐ配信」で1通目から順に届きます。</p>
				</td>
			</tr>
			<tr>
				<th scope="row">今すぐ配信処理を実行</th>
				<td>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<input type="hidden" name="action" value="apprex_drip_run_now">
						<?php wp_nonce_field( 'apprex_drip_tools' ); ?>
						<button type="submit" class="button">今すぐ配信処理を実行（cronを待たない）</button>
					</form>
					<p class="description">押した時点で「期限が来ているステップ」を送信します。テストモード中なら、購読開始から1分後→1通目、3分後→2通目…の要領で、押すたびに順番に届きます。</p>
				</td>
			</tr>
		</tbody></table>

		<h3>配信スケジュール一覧</h3>
		<p class="description">下記は本番の配信タイミングです。テストモードONのときは「1日 → 1分」に圧縮されます（例：10分後はほぼ即時、1日後は1分後）。</p>
		<table class="widefat striped" style="max-width:820px;">
			<thead><tr><th style="width:90px;">タイミング</th><th>種別</th><th>件名</th></tr></thead>
			<tbody>
			<?php
			foreach ( $types as $type => $label ) {
				if ( 'meeting' === $type ) {
					continue;
				}
				$timing = apprex_drip_timing( $type );
				foreach ( $timing as $offset => $subj ) {
					echo '<tr><td>' . esc_html( apprex_offset_label( $offset ) ) . '</td><td>' . esc_html( $label ) . '</td><td>' . esc_html( $subj ) . '</td></tr>';
				}
			}
			?>
			</tbody>
		</table>

		<hr>
		<h2>メール内容プレビュー / 1通ずつテスト送信</h2>
		<p>送信される全メール（自動返信・管理者通知・ステップメール・リマインダー）を実際の見た目で確認できます。<br>
		各プレビューの下の「<strong>この1通をテスト送信</strong>」で、メールを<strong>1通ずつ個別に</strong>指定アドレスへ送れます（送信先は初期値であなたのメールが入っています）。</p>
		<?php
		foreach ( $types as $type => $label ) {
			echo '<h3 style="margin-top:28px;border-left:4px solid #2563eb;padding-left:8px;">' . esc_html( $label ) . '（' . esc_html( $type ) . '）</h3>';

			// 自動返信（お客様向け）
			$fields = apprex_sample_fields( $type );
			list( $asub, $abody ) = apprex_autoreply_message( $type, $fields );
			apprex_render_preview( '自動返信（申込直後）', $asub, apprex_render_email( $asub, $abody, array( 'heading' => apprex_email_heading_from_subject( $asub ) ) ), array( 'type' => $type, 'part' => 'autoreply' ), $default );

			// 管理者通知（社内向け）
			$tlabel = isset( $types[ $type ] ) ? $types[ $type ] : 'お問い合わせ';
			$nsub   = sprintf( '[APPREX] %s #%s — %s', $tlabel, '0000', $fields['name'] );
			$nhtml  = apprex_email_wrap( $nsub, apprex_admin_notify_html( $tlabel, $fields, 0 ), array( 'heading' => '新しい' . $tlabel . 'が届きました' ) );
			apprex_render_preview( '管理者通知', $nsub, $nhtml, array( 'type' => $type, 'part' => 'admin' ), $default );

			// ステップメール / リマインダー
			if ( 'meeting' === $type ) {
				if ( get_option( 'apprex_wp_meeting_reminders', 0 ) ) {
					foreach ( apprex_meeting_reminders() as $rk => $r ) {
						$b = str_replace( array( '{name}', '{when}' ), array( $fields['name'], wp_date( 'Y年n月j日(D) H:i', (int) $fields['meeting_at'] ) ), $r['body'] );
						apprex_render_preview( 'リマインダー', $r['subject'], apprex_render_email( $r['subject'], $b, array( 'heading' => apprex_email_heading_from_subject( $r['subject'] ) ) ), array( 'type' => $type, 'part' => 'reminder', 'id' => $rk ), $default );
					}
				} else {
					echo '<p style="color:#6b7280;">※ ミーティングのリマインダーはGoogleカレンダーが送信（WordPressからは送信しない設定）。</p>';
				}
			} else {
				$steps = apprex_step_mails( apprex_drip_key_for( $type ) );
				ksort( $steps );
				foreach ( $steps as $offset => $mail ) {
					$b = str_replace( '{name}', $fields['name'], $mail['body'] );
					apprex_render_preview( apprex_offset_label( $offset ), $mail['subject'], apprex_render_email( $mail['subject'], $b, array( 'heading' => apprex_email_heading_from_subject( $mail['subject'] ) ) ), array( 'type' => $type, 'part' => 'step', 'id' => $offset ), $default );
				}
			}
		}
		?>
	</div>
	<?php
}

/**
 * 1通分のプレビューを iframe（隔離）で描画。
 *
 * @param string     $tag     ラベル。
 * @param string     $subject 件名。
 * @param string     $html    本文HTML。
 * @param array|null $send    指定時、この1通をテスト送信するフォームを表示（type/part/id）。
 * @param string     $to      送信先の初期値。
 */
function apprex_render_preview( $tag, $subject, $html, $send = null, $to = '' ) {
	echo '<div style="margin:0 0 18px;max-width:640px;">';
	echo '<div style="font-size:13px;color:#374151;margin-bottom:4px;"><strong style="display:inline-block;min-width:90px;color:#2563eb;">' . esc_html( $tag ) . '</strong> 件名：' . esc_html( $subject ) . '</div>';
	echo '<iframe srcdoc="' . esc_attr( $html ) . '" style="width:100%;height:420px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;" loading="lazy"></iframe>';
	if ( is_array( $send ) ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:6px;align-items:center;margin:6px 0 0;flex-wrap:wrap;">';
		echo '<input type="hidden" name="action" value="apprex_test_mail_one">';
		echo wp_nonce_field( 'apprex_test_one', '_wpnonce', true, false );
		echo '<input type="hidden" name="type" value="' . esc_attr( $send['type'] ) . '">';
		echo '<input type="hidden" name="part" value="' . esc_attr( $send['part'] ) . '">';
		echo '<input type="hidden" name="id" value="' . esc_attr( isset( $send['id'] ) ? $send['id'] : '' ) . '">';
		echo '<input type="email" name="to" value="' . esc_attr( $to ) . '" required style="max-width:240px;" placeholder="送信先メール">';
		echo '<button type="submit" class="button button-secondary">この1通をテスト送信</button>';
		echo '</form>';
	}
	echo '</div>';
}
