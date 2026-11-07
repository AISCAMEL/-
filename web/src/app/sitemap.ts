import type { MetadataRoute } from "next";
import { SITE_URL } from "@/lib/site";
import { SURF_SPOTS, SURF_AREAS } from "@/lib/spots";
import { DEMO, demoInstructors, demoCourses, demoArticles } from "@/lib/demo";

// サイトマップ：公開ページ＋全国サーフポイント（ポイント・エリア）
export default function sitemap(): MetadataRoute.Sitemap {
  const now = new Date();
  const page = (
    path: string,
    changeFrequency: MetadataRoute.Sitemap[number]["changeFrequency"],
    priority: number,
  ) => ({ url: `${SITE_URL}${path}`, lastModified: now, changeFrequency, priority });

  const fixed = [
    page("/", "daily", 1),
    page("/waves", "hourly", 0.9),
    page("/spots", "daily", 0.9),
    page("/partners", "weekly", 0.7),
    page("/instructors", "weekly", 0.8),
    page("/courses", "weekly", 0.7),
    page("/rules", "monthly", 0.7),
    page("/community", "daily", 0.6),
    page("/skills", "daily", 0.5),
    page("/articles", "weekly", 0.6),
    page("/about", "monthly", 0.5),
    page("/faq", "monthly", 0.5),
    page("/contact", "yearly", 0.3),
    page("/ads", "monthly", 0.3),
    page("/legal/tokushoho", "yearly", 0.1),
    page("/legal/privacy", "yearly", 0.1),
    page("/legal/terms", "yearly", 0.1),
  ];
  const spots = SURF_SPOTS.map((s) => page(`/spots/${s.id}`, "hourly", s.home ? 0.9 : 0.6));
  const areas = SURF_AREAS.map((a) => page(`/spots/area/${a.id}`, "hourly", 0.6));
  // デモ中はサンプルの詳細ページも載せる（本番データの詳細ページは一覧から辿られる）
  const demo = DEMO
    ? [
        ...demoInstructors.map((x) => page(`/instructors/${x.id}`, "weekly", 0.6)),
        ...demoCourses.map((x) => page(`/courses/${x.id}`, "weekly", 0.5)),
        ...demoArticles.map((x) => page(`/articles/${x.id}`, "monthly", 0.5)),
      ]
    : [];
  return [...fixed, ...spots, ...areas, ...demo];
}
