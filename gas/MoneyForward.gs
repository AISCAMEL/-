/**
 * MoneyForward.gs — マネーフォワード クラウド契約 連携アダプタ（雛形）
 * =============================================================================
 * 目的：カーメル書面作成システムで作成した「契約系書面」を、GAS経由で
 *       マネーフォワード クラウド契約（電子契約）へ連携するための"骨組み"。
 *
 * 【重要・正直な前提】───────────────────────────────────────────────
 *  ・本ファイルは「仕組み（枠組み）」です。実際に動かすには次が必要：
 *      (1) マネーフォワード クラウド契約の法人プラン契約
 *      (2) MF開発者コンソールでのアプリ登録／API利用申請（OAuth2 or APIキー）
 *      (3) Config.gs の MF_* に、上記で得た値と“正式なエンドポイント”を設定
 *  ・MF公式のエンドポイントURL・リクエスト形式は製品仕様に依存するため、
 *    本ファイルでは推測で確定させず【★要設定★】のプレースホルダにしています。
 *    公式仕様： https://developers.biz.moneyforward.com/docs/
 *  ・ブラウザから直接叩かず、必ずこのGAS（サーバー側）から呼び出します
 *    （APIシークレット秘匿・CORS回避）。
 *
 * 【組み込み（後から）】
 *  1) Config.gs の MF_ENABLED を "1" にし、MF_* を設定。
 *  2) mfPing() を実行してトークン取得が通るか確認。
 *  3) handleCmlDoc_（CarmelDocs.gs）が、契約系書面の受信時に自動で
 *     mfForwardIfEligible_() を呼び、結果をシート「MF連携/MF契約ID」列へ記録。
 * =============================================================================
 */

/** MF設定を取得（未設定判定つき） */
function mfConfig_() {
  var c = getConfig();
  return {
    enabled: String(c.MF_ENABLED || "") === "1",
    authType: c.MF_AUTH_TYPE || "oauth2",
    tokenUrl: c.MF_TOKEN_URL || "",
    clientId: c.MF_CLIENT_ID || "",
    clientSecret: c.MF_CLIENT_SECRET || "",
    scope: c.MF_SCOPE || "",
    refreshToken: c.MF_REFRESH_TOKEN || "",
    apiKey: c.MF_API_KEY || "",
    apiBase: (c.MF_API_BASE || "").replace(/\/+$/, ""),
    epCreate: c.MF_EP_CREATE_CONTRACT || "/contracts",
    epGet: c.MF_EP_GET_CONTRACT || "/contracts/{id}",
    contractDoctypes: String(c.MF_CONTRACT_DOCTYPES || "d1,d4,d6")
      .split(",").map(function (s) { return s.trim(); }).filter(String)
  };
}

/** この書面種別を電子契約に回すか */
function mfIsContractDoc_(doctype) {
  return mfConfig_().contractDoctypes.indexOf(doctype) >= 0;
}

/* ---------------------------------------------------------------------------
 * 認証：アクセストークンの取得（OAuth2 client_credentials / refresh_token）
 * トークンは PropertiesService に有効期限つきでキャッシュ。
 * ------------------------------------------------------------------------- */
function mfGetToken_() {
  var mf = mfConfig_();

  // APIキー方式なら、そのままキーを返す（Bearerとして使う想定）
  if (mf.authType === "apikey") {
    if (!mf.apiKey) throw new Error("MF_API_KEY 未設定");
    return mf.apiKey;
  }

  // OAuth2：キャッシュ確認
  var props = PropertiesService.getScriptProperties();
  var cached = props.getProperty("MF_TOKEN");
  var exp = Number(props.getProperty("MF_TOKEN_EXP") || 0);
  if (cached && Date.now() < exp - 60000) return cached; // 期限1分前まで再利用

  if (!mf.tokenUrl || !mf.clientId || !mf.clientSecret) {
    throw new Error("MF OAuth2 設定不足（MF_TOKEN_URL / MF_CLIENT_ID / MF_CLIENT_SECRET）");
  }

  // grant_type：refresh_token があれば更新、無ければ client_credentials
  var payload = mf.refreshToken
    ? { grant_type: "refresh_token", refresh_token: mf.refreshToken,
        client_id: mf.clientId, client_secret: mf.clientSecret }
    : { grant_type: "client_credentials",
        client_id: mf.clientId, client_secret: mf.clientSecret };
  if (mf.scope) payload.scope = mf.scope;

  var res = UrlFetchApp.fetch(mf.tokenUrl, {
    method: "post",
    contentType: "application/x-www-form-urlencoded",
    payload: payload,               // GASがフォームエンコードします
    muteHttpExceptions: true
  });
  var code = res.getResponseCode();
  var body = res.getContentText();
  if (code < 200 || code >= 300) {
    throw new Error("MF トークン取得失敗 (" + code + "): " + body);
  }
  var json = JSON.parse(body);
  var token = json.access_token;
  var ttl = Number(json.expires_in || 3600) * 1000;
  props.setProperty("MF_TOKEN", token);
  props.setProperty("MF_TOKEN_EXP", String(Date.now() + ttl));
  return token;
}

/* ---------------------------------------------------------------------------
 * 書面データ → MF契約 リクエストへの変換（★要マッピング調整★）
 * MFの正式なリクエストスキーマに合わせて、ここを実装します。
 * 現状は「タイトル・宛先・明細」を汎用JSONで組む雛形。
 * ------------------------------------------------------------------------- */
function mfBuildContractRequest_(d) {
  var f = d.fields || {};
  return {
    // ▼ 下記キー名は仮。MF公式スキーマに合わせて置換してください。
    title: (d.doctypeName || "契約書") + " " + (d.docNo || ""),
    counterparty_name: d.customer || "",
    // 相手方メール（署名依頼の送信先）— 書面の *_email を利用
    counterparty_email: f[d.doctype + "_email"] || f["d" + "_email"] || "",
    document_date: d.date || "",
    // 明細は control 側で保持（MFの任意項目/メタデータに載せる想定）
    metadata: {
      source: "carmel-documents",
      doctype: d.doctype,
      doc_no: d.docNo || "",
      staff: d.staff || ""
    },
    // 本文フィールド一式（MF側テンプレート差し込み用途などに）
    fields: f
  };
}

/** 契約作成APIを呼ぶ（★エンドポイント要設定★） */
function mfCreateContract_(d) {
  var mf = mfConfig_();
  if (!mf.apiBase) throw new Error("MF_API_BASE 未設定");

  var url = mf.apiBase + mf.epCreate;
  var token = mfGetToken_();
  var res = UrlFetchApp.fetch(url, {
    method: "post",
    contentType: "application/json",
    headers: { Authorization: "Bearer " + token },
    payload: JSON.stringify(mfBuildContractRequest_(d)),
    muteHttpExceptions: true
  });
  var code = res.getResponseCode();
  var body = res.getContentText();
  if (code < 200 || code >= 300) {
    return { ok: false, error: "MF契約作成失敗 (" + code + "): " + body };
  }
  var json = {};
  try { json = JSON.parse(body); } catch (e) {}
  // ▼ ID取り出しキーは仮。MFレスポンスに合わせて調整。
  var id = json.id || json.contract_id || (json.data && json.data.id) || "";
  return { ok: true, contractId: id, raw: json };
}

/**
 * 受信書面が対象なら電子契約へ連携（handleCmlDoc_ から呼ばれる）。
 * @return {Object} { skipped } | { ok, contractId } | { ok:false, error }
 */
function mfForwardIfEligible_(d) {
  var mf = mfConfig_();
  if (!mf.enabled) return { skipped: true, reason: "disabled" };
  if (!mfIsContractDoc_(d.doctype)) return { skipped: true, reason: "not-contract-doctype" };
  try {
    var r = mfCreateContract_(d);
    if (r.ok) {
      notifyStaff_("📝 MF電子契約を作成しました：" + (r.contractId || "(ID不明)") +
        "\n書面：" + (d.doctypeName || d.doctype) + " / " + (d.customer || "-") + " 様");
    }
    return r;
  } catch (err) {
    return { ok: false, error: String(err) };
  }
}

/* ---------------------------------------------------------------------------
 * 動作確認用
 * ------------------------------------------------------------------------- */
/** トークン取得だけ試す */
function mfPing() {
  try {
    var t = mfGetToken_();
    Logger.log("✅ MFトークン取得OK（先頭12文字）: " + String(t).slice(0, 12) + "…");
  } catch (e) {
    Logger.log("❌ " + e);
  }
}

/** サンプル書面でMF契約作成を試す（設定完了後に実行） */
function mfTestCreate() {
  var r = mfForwardIfEligible_({
    type: "cmldoc", doctype: "d6", doctypeName: "自動車売買契約書",
    docNo: "ORD-2026-TEST", customer: "テスト 太郎", staff: "動作確認", date: "2026-08-13",
    fields: { d6_email: "test@example.com", d6_car: "アルファード", d6_payable: "2570000" }
  });
  Logger.log("mfTestCreate → " + JSON.stringify(r));
}
