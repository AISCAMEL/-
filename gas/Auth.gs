/**
 * Auth.gs — BUYMO 業務システム 認証（セッション方式）
 *
 * - パスワードはソルト付き SHA-256 ハッシュで「認証ユーザー」シートに保存（平文保存しない）。
 * - ログインで発行したトークンを「セッション」シートに保存（8時間有効）。
 * - 書き込みAPI（type:"case"/"note" 等）は有効なトークンを要求（ユーザー登録後）。
 *
 * セットアップ
 *   1) 管理者を作る：エディタで createUser('you@example.com','強いパスワード','hq','氏名') を実行。
 *      （または seedAdmin() を実行→必ず changePassword で変更）
 *   2) 加盟店アカウントは createUser('store@example.com','pw','partner','いわき店') のように role='partner'。
 *
 * 重要（限界）
 *   GASウェブアプリは応答にCORSヘッダを返さないため、ログインは JSONP(GET) で受けます。
 *   そのため**パスワードがURL/サーバーログに残り得ます**。本番でセンシティブに運用する場合は
 *   docs/BUYMO_認証設計.md の「本番ハードニング」を参照（独自バックエンド/SSO等）。
 */

function authUsersSheet_() { var ss = openBook_(); return ss.getSheetByName('認証ユーザー') || ensureSheet_(ss, '認証ユーザー', ['メール', 'ソルト', 'ハッシュ', 'ロール', '氏名', '作成']); }
function authSessSheet_() { var ss = openBook_(); return ss.getSheetByName('セッション') || ensureSheet_(ss, 'セッション', ['トークン', 'メール', 'ロール', '氏名', '期限']); }

function hashPw_(pw, salt) {
  var raw = Utilities.computeDigest(Utilities.DigestAlgorithm.SHA_256, salt + '|' + String(pw), Utilities.Charset.UTF_8);
  return raw.map(function (b) { return ('0' + (b & 0xff).toString(16)).slice(-2); }).join('');
}

function createUser(email, pw, role, name) {
  email = String(email || '').toLowerCase().trim();
  if (!email || !pw) throw new Error('email/pw required');
  var sh = authUsersSheet_();
  var v = sh.getDataRange().getValues();
  for (var r = 1; r < v.length; r++) if (String(v[r][0]).toLowerCase() === email) throw new Error('already exists: ' + email);
  var salt = Utilities.getUuid();
  sh.appendRow([email, salt, hashPw_(pw, salt), role || 'hq', name || '', new Date()]);
  return 'created: ' + email + ' (' + (role || 'hq') + ')';
}
function changePassword(email, newPw) {
  email = String(email || '').toLowerCase().trim();
  var sh = authUsersSheet_(), v = sh.getDataRange().getValues();
  for (var r = 1; r < v.length; r++) if (String(v[r][0]).toLowerCase() === email) {
    var salt = Utilities.getUuid();
    sh.getRange(r + 1, 2).setValue(salt); sh.getRange(r + 1, 3).setValue(hashPw_(newPw, salt));
    return 'password updated: ' + email;
  }
  return 'not found: ' + email;
}
function seedAdmin() { return createUser('admin@buymo.local', 'changeme', 'hq', '管理者'); }

function authHasUsers_() { return authUsersSheet_().getLastRow() > 1; }

function authLogin_(email, pw, role) {
  email = String(email || '').toLowerCase().trim();
  var sh = authUsersSheet_(), v = sh.getDataRange().getValues();
  for (var r = 1; r < v.length; r++) {
    if (String(v[r][0]).toLowerCase() === email) {
      if (hashPw_(pw, v[r][1]) === String(v[r][2])) {
        var token = Utilities.getUuid();
        var ttl = 8 * 3600 * 1000;
        authSessSheet_().appendRow([token, email, v[r][3], v[r][4], new Date(Date.now() + ttl)]);
        return { ok: true, token: token, role: v[r][3], name: v[r][4], ttl: ttl };
      }
      return { ok: false, error: 'パスワードが違います' };
    }
  }
  return { ok: false, error: 'ユーザーが見つかりません' };
}
function verifyToken_(token) {
  if (!token) return null;
  var sh = authSessSheet_(), v = sh.getDataRange().getValues(), now = new Date();
  for (var r = 1; r < v.length; r++) {
    if (String(v[r][0]) === String(token)) {
      return (new Date(v[r][4]) > now) ? { email: v[r][1], role: v[r][2], name: v[r][3] } : null;
    }
  }
  return null;
}
function authLogout_(token) {
  var sh = authSessSheet_(), v = sh.getDataRange().getValues();
  for (var r = 1; r < v.length; r++) if (String(v[r][0]) === String(token)) { sh.deleteRow(r + 1); return; }
}

/* 書き込みAPIの保護：ユーザー未設定なら従来通り通す（後方互換）。設定後は有効トークン必須。 */
function requireAuth_(data) {
  if (!authHasUsers_()) return true;
  return !!verifyToken_(data && data.token);
}
