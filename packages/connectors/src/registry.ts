import type { ChannelId, SupplierId } from "@hub/core";
import { AlibabaConnector } from "./alibaba/alibaba-connector.js";
import { BaseConnector } from "./base/base-connector.js";
import { TheCkbConnector } from "./theckb/theckb-connector.js";
import type { ConnectorConfig, SalesChannelConnector, SupplierConnector } from "./types.js";

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
