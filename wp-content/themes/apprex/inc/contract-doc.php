<?php
/**
 * 契約書（書面）＋電子契約連携（マネーフォワード クラウド契約：リンク＋ステータス方式）。
 *
 * - 契約書テンプレート（管理画面で条項を編集、{{差し込み}}対応）
 * - 契約レコードから契約書を自動生成 → A4印刷最適化ページで表示（ブラウザでPDF保存）
 * - マネーフォワード クラウド契約と「リンク＋ステータス」で連携
 *     締結ページURL / 締結ステータス / 署名済みPDFのURL / 締結日 を契約に記録
 * - 会員マイページに「契約書」セクション（プレビュー・締結ボタン・署名済みPDF）
 * - 締結済みに切り替えた時、会員へ確認メールを送信
 *
 * APIキー不要。締結自体はマネーフォワード側で行い、本テーマはその入口と記録を担います。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* -------------------------------------------------------------------------
 * 締結ステータス
 * ---------------------------------------------------------------------- */

/** マネーフォワード契約の締結ステータス（値 => ラベル）。 */
function apprex_mf_statuses() {
	return array(
		'none'     => '未送付',
		'sent'     => '送付済（締結待ち）',
		'signed'   => '締結済',
		'rejected' => '却下／取消',
	);
}

/** 締結ステータスのラベル。 */
function apprex_mf_status_label( $key ) {
	$s = apprex_mf_statuses();
	return isset( $s[ $key ] ) ? $s[ $key ] : '未送付';
}

/* -------------------------------------------------------------------------
 * 契約書テンプレート
 * ---------------------------------------------------------------------- */

/** 差し込みプレースホルダの説明（テンプレート編集画面で表示）。 */
function apprex_contract_placeholders() {
	return array(
		'{{contract_id}}' => '契約ID',
		'{{name}}'        => 'お名前',
		'{{company}}'     => '会社名',
		'{{email}}'       => 'メール',
		'{{member_type}}' => '会員種別',
		'{{service}}'     => 'サービス',
		'{{plan}}'        => 'プラン',
		'{{monthly}}'     => '月額（数字のみ）',
		'{{monthly_yen}}' => '月額（¥表記）',
		'{{start}}'       => '契約開始日',
		'{{term}}'        => '契約年数',
		'{{renewal}}'     => '次回更新日',
		'{{provider}}'    => '事業者情報（甲）',
		'{{today}}'       => '本日の日付',
	);
}

/** 事業者情報（甲）の既定値。 */
function apprex_contract_provider_default() {
	$name = get_bloginfo( 'name' );
	return "{$name}（以下「甲」という。）";
}

/** 契約書テンプレートの既定値（条項のひな形）。 */
function apprex_contract_template_default() {
	return implode(
		"\n",
		array(
			'<h1>サービス利用契約書</h1>',
			'',
			'<p>{{provider}} と {{company}} {{name}}（以下「乙」という。）は、甲が提供するサービスの利用に関し、以下のとおり契約（以下「本契約」という。）を締結する。</p>',
			'',
			'<h2>第1条（契約内容）</h2>',
			'<p>甲は乙に対し、次の内容のサービスを提供する。</p>',
			'<ul>',
			'<li>サービス：{{service}}</li>',
			'<li>プラン：{{plan}}</li>',
			'<li>利用料金：月額 {{monthly_yen}}（税抜）</li>',
			'</ul>',
			'',
			'<h2>第2条（契約期間）</h2>',
			'<p>本契約の有効期間は、{{start}} から {{term}} 年間とし、次回更新日は {{renewal}} とする。期間満了の1か月前までに甲乙いずれからも書面による申し出がない場合、本契約は同一条件でさらに1年間更新されるものとし、以後も同様とする。</p>',
			'',
			'<h2>第3条（利用料金の支払い）</h2>',
			'<p>乙は、前条の利用料金を、甲が別途指定する方法により支払うものとする。</p>',
			'',
			'<h2>第4条（秘密保持）</h2>',
			'<p>甲および乙は、本契約に関して知り得た相手方の業務上・技術上の秘密を、相手方の事前の書面による承諾なく第三者に開示・漏洩してはならない。</p>',
			'',
			'<h2>第5条（解約）</h2>',
			'<p>乙が本契約を解約する場合、解約希望日の1か月前までに甲に通知するものとする。</p>',
			'',
			'<h2>第6条（協議事項）</h2>',
			'<p>本契約に定めのない事項、または本契約の解釈に疑義が生じた事項については、甲乙誠意をもって協議のうえ解決する。</p>',
			'',
			'<p>本契約締結の証として本書を作成し、甲乙記名押印（電子署名を含む）のうえ、各自その1通を保有する。</p>',
			'',
			'<p style="text-align:right;">作成日：{{today}}</p>',
			'',
			'<div class="apprex-doc-sign">',
			'<p>甲：{{provider}}</p>',
			'<p>乙：{{company}}　{{name}}　様</p>',
			'</div>',
		)
	);
}

/* -------------------------------------------------------------------------
 * テンプレート編集ページ（契約メニュー配下）
 * ---------------------------------------------------------------------- */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'edit.php?post_type=apprex_contract',
		'契約書テンプレート',
		'契約書テンプレート',
		'manage_options',
		'apprex-contract-template',
		'apprex_contract_template_page'
	);
} );

add_action( 'admin_init', function () {
	register_setting( 'apprex_contract_doc', 'apprex_contract_doc_title', array( 'sanitize_callback' => 'sanitize_text_field' ) );
	register_setting( 'apprex_contract_doc', 'apprex_contract_provider', array( 'sanitize_callback' => 'wp_kses_post' ) );
	register_setting( 'apprex_contract_doc', 'apprex_contract_template', array( 'sanitize_callback' => 'wp_kses_post' ) );
} );

/** 契約書テンプレート編集画面。 */
function apprex_contract_template_page() {
	$title    = get_option( 'apprex_contract_doc_title', 'サービス利用契約書' );
	$provider = get_option( 'apprex_contract_provider', '' );
	$tpl      = get_option( 'apprex_contract_template', '' );
	if ( '' === $provider ) {
		$provider = apprex_contract_provider_default();
	}
	if ( '' === $tpl ) {
		$tpl = apprex_contract_template_default();
	}
	?>
	<div class="wrap">
		<h1>契約書テンプレート</h1>
		<p>各契約の情報を差し込んで契約書を自動生成します。締結は「マネーフォワード クラウド契約」で行い、締結ページURL・状況・署名済みPDFを各契約に記録します。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_contract_doc' ); ?>
			<table class="form-table">
				<tr>
					<th scope="row"><label for="apprex_contract_doc_title">タイトル</label></th>
					<td><input type="text" id="apprex_contract_doc_title" name="apprex_contract_doc_title" class="regular-text" value="<?php echo esc_attr( $title ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_contract_provider">事業者情報（甲）</label></th>
					<td>
						<textarea id="apprex_contract_provider" name="apprex_contract_provider" rows="3" class="large-text"><?php echo esc_textarea( $provider ); ?></textarea>
						<p class="description">自社（甲）の名称・住所・代表者など。テンプレ内の <code>{{provider}}</code> に差し込まれます。</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="apprex_contract_template">契約書本文</label></th>
					<td>
						<textarea id="apprex_contract_template" name="apprex_contract_template" rows="22" class="large-text code"><?php echo esc_textarea( $tpl ); ?></textarea>
						<p class="description">HTML（見出し <code>&lt;h2&gt;</code>、段落 <code>&lt;p&gt;</code>、箇条書き <code>&lt;ul&gt;&lt;li&gt;</code> 等）が使えます。</p>
						<p class="description"><strong>差し込みタグ：</strong>
							<?php
							$chips = array();
							foreach ( apprex_contract_placeholders() as $tag => $desc ) {
								$chips[] = '<code>' . esc_html( $tag ) . '</code>＝' . esc_html( $desc );
							}
							echo wp_kses_post( implode( '　／　', $chips ) );
							?>
						</p>
					</td>
				</tr>
			</table>
			<?php submit_button( '保存' ); ?>
		</form>
	</div>
	<?php
}

/* -------------------------------------------------------------------------
 * 契約書の生成・表示
 * ---------------------------------------------------------------------- */

/** 契約書表示用URL（フロント、権限チェック付きエンドポイント）。 */
function apprex_contract_doc_url( $contract_id ) {
	return add_query_arg( 'apprex_doc', (int) $contract_id, home_url( '/' ) );
}

/** この契約書を閲覧してよいか（管理者 or 本人）。 */
function apprex_can_view_contract_doc( $contract_id ) {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$u = wp_get_current_user();
	if ( (int) get_post_meta( $contract_id, 'apprex_c_user_id', true ) === (int) $u->ID ) {
		return true;
	}
	$email = (string) get_post_meta( $contract_id, 'apprex_c_email', true );
	return $email && strtolower( $email ) === strtolower( $u->user_email );
}

/**
 * テンプレートに契約情報を差し込んで本文HTMLを返す。
 *
 * @param int $contract_id 契約ID。
 * @return string
 */
function apprex_contract_doc_body( $contract_id ) {
	$m = function ( $k ) use ( $contract_id ) {
		return (string) get_post_meta( $contract_id, $k, true );
	};
	$tpl = get_option( 'apprex_contract_template', '' );
	if ( '' === $tpl ) {
		$tpl = apprex_contract_template_default();
	}
	$provider = get_option( 'apprex_contract_provider', '' );
	if ( '' === $provider ) {
		$provider = apprex_contract_provider_default();
	}
	$mtype   = $m( 'apprex_c_member_type' );
	$monthly = (int) $m( 'apprex_c_monthly' );
	$map     = array(
		'{{contract_id}}' => (string) $contract_id,
		'{{name}}'        => $m( 'apprex_c_name' ),
		'{{company}}'     => $m( 'apprex_c_company' ),
		'{{email}}'       => $m( 'apprex_c_email' ),
		'{{member_type}}' => $mtype && function_exists( 'apprex_member_type_label' ) ? apprex_member_type_label( $mtype ) : '',
		'{{service}}'     => $m( 'apprex_c_service' ),
		'{{plan}}'        => $m( 'apprex_c_plan' ),
		'{{monthly}}'     => (string) $monthly,
		'{{monthly_yen}}' => '¥' . number_format( $monthly ),
		'{{start}}'       => $m( 'apprex_c_start' ),
		'{{term}}'        => $m( 'apprex_c_term' ) ? $m( 'apprex_c_term' ) . '年' : '',
		'{{renewal}}'     => $m( 'apprex_c_renewal' ),
		'{{provider}}'    => $provider,
		'{{today}}'       => wp_date( 'Y年n月j日' ),
	);
	return strtr( $tpl, $map );
}

/** 契約書のスタンドアロンHTML（A4印刷最適化）を出力。 */
function apprex_render_contract_document( $contract_id ) {
	$title  = get_option( 'apprex_contract_doc_title', 'サービス利用契約書' );
	$body   = apprex_contract_doc_body( $contract_id );
	$status = get_post_meta( $contract_id, 'apprex_c_mf_status', true );
	$signed = 'signed' === $status;
	$sdate  = get_post_meta( $contract_id, 'apprex_c_mf_signed_at', true );
	header( 'Content-Type: text/html; charset=UTF-8' );
	?>
<!doctype html>
<html lang="ja">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex,nofollow">
	<title><?php echo esc_html( $title ); ?></title>
	<style>
		*{box-sizing:border-box}
		body{font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;color:#111827;line-height:1.9;margin:0;background:#f3f4f6}
		.apprex-doc-toolbar{position:sticky;top:0;background:#111827;color:#fff;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;gap:10px}
		.apprex-doc-toolbar button{background:#2563eb;color:#fff;border:0;border-radius:6px;padding:8px 18px;font-size:14px;cursor:pointer}
		.apprex-doc-sheet{max-width:820px;margin:18px auto;background:#fff;padding:48px 56px;box-shadow:0 1px 6px rgba(0,0,0,.12)}
		.apprex-doc-sheet h1{font-size:24px;text-align:center;margin:0 0 28px;letter-spacing:.1em}
		.apprex-doc-sheet h2{font-size:16px;margin:24px 0 6px;border-left:4px solid #2563eb;padding-left:10px}
		.apprex-doc-sheet ul{margin:6px 0 6px 1.2em}
		.apprex-doc-sign{margin-top:40px;padding-top:18px;border-top:1px solid #d1d5db}
		.apprex-doc-stamp{display:inline-block;margin-top:10px;color:#16a34a;border:2px solid #16a34a;border-radius:8px;padding:6px 14px;font-weight:bold;transform:rotate(-4deg)}
		@media print{body{background:#fff}.apprex-doc-toolbar{display:none}.apprex-doc-sheet{box-shadow:none;margin:0;max-width:none;padding:0}@page{margin:18mm}}
	</style>
</head>
<body>
	<div class="apprex-doc-toolbar">
		<span>契約書プレビュー（「PDFで保存」から保存できます）</span>
		<button type="button" onclick="window.print()">🖨 印刷 / PDFで保存</button>
	</div>
	<div class="apprex-doc-sheet">
		<?php echo wp_kses_post( $body ); ?>
		<?php if ( $signed ) : ?>
			<p class="apprex-doc-stamp">電子締結済<?php echo $sdate ? '（' . esc_html( $sdate ) . '）' : ''; ?></p>
		<?php endif; ?>
	</div>
</body>
</html>
	<?php
}

/** フロントの契約書エンドポイント（?apprex_doc=契約ID）。 */
add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['apprex_doc'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$id = absint( $_GET['apprex_doc'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $id || 'apprex_contract' !== get_post_type( $id ) ) {
		wp_die( '契約書が見つかりません。' );
	}
	if ( ! apprex_can_view_contract_doc( $id ) ) {
		auth_redirect();
	}
	apprex_render_contract_document( $id );
	exit;
} );

/* -------------------------------------------------------------------------
 * 保存：マネーフォワード連携フィールド＋締結時の確認メール
 * ---------------------------------------------------------------------- */
add_action( 'save_post_apprex_contract', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['apprex_contract_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apprex_contract_nonce'] ) ), 'apprex_contract_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$old_status = get_post_meta( $post_id, 'apprex_c_mf_status', true );
	$new_status = isset( $_POST['apprex_c_mf_status'] ) ? sanitize_text_field( wp_unslash( $_POST['apprex_c_mf_status'] ) ) : 'none';
	if ( ! array_key_exists( $new_status, apprex_mf_statuses() ) ) {
		$new_status = 'none';
	}
	update_post_meta( $post_id, 'apprex_c_mf_status', $new_status );
	update_post_meta( $post_id, 'apprex_c_mf_url', isset( $_POST['apprex_c_mf_url'] ) ? esc_url_raw( wp_unslash( $_POST['apprex_c_mf_url'] ) ) : '' );
	update_post_meta( $post_id, 'apprex_c_mf_signed_pdf', isset( $_POST['apprex_c_mf_signed_pdf'] ) ? esc_url_raw( wp_unslash( $_POST['apprex_c_mf_signed_pdf'] ) ) : '' );
	update_post_meta( $post_id, 'apprex_c_mf_signed_at', isset( $_POST['apprex_c_mf_signed_at'] ) ? sanitize_text_field( wp_unslash( $_POST['apprex_c_mf_signed_at'] ) ) : '' );

	// 締結済みへ切り替わった時だけ、会員へ確認メールを一度送る。
	if ( 'signed' === $new_status && 'signed' !== $old_status ) {
		apprex_notify_contract_signed( $post_id );
	}
} );

/** 締結完了の確認メールを会員へ送信。 */
function apprex_notify_contract_signed( $contract_id ) {
	$email = get_post_meta( $contract_id, 'apprex_c_email', true );
	if ( ! is_email( $email ) ) {
		return;
	}
	$name   = get_post_meta( $contract_id, 'apprex_c_name', true );
	$mypage = function_exists( 'apprex_mypage_url' ) ? apprex_mypage_url() : home_url( '/mypage/' );
	$pdf    = get_post_meta( $contract_id, 'apprex_c_mf_signed_pdf', true );

	$body  = "{$name} 様\n\n契約の電子締結が完了しました。ありがとうございます。\n\n";
	$body .= "マイページから契約書をご確認いただけます：\n{$mypage}\n";
	if ( $pdf ) {
		$body .= "\n署名済み契約書（PDF）：\n{$pdf}\n";
	}
	$body .= "\n今後ともよろしくお願いいたします。\n";

	$subject = '【APPREX】契約締結完了のお知らせ';
	$html    = function_exists( 'apprex_render_email' )
		? apprex_render_email( $subject, $body, array( 'heading' => '契約締結完了のお知らせ' ) )
		: nl2br( esc_html( $body ) );
	wp_mail( $email, $subject, $html, function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array( 'Content-Type: text/html; charset=UTF-8' ) );
}

/* -------------------------------------------------------------------------
 * 一覧に「締結」カラムを追加
 * ---------------------------------------------------------------------- */
add_filter( 'manage_apprex_contract_posts_columns', function ( $cols ) {
	$new = array();
	foreach ( $cols as $k => $v ) {
		$new[ $k ] = $v;
		if ( 'cstatus' === $k ) {
			$new['mfstatus'] = __( '締結', 'apprex' );
		}
	}
	return $new;
} );

add_action( 'manage_apprex_contract_posts_custom_column', function ( $col, $post_id ) {
	if ( 'mfstatus' === $col ) {
		echo esc_html( apprex_mf_status_label( get_post_meta( $post_id, 'apprex_c_mf_status', true ) ) );
	}
}, 10, 2 );
