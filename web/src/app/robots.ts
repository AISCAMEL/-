import type { MetadataRoute } from "next";
import { SITE_URL } from "@/lib/site";

// 検索エンジン向け：会員ページ・管理画面・APIはクロール対象外
export default function robots(): MetadataRoute.Robots {
  return {
    rules: {
      userAgent: "*",
      allow: "/",
      disallow: ["/admin", "/me", "/api/", "/auth/", "/login", "/signup"],
    },
    sitemap: `${SITE_URL}/sitemap.xml`,
  };
}
