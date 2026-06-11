'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import { api, yen } from '@/lib/api';

// 印刷/PDF保存用の請求書。ブラウザの「印刷 → PDFに保存」で日本語フォントのままPDF化できる。
export default function InvoicePage() {
  const [inv, setInv] = useState<any>(null);
  const [error, setError] = useState('');

  useEffect(() => { api.invoice().then(setInv).catch((e) => setError(String(e))); }, []);

  if (error) return <p className="p-6 text-red-600">{error}</p>;
  if (!inv) return <p className="p-6 text-gray-400">読み込み中…</p>;

  return (
    <div>
      {/* 画面操作（印刷時は非表示） */}
      <div className="mb-4 flex items-center justify-between print:hidden">
        <Link href="/usage" className="text-sm text-brand hover:underline">← 利用状況へ戻る</Link>
        <button onClick={() => window.print()}
          className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">
          印刷 / PDF保存
        </button>
      </div>

      {/* 請求書本体 */}
      <div className="mx-auto max-w-2xl bg-white p-10 text-sm text-gray-800 shadow-sm print:shadow-none">
        <div className="flex items-start justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-wide">請求書</h1>
            <p className="mt-1 text-xs text-gray-500">INVOICE</p>
          </div>
          <div className="text-right text-xs text-gray-600">
            <div>請求書番号：{inv.invoice_no}</div>
            <div>発行日：{inv.issued_date}</div>
            <div>対象月：{inv.month}</div>
          </div>
        </div>

        <div className="mt-8 grid grid-cols-2 gap-8">
          <div>
            <div className="border-b pb-1 text-xs text-gray-400">請求先</div>
            <div className="mt-2 font-semibold">{inv.tenant.company_name} 御中</div>
            {inv.tenant.address && <div className="mt-1 text-xs text-gray-600">{inv.tenant.address}</div>}
            {inv.tenant.billing_email && <div className="text-xs text-gray-600">{inv.tenant.billing_email}</div>}
          </div>
          <div>
            <div className="border-b pb-1 text-xs text-gray-400">請求元</div>
            <div className="mt-2 font-semibold text-brand">AIオペレーター24</div>
            <div className="mt-1 text-xs text-gray-600">AI電話受付サービス</div>
            <div className="text-xs text-gray-600">support@ai-operator24.com</div>
          </div>
        </div>

        <div className="mt-8 rounded-lg bg-brand-light p-4">
          <div className="text-xs text-gray-500">ご請求金額（税込）</div>
          <div className="mt-1 text-3xl font-bold text-brand">{yen(inv.total)}</div>
        </div>

        <table className="mt-8 w-full">
          <thead>
            <tr className="border-b text-left text-xs text-gray-500">
              <th className="py-2">項目</th>
              <th className="py-2 text-right">数量</th>
              <th className="py-2 text-right">単価</th>
              <th className="py-2 text-right">金額</th>
            </tr>
          </thead>
          <tbody>
            {inv.lines.map((l: any, i: number) => (
              <tr key={i} className="border-b">
                <td className="py-2">{l.desc}</td>
                <td className="py-2 text-right">{l.qty}{l.unit}</td>
                <td className="py-2 text-right">{yen(l.unitPrice)}</td>
                <td className="py-2 text-right">{yen(l.amount)}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <div className="mt-4 flex justify-end">
          <table className="w-64 text-sm">
            <tbody>
              <tr><td className="py-1 text-gray-500">小計</td><td className="py-1 text-right">{yen(inv.subtotal)}</td></tr>
              <tr><td className="py-1 text-gray-500">消費税（{Math.round(inv.tax_rate * 100)}%）</td><td className="py-1 text-right">{yen(inv.tax)}</td></tr>
              <tr className="border-t font-bold"><td className="py-2">合計</td><td className="py-2 text-right">{yen(inv.total)}</td></tr>
            </tbody>
          </table>
        </div>

        <div className="mt-8 border-t pt-4 text-xs text-gray-500">
          <p>ご利用状況：通話 {inv.usage.calls}件 / 課金対象 {inv.usage.billable_minutes}分
            {inv.usage.overage_minutes > 0 && `（うち超過 ${inv.usage.overage_minutes}分）`}</p>
          <p className="mt-1">※ 本請求書はデモ環境のサンプルです。実際の請求内容はご契約条件に基づきます。</p>
        </div>
      </div>
    </div>
  );
}
