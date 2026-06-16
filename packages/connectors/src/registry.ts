import type { ChannelId, MarketId, SupplierId } from "@hub/core";
import { AlibabaConnector } from "./alibaba/alibaba-connector.js";
import { BaseConnector } from "./base/base-connector.js";
import { AmazonConnector } from "./research/amazon-connector.js";
import { RakutenConnector } from "./research/rakuten-connector.js";
import { TheCkbConnector } from "./theckb/theckb-connector.js";
import {
  configFor,
  resolveMode,
  type ConnectorConfig,
  type ConnectorKey,
  type ConnectorMode,
  type MarketResearchConnector,
  type SalesChannelConnector,
  type SupplierConnector,
} from "./types.js";

/** 設定からコネクタ群を生成するファクトリ（各コネクタは個別の実効モードで動く）。 */
export function createSupplierConnectors(
  config: ConnectorConfig,
): Record<SupplierId, SupplierConnector> {
  return {
    alibaba: new AlibabaConnector(configFor(config, "alibaba")),
    theckb: new TheCkbConnector(configFor(config, "theckb")),
  };
}

export function createChannelConnectors(
  config: ConnectorConfig,
): Record<ChannelId, SalesChannelConnector> {
  return {
    base: new BaseConnector(configFor(config, "base")),
  };
}

export function createMarketConnectors(
  config: ConnectorConfig,
): Record<MarketId, MarketResearchConnector> {
  return {
    amazon: new AmazonConnector(configFor(config, "amazon")),
    rakuten: new RakutenConnector(configFor(config, "rakuten")),
  };
}

/** 各コネクタの実効モード（mock | live）を一覧で返す。状態表示用。 */
export function connectorModes(config: ConnectorConfig): Record<ConnectorKey, ConnectorMode> {
  const keys: ConnectorKey[] = ["base", "alibaba", "theckb", "amazon", "rakuten"];
  return Object.fromEntries(keys.map((k) => [k, resolveMode(config, k)])) as Record<
    ConnectorKey,
    ConnectorMode
  >;
}
