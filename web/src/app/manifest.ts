import type { MetadataRoute } from "next";

export default function manifest(): MetadataRoute.Manifest {
  return {
    name: "IWASAWA SURF BASE",
    short_name: "IWASAWA",
    description:
      "福島の波を、もっと近くに。学べる・借りられる・移動できる・案内される、岩沢海岸の海体験プラットフォーム。",
    start_url: "/",
    display: "standalone",
    orientation: "portrait",
    background_color: "#0B2540",
    theme_color: "#0B2540",
    lang: "ja",
    categories: ["sports", "lifestyle", "social"],
    icons: [
      {
        src: "/icon-192.png",
        sizes: "192x192",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/icon-512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "any",
      },
      {
        src: "/icon-512.png",
        sizes: "512x512",
        type: "image/png",
        purpose: "maskable",
      },
    ],
  };
}
