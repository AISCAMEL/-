<?php
/**
 * Plugin Name: カーメル在庫 STEP UI 一式
 * Description: 在庫STEP UI一式（基本情報・装備・見積もり・担当店舗・複数画像・全体図確認）、支払回数、諸経費設定、画面整理、フロント[carmel_equipment]/[carmel_gallery]、金額コンマ、1枚目アイキャッチ。ACF自動登録。
 * Version: 1.4.0
 * Author: カーメル
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }
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


/* ===================== fee-settings.php ===================== */

/**
 * カーメル：諸経費設定（旧「見積初期費用」）
 * ---------------------------------------------------------------------------
 * AS-NET「かんたん見積作成」設定画面と同じ構成。普通自動車／軽自動車の2列で
 * 各費用の初期値を管理。STEP3見積もりや点検整備A/B選択の元データになる。
 * 値は wp_options 'carmel_fee_settings' に保存。ACF Pro 不要。
 *
 * 公開API: carmel_get_fee_settings()
 * 導入   : WPCode PHP Snippet（Run Everywhere）／統合プラグインに内包。
 * ---------------------------------------------------------------------------
 */

/* 車種別の費用項目（key => [ラベル, グループ]） */
function carmel_fee_items() {
	return array(
		'yotei_rieki'     => array( '予定利益（上乗せ・税抜）', 'rieki' ),
		'shaken_seibi'    => array( '車検整備費用（税抜）',     'seibi' ),
		'nousha_seibi'    => array( '納車整備費用（税抜）',     'seibi' ),
		'kensa_touroku'   => array( '検査登録（印紙代）',       'houtei' ),
		'shako_inshi'     => array( '車庫証明（印紙代）',       'houtei' ),
		'shitadori_inshi' => array( '下取車手続・処分（印紙代）', 'houtei' ),
		'number_dai'      => array( 'ナンバー代',               'houtei' ),
		'kibou_number'    => array( '希望ナンバー（OP）',       'houtei' ),
		'kensa_daiko'     => array( '検査登録手続代行（税抜）', 'daiko' ),
		'shako_daiko'     => array( '車庫証明手続代行（税抜）', 'daiko' ),
		'shitadori_daiko' => array( '下取車手続・処分代行（税抜）', 'daiko' ),
		'shitadori_satei' => array( '下取車査定料（税抜）',     'daiko' ),
		'shikin_kanri'    => array( '資金管理料金（税抜）',     'daiko' ),
		'nousha'          => array( '納車費用（税抜）',         'daiko' ),
		'mccs'            => array( 'MCCS（税抜）',             'daiko' ),
		'kengai'          => array( '県外登録費（税抜）',       'daiko' ),
		'hoshou_hiyou'    => array( '中古車保証費用（税抜）',   'hoshou' ),
	);
}

function carmel_fee_group_labels() {
	return array(
		'rieki'  => '予定利益',
		'seibi'  => '整備費用（税抜）',
		'houtei' => '預り法定費用（非課税）',
		'daiko'  => '手続代行費用（税抜）',
		'hoshou' => '保証',
	);
}

/* 初期値 */
function carmel_fee_defaults() {
	$futsu = array(
		'yotei_rieki' => 150000, 'shaken_seibi' => 100000, 'nousha_seibi' => 50000,
		'kensa_touroku' => 1800, 'shako_inshi' => 2750, 'shitadori_inshi' => 0,
		'number_dai' => 4400, 'kibou_number' => 10000,
		'kensa_daiko' => 16500, 'shako_daiko' => 0, 'shitadori_daiko' => 0,
		'shitadori_satei' => 0, 'shikin_kanri' => 0, 'nousha' => 38500,
		'mccs' => 80000, 'kengai' => 50000, 'hoshou_hiyou' => 0,
	);
	$kei = $futsu;
	$kei['yotei_rieki'] = 100000;
	$kei['shako_inshi'] = 0; // 軽は車庫証明印紙なし
	return array(
		'tax_mode'      => 'excl',  // excl=税抜 / incl=税込
		'teiki_tenken'  => 'yes',
		'hoshou_umu'    => 'no',
		'hoshou_naiyou' => '',
		'jibai_months'  => 25,
		'loan_rate'     => 12.5,
		'tax_rate'      => 10,
		'shop'          => array(
			'name' => 'カーメル', 'address' => '福島県いわき市四倉町細谷字大町1番',
			'tel' => '050-1807-2533', 'tantou' => '吉田一平', 'sekinin' => '吉田一平',
			'url' => 'carmelonline.jp',
		),
		'futsu'         => $futsu,
		'kei'           => $kei,
	);
}

/* 設定値（デフォルト補完）取得 */
function carmel_get_fee_settings() {
	$saved = get_option( 'carmel_fee_settings', array() );
	$def   = carmel_fee_defaults();
	$out   = array(
		'tax_mode'      => isset( $saved['tax_mode'] ) ? $saved['tax_mode'] : $def['tax_mode'],
		'teiki_tenken'  => isset( $saved['teiki_tenken'] ) ? $saved['teiki_tenken'] : $def['teiki_tenken'],
		'hoshou_umu'    => isset( $saved['hoshou_umu'] ) ? $saved['hoshou_umu'] : $def['hoshou_umu'],
		'hoshou_naiyou' => isset( $saved['hoshou_naiyou'] ) ? $saved['hoshou_naiyou'] : $def['hoshou_naiyou'],
		'jibai_months'  => isset( $saved['jibai_months'] ) ? $saved['jibai_months'] : $def['jibai_months'],
		'loan_rate'     => isset( $saved['loan_rate'] ) ? $saved['loan_rate'] : $def['loan_rate'],
		'tax_rate'      => isset( $saved['tax_rate'] ) ? $saved['tax_rate'] : $def['tax_rate'],
		'shop'          => array(),
		'futsu'         => array(),
		'kei'           => array(),
	);
	foreach ( $def['shop'] as $k => $val ) {
		$out['shop'][ $k ] = isset( $saved['shop'][ $k ] ) ? $saved['shop'][ $k ] : $val;
	}
	foreach ( array( 'futsu', 'kei' ) as $type ) {
		foreach ( array_keys( carmel_fee_items() ) as $k ) {
			$out[ $type ][ $k ] = ( isset( $saved[ $type ][ $k ] ) && $saved[ $type ][ $k ] !== '' )
				? (int) $saved[ $type ][ $k ]
				: (int) $def[ $type ][ $k ];
		}
	}
	return $out;
}

add_action( 'admin_menu', 'carmel_fee_settings_menu' );
function carmel_fee_settings_menu() {
	add_menu_page( '諸経費設定', '💴 諸経費設定', 'manage_options', 'carmel-fee-settings', 'carmel_fee_settings_render', 'dashicons-money-alt', 58 );
}

add_action( 'admin_init', 'carmel_fee_settings_register' );
function carmel_fee_settings_register() {
	register_setting( 'carmel_fee_settings_group', 'carmel_fee_settings', 'carmel_fee_settings_sanitize' );
}

function carmel_fee_settings_sanitize( $in ) {
	$out = array(
		'tax_mode'      => ( isset( $in['tax_mode'] ) && $in['tax_mode'] === 'incl' ) ? 'incl' : 'excl',
		'teiki_tenken'  => ( isset( $in['teiki_tenken'] ) && $in['teiki_tenken'] === 'no' ) ? 'no' : 'yes',
		'hoshou_umu'    => ( isset( $in['hoshou_umu'] ) && $in['hoshou_umu'] === 'yes' ) ? 'yes' : 'no',
		'hoshou_naiyou' => isset( $in['hoshou_naiyou'] ) ? sanitize_text_field( $in['hoshou_naiyou'] ) : '',
		'jibai_months'  => isset( $in['jibai_months'] ) ? (int) $in['jibai_months'] : 25,
		'loan_rate'     => isset( $in['loan_rate'] ) ? (float) $in['loan_rate'] : 12.5,
		'tax_rate'      => isset( $in['tax_rate'] ) ? (float) $in['tax_rate'] : 10,
		'shop'          => array(),
		'futsu'         => array(),
		'kei'           => array(),
	);
	foreach ( array( 'name', 'address', 'tel', 'tantou', 'sekinin', 'url' ) as $k ) {
		$out['shop'][ $k ] = isset( $in['shop'][ $k ] ) ? sanitize_text_field( $in['shop'][ $k ] ) : '';
	}
	foreach ( array( 'futsu', 'kei' ) as $type ) {
		foreach ( array_keys( carmel_fee_items() ) as $k ) {
			$val = isset( $in[ $type ][ $k ] ) ? preg_replace( '/[^0-9]/', '', (string) $in[ $type ][ $k ] ) : '';
			$out[ $type ][ $k ] = ( $val === '' ) ? 0 : (int) $val;
		}
	}
	return $out;
}

function carmel_fee_settings_render() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$v      = carmel_get_fee_settings();
	$items  = carmel_fee_items();
	$glabel = carmel_fee_group_labels();
	?>
	<div class="wrap">
		<h1>💴 諸経費設定（普通自動車 / 軽自動車）</h1>
		<p>この設定が STEP3 見積もりや点検整備A/B（普通車=A・軽=B）の初期値になります。AS-NET見積画面と同じ構成です。</p>
		<form method="post" action="options.php">
			<?php settings_fields( 'carmel_fee_settings_group' ); ?>

			<h2>課税対象金額の入力方法</h2>
			<label><input type="radio" name="carmel_fee_settings[tax_mode]" value="incl" <?php checked( $v['tax_mode'], 'incl' ); ?>> 税込みで入力</label>
			<label><input type="radio" name="carmel_fee_settings[tax_mode]" value="excl" <?php checked( $v['tax_mode'], 'excl' ); ?>> 税抜きで入力</label>

			<table class="widefat striped" style="max-width:760px;margin-top:14px;">
				<thead><tr><th>費用項目</th><th style="width:150px;">普通自動車</th><th style="width:150px;">軽自動車</th></tr></thead>
				<tbody>
				<?php
				$curgrp = '';
				foreach ( $items as $key => $def ) :
					if ( $def[1] !== $curgrp ) {
						$curgrp = $def[1];
						echo '<tr><td colspan="3" style="background:#eef1f4;font-weight:700;">' . esc_html( $glabel[ $curgrp ] ) . '</td></tr>';
						if ( $curgrp === 'seibi' ) {
							echo '<tr><td>定期点検整備の有無</td><td colspan="2">'
								. '<label><input type="radio" name="carmel_fee_settings[teiki_tenken]" value="yes" ' . checked( $v['teiki_tenken'], 'yes', false ) . '> 有</label>　'
								. '<label><input type="radio" name="carmel_fee_settings[teiki_tenken]" value="no" ' . checked( $v['teiki_tenken'], 'no', false ) . '> 無</label></td></tr>';
						}
						if ( $curgrp === 'hoshou' ) {
							echo '<tr><td>保証の有無</td><td colspan="2">'
								. '<label><input type="radio" name="carmel_fee_settings[hoshou_umu]" value="yes" ' . checked( $v['hoshou_umu'], 'yes', false ) . '> 有</label>　'
								. '<label><input type="radio" name="carmel_fee_settings[hoshou_umu]" value="no" ' . checked( $v['hoshou_umu'], 'no', false ) . '> 無</label></td></tr>';
							echo '<tr><td>保証の内容</td><td colspan="2"><input type="text" name="carmel_fee_settings[hoshou_naiyou]" value="' . esc_attr( $v['hoshou_naiyou'] ) . '" style="width:100%;"></td></tr>';
						}
					}
					?>
					<tr>
						<td><?php echo esc_html( $def[0] ); ?></td>
						<td><input type="number" name="carmel_fee_settings[futsu][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $v['futsu'][ $key ] ); ?>" style="width:120px;text-align:right;"> 円</td>
						<td><input type="number" name="carmel_fee_settings[kei][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $v['kei'][ $key ] ); ?>" style="width:120px;text-align:right;"> 円</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2>その他の設定</h2>
			<table class="form-table" style="max-width:760px;">
				<tr><th>自賠責保険料の算出月数</th><td><input type="number" name="carmel_fee_settings[jibai_months]" value="<?php echo esc_attr( $v['jibai_months'] ); ?>" style="width:80px;"> ヶ月（車検無しの車両の場合）</td></tr>
				<tr><th>ローン計算標準金利</th><td><input type="number" step="0.1" name="carmel_fee_settings[loan_rate]" value="<?php echo esc_attr( $v['loan_rate'] ); ?>" style="width:80px;"> ％</td></tr>
				<tr><th>消費税率</th><td><input type="number" step="0.1" name="carmel_fee_settings[tax_rate]" value="<?php echo esc_attr( $v['tax_rate'] ); ?>" style="width:80px;"> ％</td></tr>
			</table>

			<h2>販売店情報（見積書に表示する内容）</h2>
			<table class="form-table" style="max-width:760px;">
				<tr><th>販売店名</th><td><input type="text" name="carmel_fee_settings[shop][name]" value="<?php echo esc_attr( $v['shop']['name'] ); ?>" style="width:100%;"></td></tr>
				<tr><th>住所</th><td><input type="text" name="carmel_fee_settings[shop][address]" value="<?php echo esc_attr( $v['shop']['address'] ); ?>" style="width:100%;"></td></tr>
				<tr><th>電話番号</th><td><input type="text" name="carmel_fee_settings[shop][tel]" value="<?php echo esc_attr( $v['shop']['tel'] ); ?>" style="width:240px;"></td></tr>
				<tr><th>見積担当者</th><td><input type="text" name="carmel_fee_settings[shop][tantou]" value="<?php echo esc_attr( $v['shop']['tantou'] ); ?>" style="width:240px;"></td></tr>
				<tr><th>責任者</th><td><input type="text" name="carmel_fee_settings[shop][sekinin]" value="<?php echo esc_attr( $v['shop']['sekinin'] ); ?>" style="width:240px;"></td></tr>
				<tr><th>販売店URL</th><td><input type="text" name="carmel_fee_settings[shop][url]" value="<?php echo esc_attr( $v['shop']['url'] ); ?>" style="width:100%;"></td></tr>
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

			// 見積もりに数字が入っているか（全項目0なら未入力とみなす）
			var hasInput = false;
			IN.forEach(function(k){ if (n(k) > 0) hasInput = true; });

			// 見積もり明細ACF（est_*）へ ※未入力なら触らない（既存値を消さない）
			if (hasInput) {
				IN.concat(OUT).forEach(function(k){
					setAcf('est_'+k, document.getElementById('cs_est_'+k) ? n(k) : '');
				});
				setAcf('est_shouhizei', shouhizei);
				setAcf('est_total', total);
				setAcf('est_getsugaku', getsugaku);
			}

			// 既存フィールドへミラー ※0/空のときは上書きしない（手入力の月額等を保護）
			if (getsugaku > 0) { setAcf('total', yen(getsugaku) + '円'); }      // 月々のお支払い
			var feesTotal = taxFees + nonTax + shouhizei;
			if (feesTotal > 0) { setAcf('keihi', yen(feesTotal) + '円'); }       // 諸経費合計
			if (n('recycle') > 0) { setAcf('recicle', yen(n('recycle')) + '円'); } // リサイクル料
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


/* ===================== step5-gallery.php ===================== */

/**
 * カーメル：STEP5 画像ギャラリー（複数画像アップ / 1枚目=アイキャッチ）
 * ---------------------------------------------------------------------------
 * 目的 : 在庫STEP UIの中で、車の画像を複数枚アップロード・並べ替え・削除できる。
 *        保存先は post_meta 'carmel_gallery'（カンマ区切りの添付ID）。
 *        1枚目はアイキャッチに自動同期（featured-from-gallery 側で対応）。
 *        フロント表示は [carmel_gallery] ショートコード。
 *
 * 導入 : WPCode PHP Snippet（Run Everywhere）／統合プラグインに内包。
 * ---------------------------------------------------------------------------
 */

/* メディアアップローダを読み込む */
add_action( 'admin_enqueue_scripts', 'carmel_step5_enqueue' );
function carmel_step5_enqueue( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) { return; }
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && $screen->post_type === 'portfolio' ) {
		wp_enqueue_media();
	}
}

/* STEP5 パネル出力 */
add_action( 'admin_footer-post.php',     'carmel_step5_gallery' );
add_action( 'admin_footer-post-new.php', 'carmel_step5_gallery' );
function carmel_step5_gallery() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) { return; }

	global $post;
	$raw = $post ? get_post_meta( $post->ID, 'carmel_gallery', true ) : '';
	$ids = $raw ? array_filter( array_map( 'intval', explode( ',', $raw ) ) ) : array();

	$thumbs = '';
	foreach ( $ids as $id ) {
		$url = wp_get_attachment_image_url( $id, 'thumbnail' );
		if ( ! $url ) { continue; }
		$thumbs .= carmel_step5_thumb_html( $id, $url );
	}
	?>
	<style>
	#cs-gallery { margin:14px 0; border:1px solid #d9dee5; border-radius:8px; background:#fff; }
	#cs-gallery .cs-g-h { padding:10px 14px; background:#1f2d3d; color:#fff; border-radius:8px 8px 0 0; font-weight:700; }
	#cs-gallery .cs-g-body { padding:12px 14px; }
	#cs-gallery .cs-g-add { background:#2271b1; color:#fff; border:0; padding:8px 16px; border-radius:5px; cursor:pointer; font-weight:700; }
	#cs-g-thumbs { display:flex; flex-wrap:wrap; gap:10px; margin-top:12px; }
	#cs-g-thumbs .cs-g-thumb { position:relative; width:110px; }
	#cs-g-thumbs .cs-g-thumb img { width:110px; height:82px; object-fit:cover; border:1px solid #ccc; border-radius:6px; display:block; }
	#cs-g-thumbs .cs-g-thumb .cs-g-badge { position:absolute; top:3px; left:3px; background:#c0392b; color:#fff; font-size:10px; padding:1px 6px; border-radius:8px; }
	#cs-g-thumbs .cs-g-thumb .cs-g-tools { display:flex; gap:4px; margin-top:3px; }
	#cs-g-thumbs .cs-g-thumb button { flex:1; font-size:11px; padding:2px 0; cursor:pointer; border:1px solid #bbb; border-radius:4px; background:#f6f7f7; }
	#cs-g-thumbs .cs-g-thumb .cs-g-del { color:#c0392b; }
	#cs-gallery .cs-g-note { font-size:11px; color:#888; margin-top:8px; }
	</style>
	<script>
	(function ($) {
		'use strict';

		function thumbHtml( id, url ) {
			return '<div class="cs-g-thumb" data-id="' + id + '">' +
				'<img src="' + url + '">' +
				'<span class="cs-g-badge" style="display:none;">1枚目</span>' +
				'<div class="cs-g-tools">' +
				'<button type="button" class="cs-g-first" title="1枚目にする">★</button>' +
				'<button type="button" class="cs-g-del" title="削除">×</button>' +
				'</div></div>';
		}

		function syncHidden() {
			var ids = [];
			$( '#cs-g-thumbs .cs-g-thumb' ).each( function () { ids.push( $( this ).data( 'id' ) ); } );
			$( '#cs-gallery-ids' ).val( ids.join( ',' ) );
			$( '#cs-g-thumbs .cs-g-thumb .cs-g-badge' ).hide();
			$( '#cs-g-thumbs .cs-g-thumb' ).first().find( '.cs-g-badge' ).show();
		}

		$( function () {
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ! ui ) { return; }

			var html = '<div id="cs-gallery">' +
				'<div class="cs-g-h">📷 車両画像（複数可・1枚目がアイキャッチ）</div>' +
				'<div class="cs-g-body">' +
				'<button type="button" class="cs-g-add">＋ 画像を追加</button>' +
				'<input type="hidden" id="cs-gallery-ids" name="carmel_gallery" value="<?php echo esc_attr( implode( ',', $ids ) ); ?>">' +
				'<?php echo wp_create_nonce( 'carmel_gallery_save' ) ? '<input type="hidden" name="carmel_gallery_nonce" value="' . esc_attr( wp_create_nonce( 'carmel_gallery_save' ) ) . '">' : ''; ?>' +
				'<div id="cs-g-thumbs"><?php echo str_replace( array( "\n", "\r" ), '', addslashes( $thumbs ) ); ?></div>' +
				'<div class="cs-g-note">ドラッグ不要。★で1枚目に、×で削除。並びの1枚目がアイキャッチ＆フロント先頭になります。</div>' +
				'</div></div>';

			var $mount = $( '#cs-gallery-mount' );
			if ( $mount.length ) { $mount.html( html ); } else { $( ui ).append( html ); }
			syncHidden();

			var frame;
			$( document ).on( 'click', '.cs-g-add', function ( e ) {
				e.preventDefault();
				if ( frame ) { frame.open(); return; }
				frame = wp.media( {
					title: '車両画像を選択',
					multiple: true,
					library: { type: 'image' },
					button: { text: 'この画像を追加' }
				} );
				frame.on( 'select', function () {
					var sel = frame.state().get( 'selection' );
					sel.each( function ( att ) {
						var a = att.attributes;
						if ( $( '#cs-g-thumbs .cs-g-thumb[data-id="' + a.id + '"]' ).length ) { return; }
						var url = ( a.sizes && a.sizes.thumbnail ) ? a.sizes.thumbnail.url : a.url;
						$( '#cs-g-thumbs' ).append( thumbHtml( a.id, url ) );
					} );
					syncHidden();
				} );
				frame.open();
			} );

			$( document ).on( 'click', '.cs-g-del', function () {
				$( this ).closest( '.cs-g-thumb' ).remove();
				syncHidden();
			} );

			$( document ).on( 'click', '.cs-g-first', function () {
				var $t = $( this ).closest( '.cs-g-thumb' );
				$( '#cs-g-thumbs' ).prepend( $t );
				syncHidden();
			} );
		} );

	})( jQuery );
	</script>
	<?php
}

/* サムネイルHTML（初期表示用） */
function carmel_step5_thumb_html( $id, $url ) {
	return '<div class="cs-g-thumb" data-id="' . esc_attr( $id ) . '">' .
		'<img src="' . esc_url( $url ) . '">' .
		'<span class="cs-g-badge" style="display:none;">1枚目</span>' .
		'<div class="cs-g-tools">' .
		'<button type="button" class="cs-g-first" title="1枚目にする">★</button>' .
		'<button type="button" class="cs-g-del" title="削除">×</button>' .
		'</div></div>';
}

/* 保存 */
add_action( 'save_post_portfolio', 'carmel_step5_save', 10, 1 );
function carmel_step5_save( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! isset( $_POST['carmel_gallery_nonce'] ) || ! wp_verify_nonce( $_POST['carmel_gallery_nonce'], 'carmel_gallery_save' ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	if ( ! isset( $_POST['carmel_gallery'] ) ) { return; }

	$ids = array_filter( array_map( 'intval', explode( ',', (string) $_POST['carmel_gallery'] ) ) );
	if ( $ids ) {
		update_post_meta( $post_id, 'carmel_gallery', implode( ',', $ids ) );
	} else {
		delete_post_meta( $post_id, 'carmel_gallery' );
	}
}

/* フロント表示 [carmel_gallery] */
add_shortcode( 'carmel_gallery', 'carmel_gallery_shortcode' );
function carmel_gallery_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts );
	$pid  = (int) $atts['id'] ? (int) $atts['id'] : get_the_ID();
	if ( ! $pid ) { return ''; }
	$raw = get_post_meta( $pid, 'carmel_gallery', true );
	$ids = $raw ? array_filter( array_map( 'intval', explode( ',', $raw ) ) ) : array();
	if ( ! $ids ) { return ''; }

	$out = '<div class="carmel-gallery">';
	foreach ( $ids as $id ) {
		$full  = wp_get_attachment_image_url( $id, 'large' );
		$thumb = wp_get_attachment_image( $id, 'medium', false, array( 'class' => 'carmel-gallery-img', 'loading' => 'lazy' ) );
		if ( ! $thumb ) { continue; }
		$out .= $full
			? '<a class="carmel-gallery-link" href="' . esc_url( $full ) . '">' . $thumb . '</a>'
			: $thumb;
	}
	$out .= '</div>';
	return $out;
}

add_action( 'wp_head', 'carmel_gallery_style' );
function carmel_gallery_style() {
	?>
	<style>
	.carmel-gallery { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:8px; margin:16px 0; }
	.carmel-gallery img { width:100%; height:110px; object-fit:cover; border-radius:6px; display:block; }
	</style>
	<?php
}


/* ===================== step6-review.php ===================== */

/**
 * カーメル：STEP6 確認（全体図）
 * ---------------------------------------------------------------------------
 * 目的 : STEP1〜5で入力した内容を1画面で一覧確認できる「全体図」を表示。
 *        保存（更新）前の最終チェック用。現在のACF/STEPの値をその場で集計表示。
 *
 * 表示 : タイトル / 基本情報 / 装備一覧 / 見積もり / 担当店舗 / 画像枚数
 *
 * 設置 : STEP6領域に <div id="cs-step6-mount"></div> を置くとそこに表示。
 *        無ければ STEP UI 末尾に表示。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_step6_review' );
add_action( 'admin_footer-post-new.php', 'carmel_step6_review' );
function carmel_step6_review() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) { return; }
	?>
	<style>
	#cs-review { margin:14px 0; border:2px solid #1f2d3d; border-radius:8px; background:#fff; }
	#cs-review .cs-rv-h { padding:10px 14px; background:#1f2d3d; color:#fff; border-radius:6px 6px 0 0;
		font-weight:700; display:flex; justify-content:space-between; align-items:center; }
	#cs-review .cs-rv-refresh { background:#3a4a5d; color:#fff; border:0; padding:5px 12px; border-radius:5px; cursor:pointer; font-size:12px; }
	#cs-review .cs-rv-body { padding:12px 14px; }
	#cs-review .cs-rv-sec { margin-bottom:14px; }
	#cs-review .cs-rv-t { font-weight:700; color:#1f2d3d; border-left:4px solid #c0392b; padding-left:8px; margin-bottom:6px; }
	#cs-review .cs-rv-grid { display:grid; grid-template-columns:140px 1fr; gap:2px 10px; font-size:13px; }
	#cs-review .cs-rv-grid .k { color:#666; }
	#cs-review .cs-rv-grid .v { color:#111; font-weight:600; }
	#cs-review .cs-rv-badges { display:flex; flex-wrap:wrap; gap:5px; }
	#cs-review .cs-rv-badge { background:#f3f6fa; border:1px solid #d9e0e8; border-radius:12px; padding:3px 9px; font-size:12px; }
	#cs-review .cs-rv-money { font-size:18px; font-weight:700; color:#c0392b; }
	#cs-review .cs-rv-warn { color:#c0392b; font-size:12px; }
	#cs-review .cs-rv-note { margin-top:6px; padding:8px 12px; background:#fff7e6; border:1px solid #f0c36d; border-radius:6px; font-size:12px; }
	</style>
	<script>
	(function ($) {
		'use strict';

		function acfVal( name ) {
			var $f = $( '.acf-field[data-name="' + name + '"]' ).first();
			if ( ! $f.length ) { return ''; }
			var $sel = $f.find( 'select' ).first();
			if ( $sel.length ) { return $.trim( $sel.find( 'option:selected' ).text() ); }
			var $i = $f.find( 'input[type="text"], input[type="number"], input[type="url"], textarea' ).first();
			return $i.length ? $.trim( $i.val() ) : '';
		}
		function acfRadio( name ) {
			var $f = $( '.acf-field[data-name="' + name + '"]' ).first();
			return $f.length ? $.trim( $f.find( 'input[type="radio"]:checked' ).closest( 'label' ).text() ) : '';
		}
		function yen( s ) {
			var d = String( s == null ? '' : s ).replace( /[^0-9]/g, '' );
			return d ? Number( d ).toLocaleString() + '円' : '—';
		}
		function row( k, v ) {
			return '<div class="k">' + k + '</div><div class="v">' + ( v ? v : '—' ) + '</div>';
		}

		function checkedEquip() {
			var out = [];
			$( '.acf-field input[type="checkbox"]:checked' ).each( function () {
				var t = $.trim( $( this ).closest( 'label' ).text() );
				if ( t && out.indexOf( t ) === -1 ) { out.push( t ); }
			} );
			return out;
		}

		function build() {
			var basic = row( 'メーカー', acfVal( 'marker' ) ) + row( '車種・型式', acfVal( 'type' ) ) +
				row( '年式', acfVal( 'year' ) ) + row( '走行距離', acfVal( 'mileage' ) ) +
				row( '色', acfVal( 'color' ) ) + row( '排気量', acfVal( 'displacement' ) ) +
				row( '車検', acfVal( 'inspection' ) ) + row( 'ミッション', acfVal( 'mission' ) ) +
				row( '駆動', acfVal( 'kudou' ) ) + row( '管理番号', acfVal( 'kanri_bango' ) ) +
				row( 'ステータス', acfRadio( 'stauts' ) || acfVal( 'status' ) );

			var eq = checkedEquip();
			var eqHtml = eq.length
				? '<div class="cs-rv-badges">' + eq.map( function ( e ) { return '<span class="cs-rv-badge">' + e + '</span>'; } ).join( '' ) + '</div>'
				: '<span class="cs-rv-warn">装備が未選択です</span>';

			var total = acfVal( 'est_total' );
			var getsu = acfVal( 'est_getsugaku' );
			var estHtml = '<div class="cs-rv-grid">' +
				'<div class="k">支払総額</div><div class="v cs-rv-money">' + yen( total ) + '</div>' +
				'<div class="k">月々支払</div><div class="v cs-rv-money">' + yen( getsu ) + '</div></div>';

			var shop = row( '販売店', acfVal( 'shop' ) ) + row( 'TEL', acfVal( 'tel' ) ) +
				row( 'LINE', acfVal( 'line-link' ) ) + row( '問い合わせ', acfVal( 'contact-link' ) );

			var gids = ( $( '#cs-gallery-ids' ).val() || '' ).split( ',' ).filter( function ( x ) { return x; } );

			var html =
				'<div class="cs-rv-sec"><div class="cs-rv-t">タイトル</div><div class="v">' +
					( $.trim( $( '#title' ).val() ) || '<span class="cs-rv-warn">未入力</span>' ) + '</div></div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">基本情報</div><div class="cs-rv-grid">' + basic + '</div></div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">装備（' + eq.length + '件）</div>' + eqHtml + '</div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">見積もり</div>' + estHtml + '</div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">担当店舗</div><div class="cs-rv-grid">' + shop + '</div></div>' +
				'<div class="cs-rv-sec"><div class="cs-rv-t">画像</div><div class="v">' + gids.length + ' 枚' +
					( gids.length ? '' : ' <span class="cs-rv-warn">（未登録）</span>' ) + '</div></div>' +
				'<div class="cs-rv-note">内容を確認したら、ページ右上の「更新」または「公開」を押して保存してください。</div>';

			$( '#cs-rv-content' ).html( html );
		}

		$( function () {
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ! ui ) { return; }

			var shell = '<div id="cs-review">' +
				'<div class="cs-rv-h"><span>📋 STEP6 確認（全体図）</span>' +
				'<button type="button" class="cs-rv-refresh">🔄 最新の内容を表示</button></div>' +
				'<div class="cs-rv-body"><div id="cs-rv-content"></div></div></div>';

			var $mount = $( '#cs-step6-mount' );
			if ( $mount.length ) { $mount.html( shell ); } else { $( ui ).append( shell ); }

			build();
			$( document ).on( 'click', '.cs-rv-refresh', build );
			$( document ).on( 'click', '.cs-nav-btn, .cs-btn-next, .cs-btn-back', function () { setTimeout( build, 120 ); } );
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


/* ===================== money-format.php ===================== */

/**
 * カーメル：金額入力のコンマ自動化
 * ---------------------------------------------------------------------------
 * 目的 : 在庫編集画面で金額を「50000」や全角「５００００」で打っても
 *        自動で「50,000」に整形する。全角数字→半角化＋3桁カンマ。
 *
 * 対象 : STEP UI 内の「円」が付く金額入力／会員ローン系の金額テキスト欄。
 *        ※ 計算用の数値フィールド（type=number）はそのまま（計算に影響させない）。
 *
 * 導入 : WPCode PHP Snippet（Run Everywhere）／統合プラグインに内包。
 * ---------------------------------------------------------------------------
 */

add_action( 'admin_footer-post.php',     'carmel_money_format' );
add_action( 'admin_footer-post-new.php', 'carmel_money_format' );

function carmel_money_format() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->post_type !== 'portfolio' ) {
		return;
	}
	?>
	<script>
	(function ($) {
		'use strict';

		// コンマ整形する ACF 金額テキスト欄（data-name）
		var MONEY_FIELDS = [ 'kariire_gaku', 'monthly_pay', 'loan_amount', 'monthly_payment' ];

		// 全角→半角、全角カンマ→半角
		function z2h( s ) {
			return String( s == null ? '' : s )
				.replace( /[０-９]/g, function ( c ) { return String.fromCharCode( c.charCodeAt( 0 ) - 0xFEE0 ); } )
				.replace( /[，]/g, ',' );
		}
		function digits( s ) { return z2h( s ).replace( /[^0-9]/g, '' ); }
		function withCommas( s ) {
			var d = digits( s );
			return d ? Number( d ).toLocaleString( 'en-US' ) : '';
		}

		function attach( inp ) {
			var $i = $( inp );
			if ( ! $i.length || $i.data( 'cmf' ) ) { return; }
			$i.data( 'cmf', 1 );
			$i.on( 'input', function () { this.value = withCommas( this.value ); } );
			$i.on( 'blur',  function () { this.value = withCommas( this.value ); } );
			if ( $i.val() ) { $i.val( withCommas( $i.val() ) ); }
		}

		$( function () {
			// ACF 金額テキスト欄
			MONEY_FIELDS.forEach( function ( dn ) {
				$( '.acf-field[data-name="' + dn + '"]' ).find( 'input[type="text"]' ).each( function () { attach( this ); } );
			} );

			// STEP UI 内：「円」が近くにある金額入力（万円含む）
			var ui = document.getElementById( 'carmel_step_ui' );
			if ( ui ) {
				$( ui ).find( 'input[type="text"]' ).each( function () {
					if ( /円/.test( $( this ).parent().text() ) ) { attach( this ); }
				} );
			}
		} );

	})( jQuery );
	</script>
	<?php
}


/* ===================== featured-from-gallery.php ===================== */

/**
 * カーメル：ギャラリー1枚目を自動でアイキャッチ（featured image）に
 * ---------------------------------------------------------------------------
 * 目的 : 在庫(portfolio)を保存したとき、画像ギャラリーの1枚目を
 *        アイキャッチ画像として自動設定する。
 *
 * 対応ギャラリー（自動判定）:
 *   1) Easy Image Gallery プラグイン（meta: _easy_image_gallery / カンマ区切りID）
 *   2) ACF の画像ギャラリー(gallery)フィールド
 *   3) フィルタ carmel_gallery_ids で独自指定も可
 *
 * 挙動 : 保存時、ギャラリーに画像があれば「常に1枚目」をアイキャッチに同期。
 *        （手動アイキャッチを優先したい場合は CARMEL_FEATURED_OVERWRITE を false に）
 *
 * 導入 : WPCode PHP Snippet（Run Everywhere）／統合プラグインに内包。
 * ---------------------------------------------------------------------------
 */

if ( ! defined( 'CARMEL_FEATURED_OVERWRITE' ) ) {
	define( 'CARMEL_FEATURED_OVERWRITE', true ); // true=常に1枚目に同期 / false=未設定時のみ
}

add_action( 'save_post_portfolio', 'carmel_featured_from_gallery', 20, 1 );
function carmel_featured_from_gallery( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( wp_is_post_revision( $post_id ) ) { return; }

	if ( ! CARMEL_FEATURED_OVERWRITE && has_post_thumbnail( $post_id ) ) { return; }

	$ids = carmel_get_gallery_ids( $post_id );
	if ( empty( $ids ) ) { return; }

	$first = (int) $ids[0];
	if ( $first > 0 && 'attachment' === get_post_type( $first ) ) {
		set_post_thumbnail( $post_id, $first );
	}
}

/* ギャラリーの添付ID配列を取得（自動判定） */
function carmel_get_gallery_ids( $post_id ) {
	// 0) STEP5 ギャラリー（carmel_gallery / カンマ区切りID）
	$cg = get_post_meta( $post_id, 'carmel_gallery', true );
	if ( ! empty( $cg ) ) {
		return array_values( array_filter( array_map( 'intval', explode( ',', $cg ) ) ) );
	}

	// 1) Easy Image Gallery（カンマ区切りID）
	$eig = get_post_meta( $post_id, '_easy_image_gallery', true );
	if ( ! empty( $eig ) ) {
		if ( is_array( $eig ) ) {
			return array_values( array_filter( array_map( 'intval', $eig ) ) );
		}
		return array_values( array_filter( array_map( 'intval', explode( ',', $eig ) ) ) );
	}

	// 2) ACF の gallery フィールド
	if ( function_exists( 'get_field_objects' ) ) {
		$fields = get_field_objects( $post_id );
		if ( is_array( $fields ) ) {
			foreach ( $fields as $f ) {
				if ( isset( $f['type'] ) && 'gallery' === $f['type'] && ! empty( $f['value'] ) ) {
					$out = array();
					foreach ( (array) $f['value'] as $img ) {
						if ( is_array( $img ) && isset( $img['ID'] ) ) { $out[] = (int) $img['ID']; }
						elseif ( is_numeric( $img ) ) { $out[] = (int) $img; }
					}
					if ( $out ) { return $out; }
				}
			}
		}
	}

	// 3) 独自指定（必要なら add_filter で）
	return (array) apply_filters( 'carmel_gallery_ids', array(), $post_id );
}

