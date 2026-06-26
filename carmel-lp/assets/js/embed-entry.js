/**
 * embed-entry.js
 * ------------------------------------------------------------------
 * 埋め込み(WordPress等)用のチャット初期化エントリ。
 * LP本体のFAQ/診断などは初期化せず、チャットウィジェットのみ起動する。
 * embed.js が widgetのHTML/CSSを注入した後に、動的importで読み込まれる。
 */

import { initChatFeature } from './lp-chat.js';
import { initChatbot } from './chatbot.js';

export function bootEmbed() {
  initChatFeature();
  initChatbot();
}

bootEmbed();
export default bootEmbed;
