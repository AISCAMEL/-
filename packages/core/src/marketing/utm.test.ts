import { describe, expect, it } from "vitest";
import { buildSocialLink, buildTrackingUrl, utmForPlatform } from "./utm.js";

describe("utmForPlatform", () => {
  it("tiktok は source=tiktok / medium=social", () => {
    const p = utmForPlatform("tiktok", "cat_toy_0616", "reel_a");
    expect(p).toMatchObject({ source: "tiktok", medium: "social", campaign: "cat_toy_0616", content: "reel_a" });
  });
});

describe("buildTrackingUrl", () => {
  it("utm パラメータを付与する", () => {
    const url = buildTrackingUrl("https://shop.example.com/items/123", {
      source: "instagram",
      medium: "social",
      campaign: "cat_bed",
      content: "story_1",
    });
    expect(url).toContain("utm_source=instagram");
    expect(url).toContain("utm_medium=social");
    expect(url).toContain("utm_campaign=cat_bed");
    expect(url).toContain("utm_content=story_1");
  });

  it("既存クエリを保持する", () => {
    const url = buildTrackingUrl("https://shop.example.com/items/123?color=red", {
      source: "tiktok",
      medium: "social",
      campaign: "c",
    });
    expect(url).toContain("color=red");
  });
});

describe("buildSocialLink", () => {
  it("プラットフォーム指定でリンクを生成する", () => {
    const url = buildSocialLink("https://shop.example.com/i/1", "tiktok", "camp1", "v2");
    expect(url).toContain("utm_source=tiktok");
    expect(url).toContain("utm_content=v2");
  });
});
