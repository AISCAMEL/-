import Link from 'next/link';

// 法務ページ共通シェル（ヘッダ＋本文枠）。
export function LegalShell({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <main className="min-h-screen bg-gray-50">
      <header className="border-b bg-white">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-6 py-4">
          <Link href="/" className="text-lg font-bold text-brand">AIオペレーター24</Link>
          <Link href="/contact" className="text-sm text-gray-600 hover:text-gray-900">お問い合わせ</Link>
        </div>
      </header>
      <div className="mx-auto max-w-3xl px-6 py-12">
        <h1 className="text-2xl font-bold">{title}</h1>
        <div className="mt-8 rounded-2xl border bg-white p-8 leading-relaxed text-gray-800 shadow-sm">
          {children}
        </div>
        <p className="mt-6 text-center text-xs text-gray-400">© 2026 AIオペレーター24</p>
      </div>
    </main>
  );
}

// 特商法表記の行
export function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <tr className="border-b align-top last:border-0">
      <th className="w-44 bg-gray-50 px-3 py-3 text-left font-medium text-gray-600">{label}</th>
      <td className="px-3 py-3">{children}</td>
    </tr>
  );
}

// 規約・ポリシーの条文セクション
export function Section({ heading, children }: { heading: string; children: React.ReactNode }) {
  return (
    <section className="mt-6 first:mt-0">
      <h2 className="text-base font-semibold">{heading}</h2>
      <div className="mt-2 space-y-2 text-sm text-gray-700">{children}</div>
    </section>
  );
}
