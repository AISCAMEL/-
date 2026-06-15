"use server";

import { revalidatePath } from "next/cache";
import { createClient } from "@/lib/supabase/server";
import { requireStaff } from "@/lib/admin";
import type { Category } from "@/lib/community";

/** 操作ログを最小記録（失敗してもメイン操作は止めない） */
async function audit(
  actorId: string,
  action: string,
  targetType: string,
  targetId: string,
  meta?: Record<string, unknown>,
) {
  const supabase = await createClient();
  await supabase.from("admin_audit_logs").insert({
    actor_id: actorId,
    action,
    target_type: targetType,
    target_id: targetId,
    meta: meta ?? null,
  });
}

// --- 投稿管理 ----------------------------------------------------
export async function setPostStatus(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const status = String(formData.get("status")); // published | hidden
  const supabase = await createClient();
  await supabase.from("posts").update({ status }).eq("id", id);
  await audit(staff.id, `post.${status}`, "post", id);
  revalidatePath("/admin/posts");
}

export async function toggleFeatured(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const featured = formData.get("featured") === "true";
  const supabase = await createClient();
  await supabase.from("posts").update({ is_featured: featured }).eq("id", id);
  await audit(staff.id, "post.featured", "post", id, { featured });
  revalidatePath("/admin/posts");
}

export async function setPostCategory(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const category = String(formData.get("category")) as Category;
  const supabase = await createClient();
  await supabase.from("posts").update({ category }).eq("id", id);
  await audit(staff.id, "post.category", "post", id, { category });
  revalidatePath("/admin/posts");
}

export async function deletePost(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const supabase = await createClient();
  await supabase.from("posts").delete().eq("id", id);
  await audit(staff.id, "post.delete", "post", id);
  revalidatePath("/admin/posts");
}

// --- 会員管理 ----------------------------------------------------
export async function setMemberRole(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const role = String(formData.get("role"));
  const supabase = await createClient();
  await supabase.from("members").update({ role }).eq("id", id);
  await audit(staff.id, "member.role", "member", id, { role });
  revalidatePath("/admin/members");
}

export async function setMemberStatus(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const status = String(formData.get("status")); // active | suspended
  const supabase = await createClient();
  await supabase.from("members").update({ status }).eq("id", id);
  await audit(staff.id, `member.${status}`, "member", id);
  revalidatePath("/admin/members");
}

// --- 通報対応 ----------------------------------------------------
export async function resolveReport(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const action = String(formData.get("action")); // hide | dismiss
  const targetType = String(formData.get("target_type"));
  const targetId = String(formData.get("target_id"));
  const supabase = await createClient();

  if (action === "hide") {
    // 対象を非公開にする（post / comment）
    if (targetType === "post" || targetType === "comment") {
      const table = targetType === "post" ? "posts" : "comments";
      await supabase.from(table).update({ status: "hidden" }).eq("id", targetId);
    } else if (targetType === "skill") {
      await supabase.from("skills").update({ status: "hidden" }).eq("id", targetId);
    }
    await supabase
      .from("reports")
      .update({ status: "resolved", handled_by: staff.id })
      .eq("id", id);
    await audit(staff.id, "report.resolve_hide", targetType, targetId);
  } else {
    await supabase
      .from("reports")
      .update({ status: "dismissed", handled_by: staff.id })
      .eq("id", id);
    await audit(staff.id, "report.dismiss", targetType, targetId);
  }
  revalidatePath("/admin/reports");
}
