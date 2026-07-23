/* =====================================================================
 * バイモダイレクト 加盟店ポータル サーバー（依存ライブラリ不要）
 *   - site/ を静的配信
 *   - /partner/* を「本物のサーバー認証」で保護（社外秘）
 *   - パスワードは scrypt ハッシュ、セッションは HMAC 署名Cookie
 *   - ログインを access.log に記録（漏洩抑止・監査）
 *
 * 実行：  node server/server.js        （既定 http://localhost:8080）
 * 環境変数：
 *   PORT         待受ポート（既定 8080）
 *   BMD_SECRET   セッション署名の秘密鍵（本番では必ず設定）
 *   SESSION_HOURS セッション有効時間（既定 12）
 * ===================================================================== */
"use strict";
const http = require("http");
const fs = require("fs");
const path = require("path");
const crypto = require("crypto");

const ROOT = path.join(__dirname, "..", "site");
const PORT = parseInt(process.env.PORT || "8080", 10);
const SECRET = process.env.BMD_SECRET || "CHANGE-ME-IN-PRODUCTION";
const SESSION_MS = (parseInt(process.env.SESSION_HOURS || "12", 10)) * 3600 * 1000;
const ACCOUNTS_FILE = path.join(__dirname, "partners.json");
const ACCESS_LOG = path.join(__dirname, "access.log");
const DATA_DIR = path.join(__dirname, "data");
const RECORDS_FILE = path.join(DATA_DIR, "records.json");

/* ---------- パスワード（scrypt） ---------- */
function hashPassword(pw, salt) {
  salt = salt || crypto.randomBytes(16).toString("hex");
  const h = crypto.scryptSync(String(pw), salt, 64).toString("hex");
  return salt + ":" + h;
}
function verifyPassword(pw, stored) {
  try {
    const i = String(stored).indexOf(":");
    const salt = stored.slice(0, i), h = stored.slice(i + 1);
    const hh = crypto.scryptSync(String(pw), salt, 64).toString("hex");
    return crypto.timingSafeEqual(Buffer.from(h, "hex"), Buffer.from(hh, "hex"));
  } catch (e) { return false; }
}

/* ---------- セッション（HMAC署名トークン） ---------- */
function signToken(payload) {
  const body = Buffer.from(JSON.stringify(payload)).toString("base64url");
  const mac = crypto.createHmac("sha256", SECRET).update(body).digest("base64url");
  return body + "." + mac;
}
function verifyToken(token) {
  if (!token || token.indexOf(".") < 0) return null;
  const [body, mac] = token.split(".");
  const exp = crypto.createHmac("sha256", SECRET).update(body).digest("base64url");
  const a = Buffer.from(mac), b = Buffer.from(exp);
  if (a.length !== b.length || !crypto.timingSafeEqual(a, b)) return null;
  try {
    const p = JSON.parse(Buffer.from(body, "base64url").toString());
    if (p.exp && Date.now() > p.exp) return null;
    return p;
  } catch (e) { return null; }
}

/* ---------- アカウント ---------- */
function loadAccounts() {
  try { return JSON.parse(fs.readFileSync(ACCOUNTS_FILE, "utf8")); }
  catch (e) { return {}; }
}
// 初回起動時、アカウントが無ければデモ用を自動生成
function ensureSeed() {
  if (fs.existsSync(ACCOUNTS_FILE)) return;
  const seed = {
    "BMD-001": { store: "いわき店", pass: hashPassword("bmd-demo-2026"), active: true },
    "HQ-ADMIN": { store: "本部", pass: hashPassword("admin-demo-2026"), active: true, role: "admin" }
  };
  fs.writeFileSync(ACCOUNTS_FILE, JSON.stringify(seed, null, 2));
  console.log("→ partners.json を作成しました");
  console.log("   加盟店デモ : BMD-001 / bmd-demo-2026");
  console.log("   本部デモ   : HQ-ADMIN / admin-demo-2026");
}

/* ---------- ユーティリティ ---------- */
function parseCookies(req) {
  const out = {}; const h = req.headers.cookie || "";
  h.split(";").forEach(function (p) {
    const i = p.indexOf("="); if (i < 0) return;
    out[p.slice(0, i).trim()] = decodeURIComponent(p.slice(i + 1).trim());
  });
  return out;
}
function readBody(req) {
  return new Promise(function (resolve) {
    let d = ""; req.on("data", function (c) { d += c; if (d.length > 1e6) req.destroy(); });
    req.on("end", function () { try { resolve(JSON.parse(d || "{}")); } catch (e) { resolve({}); } });
  });
}
function log(line) {
  const ts = new Date().toISOString();
  try { fs.appendFileSync(ACCESS_LOG, ts + " " + line + "\n"); } catch (e) {}
}
function clientIp(req) {
  return (req.headers["x-forwarded-for"] || "").split(",")[0].trim() || req.socket.remoteAddress || "-";
}
function isSecure(req) {
  return (req.headers["x-forwarded-proto"] || "").indexOf("https") === 0;
}
function setCookie(res, name, value, opts) {
  opts = opts || {};
  let c = name + "=" + encodeURIComponent(value) + "; Path=/; SameSite=Lax";
  if (opts.httpOnly) c += "; HttpOnly";
  if (opts.maxAge != null) c += "; Max-Age=" + opts.maxAge;
  if (opts.secure) c += "; Secure";
  const prev = res.getHeader("Set-Cookie") || [];
  res.setHeader("Set-Cookie", prev.concat(c));
}

const MIME = {
  ".html": "text/html; charset=utf-8", ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8", ".json": "application/json; charset=utf-8",
  ".svg": "image/svg+xml", ".png": "image/png", ".jpg": "image/jpeg", ".jpeg": "image/jpeg",
  ".gif": "image/gif", ".ico": "image/x-icon", ".pdf": "application/pdf",
  ".woff": "font/woff", ".woff2": "font/woff2", ".txt": "text/plain; charset=utf-8"
};

/* ---------- 静的配信 ---------- */
function serveStatic(req, res, urlPath) {
  let rel = decodeURIComponent(urlPath.split("?")[0]);
  if (rel.endsWith("/")) rel += "index.html";
  const full = path.join(ROOT, rel);
  // ディレクトリトラバーサル防止
  if (full.indexOf(ROOT) !== 0) { res.writeHead(403); return res.end("Forbidden"); }
  fs.stat(full, function (err, st) {
    if (err) { res.writeHead(404, { "Content-Type": "text/html; charset=utf-8" }); return res.end("<h1>404 Not Found</h1>"); }
    if (st.isDirectory()) return serveStatic(req, res, rel + "/index.html");
    const ext = path.extname(full).toLowerCase();
    res.writeHead(200, {
      "Content-Type": MIME[ext] || "application/octet-stream",
      "X-Content-Type-Options": "nosniff",
      // 社外秘：検索エンジンにインデックスさせない
      "X-Robots-Tag": "noindex, nofollow"
    });
    fs.createReadStream(full).pipe(res);
  });
}

/* ---------- 保存レコード（申込・報告・書類の控え） ---------- */
function loadRecords() {
  try { return JSON.parse(fs.readFileSync(RECORDS_FILE, "utf8")); } catch (e) { return []; }
}
function saveRecords(list) {
  try { fs.mkdirSync(DATA_DIR, { recursive: true }); fs.writeFileSync(RECORDS_FILE, JSON.stringify(list)); } catch (e) {}
}
function currentSession(req) { return verifyToken(parseCookies(req).bmd_session); }
function jsonRes(res, code, obj) { res.writeHead(code, { "Content-Type": "application/json" }); res.end(JSON.stringify(obj)); }

/* ---------- 加盟店ページの保護判定 ---------- */
function isProtected(urlPath) {
  const p = urlPath.split("?")[0];
  if (p.indexOf("/partner/") !== 0) return false;         // /partner/ 配下のみ
  const base = p.split("/").pop();
  if (base === "login.html") return false;                // ログイン画面は公開
  // 拡張子のないパス（=ディレクトリ）や .html を保護
  return base === "" || base.endsWith(".html");
}

/* ===================== サーバー本体 ===================== */
ensureSeed();
const server = http.createServer(async function (req, res) {
  const u = req.url;

  /* --- API --- */
  if (u === "/api/partner/login" && req.method === "POST") {
    const body = await readBody(req);
    const code = String(body.code || "").trim();
    const staff = String(body.staff || "").trim();
    const password = String(body.password || "");
    const acc = loadAccounts()[code];
    const ip = clientIp(req);
    if (!acc || acc.active === false || !verifyPassword(password, acc.pass)) {
      log("LOGIN_FAIL code=" + JSON.stringify(code) + " staff=" + JSON.stringify(staff) + " ip=" + ip);
      res.writeHead(401, { "Content-Type": "application/json" });
      return res.end(JSON.stringify({ ok: false, error: "加盟店コードまたはパスワードが違います。" }));
    }
    const role = acc.role || "partner";
    const exp = Date.now() + SESSION_MS;
    const token = signToken({ code: code, staff: staff, role: role, exp: exp });
    const partner = { store: acc.store, code: code, staff: staff, role: role };
    const sec = isSecure(req);
    setCookie(res, "bmd_session", token, { httpOnly: true, maxAge: Math.floor(SESSION_MS / 1000), secure: sec });
    setCookie(res, "bmd_partner", JSON.stringify(partner), { maxAge: Math.floor(SESSION_MS / 1000), secure: sec });
    log("LOGIN_OK code=" + JSON.stringify(code) + " store=" + JSON.stringify(acc.store) + " staff=" + JSON.stringify(staff) + " ip=" + ip);
    res.writeHead(200, { "Content-Type": "application/json" });
    return res.end(JSON.stringify({ ok: true, partner: partner }));
  }
  if (u === "/api/partner/logout" && req.method === "POST") {
    setCookie(res, "bmd_session", "", { httpOnly: true, maxAge: 0 });
    setCookie(res, "bmd_partner", "", { maxAge: 0 });
    res.writeHead(200, { "Content-Type": "application/json" });
    return res.end(JSON.stringify({ ok: true }));
  }
  if (u === "/api/partner/me") {
    const s = verifyToken(parseCookies(req).bmd_session);
    res.writeHead(s ? 200 : 401, { "Content-Type": "application/json" });
    return res.end(JSON.stringify(s ? { ok: true, code: s.code, staff: s.staff } : { ok: false }));
  }

  /* --- レコード保存/一覧（要ログイン） --- */
  if (u.split("?")[0] === "/api/records" && req.method === "POST") {
    const s = currentSession(req);
    if (!s) return jsonRes(res, 401, { ok: false, error: "unauthorized" });
    const body = await readBody(req);
    if (!body || !body.doc) return jsonRes(res, 400, { ok: false, error: "doc required" });
    const rec = {
      id: crypto.randomBytes(6).toString("hex"),
      doc: String(body.doc).slice(0, 60),
      label: String(body.label || "").slice(0, 160),
      data: (body.data && typeof body.data === "object") ? body.data : {},
      partner: { code: s.code, staff: s.staff },
      at: new Date().toISOString()
    };
    const list = loadRecords();
    list.unshift(rec);
    if (list.length > 5000) list.length = 5000;
    saveRecords(list);
    log("RECORD_SAVE id=" + rec.id + " doc=" + rec.doc + " by=" + s.code);
    return jsonRes(res, 200, { ok: true, id: rec.id });
  }
  if (u.split("?")[0] === "/api/records" && req.method === "GET") {
    const s = currentSession(req);
    if (!s) return jsonRes(res, 401, { ok: false, error: "unauthorized" });
    const q = new URLSearchParams(u.split("?")[1] || "");
    let list = loadRecords();
    const doc = q.get("doc"); if (doc) list = list.filter(function (r) { return r.doc === doc; });
    const limit = Math.min(parseInt(q.get("limit") || "300", 10) || 300, 1000);
    return jsonRes(res, 200, { ok: true, records: list.slice(0, limit) });
  }

  /* --- 本部（管理者）API：要 admin --- */
  if (u.split("?")[0] === "/api/admin/partners" && req.method === "GET") {
    const s = currentSession(req);
    if (!s || s.role !== "admin") return jsonRes(res, 403, { ok: false, error: "forbidden" });
    const accs = loadAccounts();
    const list = Object.keys(accs).map(function (code) {
      return { code: code, store: accs[code].store, active: accs[code].active !== false, role: accs[code].role || "partner" };
    });
    return jsonRes(res, 200, { ok: true, partners: list });
  }
  if (u.split("?")[0] === "/api/admin/partners" && req.method === "POST") {
    const s = currentSession(req);
    if (!s || s.role !== "admin") return jsonRes(res, 403, { ok: false, error: "forbidden" });
    const body = await readBody(req);
    const code = String(body.code || "").trim();
    if (!code) return jsonRes(res, 400, { ok: false, error: "code required" });
    const accs = loadAccounts();
    const acc = accs[code] || { active: true };
    if (body.store != null) acc.store = String(body.store).slice(0, 60);
    if (body.role != null) acc.role = (body.role === "admin") ? "admin" : "partner";
    if (body.active != null) acc.active = !!body.active;
    if (body.password) acc.pass = hashPassword(String(body.password));
    if (!acc.pass) return jsonRes(res, 400, { ok: false, error: "初回はパスワードが必要です" });
    accs[code] = acc;
    try { fs.writeFileSync(ACCOUNTS_FILE, JSON.stringify(accs, null, 2)); } catch (e) {}
    log("ADMIN_PARTNER_SAVE code=" + code + " by=" + s.code);
    return jsonRes(res, 200, { ok: true });
  }
  if (u.split("?")[0] === "/api/admin/records" && req.method === "GET") {
    const s = currentSession(req);
    if (!s || s.role !== "admin") return jsonRes(res, 403, { ok: false, error: "forbidden" });
    const q = new URLSearchParams(u.split("?")[1] || "");
    let list = loadRecords();
    const doc = q.get("doc"); if (doc) list = list.filter(function (r) { return r.doc === doc; });
    const code = q.get("code"); if (code) list = list.filter(function (r) { return r.partner && r.partner.code === code; });
    const kw = (q.get("q") || "").toLowerCase();
    if (kw) list = list.filter(function (r) {
      return (r.label || "").toLowerCase().indexOf(kw) >= 0 ||
        JSON.stringify(r.data || {}).toLowerCase().indexOf(kw) >= 0;
    });
    const limit = Math.min(parseInt(q.get("limit") || "500", 10) || 500, 2000);
    return jsonRes(res, 200, { ok: true, records: list.slice(0, limit) });
  }

  /* --- 本部ページ保護（/admin/*） --- */
  {
    const pth = u.split("?")[0];
    const base = pth.split("/").pop();
    if (pth.indexOf("/admin/") === 0 && base !== "login.html" && (base === "" || base.endsWith(".html"))) {
      const s = currentSession(req);
      if (!s || s.role !== "admin") { res.writeHead(302, { "Location": "/admin/login.html" }); return res.end(); }
    }
  }

  /* --- 加盟店ページ保護 --- */
  if (isProtected(u)) {
    const s = verifyToken(parseCookies(req).bmd_session);
    if (!s) {
      log("DENY " + u.split("?")[0] + " ip=" + clientIp(req));
      res.writeHead(302, { "Location": "/partner/login.html" });
      return res.end();
    }
  }

  /* --- 静的配信 --- */
  serveStatic(req, res, u);
});

server.listen(PORT, function () {
  console.log("バイモダイレクト サーバー起動: http://localhost:" + PORT);
  console.log("  一般サイト : http://localhost:" + PORT + "/index.html");
  console.log("  加盟店ログイン: http://localhost:" + PORT + "/partner/login.html");
  if (SECRET === "CHANGE-ME-IN-PRODUCTION") console.log("  ⚠ 本番では環境変数 BMD_SECRET を必ず設定してください。");
});
