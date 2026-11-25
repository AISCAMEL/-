<?php
/**
 * ホームページ制作：提案書 → 月額見積り → お申し込み（管理する側とお客様の画面）。
 *
 * 1) ヒアリング（受付の種類「ホームページのヒアリング」）の画面に「提案書と見積り」の欄を出す
 * 2) AIがヒアリングの答えから提案書の下書きを作る → 担当者が直す（デザイン案の画像も付けられる）
 * 3) プラン（月額）を選んで「お客様に送る」→ お客様専用のページ（提案書＋見積り）をメールで送る
 * 4) お客様が「この内容で申し込む」（3年契約・解約金に同意）→ 契約の下書きを自動で作り、社内に知らせる
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function apprex_hpp_sig( $id ) {
	return substr( hash_hmac( 'sha256', 'hpp' . (int) $id, wp_salt( 'auth' ) ), 0, 14 );
}

/** お客様に見せるページのURL。 */
function apprex_hpp_url( $id ) {
	return add_query_arg( 'apx_hpp', (int) $id . '.' . apprex_hpp_sig( $id ), home_url( '/' ) );
}

/** この受付の提案（保存した中身）。 */
function apprex_hpp_get( $id ) {
	$d = get_post_meta( $id, 'apprex_hpp', true );
	return wp_parse_args(
		is_array( $d ) ? $d : array(),
		array( 'body' => '', 'plan' => 'standard', 'monthly' => 0, 'images' => '', 'note' => '', 'status' => 'draft', 'sent' => 0, 'viewed' => 0, 'accepted' => 0, 'contract' => 0 )
	);
}

/** 月額（特別な金額があればそれ）。 */
function apprex_hpp_monthly( $p ) {
	$plans = apprex_hp_plans();
	return (int) $p['monthly'] > 0 ? (int) $p['monthly'] : (int) ( $plans[ $p['plan'] ][1] ?? 0 );
}

/** このページでヒアリングか（提案書の欄を出すか）。 */
function apprex_hpp_is_hp( $id ) {
	return 'hp' === get_post_meta( $id, 'apprex_type', true ) || get_post_meta( $id, 'apprex_hpp', true );
}

/* =========================================================================
 * AIで提案書の下書き
 * ====================================================================== */
function apprex_hpp_ai_draft( $id ) {
	if ( ! function_exists( 'apprex_openrouter_complete' ) ) {
		return new WP_Error( 'hpp', 'AIの設定がありません。' );
	}
	$ans  = (array) get_post_meta( $id, 'apprex_hp_hearing', true );
	$msg  = (string) get_post_meta( $id, 'apprex_message', true );
	$name = trim( get_post_meta( $id, 'apprex_company', true ) . ' ' . get_post_meta( $id, 'apprex_name', true ) );
	$facts = '';
	foreach ( $ans as $k => $v ) {
		$facts .= "{$k}：{$v}\n";
	}
	if ( '' === $facts ) {
		$facts = $msg;
	}
	$plans = '';
	foreach ( apprex_hp_plans() as $k => $p ) {
		$plans .= "- {$p[0]}（月額" . number_format( $p[1] ) . "円・税抜）：{$p[2]}\n";
	}
	$sys = 'あなたはホームページ制作会社APPREX（合同会社アイズ）のディレクターです。お客様のヒアリングの答えから、ホームページの提案書を書きます。'
		. '守ること：ヒアリングに書いてあることをもとにする。分からないことは「打ち合わせで決めます」と書く。効果の数字（〇%アップなど）を約束しない。です・ます調で、お客様に見せる文章。絵文字は使わない。';
	$usr = "お客様：{$name} 様\nヒアリングの答え：\n{$facts}\n" . ( '' !== $msg && $facts !== $msg ? "そのほかのご要望：\n{$msg}\n" : '' )
		. "\n月額プラン（制作費0円・契約期間3年）：\n{$plans}\n"
		. "次の見出しで提案書を書いてください（見出しは「## 」で始める。箇条書きは「- 」）。\n"
		. "## このホームページの目的\n## 見てほしいお客様\n## デザインの方針（雰囲気・色・写真の使い方）\n## ページの構成（ページごとに、載せる内容）\n## 必要な機能（お問い合わせ・予約など）\n## 公開までの進め方とスケジュールの目安\n## おすすめのプランと理由（上のプランから1つ）";
	$r = apprex_openrouter_complete( array( array( 'role' => 'system', 'content' => $sys ), array( 'role' => 'user', 'content' => $usr ) ), array( 'max_tokens' => 2600, 'temperature' => 0.5, 'timeout' => 120, 'article' => true ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	$t = trim( str_replace( "\r", '', (string) $r ) );
	// おすすめのプランを読み取る。
	$plan = '';
	if ( preg_match( '/##[^\n]*おすすめ[^\n]*\n([\s\S]{0,400})/u', $t, $m ) ) {
		foreach ( array( 'premium' => 'Premium', 'standard' => 'Standard', 'light' => 'Light' ) as $k => $l ) {
			if ( false !== stripos( $m[1], $l ) ) {
				$plan = $k;
				break;
			}
		}
	}
	return array( $t, $plan );
}

/* =========================================================================
 * 管理画面：受付の画面に「提案書と見積り」
 * ====================================================================== */
add_action(
	'add_meta_boxes_apprex_inquiry',
	function ( $post ) {
		if ( apprex_hpp_is_hp( $post->ID ) ) {
			add_meta_box( 'apprex_hpp_box', 'ホームページの提案書と見積り', 'apprex_hpp_box', 'apprex_inquiry', 'normal', 'high' );
		}
	}
);

function apprex_hpp_box( $post ) {
	$p     = apprex_hpp_get( $post->ID );
	$ans   = (array) get_post_meta( $post->ID, 'apprex_hp_hearing', true );
	$act   = admin_url( 'admin-post.php' );
	$stl   = array( 'draft' => '下書き', 'sent' => '送りました', 'viewed' => 'お客様が見ました', 'accepted' => 'お申し込みがありました' );
	$m     = apprex_hpp_monthly( $p );
	wp_nonce_field( 'apprex_hpp_' . $post->ID, 'apprex_hpp_nonce' );
	?>
	<p>状態：<b><?php echo esc_html( $stl[ $p['status'] ] ?? $p['status'] ); ?></b>
		<?php echo $p['sent'] ? '　送った日 ' . esc_html( wp_date( 'n/j G:i', (int) $p['sent'] ) ) : ''; ?>
		<?php echo $p['viewed'] ? '　見た日 ' . esc_html( wp_date( 'n/j G:i', (int) $p['viewed'] ) ) : ''; ?>
		<?php echo $p['accepted'] ? '　<b style="color:#00a32a">申し込み ' . esc_html( wp_date( 'n/j G:i', (int) $p['accepted'] ) ) . '</b>' : ''; ?>
		<?php if ( $p['contract'] && get_post( (int) $p['contract'] ) ) : ?>　<a href="<?php echo esc_url( get_edit_post_link( (int) $p['contract'] ) ); ?>">契約の下書きを開く</a><?php endif; ?>
	</p>
	<?php if ( $ans ) : ?>
		<details style="margin-bottom:10px"><summary style="cursor:pointer"><b>ヒアリングの答え</b></summary>
			<table class="widefat striped" style="margin-top:6px"><tbody>
			<?php foreach ( $ans as $k => $v ) : ?><tr><th style="width:180px"><?php echo esc_html( $k ); ?></th><td><?php echo nl2br( esc_html( $v ) ); ?></td></tr><?php endforeach; ?>
			</tbody></table>
		</details>
	<?php endif; ?>
	<p><button class="button" formaction="<?php echo esc_url( $act . '?action=apprex_hpp&do=ai&id=' . $post->ID ); ?>" formmethod="post" onclick="return confirm('今の提案書の欄を、AIの下書きで置きかえます。よろしいですか？')">AIで提案書の下書きを作る</button>
		<span class="description">ヒアリングの答えから、目的・お客様・デザインの方針・ページ構成・機能・進め方・おすすめプランを書きます（1〜2分）。</span></p>
	<p><label><b>提案書</b>（「## 」で見出し、「- 」で箇条書き）</label><br>
		<textarea name="apprex_hpp[body]" rows="18" class="large-text" style="font-size:13px;line-height:1.7"><?php echo esc_textarea( $p['body'] ); ?></textarea></p>
	<p><label><b>デザイン案の画像</b>（URLを1行に1つ）</label><br>
		<textarea name="apprex_hpp[images]" id="apprex_hpp_images" rows="3" class="large-text" placeholder="https://…/design-top.png"><?php echo esc_textarea( $p['images'] ); ?></textarea>
		<button type="button" class="button" id="apprex_hpp_pick">メディアから選ぶ</button></p>
	<table class="form-table" role="presentation"><tbody>
		<tr><th>プラン</th><td><select name="apprex_hpp[plan]">
			<?php foreach ( apprex_hp_plans() as $k => $pl ) : ?><option value="<?php echo esc_attr( $k ); ?>" <?php selected( $p['plan'], $k ); ?>><?php echo esc_html( $pl[0] . '（月額' . number_format( $pl[1] ) . '円）' ); ?></option><?php endforeach; ?>
		</select>　特別な月額 <input type="number" name="apprex_hpp[monthly]" value="<?php echo (int) $p['monthly'] ? (int) $p['monthly'] : ''; ?>" min="0" step="100" style="width:110px" placeholder="空ならプランの金額"> 円</td></tr>
		<tr><th>見積り</th><td>制作費 0円／月額 <b><?php echo esc_html( number_format( $m ) ); ?>円</b>（税抜）／契約期間 <?php echo (int) apprex_hp_term_months(); ?>か月（3年間の合計 <?php echo esc_html( number_format( $m * apprex_hp_term_months() ) ); ?>円）</td></tr>
		<tr><th>お客様へのひとこと</th><td><textarea name="apprex_hpp[note]" rows="2" class="large-text" placeholder="例：先日はありがとうございました。ご要望をもとに提案書をお作りしました。"><?php echo esc_textarea( $p['note'] ); ?></textarea></td></tr>
	</tbody></table>
	<p>
		<button class="button" formaction="<?php echo esc_url( $act . '?action=apprex_hpp&do=save&id=' . $post->ID ); ?>" formmethod="post">保存</button>
		<a class="button" href="<?php echo esc_url( apprex_hpp_url( $post->ID ) . '&preview=1' ); ?>" target="_blank" rel="noopener">お客様の画面を見る</a>
		<button class="button button-primary" formaction="<?php echo esc_url( $act . '?action=apprex_hpp&do=send&id=' . $post->ID ); ?>" formmethod="post" onclick="return confirm('保存して、<?php echo esc_js( (string) get_post_meta( $post->ID, 'apprex_email', true ) ); ?> に提案書と見積りを送ります。よろしいですか？')">保存してお客様に送る</button>
	</p>
	<p class="description">お客様のページでは、提案書・デザイン案・見積り（制作費0円・月額・3年契約・途中解約の解約金＝残りの月数×月額）を見て、「この内容で申し込む」を押せます。申し込みがあると、契約の下書きを自動で作って知らせます。</p>
	<script>
	(function(){var b=document.getElementById('apprex_hpp_pick');if(!b||!window.wp||!wp.media)return;var f;b.addEventListener('click',function(){if(!f){f=wp.media({title:'デザイン案の画像',multiple:true,library:{type:'image'}});f.on('select',function(){var t=document.getElementById('apprex_hpp_images'),u=f.state().get('selection').map(function(a){return a.toJSON().url;});t.value=(t.value.trim()?t.value.trim()+'\n':'')+u.join('\n');});}f.open();});})();
	</script>
	<?php
}

add_action(
	'admin_enqueue_scripts',
	function () {
		$s = get_current_screen();
		if ( $s && 'apprex_inquiry' === $s->post_type ) {
			wp_enqueue_media();
		}
	}
);

/** 画面の欄を保存。 */
function apprex_hpp_save_from_post( $id ) {
	$in = isset( $_POST['apprex_hpp'] ) ? (array) wp_unslash( $_POST['apprex_hpp'] ) : array(); // phpcs:ignore WordPress.Security
	if ( ! $in ) {
		return apprex_hpp_get( $id );
	}
	$p           = apprex_hpp_get( $id );
	$p['body']   = sanitize_textarea_field( (string) ( $in['body'] ?? '' ) );
	$p['images'] = implode( "\n", array_filter( array_map( 'esc_url_raw', preg_split( '/\R/', (string) ( $in['images'] ?? '' ) ) ) ) );
	$p['plan']   = isset( apprex_hp_plans()[ $in['plan'] ?? '' ] ) ? $in['plan'] : 'standard';
	$p['monthly'] = absint( $in['monthly'] ?? 0 );
	$p['note']   = sanitize_textarea_field( (string) ( $in['note'] ?? '' ) );
	update_post_meta( $id, 'apprex_hpp', $p );
	return $p;
}

// 「更新」ボタンで受付を保存したときも、提案書の欄を保存する。
add_action(
	'save_post_apprex_inquiry',
	function ( $id ) {
		if ( isset( $_POST['apprex_hpp_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['apprex_hpp_nonce'] ), 'apprex_hpp_' . $id ) && current_user_can( 'edit_post', $id ) ) {
			apprex_hpp_save_from_post( $id );
		}
	}
);

add_action(
	'admin_post_apprex_hpp',
	function () {
		$id = absint( $_GET['id'] ?? 0 );
		if ( ! $id || ! current_user_can( 'edit_post', $id ) || ! isset( $_POST['apprex_hpp_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['apprex_hpp_nonce'] ), 'apprex_hpp_' . $id ) ) {
			wp_die( '権限がありません。' );
		}
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
		}
		$do  = sanitize_key( $_GET['do'] ?? '' );
		$p   = apprex_hpp_save_from_post( $id );
		$msg = '保存しました。';
		if ( 'ai' === $do ) {
			$r = apprex_hpp_ai_draft( $id );
			if ( is_wp_error( $r ) ) {
				$msg = 'AIの下書きを作れませんでした：' . $r->get_error_message();
			} else {
				$p['body'] = $r[0];
				if ( '' !== $r[1] ) {
					$p['plan'] = $r[1];
				}
				update_post_meta( $id, 'apprex_hpp', $p );
				$msg = 'AIが提案書の下書きを作りました。読んで直してから、お客様に送ってください。';
			}
		} elseif ( 'send' === $do ) {
			$msg = apprex_hpp_send( $id ) ? 'お客様に提案書と見積りを送りました。' : '送れませんでした（提案書が空か、メールの送信設定を確かめてください）。';
		}
		set_transient( 'apprex_hpp_msg_' . get_current_user_id(), $msg, 120 );
		wp_safe_redirect( get_edit_post_link( $id, 'raw' ) . '#apprex_hpp_box' );
		exit;
	}
);
add_action(
	'admin_notices',
	function () {
		$m = get_transient( 'apprex_hpp_msg_' . get_current_user_id() );
		if ( $m ) {
			delete_transient( 'apprex_hpp_msg_' . get_current_user_id() );
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $m ) . '</p></div>';
		}
	}
);

/** お客様にメールで送る。 */
function apprex_hpp_send( $id ) {
	$p     = apprex_hpp_get( $id );
	$email = (string) get_post_meta( $id, 'apprex_email', true );
	if ( '' === trim( $p['body'] ) || ! is_email( $email ) || ! function_exists( 'apprex_render_email' ) ) {
		return false;
	}
	$name = (string) get_post_meta( $id, 'apprex_name', true );
	$m    = apprex_hpp_monthly( $p );
	$subj = '【APPREX】ホームページのご提案とお見積り';
	$body = "{$name} 様\n\n" . ( '' !== trim( $p['note'] ) ? trim( $p['note'] ) . "\n\n" : "ヒアリングにご協力いただき、ありがとうございました。\nご要望をもとに、ホームページの提案書とお見積りをお作りしました。\n\n" )
		. "下のボタンから、提案書・デザイン案・お見積りをご覧いただけます。\n" . apprex_hpp_url( $id ) . "\n\n"
		. "■ お見積り（税抜）\n制作費：0円\n月額：" . number_format( $m ) . "円\n契約期間：3年（" . apprex_hp_term_months() . "か月）\n\n"
		. "内容にご納得いただけましたら、ページの「この内容で申し込む」からお申し込みください。直したいところやご質問は、このメールへのご返信でどうぞ。\n";
	$ok = wp_mail( $email, $subj, apprex_render_email( $subj, $body ), function_exists( 'apprex_mail_headers' ) ? apprex_mail_headers() : array( 'Content-Type: text/html; charset=UTF-8' ) );
	if ( $ok ) {
		$p['sent']   = time();
		$p['status'] = $p['accepted'] ? 'accepted' : 'sent';
		update_post_meta( $id, 'apprex_hpp', $p );
	}
	return $ok;
}

/* =========================================================================
 * お客様の画面（提案書＋見積り＋申し込み）
 * ====================================================================== */

/** 提案書の文章を HTML に（## 見出し・- 箇条書き・段落）。 */
function apprex_hpp_html( $text ) {
	$out = '';
	foreach ( preg_split( '/\n{2,}/', trim( (string) $text ) ) as $blk ) {
		$lines = preg_split( '/\n/', trim( $blk ) );
		$list  = '';
		foreach ( $lines as $l ) {
			$l = trim( $l );
			if ( preg_match( '/^#{1,3}\s*(.+)$/u', $l, $m ) ) {
				$out .= ( $list ? '<ul>' . $list . '</ul>' : '' ) . '<h2>' . esc_html( trim( $m[1], ' *' ) ) . '</h2>';
				$list = '';
			} elseif ( preg_match( '/^(?:[-・]|\*(?!\*))\s*(.+)$/u', $l, $m ) ) {
				$list .= '<li>' . esc_html( $m[1] ) . '</li>';
			} elseif ( '' !== $l ) {
				$out .= ( $list ? '<ul>' . $list . '</ul>' : '' ) . '<p>' . esc_html( $l ) . '</p>';
				$list = '';
			}
		}
		$out .= $list ? '<ul>' . $list . '</ul>' : '';
	}
	return preg_replace( '/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $out );
}

add_action(
	'template_redirect',
	function () {
		if ( ! isset( $_GET['apx_hpp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return;
		}
		$v  = explode( '.', sanitize_text_field( wp_unslash( $_GET['apx_hpp'] ) ), 2 ); // phpcs:ignore WordPress.Security.NonceVerification
		$id = (int) ( $v[0] ?? 0 );
		if ( ! $id || 'apprex_inquiry' !== get_post_type( $id ) || ! hash_equals( apprex_hpp_sig( $id ), (string) ( $v[1] ?? '' ) ) ) {
			wp_die( 'ページが見つかりません。', 'ご提案', array( 'response' => 404 ) );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow' );
		$p       = apprex_hpp_get( $id );
		$preview = ! empty( $_GET['preview'] ) && current_user_can( 'edit_post', $id ); // phpcs:ignore WordPress.Security.NonceVerification
		$name    = (string) get_post_meta( $id, 'apprex_name', true );
		$company = (string) get_post_meta( $id, 'apprex_company', true );
		$plans   = apprex_hp_plans();
		$plan    = $plans[ $p['plan'] ] ?? $plans['standard'];
		$m       = apprex_hpp_monthly( $p );
		$term    = apprex_hp_term_months();
		$done    = '';

		// お申し込み。
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && ! $preview && ! $p['accepted'] ) {
			if ( empty( $_POST['agree'] ) || ! isset( $_POST['_hppn'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_hppn'] ), 'apprex_hpp_accept_' . $id ) ) {
				$done = 'err';
			} else {
				$p['accepted'] = time();
				$p['status']   = 'accepted';
				$p['contract'] = apprex_hpp_make_contract( $id, $p );
				update_post_meta( $id, 'apprex_hpp', $p );
				update_post_meta( $id, 'apprex_hpp_accept_ip', sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ) );
				if ( function_exists( 'apprex_notify' ) ) {
					apprex_notify( 'contract', '🎉 ' . trim( $company . ' ' . $name ) . ' 様が、ホームページの提案に申し込みました（' . $plan[0] . '・月額' . number_format( $m ) . '円・3年契約）。契約の下書きを作りました。契約書を送って締結してください。' . "\n" . ( $p['contract'] ? admin_url( 'post.php?action=edit&post=' . (int) $p['contract'] ) : get_edit_post_link( $id, 'raw' ) ) );
				}
				$done = 'ok';
			}
		} elseif ( ! $preview && ! $p['viewed'] ) {
			$p['viewed'] = time();
			if ( 'sent' === $p['status'] ) {
				$p['status'] = 'viewed';
			}
			update_post_meta( $id, 'apprex_hpp', $p );
			if ( function_exists( 'apprex_notify' ) ) {
				apprex_notify( 'inquiry', '👀 ' . trim( $company . ' ' . $name ) . ' 様が、ホームページの提案書を開きました。' . "\n" . get_edit_post_link( $id, 'raw' ) );
			}
		}

		$imgs = array_filter( preg_split( '/\R/', (string) $p['images'] ) );
		$meet = function_exists( 'apprex_page_url' ) ? apprex_page_url( 'meeting' ) : home_url( '/meeting/' );
		status_header( 200 );
		header( 'Content-Type: text/html; charset=utf-8' );
		?><!doctype html>
<html lang="ja"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title>ホームページのご提案｜APPREX</title>
<style>
:root{--ink:#0b1f44;--sub:#475569;--acc:#1d4ed8;--line:#e2e8f0;--bg:#f5f7fb}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:-apple-system,BlinkMacSystemFont,"Hiragino Sans","Noto Sans JP",sans-serif;line-height:1.85}
.w{max-width:820px;margin:0 auto;padding:28px 18px 60px}
.hd{background:var(--ink);color:#fff;padding:30px 22px;border-radius:18px;margin-bottom:22px}.hd small{opacity:.75}.hd h1{margin:6px 0 0;font-size:1.6rem;line-height:1.4}
.card{background:#fff;border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:18px}
.card h2{font-size:1.15rem;margin:22px 0 8px;padding-left:10px;border-left:4px solid var(--acc)}.card h2:first-child{margin-top:0}
.card p,.card li{color:#1e293b}.imgs{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}.imgs img{width:100%;border-radius:12px;border:1px solid var(--line)}
table{width:100%;border-collapse:collapse}th,td{padding:10px 8px;border-bottom:1px solid var(--line);text-align:left;vertical-align:top}th{width:40%;color:var(--sub);font-weight:600}
.big{font-size:1.5rem;font-weight:800}.note{font-size:.88rem;color:var(--sub)}
.btn{display:inline-block;padding:15px 24px;border-radius:12px;font-weight:800;text-decoration:none;border:0;font-size:1rem;cursor:pointer}
.btn-p{background:#f97316;color:#fff;width:100%}.btn-g{background:#fff;color:var(--acc);border:2px solid var(--acc)}
.ok{background:#ecfdf5;border-color:#a7f3d0}.agree{display:flex;gap:10px;align-items:flex-start;margin:14px 0}.agree input{width:22px;height:22px;margin-top:3px}
.pv{background:#fef3c7;border:1px solid #fcd34d;padding:8px 12px;border-radius:10px;margin-bottom:14px;font-size:.9rem}
</style></head><body><div class="w">
<?php if ( $preview ) : ?><div class="pv">管理者のプレビューです（お客様が見ても「見た」にはなりません）。</div><?php endif; ?>
<div class="hd"><small>APPREX（合同会社アイズ）</small><h1><?php echo esc_html( trim( $company . ' ' . $name ) ); ?> 様<br>ホームページのご提案</h1></div>
<?php if ( 'ok' === $done || $p['accepted'] ) : ?>
	<div class="card ok"><h2>お申し込みありがとうございます</h2><p>担当者から、契約書（電子契約）と今後の進め方をご連絡します。しばらくお待ちください。</p></div>
<?php endif; ?>
<?php if ( 'err' === $done ) : ?><div class="card" style="border-color:#fca5a5"><p>「契約内容に同意します」にチェックを入れてから、もう一度お申し込みください。</p></div><?php endif; ?>
<div class="card"><?php echo apprex_hpp_html( $p['body'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 中で esc_html 済み ?></div>
<?php if ( $imgs ) : ?>
	<div class="card"><h2>デザイン案</h2><div class="imgs"><?php foreach ( $imgs as $u ) : ?><a href="<?php echo esc_url( $u ); ?>" target="_blank" rel="noopener"><img src="<?php echo esc_url( $u ); ?>" alt="デザイン案"></a><?php endforeach; ?></div><p class="note">イメージです。打ち合わせで直しながら仕上げます。</p></div>
<?php endif; ?>
<div class="card">
	<h2>お見積り（税抜）</h2>
	<table><tbody>
		<tr><th>プラン</th><td><b><?php echo esc_html( $plan[0] ); ?></b><br><span class="note"><?php echo esc_html( $plan[2] ); ?></span></td></tr>
		<tr><th>制作費</th><td class="big">0円</td></tr>
		<tr><th>月額</th><td class="big"><?php echo esc_html( number_format( $m ) ); ?>円</td></tr>
		<tr><th>契約期間</th><td>3年（<?php echo (int) $term; ?>か月）</td></tr>
		<tr><th>3年間の合計</th><td><?php echo esc_html( number_format( $m * $term ) ); ?>円</td></tr>
		<tr><th>3年たったあと</th><td><?php echo esc_html( apprex_hp_renewal_text() ); ?></td></tr>
		<tr><th>途中で解約するとき</th><td>解約金として「残りの月数 × 月額」をお支払いいただきます。<br>
			<span class="note"><?php foreach ( array( 6, 12, 24 ) as $u ) : ?><?php echo esc_html( $u . 'か月で解約：残り' . ( $term - $u ) . 'か月 × ' . number_format( $m ) . '円 ＝ ' . number_format( apprex_hp_penalty( $m, $u ) ) . '円' ); ?><br><?php endforeach; ?></span></td></tr>
	</tbody></table>
	<p class="note">制作費をいただかない代わりに、3年間の月額で費用をいただく仕組みです。ドメインや有料の素材など、実費がかかるものは事前にご相談します。</p>
</div>
<?php if ( ! $p['accepted'] && 'ok' !== $done ) : ?>
	<div class="card">
		<h2>この内容で申し込む</h2>
		<form method="post">
			<input type="hidden" name="_hppn" value="<?php echo esc_attr( wp_create_nonce( 'apprex_hpp_accept_' . $id ) ); ?>">
			<label class="agree"><input type="checkbox" name="agree" value="1" required><span>契約期間が3年（<?php echo (int) $term; ?>か月）であること、途中で解約するときは「残りの月数 × 月額」の解約金がかかることを確認し、同意します。</span></label>
			<button class="btn btn-p" type="submit" <?php echo $preview ? 'disabled' : ''; ?>>この内容で申し込む</button>
		</form>
		<p class="note">お申し込みのあと、担当者から契約書（電子契約）をお送りします。契約書の締結をもってご契約となります。</p>
		<p style="text-align:center;margin-top:14px"><a class="btn btn-g" href="<?php echo esc_url( $meet ); ?>">相談してから決めたい（オンライン相談）</a></p>
	</div>
<?php endif; ?>
<p class="note" style="text-align:center">ご質問は、お送りしたメールへのご返信でどうぞ。</p>
</div></body></html>
		<?php
		exit;
	},
	1
);

/** 申し込みがあったら、契約の下書きを作る（担当者が契約書を送って締結する）。 */
function apprex_hpp_make_contract( $id, $p ) {
	$plans = apprex_hp_plans();
	$plan  = $plans[ $p['plan'] ] ?? $plans['standard'];
	$name  = (string) get_post_meta( $id, 'apprex_name', true );
	$co    = (string) get_post_meta( $id, 'apprex_company', true );
	$cid   = wp_insert_post(
		array(
			'post_type'   => 'apprex_contract',
			'post_status' => 'draft',
			'post_title'  => trim( $co . ' ' . $name ) . '（ホームページ ' . $plan[0] . '）',
		),
		true
	);
	if ( is_wp_error( $cid ) ) {
		return 0;
	}
	$start = wp_date( 'Y-m-01', strtotime( 'first day of next month' ) );
	$meta  = array(
		'apprex_c_name'       => $name,
		'apprex_c_company'    => $co,
		'apprex_c_email'      => (string) get_post_meta( $id, 'apprex_email', true ),
		'apprex_c_service'    => 'ホームページ制作・運用',
		'apprex_c_plan'       => 'ホームページ ' . $plan[0],
		'apprex_c_monthly'    => apprex_hpp_monthly( $p ),
		'apprex_c_initial'    => 0,
		'apprex_c_production' => 0,
		'apprex_c_start'      => $start,
		'apprex_c_term'       => 3,
		'apprex_c_renewal'    => wp_date( 'Y-m-d', strtotime( $start . ' +3 years' ) ),
		'apprex_c_status'     => 'active',
		'apprex_c_autorenew'  => 1, // 3年たったら1年ごとに自動更新。
		'apprex_c_kind'       => 'hp',
		'apprex_c_note'       => 'ホームページの提案から申し込み（受付 #' . $id . '・' . wp_date( 'Y/n/j G:i' ) . '・3年契約と解約金に同意）',
	);
	foreach ( $meta as $k => $v ) {
		update_post_meta( $cid, $k, $v );
	}
	return (int) $cid;
}

/* =========================================================================
 * 契約書：ホームページの契約は、3年契約・解約金（残りの月数×月額）の条文にする
 * ====================================================================== */
add_filter(
	'apprex_contract_doc_body',
	function ( $html, $contract_id ) {
		$is_hp = 'hp' === get_post_meta( $contract_id, 'apprex_c_kind', true ) || false !== mb_strpos( (string) get_post_meta( $contract_id, 'apprex_c_service', true ), 'ホームページ' );
		if ( ! $is_hp ) {
			return $html;
		}
		$html   = str_replace( 'におけるアプリ作成サービスの', 'におけるホームページ制作・運用サービスの', $html );
		$clause = '<p>4.（契約期間・中途解約・違約金）本サービスのうちホームページ制作・運用の契約期間は、契約開始日から起算して' . apprex_hp_term_months() . 'ヶ月間とする。制作費は0円とし、ホームページの制作の対価は契約期間中の月額利用料に含めるものとする。乙が契約期間の満了前に、退会その他事由の如何を問わず本サービス利用契約を終了する場合（第１０条に基づき甲が解除した場合を含む。）、乙は甲に対し、違約金として、解約日の属する月の翌月から契約期間の満了月までの残存月数に月額利用料を乗じた額を、解約日の属する月の翌月末日までに一括して支払うものとする。<br>'
			. 'なお、本項の違約金は損害賠償額の予定とし、既に支払済みの月額利用料を返金しない旨（本条第１項）の定めを妨げるものではない。ドメイン・有料素材その他の実費は別途乙の負担とする。<br>'
			. '5.（自動更新）契約期間満了日の１ヶ月前までに、甲乙いずれからも書面又は電磁的方法により更新しない旨の申出がないときは、本契約は同一の条件でさらに１年間自動的に更新されるものとし、以後も同様とする。更新後の期間の途中で乙が本サービス利用契約を終了する場合も、前項に準じ、当該期間の残存月数に月額利用料を乗じた額を違約金として支払うものとする。</p>';
		$new = preg_replace( '#<p>\s*4\.（中途解約・違約金）.*?</p>#su', $clause, $html, 1, $n );
		if ( ! $n ) {
			$new = preg_replace( '#(<h2>第７条)#u', $clause . "\n$1", $html, 1 );
		}
		return $new;
	},
	10,
	2
);
