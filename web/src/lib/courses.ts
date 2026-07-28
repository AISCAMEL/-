export type CourseLevel = "prep" | "beginner" | "intermediate";

export const LEVEL_LABEL: Record<CourseLevel, string> = {
  prep: "事前学習",
  beginner: "初心者",
  intermediate: "ステップアップ",
};

export const LEVEL_BADGE: Record<CourseLevel, string> = {
  prep: "bg-navy/10 text-navy/70",
  beginner: "bg-teal/15 text-teal",
  intermediate: "bg-ocean/15 text-ocean",
};
