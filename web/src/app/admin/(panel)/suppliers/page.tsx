import type { Metadata } from "next";
import { createClient } from "@/lib/supabase/server";
import { channelStatus } from "@/lib/integrations";
import { ConfirmSubmit } from "@/components/admin/confirm-submit";
import {
  createSupplier,
  createSupplierProduct,
  deleteSupplierProduct,
  publishProductToBase,
} from "@/app/admin/actions";

export const metadata: Metadata = { title: "仕入れ先・BASE掲載｜運営管理" };

type Supplier = { id: string; name: string };
type Product = {
  id: string;
  name: string;
  category: string | null;
  cost: number | null;
  price: number;
  base_status: string;
  base_message: string | null;
  supplier: { name: string } | null;
};

const input = "w-full rounded border border-slate-300 px-3 py-2 text-sm";

export default async function AdminSuppliers() {
  const supabase = await createClient();
  const [{ data: sData }, { data: pData }] = await Promise.all([
    supabase.from("suppliers").select("id, name").order("created_at", { ascending: false }),
    supabase
      .from("supplier_products")
      .select("id, name, category, cost, price, base_status, base_message, supplier:suppliers(name)")
      .order("created_at", { ascending: false })
      .limit(100),
  ]);
  const suppliers = (sData ?? []) as unknown as Supplier[];
  const products = (pData ?? []) as unknown as Product[];
  const baseConnected = channelStatus().base;

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">仕入れ先・BASE掲載</h1>
      <p className="mt-1 text-sm text-slate-500">
        仕入れ先の商品を登録し、BASE（オンラインショップ本体）へ出品します。
      </p>

      {/* BASE接続状況 */}
      <div
        className={`mt-4 rounded-lg border px-4 py-3 text-sm ${
          baseConnected
            ? "border-emerald-300 bg-emerald-50 text-emerald-700"
            : "border-amber-300 bg-amber-50 text-amber-700"
        }`}
      >
        {baseConnected
          ? "✅ BASE 接続済み。「BASEに掲載」で自動出品されます。"
          : "⚠️ BASE 未接続。BASE_API_ACCESS_TOKEN を設定すると自動掲載できます（各サービスのアカウント・APIキーが必要です）。"}
      </div>

      <div className="mt-6 grid gap-4 lg:grid-cols-2">
        {/* 仕入れ先登録 */}
        <form action={createSupplier} className="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
          <p className="text-sm font-semibold text-slate-700">＋ 仕入れ先を登録</p>
          <input name="name" placeholder="仕入れ先名（必須）" required className={input} />
          <input name="contact" placeholder="連絡先・担当" className={input} />
          <input name="note" placeholder="メモ" className={input} />
          <button className="rounded bg-slate-700 px-4 py-1.5 text-sm font-medium text-white">登録</button>
          <p className="text-xs text-slate-400">登録済み：{suppliers.map((s) => s.name).join("、") || "なし"}</p>
        </form>

        {/* 商品登録 */}
        <form action={createSupplierProduct} className="space-y-2 rounded-lg border border-slate-200 bg-white p-4">
          <p className="text-sm font-semibold text-slate-700">＋ 仕入れ商品を登録</p>
          <select name="supplier_id" className={input}>
            <option value="">仕入れ先を選択（任意）</option>
            {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
          </select>
          <input name="name" placeholder="商品名（必須）" required className={input} />
          <input name="category" placeholder="カテゴリ（例：ウェット）" className={input} />
          <div className="flex gap-2">
            <input name="cost" placeholder="仕入値" className={input} />
            <input name="price" placeholder="販売価格（必須）" required className={input} />
          </div>
          <input name="image_url" placeholder="画像URL" className={input} />
          <textarea name="description" rows={2} placeholder="説明" className={input} />
          <button className="rounded bg-slate-700 px-4 py-1.5 text-sm font-medium text-white">登録</button>
        </form>
      </div>

      {/* 商品一覧＋BASE掲載 */}
      <h2 className="mt-8 text-sm font-semibold text-slate-600">仕入れ商品（{products.length}）</h2>
      <div className="mt-3 space-y-2">
        {products.length === 0 ? (
          <p className="rounded-lg border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-slate-400">
            まだ商品がありません。
          </p>
        ) : (
          products.map((p) => (
            <div key={p.id} className="rounded-lg border border-slate-200 bg-white p-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div>
                  <p className="font-medium text-slate-800">{p.name}</p>
                  <p className="text-xs text-slate-400">
                    {p.supplier?.name ?? "仕入れ先未設定"}
                    {p.category ? `・${p.category}` : ""}
                    ・販売 ¥{p.price.toLocaleString("ja-JP")}
                    {p.cost != null ? `（仕入 ¥${p.cost.toLocaleString("ja-JP")}）` : ""}
                  </p>
                </div>
                <span
                  className={`rounded px-2 py-0.5 text-xs ${
                    p.base_status === "published"
                      ? "bg-emerald-100 text-emerald-700"
                      : p.base_status === "error"
                        ? "bg-red-100 text-red-700"
                        : "bg-slate-200 text-slate-500"
                  }`}
                >
                  {p.base_status === "published" ? "BASE掲載中" : p.base_status === "error" ? "掲載エラー" : "未掲載"}
                </span>
              </div>
              {p.base_message ? (
                <p className="mt-1 text-xs text-slate-400">{p.base_message}</p>
              ) : null}
              <div className="mt-3 flex gap-2">
                <form action={publishProductToBase}>
                  <input type="hidden" name="id" value={p.id} />
                  <button className="rounded bg-teal px-3 py-1.5 text-xs font-medium text-navy">
                    BASEに掲載
                  </button>
                </form>
                <form action={deleteSupplierProduct}>
                  <input type="hidden" name="id" value={p.id} />
                  <ConfirmSubmit
                    message="この仕入れ商品を削除します。よろしいですか？"
                    className="rounded border border-red-300 px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50"
                  >
                    削除
                  </ConfirmSubmit>
                </form>
              </div>
            </div>
          ))
        )}
      </div>
    </div>
  );
}
