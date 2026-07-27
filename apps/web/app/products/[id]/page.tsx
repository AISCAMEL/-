"use client";

import { useEffect, useState, use } from "react";

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

const yen = (v: string | number | null | undefined) => {
  if (v == null) return "-";
  const n = typeof v === "string" ? parseFloat(v) : v;
  return `¥${n.toLocaleString()}`;
};

const STATUS_LABEL: Record<string, { text: string; bg: string; color: string }> = {
  published: { text: "出品中", bg: "#dcfce7", color: "#166534" },
  draft: { text: "下書き", bg: "#f3f4f6", color: "#6b7280" },
  unpublished: { text: "非公開", bg: "#fef9c3", color: "#854d0e" },
  error: { text: "エラー", bg: "#fee2e2", color: "#dc2626" },
};

export default function ProductDetailPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [product, setProduct] = useState<Product | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [editPrice, setEditPrice] = useState("");
  const [saving, setSaving] = useState(false);
  const [saveMsg, setSaveMsg] = useState<string | null>(null);

  useEffect(() => {
    fetch(`/api/products/${id}`)
      .then((r) => r.json())
      .then((data) => {
        if (data.error) {
          setError(data.error);
        } else {
          setProduct(data);
          setEditPrice(data.sellPrice);
        }
      })
      .catch((e) => setError(String(e)))
      .finally(() => setLoading(false));
  }, [id]);

  async function handleSavePrice() {
    if (!product) return;
    setSaving(true);
    setSaveMsg(null);
    try {
      const res = await fetch(`/api/products/${id}`, {
        method: "PATCH",
        headers: { "content-type": "application/json" },
        body: JSON.stringify({ sellPrice: editPrice }),
      });
      const data = await res.json();
      if (!res.ok) {
        setSaveMsg(`保存失敗: ${data.error ?? "不明"}`);
        return;
      }
      setProduct((p) => p ? { ...p, sellPrice: editPrice } : p);
      setSaveMsg("保存しました");
    } catch (e) {
      setSaveMsg(`エラー: ${String(e)}`);
    } finally {
      setSaving(false);
    }
  }

  if (loading) return <div style={{ padding: 40, color: "var(--muted)" }}>読み込み中...</div>;
  if (error) return (
    <div style={{ padding: 40 }}>
      <a href="/products">← 商品一覧</a>
      <p style={{ color: "#dc2626", marginTop: 16 }}>エラー: {error}</p>
    </div>
  );
  if (!product) return null;

  const src = product.sourceProduct;
  const cost = parseFloat(src.cost);

  return (
    <div>
      <p style={{ marginBottom: 16 }}>
        <a href="/products">← 商品一覧</a>
      </p>

      <div style={{
        display: "grid", gridTemplateColumns: "auto 1fr", gap: 24,
        background: "#fff", border: "2px solid var(--card-border)",
        borderRadius: "var(--radius)", padding: 24, marginBottom: 20,
      }}>
        <div>
          {src.imageUrls?.[0] ? (
            <img src={src.imageUrls[0]} alt={product.title}
              style={{ width: 200, height: 200, objectFit: "cover", borderRadius: 12, border: "1px solid #e5e7eb" }} />
          ) : (
            <div style={{ width: 200, height: 200, borderRadius: 12, background: "#f3f4f6", display: "grid", placeItems: "center", fontSize: 48 }}>
              📦
            </div>
          )}
          {src.imageUrls.length > 1 && (
            <div style={{ display: "flex", gap: 6, marginTop: 8, flexWrap: "wrap" }}>
              {src.imageUrls.slice(1, 5).map((url, i) => (
                <img key={i} src={url} alt="" style={{ width: 44, height: 44, objectFit: "cover", borderRadius: 6, border: "1px solid #e5e7eb" }} />
              ))}
            </div>
          )}
        </div>

        <div>
          <h1 style={{ fontSize: 20, margin: "0 0 12px" }}>{product.title}</h1>

          <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "8px 24px", fontSize: 14, marginBottom: 16 }}>
            <div><span style={{ color: "var(--muted)" }}>仕入れ先:</span> {src.supplier.name}</div>
            <div><span style={{ color: "var(--muted)" }}>商品ID:</span> {src.externalId}</div>
            <div><span style={{ color: "var(--muted)" }}>原価:</span> {cost.toFixed(0)} {src.costCurrency}</div>
            <div><span style={{ color: "var(--muted)" }}>在庫:</span> {src.stock ?? "-"}</div>
            <div><span style={{ color: "var(--muted)" }}>登録日:</span> {product.createdAt.slice(0, 10)}</div>
          </div>

          <div style={{
            padding: 16, background: "#fef6f0", borderRadius: "var(--radius-sm)",
            marginBottom: 16,
          }}>
            <label style={{ fontSize: 13, fontWeight: 600, display: "block", marginBottom: 6 }}>
              売値 (JPY)
            </label>
            <div style={{ display: "flex", gap: 8, alignItems: "center" }}>
              <input
                type="number"
                value={editPrice}
                onChange={(e) => setEditPrice(e.target.value)}
                style={{ width: 140, padding: "8px 12px" }}
              />
              <button
                onClick={handleSavePrice}
                disabled={saving || editPrice === product.sellPrice}
                style={{
                  padding: "8px 20px", borderRadius: 20, cursor: "pointer", fontSize: 13, fontWeight: 700,
                  border: 0, color: "#fff",
                  background: editPrice !== product.sellPrice ? "linear-gradient(135deg, #f472b6, #a78bfa)" : "#d1d5db",
                }}
              >
                {saving ? "保存中..." : "保存"}
              </button>
              {saveMsg && (
                <span style={{ fontSize: 12, color: saveMsg.startsWith("保存しました") ? "#16a34a" : "#dc2626" }}>
                  {saveMsg}
                </span>
              )}
            </div>
            <div style={{ fontSize: 12, color: "var(--muted)", marginTop: 6 }}>
              現在の売値: {yen(product.sellPrice)} / 原価: {cost.toFixed(0)} {src.costCurrency}
            </div>
          </div>

          {product.listings.length > 0 && (
            <div>
              <h3 style={{ fontSize: 14, margin: "0 0 8px" }}>出品状態</h3>
              {product.listings.map((l) => {
                const st = STATUS_LABEL[l.status] ?? STATUS_LABEL.draft;
                return (
                  <div key={l.id} style={{
                    display: "flex", gap: 12, alignItems: "center", fontSize: 13,
                    padding: "8px 12px", background: "#f9fafb", borderRadius: 8, marginBottom: 4,
                  }}>
                    <span style={{
                      padding: "2px 10px", borderRadius: 12, fontSize: 12, fontWeight: 600,
                      background: st.bg, color: st.color,
                    }}>
                      {st.text}
                    </span>
                    <span>{l.channel.name}</span>
                    {l.publishedPrice && <span style={{ color: "var(--muted)" }}>出品価格: {yen(l.publishedPrice)}</span>}
                    {l.externalListingId && <span style={{ color: "var(--muted)" }}>ID: {l.externalListingId}</span>}
                  </div>
                );
              })}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
