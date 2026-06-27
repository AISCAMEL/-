'use client';

import { useEffect, useState } from 'react';
import { api } from '@/lib/api';
import { Card, PageTitle } from '@/components/ui';

// JSTで時刻・日付を表示
function jstTime(iso: string) { return new Date(iso).toLocaleTimeString('ja-JP', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit' }); }
function jstDateTime(iso: string) { return new Date(iso).toLocaleString('ja-JP', { timeZone: 'Asia/Tokyo', month: 'numeric', day: 'numeric', weekday: 'short', hour: '2-digit', minute: '2-digit' }); }
function todayYmd() { return new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' }); } // YYYY-MM-DD

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

  function loadList() { api.appointments().then(setList); }
  function loadStatus() { api.calendarStatus().then((s) => { setStatus(s); setCfg((c) => ({ ...c, appointment_duration_min: s.appointment_duration_min, google_calendar_id: s.calendar_id || '' })); }); }
  useEffect(() => { loadList(); loadStatus(); }, []);

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
        <button onClick={() => setCfgOpen((v) => !v)} className="rounded-lg border bg-white px-3 py-1.5 hover:bg-gray-50">{cfgOpen ? '閉じる' : 'Google連携設定'}</button>
      </div>
      {msg && <p className="mb-3 text-sm text-brand">{msg}</p>}

      {cfgOpen && (
        <Card className="mb-4">
          <h2 className="mb-2 text-sm font-semibold text-gray-500">Googleカレンダー連携</h2>
          <form onSubmit={saveCfg} className="space-y-2">
            <input value={cfg.google_calendar_id} onChange={(e) => setCfg({ ...cfg, google_calendar_id: e.target.value })} placeholder="カレンダーID（例：your@gmail.com）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <input value={cfg.google_refresh_token} onChange={(e) => setCfg({ ...cfg, google_refresh_token: e.target.value })} placeholder="リフレッシュトークン（docs/google-calendar-setup.md 参照）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <label className="flex items-center gap-2 text-sm text-gray-600">1件あたりの所要時間
              <input type="number" min={15} step={15} value={cfg.appointment_duration_min} onChange={(e) => setCfg({ ...cfg, appointment_duration_min: Number(e.target.value) })} className="w-20 rounded-lg border px-2 py-1" />分
            </label>
            <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">保存</button>
          </form>
          <p className="mt-2 text-xs text-gray-400">※ 接続手順は docs/google-calendar-setup.md。未接続でも店内予約の重複防止は動きます。</p>
        </Card>
      )}

      {/* 空き枠を探して予約 */}
      <Card className="mb-6">
        <h2 className="mb-3 text-sm font-semibold text-gray-500">空き枠を探して予約</h2>
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
                {['査定', '来店', '内見', '施術', '相談'].map((t) => <option key={t}>{t}</option>)}
              </select>
              <input value={form.customer_name} onChange={(e) => setForm({ ...form, customer_name: e.target.value })} placeholder="顧客名" className="flex-1 rounded-lg border px-3 py-2 text-sm" />
              <input value={form.phone_number} onChange={(e) => setForm({ ...form, phone_number: e.target.value })} placeholder="電話番号" className="rounded-lg border px-3 py-2 text-sm" />
            </div>
            <input value={form.note} onChange={(e) => setForm({ ...form, note: e.target.value })} placeholder="メモ（車種・年式など）" className="w-full rounded-lg border px-3 py-2 text-sm" />
            <button className="rounded-lg bg-brand px-4 py-2 text-sm font-medium text-white hover:bg-brand-dark">この枠で予約する</button>
          </form>
        )}
      </Card>

      {/* 予約一覧 */}
      <h2 className="mb-3 text-lg font-semibold">予約一覧</h2>
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
                <td className="px-4 py-3">{a.type}<div className="text-xs text-gray-500">{a.customer_name ?? '—'}{a.phone_number ? ` / ${a.phone_number}` : ''}</div>{a.note && <div className="text-xs text-gray-400">{a.note}</div>}</td>
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
    </div>
  );
}
