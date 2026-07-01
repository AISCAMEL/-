/** @type {import('next').NextConfig} */
const nextConfig = {
  env: {
    HUB_API_URL: process.env.HUB_API_URL ?? "http://127.0.0.1:3001",
  },
};

export default nextConfig;
