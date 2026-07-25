"use client";

import { useState } from "react";
import Logo from "../logo";

export default function LoginPage() {
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setLoading(true);
    setError(null);
    try {
      const res = await fetch("/api/login", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ password }),
      });
      if (!res.ok) {
        const d = await res.json().catch(() => ({}));
        setError(d.error ?? "ログインに失敗しました");
        return;
      }
      window.location.href = "/";
    } catch {
      setError("通信エラー");
    } finally {
      setLoading(false);
    }
  }

  return (
    <div style={{ maxWidth: 360, margin: "60px auto", textAlign: "center" }}>
      <div style={{
        background: "#fff",
        borderRadius: 24,
        padding: "40px 32px 32px",
        boxShadow: "0 4px 24px rgba(244,114,182,0.12)",
        border: "2px solid var(--card-border)",
      }}>
        <div style={{ display: "grid", placeItems: "center", gap: 8, marginBottom: 8 }}>
          <span style={{
            width: 72, height: 72, borderRadius: 20, display: "grid", placeItems: "center",
            background: "linear-gradient(135deg, #fce7f3, #ede9fe)",
          }}>
            <Logo size={54} />
          </span>
          <h1 style={{ fontSize: 22, margin: "8px 0 0", fontWeight: 700 }}>necorope</h1>
        </div>
        <p style={{ color: "var(--muted)", fontSize: 14, margin: "4px 0 24px" }}>
          🐾 猫グッズ管理画面にログイン
        </p>
        <form onSubmit={submit} style={{ display: "grid", gap: 14 }}>
          <input
            type="password"
            placeholder="パスワード"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            autoFocus
            style={{ padding: 12, fontSize: 16, border: "2px solid var(--card-border)", borderRadius: 12 }}
          />
          <button
            type="submit"
            disabled={loading}
            style={{
              padding: 12,
              background: "linear-gradient(135deg, #f472b6, #a78bfa)",
              color: "#fff", border: 0, borderRadius: 12,
              cursor: "pointer", fontSize: 16, fontWeight: 700,
              boxShadow: "0 2px 12px rgba(244,114,182,0.3)",
            }}
          >
            {loading ? "確認中…" : "🐱 ログイン"}
          </button>
          {error && <p style={{ color: "#dc2626", margin: 0, fontSize: 14 }}>😿 {error}</p>}
        </form>
      </div>
    </div>
  );
}
