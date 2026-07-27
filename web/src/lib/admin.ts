import { redirect } from "next/navigation";
import { getCurrentMember, type CurrentMember } from "@/lib/auth";

/**
 * 管理画面の入口ゲート（API層）。
 * staff / admin 以外はアクセス不可。proxy でも保護しているが二重で確認する。
 */
export async function requireStaff(): Promise<CurrentMember> {
  const member = await getCurrentMember();
  if (!member) redirect("/admin/login");
  if (member.role !== "staff" && member.role !== "admin") redirect("/");
  return member;
}
