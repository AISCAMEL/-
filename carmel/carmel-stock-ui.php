<?php
/**
 * Plugin Name: カーメル在庫 STEP UI 一式
 * Description: 在庫(portfolio)のSTEP UI連携（基本情報・装備・見積もり・担当店舗）、支払回数修正、見積初期費用設定、管理画面整理、フロント装備表示 [carmel_equipment] を一括提供。ACFフィールド（見積もり明細・追加装備）も自動登録。
 * Version: 1.0.0
 * Author: カーメル
 * ---------------------------------------------------------------------------
 *  導入方法（どちらか）:
 *   (1) プラグインとして: 本ファイルを wp-content/plugins/carmel-stock-ui.php に置く
 *       → 管理画面「プラグイン」で『カーメル在庫 STEP UI 一式』を有効化。
 *   (2) 子テーマに: 本ファイルの先頭プラグインヘッダーを除いた中身を
 *       子テーマの functions.php 末尾に貼り付け。
 *
 *  これ1つで完結します。旧 WPCode スニペット（carmel_step1_autofill / 旧装備JS /
 *  これまでの修正一式）と、手動インポートした ACF「見積もり明細」「追加装備」グループは
 *  不要です（重複防止のため旧スニペットは無効化してください。ACFグループはキーが同じため
 *  自動的に1つに統合されます）。
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/* =========================================================================
 *  ACF フィールドグループをコードから自動登録（手動インポート不要）
 *  ※ DBに同キーのグループが既にある場合はそちらが優先され重複しません。
 * ========================================================================= */
add_action( 'acf/init', 'carmel_register_local_field_groups' );
function carmel_register_local_field_groups() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) { return; }

	$estimate = json_decode( <<<'CARMEL_ESTIMATE_JSON'
[
 {
  "key": "group_carmel_estimate",
  "title": "見積もり明細",
  "fields": [
   {
    "key": "field_cest_h1",
    "label": "車両",
    "name": "_cest_h1",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── 車両 ──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_cest_honntai",
    "label": "車両本体価格",
    "name": "est_honntai",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_nebiki",
    "label": "値引き",
    "name": "est_nebiki",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_shitadori",
    "label": "下取り価格",
    "name": "est_shitadori",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_h2",
    "label": "法定費用（非課税）",
    "name": "_cest_h2",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── 法定費用（非課税）──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_cest_jidoshazei",
    "label": "自動車税（環境性能割・種別割）",
    "name": "est_jidoshazei",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_jibaiseki",
    "label": "自賠責保険料",
    "name": "est_jibaiseki",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_juuryouzei",
    "label": "自動車重量税",
    "name": "est_juuryouzei",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_inshi",
    "label": "登録時印紙代",
    "name": "est_inshi",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_recycle",
    "label": "リサイクル預託金",
    "name": "est_recycle",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_h3",
    "label": "諸費用（課税）",
    "name": "_cest_h3",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── 諸費用（課税）──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_cest_touroku_daiko",
    "label": "登録代行費用",
    "name": "est_touroku_daiko",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_shako",
    "label": "車庫証明代行",
    "name": "est_shako",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_nousha",
    "label": "納車費用",
    "name": "est_nousha",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_kensa_daiko",
    "label": "検査登録手続代行",
    "name": "est_kensa_daiko",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_seibi",
    "label": "点検整備費用",
    "name": "est_seibi",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_hoshou",
    "label": "保証料",
    "name": "est_hoshou",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_h4",
    "label": "計算結果（自動）",
    "name": "_cest_h4",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── 計算結果（自動計算）──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_cest_shouhizei",
    "label": "消費税",
    "name": "est_shouhizei",
    "aria-label": "",
    "type": "number",
    "instructions": "課税対象×10%（自動計算）",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_total",
    "label": "支払総額",
    "name": "est_total",
    "aria-label": "",
    "type": "number",
    "instructions": "自動計算",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_h5",
    "label": "ローン",
    "name": "_cest_h5",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── ローン ──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_cest_atamakin",
    "label": "頭金",
    "name": "est_atamakin",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   },
   {
    "key": "field_cest_kaisuu",
    "label": "支払回数",
    "name": "est_kaisuu",
    "aria-label": "",
    "type": "number",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "回"
   },
   {
    "key": "field_cest_nenritsu",
    "label": "実質年率",
    "name": "est_nenritsu",
    "aria-label": "",
    "type": "number",
    "instructions": "0なら均等割り（金利なし）",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "%"
   },
   {
    "key": "field_cest_getsugaku",
    "label": "月々支払額",
    "name": "est_getsugaku",
    "aria-label": "",
    "type": "number",
    "instructions": "自動計算",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "default_value": "",
    "min": "",
    "max": "",
    "placeholder": "",
    "step": "",
    "prepend": "",
    "append": "円"
   }
  ],
  "location": [
   [
    {
     "param": "post_type",
     "operator": "==",
     "value": "portfolio"
    }
   ]
  ],
  "menu_order": 1,
  "position": "normal",
  "style": "default",
  "label_placement": "top",
  "instruction_placement": "label",
  "hide_on_screen": "",
  "active": true,
  "description": "STEP3 見積もり内訳。STEP3パネルから自動入力される。",
  "show_in_rest": 0,
  "display_title": "",
  "allow_ai_access": false,
  "ai_description": ""
 }
]
CARMEL_ESTIMATE_JSON
	, true );
	$equip = json_decode( <<<'CARMEL_EQUIP_JSON'
[
 {
  "key": "group_carmel_equip_extra",
  "title": "追加装備（STEP2連携）",
  "fields": [
   {
    "key": "field_xh1",
    "label": "── ナビ・AV ──",
    "name": "_xh1",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── ナビ・AV ──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_junsei_nav",
    "label": "純正ナビ",
    "name": "junsei_nav",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "純正ナビ": "純正ナビ"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_shagai_nav",
    "label": "社外ナビ",
    "name": "shagai_nav",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "社外ナビ": "社外ナビ"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_carplay",
    "label": "Apple CarPlay",
    "name": "carplay",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "Apple CarPlay": "Apple CarPlay"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_androidauto",
    "label": "Android Auto",
    "name": "androidauto",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "Android Auto": "Android Auto"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_xh2",
    "label": "── 安全・運転支援 ──",
    "name": "_xh2",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── 安全・運転支援 ──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_lane_assist",
    "label": "レーンアシスト",
    "name": "lane_assist",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "レーンアシスト": "レーンアシスト"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_acc",
    "label": "アダプティブクルーズ",
    "name": "acc",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "アダプティブクルーズ": "アダプティブクルーズ"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_corner_sensor",
    "label": "コーナーセンサー",
    "name": "corner_sensor",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "コーナーセンサー": "コーナーセンサー"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_drive_recorder",
    "label": "ドライブレコーダー",
    "name": "drive_recorder",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "ドライブレコーダー": "ドライブレコーダー"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_xh3",
    "label": "── シート ──",
    "name": "_xh3",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── シート ──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_ventilation_seat",
    "label": "ベンチレーションシート",
    "name": "ventilation_seat",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "ベンチレーションシート": "ベンチレーションシート"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_leather_seat",
    "label": "革シート",
    "name": "leather_seat",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "革シート": "革シート"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_xh4",
    "label": "── ルーフ ──",
    "name": "_xh4",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── ルーフ ──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_panorama_roof",
    "label": "パノラマルーフ",
    "name": "panorama_roof",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "パノラマルーフ": "パノラマルーフ"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_xh5",
    "label": "── ライト・キー ──",
    "name": "_xh5",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── ライト・キー ──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_push_start",
    "label": "プッシュスタート",
    "name": "push_start",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "プッシュスタート": "プッシュスタート"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_led_light",
    "label": "LEDヘッドライト",
    "name": "led_light",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "LEDヘッドライト": "LEDヘッドライト"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_fog_lamp",
    "label": "フォグランプ",
    "name": "fog_lamp",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "フォグランプ": "フォグランプ"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_adaptive_light",
    "label": "アダプティブライト",
    "name": "adaptive_light",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "アダプティブライト": "アダプティブライト"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_xh6",
    "label": "── 車歴・状態 ──",
    "name": "_xh6",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── 車歴・状態 ──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_kinen_sha",
    "label": "禁煙車",
    "name": "kinen_sha",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "禁煙車": "禁煙車"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_one_owner",
    "label": "ワンオーナー",
    "name": "one_owner",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "ワンオーナー": "ワンオーナー"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_kirokubo",
    "label": "記録簿あり",
    "name": "kirokubo",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "記録簿あり": "記録簿あり"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_seibi_zumi",
    "label": "整備済み",
    "name": "seibi_zumi",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "整備済み": "整備済み"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_shuufuku_nashi",
    "label": "修復歴なし",
    "name": "shuufuku_nashi",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "修復歴なし": "修復歴なし"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   },
   {
    "key": "field_xh7",
    "label": "── その他 ──",
    "name": "_xh7",
    "aria-label": "",
    "type": "message",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "message": "── その他 ──",
    "new_lines": "wpautop",
    "esc_html": 0
   },
   {
    "key": "field_etc2",
    "label": "ETC2.0",
    "name": "etc2",
    "aria-label": "",
    "type": "checkbox",
    "instructions": "",
    "required": 0,
    "conditional_logic": 0,
    "wrapper": {
     "width": "",
     "class": "",
     "id": ""
    },
    "choices": {
     "ETC2.0": "ETC2.0"
    },
    "default_value": [],
    "return_format": "value",
    "allow_custom": 0,
    "save_custom": 0,
    "toggle": 0,
    "layout": "vertical",
    "custom_choice_button_text": "他を追加"
   }
  ],
  "location": [
   [
    {
     "param": "post_type",
     "operator": "==",
     "value": "portfolio"
    }
   ]
  ],
  "menu_order": 2,
  "position": "normal",
  "style": "default",
  "label_placement": "top",
  "instruction_placement": "label",
  "hide_on_screen": "",
  "active": true,
  "description": "STEP2の装備でACFに無かった項目。名前一致でSTEP2と連動する。",
  "show_in_rest": 0
 }
]
CARMEL_EQUIP_JSON
	, true );
	if ( is_array( $estimate ) && isset( $estimate[0] ) ) { acf_add_local_field_group( $estimate[0] ); }
	if ( is_array( $equip ) && isset( $equip[0] ) ) { acf_add_local_field_group( $equip[0] ); }
}


/* ===================== fee-presets-settings.php ===================== */

/**
 * カーメル：見積「初期費用セット」設定ページ（軽 / 普通車）
 * ---------------------------------------------------------------------------
 * 目的 : 在庫STEP3の見積もりに最初から入れる「定型費用」を、WP管理画面の
 *        専用ページ（GUI）で編集できるようにする。ACF Pro 不要。
 *        セットは A=軽自動車 / B=普通車 の2種。値は wp_options に保存。
 *
 * 初期値 : 添付の御見積書PDF（軽＝ダイハツ テリオスキッド）を参考に設定。
 *          後から本ページでいつでも修正可能。
 *
 * 使い方 : 管理画面メニュー「💴 見積初期費用」→ 軽 / 普通車の各費用を入力 → 保存。
 *          STEP3側スニペットが本設定を読み込み、[軽セット適用]/[普通車セット適用]
 *          ボタンや新規在庫の初期反映に使う（別スニペット step3-fee-apply.php）。
 *
 * 公開API : carmel_get_fee_presets() / carmel_fee_preset_items()
 *
 * 導入 : WPCode の PHP Snippet（Run Everywhere）。
 * ---------------------------------------------------------------------------
 */

/* 定型費用の項目定義（key => [ラベル, 区分]） 区分: hikazei=非課税(預り法定) / kazei=課税(手続代行) */
function carmel_fee_preset_items() {
	return array(
		// ［3］預り法定費用（非課税）の定型分
		'kensa_touroku' => array( '検査登録（預り法定）', 'hikazei' ),
		'shako_yokari'  => array( '車庫証明（預り法定）', 'hikazei' ),
		'number_dai'    => array( 'ナンバー代',            'hikazei' ),
		'kibou_number'  => array( '希望ナンバー(OP)',      'hikazei' ),
		// ［4］手続代行費用（課税）
		'kensa_daiko'   => array( '検査登録手続',          'kazei' ),
		'shako_daiko'   => array( '車庫証明手続',          'kazei' ),
		'nousha'        => array( '納車費用',              'kazei' ),
		'mccs'          => array( 'MCCS',                  'kazei' ),
		'kengai'        => array( '県外登録費',            'kazei' ),
		'shikin_kanri'  => array( '資金管理料金',          'kazei' ),
		'sonota'        => array( 'その他費用',            'kazei' ),
	);
}

/* 初期値（PDF参考）。保存済みが無ければこれを使う */
function carmel_fee_preset_defaults() {
	$kei = array(
		'kensa_touroku' => 1800,
		'shako_yokari'  => 0,
		'number_dai'    => 4400,
		'kibou_number'  => 10000,
		'kensa_daiko'   => 16500,
		'shako_daiko'   => 0,
		'nousha'        => 38500,
		'mccs'          => 80000,
		'kengai'        => 50000,
		'shikin_kanri'  => 0,
		'sonota'        => 0,
	);
	// 普通車は初期は軽と同額（裏側で調整してください）
	$futsu = $kei;
	return array( 'kei' => $kei, 'futsu' => $futsu );
}

/* 保存値（無い項目はデフォルトで補完）を返す */
function carmel_get_fee_presets() {
	$saved    = get_option( 'carmel_fee_presets', array() );
	$defaults = carmel_fee_preset_defaults();
	$items    = array_keys( carmel_fee_preset_items() );
	$out      = array();
	foreach ( array( 'kei', 'futsu' ) as $set ) {
		$out[ $set ] = array();
		foreach ( $items as $k ) {
			if ( isset( $saved[ $set ][ $k ] ) && $saved[ $set ][ $k ] !== '' ) {
				$out[ $set ][ $k ] = (int) $saved[ $set ][ $k ];
			} else {
				$out[ $set ][ $k ] = isset( $defaults[ $set ][ $k ] ) ? (int) $defaults[ $set ][ $k ] : 0;
			}
		}
	}
	return $out;
}

/* メニュー登録 */
add_action( 'admin_menu', 'carmel_fee_presets_menu' );
function carmel_fee_presets_menu() {
	add_menu_page(
		'見積初期費用セット',
		'💴 見積初期費用',
		'manage_options',
		'carmel-fee-presets',
		'carmel_fee_presets_render',
		'dashicons-money-alt',
		58
	);
}

/* 設定登録 */
add_action( 'admin_init', 'carmel_fee_presets_register' );
function carmel_fee_presets_register() {
	register_setting( 'carmel_fee_presets_group', 'carmel_fee_presets', 'carmel_fee_presets_sanitize' );
}

function carmel_fee_presets_sanitize( $input ) {
	$items = array_keys( carmel_fee_preset_items() );
	$out   = array();
	foreach ( array( 'kei', 'futsu' ) as $set ) {
		foreach ( $items as $k ) {
			$v = isset( $input[ $set ][ $k ] ) ? preg_replace( '/[^0-9\-]/', '', (string) $input[ $set ][ $k ] ) : '';
			$out[ $set ][ $k ] = ( $v === '' ) ? 0 : (int) $v;
		}
	}
	return $out;
}

/* 画面描画 */
function carmel_fee_presets_render() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$vals  = carmel_get_fee_presets();
	$items = carmel_fee_preset_items();
	?>
	<div class="wrap">
		<h1>💴 見積初期費用セット（軽 / 普通車）</h1>
		<p>在庫の見積もりに最初から入れる「定型費用」です。STEP3で <b>軽セット / 普通車セット</b> として適用されます。<br>
		   税金・自賠責・リサイクル預託金など<b>車ごとに変わる費用は含みません</b>（車両側で入力）。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'carmel_fee_presets_group' ); ?>
			<table class="widefat striped" style="max-width:720px;">
				<thead>
					<tr><th>費用項目</th><th>区分</th><th style="width:150px;">軽自動車（A）</th><th style="width:150px;">普通車（B）</th></tr>
				</thead>
				<tbody>
				<?php foreach ( $items as $key => $def ) :
					$label = $def[0];
					$kbn   = ( $def[1] === 'hikazei' ) ? '非課税' : '課税';
					?>
					<tr>
						<td><?php echo esc_html( $label ); ?></td>
						<td><?php echo esc_html( $kbn ); ?></td>
						<td><input type="number" name="carmel_fee_presets[kei][<?php echo esc_attr( $key ); ?>]"
							value="<?php echo esc_attr( $vals['kei'][ $key ] ); ?>" style="width:120px;text-align:right;"> 円</td>
						<td><input type="number" name="carmel_fee_presets[futsu][<?php echo esc_attr( $key ); ?>]"
							value="<?php echo esc_attr( $vals['futsu'][ $key ] ); ?>" style="width:120px;text-align:right;"> 円</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( '保存' ); ?>
		</form>
	</div>
	<?php
}


/* ===================== step-ui-acf-bridge.php ===================== */

/**
 * カーメル在庫：STEP UI ↔ ACF ブリッジ（統合版）
 * ---------------------------------------------------------------------------
 * 対象 : carmelonline.jp / カスタム投稿タイプ portfolio（在庫）の編集画面
 * 役割 : 「車両入力 STEP UI」(#carmel_step_ui) の入力を、実際に保存される
 *        ACF フィールドへリアルタイムに反映する。
 *
 *   1) 基本情報（STEP1）  : メーカー/車種/年式/走行/色/排気量/車検/ミッション/駆動
 *   2) タイトル自動生成    : 「信用回復ローン ◯◯ … 管理番号」
 *   3) 装備（STEP2）       : 表示専用チェック → 対応する ACF チェックボックス
 *   4) 既存レコードの初期反映: ACF の状態を STEP UI 側へ戻す（装備・基本情報）
 *
 * これまで別々だった2スニペット
 *   - carmel_step1_autofill（基本情報＋タイトル：PHP）
 *   - step-ui-equip-bridge.js（装備：JSスニペット）
 * を1本に統合。ヘルパー（setAcf/tickAcf）もトリガーも一本化している。
 *
 * 導入（WPCode）:
 *   コードタイプ「PHP Snippet」/ 挿入位置「自動挿入・どこでも(Run Everywhere)」
 *   で本ファイルの中身を貼り付けて有効化。
 *   旧スニペット2本（上記）は無効化してよい。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_step_ui_acf_bridge' );
add_action( 'admin_footer-post-new.php', 'carmel_step_ui_acf_bridge' );

function carmel_step_ui_acf_bridge() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}

	global $post;
	$kanri = ( $post && $post->ID ) ? get_post_meta( $post->ID, 'kanri_bango', true ) : '';
	?>
	<script>
	(function ($) {
		'use strict';

		var KANRI = <?php echo wp_json_encode( $kanri ); ?>;

		/* STEP1 入力ID → ACF data-name（テキスト/セレクト系） */
		var BASIC_MAP = {
			cs_maker:        'marker',
			cs_year:         'year',
			cs_mileage:      'mileage',
			cs_color:        'color',
			cs_displacement: 'displacement',
			cs_inspection:   'inspection',
			cs_mission:      'mission',
			cs_kudou:        'kudou'
			// 'type'（型式）は 車種＋グレード を結合して別途セット
		};

		/* 装備の連動は「名前一致」を基本にする：
		   STEP2 の装備名と ACF チェックボックスの表示ラベルが同じなら自動で連動。
		   → 全装備をチェックしても、名前が一致するものは全部 ACF に入る。

		   下の EQUIP_ALIAS は「STEP2 と ACF で呼び名が違う」ものだけの別名表。
		   （STEP2の装備名 → ACF の data-name）。名前一致で拾えないものを補う。 */
		var EQUIP_ALIAS = {
			'自動ブレーキ': 'shoutotu',          // ACF: 衝突被害軽減ブレーキ
			'衝突軽減ブレーキ': 'shoutotu',
			'360度カメラ': 'kamera4',            // ACF: 全周囲カメラ
			'全方位カメラ': 'kamera4',
			'パワーバックドア': 'gate',          // ACF: 電動トランク・リアゲート
			'電動リアゲート': 'gate',
			'純正アルミ': 'almi',                // ACF: アルミホイール
			'電動シート': 'seat'                 // ACF: 運転席電動シート
		};

		/* 文字正規化（空白除去・小文字化）して名前一致の精度を上げる */
		function norm( s ) {
			return ( s == null ? '' : String( s ) ).replace( /[\s　]+/g, '' ).toLowerCase();
		}

		/* ACF 側の全チェックボックスを「ラベル名 → 要素」で索引化（装備の名前一致用） */
		var ACF_EQUIP_INDEX = {};
		function buildAcfEquipIndex() {
			ACF_EQUIP_INDEX = {};
			$( '.acf-field input[type="checkbox"]' ).each( function () {
				var $cb = $( this );
				var byLabel = norm( $cb.closest( 'label' ).text() );
				var byValue = norm( $cb.val() );
				if ( byLabel && ! ( byLabel in ACF_EQUIP_INDEX ) ) { ACF_EQUIP_INDEX[ byLabel ] = this; }
				if ( byValue && ! ( byValue in ACF_EQUIP_INDEX ) ) { ACF_EQUIP_INDEX[ byValue ] = this; }
			} );
		}

		/* チェックボックス要素をオン/オフ */
		function setCheckbox( $cb, on ) {
			if ( ! $cb.length || $cb.prop( 'checked' ) === on ) { return; }
			$cb.prop( 'checked', on );
			$cb.closest( 'label' ).toggleClass( 'selected', on );
			$cb.trigger( 'change' );
		}

		/* 装備名 → 対応する ACF チェックボックス（名前一致 → 別名表）をオン/オフ */
		function tickEquipByName( name, on ) {
			var cb = ACF_EQUIP_INDEX[ norm( name ) ];
			if ( cb ) { setCheckbox( $( cb ), on ); return true; }
			var dn = EQUIP_ALIAS[ $.trim( name ) ];
			if ( dn ) { tickAcf( dn, on ); return true; }
			return false;
		}

		/* STEP2 のチェック要素から装備名を取得（value 優先、無ければラベル文字） */
		function equipName( $chk ) {
			var v = $.trim( $chk.val() );
			return v || $.trim( $chk.closest( 'label' ).text() );
		}

		/* ------------------------------------------------------------------ */
		/* ヘルパー                                                            */
		/* ------------------------------------------------------------------ */

		function v( id ) {
			var el = document.getElementById( id );
			return el ? ( el.value || '' ).trim() : '';
		}

		/* テキスト/セレクト系 ACF へ値をセット（空は ACF 側も空に揃える） */
		function setAcf( fieldName, value ) {
			var $field = $( '.acf-field[data-name="' + fieldName + '"]' );
			if ( ! $field.length ) { return; }

			var $input = $field.find( 'input[type="text"], input[type="number"], input[type="url"], textarea' ).first();
			if ( $input.length ) {
				if ( $input.val() === value ) { return; }
				$input.val( value ).trigger( 'input' ).trigger( 'change' );
				return;
			}

			var $select = $field.find( 'select' ).first();
			if ( $select.length ) {
				if ( $select.val() === value ) { return; }
				$select.val( value ).trigger( 'change' );
				if ( $select.hasClass( 'select2-hidden-accessible' ) ) {
					$select.trigger( 'change.select2' );
				}
			}
		}

		/* チェックボックス系 ACF をオン/オフ */
		function tickAcf( dataName, on ) {
			var $field = $( '.acf-field[data-name="' + dataName + '"]' );
			if ( ! $field.length ) { return; }
			var $cb = $field.find( 'input[type="checkbox"]' ).first();
			if ( ! $cb.length || $cb.prop( 'checked' ) === on ) { return; }
			$cb.prop( 'checked', on );
			$cb.closest( 'label' ).toggleClass( 'selected', on );
			$cb.trigger( 'change' );
		}

		/* ------------------------------------------------------------------ */
		/* 同期処理                                                            */
		/* ------------------------------------------------------------------ */

		/* 基本情報（STEP1）→ ACF */
		function syncBasic() {
			Object.keys( BASIC_MAP ).forEach( function ( id ) {
				setAcf( BASIC_MAP[ id ], v( id ) );
			} );
			// 型式 = 車種＋グレード
			var model = v( 'cs_car_model' );
			var grade = v( 'cs_grade' );
			setAcf( 'type', grade ? ( model + ' ' + grade ) : model );
		}

		/* タイトル自動生成 */
		function buildTitle() {
			var parts = [ '信用回復ローン' ];
			if ( v( 'cs_maker' ) )     { parts.push( v( 'cs_maker' ) ); }
			if ( v( 'cs_car_model' ) ) { parts.push( v( 'cs_car_model' ) ); }
			if ( v( 'cs_grade' ) )     { parts.push( v( 'cs_grade' ) ); }
			if ( v( 'cs_year' ) )      { parts.push( v( 'cs_year' ) ); }
			if ( v( 'cs_color' ) )     { parts.push( v( 'cs_color' ) ); }
			if ( v( 'cs_mileage' ) ) {
				var n = parseInt( v( 'cs_mileage' ).replace( /[^0-9]/g, '' ), 10 );
				parts.push( isNaN( n ) ? v( 'cs_mileage' ) : n.toLocaleString() + 'km' );
			}
			if ( KANRI ) { parts.push( KANRI ); }
			return parts.join( ' ' );
		}

		function setTitle() {
			var title = buildTitle();
			if ( ! title || title === '信用回復ローン' ) { return; }
			var input = document.getElementById( 'title' );
			if ( ! input ) { return; }
			input.value = title;
			input.dispatchEvent( new Event( 'input',  { bubbles: true } ) );
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			var ph = document.getElementById( 'title-prompt-text' );
			if ( ph ) { ph.style.display = 'none'; }
		}

		/* 装備（STEP2）→ ACF（全件、名前一致＋別名） */
		function syncEquip() {
			$( '.cs-equip-check' ).each( function () {
				tickEquipByName( equipName( $( this ) ), $( this ).is( ':checked' ) );
			} );
		}

		/* まとめて同期（基本＋装備＋タイトル） */
		function syncAll() {
			syncBasic();
			syncEquip();
			setTitle();
		}

		/* ------------------------------------------------------------------ */
		/* 既存レコードの初期反映（ACF → STEP UI）                              */
		/* ------------------------------------------------------------------ */

		function prefillEquipFromAcf() {
			$( '.cs-equip-check' ).each( function () {
				var $s   = $( this );
				var name = equipName( $s );
				var cb   = ACF_EQUIP_INDEX[ norm( name ) ];
				var checked = false;
				if ( cb ) {
					checked = $( cb ).is( ':checked' );
				} else {
					var dn = EQUIP_ALIAS[ $.trim( name ) ];
					if ( dn ) {
						checked = $( '.acf-field[data-name="' + dn + '"]' ).find( 'input[type="checkbox"]' ).first().is( ':checked' );
					}
				}
				if ( checked ) { $s.prop( 'checked', true ); }
			} );
		}

		/* ------------------------------------------------------------------ */
		/* 初期化・イベント                                                    */
		/* ------------------------------------------------------------------ */

		$( function () {
			if ( ! document.getElementById( 'carmel_step_ui' ) ) { return; }

			// ACF チェックボックスの索引を作成（名前一致用）
			buildAcfEquipIndex();

			// 既存装備を STEP2 に戻す
			prefillEquipFromAcf();

			// STEP1 → STEP2 ボタン（管理番号の注意喚起つき）
			document.addEventListener( 'click', function ( e ) {
				var btn = e.target.closest( '[onclick*="csStep(2)"]' );
				if ( ! btn ) { return; }
				if ( ! KANRI ) {
					alert(
						'先に「下書き保存」を押して管理番号を発番してから「次へ」を押すと、' +
						'タイトルに管理番号が入ります。\n' +
						'（このまま進めると管理番号なしでタイトルが入ります）'
					);
				}
				setTimeout( function () {
					syncBasic();
					setTitle();
				}, 80 );
			}, true );

			// 装備チェック変更で即同期
			$( document ).on( 'change', '.cs-equip-check', syncEquip );

			// ステップ移動（次へ/戻る/ナビ）で取りこぼし防止のため全同期
			$( document ).on( 'click', '.cs-nav-btn, .cs-btn-next, .cs-btn-back', function () {
				setTimeout( syncAll, 60 );
			} );

			// 全リセットで装備 ACF もオフ
			$( document ).on( 'click', '#cs-reset-all-btn', function () {
				setTimeout( function () {
					$( '.cs-equip-check' ).each( function () {
						tickEquipByName( equipName( $( this ) ), false );
					} );
				}, 80 );
			} );

			// 保存直前に最終同期（STEP2以降で直した値の取りこぼし防止）
			$( '#post' ).on( 'submit', syncAll );
		} );

	})( jQuery );
	</script>
	<?php
}


/* ===================== step3-estimate.php ===================== */

/**
 * カーメル在庫：STEP3「見積もり明細」パネル（自動計算 → ACF反映）
 * ---------------------------------------------------------------------------
 * 対象 : carmelonline.jp / portfolio（在庫）編集画面の「車両入力 STEP UI」
 * 役割 : STEP3 に詳細な見積もり入力パネルを表示し、金額を入れると
 *          ・消費税（課税対象×10%）
 *          ・支払総額
 *          ・月々支払額（実質年率対応／0なら均等割り）
 *        をリアルタイム計算して、見積もり明細ACF（acf-estimate-fields.json で
 *        作成した est_* フィールド）へ書き込む。あわせて既存フィールド
 *          total（月々のお支払い） ← 月々支払額
 *          keihi（諸経費）         ← 諸費用合計（課税諸費用＋非課税＋消費税）
 *          recicle（リサイクル料） ← リサイクル預託金
 *        も更新する。
 *
 * 前提 : acf-estimate-fields.json を ACF にインポート済み（est_* フィールド）。
 *
 * 計算 :
 *   課税対象   = max(0, 本体 − 値引き) ＋ 課税諸費用合計
 *   消費税     = round(課税対象 × 10%)
 *   非課税合計 = 自動車税＋自賠責＋重量税＋印紙＋リサイクル
 *   支払総額   = 課税対象 ＋ 消費税 ＋ 非課税合計 − 下取り
 *   ローン元金 = max(0, 支払総額 − 頭金)
 *   月々       = 年率>0 → 元利均等(月利=年率/12, n=回数) / 年率=0 → ceil(元金/回数)
 *
 * 導入（WPCode）:
 *   コードタイプ「PHP Snippet」/ 挿入位置「自動挿入・どこでも(Run Everywhere)」。
 *   STEP3パネルの差し込み位置を指定したい場合は、STEP3のHTML内に
 *     <div id="cs-est-mount"></div>
 *   を置く。無ければ #carmel_step_ui の末尾に自動追加する。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_step3_estimate' );
add_action( 'admin_footer-post-new.php', 'carmel_step3_estimate' );

function carmel_step3_estimate() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}
	?>
	<style>
	/* 「見積もり明細」ACFボックスを非表示（入力はDOMに残るので保存はされる） */
	#acf-group_carmel_estimate { display:none !important; }
	#cs-est { margin-top:12px; border:1px solid #d9dee5; border-radius:8px; background:#fff; }
	#cs-est .cs-est-h { padding:10px 14px; background:#1f2d3d; color:#fff; border-radius:8px 8px 0 0; font-weight:700; }
	#cs-est .cs-est-grp { padding:6px 14px 2px; font-weight:700; color:#1f2d3d; border-top:1px solid #eef1f4; margin-top:4px; }
	#cs-est .cs-est-row { display:flex; align-items:center; gap:8px; padding:4px 14px; }
	#cs-est .cs-est-row label { flex:1 1 auto; font-size:13px; color:#333; }
	#cs-est .cs-est-row .cs-est-in { width:140px; text-align:right; padding:5px 8px; border:1px solid #c5ccd4; border-radius:5px; }
	#cs-est .cs-est-row .yen { width:1.5em; color:#666; font-size:12px; }
	#cs-est .cs-est-calc input { background:#f2f7ff; font-weight:700; border-color:#9cbdf0; }
	#cs-est .cs-est-total { padding:10px 14px; display:flex; justify-content:space-between; align-items:center;
		background:#fff7e6; border-top:2px solid #f0c36d; border-radius:0 0 8px 8px; }
	#cs-est .cs-est-total b { font-size:20px; color:#c0392b; }
	#cs-est .cs-est-note { padding:2px 14px 10px; font-size:11px; color:#888; }
	</style>
	<script>
	(function ($) {
		'use strict';

		// 入力項目（cs_est_<key> → ACF data-name est_<key>）
		var IN = [
			'honntai','nebiki','shitadori',
			'jidoshazei','jibaiseki','juuryouzei','inshi','recycle',
			'touroku_daiko','shako','nousha','kensa_daiko','seibi','hoshou',
			'atamakin','kaisuu','nenritsu'
		];
		// 自動計算（出力）項目
		var OUT = ['shouhizei','total','getsugaku'];

		var TAXABLE_FEES = ['touroku_daiko','shako','nousha','kensa_daiko','seibi','hoshou'];
		var NONTAX_FEES  = ['jidoshazei','jibaiseki','juuryouzei','inshi','recycle'];

		function yen(n){ return (isFinite(n)?Math.round(n):0).toLocaleString(); }

		function n(id){
			var el = document.getElementById('cs_est_'+id);
			if (!el) return 0;
			var v = parseFloat(String(el.value).replace(/[^0-9.\-]/g,''));
			return isNaN(v) ? 0 : v;
		}

		// テキスト/数値ACFへ反映（step-ui-acf-bridge.php と同じ流儀）
		function setAcf(dataName, value){
			var $f = $('.acf-field[data-name="'+dataName+'"]');
			if (!$f.length) return;
			var $i = $f.find('input[type="text"], input[type="number"], textarea').first();
			if (!$i.length) return;
			var s = (value===''||value===null||value===undefined) ? '' : String(value);
			if ($i.val() === s) return;
			$i.val(s).trigger('input').trigger('change');
		}

		function calc(){
			var honntai=n('honntai'), nebiki=n('nebiki'), shitadori=n('shitadori');
			var taxFees=0; TAXABLE_FEES.forEach(function(k){ taxFees+=n(k); });
			var nonTax=0;  NONTAX_FEES.forEach(function(k){ nonTax+=n(k); });

			var taxableBase = Math.max(0, honntai - nebiki) + taxFees;
			var shouhizei   = Math.round(taxableBase * 0.10);
			var total       = taxableBase + shouhizei + nonTax - shitadori;
			if (total < 0) total = 0;

			var principal = Math.max(0, total - n('atamakin'));
			var kaisuu    = Math.max(0, Math.floor(n('kaisuu')));
			var nenritsu  = n('nenritsu');
			var getsugaku = 0;
			if (kaisuu > 0){
				if (nenritsu > 0){
					var r = (nenritsu/100)/12;
					getsugaku = principal * r / (1 - Math.pow(1+r, -kaisuu));
				} else {
					getsugaku = principal / kaisuu;
				}
				getsugaku = Math.ceil(getsugaku);
			}

			// 出力欄へ反映
			setOut('shouhizei', shouhizei);
			setOut('total', total);
			setOut('getsugaku', getsugaku);

			// 合計表示
			$('#cs-est-total-val').text(yen(total));
			$('#cs-est-month-val').text(yen(getsugaku));

			// 見積もり明細ACF（est_*）へ
			IN.concat(OUT).forEach(function(k){
				setAcf('est_'+k, document.getElementById('cs_est_'+k) ? n(k) : '');
			});
			// 計算結果も est_* へ（n() は出力欄も読む）
			setAcf('est_shouhizei', shouhizei);
			setAcf('est_total', total);
			setAcf('est_getsugaku', getsugaku);

			// 既存フィールドへミラー
			setAcf('total',   yen(getsugaku) + '円');                 // 月々のお支払い
			setAcf('keihi',   yen(taxFees + nonTax + shouhizei) + '円'); // 諸経費合計
			setAcf('recicle', n('recycle') ? yen(n('recycle')) + '円' : '');
		}

		function setOut(id, val){
			var el = document.getElementById('cs_est_'+id);
			if (el && el.value !== String(val)) el.value = val;
		}

		// 既存レコード：ACF(est_*) の値をパネルへ初期反映
		function prefill(){
			IN.concat(OUT).forEach(function(k){
				var $f = $('.acf-field[data-name="est_'+k+'"]');
				if (!$f.length) return;
				var v = $f.find('input').first().val();
				var el = document.getElementById('cs_est_'+k);
				if (el && v !== '' && v != null) el.value = v;
			});
		}

		function row(id, label, append){
			return '<div class="cs-est-row">'+
				'<label for="cs_est_'+id+'">'+label+'</label>'+
				'<input type="number" inputmode="numeric" class="cs-est-in" id="cs_est_'+id+'">'+
				'<span class="yen">'+(append||'円')+'</span></div>';
		}
		function calcRow(id, label){
			return '<div class="cs-est-row cs-est-calc">'+
				'<label for="cs_est_'+id+'">'+label+'</label>'+
				'<input type="number" class="cs-est-in" id="cs_est_'+id+'" readonly>'+
				'<span class="yen">円</span></div>';
		}

		function html(){
			return '<div id="cs-est">'+
				'<div class="cs-est-h">📝 見積もり明細</div>'+

				'<div class="cs-est-grp">車両</div>'+
				row('honntai','車両本体価格')+
				row('nebiki','値引き')+
				row('shitadori','下取り価格')+

				'<div class="cs-est-grp">法定費用（非課税）</div>'+
				row('jidoshazei','自動車税（環境性能割・種別割）')+
				row('jibaiseki','自賠責保険料')+
				row('juuryouzei','自動車重量税')+
				row('inshi','登録時印紙代')+
				row('recycle','リサイクル預託金')+

				'<div class="cs-est-grp">諸費用（課税）</div>'+
				row('touroku_daiko','登録代行費用')+
				row('shako','車庫証明代行')+
				row('nousha','納車費用')+
				row('kensa_daiko','検査登録手続代行')+
				row('seibi','点検整備費用')+
				row('hoshou','保証料')+

				'<div class="cs-est-grp">計算結果（自動）</div>'+
				calcRow('shouhizei','消費税（課税対象×10%）')+
				calcRow('total','支払総額')+

				'<div class="cs-est-grp">ローン</div>'+
				row('atamakin','頭金')+
				row('kaisuu','支払回数','回')+
				row('nenritsu','実質年率（0で金利なし）','%')+
				calcRow('getsugaku','月々支払額')+

				'<div class="cs-est-total">'+
					'<span>月々 <b id="cs-est-month-val">0</b> 円</span>'+
					'<span>支払総額 <b id="cs-est-total-val">0</b> 円</span>'+
				'</div>'+
				'<div class="cs-est-note">※ 支払総額・消費税・月々支払額は自動計算です。'+
				'各金額を保存すると「見積もり明細」ACFと、月々＝total / 諸経費＝keihi / リサイクル料＝recicle に反映されます。</div>'+
			'</div>';
		}

		$(function(){
			var ui = document.getElementById('carmel_step_ui');
			if (!ui) return;

			var $mount = $('#cs-est-mount');
			if ($mount.length) $mount.html(html());
			else $(ui).append(html());

			prefill();
			calc();

			$(document).on('input change', '#cs-est input', calc);
			// 保存直前にも再計算（取りこぼし防止）
			$('#post').on('submit', calc);
		});

	})(jQuery);
	</script>
	<?php
}


/* ===================== step-ui-kaisuu-fix.php ===================== */

/**
 * カーメル在庫：STEP UI「支払回数」セレクトの並び・選択肢を修正
 * ---------------------------------------------------------------------------
 * 症状 : 支払回数プルダウンの並びがバラバラ（60→72→84→48→36→24→12）で、
 *        120回までの選択肢が無い。
 * 対応 : #carmel_step_ui 内の「◯回」だけで構成された <select> を自動検出し、
 *        12〜120回（12刻み）の昇順に作り直す。現在の選択値は維持する。
 *        ＝既存STEP UIのHTMLを書き換えずJSだけで整える（id非依存）。
 *
 * 導入 : WPCode の PHP Snippet（Run Everywhere）。統合版にも内包済み。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_kaisuu_fix' );
add_action( 'admin_footer-post-new.php', 'carmel_kaisuu_fix' );

function carmel_kaisuu_fix() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}
	?>
	<script>
	(function ($) {
		'use strict';

		// 昇順・12刻み・120回まで
		var COUNTS = [12, 24, 36, 48, 60, 72, 84, 96, 108, 120];

		// 「◯回」だけで構成された select か判定（支払回数セレクトの検出）
		function isKaisuuSelect($sel) {
			var $opts = $sel.find('option');
			if ($opts.length < 2) return false;
			var kai = 0, other = 0;
			$opts.each(function () {
				var t = $.trim($(this).text());
				if (/^\d+\s*回$/.test(t)) kai++;
				else if (t !== '') other++; // 空（プレースホルダ）は許容
			});
			return kai >= 2 && other === 0;
		}

		function rebuild($sel) {
			var $first = $sel.find('option').first();
			var valHasKai = /回/.test(String($first.val())); // value が "60回" 形式か "60" 形式か
			var cur = String($sel.val() || '').replace(/[^0-9]/g, ''); // 現在値（数字）

			$sel.empty();
			COUNTS.forEach(function (n) {
				var val = valHasKai ? (n + '回') : String(n);
				$sel.append($('<option>').val(val).text(n + '回'));
			});

			// 選択値を復元（無ければ 60回）
			var want = cur || '60';
			var target = valHasKai ? (want + '回') : want;
			if (!$sel.find('option[value="' + target + '"]').length) {
				target = valHasKai ? '60回' : '60';
			}
			$sel.val(target).trigger('change');
		}

		$(function () {
			var ui = document.getElementById('carmel_step_ui');
			if (!ui) return;
			$(ui).find('select').each(function () {
				var $sel = $(this);
				if (isKaisuuSelect($sel)) rebuild($sel);
			});
		});

	})(jQuery);
	</script>
	<?php
}


/* ===================== step4-shop-bridge.php ===================== */

/**
 * カーメル在庫：STEP4「担当店舗」→ 店舗情報を在庫ACFへ自動反映
 * ---------------------------------------------------------------------------
 * 対象 : carmelonline.jp / portfolio（在庫）編集画面の「車両入力 STEP UI」STEP4(販売情報)
 * 症状 : STEP4 で担当店舗を選んでも、下の店舗系フィールド
 *          shop（販売店） / tel（電話番号） / line-link（LINEリンク） /
 *          contact-link（問い合わせリンク）
 *        に反映されず、通常の「更新」で保存されない。
 *
 * 対応 : 公開中の shop 投稿（店舗）の情報をサーバ側でまとめてJSへ出力し、
 *        担当店舗セレクトを変更した瞬間／「店舗情報取得」クリック時に、
 *        対応する在庫ACFへ反映する。ACFの実フィールドへ書くので、
 *        通常の「更新」でそのまま保存される。
 *
 * 担当店舗セレクトの検出:
 *   id/class に依存せず、#carmel_step_ui 内で
 *   「店舗を選択」プレースホルダを持つ <select>、または
 *   option の value/表示が shop 投稿と一致する <select> を自動検出。
 *
 * 導入（WPCode）:
 *   コードタイプ「PHP Snippet」/ 挿入位置「自動挿入・どこでも(Run Everywhere)」。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_step4_shop_bridge' );
add_action( 'admin_footer-post-new.php', 'carmel_step4_shop_bridge' );

function carmel_step4_shop_bridge() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}

	// 全店舗（shop投稿）の情報を取得
	$shops = get_posts( array(
		'post_type'   => 'shop',
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'menu_order title',
		'order'       => 'ASC',
	) );

	$has_acf = function_exists( 'get_field' );
	$map = array();
	foreach ( $shops as $s ) {
		$get = function ( $key ) use ( $s, $has_acf ) {
			$v = $has_acf ? get_field( $key, $s->ID ) : get_post_meta( $s->ID, $key, true );
			return is_string( $v ) ? trim( $v ) : ( $v ? $v : '' );
		};
		$map[ (string) $s->ID ] = array(
			'name'    => $get( 'name' ) ?: $s->post_title,
			'tel'     => $get( 'tel' ),
			'line'    => $get( 'line_link' ),
			'contact' => $get( 'contact-link' ),
		);
	}
	?>
	<script>
	(function ($) {
		'use strict';

		var SHOPS = <?php echo wp_json_encode( $map ); ?>;

		// 店舗名 → 情報（表示名で引く逆引き）
		var SHOPS_BY_NAME = {};
		Object.keys( SHOPS ).forEach( function ( id ) {
			var nm = ( SHOPS[ id ].name || '' ).trim();
			if ( nm ) { SHOPS_BY_NAME[ nm ] = SHOPS[ id ]; }
		} );

		// テキスト/URL/textarea 系 ACF へ反映
		function setAcfText( dataName, value ) {
			var $f = $( '.acf-field[data-name="' + dataName + '"]' );
			if ( ! $f.length ) { return; }
			var $i = $f.find( 'input[type="text"], input[type="url"], input[type="tel"], textarea' ).first();
			if ( ! $i.length ) { return; }
			var s = ( value == null ) ? '' : String( value );
			if ( $i.val() === s ) { return; }
			$i.val( s ).trigger( 'input' ).trigger( 'change' );
		}

		// セレクト系 ACF（販売店 shop）へ反映：value 一致 → 無ければ表示名一致
		function setAcfSelect( dataName, optValue, optName ) {
			$( '.acf-field[data-name="' + dataName + '"]' ).each( function () {
				var $sel = $( this ).find( 'select' ).first();
				if ( ! $sel.length ) { return; }

				var matched = null;
				$sel.find( 'option' ).each( function () {
					var $o = $( this );
					if ( optValue && String( $o.val() ) === String( optValue ) ) { matched = $o.val(); return false; }
					if ( optName && $.trim( $o.text() ) === $.trim( optName ) ) { matched = $o.val(); }
				} );
				if ( matched === null ) { return; }
				if ( $sel.val() === matched ) { return; }
				$sel.val( matched ).trigger( 'change' );
				if ( $sel.hasClass( 'select2-hidden-accessible' ) ) {
					$sel.trigger( 'change.select2' );
				}
			} );
		}

		// 担当店舗の選択 → 在庫ACFへ一括反映
		function applyShop( val, text ) {
			var shop = SHOPS[ String( val ) ];
			if ( ! shop && text ) { shop = SHOPS_BY_NAME[ $.trim( text ) ]; }
			if ( ! shop ) { return; }

			setAcfSelect( 'shop', val, shop.name );   // 販売店
			setAcfText( 'tel', shop.tel );            // 電話番号
			setAcfText( 'line-link', shop.line );     // LINEリンク
			setAcfText( 'contact-link', shop.contact ); // 問い合わせリンク
		}

		// 担当店舗セレクトを検出（id非依存）
		function findShopSelect( ui ) {
			var $all = $( ui ).find( 'select' );

			// 1) 「店舗を選択」プレースホルダを持つ select
			var $byPlaceholder = $all.filter( function () {
				return $( this ).find( 'option' ).filter( function () {
					return /店舗を選択/.test( $( this ).text() );
				} ).length > 0;
			} );
			if ( $byPlaceholder.length ) { return $byPlaceholder.first(); }

			// 2) option の value/表示が shop 投稿と一致する select
			var ids = Object.keys( SHOPS );
			var names = ids.map( function ( id ) { return ( SHOPS[ id ].name || '' ).trim(); } );
			var $byMatch = $all.filter( function () {
				return $( this ).find( 'option' ).filter( function () {
					var v = String( $( this ).val() );
					var t = $.trim( $( this ).text() );
					return ids.indexOf( v ) !== -1 || names.indexOf( t ) !== -1;
				} ).length > 0;
			} );
			return $byMatch.first();
		}

		$( function () {
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ! ui || ! Object.keys( SHOPS ).length ) { return; }

			var $sel = findShopSelect( ui );
			if ( ! $sel.length ) { return; }

			function run() {
				var val = $sel.val();
				var text = $sel.find( 'option:selected' ).text();
				applyShop( val, text );
			}

			// 担当店舗を選んだ瞬間に反映
			$sel.on( 'change', run );

			// 「店舗情報取得」ボタンでも反映（id非依存・テキストで検出）
			$( ui ).find( 'button, a, input[type="button"]' ).filter( function () {
				return /店舗情報取得|店舗情報を取得|取得/.test( $( this ).text() || $( this ).val() || '' );
			} ).on( 'click', function () {
				setTimeout( run, 30 );
			} );

			// 既に選択済みなら初期反映（空の項目だけ埋める想定でも上書きはしない）
			if ( $sel.val() ) {
				// 既存レコードの手動編集を尊重し、ここでは自動上書きしない。
				// 必要なら次の1行を有効化して初期反映:
				// run();
			}
		} );

	})( jQuery );
	</script>
	<?php
}


/* ===================== tidy-admin.php ===================== */

/**
 * カーメル：在庫編集画面をスタッフ向けに短く整える
 * ---------------------------------------------------------------------------
 * 目的 : 保存先ACFボックスが多く編集画面が縦に長い問題を解消。
 *        STEP UI を主入力とし、保存先ACFは「非表示」または「折りたたみ」。
 *        ※ 装備データはDBに保存されたまま → フロント詳細ページの表示には影響なし。
 *
 *  - 追加装備（STEP2連携）/ 見積もり明細 … STEP UIが全て担うので非表示
 *    （入力はDOMに残るので保存はされる）
 *  - 装備系ACF（オーディオ/カーナビ/シート/ドア外装/基本装備/安全装備）
 *    … 初期状態で折りたたみ（必要時にクリックで開ける）
 *
 * 導入 : WPCode PHP Snippet（Run Everywhere）。統合版にも内包。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_tidy_admin' );
add_action( 'admin_footer-post-new.php', 'carmel_tidy_admin' );

function carmel_tidy_admin() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}
	?>
	<style>
	/* STEP UI が全て担うボックスは非表示（入力はDOMに残るので保存は維持・フロント表示に影響なし） */
	#acf-group_carmel_equip_extra,
	#acf-group_carmel_estimate { display:none !important; }
	</style>
	<script>
	(function ($) {
		'use strict';
		// 初期で折りたたむ装備系ACFグループ（ACFメタボックスID = acf-<group_key>）
		var COLLAPSE = [
			'acf-group_65ccb2f7bc7b0', // オーディオ
			'acf-group_65ccb275da4af', // カーナビ・TV
			'acf-group_65ccb37dee5a8', // シート・内装
			'acf-group_65ccb40340cec', // ドア・外装
			'acf-group_65cc94fadb356', // 基本装備
			'acf-group_65ccb11906c02'  // 安全装備
		];
		$( function () {
			if ( ! document.getElementById( 'carmel_step_ui' ) ) { return; }
			COLLAPSE.forEach( function ( id ) {
				var el = document.getElementById( id );
				if ( el ) { el.classList.add( 'closed' ); }
			} );
		} );
	})( jQuery );
	</script>
	<?php
}


/* ===================== equipment-display.php ===================== */

/**
 * カーメル：車両詳細ページ 装備表示（ショートコード [carmel_equipment]）
 * ---------------------------------------------------------------------------
 * 目的 : 在庫(portfolio)のチェック済み装備を、カテゴリ別に自動で一覧表示する。
 *        全装備フィールドを1か所で定義しているので、今後増えても1行追加でOK。
 *
 * 使い方 :
 *   - 車両詳細テンプレート/固定ページ/エディタに  [carmel_equipment]  を置く
 *   - 特定投稿を指定: [carmel_equipment id="5049"]
 *   - PHPテンプレートから直接:  echo carmel_render_equipment( get_the_ID() );
 *
 * 表示 : チェックの入った装備だけを、カテゴリ見出し付きのバッジで表示。
 *        1件もチェックが無いカテゴリは出力しない。
 *
 * 導入 : WPCode PHP Snippet（Run Everywhere）。統合版にも内包。
 * ---------------------------------------------------------------------------
 */

/* 装備フィールド定義（カテゴリ => [ data-name => 表示名 ]）
   ※ STEP2/ACF と同じ data-name。装備を増やすときはここに1行足すだけ。 */
function carmel_equipment_map() {
	return array(
		'ナビ・TV' => array(
			'nav' => 'DVDナビ', 'nav2' => 'HDDナビ', 'nav3' => 'メモリーナビ',
			'nav4' => 'ワンセグTV', 'nav5' => 'フルセグTV', 'monitar' => '後席モニター',
			'junsei_nav' => '純正ナビ', 'shagai_nav' => '社外ナビ',
		),
		'オーディオ' => array(
			'blueray' => 'ブルーレイ再生', 'dvd' => 'DVD再生', 'sarver' => 'ミュージックサーバー',
			'usb' => 'USB・iPod接続', 'cd' => 'CD再生', 'bluetooth' => 'Bluetooth', 'hdmi' => 'HDMI',
			'carplay' => 'Apple CarPlay', 'androidauto' => 'Android Auto',
		),
		'安全・運転支援' => array(
			'airbag' => '運転席エアバッグ', 'airbag2' => '助手席エアバッグ', 'airbag3' => 'サイドエアバッグ',
			'airbag4' => 'カーテンエアバッグ', 'abs' => 'ABS', 'esc' => '横滑り防止装置',
			'shoutotu' => '衝突被害軽減ブレーキ', 'asist' => 'ナイトビューアシスト',
			'kamera' => 'フロントカメラ', 'kamera2' => 'サイドカメラ', 'kamera3' => 'バックカメラ',
			'kamera4' => '全周囲カメラ', 'asist2' => 'パーキングアシスト',
			'sensar' => 'フロントセンサー', 'sensar2' => 'リアセンサー',
			'lane_assist' => 'レーンアシスト', 'acc' => 'アダプティブクルーズ',
			'corner_sensor' => 'コーナーセンサー', 'drive_recorder' => 'ドライブレコーダー',
		),
		'快適・内装' => array(
			'aircon' => 'エアコン', 'aircon2' => 'Wエアコン', 'stea' => 'パワーステアリング',
			'window' => 'パワーウィンドウ', 'keyless' => 'キーレス', 'smartkey' => 'スマートキー',
			'push_start' => 'プッシュスタート', 'controll' => 'クルーズコントロール',
			'stop' => 'アイドリングストップ', 'tounan' => '盗難防止装置',
			'seat' => '運転席電動シート', 'seat2' => '助手席電動シート', 'seat3' => '後席電動シート',
			'memory' => 'シートメモリー', 'heater' => 'シートヒーター', 'otman' => 'オットマン',
			'work' => 'ウォークスルー', 'ventilation_seat' => 'ベンチレーションシート',
			'leather_seat' => '革シート',
		),
		'外装・ドア・ルーフ' => array(
			'door' => '電動スライドドア', 'door2' => 'イージークローザードア',
			'gate' => '電動トランク・リアゲート', 'almi' => 'アルミホイール', 'earo' => 'エアロパーツ',
			'down' => 'ローダウン', 'liftup' => 'リフトアップ', 'sunroof' => 'サンルーフ',
			'panorama_roof' => 'パノラマルーフ', 'led_light' => 'LEDヘッドライト',
			'fog_lamp' => 'フォグランプ', 'adaptive_light' => 'アダプティブライト',
		),
		'その他・車歴' => array(
			'etc' => 'ETC', 'etc2' => 'ETC2.0', 'kinen_sha' => '禁煙車', 'one_owner' => 'ワンオーナー',
			'kirokubo' => '記録簿あり', 'seibi_zumi' => '整備済み', 'shuufuku_nashi' => '修復歴なし',
		),
	);
}

/* 値が「チェック済み」か判定 */
function carmel_equip_checked( $name, $pid ) {
	$v = function_exists( 'get_field' ) ? get_field( $name, $pid ) : get_post_meta( $pid, $name, true );
	if ( is_array( $v ) ) { return ! empty( $v ); }
	return ! empty( $v ) && $v !== '0';
}

/* 装備一覧HTMLを返す */
function carmel_render_equipment( $pid = 0 ) {
	if ( ! $pid ) { $pid = get_the_ID(); }
	if ( ! $pid ) { return ''; }

	$map = carmel_equipment_map();
	$out = '';
	foreach ( $map as $cat => $items ) {
		$badges = '';
		foreach ( $items as $name => $label ) {
			if ( carmel_equip_checked( $name, $pid ) ) {
				$badges .= '<li class="carmel-eq-item">' . esc_html( $label ) . '</li>';
			}
		}
		if ( $badges ) {
			$out .= '<div class="carmel-eq-cat"><h4 class="carmel-eq-h">' . esc_html( $cat ) . '</h4>'
				. '<ul class="carmel-eq-list">' . $badges . '</ul></div>';
		}
	}
	if ( ! $out ) { return ''; }

	return '<div class="carmel-equipment">' . $out . '</div>';
}

/* ショートコード [carmel_equipment id="123"] */
add_shortcode( 'carmel_equipment', 'carmel_equipment_shortcode' );
function carmel_equipment_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts );
	return carmel_render_equipment( (int) $atts['id'] );
}

/* フロント用スタイル */
add_action( 'wp_head', 'carmel_equipment_style' );
function carmel_equipment_style() {
	?>
	<style>
	.carmel-equipment { margin:16px 0; }
	.carmel-eq-cat { margin-bottom:14px; }
	.carmel-eq-h { margin:0 0 6px; font-size:15px; font-weight:700; color:#1f2d3d;
		border-left:4px solid #c0392b; padding-left:8px; }
	.carmel-eq-list { list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; gap:6px; }
	.carmel-eq-item { display:inline-block; padding:4px 10px; font-size:13px; line-height:1.4;
		background:#f3f6fa; border:1px solid #d9e0e8; border-radius:14px; color:#333; }
	</style>
	<?php
}

