import type { ConnectorConfig, ConnectorMode } from "@hub/connectors";

export interface AppConfig {
  port: number;
  connector: ConnectorConfig;
}

export function loadConfig(env: NodeJS.ProcessEnv = process.env): AppConfig {
  const mode: ConnectorMode = env.CONNECTOR_MODE === "live" ? "live" : "mock";
  return {
    port: Number(env.API_PORT ?? 3001),
    connector: {
      mode,
      credentials: {
        BASE_ACCESS_TOKEN: env.BASE_ACCESS_TOKEN,
        ALIBABA_ACCESS_TOKEN: env.ALIBABA_ACCESS_TOKEN,
        THECKB_API_KEY: env.THECKB_API_KEY,
      },
    },
  };
}
