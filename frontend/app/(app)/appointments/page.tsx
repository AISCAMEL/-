'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

// JSTで時刻・日付を表示
function jstTime(iso: string) { return new Date(iso).toLocaleTimeString('ja-JP', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit' }); }
function jstDateTime(iso: string) { return new Date(iso).toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo', month: 'numeric', day: 'numeric', weekday: 'short', hour: '2-digit', minute: '2-digit' }); }
function todayYmd() { return new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' }); } // YYYY-MM-DD
// JSTでの ymd と 小数時（9.5=9:30）。カレンダーの列判定・配置に使う。
function jstYmd(iso: string) { return new Date(iso).toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' }); }
function jstHourFloat(iso: string) {
  const s = new Date(iso).toLocaleTimeString('en-GB', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit', hour12: false });
  const [h, m] = s.split(':').map(Number); return h + m / 60;
}
// ymd を純粋なカレンダー日付として扱い、日数加算や曜日判定をタイムゾーンの影響なく行う。
function ymdAddDays(ymd: string, n: number) {
  const [y, m, d] = ymd.split('-').map(Number);
  const dt = new Date(Date.UTC(y, m - 1, d)); dt.setUTCDate(dt.getUTCDate() + n);
  return dt.toISOString().slice(0, 10);
}
function ymdWeekday(ymd: string) { const [y, m, d] = ymd.split('-').map(Number); return new Date(Date.UTC(y, m - 1, d)).getUTCDay(); } // 0=日
const WEEKDAY_JP = ['日', '月', '火', '水', '木', '金', '土'];
// カレンダー表示の営業時間帯（枠外の予約は端にクランプして表示）。
const CAL_START = 8, CAL_END = 20, CAL_ROW_H = 44;

const STATUS_LABEL: Record<string, string> = { tentative: '仮予約', confirmed: '確定', done: '完了', cancelled: '取消' };
const STATUS_COLOR: Record<string, string> = { tentative: 'bg-amber-100 text-amber-800', confirmed: 'bg-green-100 text-green-800', done: 'bg-gray-100 text-gray-500', cancelled: 'bg-red-100 text-red-600' };
const SOURCE_LABEL: Record<string, string> = { ai_inbound: 'AI着信', ai_outbound: 'AI架電', manual: '手動' };

export default function AppointmentsPage() {
  const [list, setList] = useState<any[]>([]);
  const [status, setStatus] = useState<any>(null);
  const [date, setDate] = useState(todayYmd());
  const [slots, setSlots] = useState<{ start: string; end: string }[] | null>(null);
  const [picked, setPicked] = useState<{ start: string; end: string } | null>(null);
  const [form, setForm] = useState({ type: '査定', customer_name: '', phone_number: '', note: '' });
  const [msg, setMsg] = useState('');
  const [cfgOpen, setCfgOpen] = useState(false);
  const [cfg, setCfg] = useState({ google_calendar_id: '', google_refresh_token: '', appointment_duration_min: 45 });
  const [autoForm, setAutoForm] = useState({ customer_name: '', phone_number: '', type: '出張査定', date: '', preferredHHMM: '' });
  const [autoMsg, setAutoMsg] = useState('');
  const [view, setView] = useState<'calendar' | 'list'>('calendar');
  const [weekOffset, setWeekOffset] = useState(0); // 0=今週

  async function runAutoBook(e: React.FormEvent) {
    e.preventDefault();
    setAutoMsg('空き枠を探して予約中…');
    try {
      const r = await api.autoBookAppointment({ ...autoForm, date: autoForm.date || undefined, preferredHHMM: autoForm.preferredHHMM || undefined });
      const meet = r.meet_url ? `／Google Meet発行：${r.meet_url}` : '';
      setAutoMsg(`✅ ${jstDateTime(r.slot.start)}〜${jstTime(r.slot.end)} に「${autoForm.type}」を仮予約しました（${r.google_synced ? 'Googleにも登録' : 'カレンダー未接続'}）${meet}。`);
      setAutoForm({ ...autoForm, customer_name: '', phone_number: '' });
      loadList();
    } catch (err: any) {
      const m = String(err);
      setAutoMsg(m.includes('409') ? '希望日の周辺に空き枠がありませんでした。別の日を指定してください。' : `エラー: ${err.message ?? err}`);
    }
  }

  function loadList() { api.appointments().then(setList); }
  function loadStatus() { api.calendarStatus().then((s) => { setStatus(s); setCfg((c) => ({ ...c, appointment_duration_min: s.appointment_duration_min, google_calendar_id: s.calendar_id || '' })); }); }

  // 「Googleで連携」ボタン：同意画面URLを取得して遷移
  async function connectGoogle() {
    setMsg('Googleの認証ページへ移動します…');
    try {
      const { url } = await api.calendarOauthStart();
      window.location.href = url;
    } catch (err: any) {
      const m = String(err?.message ?? err);
      setMsg(m.includes('GOOGLE_CLIENT') ? 'サーバーにGoogle連携の設定（GOOGLE_CLIENT_ID/SECRET）がありません。運営側の設定が必要です。' : `エラー: ${m}`);
      setCfgOpen(true);
    }
  }

  // Googleコールバックからの戻り（?google=connected など）を表示
  useEffect(() => {
    const g = new URLSearchParams(window.location.search).get('google');
    if (!g) return;
    const M: Record<string, string> = {
      connected: '✅ Googleカレンダーに接続しました。', denied: '連携がキャンセルされました。',
      expired: '認証の有効期限が切れました。もう一度お試しください。', notoken: '再連携が必要です（Googleの権限画面で「許可」してください）。',
      invalid: '連携に失敗しました（不正なリクエスト）。', error: '連携に失敗しました。',
    };
    setMsg(M[g] ?? '連携結果を確認してください。');
    window.history.replaceState({}, '', '/appointments'); // クエリを消す
    loadStatus();
  }, []);
  const [types, setTypes] = useState<string[]>(['出張査定', '持込査定', 'オンライン査定', '来店商談', '電話商談']);
  useEffect(() => {
    loadList(); loadStatus();
    // 対応タイプはAI設定（reception_types）から取得。業種ごとに設定可能。
    api.aiSettings().then((s: any) => {
      const raw = s?.reception_types;
      const list = typeof raw === 'string' ? raw.split(/[,、\n]/).map((x: string) => x.trim()).filter(Boolean) : [];
      if (list.length) { setTypes(list); setAutoForm((f) => ({ ...f, type: list[0] })); setForm((f) => ({ ...f, type: list[0] })); }
    }).catch(() => {});
  }, []);

  async function findSlots() {
    setMsg(''); setPicked(null);
    const r = await api.appointmentSlots(date);
    setSlots(r.slots);
  }
  async function book(e: React.FormEvent) {
    e.preventDefault();
    if (!picked) return;
    try {
      const r = await api.createAppointment({ ...form, start_at: picked.start, end_at: picked.end, source: 'manual', title: `${form.type}：${form.customer_name}` });
      setMsg(`予約しました（${jstDateTime(picked.start)}）${r.google_synced ? '／Googleカレンダーにも登録' : ''}`);
      setPicked(null); setSlots(null); setForm({ type: '査定', customer_name: '', phone_number: '', note: '' });
      loadList();
    } catch (err: any) {
      setMsg(String(err).includes('409') ? 'その枠は直前に埋まりました。別の枠を選んでください。' : `エラー: ${err.message ?? err}`);
      findSlots();
    }
  }
  async function changeStatus(id: string, st: string) {
    if (st === 'cancelled' && !confirm('この予約を取り消しますか？（Google連携時はカレンダーからも削除します）')) return;
    await api.setAppointmentStatus(id, st); loadList();
  }
  async function saveCfg(e: React.FormEvent) {
    e.preventDefault();
    const s = await api.saveCalendarSettings(cfg);
    setStatus(s); setMsg(s.google_connected ? 'Googleカレンダーに接続しました。' : '設定を保存しました（未接続：calendar_id と refresh_token が必要です）。');
  }

  return (
    <div>
      <PageTitle title="予約（査定・来店）" sub="AIが取った希望と既存予約・Googleカレンダーを突き合わせ、重複しない枠だけに予約します。" />

      {/* Google接続バナー */}
      <div className={`mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border px-4 py-3 text-sm ${status?.google_connected ? 'border-green-300 bg-green-50' : 'border-amber-300 bg-amber-50'}`}>
        <span>
          {status?.google_connected
            ? `✅ Googleカレンダー連携中（${status.calendar_id}）。スタッフの予定も避けて予約します。`
            : '⚠️ Googleカレンダー未連携。いまは「店内の予約どうし」の重複のみ防止します。スタッフ個人の予定も避けるには連携してください。'}
        </span>
        <div className="flex gap-2">
          {!status?.google_connected && (
            <button onClick={connectGoogle} className="rounded-lg bg-brand px-3 py-1.5 font-medium text-white hover:bg-brand-dark">Googleアカウントで連携</button>
          )}
          <button onClick={() => setCfgOpen((v) => !v)} className="rounded-lg border bg-white px-3 py-1.5 hover:bg-gray-50">{cfgOpen ? '閉じる' : '詳細設定'}</button>
        </div>
      </div>
      {msg && <p className="mb-3 text-sm text-brand">{msg}</p>}

      {cfgOpen && (
        <Card className="mb-4">
          <h2 className="mb-2 text-sm font-semibold text-gray-500">Googleカレンダー連携</h2>

          {/* 推奨：ワンクリック連携 */}
          <div className="mb-4 rounded-lg border border-brand/30 bg-brand-light/40 p-3">
            <p className="mb-2 text-sm text-gray-700">
              {status?.google_connected
                ? `✅ 連携済み（${status.calendar_id}）。連携し直す場合は下のボタンから。`
                : 'ボタンを押してGoogleでログイン・許可するだけで連携できます（推奨）。'}
            </p>
            <button onClick={connectGoogle} className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">
              {status?.google_connected ? 'Googleアカウントを連携し直す' : 'Googleアカウントで連携する'}
            </button>
            <p className="mt-2 text-xs text-gray-400">※ 運営側で GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET の設定が必要です（docs/google-calendar-setup.md）。</p>
          </div>

          <details>
            <summary className="cursor-pointer text-xs text-gray-500">手動で入力する（上のボタンが使えない場合）</summary>
          <form onSubmit={saveCfg} className="mt-2 space-y-2">
            <input value={cfg.google_calendar_id} onChange={(e) => setCfg({ ...cfg, google_calendar_id: e.target.value })} placeholder="カレンダーID（例：your@gmail.com）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <input value={cfg.google_refresh_token} onChange={(e) => setCfg({ ...cfg, google_refresh_token: e.target.value })} placeholder="リフレッシュトークン（docs/google-calendar-setup.md 参照）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <label className="flex items-center gap-2 text-sm text-gray-600">1件あたりの所要時間
              <input type="number" min={15} step={15} value={cfg.appointment_duration_min} onChange={(e) => setCfg({ ...cfg, appointment_duration_min: Number(e.target.value) })} className="w-20 rounded-lg border px-2 py-1" />分
            </label>
            <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">保存</button>
          </form>
          </details>
          <p className="mt-2 text-xs text-gray-400">※ 未接続でも店内予約どうしの重複防止は動きます。スタッフ個人の予定も避けたいときに連携してください。</p>
        </Card>
      )}

      {/* AIで自動予約（最短の空き枠） */}
      <Card className="mb-6 border-brand/40">
        <h2 className="mb-1 text-sm font-semibold text-brand">🤖 AIで自動予約（最短の空き枠に仮予約）</h2>
        <p className="mb-3 text-xs text-gray-500">AIが通話中に使うのと同じ仕組み。希望日を指定すると、Googleカレンダーと既存予約を避けて最短の空き枠に仮予約します。遠方はオンライン査定を選択。</p>
        <form onSubmit={runAutoBook} className="flex flex-wrap items-end gap-2">
          <input value={autoForm.customer_name} onChange={(e) => setAutoForm({ ...autoForm, customer_name: e.target.value })} placeholder="顧客名" className="rounded-lg border px-3 py-2 text-sm" />
          <input value={autoForm.phone_number} onChange={(e) => setAutoForm({ ...autoForm, phone_number: e.target.value })} placeholder="電話番号" className="rounded-lg border px-3 py-2 text-sm" />
          <select value={autoForm.type} onChange={(e) => setAutoForm({ ...autoForm, type: e.target.value })} className="rounded-lg border px-3 py-2 text-sm">
            {types.map((t) => <option key={t}>{t}</option>)}
          </select>
          <label className="text-xs text-gray-500">希望日<input type="date" value={autoForm.date} min={todayYmd()} onChange={(e) => setAutoForm({ ...autoForm, date: e.target.value })} className="ml-1 rounded-lg border px-2 py-2 text-sm" /></label>
          <label className="text-xs text-gray-500">希望時刻<input type="time" value={autoForm.preferredHHMM} onChange={(e) => setAutoForm({ ...autoForm, preferredHHMM: e.target.value })} className="ml-1 rounded-lg border px-2 py-2 text-sm" /></label>
          <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">AIで最短枠に予約</button>
        </form>
        {autoMsg && <p className="mt-2 text-sm text-brand">{autoMsg}</p>}
      </Card>

      {/* 空き枠を探して予約 */}
      <Card className="mb-6">
        <h2 className="mb-3 text-sm font-semibold text-gray-500">空き枠を探して予約（手動）</h2>
        <div className="flex flex-wrap items-end gap-2">
          <label className="text-sm text-gray-600">希望日
            <input type="date" value={date} min={todayYmd()} onChange={(e) => setDate(e.target.value)} className="ml-2 rounded-lg border px-3 py-2 text-sm" />
          </label>
          <button onClick={findSlots} className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">空き枠を表示</button>
        </div>

        {slots && (
          <div className="mt-4">
            {slots.length === 0 ? (
              <p className="text-sm text-gray-400">この日の空き枠はありません（休業日・営業時間外・すべて予約済み）。</p>
            ) : (
              <div className="flex flex-wrap gap-2">
                {slots.map((s) => (
                  <button key={s.start} onClick={() => setPicked(s)}
                    className={`rounded-lg border px-3 py-1.5 text-sm ${picked?.start === s.start ? 'border-brand bg-brand text-white' : 'hover:bg-gray-50'}`}>
                    {jstTime(s.start)}〜{jstTime(s.end)}
                  </button>
                ))}
              </div>
            )}
          </div>
        )}

        {picked && (
          <form onSubmit={book} className="mt-4 space-y-2 rounded-lg border bg-gray-50 p-3">
            <div className="text-sm font-medium">{jstDateTime(picked.start)}〜{jstTime(picked.end)} に予約</div>
            <div className="flex flex-wrap gap-2">
              <select value={form.type} onChange={(e) => setForm({ ...form, type: e.target.value })} className="rounded-lg border px-3 py-2 text-sm">
                {types.map((t) => <option key={t}>{t}</option>)}
              </select>
              <input value={form.customer_name} onChange={(e) => setForm({ ...form, customer_name: e.target.value })} placeholder="顧客名" className="flex-1 rounded-lg border px-3 py-2 text-sm" />
              <input value={form.phone_number} onChange={(e) => setForm({ ...form, phone_number: e.target.value })} placeholder="電話番号" className="rounded-lg border px-3 py-2 text-sm" />
            </div>
            <input value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} placeholder="メモ（車種・年式など）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">この枠で予約する</button>
          </form>
        )}
      </Card>

      {/* 予約（カレンダー / 一覧 切替） */}
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-lg font-semibold">予約</h2>
        <div className="inline-flex overflow-hidden rounded-lg border text-sm">
          <button onClick={() => setView('calendar')} className={`px-3 py-1.5 ${view === 'calendar' ? 'bg-brand text-white' : 'bg-white hover:bg-gray-50'}`}>📅 カレンダー</button>
          <button onClick={() => setView('list')} className={`border-l px-3 py-1.5 ${view === 'list' ? 'bg-brand text-white' : 'bg-white hover:bg-gray-50'}`}>☰ 一覧</button>
        </div>
      </div>

      {view === 'calendar' && (
        <WeekCalendar list={list} weekOffset={weekOffset} setWeekOffset={setWeekOffset} />
      )}

      {view === 'list' && (
      <Card className="p-0">
        <table className="w-full text-sm">
          <thead className="border-b text-left text-xs text-gray-500">
            <tr><th className="px-4 py-3">日時</th><th className="px-4 py-3">種別 / 顧客</th><th className="px-4 py-3">経路</th><th className="px-4 py-3">状態</th><th className="px-4 py-3"></th></tr>
          </thead>
          <tbody>
            {list.length === 0 && <tr><td colSpan={5} className="px-4 py-8 text-center text-gray-400">予約はありません。</td></tr>}
            {list.map((a) => (
              <tr key={a.id} className="border-b last:border-0 align-top">
                <td className="px-4 py-3 text-gray-700">{jstDateTime(a.start_at)}<div className="text-xs text-gray-400">〜{jstTime(a.end_at)}</div></td>
                <td className="px-4 py-3">{a.type}<div className="text-xs text-gray-500">{a.customer_name ?? '—'}{a.phone_number ? ` / ${a.phone_number}` : ''}</div>{a.note && <div className="text-xs text-gray-400">{a.note}</div>}{a.meet_url && <a href={a.meet_url} target="_blank" rel="noreferrer" className="mt-1 inline-block rounded bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700 hover:bg-green-100">🎥 ビデオ通話に参加</a>}</td>
                <td className="px-4 py-3 text-xs text-gray-500">{SOURCE_LABEL[a.source] ?? a.source}</td>
                <td className="px-4 py-3"><span className={`rounded-full px-2 py-0.5 text-xs ${STATUS_COLOR[a.status] ?? 'bg-gray-100'}`}>{STATUS_LABEL[a.status] ?? a.status}</span></td>
                <td className="px-4 py-3 text-right">
                  {a.status === 'tentative' && <button onClick={() => changeStatus(a.id, 'confirmed')} className="text-green-600 hover:underline">確定</button>}
                  {a.status !== 'done' && a.status !== 'cancelled' && <button onClick={() => changeStatus(a.id, 'done')} className="ml-3 text-gray-600 hover:underline">完了</button>}
                  {a.status !== 'cancelled' && <button onClick={() => changeStatus(a.id, 'cancelled')} className="ml-3 text-red-500 hover:underline">取消</button>}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
      )}
    </div>
  );
}

// 週カレンダー（JST・8:00–20:00）。予約を日×時刻のブロックで表示。重なりは横に分割。
function WeekCalendar({ list, weekOffset, setWeekOffset }: { list: any[]; weekOffset: number; setWeekOffset: (n: number) => void }) {
  const today = todayYmd();
  const baseSunday = ymdAddDays(today, -ymdWeekday(today)); // 今週の日曜
  const weekStart = ymdAddDays(baseSunday, weekOffset * 7);
  const days = Array.from({ length: 7 }, (_, i) => ymdAddDays(weekStart, i));
  const hours = Array.from({ length: CAL_END - CAL_START }, (_, i) => CAL_START + i);
  const gridH = (CAL_END - CAL_START) * CAL_ROW_H;

  // 日ごとに予約をまとめ、重なりをレーン分割
  function dayLayout(ymd: string) {
    const items = list
      .filter((a) => jstYmd(a.start_at) === ymd)
      .sort((a, b) => a.start_at.localeCompare(b.start_at));
    const laneEnds: number[] = [];
    const placed = items.map((a) => {
      const s = jstHourFloat(a.start_at), e = Math.max(jstHourFloat(a.end_at), s + 0.25);
      let lane = laneEnds.findIndex((end) => end <= s + 0.001);
      if (lane === -1) { lane = laneEnds.length; laneEnds.push(e); } else laneEnds[lane] = e;
      return { a, s, e, lane };
    });
    return { placed, lanes: Math.max(1, laneEnds.length) };
  }

  const fmtRange = () => {
    const end = ymdAddDays(weekStart, 6);
    const f = (y: string) => { const [, m, d] = y.split('-'); return `${Number(m)}/${Number(d)}`; };
    return `${f(weekStart)} 〜 ${f(end)}`;
  };

  return (
    <Card className="p-0">
      {/* 週ナビ */}
      <div className="flex items-center justify-between border-b px-4 py-2 text-sm">
        <div className="flex gap-1">
          <button onClick={() => setWeekOffset(weekOffset - 1)} className="rounded-lg border px-2 py-1 hover:bg-gray-50">‹ 前週</button>
          <button onClick={() => setWeekOffset(0)} className={`rounded-lg border px-3 py-1 ${weekOffset === 0 ? 'bg-brand-light/50 text-brand' : 'hover:bg-gray-50'}`}>今週</button>
          <button onClick={() => setWeekOffset(weekOffset + 1)} className="rounded-lg border px-2 py-1 hover:bg-gray-50">翌週 ›</button>
        </div>
        <div className="font-medium text-gray-600">{fmtRange()}</div>
      </div>

      {/* ヘッダ：曜日 */}
      <div className="grid border-b" style={{ gridTemplateColumns: `48px repeat(7, 1fr)` }}>
        <div />
        {days.map((ymd) => {
          const wd = ymdWeekday(ymd); const isToday = ymd === today;
          const [, , d] = ymd.split('-');
          return (
            <div key={ymd} className={`border-l py-1.5 text-center text-xs ${isToday ? 'bg-brand-light/40' : ''}`}>
              <div className={wd === 0 ? 'text-red-500' : wd === 6 ? 'text-blue-500' : 'text-gray-500'}>{WEEKDAY_JP[wd]}</div>
              <div className={`text-sm font-semibold ${isToday ? 'text-brand' : 'text-gray-700'}`}>{Number(d)}</div>
            </div>
          );
        })}
      </div>

      {/* 本体：時刻 × 7日 */}
      <div className="grid overflow-hidden" style={{ gridTemplateColumns: `48px repeat(7, 1fr)` }}>
        {/* 時刻ラベル */}
        <div className="relative" style={{ height: gridH }}>
          {hours.map((h, i) => (
            <div key={h} className="absolute right-1 -translate-y-1/2 text-[10px] text-gray-400" style={{ top: i * CAL_ROW_H }}>{h}:00</div>
          ))}
        </div>
        {/* 各日の列 */}
        {days.map((ymd) => {
          const { placed, lanes } = dayLayout(ymd);
          const isToday = ymd === today;
          return (
            <div key={ymd} className={`relative border-l ${isToday ? 'bg-brand-light/10' : ''}`} style={{ height: gridH }}>
              {/* 時間の横線 */}
              {hours.map((h, i) => (
                <div key={h} className="absolute inset-x-0 border-t border-gray-100" style={{ top: i * CAL_ROW_H }} />
              ))}
              {/* 予約ブロック */}
              {placed.map(({ a, s, e, lane }) => {
                const top = (Math.max(s, CAL_START) - CAL_START) * CAL_ROW_H;
                const bottom = (Math.min(e, CAL_END) - CAL_START) * CAL_ROW_H;
                const h = Math.max(18, bottom - top);
                const w = 100 / lanes;
                const cls = CAL_BLOCK[a.status] ?? 'border-gray-400 bg-gray-50 text-gray-700';
                return (
                  <div key={a.id} title={`${jstTime(a.start_at)}〜${jstTime(a.end_at)} ${a.type}／${a.customer_name ?? ''}${a.note ? `\n${a.note}` : ''}`}
                    className={`absolute overflow-hidden rounded border-l-2 px-1 py-0.5 text-[10px] leading-tight ${cls}`}
                    style={{ top: top + 1, height: h - 2, left: `calc(${lane * w}% + 1px)`, width: `calc(${w}% - 2px)` }}>
                    <div className="font-semibold">{jstTime(a.start_at)} {a.customer_name ?? ''}</div>
                    <div className="truncate opacity-80">{a.type}{a.meet_url ? ' 🎥' : ''}</div>
                  </div>
                );
              })}
            </div>
          );
        })}
      </div>

      {/* 凡例 */}
      <div className="flex flex-wrap gap-3 border-t px-4 py-2 text-[11px] text-gray-500">
        <span className="flex items-center gap-1"><i className="inline-block h-3 w-3 rounded-sm border-l-2 border-green-500 bg-green-50" />確定</span>
        <span className="flex items-center gap-1"><i className="inline-block h-3 w-3 rounded-sm border-l-2 border-amber-500 bg-amber-50" />仮予約</span>
        <span className="flex items-center gap-1"><i className="inline-block h-3 w-3 rounded-sm border-l-2 border-gray-400 bg-gray-100" />完了</span>
        <span className="ml-auto">🎥＝オンライン</span>
      </div>
    </Card>
  );
}
const CAL_BLOCK: Record<string, string> = {
  confirmed: 'border-green-500 bg-green-50 text-green-800',
  tentative: 'border-amber-500 bg-amber-50 text-amber-800',
  done: 'border-gray-400 bg-gray-100 text-gray-500',
  cancelled: 'border-red-400 bg-red-50 text-red-600',
};
