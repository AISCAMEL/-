import type { ChannelId, MarketId, SupplierId } from "@hub/core";
import { AlibabaConnector } from "./alibaba/alibaba-connector.js";
import { BaseConnector } from "./base/base-connector.js";
import { AmazonConnector } from "./research/amazon-connector.js";
import { RakutenConnector } from "./research/rakuten-connector.js";
import { TheCkbConnector } from "./theckb/theckb-connector.js";
import type {
  ConnectorConfig,
  MarketResearchConnector,
  SalesChannelConnector,
  SupplierConnector,
} from "./types.js";

/** 設定からコネクタ群を生成するファクトリ。 */
export function createSupplierConnectors(
  config: ConnectorConfig,
): Record<SupplierId, SupplierConnector> {
  return {
    alibaba: new AlibabaConnector(config),
    theckb: new TheCkbConnector(config),
  };
}

export function createChannelConnectors(
  config: ConnectorConfig,
): Record<ChannelId, SalesChannelConnector> {
  return {
    base: new BaseConnector(config),
  };
}

export function createMarketConnectors(
  config: ConnectorConfig,
): Record<MarketId, MarketResearchConnector> {
  return {
    amazon: new AmazonConnector(config),
    rakuten: new RakutenConnector(config),
  };
}
