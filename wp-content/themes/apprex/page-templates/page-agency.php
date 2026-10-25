<?php
/**
 * Template Name: 代理店専用ページ
 *
 * 代理店がログインして、紹介URL・ご紹介の状況・報酬の明細・営業資料・お客様の登録を使う。
 * 本部（編集者以上）は ?as=代理店ID で、その代理店の画面を確認できる。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$apprex_aid = function_exists( 'apprex_agency_for_user' ) ? apprex_agency_for_user() : 0;
if ( current_user_can( 'edit_posts' ) && isset( $_GET['as'] ) ) {
	$apprex_aid = absint( $_GET['as'] );
}

get_header();
?>
<section class="page-hero">
	<div class="container">
		<nav class="breadcrumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'ホーム', 'apprex' ); ?></a></nav>
		<h1><?php esc_html_e( '代理店専用ページ', 'apprex' ); ?></h1>
		<p><?php esc_html_e( '紹介URL、ご紹介の状況、報酬の明細、営業資料をここで確認できます。', 'apprex' ); ?></p>
	</div>
</section>

<section class="section agp">
	<div class="container">
	<?php if ( ! is_user_logged_in() ) : ?>
		<div class="agp-card agp-login">
			<h2><?php esc_html_e( 'ログイン', 'apprex' ); ?></h2>
			<p><?php esc_html_e( '本部からお送りした案内メールのログインIDとパスワードでログインしてください。', 'apprex' ); ?></p>
			<?php wp_login_form( array( 'redirect' => apprex_agency_portal_url(), 'label_username' => 'ログインID またはメールアドレス', 'label_password' => 'パスワード', 'label_remember' => 'ログインしたままにする', 'label_log_in' => 'ログイン' ) ); ?>
			<p><a href="<?php echo esc_url( wp_lostpassword_url( apprex_agency_portal_url() ) ); ?>"><?php esc_html_e( 'パスワードを忘れた方', 'apprex' ); ?></a>　／　<a href="<?php echo esc_url( home_url( '/partner/' ) ); ?>"><?php esc_html_e( '代理店のお申し込みはこちら', 'apprex' ); ?></a></p>
		</div>
	<?php elseif ( ! $apprex_aid || 'apprex_agency' !== get_post_type( $apprex_aid ) ) : ?>
		<div class="agp-card"><p><?php esc_html_e( 'このアカウントは代理店として登録されていません。代理店のお申し込みは、代理店募集のページからどうぞ。', 'apprex' ); ?></p><p><a class="btn btn--primary" href="<?php echo esc_url( home_url( '/partner/' ) ); ?>"><?php esc_html_e( '代理店募集を見る', 'apprex' ); ?></a></p></div>
	<?php else : ?>
		<?php
		$v      = apprex_agency_get( $apprex_aid );
		$types  = apprex_agency_types();
		$url    = add_query_arg( 'ref', $v['code'], home_url( '/' ) );
		$refs   = apprex_agency_referrals( $v['code'] );
		$now    = current_time( 'Y-m' );
		$cm     = apprex_agency_commission( $apprex_aid, $now );
		$paid   = apprex_agency_paid( $apprex_aid );
		$con    = count( array_filter( $refs, function ( $r ) {
			return 'contract' === $r['stage'];
		} ) );
		$news   = trim( (string) get_option( 'apprex_agency_news', '' ) );
		$active = 'active' === $v['status'];
		?>
		<?php if ( isset( $_GET['as'] ) ) : ?>
			<p class="agp-note"><?php echo esc_html( '本部の確認用表示：' . get_the_title( $apprex_aid ) ); ?></p>
		<?php endif; ?>
		<?php if ( ! $active ) : ?>
			<div class="agp-card agp-warn"><b><?php echo esc_html( apprex_agency_statuses()[ $v['status'] ] ); ?></b>　<?php esc_html_e( '本部で登録内容を確認しています。有効になると、紹介URLが使えるようになります。', 'apprex' ); ?></div>
		<?php endif; ?>
		<?php if ( $news ) : ?>
			<div class="agp-card agp-news"><h2><?php esc_html_e( '本部からのお知らせ', 'apprex' ); ?></h2><p><?php echo nl2br( esc_html( $news ) ); ?></p></div>
		<?php endif; ?>

		<div class="agp-stats">
			<div class="agp-stat"><small><?php esc_html_e( '代理店の種類', 'apprex' ); ?></small><b><?php echo esc_html( $types[ $v['type'] ] ); ?></b></div>
			<div class="agp-stat"><small><?php esc_html_e( '紹介したお客様', 'apprex' ); ?></small><b><?php echo count( $refs ); ?><span>件</span></b></div>
			<div class="agp-stat"><small><?php esc_html_e( '契約中', 'apprex' ); ?></small><b><?php echo (int) $con; ?><span>件</span></b></div>
			<div class="agp-stat"><small><?php esc_html_e( '今月の報酬（見込み）', 'apprex' ); ?></small><b><?php echo esc_html( number_format( $cm['total'] ) ); ?><span>円</span></b></div>
		</div>

		<div class="agp-grid">
			<div class="agp-card">
				<h2><?php esc_html_e( 'あなたの紹介URL', 'apprex' ); ?></h2>
				<p class="agp-url"><input type="text" readonly value="<?php echo esc_attr( $url ); ?>" id="agpUrl" onclick="this.select()"><button type="button" class="btn btn--ghost" id="agpCopy"><?php esc_html_e( 'コピー', 'apprex' ); ?></button></p>
				<div class="agp-qr" id="agpQr" aria-label="紹介URLのQRコード"></div>
				<p class="agp-small"><?php esc_html_e( 'このURL（またはQRコード）から届いたお問い合わせ・AIスタジオの会員登録・ご契約は、あなたのご紹介として90日間記録されます。チラシや名刺にQRコードを載せてもお使いいただけます。', 'apprex' ); ?></p>
				<p class="agp-small"><b><?php esc_html_e( 'ページごとのURL', 'apprex' ); ?></b><br>
					<?php foreach ( array( '料金' => '/pricing/', '見積もり' => '/estimate/', '無料体験' => '/free-trial/', '資料請求' => '/document/' ) as $l => $pth ) : ?>
						<?php echo esc_html( $l ); ?>：<code><?php echo esc_html( add_query_arg( 'ref', $v['code'], home_url( $pth ) ) ); ?></code><br>
					<?php endforeach; ?>
				</p>
			</div>
			<div class="agp-card">
				<h2><?php esc_html_e( 'あなたの報酬の条件', 'apprex' ); ?></h2>
				<table class="agp-table">
					<tr><th><?php esc_html_e( '初期費用', 'apprex' ); ?></th><td><?php echo esc_html( $v['rate_initial'] ); ?>%</td></tr>
					<tr><th><?php esc_html_e( '月額利用料', 'apprex' ); ?></th><td><?php echo esc_html( $v['rate_monthly'] ); ?>%<?php echo $v['months'] ? esc_html( '（契約開始から' . $v['months'] . 'か月）' ) : esc_html__( '（契約が続くあいだ）', 'apprex' ); ?></td></tr>
					<tr><th><?php esc_html_e( 'お支払い', 'apprex' ); ?></th><td><?php esc_html_e( '月末締め・翌月末払い（銀行振込）', 'apprex' ); ?></td></tr>
					<tr><th><?php esc_html_e( '加盟金', 'apprex' ); ?></th><td><?php echo (int) $v['fee'] > 0 ? esc_html( number_format( (int) $v['fee'] ) . '円（税抜）・' . ( $v['fee_paid'] ? '入金済み（' . $v['fee_paid'] . '）' : 'お支払い待ち' ) ) : esc_html__( 'なし', 'apprex' ); ?></td></tr>
				</table>
				<p class="agp-small"><?php esc_html_e( '条件は代理店契約書に定めた内容が優先されます。振込先は、このページの下の「振込先」から登録・変更できます。', 'apprex' ); ?></p>
			</div>
		</div>

		<div class="agp-card">
			<h2><?php esc_html_e( 'ご紹介の状況', 'apprex' ); ?></h2>
			<?php if ( $refs ) : ?>
				<div class="agp-scroll"><table class="agp-table"><thead><tr><th><?php esc_html_e( 'お客様', 'apprex' ); ?></th><th><?php esc_html_e( '最初の日', 'apprex' ); ?></th><th><?php esc_html_e( '今の段階', 'apprex' ); ?></th></tr></thead><tbody>
				<?php foreach ( $refs as $r ) : ?>
					<tr><td><?php echo esc_html( $r['label'] ); ?></td><td><?php echo esc_html( $r['first'] ); ?></td><td><span class="agp-stage agp-stage--<?php echo esc_attr( $r['stage'] ); ?>"><?php echo esc_html( $r['stage_label'] ); ?></span></td></tr>
				<?php endforeach; ?>
				</tbody></table></div>
				<p class="agp-small"><?php esc_html_e( 'お客様の連絡先は表示しません。お名前は一部だけ表示しています。', 'apprex' ); ?></p>
			<?php else : ?>
				<p><?php esc_html_e( 'まだご紹介はありません。紹介URLをお客様にお送りください。', 'apprex' ); ?></p>
			<?php endif; ?>
		</div>

		<div class="agp-card">
			<h2><?php esc_html_e( '報酬の明細', 'apprex' ); ?></h2>
			<div class="agp-scroll"><table class="agp-table"><thead><tr><th><?php esc_html_e( '月', 'apprex' ); ?></th><th style="text-align:right"><?php esc_html_e( '報酬（税抜）', 'apprex' ); ?></th><th><?php esc_html_e( 'お支払い', 'apprex' ); ?></th><th><?php esc_html_e( '内訳', 'apprex' ); ?></th></tr></thead><tbody>
			<?php
			for ( $i = 0; $i < 12; $i++ ) :
				$ym = wp_date( 'Y-m', strtotime( "-{$i} month", strtotime( $now . '-01' ) ) );
				$c  = 0 === $i ? $cm : apprex_agency_commission( $apprex_aid, $ym );
				if ( ! $c['rows'] && $i > 0 ) {
					continue;
				}
				?>
				<tr><td><?php echo esc_html( wp_date( 'Y年n月', strtotime( $ym . '-01' ) ) . ( 0 === $i ? '（見込み）' : '' ) ); ?></td><td style="text-align:right"><b><?php echo esc_html( number_format( $c['total'] ) ); ?>円</b></td>
				<td><?php echo isset( $paid[ $ym ] ) ? esc_html( '支払い済み（' . $paid[ $ym ] . '）' ) : esc_html( $c['total'] > 0 ? wp_date( 'n月j日', strtotime( 'last day of +1 month', strtotime( $ym . '-01' ) ) ) . 'お支払い予定' : '—' ); ?></td>
				<td><?php if ( $c['rows'] ) : ?><details><summary><?php echo count( $c['rows'] ); ?>件</summary><ul><?php foreach ( $c['rows'] as $r ) : ?><li><?php echo esc_html( $r['label'] . '：' . $r['kind'] . ' ' . number_format( $r['base'] ) . '円 × ' . $r['rate'] . '% = ' . number_format( $r['amount'] ) . '円' ); ?></li><?php endforeach; ?></ul></details><?php endif; ?></td></tr>
			<?php endfor; ?>
			</tbody></table></div>
		</div>

		<div class="agp-grid">
			<div class="agp-card" id="lead">
				<h2><?php esc_html_e( 'お客様を登録する', 'apprex' ); ?></h2>
				<?php
				$lead = isset( $_GET['lead'] ) ? sanitize_key( $_GET['lead'] ) : '';
				if ( 'ok' === $lead ) {
					echo '<p class="agp-ok">' . esc_html__( '登録しました。本部の担当者がご連絡します。', 'apprex' ) . '</p>';
				} elseif ( 'clash' === $lead ) {
					echo '<p class="agp-warn">' . esc_html__( '登録しました。ただし、このお客様は別の紹介の記録があるため、本部で確認してからご連絡します。', 'apprex' ) . '</p>';
				} elseif ( 'empty' === $lead ) {
					echo '<p class="agp-warn">' . esc_html__( '会社名かお名前を入れてください。', 'apprex' ) . '</p>';
				}
				?>
				<p class="agp-small"><?php esc_html_e( '商談中・紹介したいお客様を本部に知らせます。登録したお客様は、あなたのご紹介として記録されます。', 'apprex' ); ?></p>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="agp-form">
					<input type="hidden" name="action" value="apprex_agency_lead">
					<?php wp_nonce_field( 'apprex_agency_lead' ); ?>
					<label><?php esc_html_e( '会社名・店名', 'apprex' ); ?><input type="text" name="company"></label>
					<label><?php esc_html_e( 'ご担当者名', 'apprex' ); ?><input type="text" name="name"></label>
					<label><?php esc_html_e( 'メール', 'apprex' ); ?><input type="email" name="email"></label>
					<label><?php esc_html_e( '電話', 'apprex' ); ?><input type="tel" name="phone"></label>
					<label><?php esc_html_e( 'メモ（作りたいもの・時期など）', 'apprex' ); ?><textarea name="memo" rows="3"></textarea></label>
					<button type="submit" class="btn btn--primary"<?php echo ( $active && ! isset( $_GET['as'] ) ) ? '' : ' disabled'; ?>><?php esc_html_e( '本部に登録する', 'apprex' ); ?></button>
				</form>
			</div>
			<div class="agp-card">
				<h2><?php esc_html_e( '営業資料・商談で使うもの', 'apprex' ); ?></h2>
				<ul class="agp-docs">
					<?php
					$docs = array();
					if ( function_exists( 'apprex_document_url' ) && apprex_document_url() ) {
						$docs[] = array( 'サービス資料', apprex_document_url() );
					}
					$docs[] = array( '料金プラン', home_url( '/pricing/' ) );
					$docs[] = array( '見積もり（その場で概算）', home_url( '/estimate/' ) );
					$docs[] = array( 'サンプルのデモアプリ（スマホで触れる）', function_exists( 'apprex_demo_url' ) ? apprex_demo_url( 'sample' ) : home_url( '/' ) );
					if ( function_exists( 'apprex_rn_public' ) && apprex_rn_public() ) {
						$docs[] = array( 'AIスタジオ（お客様と一緒に画面を作る）', apprex_studio_url() );
						$docs[] = array( '補助金のご案内', home_url( '/subsidy/' ) );
					}
					$docs[] = array( '代理店募集のページ', home_url( '/partner/' ) );
					foreach ( preg_split( '/\r?\n/', (string) get_option( 'apprex_agency_docs', '' ) ) as $line ) {
						$p = array_map( 'trim', explode( '|', $line, 2 ) );
						if ( 2 === count( $p ) && $p[0] && $p[1] ) {
							$docs[] = $p;
						}
					}
					foreach ( $docs as $d ) {
						echo '<li><a href="' . esc_url( $d[1] ) . '" target="_blank" rel="noopener">' . esc_html( $d[0] ) . '</a></li>';
					}
					?>
				</ul>
				<p class="agp-small"><b><?php esc_html_e( 'お客様に伝えてよい料金', 'apprex' ); ?></b><br><?php esc_html_e( 'ノーコードアプリ：初期費用15万円〜（AIスタジオで作った内容から制作）、月額19,800円〜。ホームページ：初期費用0円・月額9,800円〜。マッチングアプリ：開発費300万円〜・3ヶ月〜。正式な金額は本部がお見積りします。', 'apprex' ); ?></p>
				<p class="agp-small"><b><?php esc_html_e( 'お願い', 'apprex' ); ?></b><br><?php esc_html_e( '事実と違う説明や、「必ず儲かる」「No.1」などの表現はお控えください。', 'apprex' ); ?></p>
			</div>
		</div>
		<div class="agp-card" id="bank">
			<h2><?php esc_html_e( '振込先', 'apprex' ); ?></h2>
			<?php
			$bcur  = apprex_agency_bank_current( $apprex_aid );
			$bpend = apprex_agency_bank_pending( $apprex_aid );
			$bmsg  = isset( $_GET['bank'] ) ? sanitize_key( $_GET['bank'] ) : '';
			$bmsgs = array(
				'ok'      => array( 'agp-ok', '受け付けました。本部で確認したあとに反映します。反映するまでは、今の振込先にお振り込みします。' ),
				'pass'    => array( 'agp-warn', 'パスワードが違います。もう一度入れてください。' ),
				'invalid' => array( 'agp-warn', '銀行名・支店名・口座番号（6〜8桁の数字）・口座名義を入れてください。' ),
			);
			if ( isset( $bmsgs[ $bmsg ] ) ) {
				echo '<p class="' . esc_attr( $bmsgs[ $bmsg ][0] ) . '">' . esc_html( $bmsgs[ $bmsg ][1] ) . '</p>';
			}
			?>
			<p><b><?php esc_html_e( '今の振込先', 'apprex' ); ?></b>：<?php echo esc_html( $bcur ? apprex_agency_bank_masked( $bcur ) : ( $v['bank'] ? '登録済み（本部で登録）' : '未登録' ) ); ?></p>
			<?php if ( $bpend ) : ?>
				<p class="agp-warn"><?php echo esc_html( '確認中の変更：' . apprex_agency_bank_masked( $bpend ) . '（' . wp_date( 'n月j日', (int) $bpend['t'] ) . ' 受付）' ); ?></p>
			<?php endif; ?>
			<details<?php echo ( $bcur || $v['bank'] ) ? '' : ' open'; ?>>
				<summary><?php echo esc_html( ( $bcur || $v['bank'] ) ? '振込先を変更する' : '振込先を登録する' ); ?></summary>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="agp-form agp-bank" autocomplete="off">
					<input type="hidden" name="action" value="apprex_agency_bank">
					<?php wp_nonce_field( 'apprex_agency_bank' ); ?>
					<label><?php esc_html_e( '銀行名', 'apprex' ); ?><input type="text" name="bank" required placeholder="例：東邦銀行"></label>
					<label><?php esc_html_e( '支店名', 'apprex' ); ?><input type="text" name="branch" required placeholder="例：平支店"></label>
					<label><?php esc_html_e( '口座の種類', 'apprex' ); ?><select name="kind"><option>普通</option><option>当座</option><option>貯蓄</option></select></label>
					<label><?php esc_html_e( '口座番号', 'apprex' ); ?><input type="text" name="number" required inputmode="numeric" pattern="[0-9０-９]{6,8}" placeholder="例：1234567"></label>
					<label><?php esc_html_e( '口座名義（カタカナ）', 'apprex' ); ?><input type="text" name="holder" required placeholder="例：カ）タナカウェブ"></label>
					<label><?php esc_html_e( 'ログインのパスワード（確認のため）', 'apprex' ); ?><input type="password" name="pass" required autocomplete="current-password"></label>
					<button type="submit" class="btn btn--primary"<?php echo isset( $_GET['as'] ) ? ' disabled' : ''; ?>><?php esc_html_e( '本部に送る', 'apprex' ); ?></button>
				</form>
				<p class="agp-small"><?php esc_html_e( '送った内容は、本部が確認してから反映します。変更すると、あなたのメールアドレスにもお知らせが届きます。お心当たりのない変更のお知らせが届いたら、すぐに本部へご連絡ください。', 'apprex' ); ?></p>
			</details>
		</div>

		<p class="agp-small" style="text-align:center"><a href="<?php echo esc_url( wp_logout_url( apprex_agency_portal_url() ) ); ?>"><?php esc_html_e( 'ログアウト', 'apprex' ); ?></a></p>

		<script src="<?php echo esc_url( APPREX_URI . '/assets/js/vendor/qrcode.js?ver=2.0.4' ); ?>"></script>
		<script>
		(function(){
			var u=document.getElementById('agpUrl'), b=document.getElementById('agpCopy'), q=document.getElementById('agpQr');
			if(b) b.addEventListener('click',function(){ (navigator.clipboard?navigator.clipboard.writeText(u.value):Promise.reject()).then(function(){b.textContent='コピーしました';},function(){u.select();document.execCommand('copy');b.textContent='コピーしました';}); setTimeout(function(){b.textContent='コピー';},1600); });
			try{ var r=window.qrcode(0,'M'); r.addData(u.value); r.make(); q.innerHTML=r.createSvgTag({cellSize:5,margin:2,scalable:true}); }catch(e){}
		})();
		</script>
	<?php endif; ?>
	</div>
</section>
<style>
.agp .agp-card{background:#fff;border:1px solid #e3e8f2;border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 2px 10px rgba(11,29,63,.05)}
.agp .agp-card h2{font-size:1.15rem;margin:0 0 12px}
.agp .agp-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,420px),1fr));gap:18px}
.agp .agp-grid .agp-card{margin-bottom:0}
.agp .agp-grid{margin-bottom:18px}
.agp .agp-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;margin-bottom:18px}
.agp .agp-stat{background:#0b1d3f;color:#fff;border-radius:14px;padding:14px 16px}
.agp .agp-stat small{display:block;color:#9fb2dd;font-size:.78rem}
.agp .agp-stat b{font-size:1.5rem;font-weight:800}
.agp .agp-stat b span{font-size:.8rem;margin-left:2px}
.agp .agp-url{display:flex;gap:8px}
.agp .agp-url input{flex:1;min-width:0;padding:10px 12px;border:1.5px solid #d6deec;border-radius:10px;font-size:.9rem}
.agp .agp-qr{width:160px;margin:12px 0}
.agp .agp-small{font-size:.82rem;color:#5d6987;line-height:1.7}
.agp .agp-small code{font-size:.75rem;word-break:break-all}
.agp .agp-table{width:100%;border-collapse:collapse;font-size:.9rem}
.agp .agp-table th,.agp .agp-table td{padding:10px;border-bottom:1px solid #e3e8f2;text-align:left;vertical-align:top}
.agp .agp-table th{color:#0b1d3f;background:#f5f7fc;white-space:nowrap}
.agp .agp-scroll{overflow-x:auto}
.agp .agp-stage{display:inline-block;border-radius:999px;padding:2px 10px;font-size:.78rem;font-weight:700;background:#eef3ff;color:#1c47c4}
.agp .agp-stage--contract{background:#e7f7ee;color:#13994a}
.agp .agp-stage--cancelled{background:#f3f4f6;color:#6b7280}
.agp .agp-form{display:grid;gap:10px}
.agp .agp-form label{display:grid;gap:4px;font-size:.85rem;font-weight:700;color:#0b1d3f}
.agp .agp-form input,.agp .agp-form textarea,.agp .agp-form select{padding:10px 12px;border:1.5px solid #d6deec;border-radius:10px;font-size:.95rem;font-weight:400}
.agp .agp-docs{margin:0 0 12px;padding-left:1.2em;list-style:disc}
.agp .agp-docs li{margin:6px 0}
.agp .agp-ok{background:#e7f7ee;color:#13994a;padding:10px 12px;border-radius:10px}
.agp .agp-warn{background:#fff8ed;color:#9a3412;padding:10px 12px;border-radius:10px}
.agp .agp-note{background:#eef3ff;padding:8px 12px;border-radius:10px;font-size:.85rem}
.agp .agp-login{max-width:520px;margin:0 auto}
.agp .agp-bank{max-width:520px;margin-top:12px}
.agp details summary{cursor:pointer;font-weight:700;color:#1c47c4}
.agp .agp-login input[type=text],.agp .agp-login input[type=password]{width:100%;padding:10px 12px;border:1.5px solid #d6deec;border-radius:10px}
.agp .agp-login .button{background:#ff6b1f;color:#fff;border:0;border-radius:999px;padding:12px 24px;font-weight:700;cursor:pointer}
</style>
<?php
get_footer();
