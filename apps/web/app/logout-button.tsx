"use client";

export default function LogoutButton() {
  async function logout() {
    await fetch("/api/login", { method: "DELETE" });
    window.location.href = "/login";
  }
  return (
    <button
      onClick={logout}
      style={{
        padding: "6px 14px", fontSize: 13,
        border: "2px solid var(--card-border)",
        borderRadius: 20, background: "#fff",
        cursor: "pointer", color: "var(--muted)",
        fontWeight: 500,
        transition: "all 0.2s",
      }}
      onMouseEnter={(e) => { e.currentTarget.style.borderColor = "#f472b6"; e.currentTarget.style.color = "#f472b6"; }}
      onMouseLeave={(e) => { e.currentTarget.style.borderColor = "var(--card-border)"; e.currentTarget.style.color = "var(--muted)"; }}
    >
      ログアウト
    </button>
  );
}
