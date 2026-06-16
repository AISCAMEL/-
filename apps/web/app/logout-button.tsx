"use client";

export default function LogoutButton() {
  async function logout() {
    await fetch("/api/login", { method: "DELETE" });
    window.location.href = "/login";
  }
  return (
    <button
      onClick={logout}
      style={{ padding: "4px 12px", fontSize: 13, border: "1px solid #d1d5db", borderRadius: 6, background: "#fff", cursor: "pointer", color: "#444" }}
    >
      ログアウト
    </button>
  );
}
