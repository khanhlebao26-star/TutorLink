import assert from "node:assert/strict";
import { readFileSync } from "node:fs";
import ts from "typescript";

// Run the real mock services without a browser or a backend.
const storage = new Map();
const browser = new EventTarget();
browser.localStorage = {
  getItem: (key) => storage.get(key) ?? null,
  setItem: (key, value) => storage.set(key, value),
};
globalThis.window = browser;

function load(path, imports = {}) {
  const loadedModule = { exports: {} };
  const { outputText } = ts.transpileModule(readFileSync(new URL(path, import.meta.url), "utf8"), {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2017 },
  });
  new Function("require", "module", "exports", outputText)((name) => {
    assert.ok(name in imports, `Unexpected import: ${name}`);
    return imports[name];
  }, loadedModule, loadedModule.exports);
  return loadedModule.exports;
}

const mock = load("../src/lib/data/mock.ts");
class ApiClientError extends Error {
  constructor(message, options) { super(message); Object.assign(this, options); }
}
const services = load("../src/lib/data/services.ts", {
  "./mock": mock,
  "../api/client": {
    ApiClientError, dataMode: "mock", setAuthHint() {},
    request() { assert.fail("Mock mode must not call the API"); },
  },
});

assert.equal(mock.getMockRole(), "tutor");
for (const role of ["customer", "tutor", "admin"]) {
  mock.setMockRole(role);
  assert.equal((await services.getCurrentUser()).role, role);
  assert.equal((await services.login("preview@example.test", "preview-password")).role, role);
}
for (const status of ["pending", "active", "changes_requested", "rejected"]) {
  const profiles = await services.getAdminTutorProfiles(status);
  assert.ok(profiles.length > 0);
  assert.ok(profiles.every((entry) => entry.profile.approval_status === status));
}
const [pending] = await services.getAdminTutorProfiles("pending");
await services.performAdminTutorAction(pending.profile.id, "approve", "Đủ điều kiện");
assert.equal((await services.getAdminTutorProfiles("pending")).length, 0);
assert.equal((await services.getAdminTutorProfile(pending.profile.id)).profile.approval_status, "active");
await services.performAdminAccountAction(pending.user.id, "suspend", "Tạm khóa xem thử");
assert.deepEqual((await services.getAdminTutorProfile(pending.profile.id)).allowed_actions, ["account-restore"]);
assert.equal(new Set(mock.mockListings.map((listing) => listing.slug)).size, mock.mockListings.length);
assert.equal((await services.getListings()).length, mock.mockListings.length);
console.log("Mock roles: customer, tutor, admin; filters and decisions passed without API calls.");
