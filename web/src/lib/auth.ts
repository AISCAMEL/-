import { createClient } from "@/lib/supabase/server";
import type { Category } from "@/lib/community";

export type MemberRole = "visitor" | "beginner" | "local" | "staff" | "admin";

export type CurrentMember = {
  id: string;
  display_name: string | null;
  handle: string | null;
  role: MemberRole;
  plan: "free" | "premium";
};

/**
 * 現在ログイン中の会員を返す。未ログインなら null（＝Visitor/ゲスト扱い）。
 * Server Component / Server Action から使う「API層のゲート」の起点。
 */
export async function getCurrentMember(): Promise<CurrentMember | null> {
  const supabase = await createClient();
  const {
    data: { user },
  } = await supabase.auth.getUser();
  if (!user) return null;

  const { data } = await supabase
    .from("members")
    .select("id, display_name, handle, role, plan")
    .eq("id", user.id)
    .single();

  return (data as CurrentMember) ?? null;
}

const RANK: Record<MemberRole, number> = {
  visitor: 0,
  beginner: 1,
  local: 2,
  staff: 3,
  admin: 4,
};

export function hasRole(
  member: CurrentMember | null,
  min: MemberRole,
): boolean {
  if (!member) return false;
  return RANK[member.role] >= RANK[min];
}

/** そのカテゴリに投稿できるか（waves は local 以上） */
export function canPostCategory(
  member: CurrentMember | null,
  category: Category,
): boolean {
  if (!member) return false;
  if (category === "waves") return hasRole(member, "local");
  return hasRole(member, "beginner");
}
