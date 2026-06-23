<?php
/**
 * 請求書PDF（API不要・WordPress内で発行）。
 *
 * 契約レコードの情報から、ブラウザで「請求書」を表示し、印刷→PDF保存できる。
 * 発行元（自社）情報・振込先は 設定 → APPREX 請求書(自社/振込先) で入力。
 *
 * 表示URL：?apprex_invoice=<契約ID>&period=YYYY-MM（権限：管理者 or 本人）
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* =========================================================================
 * 設定（発行元・振込先）
 * ====================================================================== */
add_action( 'admin_menu', function () {
	add_options_page( 'APPREX 請求書(自社/振込先)', 'APPREX 請求書(自社)', 'manage_options', 'apprex-invoice', 'apprex_invoice_settings_page' );
} );
add_action( 'admin_init', function () {
	foreach ( array(
		'apprex_inv_company'    => 'sanitize_text_field',
		'apprex_inv_issuer'     => 'sanitize_textarea_field',
		'apprex_inv_invoice_no' => 'sanitize_text_field',
		'apprex_inv_bank'       => 'sanitize_textarea_field',
		'apprex_inv_tax'        => 'absint',
		'apprex_inv_note'       => 'sanitize_textarea_field',
		'apprex_inv_seal'       => 'esc_url_raw',
	) as $opt => $cb ) {
		register_setting( 'apprex_invoice', $opt, array( 'sanitize_callback' => $cb ) );
	}
} );

function apprex_inv_opt( $k, $default = '' ) {
	$v = get_option( $k, '' );
	return '' !== $v ? $v : $default;
}

function apprex_invoice_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1>APPREX 請求書（発行元・振込先）</h1>
		<p>ここで設定した内容が、各契約の「請求書PDF」に差し込まれます（API不要）。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'apprex_invoice' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr><th>会社名（発行元）</th><td><input type="text" name="apprex_inv_company" class="regular-text" value="<?php echo esc_attr( apprex_inv_opt( 'apprex_inv_company', '合同会社アイズ' ) ); ?>"></td></tr>
				<tr><th>発行元 情報（住所・連絡先）</th><td><textarea name="apprex_inv_issuer" rows="4" class="large-text" placeholder="〒000-0000&#10;東京都〇〇区〇〇1-2-3&#10;TEL: 00-0000-0000 / Mail: info@aisjaltd.com"><?php echo esc_textarea( apprex_inv_opt( 'apprex_inv_issuer' ) ); ?></textarea></td></tr>
				<tr><th>インボイス登録番号</th><td><input type="text" name="apprex_inv_invoice_no" class="regular-text" value="<?php echo esc_attr( apprex_inv_opt( 'apprex_inv_invoice_no' ) ); ?>" placeholder="T0000000000000"></td></tr>
				<tr><th>振込先</th><td><textarea name="apprex_inv_bank" rows="4" class="large-text" placeholder="〇〇銀行 〇〇支店 普通 1234567&#10;名義：ゴウドウガイシャアイズ"><?php echo esc_textarea( apprex_inv_opt( 'apprex_inv_bank' ) ); ?></textarea></td></tr>
				<tr><th>消費税率（％）</th><td><input type="number" name="apprex_inv_tax" value="<?php echo esc_attr( (int) apprex_inv_opt( 'apprex_inv_tax', 10 ) ); ?>" min="0" max="20" style="width:80px;"></td></tr>
				<tr><th>備考（任意）</th><td><textarea name="apprex_inv_note" rows="2" class="large-text" placeholder="お振込手数料は貴社にてご負担をお願いいたします。"><?php echo esc_textarea( apprex_inv_opt( 'apprex_inv_note' ) ); ?></textarea></td></tr>
				<tr><th>社印画像URL（任意）</th><td><input type="url" name="apprex_inv_seal" class="regular-text" value="<?php echo esc_attr( apprex_inv_opt( 'apprex_inv_seal' ) ); ?>" placeholder="https://… 角印などの画像（任意）"></td></tr>
			</tbody></table>
			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}

/* =========================================================================
 * 契約編集に「請求書PDF」ボタン
 * ====================================================================== */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'apprex_invoice_box', '請求書PDF（振込）', 'apprex_invoice_box', 'apprex_contract', 'side', 'default' );
} );
function apprex_invoice_box( $post ) {
	$url = add_query_arg(
		array(
			'apprex_invoice' => $post->ID,
			'period'         => current_time( 'Y-m' ),
		),
		home_url( '/' )
	);
	echo '<p>当月分の請求書を表示します（印刷→PDF保存）。</p>';
	echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="button button-primary" style="width:100%;text-align:center;">請求書PDFを表示</a>';
	if ( ! get_option( 'apprex_inv_bank' ) ) {
		echo '<p class="description" style="margin-top:8px;color:#b91c1c;">※ <a href="' . esc_url( admin_url( 'options-general.php?page=apprex-invoice' ) ) . '">発行元・振込先</a>を先に設定してください。</p>';
	} else {
		echo '<p class="description" style="margin-top:8px;">月額・会社名・支払日を保存しておいてください。</p>';
	}
}

/* =========================================================================
 * 表示（印刷可能な請求書HTML）
 * ====================================================================== */

/** 閲覧権限：管理者 or 本人。 */
function apprex_invoice_can_view( $id ) {
	if ( current_user_can( 'manage_options' ) ) {
		return true;
	}
	if ( ! is_user_logged_in() ) {
		return false;
	}
	$u = wp_get_current_user();
	if ( (int) get_post_meta( $id, 'apprex_c_user_id', true ) === (int) $u->ID ) {
		return true;
	}
	$email = (string) get_post_meta( $id, 'apprex_c_email', true );
	return $email && strtolower( $email ) === strtolower( $u->user_email );
}

add_action( 'template_redirect', function () {
	if ( ! isset( $_GET['apprex_invoice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return;
	}
	$id = absint( $_GET['apprex_invoice'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( ! $id || 'apprex_contract' !== get_post_type( $id ) ) {
		wp_die( '契約が見つかりません。' );
	}
	if ( ! apprex_invoice_can_view( $id ) ) {
		auth_redirect();
	}
	$period = isset( $_GET['period'] ) ? sanitize_text_field( wp_unslash( $_GET['period'] ) ) : current_time( 'Y-m' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	apprex_render_invoice( $id, $period );
	exit;
} );

function apprex_yen( $n ) {
	return '¥' . number_format( (int) $n );
}

function apprex_render_invoice( $id, $period ) {
	$m = function ( $k ) use ( $id ) {
		return (string) get_post_meta( $id, $k, true );
	};
	$company  = $m( 'apprex_c_company' );
	$name     = $m( 'apprex_c_name' );
	$service  = trim( $m( 'apprex_c_service' ) . ' ' . $m( 'apprex_c_plan' ) );
	$monthly  = (int) $m( 'apprex_c_monthly' );
	$tax_pct  = (int) apprex_inv_opt( 'apprex_inv_tax', 10 );
	$subtotal = $monthly;
	$tax      = (int) floor( $subtotal * $tax_pct / 100 );
	$total    = $subtotal + $tax;

	$ym       = preg_match( '/^\d{4}-\d{2}$/', $period ) ? $period : current_time( 'Y-m' );
	$ym_ts    = strtotime( $ym . '-01' );
	$period_label = wp_date( 'Y年n月', $ym_ts );
	$issue_date   = wp_date( 'Y年n月j日' );
	$pay_day      = (int) $m( 'apprex_c_payment_day' );
	$due          = function_exists( 'apprex_mf_due_date' ) ? apprex_mf_due_date( $pay_day ? $pay_day : 27 ) : wp_date( 'Y-m-d' );
	$due_label    = wp_date( 'Y年n月j日', strtotime( $due ) );
	$inv_no       = 'INV-' . str_replace( '-', '', $ym ) . '-' . $id;

	$issuer_company = apprex_inv_opt( 'apprex_inv_company', '合同会社アイズ' );
	$issuer_info    = apprex_inv_opt( 'apprex_inv_issuer' );
	$invoice_no     = apprex_inv_opt( 'apprex_inv_invoice_no' );
	$bank           = apprex_inv_opt( 'apprex_inv_bank' );
	$note           = apprex_inv_opt( 'apprex_inv_note' );
	$seal           = apprex_inv_opt( 'apprex_inv_seal' );

	header( 'Content-Type: text/html; charset=UTF-8' );
	?>
<!doctype html><html lang="ja"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>請求書 <?php echo esc_html( $inv_no ); ?></title>
<style>
*{box-sizing:border-box}
body{font-family:"Hiragino Kaku Gothic ProN","Yu Gothic",Meiryo,sans-serif;color:#1f2937;background:#f3f4f6;margin:0;padding:24px}
.sheet{max-width:760px;margin:0 auto;background:#fff;padding:40px 44px;box-shadow:0 1px 6px rgba(0,0,0,.1)}
.bar{max-width:760px;margin:0 auto 12px;display:flex;justify-content:flex-end;gap:8px}
.bar button{background:#2563eb;color:#fff;border:0;border-radius:8px;padding:10px 18px;font-size:14px;font-weight:700;cursor:pointer}
h1{text-align:center;letter-spacing:.4em;font-size:28px;margin:0 0 28px;border-bottom:3px double #1f2937;padding-bottom:10px}
.top{display:flex;justify-content:space-between;gap:24px}
.to{font-size:15px}.to .big{font-size:20px;font-weight:700;border-bottom:1px solid #1f2937;padding-bottom:4px;display:inline-block;margin-bottom:6px}
.meta{font-size:13px;text-align:right;color:#374151}
.issuer{font-size:13px;text-align:right;margin-top:14px;line-height:1.7;position:relative}
.issuer .nm{font-weight:700;font-size:15px}
.seal{position:absolute;right:-6px;top:-6px;width:64px;height:64px;opacity:.85}
.amount{margin:24px 0;padding:14px 18px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;display:flex;justify-content:space-between;align-items:center}
.amount .lbl{font-weight:700}.amount .val{font-size:26px;font-weight:800;color:#1d4ed8}
table{width:100%;border-collapse:collapse;margin:18px 0;font-size:14px}
th,td{border:1px solid #cbd5e1;padding:10px 12px}
th{background:#1e3a8a;color:#fff;font-weight:700}
td.r,th.r{text-align:right}
.sum{width:300px;margin-left:auto;font-size:14px}
.sum td{border:0;border-bottom:1px solid #e5e7eb;padding:6px 4px}
.sum .total td{border-top:2px solid #1f2937;font-weight:800;font-size:16px}
.box{margin-top:20px;font-size:13px;border:1px solid #e5e7eb;border-radius:8px;padding:12px 14px;white-space:pre-wrap;line-height:1.8}
.box h3{margin:0 0 6px;font-size:13px;color:#374151}
@media print{body{background:#fff;padding:0}.bar{display:none}.sheet{box-shadow:none;max-width:none}}
</style></head>
<body>
<div class="bar"><button onclick="window.print()">🖨 印刷 / PDFで保存</button></div>
<div class="sheet">
	<h1>請 求 書</h1>
	<div class="top">
		<div class="to">
			<div class="big"><?php echo esc_html( $company ? $company : $name ); ?> 御中</div>
			<?php if ( $company && $name ) : ?><div><?php echo esc_html( $name ); ?> 様</div><?php endif; ?>
			<p style="margin:16px 0 0;">下記のとおりご請求申し上げます。</p>
		</div>
		<div>
			<div class="meta">
				請求書番号：<?php echo esc_html( $inv_no ); ?><br>
				発行日：<?php echo esc_html( $issue_date ); ?>
			</div>
			<div class="issuer">
				<?php if ( $seal ) : ?><img class="seal" src="<?php echo esc_url( $seal ); ?>" alt="印"><?php endif; ?>
				<div class="nm"><?php echo esc_html( $issuer_company ); ?></div>
				<?php echo nl2br( esc_html( $issuer_info ) ); ?>
				<?php if ( $invoice_no ) : ?><br>登録番号：<?php echo esc_html( $invoice_no ); ?><?php endif; ?>
			</div>
		</div>
	</div>

	<div class="amount">
		<span class="lbl">ご請求金額（税込）</span>
		<span class="val"><?php echo esc_html( apprex_yen( $total ) ); ?></span>
	</div>
	<p style="font-size:14px;margin:0 0 6px;">お支払期限：<strong><?php echo esc_html( $due_label ); ?></strong></p>

	<table>
		<thead><tr><th>品目</th><th class="r" style="width:80px;">数量</th><th class="r" style="width:120px;">単価</th><th class="r" style="width:130px;">金額</th></tr></thead>
		<tbody>
			<tr>
				<td><?php echo esc_html( ( $service ? $service : 'APPREX' ) . ' 月額利用料（' . $period_label . '分）' ); ?></td>
				<td class="r">1</td>
				<td class="r"><?php echo esc_html( apprex_yen( $monthly ) ); ?></td>
				<td class="r"><?php echo esc_html( apprex_yen( $monthly ) ); ?></td>
			</tr>
		</tbody>
	</table>

	<table class="sum">
		<tr><td>小計（税抜）</td><td class="r"><?php echo esc_html( apprex_yen( $subtotal ) ); ?></td></tr>
		<tr><td>消費税（<?php echo (int) $tax_pct; ?>%）</td><td class="r"><?php echo esc_html( apprex_yen( $tax ) ); ?></td></tr>
		<tr class="total"><td>合計</td><td class="r"><?php echo esc_html( apprex_yen( $total ) ); ?></td></tr>
	</table>

	<?php if ( $bank ) : ?>
		<div class="box"><h3>お振込先</h3><?php echo esc_html( $bank ); ?></div>
	<?php endif; ?>
	<?php if ( $note ) : ?>
		<div class="box"><h3>備考</h3><?php echo esc_html( $note ); ?></div>
	<?php endif; ?>
</div>
</body></html>
	<?php
}
