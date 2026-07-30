"use client";

/* eslint-disable @next/next/no-img-element */
import { useEffect, useState } from "react";
import Link from "next/link";
import { Brand } from "@/components/brand";
import { readCart, writeCart, yen, sumShipping, type CartLine } from "@/lib/shop";
import { placeOrder } from "@/app/shop/actions";
import { Field, Notice } from "@/components/ui/form";

export default function CartPage() {
  const [lines, setLines] = useState<CartLine[]>([]);
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [addr, setAddr] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);
  const [pending, setPending] = useState(false);

  useEffect(() => {
    // localStorage はクライアントのみ。初回マウント時に読み込む。
    // eslint-disable-next-line react-hooks/set-state-in-effect
    setLines(readCart());
  }, []);

  // 表示用の概算。確定金額はサーバ側で products から再計算する（改ざん防止）。
  const subtotal = lines.reduce((s, l) => s + l.price * l.qty, 0);
  const shipping = sumShipping(lines.map((l) => l.shipping_fee));
  const total = subtotal + shipping;

  function setQty(id: string, qty: number) {
    const next = lines
      .map((l) => (l.id === id ? { ...l, qty: Math.max(1, qty) } : l))
      .filter((l) => l.qty > 0);
    setLines(next);
    writeCart(next);
  }
  function remove(id: string) {
    const next = lines.filter((l) => l.id !== id);
    setLines(next);
    writeCart(next);
  }

  async function order(e: React.FormEvent) {
    e.preventDefault();
    if (!name.trim() || !email.trim()) {
      setError("お名前とメールアドレスをご記入ください。");
      return;
    }
    setError(null);
    setPending(true);

    // 金額はサーバ側で再計算。ここでは商品IDと数量・連絡先だけを送る。
    const result = await placeOrder({
      items: lines.map((l) => ({ id: l.id, qty: l.qty })),
      name: name.trim(),
      email: email.trim(),
      addr: addr.trim() || undefined,
    });

    if (!result.ok) {
      setPending(false);
      setError(result.error);
      return;
    }

    writeCart([]);
    setLines([]);
    setPending(false);
    setDone(true);
  }

  return (
    <div className="min-h-screen bg-foam">
      <header className="border-b border-navy/10 bg-white px-6 py-4">
        <div className="mx-auto flex max-w-3xl items-center justify-between">
          <Brand />
          <Link href="/shop" className="text-sm text-navy/60 hover:text-ocean">
            ← 買い物を続ける
          </Link>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-4 py-8">
        <h1 className="text-2xl font-semibold text-navy">カート</h1>

        {done ? (
          <div className="mt-8 rounded-2xl border border-teal/30 bg-white p-6 text-center">
            <div className="text-4xl">🌊</div>
            <h2 className="mt-3 text-lg font-semibold text-navy">ご注文を受け付けました</h2>
            <p className="mx-auto mt-3 max-w-md text-sm leading-relaxed text-navy/70">
              受注制のため、これから仕入先の在庫を確認します。
              確保でき次第に「受注確定」のご連絡をし、発送します。
              欠品の場合は代替のご提案または返金をご案内します。
              現時点では課金は確定していません。
            </p>
            <Link
              href="/shop"
              className="mt-6 inline-block rounded-full bg-ocean px-6 py-2.5 text-sm font-medium text-foam transition hover:bg-navy"
            >
              ショップに戻る
            </Link>
          </div>
        ) : lines.length === 0 ? (
          <div className="mt-8 rounded-2xl border border-dashed border-navy/20 bg-white p-10 text-center text-sm text-navy/60">
            カートは空です。
            <Link href="/shop" className="ml-1 text-ocean hover:underline">ショップを見る</Link>
          </div>
        ) : (
          <div className="mt-6 grid gap-8 md:grid-cols-[1.4fr_1fr]">
            {/* 明細 */}
            <div className="space-y-3">
              {lines.map((l) => (
                <div key={l.id} className="flex gap-3 rounded-2xl border border-navy/10 bg-white p-3">
                  {l.image_url ? (
                    <img src={l.image_url} alt={l.name} className="h-20 w-20 rounded-lg object-cover" />
                  ) : null}
                  <div className="flex-1">
                    <p className="text-sm font-medium text-navy">{l.name}</p>
                    <p className="mt-0.5 text-sm text-navy/70">{yen(l.price)}</p>
                    <div className="mt-2 flex items-center gap-3">
                      <div className="flex items-center rounded-lg border border-navy/15">
                        <button onClick={() => setQty(l.id, l.qty - 1)} className="px-2.5 py-1 text-navy/60">−</button>
                        <span className="w-7 text-center text-sm">{l.qty}</span>
                        <button onClick={() => setQty(l.id, l.qty + 1)} className="px-2.5 py-1 text-navy/60">＋</button>
                      </div>
                      <button onClick={() => remove(l.id)} className="text-xs text-navy/40 hover:text-red-500">
                        削除
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>

            {/* 注文フォーム */}
            <form onSubmit={order} className="space-y-4 rounded-2xl border border-navy/10 bg-white p-5">
              <div className="space-y-1 text-sm">
                <Row label="小計" value={yen(subtotal)} />
                <Row label="送料" value={yen(shipping)} />
                <div className="mt-2 flex justify-between border-t border-navy/10 pt-2 text-base font-semibold text-navy">
                  <span>合計（税込）</span>
                  <span>{yen(total)}</span>
                </div>
              </div>

              {error ? <Notice kind="error">{error}</Notice> : null}
              <Field label="お名前" type="text" value={name} onChange={(e) => setName(e.target.value)} />
              <Field label="メールアドレス" type="email" value={email} onChange={(e) => setEmail(e.target.value)} />
              <Field label="お届け先（任意）" type="text" value={addr} onChange={(e) => setAddr(e.target.value)} />

              <button
                type="submit"
                disabled={pending}
                className="w-full rounded-lg bg-ocean px-4 py-3 font-medium text-foam transition hover:bg-navy disabled:opacity-60"
              >
                {pending ? "送信中…" : "注文を確定する（受注制）"}
              </button>
              <p className="text-xs text-navy/50">
                ※ ボタンを押すと注文を受け付けます。この時点で課金は確定しません。
                在庫確保後に受注確定・お支払いのご案内をします。決済はPSP経由で、カード情報は当社で保持しません。
              </p>
            </form>
          </div>
        )}
      </main>
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex justify-between text-navy/70">
      <span>{label}</span>
      <span>{value}</span>
    </div>
  );
}
