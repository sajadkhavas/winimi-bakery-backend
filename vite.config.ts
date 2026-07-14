import { defineConfig } from "vite";
import react from "@vitejs/plugin-react-swc";
import path from "path";
import { componentTagger } from "lovable-tagger";
import { VitePWA } from "vite-plugin-pwa";

export default defineConfig(({ mode }) => ({
  server: {
    host: "::",
    port: 8080,
  },
  plugins: [
    react(),
    mode === "development" && componentTagger(),
    VitePWA({
      registerType: "autoUpdate",
      includeAssets: ["favicon.ico", "robots.txt", "images/**/*", "icons/**/*"],
      manifest: {
        name: "پارس ابزار دقیق",
        short_name: "پارس ابزار",
        description: "تأمین‌کننده تجهیزات ابزار دقیق و اتوماسیون صنعتی",
        theme_color: "#003366",
        background_color: "#ffffff",
        display: "standalone",
        orientation: "portrait",
        scope: "/",
        start_url: "/",
        lang: "fa",
        dir: "rtl",
        categories: ["business", "shopping"],
        icons: [
          // ✅ آیکون‌ها باید در public/icons/ باشن (نه CDN خارجی)
          {
            src: "/icons/icon-192x192.png",
            sizes: "192x192",
            type: "image/png",
            purpose: "any",
          },
          {
            src: "/icons/icon-192x192-maskable.png",
            sizes: "192x192",
            type: "image/png",
            purpose: "maskable",
          },
          {
            src: "/icons/icon-512x512.png",
            sizes: "512x512",
            type: "image/png",
            purpose: "any",
          },
          {
            src: "/icons/icon-512x512-maskable.png",
            sizes: "512x512",
            type: "image/png",
            purpose: "maskable",
          },
        ],
        shortcuts: [
          {
            name: "محصولات",
            short_name: "محصولات",
            description: "مشاهده کاتالوگ محصولات",
            url: "/products",
            icons: [{ src: "/icons/icon-96x96.png", sizes: "96x96" }],
          },
          {
            name: "تماس با ما",
            short_name: "تماس",
            description: "ارتباط با پارس ابزار دقیق",
            url: "/contact",
            icons: [{ src: "/icons/icon-96x96.png", sizes: "96x96" }],
          },
        ],
        screenshots: [
          {
            src: "/images/hero-home-polymers.jpg",
            sizes: "1280x720",
            type: "image/jpeg",
            form_factor: "wide",
            label: "صفحه اصلی پارس ابزار دقیق",
          },
          {
            src: "/images/hero-products-pellets.jpg",
            sizes: "750x1334",
            type: "image/jpeg",
            form_factor: "narrow",
            label: "کاتالوگ محصولات",
          },
        ],
      },
      workbox: {
        globPatterns: ["**/*.{js,css,html,ico,png,jpg,jpeg,svg,woff,woff2}"],
        runtimeCaching: [
          {
            urlPattern: /^https:\/\/fonts\.googleapis\.com\/.*/i,
            handler: "CacheFirst",
            options: {
              cacheName: "google-fonts-cache",
              expiration: { maxEntries: 10, maxAgeSeconds: 60 * 60 * 24 * 365 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
          {
            urlPattern: /^https:\/\/fonts\.gstatic\.com\/.*/i,
            handler: "CacheFirst",
            options: {
              cacheName: "gstatic-fonts-cache",
              expiration: { maxEntries: 10, maxAgeSeconds: 60 * 60 * 24 * 365 },
              cacheableResponse: { statuses: [0, 200] },
            },
          },
          {
            urlPattern: /\/api\/v1\/.*/i,
            handler: "NetworkFirst",
            options: {
              cacheName: "api-cache",
              expiration: { maxEntries: 100, maxAgeSeconds: 60 * 60 * 24 },
              cacheableResponse: { statuses: [0, 200] },
              networkTimeoutSeconds: 10,
            },
          },
        ],
        navigateFallback: "/index.html",
        navigateFallbackDenylist: [/^\/api\//],
        cleanupOutdatedCaches: true,
        skipWaiting: true,
        clientsClaim: true,
      },
      devOptions: { enabled: false },
    }),
  ].filter(Boolean),
  resolve: {
    alias: { "@": path.resolve(__dirname, "./src") },
  },
}));
