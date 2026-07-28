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
  const status = String(formData.get("status"));
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
  const status = String(formData.get("status"));
  const supabase = await createClient();
  await supabase.from("members").update({ status }).eq("id", id);
  await audit(staff.id, `member.${status}`, "member", id);
  revalidatePath("/admin/members");
}

// --- 講師管理 ----------------------------------------------------
export async function setInstructorRank(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const rank = String(formData.get("rank")); // none | instructor | top_amateur | pro
  const supabase = await createClient();

  if (rank === "none") {
    await supabase.from("instructor_profiles").delete().eq("member_id", id);
    await audit(staff.id, "instructor.remove", "member", id);
  } else {
    await supabase
      .from("instructor_profiles")
      .upsert({ member_id: id, rank }, { onConflict: "member_id" });
    await audit(staff.id, "instructor.rank", "member", id, { rank });
  }
  revalidatePath("/admin/instructors");
}

export async function toggleInstructorFlag(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const field = String(formData.get("field")); // is_featured | accepting
  const value = formData.get("value") === "true";
  const supabase = await createClient();
  await supabase
    .from("instructor_profiles")
    .update({ [field]: value })
    .eq("member_id", id);
  await audit(staff.id, `instructor.${field}`, "member", id, { value });
  revalidatePath("/admin/instructors");
}

// --- オンライン講座 ----------------------------------------------
export async function createCourse(formData: FormData) {
  const staff = await requireStaff();
  const title = String(formData.get("title") || "").trim();
  if (!title) return;
  const supabase = await createClient();
  const { data } = await supabase
    .from("courses")
    .insert({
      title,
      description: String(formData.get("description") || "").trim() || null,
      level: String(formData.get("level") || "beginner"),
      cover_url: String(formData.get("cover_url") || "").trim() || null,
      status: "draft",
    })
    .select("id")
    .single();
  await audit(staff.id, "course.create", "course", data?.id ?? "");
  revalidatePath("/admin/courses");
}

export async function updateCourse(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const supabase = await createClient();
  await supabase
    .from("courses")
    .update({
      title: String(formData.get("title") || "").trim(),
      description: String(formData.get("description") || "").trim() || null,
      level: String(formData.get("level") || "beginner"),
      cover_url: String(formData.get("cover_url") || "").trim() || null,
      status: String(formData.get("status") || "draft"),
    })
    .eq("id", id);
  await audit(staff.id, "course.update", "course", id);
  revalidatePath(`/admin/courses/${id}`);
  revalidatePath("/admin/courses");
}

export async function deleteCourse(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const supabase = await createClient();
  await supabase.from("courses").delete().eq("id", id);
  await audit(staff.id, "course.delete", "course", id);
  revalidatePath("/admin/courses");
}

export async function createLesson(formData: FormData) {
  const staff = await requireStaff();
  const courseId = String(formData.get("course_id"));
  const title = String(formData.get("title") || "").trim();
  if (!courseId || !title) return;
  const supabase = await createClient();
  await supabase.from("lessons").insert({
    course_id: courseId,
    title,
    body: String(formData.get("body") || "").trim() || null,
    video_url: String(formData.get("video_url") || "").trim() || null,
    is_free: formData.get("is_free") === "on",
    duration_min: formData.get("duration_min")
      ? Number(String(formData.get("duration_min")).replace(/[^\d]/g, ""))
      : null,
    sort_order: formData.get("sort_order")
      ? Number(String(formData.get("sort_order")).replace(/[^\d]/g, ""))
      : 0,
  });
  await audit(staff.id, "lesson.create", "course", courseId);
  revalidatePath(`/admin/courses/${courseId}`);
}

export async function updateLesson(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const courseId = String(formData.get("course_id"));
  const supabase = await createClient();
  await supabase
    .from("lessons")
    .update({
      title: String(formData.get("title") || "").trim(),
      body: String(formData.get("body") || "").trim() || null,
      video_url: String(formData.get("video_url") || "").trim() || null,
      is_free: formData.get("is_free") === "on",
      duration_min: formData.get("duration_min")
        ? Number(String(formData.get("duration_min")).replace(/[^\d]/g, ""))
        : null,
      sort_order: formData.get("sort_order")
        ? Number(String(formData.get("sort_order")).replace(/[^\d]/g, ""))
        : 0,
    })
    .eq("id", id);
  await audit(staff.id, "lesson.update", "lesson", id);
  revalidatePath(`/admin/courses/${courseId}`);
}

export async function deleteLesson(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const courseId = String(formData.get("course_id"));
  const supabase = await createClient();
  await supabase.from("lessons").delete().eq("id", id);
  await audit(staff.id, "lesson.delete", "lesson", id);
  revalidatePath(`/admin/courses/${courseId}`);
}

// --- 広告枠 ------------------------------------------------------
export async function toggleAd(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const active = formData.get("active") === "true";
  const supabase = await createClient();
  await supabase.from("ad_banners").update({ is_active: active }).eq("id", id);
  await audit(staff.id, "ad.active", "ad", id, { active });
  revalidatePath("/admin/ads");
}

// --- お問い合わせ ------------------------------------------------
export async function markInquiry(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const status = String(formData.get("status")); // open | handled
  const supabase = await createClient();
  await supabase.from("inquiries").update({ status }).eq("id", id);
  await audit(staff.id, `inquiry.${status}`, "inquiry", id);
  revalidatePath("/admin/inquiries");
}

// --- 通報対応 ----------------------------------------------------
export async function resolveReport(formData: FormData) {
  const staff = await requireStaff();
  const id = String(formData.get("id"));
  const action = String(formData.get("action"));
  const targetType = String(formData.get("target_type"));
  const targetId = String(formData.get("target_id"));
  const supabase = await createClient();

  if (action === "hide") {
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
