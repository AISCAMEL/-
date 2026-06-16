import type { ConnectorConfig, ConnectorKey, ConnectorMode } from "@hub/connectors";

export interface AppConfig {
  port: number;
  connector: ConnectorConfig;
}

/**
 * コネクタ個別の実効モードを決める。優先順位:
 *   1. 明示的な `<NAME>_MODE`（例: RAKUTEN_MODE=mock）
 *   2. 認証情報があれば live（キーを入れるだけで自動 live）
 *   3. 既定モード（CONNECTOR_MODE、未指定なら mock）
 */
function resolveModes(
  env: NodeJS.ProcessEnv,
  fallback: ConnectorMode,
): Partial<Record<ConnectorKey, ConnectorMode>> {
  const hasCred: Record<ConnectorKey, boolean> = {
    base: !!env.BASE_ACCESS_TOKEN,
    alibaba: !!env.ALIBABA_ACCESS_TOKEN,
    theckb: !!env.THECKB_API_KEY,
    amazon: !!(env.KEEPA_API_KEY || env.AMAZON_PAAPI_ACCESS_KEY),
    rakuten: !!env.RAKUTEN_APP_ID,
  };
  const override: Record<ConnectorKey, string | undefined> = {
    base: env.BASE_MODE,
    alibaba: env.ALIBABA_MODE,
    theckb: env.THECKB_MODE,
    amazon: env.AMAZON_MODE,
    rakuten: env.RAKUTEN_MODE,
  };

  const keys: ConnectorKey[] = ["base", "alibaba", "theckb", "amazon", "rakuten"];
  const modes: Partial<Record<ConnectorKey, ConnectorMode>> = {};
  for (const k of keys) {
    if (override[k] === "live" || override[k] === "mock") {
      modes[k] = override[k] as ConnectorMode;
    } else if (hasCred[k]) {
      modes[k] = "live";
    } else {
      modes[k] = fallback;
    }
  }
  return modes;
}

export function loadConfig(env: NodeJS.ProcessEnv = process.env): AppConfig {
  const mode: ConnectorMode = env.CONNECTOR_MODE === "live" ? "live" : "mock";
  return {
    port: Number(env.API_PORT ?? 3001),
    connector: {
      mode,
      modes: resolveModes(env, mode),
      credentials: {
        BASE_ACCESS_TOKEN: env.BASE_ACCESS_TOKEN,
        ALIBABA_ACCESS_TOKEN: env.ALIBABA_ACCESS_TOKEN,
        THECKB_API_KEY: env.THECKB_API_KEY,
        RAKUTEN_APP_ID: env.RAKUTEN_APP_ID,
        KEEPA_API_KEY: env.KEEPA_API_KEY,
        AMAZON_PAAPI_ACCESS_KEY: env.AMAZON_PAAPI_ACCESS_KEY,
      },
    },
  };
}
