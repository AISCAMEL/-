<?php
/**
 * カーメル 案件管理ショートコード（本部・加盟店共通）
 *
 * 設置方法（推奨）：
 *   1. プラグイン「Code Snippets」をインストール＆有効化
 *   2. Snippets → Add New → 下記を全部貼り付け → 「Run snippet everywhere」→ Save & Activate
 *   3. 固定ページを作り、本文に次のショートコードを入れる
 *        本部ページ    : [carmel_cases]
 *        加盟店ページ  : [carmel_cases franchisee="福島本店"]   ← 店名は加盟店マスタの名称
 *
 * ※ 下の2行（URL・トークン）は、あなたの値が入っています。変更不要のはずです。
 */

define('CARMEL_API_BASE',  'https://script.google.com/macros/s/AKfycbw1UVvWCzaA_d1UbXoP2SjfmefXIjCVR_FoKIWvwgN0uAvKAhqocH8a6HGm4v5TyMDKlQ/exec');
define('CARMEL_API_TOKEN', 'carmel2026secret');

// --- API呼び出し（WordPressサーバーから。CORSの影響を受けない） ---
function carmel_api_get($params) {
  $params['token'] = CARMEL_API_TOKEN;
  $res = wp_remote_get(CARMEL_API_BASE . '?' . http_build_query($params), array('timeout' => 20));
  if (is_wp_error($res)) return null;
  return json_decode(wp_remote_retrieve_body($res), true);
}
function carmel_api_post($params) {
  $params['token'] = CARMEL_API_TOKEN;
  $url = CARMEL_API_BASE . '?' . http_build_query(array('action' => $params['action'], 'token' => CARMEL_API_TOKEN));
  $res = wp_remote_post($url, array('timeout' => 20, 'body' => $params));
  if (is_wp_error($res)) return null;
  return json_decode(wp_remote_retrieve_body($res), true);
}

function carmel_cases_shortcode($atts) {
  if (!is_user_logged_in()) return '<p>ログインしてご利用ください。</p>';
  $atts = shortcode_atts(array('franchisee' => ''), $atts);

  // --- ボタン押下時（承認/否決・アサイン）の処理 ---
  $notice = '';
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['carmel_action'])) {
    if (empty($_POST['carmel_nonce']) || !wp_verify_nonce($_POST['carmel_nonce'], 'carmel_act')) {
      $notice = 'セッションが切れました。ページを再読み込みしてください。';
    } else {
      $act = sanitize_text_field($_POST['carmel_action']);
      if ($act === 'result') {
        $r = carmel_api_post(array(
          'action'        => 'result',
          'caseId'        => sanitize_text_field($_POST['caseId']),
          'creditorIndex' => intval($_POST['creditorIndex']),
          'result'        => sanitize_text_field($_POST['result']),
          'amount'        => preg_replace('/[^0-9]/', '', isset($_POST['amount']) ? $_POST['amount'] : '')
        ));
        $notice = ($r && !empty($r['ok'])) ? '記録しました。' : '記録に失敗しました。';
      } elseif ($act === 'assign') {
        $r = carmel_api_post(array(
          'action'         => 'assign',
          'caseId'         => sanitize_text_field($_POST['caseId']),
          'franchiseeName' => sanitize_text_field($_POST['franchiseeName'])
        ));
        $notice = ($r && !empty($r['ok'])) ? 'アサインしました。' : 'アサインに失敗しました。';
      }
    }
  }

  // --- 案件一覧を取得 ---
  $params = array('action' => 'cases');
  if ($atts['franchisee'] !== '') $params['franchisee'] = $atts['franchisee'];
  $data = carmel_api_get($params);

  ob_start();
  ?>
  <style>
    .carmel-card{border:1px solid #ddd;border-radius:8px;padding:14px;margin:0 0 14px;}
    .carmel-head{display:flex;justify-content:space-between;align-items:center;}
    .carmel-badge{padding:2px 10px;border-radius:12px;color:#fff;font-weight:bold;}
    .rank-A{background:#1a8a3a}.rank-B{background:#2a72d4}.rank-C{background:#d59300}.rank-D{background:#b03030}
    .carmel-sum{color:#444;font-size:13px;margin:8px 0;line-height:1.7;}
    .carmel-cred{display:flex;align-items:center;gap:8px;flex-wrap:wrap;border-top:1px dashed #eee;padding:6px 0;}
    .carmel-cred form{display:inline;margin:0;}
    .carmel-btn{padding:4px 10px;border:0;border-radius:4px;color:#fff;cursor:pointer;}
    .btn-ok{background:#1a8a3a}.btn-ng{background:#999}.btn-assign{background:#2a72d4}
    .carmel-res{font-weight:bold;}
    .carmel-notice{padding:10px;background:#eef6ff;border:1px solid #cfe2ff;border-radius:6px;margin-bottom:12px;}
  </style>
  <?php
  if ($notice) echo '<div class="carmel-notice">' . esc_html($notice) . '</div>';
  if (!$data || empty($data['ok'])) {
    echo '<p>データの取得に失敗しました。URL・トークンの設定をご確認ください。</p>';
    return ob_get_clean();
  }

  echo '<p>案件数：<b>' . intval($data['count']) . '</b></p>';
  $nonce = wp_create_nonce('carmel_act');

  foreach ($data['cases'] as $c) {
    $rankLetter = preg_replace('/[^A-D]/', '', $c['rank']);
    ?>
    <div class="carmel-card">
      <div class="carmel-head">
        <div><b><?php echo esc_html($c['name']); ?></b>
          <span style="color:#888;">（<?php echo esc_html($c['caseId']); ?>）</span></div>
        <div><span class="carmel-badge rank-<?php echo esc_attr($rankLetter); ?>">
          <?php echo $c['rank'] ? esc_html($c['rank']) : '-'; ?></span>
          <?php echo intval($c['score']); ?>点</div>
      </div>
      <div class="carmel-sum">
        受付:<?php echo esc_html($c['receivedAt']); ?> ／ 状態:<?php echo esc_html($c['status']); ?>
        <?php if ($c['franchisee']) echo ' ／ 担当:' . esc_html($c['franchisee']); ?><br>
        年収:<?php echo esc_html($c['summary']['income']); ?>
        ／ <?php echo esc_html($c['summary']['employment']); ?>
        ／ 勤続:<?php echo esc_html($c['summary']['tenure']); ?>
        ／ 他社借入:<?php echo esc_html($c['summary']['otherLoan']); ?>
        ／ 滞納:<?php echo esc_html($c['summary']['overdue']); ?>
        ／ 頭金:<?php echo esc_html($c['summary']['downPayment']); ?>
        ／ 保証人:<?php echo esc_html($c['summary']['guarantor']); ?>
      </div>

      <?php foreach ($c['creditors'] as $cr) { ?>
      <div class="carmel-cred">
        <span style="min-width:180px;"><?php echo intval($cr['index']); ?>. <?php echo esc_html($cr['name']); ?>
          <small>(<?php echo esc_html($cr['rate']); ?>)</small></span>
        <?php if ($cr['result'] === '承認' || $cr['result'] === '否決') { ?>
          <span class="carmel-res"><?php echo esc_html($cr['result']);
            if ($cr['amount'] !== '') echo ' ' . esc_html($cr['amount']) . '円'; ?></span>
        <?php } else { ?>
          <form method="post">
            <input type="hidden" name="carmel_nonce" value="<?php echo esc_attr($nonce); ?>">
            <input type="hidden" name="carmel_action" value="result">
            <input type="hidden" name="caseId" value="<?php echo esc_attr($c['caseId']); ?>">
            <input type="hidden" name="creditorIndex" value="<?php echo intval($cr['index']); ?>">
            <input type="text" name="amount" placeholder="承認額(円)" style="width:110px;">
            <button class="carmel-btn btn-ok" name="result" value="承認">承認</button>
            <button class="carmel-btn btn-ng" name="result" value="否決">否決</button>
          </form>
        <?php } ?>
      </div>
      <?php } ?>

      <?php if ($atts['franchisee'] === '') { // 本部ページのみ加盟店アサイン操作を表示 ?>
      <form method="post" style="margin-top:8px;">
        <input type="hidden" name="carmel_nonce" value="<?php echo esc_attr($nonce); ?>">
        <input type="hidden" name="carmel_action" value="assign">
        <input type="hidden" name="caseId" value="<?php echo esc_attr($c['caseId']); ?>">
        <input type="text" name="franchiseeName" placeholder="加盟店名"
               value="<?php echo esc_attr($c['franchisee']); ?>" style="width:150px;">
        <button class="carmel-btn btn-assign">加盟店アサイン</button>
      </form>
      <?php } ?>
    </div>
    <?php
  }
  return ob_get_clean();
}
add_shortcode('carmel_cases', 'carmel_cases_shortcode');
