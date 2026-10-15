<?php
/**
 * apply.php ― LIFF申込 → CarMel本番システム（Next.js/Supabase）へ中継
 *
 * LIFFの申込ページ(index.html)と同じフォルダ（例：/liff/）に置く。
 * ブラウザからは同一オリジンのこのファイルを呼ぶ＝CORS・鍵露出なし。
 * このPHPがサーバー側から本番の審査受付API /api/intake/shinsa を叩く。
 *
 * 受付APIは「入れ子payload（meta＋fields[]）」を受け取り：
 *   applications登録 → 店舗マッチング → ログイン発行(メール) → 顧客マスタ → Slack/Asana通知
 * を自動で行う。
 *
 * ▼設定（Vercelの環境変数 INTAKE_API_KEY と同じ値を入れる）
 */
$INTAKE_URL = 'https://new-repository-livid-one.vercel.app/api/intake/shinsa';
$API_KEY    = 'PUT_YOUR_INTAKE_API_KEY';   // ← Vercelの INTAKE_API_KEY と同じ文字列に変更

header('Content-Type: application/json; charset=utf-8');

// ---- 受け取り（index.html から POST される各項目） ----
function p($k){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : ''; }

$name   = p('name');
$income = p('income');
$ty     = p('ty');
$tm     = p('tm');
$down   = p('down') === '1' ? '頭金あり' : '頭金なし';
$guar   = p('guar') === '1' ? 'はい' : 'いいえ';

// ---- 本番API形式（meta ＋ fields[]）に組み立て ----
$payload = [
  'source'     => 'LINE審査',
  'no_account' => '0',                  // ログイン発行する（マイページ利用のため）
  'meta' => [
    'name'  => $name,
    'kana'  => '',
    'phone' => p('tel'),
    'email' => p('email'),
    'car'   => p('car'),
    'store' => '',
    'storeKey' => '',
  ],
  'fields' => [
    ['section'=>'ご状況',        'label'=>'現在の事情',          'value'=>p('jijou')],
    ['section'=>'ご状況',        'label'=>'これまでの審査社数',  'value'=>p('shinsa')],
    ['section'=>'お客様情報',    'label'=>'お名前',              'value'=>$name],
    ['section'=>'お客様情報',    'label'=>'生年月日',            'value'=>p('birth')],
    ['section'=>'お客様情報',    'label'=>'携帯電話',            'value'=>p('tel')],
    ['section'=>'お客様情報',    'label'=>'郵便番号',            'value'=>p('zip')],
    ['section'=>'お客様情報',    'label'=>'ご住所',              'value'=>p('addr')],
    ['section'=>'お仕事',        'label'=>'雇用形態',            'value'=>p('emp')],
    ['section'=>'お仕事',        'label'=>'勤続年数',            'value'=>($ty.'年'.$tm.'ヶ月')],
    ['section'=>'お仕事',        'label'=>'年収',                'value'=>($income!=='' ? $income.'万円' : '')],
    ['section'=>'ご希望',        'label'=>'月々支払い可能額',    'value'=>p('pay')],
    ['section'=>'ご希望',        'label'=>'他社お借入',          'value'=>p('other')],
    ['section'=>'お住まい',      'label'=>'住居',                'value'=>p('house')],
    ['section'=>'ご希望',        'label'=>'頭金',                'value'=>$down],
    ['section'=>'保証人',        'label'=>'保証人',              'value'=>$guar],
    ['section'=>'ご希望',        'label'=>'希望の車名',          'value'=>p('car')],
    ['section'=>'連携',          'label'=>'LINE_ID',             'value'=>p('userId')],
  ],
  'files' => [],
];

$ch = curl_init($INTAKE_URL);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
  CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'x-api-key: '.$API_KEY],
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_TIMEOUT        => 25,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false || $code >= 400) {
  echo json_encode(['ok' => false, 'error' => 'intake_error', 'code' => $code, 'detail' => $res]);
  exit;
}
echo $res;   // {ok:true, application_no, store_matched, account:{...}}
