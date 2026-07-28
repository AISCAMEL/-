import Link from "next/link";
import type { Metadata } from "next";
import { channelStatus, isSquareConfigured, CHANNEL_META, type ChannelKey } from "@/lib/integrations";

export const metadata: Metadata = { title: "販売チャネル・決済｜運営管理" };

export default function AdminChannels() {
  const status = channelStatus();
  const square = isSquareConfigured();
  const keys: ChannelKey[] = ["base", "rakuten", "yahoo", "amazon"];

  return (
    <div>
      <h1 className="text-xl font-semibold text-slate-900">販売チャネル・決済</h1>
      <p className="mt-1 text-sm text-slate-500">
        各サービスの接続状況です。接続にはそれぞれの加盟店アカウントとAPIキーが必要です。
      </p>

      {/* 販売チャネル */}
      <h2 className="mt-6 text-sm font-semibold text-slate-600">販売チャネル（物販）</h2>
      <div className="mt-3 space-y-2">
        {keys.map((k) => (
          <Row
            key={k}
            label={CHANNEL_META[k].label}
            role={CHANNEL_META[k].role}
            connected={status[k]}
            action={k === "base" ? { href: "/admin/suppliers", label: "仕入れ先・掲載管理 →" } : undefined}
          />
        ))}
      </div>

      {/* 決済 */}
      <h2 className="mt-8 text-sm font-semibold text-slate-600">決済</h2>
      <div className="mt-3 space-y-2">
        <Row label="Square" role="講座・プレミアムのサブスク／対面決済" connected={square} />
      </div>

      <div className="mt-8 rounded-lg border border-slate-200 bg-white p-4 text-xs leading-relaxed text-slate-500">
        <p className="font-semibold text-slate-700">セットアップ（envに設定）</p>
        <ul className="mt-2 space-y-1">
          <li>BASE：<code>BASE_API_ACCESS_TOKEN</code>（BASE Developers でアプリ登録→OAuthで取得）</li>
          <li>楽天：<code>RAKUTEN_API_KEY</code>（楽天RMS / 出店契約が必要）</li>
          <li>Yahoo!：<code>YAHOO_API_KEY</code>（Yahoo!ショッピング ストアアカウント）</li>
          <li>Amazon：<code>AMAZON_SP_API_TOKEN</code>（Amazon 出品用アカウント / SP-API）</li>
          <li>Square：<code>SQUARE_ACCESS_TOKEN</code> ほか（Square Developer）</li>
        </ul>
        <p className="mt-2">※ これらのアカウント作成・APIキー取得は運営（あなた）の作業です。キー設定後、自動掲載・決済が有効になります。</p>
      </div>
    </div>
  );
}

function Row({
  label,
  role,
  connected,
  action,
}: {
  label: string;
  role: string;
  connected: boolean;
  action?: { href: string; label: string };
}) {
  return (
    <div className="flex items-center justify-between rounded-lg border border-slate-200 bg-white px-4 py-3">
      <div>
        <p className="font-medium text-slate-800">{label}</p>
        <p className="text-xs text-slate-400">{role}</p>
      </div>
      <div className="flex items-center gap-3">
        {action ? (
          <Link href={action.href} className="text-xs text-ocean hover:underline">
            {action.label}
          </Link>
        ) : null}
        <span
          className={`rounded px-2 py-0.5 text-xs ${
            connected ? "bg-emerald-100 text-emerald-700" : "bg-slate-200 text-slate-500"
          }`}
        >
          {connected ? "接続済み" : "未接続"}
        </span>
      </div>
    </div>
  );
}
