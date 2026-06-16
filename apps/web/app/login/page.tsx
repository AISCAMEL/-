"use client";

import { useState } from "react";

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
    <div style={{ maxWidth: 360, margin: "80px auto", textAlign: "center" }}>
      <h1 style={{ fontSize: 20 }}>🛒 Dropshipping Hub</h1>
      <p style={{ color: "#777", fontSize: 14 }}>管理画面にログイン</p>
      <form onSubmit={submit} style={{ display: "grid", gap: 12, marginTop: 24 }}>
        <input
          type="password"
          placeholder="パスワード"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          autoFocus
          style={{ padding: 10, fontSize: 16, border: "1px solid #d1d5db", borderRadius: 8 }}
        />
        <button
          type="submit"
          disabled={loading}
          style={{ padding: 10, background: "#2563eb", color: "#fff", border: 0, borderRadius: 8, cursor: "pointer", fontSize: 16 }}
        >
          {loading ? "確認中…" : "ログイン"}
        </button>
        {error && <p style={{ color: "#dc2626", margin: 0 }}>{error}</p>}
      </form>
    </div>
  );
}
