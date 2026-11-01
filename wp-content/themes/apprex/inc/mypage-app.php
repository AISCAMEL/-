<?php
/**
 * マイページ（ログイン後）：管理システムのような画面。
 *
 * 上のバー＋左のメニュー＋本体の形で、ダッシュボード・ご契約・お支払い・ご予約・AIスタジオ・LINE連携・
 * プレビューアプリ・アカウントを切り替えて見られる。データは inc/member-portal.php などと同じもの。
 * ログイン前（ログインフォーム）は今までどおり [apprex_mypage] が表示する。
 *
 * @package APPREX
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ログイン後のマイページは専用の画面（ページの見出し・本文の枠を使わない）。 */
add_filter( 'template_include', function ( $template ) {
	if ( is_page( 'mypage' ) && is_user_logged_in() ) {
		$t = locate_template( 'page-templates/page-mypage-app.php' );
		return $t ? $t : $template;
	}
	return $template;
}, 20 );

add_action( 'wp_enqueue_scripts', function () {
	if ( is_page( 'mypage' ) && is_user_logged_in() ) {
		wp_enqueue_style( 'apprex-mypage', APPREX_URI . '/assets/css/mypage.css', array(), APPREX_VERSION );
	}
} );

/**
 * 画面に出すデータをまとめる。
 *
 * @param WP_User $user 会員。
 * @return array
 */
function apprex_mp_data( $user ) {
	$d = array(
		'contracts' => array(),
		'invoices'  => array(),
		'meetings'  => array(),
		'requests'  => array(),
		'todo'      => array(),
		'notices'   => array(),
		'pay'       => array(),
	);
	foreach ( function_exists( 'apprex_member_contracts' ) ? apprex_member_contracts( $user ) : array() as $id ) {
		$m = function ( $k ) use ( $id ) {
			return (string) get_post_meta( $id, $k, true );
		};
		$c = array(
			'id'       => $id,
			'title'    => trim( $m( 'apprex_c_service' ) . ' ' . $m( 'apprex_c_plan' ) ),
			'status'   => $m( 'apprex_c_status' ) ? $m( 'apprex_c_status' ) : 'active',
			'monthly'  => (int) $m( 'apprex_c_monthly' ),
			'start'    => $m( 'apprex_c_start' ),
			'term'     => $m( 'apprex_c_term' ),
			'renewal'  => $m( 'apprex_c_renewal' ),
			'method'   => 'invoice' === $m( 'apprex_c_payment_method' ) ? '請求書（振込）' : 'Square（カード）',
			'payday'   => $m( 'apprex_c_payment_day' ),
			'lastpaid' => $m( 'apprex_c_last_paid' ),
			'app_url'  => $m( 'apprex_c_app_url' ),
			'login'    => $m( 'apprex_c_app_login' ),
			'pass'     => $m( 'apprex_c_app_pass' ),
			'name'     => $m( 'apprex_c_name' ),
			'company'  => $m( 'apprex_c_company' ),
			'email'    => $m( 'apprex_c_email' ),
			'mf'       => $m( 'apprex_c_mf_status' ),
			'mf_url'   => $m( 'apprex_c_mf_url' ),
			'mf_pdf'   => $m( 'apprex_c_mf_signed_pdf' ),
			'doc'      => function_exists( 'apprex_contract_doc_url' ) ? apprex_contract_doc_url( $id ) : '',
		);
		$d['contracts'][] = $c;
		// お知らせ（お支払い・更新の自動案内）と、お支払いの状態。
		foreach ( (array) get_post_meta( $id, 'apprex_c_notices', true ) as $n ) {
			if ( is_array( $n ) && ! empty( $n['title'] ) ) {
				$d['notices'][] = $n;
			}
		}
		$ps = function_exists( 'apprex_bn_state' ) ? apprex_bn_state( $id ) : null;
		if ( $ps ) {
			$ps['plan']  = $c['title'];
			$d['pay'][]  = $ps;
		}
		if ( $m( 'apprex_c_sq_invoice_id' ) ) {
			$d['invoices'][] = array(
				'month'  => $m( 'apprex_c_sq_invoice_month' ),
				'plan'   => $c['title'],
				'amount' => $c['monthly'],
				'status' => $m( 'apprex_c_sq_invoice_status' ),
				'url'    => $m( 'apprex_c_sq_invoice_url' ),
				'paid'   => $m( 'apprex_c_sq_paid_at' ),
			);
		}
		if ( $m( 'apprex_c_sq_invoice_url' ) && in_array( $m( 'apprex_c_sq_invoice_status' ), array( 'UNPAID', 'PARTIALLY_PAID', 'FAILED' ), true ) ) {
			$d['todo'][] = array( 'お支払いをお願いします', $m( 'apprex_c_sq_invoice_month' ) . '分のご請求（' . $c['title'] . '）', $m( 'apprex_c_sq_invoice_url' ), 'カードで支払う', 'warn' );
		}
		if ( 'signed' !== $c['mf'] && $c['mf_url'] ) {
			$d['todo'][] = array( '契約書の締結をお願いします', $c['title'] . 'の契約書', $c['mf_url'], '締結する', 'warn' );
		}
	}
	// ご予約（会員のメール・LINE）。
	$or  = array( array( 'key' => 'apprex_email', 'value' => $user->user_email ) );
	$uid = (string) get_user_meta( $user->ID, 'apprex_line_uid', true );
	if ( '' !== $uid ) {
		$or[] = array( 'key' => 'apprex_line_uid', 'value' => $uid );
	}
	foreach ( get_posts( array( 'post_type' => 'apprex_inquiry', 'post_status' => 'any', 'posts_per_page' => 20, 'fields' => 'ids', 'meta_query' => array_merge( array( 'relation' => 'OR' ), $or ) ) ) as $id ) { // phpcs:ignore WordPress.DB.SlowDBQuery
		$at = (int) get_post_meta( $id, 'apprex_meeting_at', true );
		if ( $at ) {
			$d['meetings'][ $at ] = array( 'at' => $at, 'cancel' => (bool) get_post_meta( $id, 'apprex_mb_cancel', true ) );
		}
	}
	$sm = (string) get_user_meta( $user->ID, 'apprex_studio_meeting', true );
	if ( $sm && strtotime( $sm ) && ! isset( $d['meetings'][ strtotime( $sm ) ] ) ) {
		$d['meetings'][ strtotime( $sm ) ] = array( 'at' => strtotime( $sm ), 'cancel' => false );
	}
	krsort( $d['meetings'] );
	// LINEで届いたご依頼（この会員のもの）。
	foreach ( (array) get_option( 'apprex_line_requests', array() ) as $r ) {
		if ( is_array( $r ) && (int) $r['user'] === $user->ID ) {
			$d['requests'][] = $r;
		}
	}
	usort( $d['notices'], function ( $a, $b ) {
		return (int) $b['t'] - (int) $a['t'];
	} );
	if ( '' === $uid && function_exists( 'apprex_liff_ready' ) && apprex_liff_ready() ) {
		$d['todo'][] = array( 'LINEと連携しませんか', '契約・お支払い・予約の確認やご依頼が、LINEでできるようになります', '#line', '連携のしかた', 'info' );
	}
	return $d;
}

/** 状態のバッジ。 */
function apprex_mp_badge( $text, $tone = 'gray' ) {
	return '<span class="mp-badge is-' . esc_attr( $tone ) . '">' . esc_html( $text ) . '</span>';
}

/** 請求書の状態 → [表示, 色]。 */
function apprex_mp_inv_state( $status ) {
	$map = array(
		'PAID'           => array( '支払済み', 'green' ),
		'UNPAID'         => array( '未払い', 'red' ),
		'PARTIALLY_PAID' => array( '一部入金', 'amber' ),
		'PAYMENT_PENDING' => array( '処理中', 'amber' ),
		'SCHEDULED'      => array( '送信予定', 'gray' ),
		'FAILED'         => array( '失敗', 'red' ),
		'CANCELED'       => array( '取消', 'gray' ),
		'REFUNDED'       => array( '返金', 'gray' ),
	);
	return isset( $map[ $status ] ) ? $map[ $status ] : array( $status ? $status : '—', 'gray' );
}

/** 見出し行（定義リスト）。 */
function apprex_mp_dl( $rows ) {
	$h = '<dl class="mp-dl">';
	foreach ( $rows as $r ) {
		$h .= '<div><dt>' . esc_html( $r[0] ) . '</dt><dd>' . ( isset( $r[2] ) && $r[2] ? $r[1] : esc_html( '' !== (string) $r[1] ? $r[1] : '—' ) ) . '</dd></div>';
	}
	return $h . '</dl>';
}

/**
 * マイページ本体。
 *
 * @return string
 */
function apprex_mypage_app() {
	$user    = wp_get_current_user();
	$d       = apprex_mp_data( $user );
	$studio  = (bool) get_user_meta( $user->ID, 'apprex_studio', true );
	$stage   = (int) get_user_meta( $user->ID, 'apprex_studio_stage', true );
	$prod    = (int) get_user_meta( $user->ID, 'apprex_studio_prod', true );
	$line    = '' !== (string) get_user_meta( $user->ID, 'apprex_line_uid', true );
	$liff    = function_exists( 'apprex_line_mypage_link' ) ? apprex_line_mypage_link() : '';
	$meet    = function_exists( 'apprex_mb_page_url' ) ? apprex_mb_page_url() : home_url( '/meeting/' );
	$sturl   = function_exists( 'apprex_studio_url' ) ? apprex_studio_url() : home_url( '/studio/' );
	$active  = array_values( array_filter( $d['contracts'], function ( $c ) {
		return 'cancelled' !== $c['status'];
	} ) );
	$c0      = $active ? $active[0] : null;
	$inv0    = $d['invoices'] ? $d['invoices'][0] : null;
	$next    = null;
	foreach ( $d['meetings'] as $mt ) {
		if ( $mt['at'] > time() && ! $mt['cancel'] ) {
			$next = $mt;
		}
	}
	$when    = function ( $at ) {
		return function_exists( 'apprex_mb_when' ) ? apprex_mb_when( $at ) : wp_date( 'n月j日 G:i', $at );
	};
	$c_label = array( 'active' => array( '契約中', 'green' ), 'pending' => array( '更新待ち', 'amber' ), 'cancelled' => array( '解約', 'gray' ) );

	$nav = array(
		'home'     => array( 'ダッシュボード', '<path d="M3 11l9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>' ),
		'contract' => array( 'ご契約', '<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>' ),
		'billing'  => array( 'お支払い', '<rect x="3" y="6" width="18" height="13" rx="2"/><path d="M3 10h18M7 15h4"/>' ),
		'meeting'  => array( 'ご予約', '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>' ),
		'studio'   => array( 'AIスタジオ', '<path d="M12 3l2.2 5.6L20 9l-4.5 3.8L17 19l-5-3.2L7 19l1.5-6.2L4 9l5.8-.4z"/>' ),
		'line'     => array( 'LINE連携', '<path d="M4 5h16v11H9l-5 4z"/>' ),
		'preview'  => array( 'プレビューアプリ', '<rect x="7" y="2" width="10" height="20" rx="2"/><path d="M11 18h2"/>' ),
		'account'  => array( 'アカウント', '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>' ),
	);
	$unpaid = count( array_filter( $d['invoices'], function ( $i ) {
		return in_array( $i['status'], array( 'UNPAID', 'PARTIALLY_PAID', 'FAILED' ), true );
	} ) );
	$late   = array_values( array_filter( $d['pay'], function ( $p ) {
		return false === $p['paid'] && $p['days'] >= 0;
	} ) );
	if ( $late && ! $unpaid ) {
		$unpaid = count( $late );
	}

	ob_start();
	?>
<div class="mp" id="mp">
  <header class="mp-top"><div class="mp-top-in">
    <a class="mp-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>"><span>APP<b>REX</b></span><small>マイページ</small></a>
    <div class="mp-top-r">
      <a class="mp-top-link" href="<?php echo esc_url( $sturl ); ?>">AIスタジオ</a>
      <span class="mp-user"><span class="mp-ava"><?php echo esc_html( mb_substr( $user->display_name, 0, 1 ) ); ?></span><span class="mp-uname"><?php echo esc_html( $user->display_name ); ?> 様</span></span>
      <a class="mp-top-link" href="<?php echo esc_url( wp_logout_url( apprex_mypage_url() ) ); ?>">ログアウト</a>
    </div>
  </div></header>
  <div class="mp-body">
    <nav class="mp-side" aria-label="マイページのメニュー">
      <?php foreach ( $nav as $k => $n ) : ?>
        <a href="#<?php echo esc_attr( $k ); ?>" data-tab="<?php echo esc_attr( $k ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><?php echo $n[1]; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- 固定のアイコン。 ?></svg><span><?php echo esc_html( $n[0] ); ?></span><?php echo ( 'billing' === $k && $unpaid ) ? '<i class="mp-dot">' . (int) $unpaid . '</i>' : ''; ?></a>
      <?php endforeach; ?>
      <div class="mp-side-help">
        <b>お困りのときは</b>
        <span>平日10:00〜18:00</span>
        <a href="<?php echo esc_url( $meet ); ?>">オンライン相談を予約</a>
      </div>
    </nav>
    <main class="mp-main">

    <section class="mp-panel" id="mp-home" data-panel="home">
      <div class="mp-head"><div><p class="mp-eyebrow"><?php echo esc_html( wp_date( 'Y年n月j日（' ) . mb_substr( '日月火水木金土', (int) wp_date( 'w' ), 1 ) . '）' ); ?></p><h1>こんにちは、<?php echo esc_html( $user->display_name ); ?> 様</h1></div></div>
      <?php foreach ( $late as $p ) : ?>
        <div class="mp-alert">
          <div><b><?php echo esc_html( 0 === $p['days'] ? '本日がお支払い期日です' : 'お支払いが確認できていません（期日から' . $p['days'] . '日）' ); ?></b>
          <span><?php echo esc_html( $p['plan'] . '・' . $p['month'] . '分' . ( $p['amount'] ? '・¥' . number_format( $p['amount'] ) . '（税抜）' : '' ) ); ?>。ご事情でお支払いが難しい場合は、早めにご相談ください。</span></div>
          <?php if ( $p['url'] && 'square' === $p['method'] ) : ?><a class="mp-btn is-pri" href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener">カードで支払う</a><?php else : ?><a class="mp-btn" href="#billing">お支払いを見る</a><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <div class="mp-kpis">
        <div class="mp-kpi"><small>ご契約</small><b><?php echo esc_html( $c0 ? ( $c0['title'] ? $c0['title'] : 'ご契約中' ) : ( $studio ? '契約前' : '—' ) ); ?></b><?php echo $c0 ? apprex_mp_badge( $c_label[ $c0['status'] ][0] ?? '契約中', $c_label[ $c0['status'] ][1] ?? 'green' ) : '<em>AIスタジオで相談できます</em>'; // phpcs:ignore ?></div>
        <div class="mp-kpi"><small>月額（税抜）</small><b class="num"><?php echo $c0 && $c0['monthly'] ? '¥' . esc_html( number_format( $c0['monthly'] ) ) : '—'; ?></b><em><?php echo $c0 && $c0['renewal'] ? '次回更新 ' . esc_html( $c0['renewal'] ) : '&nbsp;'; ?></em></div>
        <div class="mp-kpi"><small>最新のご請求</small>
          <?php if ( $inv0 ) : $st = apprex_mp_inv_state( $inv0['status'] ); ?>
            <b><?php echo esc_html( $inv0['month'] ); ?>分</b><?php echo apprex_mp_badge( $st[0], $st[1] ); // phpcs:ignore ?>
          <?php else : ?><b>—</b><em>ご請求はまだありません</em><?php endif; ?>
        </div>
        <div class="mp-kpi"><small>次のご予約</small><?php if ( $next ) : ?><b><?php echo esc_html( $when( $next['at'] ) ); ?></b><em>オンライン相談</em><?php else : ?><b>—</b><em><a href="<?php echo esc_url( $meet ); ?>">予約する</a></em><?php endif; ?></div>
      </div>

      <div class="mp-grid2">
        <div class="mp-card">
          <div class="mp-card-h"><h2>やること</h2><?php echo $d['todo'] ? apprex_mp_badge( count( $d['todo'] ) . '件', 'amber' ) : ''; // phpcs:ignore ?></div>
          <?php if ( $d['todo'] ) : ?>
            <ul class="mp-todo">
              <?php foreach ( $d['todo'] as $t ) : ?>
                <li class="is-<?php echo esc_attr( $t[4] ); ?>"><div><b><?php echo esc_html( $t[0] ); ?></b><span><?php echo esc_html( $t[1] ); ?></span></div><a class="mp-btn<?php echo 'warn' === $t[4] ? ' is-pri' : ''; ?>" href="<?php echo esc_url( $t[2] ); ?>"<?php echo 0 === strpos( $t[2], '#' ) ? '' : ' target="_blank" rel="noopener"'; ?>><?php echo esc_html( $t[3] ); ?></a></li>
              <?php endforeach; ?>
            </ul>
          <?php else : ?>
            <p class="mp-empty">いまお願いしていることはありません。</p>
          <?php endif; ?>
        </div>
        <div class="mp-card">
          <div class="mp-card-h"><h2><?php echo $c0 ? '制作・運用の状況' : 'AIスタジオの進み具合'; ?></h2></div>
          <?php
          $steps = function_exists( 'apprex_studio_stages' ) ? apprex_studio_stages() : array();
          if ( $stage >= 4 && function_exists( 'apprex_studio_prod_steps' ) ) {
              $ps = apprex_studio_prod_steps();
              echo '<ol class="mp-steps">';
              foreach ( $ps as $i => $label ) {
                  $cls = $i < $prod ? 'done' : ( $i === $prod ? 'now' : '' );
                  echo '<li class="' . esc_attr( $cls ) . '"><i>' . ( $i < $prod ? '✓' : (int) ( $i + 1 ) ) . '</i>' . esc_html( $label ) . ( $i === $prod && $prod < 6 ? '<em>進行中</em>' : '' ) . '</li>';
              }
              echo '</ol>';
          } elseif ( $steps ) {
              echo '<ol class="mp-steps">';
              foreach ( $steps as $i => $label ) {
                  $cls = $i < $stage ? 'done' : ( $i === $stage ? 'now' : '' );
                  echo '<li class="' . esc_attr( $cls ) . '"><i>' . ( $i < $stage ? '✓' : (int) ( $i + 1 ) ) . '</i>' . esc_html( $label ) . ( $i === $stage ? '<em>いまここ</em>' : '' ) . '</li>';
              }
              echo '</ol>';
          }
          ?>
          <a class="mp-btn" href="<?php echo esc_url( $sturl ); ?>">AIスタジオを開く</a>
        </div>
      </div>
      <?php if ( $d['notices'] ) : ?>
        <div class="mp-card">
          <div class="mp-card-h"><h2>お知らせ</h2><span class="mp-muted">マイページ・LINE・メールでお送りしたもの</span></div>
          <ul class="mp-notices">
          <?php foreach ( array_slice( $d['notices'], 0, 6 ) as $n ) : ?>
            <li class="is-<?php echo esc_attr( $n['tone'] ); ?>">
              <details><summary><i></i><b><?php echo esc_html( $n['title'] ); ?></b><time><?php echo esc_html( wp_date( 'n/j', (int) $n['t'] ) ); ?></time></summary>
              <p><?php echo nl2br( esc_html( preg_replace( '/^.*様\n\n/u', '', (string) $n['body'] ) ) ); ?></p>
              <?php if ( ! empty( $n['url'] ) ) : ?><a class="mp-btn is-pri is-sm" href="<?php echo esc_url( $n['url'] ); ?>" target="_blank" rel="noopener">カードで支払う</a><?php endif; ?>
              </details>
            </li>
          <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      <?php if ( $d['requests'] ) : ?>
        <div class="mp-card">
          <div class="mp-card-h"><h2>LINEでいただいたご依頼</h2></div>
          <table class="mp-table"><thead><tr><th>日時</th><th>ご依頼</th><th>状態</th></tr></thead><tbody>
          <?php foreach ( array_slice( $d['requests'], 0, 5 ) as $r ) : ?>
            <tr><td><?php echo esc_html( wp_date( 'n/j G:i', (int) $r['t'] ) ); ?></td><td><?php echo esc_html( $r['what'] ); ?></td><td><?php echo $r['done'] ? apprex_mp_badge( '対応済み', 'green' ) : apprex_mp_badge( '対応中', 'amber' ); // phpcs:ignore ?></td></tr>
          <?php endforeach; ?>
          </tbody></table>
        </div>
      <?php endif; ?>
    </section>

    <section class="mp-panel" id="mp-contract" data-panel="contract" hidden>
      <div class="mp-head"><h1>ご契約</h1></div>
      <?php if ( ! $d['contracts'] ) : ?>
        <div class="mp-card mp-empty-card"><b>ご契約はまだありません</b><p><?php echo $studio ? 'アプリのデザイン・仕様書づくりと開発のご相談は、AIスタジオから進められます。' : 'ご契約の情報が見つかりません。お手数ですが担当者までお問い合わせください。'; ?></p><a class="mp-btn is-pri" href="<?php echo esc_url( $sturl ); ?>">AIスタジオを開く</a>
        <?php if ( current_user_can( 'manage_options' ) ) : ?><p class="mp-admin-note">【管理者向け】このアカウント（<?php echo esc_html( $user->user_email ); ?>）に紐づく契約がありません。契約の「メール」を同じにするか、契約画面の「会員アカウントを発行する」で発行したアカウントでログインすると表示されます。</p><?php endif; ?>
        </div>
      <?php endif; ?>
      <?php foreach ( $d['contracts'] as $c ) : $cl = $c_label[ $c['status'] ] ?? array( '契約中', 'green' ); ?>
        <div class="mp-card">
          <div class="mp-card-h"><h2><?php echo esc_html( $c['title'] ? $c['title'] : 'ご契約' ); ?></h2><?php echo apprex_mp_badge( $cl[0], $cl[1] ); // phpcs:ignore ?></div>
          <?php
          echo apprex_mp_dl( // phpcs:ignore
              array(
                  array( '月額（税抜）', $c['monthly'] ? '¥' . number_format( $c['monthly'] ) : '' ),
                  array( '契約開始日', $c['start'] ),
                  array( '契約年数', $c['term'] ? $c['term'] . '年' : '' ),
                  array( '次回更新日', $c['renewal'] ),
                  array( 'ご契約者', trim( $c['company'] . ' ' . $c['name'] ) ),
                  array( 'メール', $c['email'] ),
              )
          );
          ?>
          <?php if ( $c['app_url'] || $c['login'] || $c['pass'] ) : ?>
            <h3 class="mp-sub">アプリの管理画面</h3>
            <div class="mp-cred">
              <?php if ( $c['login'] ) : ?><div><small>ログインID</small><code><?php echo esc_html( $c['login'] ); ?></code><button type="button" class="mp-copy" data-copy="<?php echo esc_attr( $c['login'] ); ?>">コピー</button></div><?php endif; ?>
              <?php if ( $c['pass'] ) : ?><div><small>パスワード</small><code class="mp-secret" data-secret="<?php echo esc_attr( $c['pass'] ); ?>">••••••••</code><button type="button" class="mp-reveal">表示</button><button type="button" class="mp-copy" data-copy="<?php echo esc_attr( $c['pass'] ); ?>">コピー</button></div><?php endif; ?>
            </div>
            <?php if ( $c['app_url'] ) : ?><a class="mp-btn is-pri" href="<?php echo esc_url( $c['app_url'] ); ?>" target="_blank" rel="noopener">アプリ製作ページを開く</a><?php endif; ?>
          <?php endif; ?>
          <h3 class="mp-sub">契約書</h3>
          <?php $mfl = function_exists( 'apprex_mf_status_label' ) ? apprex_mf_status_label( $c['mf'] ) : ''; ?>
          <div class="mp-row-acts">
            <?php echo apprex_mp_badge( $mfl ? $mfl : '未送付', 'signed' === $c['mf'] ? 'green' : ( 'sent' === $c['mf'] ? 'amber' : 'gray' ) ); // phpcs:ignore ?>
            <?php if ( $c['doc'] ) : ?><a class="mp-btn" href="<?php echo esc_url( $c['doc'] ); ?>" target="_blank" rel="noopener">契約書を表示</a><?php endif; ?>
            <?php if ( 'signed' !== $c['mf'] && $c['mf_url'] ) : ?><a class="mp-btn is-pri" href="<?php echo esc_url( $c['mf_url'] ); ?>" target="_blank" rel="noopener">締結する</a><?php endif; ?>
            <?php if ( $c['mf_pdf'] ) : ?><a class="mp-btn" href="<?php echo esc_url( $c['mf_pdf'] ); ?>" target="_blank" rel="noopener">署名済みのPDF</a><?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="mp-panel" id="mp-billing" data-panel="billing" hidden>
      <div class="mp-head"><h1>お支払い</h1></div>
      <div class="mp-card">
        <div class="mp-card-h"><h2>ご請求</h2></div>
        <?php if ( $d['invoices'] ) : ?>
          <table class="mp-table is-wide"><thead><tr><th>対象月</th><th>内容</th><th class="r">金額（税抜）</th><th>状態</th><th></th></tr></thead><tbody>
          <?php foreach ( $d['invoices'] as $iv ) : $st = apprex_mp_inv_state( $iv['status'] ); ?>
            <tr><td><?php echo esc_html( $iv['month'] ); ?></td><td><?php echo esc_html( $iv['plan'] ); ?></td><td class="r num"><?php echo $iv['amount'] ? '¥' . esc_html( number_format( $iv['amount'] ) ) : '—'; ?></td><td><?php echo apprex_mp_badge( $st[0], $st[1] ); // phpcs:ignore ?><?php echo $iv['paid'] ? '<small class="mp-muted">' . esc_html( mysql2date( 'n/j', $iv['paid'] ) ) . '</small>' : ''; ?></td>
            <td class="r"><?php if ( $iv['url'] && 'PAID' !== $iv['status'] ) : ?><a class="mp-btn is-pri is-sm" href="<?php echo esc_url( $iv['url'] ); ?>" target="_blank" rel="noopener">支払う</a><?php elseif ( $iv['url'] ) : ?><a class="mp-btn is-sm" href="<?php echo esc_url( $iv['url'] ); ?>" target="_blank" rel="noopener">明細</a><?php endif; ?></td></tr>
          <?php endforeach; ?>
          </tbody></table>
          <p class="mp-note">お支払いは Square の安全なページで行います（カード情報はこのサイトでは預かりません）。</p>
        <?php else : ?>
          <p class="mp-empty">ご請求はまだありません。</p>
        <?php endif; ?>
      </div>
      <?php foreach ( $d['pay'] as $p ) : $pl = true === $p['paid'] ? array( '支払済み', 'green' ) : ( null === $p['paid'] ? array( '確認中', 'gray' ) : ( $p['days'] >= 0 ? array( '期日を過ぎています', 'red' ) : array( '未払い', 'amber' ) ) ); ?>
        <div class="mp-card">
          <div class="mp-card-h"><h2>お支払いの状況</h2><?php echo apprex_mp_badge( $pl[0], $pl[1] ); // phpcs:ignore ?></div>
          <?php echo apprex_mp_dl( array( array( '内容', $p['plan'] ), array( '対象月', $p['month'] ), array( '金額（税抜）', $p['amount'] ? '¥' . number_format( $p['amount'] ) : '' ), array( 'お支払い期日', wp_date( 'Y年n月j日', strtotime( $p['due'] ) ) ), array( '状況', apprex_bn_state_text( $p ) ) ) ); // phpcs:ignore ?>
          <?php if ( false === $p['paid'] && $p['url'] && 'square' === $p['method'] ) : ?><div class="mp-row-acts"><a class="mp-btn is-pri" href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener">カードで支払う</a></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
      <?php if ( $d['pay'] && function_exists( 'apprex_bn_policy' ) ) : ?>
        <details class="mp-card mp-policy"><summary>お支払いが遅れた場合について</summary><p><?php echo nl2br( esc_html( apprex_bn_policy() ) ); ?></p><p class="mp-note">お支払いのご相談は、LINEで「支払いの相談」と送るか、担当者までご連絡ください。</p></details>
      <?php endif; ?>
      <?php foreach ( $active as $c ) : ?>
        <div class="mp-card">
          <div class="mp-card-h"><h2>お支払い方法</h2><span class="mp-muted"><?php echo esc_html( $c['title'] ); ?></span></div>
          <?php echo apprex_mp_dl( array( array( '支払い方法', $c['method'] ), array( '支払い期日', $c['payday'] ? '毎月' . $c['payday'] . '日' : '' ), array( '最終入金確認日', $c['lastpaid'] ) ) ); // phpcs:ignore ?>
        </div>
      <?php endforeach; ?>
    </section>

    <section class="mp-panel" id="mp-meeting" data-panel="meeting" hidden>
      <div class="mp-head"><h1>ご予約</h1><a class="mp-btn is-pri" href="<?php echo esc_url( $meet ); ?>">オンライン相談を予約</a></div>
      <div class="mp-card">
        <?php if ( $d['meetings'] ) : ?>
          <table class="mp-table is-wide"><thead><tr><th>日時</th><th>内容</th><th>状態</th></tr></thead><tbody>
          <?php foreach ( $d['meetings'] as $mt ) : ?>
            <tr><td><?php echo esc_html( $when( $mt['at'] ) ); ?></td><td>オンライン相談（Google Meet）</td><td><?php echo $mt['cancel'] ? apprex_mp_badge( 'キャンセル', 'gray' ) : ( $mt['at'] > time() ? apprex_mp_badge( '予約中', 'blue' ) : apprex_mp_badge( '実施済み', 'green' ) ); // phpcs:ignore ?></td></tr>
          <?php endforeach; ?>
          </tbody></table>
        <?php else : ?>
          <p class="mp-empty">ご予約はまだありません。開発や料金のご相談は、オンライン相談（無料）でお気軽にどうぞ。</p>
        <?php endif; ?>
      </div>
    </section>

    <section class="mp-panel" id="mp-studio" data-panel="studio" hidden>
      <div class="mp-head"><h1>AIスタジオ</h1></div>
      <div class="mp-tiles">
        <?php
        $orig = json_decode( (string) get_user_meta( $user->ID, 'apprex_orig_spec', true ), true );
        $hp   = json_decode( (string) get_user_meta( $user->ID, 'apprex_hp_spec', true ), true );
        $app  = json_decode( (string) get_user_meta( $user->ID, 'apprex_studio_state', true ), true );
        $tiles = array(
            array( 'アプリ（ノーコード）', ! empty( $app['d']['name'] ) ? $app['d']['name'] : '', apprex_studio_url( 'app' ) ),
            array( 'ホームページ', ! empty( $hp['name'] ) ? $hp['name'] : '', apprex_studio_url( 'hp' ) ),
            array( 'マッチング・オリジナル', ! empty( $orig['name'] ) ? $orig['name'] : '', apprex_studio_url( 'matching' ) ),
        );
        foreach ( $tiles as $t ) :
            ?>
          <a class="mp-tile" href="<?php echo esc_url( $t[2] ); ?>"><small><?php echo esc_html( $t[0] ); ?></small><b><?php echo esc_html( $t[1] ? $t[1] : 'まだ作っていません' ); ?></b><span><?php echo $t[1] ? '続きを開く →' : 'AIで作る →'; ?></span></a>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="mp-panel" id="mp-line" data-panel="line" hidden>
      <div class="mp-head"><h1>LINE連携</h1></div>
      <div class="mp-card mp-line">
        <span class="mp-line-mark">LINE</span>
        <?php if ( $line ) : ?>
          <div><b>LINEと連携しています</b><p>公式LINEで「今月の支払いは？」「契約内容を教えて」のように聞くと、AIがあなたの情報を見てすぐお答えします。プラン変更などのご依頼も、LINEで受け付けます。お知らせは、次にLINEで話しかけたときにまとめてお届けします。</p></div>
        <?php else : ?>
          <div><b>LINEと連携しませんか？</b><p>連携すると、契約・お支払い・予約の確認やご依頼が、公式LINEでできるようになります。スマホで下のボタンを押すと、LINEが開いて連携が完了します。</p>
          <?php if ( function_exists( 'apprex_liff_ready' ) && apprex_liff_ready() ) : ?><a class="mp-btn is-line" href="<?php echo esc_url( $liff ); ?>">LINEと連携する</a><?php else : ?><p class="mp-muted">準備中です。</p><?php endif; ?></div>
        <?php endif; ?>
      </div>
    </section>

    <section class="mp-panel" id="mp-preview" data-panel="preview" hidden>
      <div class="mp-head"><h1>プレビューアプリ</h1></div>
      <div class="mp-card mp-legacy"><?php echo function_exists( 'apprex_member_preview_app_section' ) ? apprex_member_preview_app_section() : ''; // phpcs:ignore ?></div>
    </section>

    <section class="mp-panel" id="mp-account" data-panel="account" hidden>
      <div class="mp-head"><h1>アカウント</h1></div>
      <div class="mp-card">
        <?php
        echo apprex_mp_dl( // phpcs:ignore
            array(
                array( 'お名前', $user->display_name ),
                array( 'メールアドレス', $user->user_email ),
                array( '会社名', (string) get_user_meta( $user->ID, 'apprex_company', true ) ),
                array( 'LINE', $line ? '連携済み' : '未連携' ),
                array( '登録日', wp_date( 'Y年n月j日', strtotime( $user->user_registered ) ) ),
            )
        );
        ?>
        <div class="mp-row-acts"><a class="mp-btn" href="<?php echo esc_url( wp_lostpassword_url( apprex_mypage_url() ) ); ?>">パスワードを変更する</a></div>
        <p class="mp-note">登録情報の変更・ご解約などは、担当者またはお問い合わせ窓口（平日10:00〜18:00）へご連絡ください。LINE連携済みの方は、LINEでもご依頼いただけます。</p>
      </div>
    </section>

    </main>
  </div>
</div>
<script>
(function(){
  var root=document.getElementById('mp'); if(!root) return;
  var tabs=root.querySelectorAll('[data-tab]'), panels=root.querySelectorAll('[data-panel]');
  function show(k){
    var ok=false; panels.forEach(function(p){ var on=p.getAttribute('data-panel')===k; p.hidden=!on; if(on) ok=true; });
    if(!ok){ k='home'; panels.forEach(function(p){ p.hidden=p.getAttribute('data-panel')!=='home'; }); }
    tabs.forEach(function(a){ if(a.getAttribute('data-tab')===k){ a.setAttribute('aria-current','page'); } else { a.removeAttribute('aria-current'); } });
  }
  function fromHash(){ show((location.hash||'#home').slice(1)); }
  window.addEventListener('hashchange',function(){ fromHash(); var m=root.querySelector('.mp-main'); if(m&&window.innerWidth<900) m.scrollIntoView({block:'start'}); });
  fromHash();
  root.addEventListener('click',function(e){
    var c=e.target.closest('.mp-copy'); if(c){ navigator.clipboard&&navigator.clipboard.writeText(c.getAttribute('data-copy')).then(function(){ var t=c.textContent; c.textContent='コピーしました'; setTimeout(function(){ c.textContent=t; },1400); }); return; }
    var r=e.target.closest('.mp-reveal'); if(r){ var s=r.parentNode.querySelector('.mp-secret'); var on=s.textContent.indexOf('•')===0; s.textContent=on?s.getAttribute('data-secret'):'••••••••'; r.textContent=on?'隠す':'表示'; }
  });
})();
</script>
	<?php
	return ob_get_clean();
}
