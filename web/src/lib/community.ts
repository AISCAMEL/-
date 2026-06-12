export type Category =
  | "waves"
  | "experiences"
  | "questions"
  | "events"
  | "gear";

type CategoryMeta = {
  key: Category;
  label: string;
  /** 投稿に local 以上の種別が必要か */
  localOnly?: boolean;
  hint: string;
};

export const CATEGORIES: CategoryMeta[] = [
  { key: "waves", label: "波情報", localOnly: true, hint: "今日の海の様子（Local以上）" },
  { key: "experiences", label: "体験シェア", hint: "入ってみた感想・写真" },
  { key: "questions", label: "質問", hint: "初心者の疑問・相談" },
  { key: "events", label: "イベント", hint: "セッション・集まり" },
  { key: "gear", label: "ギア", hint: "ボード・ウェットの話" },
];

export const CATEGORY_LABEL: Record<Category, string> = Object.fromEntries(
  CATEGORIES.map((c) => [c.key, c.label]),
) as Record<Category, string>;

export function isCategory(value: string): value is Category {
  return CATEGORIES.some((c) => c.key === value);
}

/** ゲストにフル表示する投稿数。これを超えた分はぼかして登録に誘導する。 */
export const GUEST_VISIBLE_POSTS = 3;
