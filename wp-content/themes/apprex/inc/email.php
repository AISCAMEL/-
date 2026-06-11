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

	// フッター
	$footer  = '<p style="margin:0 0 6px;font-weight:bold;color:#374151;">APPREX（アップレックス）</p>';
	$footer .= '<p style="margin:0 0 4px;color:#6b7280;font-size:12px;line-height:1.7;">'
		. 'ノーコードアプリ開発プラットフォーム / 合同会社アイズ<br>'
		. '受付：平日 10:00〜18:00（チャット・メール・オンライン相談）</p>';
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

		<?php if ( isset( $_GET['apprex_test'] ) ) : ?>
			<?php if ( 'sent' === $_GET['apprex_test'] ) : ?>
				<div class="notice notice-success is-dismissible"><p>テストメールを <strong><?php echo (int) ( $_GET['n'] ?? 0 ); ?>通</strong> 送信しました。受信トレイ（迷惑メールも）をご確認ください。</p></div>
			<?php elseif ( 'bademail' === $_GET['apprex_test'] ) : ?>
				<div class="notice notice-error is-dismissible"><p>メールアドレスが正しくありません。</p></div>
			<?php endif; ?>
		<?php endif; ?>

		<h2>テスト送信</h2>
		<p>指定アドレスへ「自動返信 ＋ 管理者通知 ＋ ステップ1通目」をまとめて送り、実際に届くか・デザインを確認できます。</p>
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
		<h2>メール内容プレビュー</h2>
		<p>送信される全メール（自動返信・ステップメール・リマインダー）を実際の見た目で確認できます。</p>
		<?php
		foreach ( $types as $type => $label ) {
			echo '<h3 style="margin-top:28px;border-left:4px solid #2563eb;padding-left:8px;">' . esc_html( $label ) . '（' . esc_html( $type ) . '）</h3>';

			// 自動返信
			$fields = apprex_sample_fields( $type );
			list( $asub, $abody ) = apprex_autoreply_message( $type, $fields );
			apprex_render_preview( '自動返信（申込直後）', $asub, apprex_render_email( $asub, $abody, array( 'heading' => apprex_email_heading_from_subject( $asub ) ) ) );

			// ステップメール
			if ( 'meeting' === $type ) {
				if ( get_option( 'apprex_wp_meeting_reminders', 0 ) ) {
					foreach ( apprex_meeting_reminders() as $r ) {
						$b = str_replace( array( '{name}', '{when}' ), array( $fields['name'], wp_date( 'Y年n月j日(D) H:i', (int) $fields['meeting_at'] ) ), $r['body'] );
						apprex_render_preview( 'リマインダー', $r['subject'], apprex_render_email( $r['subject'], $b, array( 'heading' => apprex_email_heading_from_subject( $r['subject'] ) ) ) );
					}
				} else {
					echo '<p style="color:#6b7280;">※ ミーティングのリマインダーはGoogleカレンダーが送信（WordPressからは送信しない設定）。</p>';
				}
			} else {
				$steps = apprex_step_mails( apprex_drip_key_for( $type ) );
				ksort( $steps );
				foreach ( $steps as $offset => $mail ) {
					$b = str_replace( '{name}', $fields['name'], $mail['body'] );
					apprex_render_preview( $offset . '日後', $mail['subject'], apprex_render_email( $mail['subject'], $b, array( 'heading' => apprex_email_heading_from_subject( $mail['subject'] ) ) ) );
				}
			}
		}
		?>
	</div>
	<?php
}

/** 1通分のプレビューを iframe（隔離）で描画。 */
function apprex_render_preview( $tag, $subject, $html ) {
	echo '<div style="margin:0 0 18px;max-width:640px;">';
	echo '<div style="font-size:13px;color:#374151;margin-bottom:4px;"><strong style="display:inline-block;min-width:90px;color:#2563eb;">' . esc_html( $tag ) . '</strong> 件名：' . esc_html( $subject ) . '</div>';
	echo '<iframe srcdoc="' . esc_attr( $html ) . '" style="width:100%;height:420px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;" loading="lazy"></iframe>';
	echo '</div>';
}
