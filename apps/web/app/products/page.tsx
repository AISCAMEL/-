"use client";

import { useEffect, useState, useCallback } from "react";

interface SourceProduct {
  externalId: string;
  title: string;
  cost: string;
  costCurrency: string;
  stock: number | null;
  imageUrls: string[];
  supplier: { kind: string; name: string };
}

interface Listing {
  id: string;
  status: string;
  publishedPrice: string | null;
  publishedStock: number | null;
  externalListingId: string | null;
  channel: { kind: string; name: string };
}

interface Product {
  id: string;
  title: string;
  sellPrice: string;
  createdAt: string;
  sourceProduct: SourceProduct;
  listings: Listing[];
}

const STATUS_LABEL: Record<string, { text: string; bg: string; color: string }> = {
  published: { text: "出品中", bg: "#dcfce7", color: "#166534" },
  draft: { text: "下書き", bg: "#f3f4f6", color: "#6b7280" },
  unpublished: { text: "非公開", bg: "#fef9c3", color: "#854d0e" },
  error: { text: "エラー", bg: "#fee2e2", color: "#dc2626" },
};

const yen = (v: string | number | null | undefined) => {
  if (v == null) return "-";
  const n = typeof v === "string" ? parseFloat(v) : v;
  return `¥${n.toLocaleString()}`;
};

export default function ProductsPage() {
  const [products, setProducts] = useState<Product[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [importForm, setImportForm] = useState({ supplierId: "theckb", externalId: "" });
  const [importing, setImporting] = useState(false);
  const [importMsg, setImportMsg] = useState<string | null>(null);
  const [pubState, setPubState] = useState<Record<string, { status: string; msg?: string }>>({});

  const load = useCallback(async () => {
    try {
      const res = await fetch("/api/products");
      const data = await res.json();
      if (data.items) {
        setProducts(data.items);
        setTotal(data.total);
      }
    } catch (e) {
      setError(String(e));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(); }, [load]);

  async function handleImport(e: React.FormEvent) {
    e.preventDefault();
    if (!importForm.externalId) return;
    setImporting(true);
    setImportMsg(null);
    try {
      const res = await fetch("/api/products", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify(importForm),
      });
      const data = await res.json();
      if (!res.ok) {
        setImportMsg(`取込失敗: ${data.error ? JSON.stringify(data.error) : "不明"}`);
        return;
      }
      setImportMsg(`取込完了: ${data.product?.title ?? ""}  売値 ${yen(data.sellPrice)}`);
      setImportForm((f) => ({ ...f, externalId: "" }));
      await load();
    } catch (e) {
      setImportMsg(`エラー: ${String(e)}`);
    } finally {
      setImporting(false);
    }
  }

  async function handlePublish(product: Product) {
    const key = product.id;
    setPubState((s) => ({ ...s, [key]: { status: "publishing" } }));
    try {
      const res = await fetch("/api/publish", {
        method: "POST",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({
          supplierId: product.sourceProduct.supplier.kind,
          externalId: product.sourceProduct.externalId,
          channelId: "base",
        }),
      });
      const data = await res.json();
      if (!res.ok) {
        const reason = data.issues
          ? data.issues.map((i: { code: string }) => i.code).join(", ")
          : data.error ? JSON.stringify(data.error) : "出品不可";
        setPubState((s) => ({ ...s, [key]: { status: "error", msg: reason } }));
        return;
      }
      setPubState((s) => ({ ...s, [key]: { status: "done", msg: data.listing?.externalListingId ?? "出品済み" } }));
      await load();
    } catch (e) {
      setPubState((s) => ({ ...s, [key]: { status: "error", msg: String(e) } }));
    }
  }

  async function handleDelete(product: Product) {
    if (!confirm(`「${product.title}」を削除しますか？`)) return;
    try {
      await fetch(`/api/products/${product.id}`, { method: "DELETE" });
      await load();
    } catch (e) {
      setError(String(e));
    }
  }

  function renderPublishButton(product: Product) {
    const existingListing = product.listings.find((l) => l.channel.kind === "base");
    if (existingListing?.status === "published") {
      return <span style={{ color: "#16a34a", fontSize: 13, fontWeight: 600 }}>出品中</span>;
    }

    const st = pubState[product.id];
    if (st?.status === "done") {
      return <span style={{ color: "#16a34a", fontSize: 13, fontWeight: 600 }}>出品済</span>;
    }
    if (st?.status === "error") {
      return (
        <span title={st.msg}>
          <span style={{ color: "#dc2626", fontSize: 13 }}>不可</span>{" "}
          <button onClick={() => handlePublish(product)} style={{ fontSize: 12, cursor: "pointer", borderRadius: 8, border: "1px solid #fecaca", background: "#fff", padding: "2px 8px" }}>再試行</button>
        </span>
      );
    }
    const publishing = st?.status === "publishing";
    return (
      <button
        onClick={() => handlePublish(product)}
        disabled={publishing}
        style={{
          padding: "5px 14px", borderRadius: 20, cursor: "pointer", fontSize: 13, fontWeight: 600,
          border: 0, color: "#fff",
          background: "linear-gradient(135deg, #60a5fa, #3b82f6)",
          boxShadow: "0 2px 8px rgba(59,130,246,0.3)",
        }}
      >
        {publishing ? "出品中…" : "BASE出品"}
      </button>
    );
  }

  return (
    <div>
      <p style={{ marginBottom: 16 }}>
        <a href="/">← ダッシュボード</a>
      </p>
      <h1 style={{ fontSize: 22 }}>📦 商品・出品管理</h1>
      <p style={{ color: "var(--muted)", fontSize: 14 }}>
        仕入れ先から取り込んだ商品の管理と、BASEへの出品操作ができます。
      </p>

      <div style={{
        background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
        padding: 16, marginBottom: 20,
      }}>
        <h3 style={{ margin: "0 0 12px", fontSize: 15 }}>商品取込</h3>
        <form onSubmit={handleImport} style={{ display: "flex", gap: 8, flexWrap: "wrap", alignItems: "end" }}>
          <label style={{ fontSize: 13, fontWeight: 600 }}>
            仕入れ先
            <br />
            <select
              value={importForm.supplierId}
              onChange={(e) => setImportForm((f) => ({ ...f, supplierId: e.target.value }))}
              style={{ marginTop: 4, padding: "6px 10px" }}
            >
              <option value="theckb">THE CKB</option>
              <option value="alibaba">Alibaba</option>
            </select>
          </label>
          <label style={{ fontSize: 13, fontWeight: 600 }}>
            商品ID
            <br />
            <input
              type="text"
              value={importForm.externalId}
              onChange={(e) => setImportForm((f) => ({ ...f, externalId: e.target.value }))}
              placeholder="CKB-0001"
              style={{ marginTop: 4, padding: "6px 10px", width: 140 }}
            />
          </label>
          <button
            type="submit"
            disabled={importing || !importForm.externalId}
            style={{
              padding: "8px 20px", borderRadius: 20, cursor: "pointer", fontSize: 13, fontWeight: 700,
              border: 0, color: "#fff",
              background: "linear-gradient(135deg, #f472b6, #a78bfa)",
              boxShadow: "0 2px 12px rgba(244,114,182,0.3)",
            }}
          >
            {importing ? "取込中…" : "取込"}
          </button>
        </form>
        {importMsg && (
          <p style={{ marginTop: 8, fontSize: 13, color: importMsg.startsWith("取込完了") ? "#16a34a" : "#dc2626" }}>
            {importMsg}
          </p>
        )}
      </div>

      {error && <p style={{ color: "#dc2626" }}>エラー: {error}</p>}

      {loading ? (
        <p style={{ color: "var(--muted)" }}>読み込み中…</p>
      ) : products.length === 0 ? (
        <div style={{
          textAlign: "center", padding: 40, color: "var(--muted)",
          background: "#fff", border: "2px solid var(--card-border)", borderRadius: "var(--radius)",
        }}>
          <p style={{ fontSize: 36, margin: "0 0 8px" }}>📦</p>
          <p style={{ fontSize: 14 }}>まだ商品がありません。上のフォームから取り込んでください。</p>
        </div>
      ) : (
        <>
          <p style={{ fontSize: 13, color: "var(--muted)", marginBottom: 8 }}>
            全 {total} 件
          </p>
          <div style={{ overflowX: "auto", background: "#fff", borderRadius: "var(--radius)", border: "2px solid var(--card-border)", padding: 4 }}>
            <table>
              <thead>
                <tr>
                  {["#", "画像", "商品名", "仕入れ先", "原価(CNY)", "在庫", "売値(JPY)", "出品状態", "操作"].map((h) => (
                    <th key={h}>{h}</th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {products.map((p, i) => {
                  const listing = p.listings[0];
                  const statusInfo = listing ? STATUS_LABEL[listing.status] ?? STATUS_LABEL.draft : null;
                  return (
                    <tr key={p.id}>
                      <td>{i + 1}</td>
                      <td>
                        {p.sourceProduct.imageUrls?.[0] ? (
                          <img
                            src={p.sourceProduct.imageUrls[0]}
                            alt={p.title}
                            style={{ width: 48, height: 48, objectFit: "cover", borderRadius: 6, border: "1px solid #e5e7eb" }}
                          />
                        ) : (
                          <span style={{ display: "inline-block", width: 48, height: 48, borderRadius: 6, background: "#f3f4f6", textAlign: "center", lineHeight: "48px", fontSize: 20 }}>📦</span>
                        )}
                      </td>
                      <td style={{ fontWeight: 500, maxWidth: 240 }}>{p.title}</td>
                      <td>
                        <span style={{
                          padding: "2px 8px", borderRadius: 12, fontSize: 12, fontWeight: 600,
                          background: "#f3f4f6", color: "#374151",
                        }}>
                          {p.sourceProduct.supplier.name}
                        </span>
                      </td>
                      <td>{parseFloat(p.sourceProduct.cost).toFixed(0)} {p.sourceProduct.costCurrency}</td>
                      <td>{p.sourceProduct.stock ?? "-"}</td>
                      <td style={{ fontWeight: 700 }}>{yen(p.sellPrice)}</td>
                      <td>
                        {statusInfo ? (
                          <span style={{
                            padding: "3px 10px", borderRadius: 20, fontSize: 12, fontWeight: 600,
                            background: statusInfo.bg, color: statusInfo.color,
                          }}>
                            {statusInfo.text}
                          </span>
                        ) : (
                          <span style={{ color: "#9ca3af", fontSize: 12 }}>未出品</span>
                        )}
                      </td>
                      <td>
                        <div style={{ display: "flex", gap: 6, alignItems: "center" }}>
                          {renderPublishButton(p)}
                          <button
                            onClick={() => handleDelete(p)}
                            style={{
                              padding: "4px 10px", borderRadius: 8, cursor: "pointer",
                              fontSize: 12, border: "1px solid #fecaca", background: "#fff", color: "#dc2626",
                            }}
                          >
                            削除
                          </button>
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}
