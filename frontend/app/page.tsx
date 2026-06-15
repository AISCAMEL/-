import Link from 'next/link';
import ChatWidget from '@/components/ChatWidget';

const features = [
  { icon: '📞', title: '24時間AIが一次対応', desc: '営業時間外も接客中も移動中も、AIが自然な日本語で電話に応答します。' },
  { icon: '📅', title: '予約・問い合わせ受付', desc: '希望日時・名前・要件を聞き取り、予約希望として受け付けます。' },
  { icon: '🔁', title: '折り返し・担当者転送', desc: '折り返し依頼の受付や、必要に応じて担当者への転送を判断します。' },
  { icon: '📝', title: '通話の文字起こし・要約', desc: '通話内容を自動で文字起こし・要約。必要な情報だけを届けます。' },
  { icon: '✉️', title: 'メール・Slack通知', desc: '通話終了後、要件と要約をすぐに通知。折り返し漏れを防ぎます。' },
  { icon: '📊', title: '管理画面で可視化', desc: '着信数・対応結果・履歴をダッシュボードで一目で把握できます。' },
];

const plans = [
  { name: 'Starter', price: '9,800', target: '小規模店舗向け', limit: '月100分まで',
    items: ['AI電話受付', 'FAQ回答', '通話要約', 'メール通知', '基本管理画面'], over: '超過 1分80円' },
  { name: 'Business', price: '29,800', target: '店舗・中小企業向け', limit: '月500分まで', featured: true,
    items: ['Starterの全機能', '人間転送', '予約受付', 'Slack通知', '通話履歴管理'], over: '超過 1分60円' },
  { name: 'Pro', price: '59,800', target: '営業組織・複数担当向け', limit: '月1,500分まで',
    items: ['Businessの全機能', '複数番号', 'CRM連携', '高度な分析', '担当者振り分け', '優先サポート'], over: '超過 1分50円' },
];

export default function LandingPage() {
  return (
    <main className="min-h-screen bg-white">
      {/* Header */}
      <header className="border-b">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <div className="text-lg font-bold text-brand">AIオペレーター24</div>
          <nav className="flex items-center gap-4 text-sm">
            <a href="#features" className="hidden text-gray-600 hover:text-gray-900 sm:inline">機能</a>
            <a href="#compare" className="hidden text-gray-600 hover:text-gray-900 sm:inline">比較</a>
            <a href="#plans" className="hidden text-gray-600 hover:text-gray-900 sm:inline">料金</a>
            <Link href="/login" className="rounded-lg bg-brand px-4 py-2 font-medium text-white hover:bg-brand-dark">
              管理画面ログイン
            </Link>
          </nav>
        </div>
      </header>

      {/* Hero */}
      <section className="bg-gradient-to-b from-brand-light to-white">
        <div className="mx-auto max-w-6xl px-6 py-20 text-center">
          <h1 className="text-4xl font-bold leading-tight sm:text-5xl">
            もう、電話を取り逃がさない。<br />
            <span className="text-brand">AIが24時間、あなたの会社の電話受付に。</span>
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-lg text-gray-600">
            AIオペレーター24は、予約受付・問い合わせ対応・折り返し依頼・担当者転送・通話要約まで自動化するAI電話受付サービスです。
            営業時間外も、接客中も、移動中も。AIが自然な日本語でお客様の要件を聞き取り、必要な情報だけをあなたに届けます。
          </p>
          <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <Link href="/contact" className="rounded-lg bg-brand px-8 py-3 font-semibold text-white hover:bg-brand-dark">
              無料デモを試す
            </Link>
            <Link href="/contact" className="rounded-lg border border-gray-300 px-8 py-3 font-semibold text-gray-700 hover:bg-gray-50">
              資料請求する
            </Link>
          </div>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="mx-auto max-w-6xl px-6 py-20">
        <h2 className="text-center text-3xl font-bold">電話対応のすべてを、AIが。</h2>
        <div className="mt-12 grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
          {features.map((f) => (
            <div key={f.title} className="rounded-xl border p-6 shadow-sm">
              <div className="text-3xl">{f.icon}</div>
              <h3 className="mt-3 text-lg font-semibold">{f.title}</h3>
              <p className="mt-2 text-sm text-gray-600">{f.desc}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Comparison */}
      <section id="compare" className="bg-white">
        <div className="mx-auto max-w-6xl px-6 py-20">
          <h2 className="text-center text-3xl font-bold">他の方法と比べてみてください</h2>
          <p className="mt-3 text-center text-gray-600">
            人を雇う・電話代行を頼む・他のAI電話と比べても、24時間と要約の自動化で“ちょうどいい”。
          </p>

          <div className="mt-12 overflow-x-auto">
            <table className="w-full min-w-[680px] border-collapse text-sm">
              <thead>
                <tr className="border-b">
                  <th className="px-3 py-3 text-left font-medium text-gray-500"> </th>
                  <th className="rounded-t-xl bg-brand-light px-3 py-3 text-center font-bold text-brand">
                    AIオペレーター24
                  </th>
                  <th className="px-3 py-3 text-center font-medium text-gray-600">AI電話（最安系）</th>
                  <th className="px-3 py-3 text-center font-medium text-gray-600">電話代行（人手）</th>
                  <th className="px-3 py-3 text-center font-medium text-gray-600">スタッフ採用</th>
                </tr>
              </thead>
              <tbody>
                {[
                  ['月額の目安', '¥9,800〜', '¥0〜3,000〜', '¥5,000〜30,000', '¥100,000〜'],
                  ['24時間対応', '◎', '◎', '△（高額）', '△（シフト）'],
                  ['AIが会話で要件聞き取り', '◎', '△（選択式中心）', '◎（人）', '◎（人）'],
                  ['予約・折り返し受付', '◎', '○', '◎', '◎'],
                  ['担当者へ転送', '◎', '○', '◎', '◎'],
                  ['通話の文字起こし・要約', '◎ 自動', '△', '△ メモ', '△'],
                  ['メール／Slack通知', '◎', '○', '○', '—'],
                  ['管理画面で履歴管理', '◎', '○', '△', '—'],
                  ['初期費用', '¥0', '¥0', '¥1〜3万', '採用コスト'],
                ].map((row, i) => (
                  <tr key={i} className="border-b">
                    <td className="px-3 py-3 text-left font-medium text-gray-700">{row[0]}</td>
                    <td className="bg-brand-light/50 px-3 py-3 text-center font-semibold text-brand">{row[1]}</td>
                    <td className="px-3 py-3 text-center text-gray-600">{row[2]}</td>
                    <td className="px-3 py-3 text-center text-gray-600">{row[3]}</td>
                    <td className="px-3 py-3 text-center text-gray-600">{row[4]}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <p className="mt-4 text-center text-xs text-gray-400">
            ※ 各社の料金・機能は2026年6月時点の一般的な相場をもとにした比較です。最新の内容は各社公式をご確認ください。
          </p>
        </div>
      </section>

      {/* Plans */}
      <section id="plans" className="bg-gray-50">
        <div className="mx-auto max-w-6xl px-6 py-20">
          <h2 className="text-center text-3xl font-bold">料金プラン</h2>
          <p className="mt-3 text-center text-gray-600">月額固定 + 通話分数上限 + 超過課金。まずは1週間のテスト導入から。</p>
          <div className="mt-12 grid gap-8 lg:grid-cols-3">
            {plans.map((p) => (
              <div key={p.name}
                className={`rounded-2xl border bg-white p-8 shadow-sm ${p.featured ? 'ring-2 ring-brand' : ''}`}>
                {p.featured && (
                  <div className="mb-3 inline-block rounded-full bg-brand px-3 py-1 text-xs font-semibold text-white">
                    人気No.1
                  </div>
                )}
                <h3 className="text-xl font-bold">{p.name}</h3>
                <p className="mt-1 text-sm text-gray-500">{p.target}</p>
                <div className="mt-4">
                  <span className="text-3xl font-bold">¥{p.price}</span>
                  <span className="text-gray-500">〜 / 月</span>
                </div>
                <p className="mt-1 text-sm font-medium text-brand">{p.limit}</p>
                <ul className="mt-6 space-y-2 text-sm">
                  {p.items.map((it) => (
                    <li key={it} className="flex items-center gap-2">
                      <span className="text-brand">✓</span> {it}
                    </li>
                  ))}
                </ul>
                <p className="mt-4 text-xs text-gray-400">{p.over}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="bg-brand">
        <div className="mx-auto max-w-4xl px-6 py-16 text-center text-white">
          <h2 className="text-3xl font-bold">まずは御社専用のAI電話番号で1週間テスト。</h2>
          <p className="mt-4 text-brand-light">電話に出られなかった問い合わせ、もう逃しません。</p>
          <Link href="/contact" className="mt-8 inline-block rounded-lg bg-white px-8 py-3 font-semibold text-brand hover:bg-gray-100">
            無料デモを試す
          </Link>
        </div>
      </section>

      <footer className="border-t">
        <div className="mx-auto max-w-6xl px-6 py-8 text-center text-sm text-gray-400">
          <nav className="mb-3 flex flex-wrap justify-center gap-4">
            <Link href="/contact" className="hover:text-gray-700">お問い合わせ</Link>
            <Link href="/legal/terms" className="hover:text-gray-700">利用規約</Link>
            <Link href="/legal/privacy" className="hover:text-gray-700">プライバシーポリシー</Link>
            <Link href="/legal/tokushoho" className="hover:text-gray-700">特定商取引法に基づく表記</Link>
          </nav>
          © 2026 AIオペレーター24
        </div>
      </footer>

      {/* AIチャットボット（動画アバター対応） */}
      <ChatWidget />
    </main>
  );
}
