import { defineConfig } from "vitest/config";
import react from "@vitejs/plugin-react";
import tsconfigPaths from "vite-tsconfig-paths";
export default defineConfig({
  plugins: [react(), tsconfigPaths()],
  test: {
    exclude: ["**/node_modules/**", "**/.next*/**", "e2e/**"],
    environment: "jsdom",
    setupFiles: ["./src/tests/setup.ts"],
    restoreMocks: true,
    coverage: {
      provider: "v8",
      include: ["src/**/*.{ts,tsx}"],
      exclude: ["src/tests/**", "src/**/*.test.*"],
      reporter: ["text", "json-summary", "lcov", "html"],
    },
  },
});
