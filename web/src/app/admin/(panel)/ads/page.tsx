/* eslint-disable @next/next/no-img-element */
import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { toggleAd } from "@/app/admin/actions";

export const metadata: Metadata = { title: "広告枠｜運営管理" };

type Row = {
  id: string;
  advertiser: string;
  image_url: string;
  href: string;
  placement: string;
  is_active: boolean;
  impressions: number;
  clicks: number;
};

const PLACE_LABEL: Record<string, string> = {
  feed: "コミュニティ",
  waves: "波情報",
  blog: "ブログ",
  all: "全体",
};

export default async function AdminAds() {
  const supabase = await createClient();
  const { data } = await supabase
    .from("ad_banners")
    .select("id, advertiser, image_url, href, placement, is_active, impressions, clicks")
    .order("created_at", { ascending: false })
    .limit(100);
  const ads = (data ?? []) as unknown as Row[];

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">広告枠</h1>
      <p className="mt-1 text-sm text-slate-500">
        業者バナーの掲載を管理します。{ads.length} 件
      </p>

      <div className="mt-6 space-y-3">
        {ads.length === 0 ? (
          <p className="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-400">
            まだ広告がありません。バナー画像とリンクを登録すると掲載できます。
          </p>
        ) : (
          ads.map((a) => (
            <div key={a.id} className="rounded-lg border border-slate-200 bg-white p-4">
              <div className="flex items-center gap-4">
                <img
                  src={a.image_url}
                  alt={a.advertiser}
                  className="h-14 w-40 shrink-0 rounded object-cover"
                />
                <div className="min-w-0 flex-1">
                  <p className="font-medium text-slate-800">{a.advertiser}</p>
                  <p className="text-xs text-slate-400">
                    掲載：{PLACE_LABEL[a.placement] ?? a.placement}・表示{a.impressions}・クリック{a.clicks}
                  </p>
                </div>
                <form action={toggleAd}>
                  <input type="hidden" name="id" value={a.id} />
                  <input type="hidden" name="active" value={String(!a.is_active)} />
                  <button
                    className={`rounded px-2.5 py-1 text-xs font-medium ${
                      a.is_active
                        ? "bg-emerald-100 text-emerald-700"
                        : "bg-slate-200 text-slate-500"
                    }`}
                  >
                    {a.is_active ? "掲載中" : "停止中"}
                  </button>
                </form>
              </div>
            </div>
          ))
        )}
      </div>

      <p className="mt-4 text-xs text-slate-400">
        ※ 新規広告の登録フォーム（画像アップロード・期間・料金）は次フェーズで追加できます。
        いまはバナー画像URL・リンク・掲載面を登録すると、各ページの広告枠に表示されます。
      </p>
    </div>
  );
}
