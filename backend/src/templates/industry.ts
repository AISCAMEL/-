// 業種別テンプレート。選ぶだけで 挨拶文・FAQ・架電シナリオ を一括投入できる。
export interface IndustryTemplate {
  key: string;
  label: string;
  summary: string;
  industry: string;
  greeting: string;
  ai_tone: string;
  faqs: { question: string; answer: string; category: string }[];
  campaigns: { name: string; purpose: string; opening: string; goal_prompt: string }[];
}

export const INDUSTRY_TEMPLATES: IndustryTemplate[] = [
  {
    key: 'car_dealer',
    label: '自動車販売店',
    summary: '車検案内・買取査定・在庫問い合わせ・来店後の後追いに対応',
    industry: '中古車販売',
    greeting: 'お電話ありがとうございます。AI受付です。ご用件をお話しください。',
    ai_tone: 'polite',
    faqs: [
      { question: '営業時間を教えてください', answer: '営業時間は10時から19時までです。定休日は水曜日です。', category: '営業案内' },
      { question: '車検の予約はできますか', answer: '車検のご予約を承ります。ご希望の日時とお車の車種を伺い、担当者よりご案内します。', category: '車検' },
      { question: '買取査定をお願いしたい', answer: '無料査定を承っております。お車の車種・年式・走行距離をお伺いし、担当者より査定のご案内をいたします。', category: '買取' },
      { question: '在庫について聞きたい', answer: 'ご希望の車種・予算をお伺いし、担当者より在庫状況をご案内します。', category: '在庫' },
      { question: '試乗はできますか', answer: '試乗のご予約を承ります。ご希望の車種と日時を担当者よりご案内します。', category: '試乗' },
      { question: 'ローンの相談はできますか', answer: 'オートローンのご相談を承ります。詳細は担当者よりご案内いたします（審査・条件の確約はできかねます）。', category: 'ローン' },
    ],
    campaigns: [
      { name: '車検時期のご案内', purpose: 'followup', opening: 'お世話になっております。AI担当です。お車の車検時期のご案内でお電話しました。少しお時間よろしいでしょうか？', goal_prompt: '車検の時期が近いお客様へ点検・車検をご案内し、ご希望があれば予約日時を伺う、または担当者へおつなぎする。' },
      { name: '買取査定のご提案', purpose: 'sales', opening: 'お世話になっております。AI担当です。お車の無料査定のご案内でお電話しました。', goal_prompt: '乗り換え・買取に関心がある方へ無料査定をご提案し、希望者は車種・年式を伺い担当者へつなぐ。' },
      { name: '来店後の後追い', purpose: 'followup', opening: '先日はご来店ありがとうございました。AI担当です。その後のご検討状況の確認でお電話しました。', goal_prompt: '来店・見積もり後のお客様へ検討状況を伺い、疑問点があれば担当者へつなぐ。しつこくしない。' },
    ],
  },
  {
    key: 'salon',
    label: '美容室・サロン',
    summary: '予約受付・メニュー料金・キャンセル対応・再来店フォロー',
    industry: '美容室',
    greeting: 'お電話ありがとうございます。AI受付です。ご予約やお問い合わせを承ります。',
    ai_tone: 'friendly',
    faqs: [
      { question: '営業時間を教えてください', answer: '営業時間は10時から18時までです。土日祝もご利用いただけます。', category: '営業案内' },
      { question: 'カットの料金はいくらですか', answer: 'カットは4,400円からです。メニューにより異なりますので、詳細は担当者よりご案内します。', category: '料金' },
      { question: '予約を変更・キャンセルしたい', answer: 'ご予約の変更・キャンセルを承ります。お名前とご予約日時を伺います。', category: '予約' },
      { question: '駐車場はありますか', answer: '近隣のコインパーキングをご利用ください。提携駐車場はございません。', category: '営業案内' },
      { question: '指名はできますか', answer: 'スタッフのご指名を承ります。ご希望のスタッフ名をお伝えください。', category: '予約' },
      { question: 'カードは使えますか', answer: '各種クレジットカードがご利用いただけます。', category: '支払い' },
    ],
    campaigns: [
      { name: '再来店のご案内', purpose: 'followup', opening: 'いつもありがとうございます。AI担当です。次回のご来店のご案内でお電話しました。', goal_prompt: '前回来店から時間が経ったお客様へ次回予約をご案内し、希望日時を伺う。' },
      { name: 'キャンセル枠のご案内', purpose: 'followup', opening: 'お世話になっております。AI担当です。ご希望に近い空き枠が出ましたのでご案内のお電話です。', goal_prompt: 'キャンセル待ち・予約希望の方へ空き枠をご案内し、希望があれば予約を承る。' },
    ],
  },
  {
    key: 'restaurant',
    label: '飲食店',
    summary: '席の予約・コース/個室・テイクアウト・宴会シーズン案内',
    industry: '飲食店',
    greeting: 'お電話ありがとうございます。AI受付です。ご予約やお問い合わせを承ります。',
    ai_tone: 'polite',
    faqs: [
      { question: '営業時間を教えてください', answer: 'ランチ11時から14時、ディナー17時から22時です。定休日は月曜日です。', category: '営業案内' },
      { question: '席の予約はできますか', answer: 'ご予約を承ります。ご希望の日時・人数を伺います。', category: '予約' },
      { question: '個室やコースはありますか', answer: '個室とコースをご用意しています。人数・ご予算を伺い、担当者よりご案内します。', category: 'メニュー' },
      { question: '駐車場はありますか', answer: '提携駐車場がございます。詳細は担当者よりご案内します。', category: '営業案内' },
      { question: 'テイクアウトはできますか', answer: 'テイクアウトを承っております。ご希望のメニューと受け取り時間を伺います。', category: 'テイクアウト' },
      { question: '貸切はできますか', answer: '貸切のご相談を承ります。人数・日時・ご予算を担当者よりご案内します。', category: '宴会' },
    ],
    campaigns: [
      { name: '宴会シーズンのご案内', purpose: 'sales', opening: 'お世話になっております。AI担当です。宴会・ご予約のご案内でお電話しました。', goal_prompt: '繁忙期前に常連・法人のお客様へ宴会プランをご案内し、希望があれば日時・人数を伺い担当者へつなぐ。' },
      { name: '予約前日のご確認', purpose: 'reminder', opening: 'お世話になっております。AI担当です。明日のご予約の確認でお電話しました。', goal_prompt: '予約当日・前日の確認。来店人数・時間に変更がないか丁寧に確認する。' },
    ],
  },
];

export function getTemplate(key: string): IndustryTemplate | undefined {
  return INDUSTRY_TEMPLATES.find((t) => t.key === key);
}
