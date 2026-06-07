/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  images: {
    // 将来 CMS / 外部CDN の画像を使う際はここに remotePatterns を追加
    formats: ["image/avif", "image/webp"],
  },
};

export default nextConfig;
