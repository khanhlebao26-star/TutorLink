import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import { createRequire } from "node:module";
import ts from "typescript";

// Transpile this isolated URL helper with the project's existing TypeScript dependency.
const source = readFileSync(
  new URL("../src/lib/data/listing-query.ts", import.meta.url),
  "utf8",
);
const compiled = ts.transpileModule(source, {
  compilerOptions: { module: ts.ModuleKind.CommonJS },
}).outputText;
const evaluated = { exports: {} };
new Function("exports", "require", "module", compiled)(
  evaluated.exports,
  createRequire(import.meta.url),
  evaluated,
);
const { readListingQuery, updateListingQuery } = evaluated.exports;
const read = (value) => readListingQuery(new URLSearchParams(value));
assert.equal(
  read("q=%20To%C3%A1n%20&category=12&delivery_mode=online&page=3").q,
  "Toán",
);
assert.equal(read("category=12").category_id, 12);
assert.equal(read("page=-1&per_page=500&min_price=10&sort=price_asc").page, 1);
assert.equal(read("per_page=500").per_page, 50);
assert.equal(read("min_price=10&sort=price_asc").min_price, undefined);
assert.equal(read("min_price=10&sort=price_asc").sort, "newest");
assert.equal(
  read("price_unit=hour&min_price=0&max_price=150000&sort=price_asc").min_price,
  0,
);
assert.equal(
  read("price_unit=hour&min_price=1.5&max_price=9007199254740992").max_price,
  undefined,
);
assert.equal(
  read("delivery_mode=bogus&price_unit=day").delivery_mode,
  undefined,
);
let changed = new URLSearchParams(
  updateListingQuery(
    "category=1&subcategory=2&specialization=3&page=4&q=Toan",
    { category: "5" },
  ),
);
assert.equal(changed.get("category"), "5");
assert.equal(changed.has("subcategory"), false);
assert.equal(changed.has("specialization"), false);
assert.equal(changed.has("page"), false);
assert.equal(changed.get("q"), "Toan");
changed = new URLSearchParams(
  updateListingQuery("category=1&subcategory=2&specialization=3&page=4", {
    subcategory: "6",
  }),
);
assert.equal(changed.get("category"), "1");
assert.equal(changed.has("specialization"), false);
changed = new URLSearchParams(
  updateListingQuery(
    "price_unit=session&min_price=10&max_price=100&sort=price_desc&page=3",
    { price_unit: "" },
  ),
);
for (const key of ["price_unit", "min_price", "max_price", "sort", "page"])
  assert.equal(changed.has(key), false);
changed = new URLSearchParams(
  updateListingQuery("q=English&city=Ha%20Noi&per_page=12", { page: "2" }),
);
assert.equal(changed.get("page"), "2");
assert.equal(changed.get("city"), "Ha Noi");
assert.equal(changed.get("per_page"), "12");
// Restoring a saved URL reconstructs the identical API query for browser back/forward.
assert.deepEqual(
  read(changed.toString()),
  read("q=English&city=Ha%20Noi&per_page=12&page=2"),
);
console.log("Marketplace query checks passed.");
