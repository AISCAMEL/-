'use client';

import { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import { api } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

export default function ContactsPage() {
  const router = useRouter();
  const [rows, setRows] = useState<any[]>([]);
  const [cats, setCats] = useState<string[]>([]);
  const [category, setCategory] = useState('');
  const [q, setQ] = useState('');
  const [bulk, setBulk] = useState('');
  const [bulkCat, setBulkCat] = useState('');
  const [msg, setMsg] = useState('');
  const [showImport, setShowImport] = useState(false);
  const [showCampaign, setShowCampaign] = useState(false);
  const [camp, setCamp] = useState({ name: '', purpose: 'sales', opening: '', goal_prompt: '' });
  const [draftOpen, setDraftOpen] = useState(false);
  const [draft, setDraft] = useState({ kind: 'email', product: '', target: '', text: '' });
  const [mailOpen, setMailOpen] = useState(false);
  const [mail, setMail] = useState({ subject: '', body: '' });

  function load() {
    const p = new URLSearchParams();
    if (category) p.set('category', category);
    if (q) p.set('q', q);
    const s = p.toString();
    api.contacts(s ? `?${s}` : '').then(setRows);
    api.contactCategories().then(setCats);
  }
  useEffect(() => { load(); /* eslint-disable-next-line */ }, [category]);

  async function importBulk(e: React.FormEvent) {
    e.preventDefault();
    // 1行：名前,会社,電話,メール（足りない分は空でOK）
    const items = bulk.split('\n').map((l) => l.trim()).filter(Boolean).map((l) => {
      const [name, company, phone_number, email] = l.split(/[,\t]/).map((s) => s.trim());
      return { name, company, phone_number, email, category: bulkCat || null };
    });
    await api.createContacts(items);
    setBulk(''); setShowImport(false); setMsg(`${items.length}件を追加しました。`);
    load();
  }
  async function saveNote(id: string, note: string) { await api.updateContact(id, { note }); }
  async function remove(id: string) { if (!confirm('削除しますか？')) return; await api.deleteContact(id); load(); }

  async function makeCampaign(e: React.FormEvent) {
    e.preventDefault();
    if (!camp.name) return;
    const r = await api.contactsToCampaign({ ...camp, category: category || undefined });
    setShowCampaign(false);
    router.push(`/campaigns/${r.campaign.id}`);
  }
  async function generate() {
    if (!draft.product) return;
    setMsg('AIが文面を作成中…');
    const r = await api.aiDraft({ kind: draft.kind, product: draft.product, target: draft.target });
    setDraft({ ...draft, text: r.text });
    setMsg(r.fallback ? '（AI未接続のためサンプル文面）' : 'AIが文面を作成しました。');
  }
  async function sendBulk(e: React.FormEvent) {
    e.preventDefault();
    if (!mail.subject || !mail.body) return;
    if (!confirm(`${category ? `「${category}」の` : '全'}連絡先（メールあり）に一斉送信します。よろしいですか？`)) return;
    setMsg('送信中…');
    const r = await api.bulkEmail(mail.subject, mail.body, category || undefined);
    setMsg(`一斉メール：${r.sent}件送信${r.failed ? `／${r.failed}件失敗` : ''}（対象${r.total}件）`);
    setMailOpen(false);
  }

  return (
    <div>
      <div className="flex flex-wrap items-center justify-between gap-2">
        <PageTitle title="連絡先リスト" sub="見込み客・取引先をカテゴリ分けして管理。架電キャンペーンやメールにつなげられます。" />
        <div className="flex gap-2">
          <button onClick={() => setDraftOpen(true)} className="h-10 rounded-lg border px-4 text-sm hover:bg-gray-50">AIで営業文面</button>
          <button onClick={() => setMailOpen(true)} className="h-10 rounded-lg border px-4 text-sm hover:bg-gray-50">一斉メール</button>
          <button onClick={() => setShowCampaign(true)} className="h-10 rounded-lg border px-4 text-sm hover:bg-gray-50">架電にする</button>
          <button onClick={() => setShowImport((v) => !v)} className="h-10 rounded-lg bg-brand px-4 text-sm font-medium text-white hover:bg-brand-dark">＋ 取込</button>
        </div>
      </div>
      {msg && <p className="mb-3 text-sm text-brand">{msg}</p>}

      {showImport && (
        <Card className="mb-4">
          <h2 className="mb-2 text-sm font-semibold text-gray-500">一括取込（1行ずつ：名前,会社,電話番号,メール）</h2>
          <form onSubmit={importBulk} className="space-y-2">
            <input value={bulkCat} onChange={(e) => setBulkCat(e.target.value)} placeholder="カテゴリ（任意・例：見込み客）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <textarea value={bulk} onChange={(e) => setBulk(e.target.value)} rows={4} placeholder={'田中太郎,田中商店,+819012340001,tanaka@example.com\n鈴木一郎,鈴木工務店,+819012340003'} className="w-full rounded-lg border px-3 py-2 font-mono text-sm" />
            <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">取り込む</button>
          </form>
        </Card>
      )}

      {mailOpen && (
        <Card className="mb-4">
          <h2 className="mb-2 text-sm font-semibold text-gray-500">一斉メール送信{category ? `（カテゴリ：${category}）` : '（全連絡先）'}</h2>
          <form onSubmit={sendBulk} className="space-y-2">
            <input value={mail.subject} onChange={(e) => setMail({ ...mail, subject: e.target.value })} placeholder="件名（{{name}} {{company}} 使用可）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <textarea value={mail.body} onChange={(e) => setMail({ ...mail, body: e.target.value })} rows={5} placeholder="本文（{{name}}様、{{company}}ご担当者様 などで差し込み可）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <div className="flex gap-2">
              <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">送信</button>
              {draft.text && <button type="button" onClick={() => setMail({ ...mail, body: draft.text })} className="rounded-lg border px-4 py-2 text-sm hover:bg-gray-50">AI文面を流用</button>}
            </div>
          </form>
          <p className="mt-2 text-xs text-gray-400">※ メールアドレスのある連絡先のみに送信します。実送信にはResend接続が必要（未接続時はログ出力）。</p>
        </Card>
      )}

      {showCampaign && (
        <Card className="mb-4">
          <h2 className="mb-2 text-sm font-semibold text-gray-500">この絞り込みの連絡先で架電キャンペーンを作成</h2>
          <form onSubmit={makeCampaign} className="space-y-2">
            <input value={camp.name} onChange={(e) => setCamp({ ...camp, name: e.target.value })} placeholder="キャンペーン名" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <input value={camp.opening} onChange={(e) => setCamp({ ...camp, opening: e.target.value })} placeholder="最初の一言（任意）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <textarea value={camp.goal_prompt} onChange={(e) => setCamp({ ...camp, goal_prompt: e.target.value })} rows={2} placeholder="AIへの指示（任意）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">{category ? `「${category}」の連絡先で作成` : '全連絡先で作成'}</button>
          </form>
        </Card>
      )}

      {draftOpen && (
        <Card className="mb-4">
          <h2 className="mb-2 text-sm font-semibold text-gray-500">AIで営業文面を作成（会社データは作りません。文面のみ）</h2>
          <div className="space-y-2">
            <div className="flex gap-2">
              <select value={draft.kind} onChange={(e) => setDraft({ ...draft, kind: e.target.value })} className="rounded-lg border px-3 py-2 text-sm">
                <option value="email">メール文面</option><option value="call">電話トーク</option>
              </select>
              <input value={draft.product} onChange={(e) => setDraft({ ...draft, product: e.target.value })} placeholder="商品・サービスの説明" className="flex-1 rounded-lg border px-3 py-2 text-sm" />
            </div>
            <input value={draft.target} onChange={(e) => setDraft({ ...draft, target: e.target.value })} placeholder="想定の相手（例：美容室オーナー）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <button onClick={generate} className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">AIで作成</button>
            {draft.text && <textarea value={draft.text} onChange={(e) => setDraft({ ...draft, text: e.target.value })} rows={6} className="w-full rounded-lg border px-3 py-2 text-sm" />}
          </div>
        </Card>
      )}

      <div className="mb-4 flex flex-wrap gap-3">
        <select value={category} onChange={(e) => setCategory(e.target.value)} className="rounded-lg border px-3 py-2 text-sm">
          <option value="">すべてのカテゴリ</option>
          {cats.map((c) => <option key={c} value={c}>{c}</option>)}
        </select>
        <form onSubmit={(e) => { e.preventDefault(); load(); }} className="flex gap-2">
          <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="名前・会社・番号で検索" className="rounded-lg border px-3 py-2 text-sm" />
          <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">検索</button>
        </form>
      </div>

      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr><th className="px-4 py-3">名前 / 会社</th><th className="px-4 py-3">電話 / メール</th><th className="px-4 py-3">カテゴリ</th><th className="px-4 py-3">メモ</th><th className="px-4 py-3"></th></tr>
          </thead>
          <tbody>
            {rows.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">連絡先がありません。「＋ 取込」から追加してください。</td></tr>}
            {rows.map((c) => (
              <tr key={c.id} className="border-b last:border-0 align-top">
                <td className="px-4 py-3">{c.name ?? '—'}{c.company ? <div className="text-xs text-gray-500">{c.company}</div> : null}</td>
                <td className="px-4 py-3 text-gray-600">{c.phone_number ?? '—'}<div className="text-xs text-gray-400">{c.email ?? ''}</div></td>
                <td className="px-4 py-3">{c.category && <span className="rounded-full bg-gray-100 px-2 py-0.5 text-xs">{c.category}</span>}</td>
                <td className="px-4 py-3"><input defaultValue={c.note ?? ''} onBlur={(e) => saveNote(c.id, e.target.value)} placeholder="メモ（入力で保存）" className="w-full rounded border px-2 py-1 text-xs" /></td>
                <td className="px-4 py-3 text-right"><button onClick={() => remove(c.id)} className="text-red-500 hover:underline">削除</button></td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <p className="mt-4 text-xs text-gray-400">※「会社情報をAIで自動収集」は提供していません（実在データの自動取得は不正確・法令上の懸念のため）。お手元のリストやCSVを取り込んでご利用ください。AIは文面作成や分類のお手伝いをします。</p>
    </div>
  );
}
