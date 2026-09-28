<?php
/**
 * apply.php ― LIFF申込 → GAS API プロキシ
 *
 * LIFFの申込ページ(index.html)と同じフォルダ（例：/liff/）に置く。
 * ブラウザからは同一オリジンのこのファイルを呼ぶ＝CORSを受けない。
 * このPHPがサーバー側から GAS Web API を叩く（トークンはここに置くので安全）。
 *
 * ▼あなたの値（既に設定済み。必要なら変更）
 */
$GAS_URL = 'https://script.google.com/macros/s/AKfycbw1UVvWCzaA_d1UbXoP2SjfmefXIjCVR_FoKIWvwgN0uAvKAhqocH8a6HGm4v5TyMDKlQ/exec';
$TOKEN   = 'carmel2026secret';

header('Content-Type: application/json; charset=utf-8');

$fields = ['userId','name','birth','tel','zip','addr','emp','ty','tm',
           'income','pay','other','shinsa','jijou','house','down','guar','car'];
$post = ['action' => 'apply', 'token' => $TOKEN];
foreach ($fields as $f) {
  $post[$f] = isset($_POST[$f]) ? $_POST[$f] : '';
}

$ch = curl_init($GAS_URL);
curl_setopt_array($ch, [
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => http_build_query($post),
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_FOLLOWLOCATION => true,   // GASは302リダイレクトするため追従が必須
  CURLOPT_TIMEOUT        => 25,
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($res === false || $code >= 400) {
  echo json_encode(['ok' => false, 'error' => 'proxy_error', 'code' => $code]);
  exit;
}
echo $res;
