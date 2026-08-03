import Link from 'next/link';
import ChatWidget from '@/components/ChatWidget';

const features = [
  { icon: '📞', title: '商談中でも電話を逃さない', desc: '接客・試乗対応・整備作業の最中も、AIが24時間お客様の電話に応答します。' },
  { icon: '🚗', title: '査定・車検・試乗の予約受付', desc: '車種・年式・走行距離・希望日時を聞き取り、予約希望として受け付けます。' },
  { icon: '🔁', title: '担当者・工場へスマート転送', desc: '内容に応じて担当営業・整備・板金へ。固定/携帯/複数人への転送も可能。' },
  { icon: '📝', title: '通話の文字起こし・要約', desc: '「プリウス2019・4万km・出張査定希望」など、要件を自動で要約して届けます。' },
  { icon: '✉️', title: 'メール・Slack通知', desc: '通話終了後すぐ通知。査定・見積り依頼の折り返し漏れを防ぎます。' },
  { icon: '📊', title: '来店・成約につなげる管理画面', desc: '着信→連絡先→追客まで一元管理。取りこぼしを売上に変えます。' },
];

// 自動車業界のジャンル別（それぞれ専用テンプレを用意）
const autoGenres = [
  { icon: '🏷️', name: '自動車販売店', desc: '在庫問い合わせ・試乗予約・見積り/ローン・下取り・来店後フォロー' },
  { icon: '💰', name: '買取専門店', desc: '買取査定・出張/持込査定・査定予約・残債相談・成約後追い' },
  { icon: '🔧', name: '整備工場・車検', desc: '車検予約・点検/整備・オイル交換・代車・入庫案内' },
  { icon: '🎨', name: '板金塗装・鈑金', desc: 'キズ/へこみ見積り・保険対応・代車・納期・事故/レッカー相談' },
];

const plans = [
  { name: '受付プラン', price: '3,980', target: '電話番の代わり（インバウンド）', limit: '着信 ¥30/件 ＋ 通話 ¥30/分',
    items: ['AI電話受付（24時間）', 'FAQ自動回答', '通話要約・文字起こし', 'メール通知', '予約受付（査定・来店）', '人間へ転送'], over: '基本料＋着信件数＋通話分の従量' },
  { name: '営業プラン', price: '6,980', target: '受付＋集客・追客まで', limit: '着信 ¥30/件 ＋ 通話 ¥25/分', featured: true,
    items: ['受付プランの全機能', '連絡先CRM', '一斉メール', 'AI営業・自動架電', '発信者ルール', 'Slack通知', 'CSV出力'], over: '基本料＋着信件数＋通話分の従量' },
  { name: '統合プラン', price: '12,800', target: '全機能・多拠点・高度分析', limit: '着信 ¥20/件 ＋ 通話 ¥20/分',
    items: ['営業プランの全機能', 'Googleカレンダー連携', '複数電話番号', '高度な分析', '担当者振り分け', '優先サポート'], over: '基本料＋着信件数＋通話分の従量' },
];

const numberOptions = [
  { name: '標準番号（050）', price: '込み', note: '基本料に1つ含む' },
  { name: '市外局番（03・06等）', price: '+¥1,000/月', note: '地域番号' },
  { name: 'フリーダイヤル（0120/0800）', price: '+¥2,000/月', note: '信頼感アップ' },
  { name: '追加番号', price: '+¥1,000/本', note: '拠点・用途別' },
];

const transferOptions = [
  { name: '固定電話へ転送', price: '¥10/分', note: '店舗・工場の固定電話へ' },
  { name: '携帯電話へ転送', price: '¥20/分', note: '担当者のスマホへ' },
  { name: '複数人へ順次転送', price: '¥25/分', note: 'つながるまで順番に発信' },
];

export default function LandingPage() {
  return (
    <main className="min-h-screen bg-white">
      {/* Header */}
      <header className="border-b">
        <div className="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
          <div className="text-lg font-bold text-brand">AIオペレーター24 <span className="text-xs font-medium text-gray-500">for 自動車業界</span></div>
          <nav className="flex items-center gap-4 text-sm">
            <a href="#genres" className="hidden text-gray-600 hover:text-gray-900 sm:inline">対応ジャンル</a>
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
          <span className="inline-block rounded-full bg-brand/10 px-4 py-1 text-sm font-medium text-brand">自動車業界に特化したAI電話受付</span>
          <h1 className="mt-4 text-4xl font-bold leading-tight sm:text-5xl">
            商談中の1本の電話が、<br />
            <span className="text-brand">査定・車検・成約のチャンスに。</span>
          </h1>
          <p className="mx-auto mt-6 max-w-2xl text-lg text-gray-600">
            接客中・試乗対応中・整備作業中でも、AIが24時間お客様の電話に応答。
            買取査定・車検予約・在庫問い合わせ・見積り依頼を自然な日本語で聞き取り、要約して即通知します。
            <strong>自動車販売・買取・整備・板金</strong>それぞれの専用テンプレートで、すぐに使えます。
          </p>
          <div className="mt-8 flex flex-col justify-center gap-3 sm:flex-row">
            <Link href="/contact" className="rounded-lg bg-brand px-8 py-3 font-semibold text-white hover:bg-brand-dark">
              無料デモを試す
            </Link>
            <Link href="/contact" className="rounded-lg border border-gray-300 px-8 py-3 font-semibold text-gray-700 hover:bg-gray-50">
              資料請求する
            </Link>
          </div>
          <p className="mt-4 text-xs text-gray-400">※ 将来的に他業界向けも展開予定。まずは自動車業界に完全特化。</p>
        </div>
      </section>

      {/* 対応ジャンル（自動車業界） */}
      <section id="genres" className="bg-white">
        <div className="mx-auto max-w-6xl px-6 py-20">
          <h2 className="text-center text-3xl font-bold">自動車業界の、あらゆる現場に。</h2>
          <p className="mt-3 text-center text-gray-600">ジャンルごとに専用の受け答え・FAQ・オペレーションを用意。導入初日から自社仕様で動きます。</p>
          <div className="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {autoGenres.map((g) => (
              <div key={g.name} className="rounded-xl border p-6 text-center shadow-sm">
                <div className="text-4xl">{g.icon}</div>
                <h3 className="mt-3 text-lg font-bold">{g.name}</h3>
                <p className="mt-2 text-sm text-gray-600">{g.desc}</p>
              </div>
            ))}
          </div>
          <p className="mt-8 text-center text-sm text-gray-500">中古車・新車販売／買取／車検・整備／板金塗装／ロードサービス 等、自動車まわりの電話をまるごとAIに。</p>
        </div>
      </section>

      {/* Features */}
      <section id="features" className="mx-auto max-w-6xl px-6 py-20">
        <h2 className="text-center text-3xl font-bold">自動車業界の電話対応を、まるごとAIに。</h2>
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
                  ['月額の目安', '¥2,980〜', '¥0〜3,000〜', '¥5,000〜30,000', '¥100,000〜'],
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
          <p className="mt-3 text-center text-gray-600">安い月額基本料 ＋ <strong>着信1件・通話1分ごと</strong>の明快な従量課金。使った分だけなので無駄がありません。まずは1週間のテスト導入から。</p>
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

          {/* 機能別 比較表 */}
          <div className="mt-14">
            <h3 className="mb-4 text-center text-xl font-bold">機能別の対応表</h3>
            <div className="overflow-x-auto">
              <table className="mx-auto w-full max-w-3xl border-collapse text-sm">
                <thead>
                  <tr className="border-b">
                    <th className="px-3 py-3 text-left font-medium text-gray-600">機能</th>
                    <th className="px-3 py-3 text-center font-medium">受付</th>
                    <th className="bg-brand-light/40 px-3 py-3 text-center font-semibold text-brand">営業</th>
                    <th className="px-3 py-3 text-center font-medium">統合</th>
                  </tr>
                </thead>
                <tbody>
                  {[
                    ['AI電話受付・FAQ・要約・通知', true, true, true],
                    ['予約受付（査定・来店）', true, true, true],
                    ['人間へ転送', true, true, true],
                    ['連絡先CRM・一斉メール', false, true, true],
                    ['AI営業・自動架電', false, true, true],
                    ['発信者ルール・Slack通知・CSV', false, true, true],
                    ['Googleカレンダー連携（重複防止）', false, false, true],
                    ['複数電話番号・担当振り分け', false, false, true],
                    ['高度な分析・優先サポート', false, false, true],
                  ].map((row, idx) => (
                    <tr key={idx} className="border-b">
                      <td className="px-3 py-2.5 text-left text-gray-700">{row[0] as string}</td>
                      {[1, 2, 3].map((ci) => (
                        <td key={ci} className={`px-3 py-2.5 text-center ${ci === 2 ? 'bg-brand-light/20' : ''}`}>
                          {row[ci] ? <span className="text-brand">✓</span> : <span className="text-gray-300">—</span>}
                        </td>
                      ))}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>

          {/* 電話番号オプション */}
          <div className="mt-14">
            <h3 className="mb-2 text-center text-xl font-bold">電話番号オプション</h3>
            <p className="mb-4 text-center text-sm text-gray-500">番号の種類は選べます。市外局番・フリーダイヤルもご用意。</p>
            <div className="mx-auto grid max-w-3xl gap-3 sm:grid-cols-2 lg:grid-cols-4">
              {numberOptions.map((o) => (
                <div key={o.name} className="rounded-xl border bg-white p-4 text-center">
                  <div className="text-sm font-semibold">{o.name}</div>
                  <div className="mt-1 text-brand">{o.price}</div>
                  <div className="mt-1 text-xs text-gray-400">{o.note}</div>
                </div>
              ))}
            </div>
          </div>

          {/* 転送オプション */}
          <div className="mt-12">
            <h3 className="mb-2 text-center text-xl font-bold">転送オプション</h3>
            <p className="mb-4 text-center text-sm text-gray-500">転送先に応じて料金が変わります。担当営業・整備・板金へ、必要な人へつなぎます。</p>
            <div className="mx-auto grid max-w-2xl gap-3 sm:grid-cols-3">
              {transferOptions.map((o) => (
                <div key={o.name} className="rounded-xl border bg-white p-4 text-center">
                  <div className="text-sm font-semibold">{o.name}</div>
                  <div className="mt-1 text-brand">{o.price}</div>
                  <div className="mt-1 text-xs text-gray-400">{o.note}</div>
                </div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* CTA */}
      <section className="bg-brand">
        <div className="mx-auto max-w-4xl px-6 py-16 text-center text-white">
          <h2 className="text-3xl font-bold">まずは御社専用のAI電話番号で1週間テスト。</h2>
          <p className="mt-4 text-brand-light">取り逃していた査定・車検・見積りの電話を、そのまま売上に。自動車業界専用テンプレですぐ使えます。</p>
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
