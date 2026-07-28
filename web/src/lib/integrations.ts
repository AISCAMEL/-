// ============================================================
// 外部サービス連携（BASE / Square / 楽天 / Yahoo! / Amazon）
// いずれも各サービスの加盟店アカウントとAPIキーが必要。
// キーが未設定のときは「未接続」として扱い、実際の出品/決済は行わない。
// ============================================================

export type ChannelKey = "base" | "rakuten" | "yahoo" | "amazon";

/** 各販売チャネルの接続状況（環境変数の有無で判定） */
export function channelStatus() {
  return {
    base: Boolean(process.env.BASE_API_ACCESS_TOKEN),
    rakuten: Boolean(process.env.RAKUTEN_API_KEY),
    yahoo: Boolean(process.env.YAHOO_API_KEY),
    amazon: Boolean(process.env.AMAZON_SP_API_TOKEN),
  } as Record<ChannelKey, boolean>;
}

export function isSquareConfigured() {
  return Boolean(process.env.SQUARE_ACCESS_TOKEN);
}

export const CHANNEL_META: Record<ChannelKey, { label: string; role: string }> = {
  base: { label: "BASE", role: "オンラインショップ本体（在庫・注文・配送）" },
  rakuten: { label: "楽天市場", role: "販売チャネル（多店舗展開）" },
  yahoo: { label: "Yahoo!ショッピング", role: "販売チャネル（多店舗展開）" },
  amazon: { label: "Amazon", role: "販売チャネル（多店舗展開）" },
};

// ---- BASE API（商品出品）-------------------------------------
// BASE Developers: POST https://api.thebase.in/1/items/add
export type BasePublishInput = {
  name: string;
  description?: string | null;
  price: number;
  imageUrl?: string | null;
};

export async function publishItemToBase(
  p: BasePublishInput,
): Promise<{ ok: boolean; itemId?: string; message: string }> {
  const token = process.env.BASE_API_ACCESS_TOKEN;
  if (!token) {
    return {
      ok: false,
      message: "BASE未接続：BASE_API_ACCESS_TOKEN を設定すると自動掲載できます。",
    };
  }
  try {
    const body = new URLSearchParams({
      title: p.name,
      detail: p.description ?? "",
      price: String(p.price),
      stock: "1",
      visible: "1",
    });
    const res = await fetch("https://api.thebase.in/1/items/add", {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body,
    });
    if (!res.ok) {
      return { ok: false, message: `BASE APIエラー（${res.status}）` };
    }
    const json = await res.json();
    const itemId = String(json?.item?.item_id ?? "");
    return { ok: true, itemId, message: "BASEに掲載しました。" };
  } catch {
    return { ok: false, message: "BASEへの通信に失敗しました。" };
  }
}
