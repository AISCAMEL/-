import type { Metadata } from "next";
import { SITE } from "@/lib/site";
import { createClient as createSupabaseClient } from "@supabase/supabase-js";
import {
  DEMO,
  demoInstructors,
  demoPosts,
  demoCourses,
  demoSkills,
  demoArticles,
} from "@/lib/demo";

// ============================================================
// 詳細ページのタイトル・説明文（検索結果・SNSシェア用）
// ログイン状態に依存しない公開情報だけを、cookie なしで取得する。
// 取得できない場合はページ種別だけのタイトルにする。
// ============================================================

export type MetaKind = "instructor" | "post" | "course" | "skill" | "article" | "lesson";

const FALLBACK: Record<MetaKind, string> = {
  instructor: "インストラクター",
  post: "コミュニティの投稿",
  course: "オンライン講座",
  skill: "スキル掲示板",
  article: "プロのブログ",
  lesson: "レッスン",
};

function clip(text: string | null | undefined, n = 110): string | undefined {
  if (!text) return undefined;
  const t = text.replace(/\s+/g, " ").trim();
  return t.length > n ? `${t.slice(0, n)}…` : t;
}

type Found = { title: string; description?: string } | null;

function fromDemo(kind: MetaKind, id: string): Found {
  switch (kind) {
    case "instructor": {
      const d = demoInstructors.find((x) => x.id === id);
      return d ? { title: `${d.name}｜サーフィンスクール`, description: clip(d.headline || d.bio) } : null;
    }
    case "post": {
      const d = demoPosts.find((x) => x.id === id);
      return d ? { title: d.title ?? "コミュニティの投稿", description: clip(d.body) } : null;
    }
    case "course": {
      const d = demoCourses.find((x) => x.id === id);
      return d ? { title: `${d.title}｜オンライン講座`, description: clip(d.description) } : null;
    }
    case "skill": {
      const d = demoSkills.find((x) => x.id === id);
      return d ? { title: `${d.title}｜スキル掲示板`, description: clip(d.description) } : null;
    }
    case "article": {
      const d = demoArticles.find((x) => x.id === id);
      return d ? { title: d.title, description: clip(d.excerpt || d.body) } : null;
    }
    case "lesson": {
      for (const c of demoCourses) {
        const l = c.lessons.find((x) => x.id === id);
        if (l) return { title: `${l.title}｜${c.title}`, description: clip(l.body) };
      }
      return null;
    }
  }
}

async function fromDb(kind: MetaKind, id: string): Promise<Found> {
  const supabase = createSupabaseClient(
    process.env.NEXT_PUBLIC_SUPABASE_URL!,
    process.env.NEXT_PUBLIC_SUPABASE_ANON_KEY!,
    { auth: { persistSession: false } },
  );
  type Row = Record<string, unknown> & { title?: string | null };
  const one = async (table: string, cols: string, key = "id") => {
    const { data } = await supabase.from(table).select(cols).eq(key, id).maybeSingle();
    return (data as Row | null) ?? null;
  };
  switch (kind) {
    case "instructor": {
      const r = await one(
        "instructor_profiles",
        "headline, bio, member:members!instructor_profiles_member_id_fkey(display_name)",
        "member_id",
      );
      const name = (r?.member as { display_name?: string | null } | undefined)?.display_name;
      return r ? { title: `${name ?? "インストラクター"}｜サーフィンスクール`, description: clip((r.headline as string) || (r.bio as string)) } : null;
    }
    case "post": {
      const r = await one("posts", "title, body");
      return r?.title ? { title: r.title, description: clip(r.body as string) } : null;
    }
    case "course": {
      const r = await one("courses", "title, description");
      return r?.title ? { title: `${r.title}｜オンライン講座`, description: clip(r.description as string) } : null;
    }
    case "skill": {
      const r = await one("skills", "title, description");
      return r?.title ? { title: `${r.title}｜スキル掲示板`, description: clip(r.description as string) } : null;
    }
    case "article": {
      const r = await one("articles", "title, body");
      return r?.title ? { title: r.title, description: clip(r.body as string) } : null;
    }
    case "lesson": {
      const r = await one("lessons", "title, course:courses(title)");
      const course = (r?.course as { title?: string } | undefined)?.title;
      return r?.title ? { title: course ? `${r.title}｜${course}` : r.title } : null;
    }
  }
}

export async function detailMetadata(kind: MetaKind, id: string): Promise<Metadata> {
  let found: Found = null;
  try {
    found = DEMO ? fromDemo(kind, id) : await fromDb(kind, id);
  } catch {
    found = null;
  }
  const title = found?.title ?? FALLBACK[kind];
  return {
    title,
    description: found?.description,
    // 子ページの openGraph は親の設定を丸ごと置き換えるので、画像なども入れ直す
    openGraph: {
      title,
      description: found?.description,
      siteName: SITE.name,
      locale: "ja_JP",
      type: "article",
      images: [{ url: "/og.jpg", width: 1200, height: 630 }],
    },
  };
}
