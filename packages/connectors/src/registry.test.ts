import { describe, expect, it } from "vitest";
import { connectorModes } from "./registry.js";
import { configFor, resolveMode } from "./types.js";

describe("resolveMode", () => {
  it("個別指定が既定より優先される", () => {
    const cfg = { mode: "mock" as const, modes: { rakuten: "live" as const } };
    expect(resolveMode(cfg, "rakuten")).toBe("live");
    expect(resolveMode(cfg, "amazon")).toBe("mock");
  });

  it("個別指定が無ければ既定モード", () => {
    expect(resolveMode({ mode: "mock" }, "base")).toBe("mock");
  });
});

describe("configFor", () => {
  it("対象コネクタの実効モードを mode に埋め込む", () => {
    const cfg = { mode: "mock" as const, modes: { rakuten: "live" as const } };
    expect(configFor(cfg, "rakuten").mode).toBe("live");
    expect(configFor(cfg, "base").mode).toBe("mock");
  });
});

describe("connectorModes", () => {
  it("楽天だけ live・他は mock を表現できる", () => {
    const modes = connectorModes({ mode: "mock", modes: { rakuten: "live" } });
    expect(modes).toEqual({
      base: "mock",
      alibaba: "mock",
      theckb: "mock",
      amazon: "mock",
      rakuten: "live",
    });
  });
});
