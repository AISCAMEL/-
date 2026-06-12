'use client';

import { useState } from 'react';
import Link from 'next/link';
import { submitLead } from '@/lib/api';

const CATEGORIES = [
  { value: 'document', label: '資料請求' },
  { value: 'demo', label: '無料デモを試したい' },
  { value: 'consultation', label: '導入のご相談' },
  { value: 'inquiry', label: 'その他お問い合わせ' },
];

export default function ContactPage() {
  const [form, setForm] = useState({ category: 'document', name: '', company: '', email: '', phone: '', industry: '', message: '' });
  const [companyUrl, setCompanyUrl] = useState(''); // ハニーポット
  const [agreed, setAgreed] = useState(false);
  const [done, setDone] = useState(false);
  const [error, setError] = useState('');
  const [sending, setSending] = useState(false);

  function set(k: string, v: string) { setForm({ ...form, [k]: v }); }

  async function submit(e: React.FormEvent) {
    e.preventDefault();
    setError('');
    if (!form.email && !form.phone) { setError('メールアドレスまたは電話番号をご入力ください。'); return; }
    if (!agreed) { setError('利用規約・プライバシーポリシーへの同意が必要です。'); return; }
    setSending(true);
    try {
      await submitLead({ ...form, company_url: companyUrl, page: 'contact' });
      setDone(true);
    } catch (err: any) {
      setError(String(err.message ?? err));
    } finally {
      setSending(false);
    }
  }

  return (
    <main className="min-h-screen bg-gray-50">
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
          <Link href="/" className="text-lg font-bold text-brand">AIオペレーター24</Link>
          <Link href="/login" className="text-sm text-gray-600 hover:text-gray-900">管理画面ログイン</Link>
        </div>
      </header>

      <div className="mx-auto max-w-2xl px-6 py-12">
        {done ? (
          <div className="rounded-2xl border bg-white p-10 text-center shadow-sm">
            <div className="text-4xl">✅</div>
            <h1 className="mt-4 text-2xl font-bold">送信が完了しました</h1>
            <p className="mt-3 text-gray-600">
              お問い合わせありがとうございます。担当者より2営業日以内にご連絡いたします。<br />
              確認メールをお送りしましたのでご確認ください。
            </p>
            <Link href="/" className="mt-6 inline-block rounded-lg bg-brand px-6 py-2.5 font-medium text-white hover:bg-brand-dark">トップへ戻る</Link>
          </div>
        ) : (
          <div className="rounded-2xl border bg-white p-8 shadow-sm">
            <h1 className="text-2xl font-bold">お問い合わせ・資料請求</h1>
            <p className="mt-2 text-sm text-gray-600">下記フォームよりお気軽にお問い合わせください。無料デモのご予約も承ります。</p>

            {error && <p className="mt-4 rounded-lg bg-red-50 px-4 py-2 text-sm text-red-700">{error}</p>}

            <form onSubmit={submit} className="mt-6 space-y-4">
              <div>
                <label className="block text-sm font-medium text-gray-700">お問い合わせ種別</label>
                <select value={form.category} onChange={(e) => set('category', e.target.value)}
                  className="mt-1 w-full rounded-lg border px-3 py-2 text-sm">
                  {CATEGORIES.map((c) => <option key={c.value} value={c.value}>{c.label}</option>)}
                </select>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="お名前" value={form.name} onChange={(v) => set('name', v)} placeholder="山田 太郎" />
                <Field label="会社名・店舗名" value={form.company} onChange={(v) => set('company', v)} placeholder="〇〇株式会社" />
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <Field label="メールアドレス" type="email" value={form.email} onChange={(v) => set('email', v)} placeholder="you@example.com" />
                <Field label="電話番号" value={form.phone} onChange={(v) => set('phone', v)} placeholder="090-1234-5678" />
              </div>
              <Field label="業種" value={form.industry} onChange={(v) => set('industry', v)} placeholder="美容室 / 飲食 / 不動産 など" />
              <div>
                <label className="block text-sm font-medium text-gray-700">お問い合わせ内容</label>
                <textarea value={form.message} onChange={(e) => set('message', e.target.value)} rows={4}
                  className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" placeholder="ご質問・ご相談内容をご記入ください" />
              </div>
              {/* ハニーポット（人間には非表示。botが埋めると送信が無効化される） */}
              <input type="text" name="company_url" value={companyUrl} onChange={(e) => setCompanyUrl(e.target.value)}
                tabIndex={-1} autoComplete="off" aria-hidden="true"
                className="absolute left-[-9999px] h-0 w-0 opacity-0" />

              <label className="flex items-start gap-2 text-xs text-gray-600">
                <input type="checkbox" checked={agreed} onChange={(e) => setAgreed(e.target.checked)} className="mt-0.5 h-4 w-4" />
                <span>
                  <Link href="/legal/terms" className="text-brand hover:underline" target="_blank">利用規約</Link>
                  および
                  <Link href="/legal/privacy" className="text-brand hover:underline" target="_blank">プライバシーポリシー</Link>
                  に同意します。
                </span>
              </label>

              <button disabled={sending}
                className="w-full rounded-lg bg-brand py-3 font-semibold text-white hover:bg-brand-dark disabled:opacity-50">
                {sending ? '送信中…' : '送信する'}
              </button>
            </form>
          </div>
        )}
      </div>
    </main>
  );
}

function Field({ label, value, onChange, placeholder, type = 'text' }: { label: string; value: string; onChange: (v: string) => void; placeholder?: string; type?: string }) {
  return (
    <div>
      <label className="block text-sm font-medium text-gray-700">{label}</label>
      <input type={type} value={value} onChange={(e) => onChange(e.target.value)} placeholder={placeholder}
        className="mt-1 w-full rounded-lg border px-3 py-2 text-sm" />
    </div>
  );
}
