'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle, formatDateTime } from '@/components/ui';

export default function PhoneNumbersPage() {
  const [nums, setNums] = useState<any[]>([]);

  function load() { api.phoneNumbers().then(setNums); }
  useEffect(() => { load(); }, []);

  async function toggleStatus(n: any) {
    await api.updatePhoneNumber(n.id, { status: n.status === 'active' ? 'released' : 'active' });
    load();
  }

  return (
    <div>
      <PageTitle title="電話番号設定" sub="割り当てられたAI受付用の電話番号です。番号の発行・購入は運営側で行います（MVP）。" />
      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr>
              <th className="px-4 py-3">電話番号</th>
              <th className="px-4 py-3">種別</th>
              <th className="px-4 py-3">割当日</th>
              <th className="px-4 py-3">状態</th>
              <th className="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            {nums.map((n) => (
              <tr key={n.id} className="border-b last:border-0">
                <td className="px-4 py-3 font-medium">{n.phone_number}</td>
                <td className="px-4 py-3 text-gray-600">{n.type}</td>
                <td className="px-4 py-3 text-gray-600">{formatDateTime(n.assigned_at)}</td>
                <td className="px-4 py-3">
                  <span className={`rounded-full px-2.5 py-0.5 text-xs font-medium ${
                    n.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'}`}>
                    {n.status === 'active' ? '有効' : '停止中'}
                  </span>
                </td>
                <td className="px-4 py-3 text-right">
                  <button onClick={() => toggleStatus(n)} className="text-brand hover:underline">
                    {n.status === 'active' ? '停止' : '有効化'}
                  </button>
                </td>
              </tr>
            ))}
            {nums.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">番号がありません。</td></tr>}
          </tbody>
        </table>
      </Card>
    </div>
  );
}
