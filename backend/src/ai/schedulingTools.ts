// 通話中のAIが使う「スケジューリング用ツール」。
// 空き枠の確認(find_available_slots)と仮予約(book_appointment)を提供する。
// 実データは Googleカレンダー＋内部予約（calendar/index.ts）から取得する。
import type { ToolSpec, ToolRunner } from './llm.js';
import { findSlots, book, calendarStatus } from '../calendar/index.js';
import { jstWallToDate, type Slot } from '../calendar/slots.js';

const WD = ['日', '月', '火', '水', '木', '金', '土'];

// JSTの YYYY-MM-DD（今日）。モデルが相対日付を絶対日付へ変換する基準に使う。
export function todayYmdJst(): string {
  return new Date().toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' });
}

function ymdPlusDays(ymd: string, days: number): string {
  const base = new Date(`${ymd}T00:00:00+09:00`);
  return new Date(base.getTime() + days * 24 * 3600_000).toLocaleDateString('sv-SE', { timeZone: 'Asia/Tokyo' });
}

// ISO(UTC) → 「11月20日(木) 14:00」形式（JST）。
function labelJst(iso: string): string {
  const d = new Date(iso);
  const p = new Intl.DateTimeFormat('ja-JP', {
    timeZone: 'Asia/Tokyo', month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false,
  }).formatToParts(d);
  const get = (t: string) => p.find((x) => x.type === t)?.value ?? '';
  // 曜日はJSTで算出（UTC+9してからgetUTCDay）。
  const jstWd = new Date(d.getTime() + 9 * 3600_000).getUTCDay();
  return `${get('month')}月${get('day')}日(${WD[jstWd]}) ${get('hour')}:${get('minute')}`;
}

// ISO(UTC) → JSTの "HH:MM"
function hhmmJst(iso: string): string {
  const d = new Date(iso);
  const p = new Intl.DateTimeFormat('ja-JP', { timeZone: 'Asia/Tokyo', hour: '2-digit', minute: '2-digit', hour12: false })
    .formatToParts(d);
  const get = (t: string) => p.find((x) => x.type === t)?.value ?? '';
  return `${get('hour')}:${get('minute')}`;
}

// AIに渡すツールの定義。
export const schedulingTools: ToolSpec[] = [
  {
    name: 'find_available_slots',
    description:
      '出張査定・オンライン査定などの「空いている日時」を調べる。希望日や時間帯があれば指定する。' +
      '結果の候補をお客様に2〜3件、口頭で提案すること。日付は必ず YYYY-MM-DD（例 2026-11-20）で渡す。',
    input_schema: {
      type: 'object',
      properties: {
        date: { type: 'string', description: '希望日 YYYY-MM-DD。無指定なら直近の空きを探す。' },
        preferred_time: { type: 'string', description: '希望時刻 HH:MM（任意）。近い枠を優先する。' },
      },
    },
  },
  {
    name: 'book_appointment',
    description:
      'お客様と合意した日時で「仮予約」を取る。find_available_slots で提示した候補の中からお客様が選んだ枠にのみ使う。' +
      '予約後は必ず口頭で日時・お名前を復唱して確認すること。',
    input_schema: {
      type: 'object',
      properties: {
        date: { type: 'string', description: '予約日 YYYY-MM-DD' },
        time: { type: 'string', description: '予約時刻 HH:MM（24時間制, JST）' },
        type: { type: 'string', description: '査定種別（例: 出張査定 / オンライン査定）' },
        customer_name: { type: 'string', description: 'お客様のお名前' },
        phone: { type: 'string', description: '連絡先電話番号' },
        note: { type: 'string', description: '車種・住所・要望などのメモ' },
      },
      required: ['date', 'time'],
    },
  },
];

/** tenant・通話に紐づくツール実行関数を作る。 */
export function makeSchedulingRunner(tenantId: string, callId: string | null): ToolRunner {
  return async (name, input) => {
    if (name === 'find_available_slots') {
      const start = typeof input?.date === 'string' && input.date ? input.date : ymdPlusDays(todayYmdJst(), 1);
      const within = 14;
      const max = 4;
      const out: { date: string; time: string; label: string }[] = [];
      for (let i = 0; i <= within && out.length < max; i++) {
        const ymd = ymdPlusDays(start, i);
        let slots: Slot[];
        try { slots = await findSlots(tenantId, ymd); }
        catch { slots = []; }
        // 1日あたり最大2件まで提示（朝/午後が散るように先頭と中間）。
        const picks = slots.length <= 2 ? slots : [slots[0], slots[Math.floor(slots.length / 2)]];
        for (const s of picks) {
          if (out.length >= max) break;
          out.push({ date: ymd, time: hhmmJst(s.start), label: labelJst(s.start) });
        }
      }
      if (out.length === 0) {
        return { slots: [], message: '直近に空き枠が見つかりませんでした。担当者から折り返し日程調整する案内をしてください。' };
      }
      return { slots: out };
    }

    if (name === 'book_appointment') {
      const date = String(input?.date ?? '');
      const time = String(input?.time ?? '');
      if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !/^\d{1,2}:\d{2}$/.test(time)) {
        return { booked: false, reason: 'invalid_datetime', message: '日付か時刻の形式が不正です。' };
      }
      let durationMin = 45;
      try { durationMin = (await calendarStatus(tenantId)).appointment_duration_min || 45; } catch { /* default */ }
      const startD = jstWallToDate(date, time);
      const endD = new Date(startD.getTime() + durationMin * 60_000);
      const r = await book(tenantId, {
        type: String(input?.type ?? '出張査定'),
        title: `${input?.type ?? '出張査定'}：${input?.customer_name ?? ''}`.trim(),
        customer_name: input?.customer_name ?? null,
        phone_number: input?.phone ?? null,
        note: input?.note ?? null,
        call_id: callId,
        source: 'ai_inbound',
        status: 'tentative',
        start_at: startD.toISOString(),
        end_at: endD.toISOString(),
      });
      if (!r.ok) {
        return { booked: false, reason: r.conflict ? 'conflict' : 'error', message: r.error ?? '予約できませんでした。別の候補を提案してください。' };
      }
      return {
        booked: true,
        when: labelJst(startD.toISOString()),
        google_synced: Boolean(r.google_synced),
        note: '仮予約として登録しました。担当者が確認のうえ最終確定します。',
      };
    }

    return { error: `unknown tool: ${name}` };
  };
}
