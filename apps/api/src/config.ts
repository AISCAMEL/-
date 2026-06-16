import type { ConnectorConfig, ConnectorKey, ConnectorMode } from "@hub/connectors";

export interface AppConfig {
  port: number;
  connector: ConnectorConfig;
  /** 在庫・価格の定期同期間隔（分）。0 で無効。 */
  syncIntervalMinutes: number;
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
    aliexpress: !!env.ALIEXPRESS_APP_KEY,
    amazon: !!(env.KEEPA_API_KEY || env.AMAZON_PAAPI_ACCESS_KEY),
    rakuten: !!env.RAKUTEN_APP_ID,
    yahoo: !!env.YAHOO_APP_ID,
    ebay: !!env.EBAY_OAUTH_TOKEN,
  };
  const override: Record<ConnectorKey, string | undefined> = {
    base: env.BASE_MODE,
    alibaba: env.ALIBABA_MODE,
    theckb: env.THECKB_MODE,
    aliexpress: env.ALIEXPRESS_MODE,
    amazon: env.AMAZON_MODE,
    rakuten: env.RAKUTEN_MODE,
    yahoo: env.YAHOO_MODE,
    ebay: env.EBAY_MODE,
  };

  const keys: ConnectorKey[] = [
    "base",
    "alibaba",
    "theckb",
    "aliexpress",
    "amazon",
    "rakuten",
    "yahoo",
    "ebay",
  ];
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
    syncIntervalMinutes: Number(env.SYNC_INTERVAL_MINUTES ?? 0),
    connector: {
      mode,
      modes: resolveModes(env, mode),
      credentials: {
        BASE_ACCESS_TOKEN: env.BASE_ACCESS_TOKEN,
        ALIBABA_ACCESS_TOKEN: env.ALIBABA_ACCESS_TOKEN,
        THECKB_API_KEY: env.THECKB_API_KEY,
        ALIEXPRESS_APP_KEY: env.ALIEXPRESS_APP_KEY,
        ALIEXPRESS_APP_SECRET: env.ALIEXPRESS_APP_SECRET,
        RAKUTEN_APP_ID: env.RAKUTEN_APP_ID,
        KEEPA_API_KEY: env.KEEPA_API_KEY,
        AMAZON_PAAPI_ACCESS_KEY: env.AMAZON_PAAPI_ACCESS_KEY,
        YAHOO_APP_ID: env.YAHOO_APP_ID,
        EBAY_OAUTH_TOKEN: env.EBAY_OAUTH_TOKEN,
      },
    },
  };
}
