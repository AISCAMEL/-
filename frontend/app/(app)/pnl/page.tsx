'use client';

import { useEffect, useState } from 'react';
import { api, yen } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

const CAT_LABEL: Record<string, string> = { personnel: '人件費', infra: 'インフラ', tools: 'ツール', marketing: '広告宣伝', other: 'その他' };

export default function PnlPage() {
  const [d, setD] = useState<any>(null);
  const [exp, setExp] = useState<any[]>([]);
  const [error, setError] = useState('');
  const [form, setForm] = useState({ label: '', category: 'other', monthly_jpy: '' });

  function load() {
    api.pnl().then(setD).catch((e) => setError(String(e)));
    api.expenses().then(setExp);
  }
  useEffect(() => { load(); }, []);

  async function addExpense(e: React.FormEvent) {
    e.preventDefault();
    if (!form.label || !form.monthly_jpy) return;
    await api.createExpense({ label: form.label, category: form.category, monthly_jpy: Number(form.monthly_jpy) });
    setForm({ label: '', category: 'other', monthly_jpy: '' });
    load();
  }
  async function removeExpense(id: string) {
    if (!confirm('この経費を削除しますか？')) return;
    await api.deleteExpense(id); load();
  }

  if (error) return <p className="text-red-600">{error}</p>;
  if (!d) return <p className="text-gray-400">読み込み中…</p>;

  const profitPositive = d.operating_profit >= 0;

  return (
    <div>
      <PageTitle title="損益計算書（P&L）" sub={`${d.month} の月次損益（運営）。課金対象${d.tenants_billed}社ベース。`} />

      <div className="grid gap-6 lg:grid-cols-3">
        {/* P&L本体 */}
        <Card className="lg:col-span-2">
          <table className="w-full text-sm">
            <tbody>
              <Section label="売上高" value={d.revenue.total} strong />
              <Line label="　基本料金（月額合計）" value={d.revenue.base} />
              <Line label="　従量課金（超過分）" value={d.revenue.overage} />

              <Section label="売上原価" value={-d.cogs.total} />
              <Line label="　AI通話原価" value={-d.cogs.ai} />
              <Line label="　転送通話原価" value={-d.cogs.transfer} />

              <Section label="売上総利益（粗利）" value={d.gross_profit} strong sub={`粗利率 ${d.gross_margin_rate}%`} />

              <Section label="販売管理費" value={-d.opex.total} />
              {Object.entries(d.opex.by_category).map(([c, v]: any) => (
                <Line key={c} label={`　${CAT_LABEL[c] ?? c}`} value={-v} />
              ))}

              <tr className={`border-t-2 ${profitPositive ? '' : 'text-red-600'}`}>
                <td className="py-3 text-base font-bold">営業利益</td>
                <td className="py-3 text-right text-base font-bold">{yen(d.operating_profit)}</td>
              </tr>
              <tr><td className="pb-2 text-xs text-gray-400">営業利益率</td><td className="pb-2 text-right text-xs text-gray-400">{d.operating_margin_rate}%</td></tr>
            </tbody>
          </table>

          {d.cogs.trial_ai > 0 && (
            <p className="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-700">
              ※ うち無償トライアルにかかっている原価 {yen(d.cogs.trial_ai)}（売上0・原価のみ）。有料転換で粗利が改善します。
            </p>
          )}
        </Card>

        {/* サマリKPI */}
        <div className="space-y-4">
          <Card className={profitPositive ? 'ring-1 ring-green-300' : 'ring-1 ring-red-300'}>
            <div className="text-xs text-gray-500">営業利益（当月）</div>
            <div className={`mt-1 text-3xl font-bold ${profitPositive ? 'text-green-700' : 'text-red-600'}`}>{yen(d.operating_profit)}</div>
            <div className="mt-1 text-xs text-gray-400">{profitPositive ? '黒字' : '赤字'} ・ 利益率 {d.operating_margin_rate}%</div>
          </Card>
          <Card>
            <div className="space-y-1 text-sm">
              <Row label="売上高" value={yen(d.revenue.total)} />
              <Row label="粗利" value={yen(d.gross_profit)} />
              <Row label="販管費" value={yen(d.opex.total)} />
            </div>
          </Card>
        </div>
      </div>

      {/* 経費の管理 */}
      <h2 className="mb-3 mt-8 text-lg font-semibold">固定経費（販管費）</h2>
      <Card>
        <form onSubmit={addExpense} className="mb-4 flex flex-wrap items-end gap-2">
          <input value={form.label} onChange={(e) => setForm({ ...form, label: e.target.value })} placeholder="費目（例：オフィス賃料）" className="flex-1 rounded-lg border px-3 py-2 text-sm" />
          <select value={form.category} onChange={(e) => setForm({ ...form, category: e.target.value })} className="rounded-lg border px-3 py-2 text-sm">
            {Object.entries(CAT_LABEL).map(([k, v]) => <option key={k} value={k}>{v}</option>)}
          </select>
          <input value={form.monthly_jpy} onChange={(e) => setForm({ ...form, monthly_jpy: e.target.value })} type="number" placeholder="月額(円)" className="w-32 rounded-lg border px-3 py-2 text-sm" />
          <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">追加</button>
        </form>
        <table className="w-full text-sm">
          <tbody>
            {exp.map((e) => (
              <tr key={e.id} className="border-b last:border-0">
                <td className="py-2">{e.label}<span className="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-500">{CAT_LABEL[e.category] ?? e.category}</span></td>
                <td className="py-2 text-right">{yen(Number(e.monthly_jpy))}</td>
                <td className="py-2 text-right"><button onClick={() => removeExpense(e.id)} className="text-red-500 hover:underline">削除</button></td>
              </tr>
            ))}
            {exp.length === 0 && <tr><td colSpan={3} className="py-6 text-center text-gray-400">経費が登録されていません。</td></tr>}
          </tbody>
        </table>
      </Card>

      <p className="mt-4 text-xs text-gray-400">※ 売上・原価は当月の利用実績（課金対象テナント）から算出。経費はここで登録した月額固定費を差し引きます。トライアル/休止テナントは売上0・原価のみ計上します。</p>
    </div>
  );
}

function Section({ label, value, strong, sub }: { label: string; value: number; strong?: boolean; sub?: string }) {
  return (
    <tr className="border-t">
      <td className={`py-2 ${strong ? 'font-semibold' : 'text-gray-600'}`}>{label}{sub && <span className="ml-2 text-xs font-normal text-gray-400">{sub}</span>}</td>
      <td className={`py-2 text-right ${strong ? 'font-semibold' : ''}`}>{yen(value)}</td>
    </tr>
  );
}
function Line({ label, value }: { label: string; value: number }) {
  return <tr><td className="py-1 text-sm text-gray-500">{label}</td><td className="py-1 text-right text-sm text-gray-500">{yen(value)}</td></tr>;
}
function Row({ label, value }: { label: string; value: string }) {
  return <div className="flex justify-between"><span className="text-gray-500">{label}</span><span className="font-medium">{value}</span></div>;
}
