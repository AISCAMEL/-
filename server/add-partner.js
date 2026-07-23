/* 加盟店アカウントの追加・更新（パスワードは scrypt でハッシュ化して保存）
 * 使い方： node server/add-partner.js <加盟店コード> <店名> <パスワード>
 * 例    ： node server/add-partner.js BMD-002 郡山店 s3cret-pass
 */
"use strict";
const fs = require("fs");
const path = require("path");
const crypto = require("crypto");
const FILE = path.join(__dirname, "partners.json");

function hashPassword(pw) {
  const salt = crypto.randomBytes(16).toString("hex");
  const h = crypto.scryptSync(String(pw), salt, 64).toString("hex");
  return salt + ":" + h;
}

const [code, store, pass] = process.argv.slice(2);
if (!code || !store || !pass) {
  console.error("使い方: node server/add-partner.js <加盟店コード> <店名> <パスワード>");
  process.exit(1);
}
let data = {};
try { data = JSON.parse(fs.readFileSync(FILE, "utf8")); } catch (e) {}
data[code] = { store: store, pass: hashPassword(pass), active: true };
fs.writeFileSync(FILE, JSON.stringify(data, null, 2));
console.log("保存しました: " + code + "（" + store + "）");
console.log("※ パスワードはハッシュ化して保存しています（平文は保存されません）。");
