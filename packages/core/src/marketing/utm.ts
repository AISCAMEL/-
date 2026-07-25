/** 集客元のSNSプラットフォーム。 */
export type SocialPlatform = "instagram" | "tiktok" | "x" | "youtube";

export interface UtmParams {
  source: string;
  medium: string;
  campaign: string;
  content?: string;
  term?: string;
}

const PLATFORM_SOURCE: Record<SocialPlatform, string> = {
  instagram: "instagram",
  tiktok: "tiktok",
  x: "x",
  youtube: "youtube",
};

/** プラットフォームから UTM の source/medium を決める。 */
export function utmForPlatform(
  platform: SocialPlatform,
  campaign: string,
  content?: string,
): UtmParams {
  return { source: PLATFORM_SOURCE[platform], medium: "social", campaign, content };
}

/**
 * 商品URLに UTM パラメータを付与した計測用リンクを生成する。
 * 既存のクエリは保持し、utm_* のみ上書きする。
 */
export function buildTrackingUrl(baseUrl: string, params: UtmParams): string {
  const url = new URL(baseUrl);
  url.searchParams.set("utm_source", params.source);
  url.searchParams.set("utm_medium", params.medium);
  url.searchParams.set("utm_campaign", params.campaign);
  if (params.content) url.searchParams.set("utm_content", params.content);
  if (params.term) url.searchParams.set("utm_term", params.term);
  return url.toString();
}

/** プラットフォーム指定でワンショットに計測リンクを作るヘルパ。 */
export function buildSocialLink(
  baseUrl: string,
  platform: SocialPlatform,
  campaign: string,
  content?: string,
): string {
  return buildTrackingUrl(baseUrl, utmForPlatform(platform, campaign, content));
}
