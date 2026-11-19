import { readdir, readFile, writeFile } from "node:fs/promises";
import { join } from "node:path";
import { gzipSync } from "node:zlib";
const output = process.argv[2];
if (!output)
  throw new Error(
    "Usage: node scripts/bundle-report.mjs output.json [previous.json]",
  );
const root = process.env.TUTORLINK_NEXT_DIST_DIR ?? ".next";
async function walk(path) {
  const entries = await readdir(path, { withFileTypes: true });
  return (
    await Promise.all(
      entries.map((e) =>
        e.isDirectory() ? walk(join(path, e.name)) : [join(path, e.name)],
      ),
    )
  ).flat();
}
const files = (await walk(join(root, "static"))).filter((p) =>
  p.endsWith(".js"),
);
const entries = await Promise.all(
  files.map(async (path) => {
    const bytes = await readFile(path);
    return { path, bytes: bytes.length, gzip: gzipSync(bytes).length };
  }),
);
const report = {
  at: new Date().toISOString(),
  buildId: (await readFile(join(root, "BUILD_ID"), "utf8")).trim(),
  files: entries.length,
  bytes: entries.reduce((s, f) => s + f.bytes, 0),
  gzip: entries.reduce((s, f) => s + f.gzip, 0),
  entries,
};
if (process.argv[3]) {
  const before = JSON.parse(await readFile(process.argv[3], "utf8"));
  report.delta = {
    bytes: report.bytes - before.bytes,
    gzip: report.gzip - before.gzip,
  };
}
await writeFile(output, JSON.stringify(report, null, 2) + "\n");
