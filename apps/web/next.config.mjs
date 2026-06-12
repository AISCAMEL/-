/** @type {import('next').NextConfig} */
const nextConfig = {
  env: {
    HUB_API_URL: process.env.HUB_API_URL ?? "http://localhost:3001",
  },
};

export default nextConfig;
