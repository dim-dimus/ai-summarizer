import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // Static export: the whole app is client-rendered and talks to the API from
  // the browser, so no SSR runtime is needed. Outputs a static site to `out/`.
  output: "export",

  // Pin the workspace root to this app (a stray lockfile exists higher up,
  // which otherwise makes Turbopack infer the wrong root).
  turbopack: {
    root: import.meta.dirname,
  },
};

export default nextConfig;
