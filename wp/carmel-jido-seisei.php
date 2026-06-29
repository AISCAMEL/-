<?php
/**
 * Plugin Name: CARMEL 自動生成（毎日自動）
 * Description: 本体「CARMEL統合管理 v5.7」を使って記事を自動生成・自動投稿するアドオン（WP-Cron）。カーメル管理メニューの中に表示。
 * Version: 8.4
 * Author: CARMEL
 */

if (!defined('ABSPATH')) exit;

if (!defined('CARMEL3_AUTO_OPTION')) {
    define('CARMEL3_AUTO_OPTION', 'carmel_auto_settings');
}
if (!defined('CARMEL3_AUTO_HOOK')) {
    define('CARMEL3_AUTO_HOOK', 'carmel3_auto_generate_cron');
}
if (!defined('CARMEL3_IMG_DEFAULT_MODEL')) {
    define('CARMEL3_IMG_DEFAULT_MODEL', 'google/gemini-2.5-flash-image-preview');
}
// 本文セクション画像を「裏側で1枚ずつ」生成するためのフック
if (!defined('CARMEL3_AUTO_IMGHOOK')) {
    define('CARMEL3_AUTO_IMGHOOK', 'carmel3_auto_img_worker');
}
// 「今すぐ1件だけ生成」を裏側で実行するためのフック（画面を固まらせない）
if (!defined('CARMEL3_AUTO_RUNHOOK')) {
    define('CARMEL3_AUTO_RUNHOOK', 'carmel3_auto_run_single');
}
// 進捗（ただいま生成中…）を保存するオプション名
if (!defined('CARMEL3_PROGRESS_OPTION')) {
    define('CARMEL3_PROGRESS_OPTION', 'carmel3_auto_progress');
}
// 記事の「作り直し」を裏側で実行するフック
if (!defined('CARMEL3_REGEN_HOOK')) {
    define('CARMEL3_REGEN_HOOK', 'carmel3_regen_single');
}
// 本文セクション画像 ACF フィールドキー（編集ページのHTMLから確認した値）
if (!defined('CARMEL3_F_SEC1_IMG')) { define('CARMEL3_F_SEC1_IMG', 'field_69ffb5a4d372b'); } // section_1_image
if (!defined('CARMEL3_F_SEC2_IMG')) { define('CARMEL3_F_SEC2_IMG', 'field_69ffb5e5d372e'); } // section_2_image
if (!defined('CARMEL3_F_SEC3_IMG')) { define('CARMEL3_F_SEC3_IMG', 'field_69ffb8a0c02c3'); } // section_3_image
// CTAボタンURL ACF フィールドキー
if (!defined('CARMEL3_F_CTA1_URL')) { define('CARMEL3_F_CTA1_URL', 'field_69fef07b6cbf3'); } // main_cta_url
if (!defined('CARMEL3_F_CTA2_URL')) { define('CARMEL3_F_CTA2_URL', 'field_69ffb66cd3733'); } // cta_button_url

/* ===== 設定 ===== */

function carmel3_auto_get_settings() {
    $defaults = array(
        'enabled'      => 0,
        'frequency'    => 'carmel_weekly',
        'run_time'     => '09:00',   // 自動生成を実行する時刻（サイトのタイムゾーン）
        'max_tokens_cap' => 12000,   // OpenRouterへの max_tokens 上限（0=制限しない／残高節約・エラー回避）
        'auto_slug_meta' => 1,       // スラッグ（英語URL）とメタディスクリプションを自動生成
        'text_model'   => '',        // 文章生成モデルの上書き（空=本体まかせ。無料モデル指定で残高不足回避）
        'publish'      => 0,
        'gen_images'   => 0,
        'gen_section_images' => 1,
        'eyecatch_copy'      => 0,                    // アイキャッチに訴求コピーを重ねる
        'eyecatch_copy_text' => '自社ローンOK｜全国対応', // 訴求コピー（｜で改行）
        'fix_cta'      => 1,
        'contact_url'  => '',
        'image_model'  => CARMEL3_IMG_DEFAULT_MODEL,
        'banner_field' => 'hero_image',
        'gmb_post'     => 0,
        // 追加で自動生成する投稿タイプ（メディア記事は常に本体エンジンで生成。ここは“追加分”）
        'extra_types'  => array(),
        // 投稿タイプごとの「書き方（AI指示プロンプト）」 array(post_type => 指示文)
        'type_prompts' => array(),
        // 通知（Slack / LINE）
        'notify_on'     => 'draft',   // draft=下書きができた時 / publish=公開した時
        'slack_enabled' => 0,
        'slack_webhook' => '',
        'line_enabled'  => 0,
        'line_token'    => '',
        'line_to'       => '',
        // 左メニューのスリム化：隠すトップメニューのスラッグ一覧
        'hidden_menus' => array(),
        // カーメル管理の中に移設するトップメニューのスラッグ一覧（移設＝中に入れて元は隠す）
        'moved_menus'  => array(),
        // 加盟店ブログ・NEWS を自動でカーメル管理に移設する（おすすめ・既定ON）
        'tidy_default' => 1,
        'eyecatch_w'   => 1200,
        'eyecatch_h'   => 630,
        'banner_w'     => 1200,
        'banner_h'     => 400,
        'sec_w'        => 1200,
        'sec_h'        => 675,
        'cursor'       => 0,
        'queue'        => array(),
        'done'         => array(),
        'last_run'     => '',
        'last_msg'     => '',
    );
    $saved = get_option(CARMEL3_AUTO_OPTION, array());
    if (!is_array($saved)) $saved = array();
    return wp_parse_args($saved, $defaults);
}

function carmel3_auto_save_settings($settings) {
    update_option(CARMEL3_AUTO_OPTION, $settings);
}

function carmel3_auto_parse_queue($text) {
    $rows = array();
    $lines = preg_split('/\r\n|\r|\n/', (string)$text);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        $cols = array_map('trim', explode('|', $line));
        $rows[] = array(
            'account'    => isset($cols[0]) && $cols[0] !== '' ? sanitize_key($cols[0]) : 'main',
            'category'   => isset($cols[1]) ? sanitize_key($cols[1]) : '',
            'keyword'    => isset($cols[2]) ? sanitize_text_field($cols[2]) : '',
            'prefecture' => isset($cols[3]) ? sanitize_text_field($cols[3]) : '',
            'city'       => isset($cols[4]) ? sanitize_text_field($cols[4]) : '',
            'title'      => isset($cols[5]) ? sanitize_text_field($cols[5]) : '',
        );
    }
    return $rows;
}

function carmel3_auto_queue_to_text($queue) {
    $lines = array();
    foreach ((array)$queue as $r) {
        $lines[] = implode(' | ', array(
            isset($r['account']) ? $r['account'] : 'main',
            isset($r['category']) ? $r['category'] : '',
            isset($r['keyword']) ? $r['keyword'] : '',
            isset($r['prefecture']) ? $r['prefecture'] : '',
            isset($r['city']) ? $r['city'] : '',
            isset($r['title']) ? $r['title'] : '',
        ));
    }
    return implode("\n", $lines);
}

/* ===== WP-Cron ===== */

add_filter('cron_schedules', function ($schedules) {
    if (!isset($schedules['carmel_weekly'])) {
        $schedules['carmel_weekly'] = array('interval' => WEEK_IN_SECONDS, 'display' => 'CARMEL 毎週');
    }
    if (!isset($schedules['carmel_twiceweekly'])) {
        $schedules['carmel_twiceweekly'] = array('interval' => (int)(3.5 * DAY_IN_SECONDS), 'display' => 'CARMEL 週2回');
    }
    return $schedules;
});

add_action(CARMEL3_AUTO_HOOK, 'carmel3_auto_run');
// 「今すぐ1件だけ生成」の裏側実行（同じ処理を呼ぶ）
add_action(CARMEL3_AUTO_RUNHOOK, 'carmel3_auto_run');
// 記事の「作り直し」の裏側実行
add_action(CARMEL3_REGEN_HOOK, 'carmel3_regenerate_post', 10, 1);

/* ===== OpenRouterへの max_tokens を送信時に上限制限（残高不足エラー回避・本体は無改変） =====
   本体v5.7が大きすぎる max_tokens（例: 65536）を送ると残高不足で失敗するため、
   WordPressの通信フィルターで「送信直前」に上限を下げる。クレジットを足さずに通せる。 */
add_filter('http_request_args', function ($args, $url) {
    if (strpos((string)$url, 'openrouter.ai') === false) return $args;
    if (empty($args['body'])) return $args;

    $s = carmel3_auto_get_settings();
    $cap = isset($s['max_tokens_cap']) ? (int)$s['max_tokens_cap'] : 0;
    $text_model = isset($s['text_model']) ? trim((string)$s['text_model']) : '';
    if ($cap <= 0 && $text_model === '') return $args; // 何もしない

    $is_chat = (strpos((string)$url, '/chat/completions') !== false) || (strpos((string)$url, '/completions') !== false);

    $apply = function ($body) use ($cap, $text_model, $is_chat) {
        if (!is_array($body)) return $body;
        // 画像生成リクエスト（modalitiesにimage）はモデルを変えない・max_tokensも触らない
        $is_image = isset($body['modalities']) && is_array($body['modalities']) && in_array('image', $body['modalities'], true);
        if ($is_image) return $body;

        if ($cap > 0) {
            if (isset($body['max_tokens']) && (int)$body['max_tokens'] > $cap) {
                $body['max_tokens'] = $cap;
            } elseif ($is_chat && !isset($body['max_tokens'])) {
                $body['max_tokens'] = $cap;
            }
        }
        // テキスト生成モデルの上書き（無料モデル等。残高不足回避）
        if ($text_model !== '' && $is_chat && isset($body['messages'])) {
            $body['model'] = $text_model;
        }
        return $body;
    };

    if (is_array($args['body'])) {
        $args['body'] = $apply($args['body']);
        return $args;
    }
    if (is_string($args['body'])) {
        $body = json_decode($args['body'], true);
        if (!is_array($body)) return $args;
        $before = $body;
        $body = $apply($body);
        if ($body !== $before) {
            $args['body'] = wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }
    return $args;
}, 99, 2);
// 本文セクション画像を裏側で1枚ずつ処理するワーカー
add_action(CARMEL3_AUTO_IMGHOOK, 'carmel3_auto_process_one_image');

// 指定時刻（HH:MM・サイトのタイムゾーン）の「次の実行時刻」をUNIXタイムで返す
function carmel3_next_run_ts($time_str) {
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim((string)$time_str), $m)) {
        $h = 9; $i = 0;
    } else {
        $h = max(0, min(23, (int)$m[1]));
        $i = max(0, min(59, (int)$m[2]));
    }
    $tz  = function_exists('wp_timezone') ? wp_timezone() : new DateTimeZone(date_default_timezone_get());
    try {
        $now = new DateTime('now', $tz);
        $run = new DateTime('now', $tz);
        $run->setTime($h, $i, 0);
        if ($run <= $now) { $run->modify('+1 day'); }
        return $run->getTimestamp();
    } catch (Exception $e) {
        return time() + 60;
    }
}

function carmel3_auto_reschedule() {
    $s = carmel3_auto_get_settings();
    $existing = wp_next_scheduled(CARMEL3_AUTO_HOOK);
    if ($existing) {
        wp_unschedule_event($existing, CARMEL3_AUTO_HOOK);
    }
    if (!empty($s['enabled'])) {
        $recur = in_array($s['frequency'], array('daily', 'carmel_weekly', 'carmel_twiceweekly'), true)
            ? $s['frequency'] : 'carmel_weekly';
        $first = carmel3_next_run_ts(isset($s['run_time']) ? $s['run_time'] : '09:00');
        wp_schedule_event($first, $recur, CARMEL3_AUTO_HOOK);
    }
}

add_action('init', function () {
    $s = carmel3_auto_get_settings();
    if (!empty($s['enabled']) && !wp_next_scheduled(CARMEL3_AUTO_HOOK)) {
        carmel3_auto_reschedule();
    }
});

/* ===== 自動生成の本体 ===== */

/* ===== 進捗（ただいま生成中…）の記録・取得 ===== */

function carmel3_progress_set($step, $pct, $state = 'running', $extra = array()) {
    $p = array_merge(array(
        'state'   => $state,            // running / done / error / idle
        'step'    => (string) $step,
        'pct'     => max(0, min(100, (int) $pct)),
        'time'    => current_time('mysql'),
        'ts'      => time(),
        'post_id' => 0,
        'title'   => '',
    ), $extra);
    update_option(CARMEL3_PROGRESS_OPTION, $p, false);
}

function carmel3_progress_get() {
    $p = get_option(CARMEL3_PROGRESS_OPTION, array());
    return is_array($p) ? $p : array();
}

// 直近の実行ログ（成功/失敗）を最大12件、新しい順で保持
function carmel3_loglist_add($message, $ok = true) {
    $list = get_option('carmel3_auto_loglist', array());
    if (!is_array($list)) $list = array();
    array_unshift($list, array('time' => current_time('mysql'), 'ok' => $ok ? 1 : 0, 'msg' => (string) $message));
    $list = array_slice($list, 0, 12);
    update_option('carmel3_auto_loglist', $list, false);
}

function carmel3_loglist_get() {
    $list = get_option('carmel3_auto_loglist', array());
    return is_array($list) ? $list : array();
}

// 進捗をJSONで返す（画面のポーリング用）
add_action('wp_ajax_carmel3_auto_progress', function () {
    if (!current_user_can('manage_options')) wp_send_json_error(array('msg' => 'no permission'), 403);
    wp_send_json_success(carmel3_progress_get());
});

function carmel3_auto_run() {
    @set_time_limit(300);
    $s = carmel3_auto_get_settings();
    carmel3_progress_set('準備をしています…', 4, 'running');

    if (!function_exists('carmel_generate_article_api')) {
        carmel3_auto_log($s, '失敗: 既存プラグイン(carmel_generate_article_api)が見つかりません。CARMEL統合管理v5.7が有効か確認してください。');
        carmel3_progress_set('エラー: 本体プラグイン(v5.7)が見つかりません', 100, 'error');
        carmel3_loglist_add('本体プラグイン(v5.7)が見つかりません', false);
        return false;
    }
    $queue = array_values((array)$s['queue']);
    if (empty($queue)) {
        carmel3_auto_log($s, '失敗: テーマキューが空です');
        carmel3_progress_set('エラー: テーマキューが空です（テーマを追加してください）', 100, 'error');
        carmel3_loglist_add('テーマキューが空です', false);
        return false;
    }

    $done = isset($s['done']) && is_array($s['done']) ? $s['done'] : array();

    $item = null;
    $picked_key = '';
    foreach ($queue as $row) {
        $key = carmel3_auto_item_key($row);
        if ($key === '') continue;
        if (in_array($key, $done, true)) continue;
        $item = $row;
        $picked_key = $key;
        break;
    }

    if ($item === null) {
        carmel3_auto_log($s, '完了: テーマキューの全テーマを生成済みです（新しいテーマを追加してください）');
        carmel3_progress_set('完了: すべてのテーマを生成済みです（新しいテーマを追加してください）', 100, 'done');
        carmel3_loglist_add('すべてのテーマを生成済み（新しいテーマを追加してください）', true);
        $s['enabled'] = 0;
        carmel3_auto_save_settings($s);
        $existing = wp_next_scheduled(CARMEL3_AUTO_HOOK);
        if ($existing) {
            wp_unschedule_event($existing, CARMEL3_AUTO_HOOK);
        }
        return false;
    }

    $title = $item['title'] !== '' ? $item['title'] : $item['keyword'];
    if ($title === '') {
        carmel3_auto_log($s, "スキップ: テーマはタイトルもキーワードも空");
        carmel3_progress_set('スキップ: テーマが空でした', 100, 'error');
        return false;
    }

    carmel3_progress_set('記事の文章をAIが作成中…（30〜60秒）', 20, 'running', array('title' => $title));

    $req = new WP_REST_Request('POST', '/carmel/v1/generate');
    $req->set_header('Content-Type', 'application/json');
    $req->set_body(wp_json_encode(array(
        'title'      => $title,
        'category'   => $item['category'],
        'account'    => $item['account'] !== '' ? $item['account'] : 'main',
        'prefecture' => $item['prefecture'],
        'city'       => $item['city'],
        'keyword'    => $item['keyword'],
    ), JSON_UNESCAPED_UNICODE));

    $res  = carmel_generate_article_api($req);
    $data = ($res instanceof WP_REST_Response) ? $res->get_data() : array();

    if (empty($data['success'])) {
        $msg = isset($data['message']) ? $data['message'] : '不明なエラー';
        carmel3_auto_log($s, "失敗: {$title} / {$msg}");
        carmel3_progress_set('エラー: 文章の生成に失敗（' . $msg . '）', 100, 'error', array('title' => $title));
        carmel3_loglist_add("失敗: {$title} / {$msg}", false);
        return false;
    }

    $post_id = intval($data['post_id']);

    $done[] = $picked_key;
    $s['done'] = $done;

    if ($post_id) { update_post_meta($post_id, '_carmel3_item', $item); } // 作り直し用にテーマを保存

    carmel3_progress_set('本文を保存しました。仕上げ中…', 45, 'running', array('post_id' => $post_id, 'title' => $title));

    // スラッグ（英語URL）とメタディスクリプションの自動生成（メディア記事）
    if (!empty($s['auto_slug_meta']) && $post_id) {
        $sm = carmel3_ai_slug_meta(get_the_title($post_id) ?: $title, $item['keyword']);
        $slug = carmel3_make_slug($sm['slug'], get_the_title($post_id) ?: $title, $item['keyword']);
        if ($slug !== '') carmel3_apply_slug($post_id, $slug);
        $meta_src = $sm['meta'] !== '' ? $sm['meta'] : get_the_excerpt($post_id);
        carmel3_write_meta_description($post_id, $meta_src);
    }

    // 店舗情報の差し込み（メディア記事）：架空フッター除去→伏字置換→正確な店舗カード追加
    $store = carmel3_pick_store($item);
    if ($post_id) {
        $body = (string) get_post_field('post_content', $post_id);
        $new  = carmel3_strip_store_footer($body);
        if (is_array($store)) {
            $new = carmel3_replace_placeholders($new, $store);
            if (strpos($new, 'carmel-store-card') === false) {
                $new .= "\n" . carmel3_store_card_html($store);
            }
        }
        if ($new !== $body) {
            wp_update_post(array('ID' => $post_id, 'post_content' => $new));
        }
    }

    // CTAボタンのURLが壊れていれば /contact/ に自動修正
    $cta_msg = '';
    if (!empty($s['fix_cta']) && $post_id) {
        $cta_n = carmel3_auto_fix_cta($post_id, $s);
        $cta_msg = ' | CTA: ' . ($cta_n > 0 ? "{$cta_n}箇所修正" : '修正不要');
    }

    $img_msg = '';
    if (!empty($s['gen_images']) && $post_id) {
        carmel3_progress_set('アイキャッチ・バナー画像を生成中…（1〜2分）', 60, 'running', array('post_id' => $post_id, 'title' => $title));
        // アイキャッチ＋トップバナーは即時に（従来どおり）
        $img_msg = ' | 画像: ' . carmel3_img_attach_to_post($post_id);

        // 本文セクション画像はタイムアウトを避けるため裏側で1枚ずつ
        if (!empty($s['gen_section_images'])) {
            $jobs = carmel3_auto_build_section_jobs($post_id, $s);
            if (!empty($jobs)) {
                update_post_meta($post_id, '_carmel_pending_img_jobs', $jobs);
                if (!wp_next_scheduled(CARMEL3_AUTO_IMGHOOK, array($post_id))) {
                    wp_schedule_single_event(time() + 10, CARMEL3_AUTO_IMGHOOK, array($post_id));
                }
                if (function_exists('spawn_cron')) { spawn_cron(); }
                $img_msg .= ' | 本文画像: 裏側で' . count($jobs) . '枚を順次生成';
            }
        }
    }

    if (!empty($s['publish']) && $post_id) {
        wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));
    }

    // Googleマイビジネス自動投稿
    $gmb_msg = '';
    if (!empty($s['gmb_post']) && $post_id) {
        $gmb_msg = ' | Google: ' . carmel3_auto_post_to_gmb($post_id, $item['account'] !== '' ? $item['account'] : 'main');
    }

    // 追加投稿タイプ（NEWS・加盟店ブログ等）にも同じテーマでAI生成
    $extra_msg = '';
    $extra_titles = array();
    $extra_types = (isset($s['extra_types']) && is_array($s['extra_types'])) ? $s['extra_types'] : array();
    foreach ($extra_types as $pt) {
        if ($pt === 'media_article' || !post_type_exists($pt)) continue;
        $pobj = get_post_type_object($pt);
        $plbl = $pobj ? ($pobj->labels->singular_name ?: $pt) : $pt;
        carmel3_progress_set($plbl . 'をAIが作成中…', 80, 'running', array('post_id' => $post_id, 'title' => $title));
        $epid = carmel3_gen_post($pt, $item, $s);
        if (is_wp_error($epid)) {
            $extra_msg .= ' | ' . $pt . '失敗(' . $epid->get_error_message() . ')';
        } else {
            $obj = get_post_type_object($pt);
            $lbl = $obj ? ($obj->labels->singular_name ?: $pt) : $pt;
            $extra_titles[] = $lbl . '#' . $epid;
            $extra_msg .= ' | ' . $lbl . 'OK#' . $epid;
            // 追加記事も通知（テスト宛プレビュー）
            $note = '（メディア記事「' . $title . '」と同テーマ）';
            carmel3_notify_new_post($s, $epid, $note);
        }
    }

    if (function_exists('carmel_history_add')) {
        carmel_history_add('auto_generate', $title, $post_id, array(
            'account'  => $item['account'],
            'category' => $item['category'],
        ));
    }

    // 通知（Slack / LINE）：メディア記事の本体ぶん
    $notify_msg = '';
    $notify_now = (isset($s['notify_on']) && $s['notify_on'] === 'publish') ? !empty($s['publish']) : true;
    if ($notify_now && (!empty($s['slack_enabled']) || !empty($s['line_enabled']))) {
        carmel3_progress_set('通知を送信中…', 92, 'running', array('post_id' => $post_id, 'title' => $title));
        $extra_note = !empty($extra_titles) ? ('同時作成: ' . implode('、', $extra_titles)) : '';
        $r = carmel3_notify_new_post($s, $post_id, $extra_note);
        if ($r !== '') $notify_msg = ' | 通知: ' . $r;
    }

    $remaining = carmel3_auto_remaining_count($s);
    $status_label = !empty($s['publish']) ? '公開' : '下書き';
    carmel3_auto_log($s, "成功: {$title}（{$status_label}） post#{$post_id}{$cta_msg}{$img_msg}{$gmb_msg}{$extra_msg}{$notify_msg} | 残り{$remaining}件");
    carmel3_progress_set("完了：「{$title}」を{$status_label}で作成しました（残り{$remaining}件）", 100, 'done', array('post_id' => $post_id, 'title' => $title));
    carmel3_loglist_add("成功: {$title}（{$status_label}） post#{$post_id}{$cta_msg}{$img_msg}{$extra_msg} | 残り{$remaining}件", true);
    return $post_id;
}

function carmel3_auto_item_key($row) {
    if (!is_array($row)) return '';
    $parts = array(
        isset($row['account']) ? $row['account'] : '',
        isset($row['category']) ? $row['category'] : '',
        isset($row['keyword']) ? $row['keyword'] : '',
        isset($row['title']) ? $row['title'] : '',
        isset($row['prefecture']) ? $row['prefecture'] : '',
        isset($row['city']) ? $row['city'] : '',
    );
    $joined = trim(implode('|', $parts), '|');
    if (trim((isset($row['keyword']) ? $row['keyword'] : '') . (isset($row['title']) ? $row['title'] : '')) === '') {
        return '';
    }
    return md5($joined);
}

function carmel3_auto_remaining_count($s) {
    $queue = array_values((array)$s['queue']);
    $done  = isset($s['done']) && is_array($s['done']) ? $s['done'] : array();
    $remaining = 0;
    foreach ($queue as $row) {
        $key = carmel3_auto_item_key($row);
        if ($key === '') continue;
        if (in_array($key, $done, true)) continue;
        $remaining++;
    }
    return $remaining;
}

function carmel3_auto_log(&$s, $message) {
    $s['last_run'] = current_time('mysql');
    $s['last_msg'] = $message;
    carmel3_auto_save_settings($s);
}

/* ===== 画像生成（OpenRouter / Gemini） ===== */

function carmel3_img_api_key() {
    if (function_exists('carmel_get_openrouter_api_key')) {
        $k = carmel_get_openrouter_api_key();
        if ($k !== '') return $k;
    }
    $opt = get_option('carmel_plugin_settings', array());
    if (is_array($opt) && !empty($opt['openrouter_api_key'])) {
        return trim((string)$opt['openrouter_api_key']);
    }
    return '';
}

function carmel3_img_mime_ext($mime) {
    $map = array('image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/webp' => 'webp');
    return isset($map[$mime]) ? $map[$mime] : 'png';
}

function carmel3_img_generate_openrouter($prompt, $model) {
    $key = carmel3_img_api_key();
    if ($key === '') return new WP_Error('no_key', 'OpenRouter APIキー未設定');

    $args = array(
        'headers' => array(
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
            'HTTP-Referer'  => home_url('/'),
            'X-Title'       => 'CARMEL Image Generation',
        ),
        'body' => wp_json_encode(array(
            'model'      => $model,
            'messages'   => array(array('role' => 'user', 'content' => $prompt)),
            'modalities' => array('image', 'text'),
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'timeout' => 180,
    );

    // 最大3回まで（429 / 5xx / 通信エラー のときは少し待って再試行）
    $res = null; $code = 0; $raw = ''; $json = null;
    for ($try = 1; $try <= 3; $try++) {
        $res = wp_remote_post('https://openrouter.ai/api/v1/chat/completions', $args);
        if (is_wp_error($res)) {
            if ($try < 3) { sleep(3); continue; }
            return $res;
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $raw  = (string) wp_remote_retrieve_body($res);
        $json = json_decode($raw, true);
        if ($code === 429 || ($code >= 500 && $code < 600)) {
            if ($try < 3) { sleep(3); continue; }
        }
        break;
    }

    if ($code < 200 || $code >= 300) {
        $m = (is_array($json) && !empty($json['error']['message'])) ? $json['error']['message'] : ('HTTP' . $code);
        return new WP_Error('http', '画像API: ' . $m);
    }

    $msg = isset($json['choices'][0]['message']) ? $json['choices'][0]['message'] : array();
    $url = '';
    if (!empty($msg['images'][0]['image_url']['url'])) {
        $url = $msg['images'][0]['image_url']['url'];
    } elseif (!empty($msg['images'][0]['url'])) {
        $url = $msg['images'][0]['url'];
    } elseif (!empty($msg['content']) && is_string($msg['content'])
        && preg_match('#data:image/[^;]+;base64,[A-Za-z0-9+/=]+#', $msg['content'], $mm)) {
        $url = $mm[0];
    }

    if ($url === '') {
        return new WP_Error('noimg', 'モデルが画像を返しませんでした（モデル名が画像対応か確認）');
    }

    if (strpos($url, 'data:') === 0) {
        if (!preg_match('#^data:(image/[^;]+);base64,(.*)$#s', $url, $m)) {
            return new WP_Error('badimg', '画像データURLの解析に失敗');
        }
        $bytes = base64_decode($m[2]);
        if ($bytes === false) return new WP_Error('badimg', 'base64デコード失敗');
        return array('bytes' => $bytes, 'mime' => $m[1]);
    }

    $g = wp_remote_get($url, array('timeout' => 60));
    if (is_wp_error($g)) return $g;
    $bytes = (string) wp_remote_retrieve_body($g);
    $mime  = (string) wp_remote_retrieve_header($g, 'content-type');
    if ($mime === '') $mime = 'image/png';
    if ($bytes === '') return new WP_Error('badimg', '画像URLからの取得に失敗');
    return array('bytes' => $bytes, 'mime' => $mime);
}

// 画像に文字を一切描かせず、登場人物は必ず日本人にするための強い指示。すべての画像プロンプト末尾に付ける。
function carmel3_img_no_text_suffix() {
    return ' . High-end photorealistic editorial photograph, shot on a full-frame camera, 50mm lens, natural soft lighting,'
        . ' sharp focus, realistic depth of field, professional color grading, clean modern Japanese composition, magazine quality.'
        . ' PEOPLE: if any person appears, they MUST be Japanese (East Asian, Japanese ethnicity), with natural Japanese features,'
        . ' wearing neat Japanese business attire, in a Japan setting. ABSOLUTELY NO foreigners, no Western/Caucasian/Black/South-Asian people,'
        . ' no non-Japanese faces. The setting is unmistakably Japan.'
        . ' ABSOLUTELY NO TEXT in the image: no words, no letters, no numbers, no Japanese characters (kanji, hiragana, katakana),'
        . ' no captions, no labels, no signboards, no posters, no brochures with writing, no brand logos, no license plate numbers,'
        . ' no phone or screen text, no watermark, no UI. The image must contain no writing of any kind anywhere.';
}

// 日本語が混ざっているか判定
function carmel3_img_has_japanese($str) {
    return (bool) preg_match('/[\x{3040}-\x{30FF}\x{4E00}-\x{9FFF}\x{FF66}-\x{FF9D}]/u', (string)$str);
}

// 日本語の文脈（タイトルや見出し）から「英語のシーン説明（文字なし）」を作る。
// AIが使えない/失敗時は $fallback をそのまま使う（英語の汎用シーン）。
function carmel3_img_scene_from_ja($ja_context, $fallback) {
    $ja_context = trim((string) $ja_context);
    if ($ja_context === '') return $fallback;
    if (!function_exists('carmel_call_openrouter_chat')) return $fallback;

    $sys = 'You turn a Japanese article topic into ONE short English scene description for a realistic editorial PHOTO '
         . 'set at a Japanese used-car dealership / auto-finance context in Japan. '
         . 'Any people described MUST be Japanese (never foreigners). '
         . 'Describe only people, setting, objects, mood (max 22 words). '
         . 'Never describe any text, words, letters, signs or logos that should appear in the photo. '
         . 'Output ONLY the scene phrase in English, no quotes, no explanation.';
    $res = carmel_call_openrouter_chat(array(
        array('role' => 'system', 'content' => $sys),
        array('role' => 'user',   'content' => 'Japanese topic: ' . $ja_context),
    ), '', 0.6, 40);

    if (is_array($res) && empty($res['error']) && !empty($res['content'])) {
        $r = trim(preg_replace('/\s+/', ' ', (string)$res['content']));
        $r = trim($r, " \t\n\r\0\x0B\"'。．");
        // 日本語が残っていたら採用しない（文字化け防止）
        if ($r !== '' && !carmel3_img_has_japanese($r) && mb_strlen($r) <= 240) {
            return $r;
        }
    }
    return $fallback;
}

// 記事の「基本シーン（英語・文字なし）」を作って投稿に保存し、以後は使い回す
function carmel3_img_base_scene($post_id, $s) {
    $cached = (string) get_post_meta($post_id, '_carmel3_img_scene_en', true);
    if ($cached !== '') return $cached;

    $fallback = 'a professional Japanese auto dealership scene in Japan, a friendly Japanese female advisor and a Japanese customer, '
              . 'a clean modern showroom with a parked car, bright and trustworthy mood';
    $title = trim((string) get_the_title($post_id));
    $scene = carmel3_img_scene_from_ja($title, $fallback);
    update_post_meta($post_id, '_carmel3_img_scene_en', $scene);
    return $scene;
}

function carmel3_img_sideload($post_id, $bytes, $mime, $base, $target_w = 0, $target_h = 0) {
    $ext = carmel3_img_mime_ext($mime);
    $filename = sanitize_file_name($base . '-' . wp_generate_password(6, false, false) . '.' . $ext);

    $up = wp_upload_bits($filename, null, $bytes);
    if (!empty($up['error'])) return new WP_Error('upload', $up['error']);

    // 指定サイズへ正確にトリミング・リサイズ（中央基準・crop）
    if ($target_w > 0 && $target_h > 0) {
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $editor = wp_get_image_editor($up['file']);
        if (!is_wp_error($editor)) {
            $editor->resize($target_w, $target_h, true); // true = 中央でクロップして指定寸法ぴったりに
            $saved = $editor->save($up['file']);
            if (!is_wp_error($saved) && !empty($saved['path'])) {
                // 保存し直したファイルへ差し替え
                $up['file'] = $saved['path'];
                if (!empty($saved['mime-type'])) $mime = $saved['mime-type'];
            }
        }
    }

    $filetype = wp_check_filetype($up['file']);
    $attachment = array(
        'post_mime_type' => $filetype['type'] ? $filetype['type'] : $mime,
        'post_title'     => $base,
        'post_content'   => '',
        'post_status'    => 'inherit',
    );
    $attach_id = wp_insert_attachment($attachment, $up['file'], $post_id);
    if (is_wp_error($attach_id)) return $attach_id;

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $meta = wp_generate_attachment_metadata($attach_id, $up['file']);
    wp_update_attachment_metadata($attach_id, $meta);

    return $attach_id;
}

/* ===== アイキャッチに訴求コピーを重ねる（日本語フォントをサーバー側で描画＝文字化けしない） ===== */

// 日本語TTFフォントを用意（無ければダウンロードしてuploadsに保存）
function carmel3_get_jp_font() {
    $dir = wp_upload_dir();
    if (!empty($dir['error'])) return '';
    $base = trailingslashit($dir['basedir']) . 'carmel3-fonts/';
    $path = $base . 'jp.ttf';
    if (file_exists($path) && filesize($path) > 50000) return $path;
    wp_mkdir_p($base);
    $urls = array(
        'https://raw.githubusercontent.com/google/fonts/main/ofl/kosugimaru/KosugiMaru-Regular.ttf',
        'https://raw.githubusercontent.com/google/fonts/main/ofl/kosugi/Kosugi-Regular.ttf',
    );
    foreach ($urls as $u) {
        $r = wp_remote_get($u, array('timeout' => 30));
        if (is_wp_error($r)) continue;
        if ((int) wp_remote_retrieve_response_code($r) !== 200) continue;
        $body = (string) wp_remote_retrieve_body($r);
        if (strlen($body) > 50000) { @file_put_contents($path, $body); if (file_exists($path)) return $path; }
    }
    return '';
}

function carmel3_eyecatch_copy_ready($s) {
    return !empty($s['eyecatch_copy'])
        && function_exists('imagettftext')
        && function_exists('imagecreatetruecolor');
}

// 添付画像の下部に帯＋訴求コピーを焼き込む
function carmel3_eyecatch_overlay($attach_id, $text) {
    $text = trim((string) $text);
    if ($text === '' || !function_exists('imagettftext')) return false;
    $font = carmel3_get_jp_font();
    if ($font === '') return false;
    $file = get_attached_file($attach_id);
    if (!$file || !file_exists($file)) return false;
    $info = @getimagesize($file);
    if (!$info) return false;
    $mime = $info['mime'];
    if ($mime === 'image/png')       $img = @imagecreatefrompng($file);
    elseif ($mime === 'image/jpeg')  $img = @imagecreatefromjpeg($file);
    elseif ($mime === 'image/webp' && function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($file);
    else return false;
    if (!$img) return false;

    $W = imagesx($img); $H = imagesy($img);
    // ｜ / | で改行（最大2行）
    $lines = preg_split('/\s*[｜|\/\n]\s*/u', $text);
    $lines = array_values(array_filter(array_map('trim', $lines), 'strlen'));
    if (empty($lines)) { imagedestroy($img); return false; }
    if (count($lines) > 2) $lines = array_slice($lines, 0, 2);

    $fontsize = max(20, (int) round($W * 0.060));
    $lineh    = (int) round($fontsize * 1.5);
    $bandh    = $lineh * count($lines) + (int) round($fontsize * 0.9);
    $y0       = $H - $bandh;

    $band   = imagecolorallocatealpha($img, 11, 18, 32, 30);   // 濃紺・半透明
    imagefilledrectangle($img, 0, $y0, $W, $H, $band);
    $accent = imagecolorallocate($img, 244, 121, 32);          // 左オレンジバー
    imagefilledrectangle($img, 0, $y0, max(4, (int) round($W * 0.014)), $H, $accent);

    $white  = imagecolorallocate($img, 255, 255, 255);
    $shadow = imagecolorallocatealpha($img, 0, 0, 0, 70);
    $ty = $y0 + (int) round($fontsize * 1.15);
    foreach ($lines as $ln) {
        $bbox = imagettfbbox($fontsize, 0, $font, $ln);
        $tw = abs($bbox[2] - $bbox[0]);
        $tx = (int) round(($W - $tw) / 2);
        if ($tx < (int) round($W * 0.035)) $tx = (int) round($W * 0.035);
        imagettftext($img, $fontsize, 0, $tx + 2, $ty + 2, $shadow, $font, $ln);
        imagettftext($img, $fontsize, 0, $tx, $ty, $white, $font, $ln);
        $ty += $lineh;
    }

    if ($mime === 'image/png')       imagepng($img, $file);
    elseif ($mime === 'image/webp' && function_exists('imagewebp')) imagewebp($img, $file);
    else imagejpeg($img, $file, 90);
    imagedestroy($img);

    if (function_exists('wp_generate_attachment_metadata') && function_exists('wp_update_attachment_metadata')) {
        wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $file));
    }
    return true;
}

function carmel3_img_attach_to_post($post_id) {
    $s = carmel3_auto_get_settings();
    $model        = $s['image_model'] !== '' ? $s['image_model'] : CARMEL3_IMG_DEFAULT_MODEL;
    $banner_field = $s['banner_field'] !== '' ? $s['banner_field'] : 'hero_image';

    $ew = !empty($s['eyecatch_w']) ? (int)$s['eyecatch_w'] : 1200;
    $eh = !empty($s['eyecatch_h']) ? (int)$s['eyecatch_h'] : 630;
    $bw = !empty($s['banner_w'])   ? (int)$s['banner_w']   : 1200;
    $bh = !empty($s['banner_h'])   ? (int)$s['banner_h']   : 400;

    // 文字化け防止のため、日本語タイトルではなく「英語のシーン説明」で生成する
    $scene = carmel3_img_base_scene($post_id, $s);
    $base_prompt = 'Professional automotive editorial visual. Scene: ' . $scene;
    $no_text = carmel3_img_no_text_suffix();

    $out = array();

    $p1  = $base_prompt . ' . 16:9 horizontal composition, the main subject centered' . $no_text;
    $img = carmel3_img_generate_openrouter($p1, $model);
    if (is_wp_error($img)) {
        $out[] = 'アイキャッチ失敗(' . $img->get_error_message() . ')';
    } else {
        $a = carmel3_img_sideload($post_id, $img['bytes'], $img['mime'], 'eyecatch-' . $post_id, $ew, $eh);
        if (is_wp_error($a)) {
            $out[] = 'アイキャッチ保存失敗(' . $a->get_error_message() . ')';
        } else {
            set_post_thumbnail($post_id, $a);
            // 訴求コピーを焼き込む（ONかつサーバー対応時）
            if (carmel3_eyecatch_copy_ready($s)) {
                carmel3_eyecatch_overlay($a, $s['eyecatch_copy_text']);
            }
            $out[] = "アイキャッチOK#{$a}({$ew}x{$eh})";
        }
    }

    $p2   = $base_prompt . ' . Ultra-wide website hero banner, the subject placed to one side leaving generous empty background space on the other side (for a headline to be added later by the website, NOT drawn in the image)' . $no_text;
    $img2 = carmel3_img_generate_openrouter($p2, $model);
    if (is_wp_error($img2)) {
        $out[] = 'バナー失敗(' . $img2->get_error_message() . ')';
    } else {
        $b = carmel3_img_sideload($post_id, $img2['bytes'], $img2['mime'], 'banner-' . $post_id, $bw, $bh);
        if (is_wp_error($b)) {
            $out[] = 'バナー保存失敗(' . $b->get_error_message() . ')';
        } else {
            if (function_exists('update_field')) {
                update_field($banner_field, $b, $post_id);
            } else {
                update_post_meta($post_id, $banner_field, $b);
            }
            $out[] = "バナーOK#{$b}({$bw}x{$bh}) ({$banner_field})";
        }
    }

    return implode(' / ', $out);
}

/* ===== CTAボタンURLの自動修正 ===== */

// 連絡先URL（設定が空なら /contact/）
function carmel3_auto_contact_url($s) {
    $u = isset($s['contact_url']) ? trim((string)$s['contact_url']) : '';
    if ($u !== '') return $u;
    return home_url('/contact/');
}

// ACF文字列フィールドの読み取り（フィールドキー優先、無ければ post_meta）
function carmel3_auto_get_acf_value($post_id, $field_key, $field_name) {
    if (function_exists('get_field')) {
        $v = get_field($field_key, $post_id);
        if ($v === null || $v === false) {
            $v = get_field($field_name, $post_id);
        }
        if (is_string($v)) return $v;
    }
    return (string) get_post_meta($post_id, $field_name, true);
}

// ACF文字列フィールドの書き込み（フィールドキー優先、無ければ post_meta + 関連付け）
function carmel3_auto_set_acf_value($post_id, $field_key, $field_name, $value) {
    if (function_exists('update_field')) {
        update_field($field_key, $value, $post_id);
        // ACFが未登録のページでも値が残るよう、名前でも保存（同値なので安全）
        update_post_meta($post_id, $field_name, $value);
    } else {
        update_post_meta($post_id, $field_name, $value);
        update_post_meta($post_id, '_' . $field_name, $field_key);
    }
}

// CTAボタンのURLが壊れていれば /contact/ に直す
function carmel3_auto_fix_cta($post_id, $s) {
    $contact = carmel3_auto_contact_url($s);
    $targets = array(
        array(CARMEL3_F_CTA1_URL, 'main_cta_url'),
        array(CARMEL3_F_CTA2_URL, 'cta_button_url'),
    );
    $fixed = 0;
    foreach ($targets as $t) {
        $val = trim(carmel3_auto_get_acf_value($post_id, $t[0], $t[1]));
        $bad = ($val === '')
            || (strpos($val, 'home_url') !== false)   // PHP文字列が漏れている
            || (strpos($val, '://.') !== false)        // http://. のような壊れURL
            || (strpos($val, '%20') !== false)         // 空白が混入
            || (strpos($val, ' ') !== false)           // 空白が混入
            || (strpos($val, 'http') !== 0);           // http で始まっていない
        if ($bad) {
            carmel3_auto_set_acf_value($post_id, $t[0], $t[1], $contact);
            $fixed++;
        }
    }
    return $fixed; // 直した箇所の数（0なら修正不要）
}

// 既存のメディア記事(media_article)のCTAをまとめてチェック＆修正
function carmel3_auto_fix_cta_bulk($s) {
    @set_time_limit(300);
    $ids = get_posts(array(
        'post_type'      => array('media_article'),
        'post_status'    => 'any',
        'posts_per_page' => 3000,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array(
            'relation' => 'OR',
            array('key' => 'main_cta_url',   'compare' => 'EXISTS'),
            array('key' => 'cta_button_url', 'compare' => 'EXISTS'),
        ),
    ));

    $checked = 0;
    $posts_fixed = 0;
    $fields_fixed = 0;
    foreach ($ids as $pid) {
        $checked++;
        $n = carmel3_auto_fix_cta((int)$pid, $s);
        if ($n > 0) {
            $posts_fixed++;
            $fields_fixed += $n;
        }
    }
    return array('checked' => $checked, 'posts' => $posts_fixed, 'fields' => $fields_fixed);
}

/* ===== 本文セクション画像（裏側で1枚ずつ生成） ===== */

// ACF画像フィールド（添付ID）の書き込み
function carmel3_auto_set_image_field($post_id, $field_key, $field_name, $att_id) {
    if (function_exists('update_field')) {
        update_field($field_key, $att_id, $post_id);
    } else {
        update_post_meta($post_id, $field_name, $att_id);
        update_post_meta($post_id, '_' . $field_name, $field_key);
    }
}

// セクションに見出し/本文があるか（無いセクションには画像を作らない）
function carmel3_auto_section_has_content($post_id, $n) {
    $candidates = array(
        "section_{$n}_title", "section_{$n}_heading", "section_{$n}_subtitle",
        "section_{$n}_text", "section_{$n}_body", "section_{$n}_content",
    );
    foreach ($candidates as $name) {
        $v = get_post_meta($post_id, $name, true);
        if (is_string($v) && trim($v) !== '') return trim($v);
    }
    return '';
}

// 生成すべきセクション画像ジョブを組み立てる（画像が空のセクションだけ）
function carmel3_auto_build_section_jobs($post_id, $s) {
    $w = !empty($s['sec_w']) ? (int)$s['sec_w'] : 1200;
    $h = !empty($s['sec_h']) ? (int)$s['sec_h'] : 675;

    $no_text = carmel3_img_no_text_suffix();

    $fields = array(
        1 => array(CARMEL3_F_SEC1_IMG, 'section_1_image'),
        2 => array(CARMEL3_F_SEC2_IMG, 'section_2_image'),
        3 => array(CARMEL3_F_SEC3_IMG, 'section_3_image'),
    );

    // セクションごとに「画の方向性」を変えて、3枚が似ないようにする（被写体・構図・距離を分ける）
    $angles = array(
        1 => 'medium shot, a friendly Japanese sales advisor and a Japanese customer (both Japanese) talking at a clean consultation desk, documents and a tablet on the desk, warm welcoming mood',
        2 => 'close-up product shot, a well-maintained used car on a bright modern showroom floor in Japan, glossy body, low angle, no people',
        3 => 'wide shot, a happy Japanese family or Japanese customer receiving car keys near a car, handshake, sunny Japanese dealership entrance, hopeful mood',
    );
    $fallback_base = 'a professional Japanese auto dealership in Japan, clean modern interior, bright and trustworthy mood';

    $jobs = array();
    foreach ($fields as $n => $f) {
        $existing = carmel3_auto_get_acf_value($post_id, $f[0], $f[1]);
        if (trim($existing) !== '' && $existing !== '0') continue; // すでに画像あり飛ばす
        $heading = carmel3_auto_section_has_content($post_id, $n);
        if ($heading === '') continue; // 中身の無いセクションは作らない

        // 見出し（日本語）→ 英語シーンに変換（文字化け防止）。失敗時はセクション固定の構図のみ。
        $heading_scene = carmel3_img_scene_from_ja($heading, '');
        $angle = isset($angles[$n]) ? $angles[$n] : $fallback_base;
        $scene = ($heading_scene !== '') ? ($angle . '; theme: ' . $heading_scene) : $angle;

        $prompt = 'Professional automotive editorial photo for an article section. Scene: ' . $scene
            . ' . 16:9 horizontal composition' . $no_text;
        $jobs[] = array(
            'name'   => $f[1],
            'key'    => $f[0],
            'w'      => $w,
            'h'      => $h,
            'prompt' => $prompt,
        );
    }
    return $jobs;
}

// 裏側ワーカー：保留ジョブから1枚だけ生成し、残りがあれば自分を再スケジュール
function carmel3_auto_process_one_image($post_id) {
    @set_time_limit(300);
    $post_id = intval($post_id);
    if (!$post_id) return;

    $jobs = get_post_meta($post_id, '_carmel_pending_img_jobs', true);
    if (!is_array($jobs) || empty($jobs)) {
        delete_post_meta($post_id, '_carmel_pending_img_jobs');
        return;
    }

    $s     = carmel3_auto_get_settings();
    $model = $s['image_model'] !== '' ? $s['image_model'] : CARMEL3_IMG_DEFAULT_MODEL;

    $job = array_shift($jobs); // 1枚だけ取り出す

    if (is_array($job) && !empty($job['prompt'])) {
        $img = carmel3_img_generate_openrouter($job['prompt'], $model);
        if (!is_wp_error($img)) {
            $att = carmel3_img_sideload(
                $post_id, $img['bytes'], $img['mime'],
                $job['name'] . '-' . $post_id,
                (int)$job['w'], (int)$job['h']
            );
            if (!is_wp_error($att)) {
                carmel3_auto_set_image_field($post_id, $job['key'], $job['name'], $att);
            }
        }
    }

    // 残りがあれば保存して+20秒後に自分を再実行、無ければ片付け
    if (!empty($jobs)) {
        update_post_meta($post_id, '_carmel_pending_img_jobs', array_values($jobs));
        if (!wp_next_scheduled(CARMEL3_AUTO_IMGHOOK, array($post_id))) {
            wp_schedule_single_event(time() + 20, CARMEL3_AUTO_IMGHOOK, array($post_id));
        }
        if (function_exists('spawn_cron')) { spawn_cron(); }
    } else {
        delete_post_meta($post_id, '_carmel_pending_img_jobs');
    }
}

/* ===== Googleマイビジネス自動投稿 ===== */

function carmel3_auto_post_to_gmb($post_id, $account = 'main') {
    // v5.7本体の関数が無ければ動かさない
    if (!function_exists('carmel_post_to_google') || !function_exists('carmel_get_sns_credentials')) {
        return 'スキップ(本体v5.7のGoogle投稿関数が見つかりません)';
    }

    // 投稿文: SNS下書きがあれば使い、無ければタイトル＋リードで作る
    $text = (string) get_post_meta($post_id, '_carmel_sns_draft', true);
    if (trim($text) === '') {
        $text = (string) get_post_meta($post_id, '_carmel_sns_draft_google', true);
    }
    if (trim($text) === '') {
        $title = get_the_title($post_id);
        $lead  = (string) get_post_meta($post_id, 'lead_text', true);
        if (trim($lead) === '') {
            $lead = (string) get_post_meta($post_id, '_carmel_generated_excerpt', true);
        }
        $text = trim($title . "\n\n" . $lead);
    }

    // 公開記事ならURLを末尾に付ける
    if (get_post_status($post_id) === 'publish') {
        $permalink = get_permalink($post_id);
        if ($permalink && strpos($text, $permalink) === false) {
            $text .= "\n\n" . $permalink;
        }
    }

    if (trim($text) === '') {
        return 'スキップ(投稿文が空)';
    }

    // 画像URL（アイキャッチ）
    $image_url = '';
    $thumb_id  = get_post_thumbnail_id($post_id);
    if ($thumb_id) {
        $image_url = wp_get_attachment_url($thumb_id) ?: '';
    }

    $all_creds = carmel_get_sns_credentials();
    $creds     = isset($all_creds[$account]) ? $all_creds[$account] : (isset($all_creds['main']) ? $all_creds['main'] : array());

    $r = carmel_post_to_google($text, $image_url, $creds);

    // 履歴にも残す
    if (function_exists('carmel_history_add')) {
        carmel_history_add('sns_posted', get_the_title($post_id), $post_id, array(
            'platforms' => 'google',
            'account'   => $account,
            'all_ok'    => !empty($r['success']),
        ));
    }

    return !empty($r['success']) ? '投稿成功' : ('投稿失敗(' . ($r['message'] ?? '不明') . ')');
}

/* ===== 追加投稿タイプ（NEWS・加盟店ブログ等）をAIでゼロから生成 ===== */

// 選べる投稿タイプ（記事になるものだけ。テーマ/プラグインの内部用は除外）
function carmel3_selectable_post_types() {
    $out = array();
    $types = get_post_types(array('show_ui' => true), 'objects');

    // 記事ではない（システム/ビルダー）投稿タイプ：完全一致で除外
    $skip = array(
        'attachment', 'media_article', 'revision', 'nav_menu_item',
        'wp_block', 'wp_template', 'wp_template_part', 'wp_navigation', 'wp_global_styles',
        'wp_font_family', 'wp_font_face', 'custom_css', 'customize_changeset', 'oembed_cache',
        'user_request', 'acf-field-group', 'acf-field', 'acf-post-type', 'acf-taxonomy', 'acf-ui-options-page',
        'product', 'product_variation', 'shop_order', 'shop_coupon',
    );
    // スラッグにこれらの語を含むものは除外（ページビルダー/テンプレ/設定系）
    $deny = array('template', 'widget', 'font', 'color', 'structured', 'taxonom', 'field',
        'collection', 'cart', 'grid', 'dynamic', 'library', 'block', 'pattern', 'global',
        'style', 'setting', 'option', 'menu', 'reusable');

    // 記事系は必ず表示（存在すれば）
    $always = array('post', 'news', 'shop_blog');

    foreach ($types as $pt) {
        $name = $pt->name;
        if (in_array($name, $skip, true)) continue;

        $keep = in_array($name, $always, true);
        if (!$keep) {
            $hay = strtolower($name);
            $blocked = false;
            foreach ($deny as $d) { if (strpos($hay, $d) !== false) { $blocked = true; break; } }
            // 「公開される」かつ「本文エディタを持つ」非ビルトインの投稿タイプ＝記事になり得る
            $keep = !$blocked && !empty($pt->public) && post_type_supports($name, 'editor') && empty($pt->_builtin);
        }
        if (!$keep) continue;
        $out[$name] = $pt->labels->singular_name ? $pt->labels->singular_name : $pt->label;
    }
    return $out;
}

// 1つの投稿タイプに、テーマから記事をAI生成して保存
/* ===== 店舗・担当者（記事にランダム差し込み） ===== */

function carmel3_stores_get() {
    $d = get_option('carmel3_stores', array());
    if (!is_array($d)) return array();
    // 名前のある店舗だけ
    $out = array();
    foreach ($d as $st) {
        if (is_array($st) && !empty($st['name'])) $out[] = $st;
    }
    return $out;
}

// 記事に差し込む店舗を1つ選ぶ（地域が一致すれば優先、なければランダム）
function carmel3_pick_store($item = null) {
    $stores = carmel3_stores_get();
    if (empty($stores)) return null;

    if (is_array($item)) {
        $pref = isset($item['prefecture']) ? trim($item['prefecture']) : '';
        $city = isset($item['city']) ? trim($item['city']) : '';
        if ($pref !== '' || $city !== '') {
            $matched = array();
            foreach ($stores as $st) {
                $sp = isset($st['pref']) ? $st['pref'] : '';
                $sc = isset($st['city']) ? $st['city'] : '';
                if (($pref !== '' && $sp === $pref) || ($city !== '' && $sc === $city)) {
                    $matched[] = $st;
                }
            }
            if (!empty($matched)) $stores = $matched;
        }
    }
    return $stores[ array_rand($stores) ];
}

// AIに渡す「実在の店舗情報を使え／伏字禁止」の指示テキスト
function carmel3_store_prompt_block($store) {
    if (!is_array($store)) return '';
    $lines = array();
    if (!empty($store['name']))    $lines[] = '店舗名: ' . $store['name'];
    $addr = trim((isset($store['zip']) && $store['zip'] !== '' ? '〒' . $store['zip'] . ' ' : '') . (isset($store['address']) ? $store['address'] : ''));
    if ($addr !== '')              $lines[] = '住所: ' . $addr;
    if (!empty($store['tel']))     $lines[] = '電話: ' . $store['tel'];
    if (!empty($store['hours']))   $lines[] = '営業時間: ' . $store['hours'];
    if (!empty($store['closed']))  $lines[] = '定休日: ' . $store['closed'];
    if (!empty($store['staff_name'])) $lines[] = '担当者: ' . $store['staff_name'];
    if (empty($lines)) return '';
    return "【この記事で使う実在の店舗情報】\n" . implode("\n", $lines)
        . "\n■店舗の扱い（厳守）：\n"
        . "・店舗名は上の値だけを使う。『本店』『支店』『○○店』など勝手に付け足さない。\n"
        . "・住所・電話番号・営業時間・定休日・ウェブサイトURLなどの店舗情報は本文に書かない（正確な情報はシステムが記事末尾に自動で添付します）。\n"
        . "・店舗の署名・連絡先・フッター（例：『——／中古車販売 ○○／住所：…／電話：…』）も書かない。\n"
        . "・伏字（〇〇県・XXX-XXXX・0120-XXX-XXX 等）や、実在しない住所・電話・URLの創作は禁止。";
}

// 店舗未登録のときにAIへ渡す指示（架空の店舗情報を書かせない）
function carmel3_no_store_prompt_block() {
    return "■店舗情報について（厳守）：\n"
        . "・実在しない店舗名・住所・電話番号・ウェブサイトURLを創作しないこと（『カーメル本店』『東京都…1-2-3』『03-1234-5678』『example-carmel.com』のような架空情報は禁止）。\n"
        . "・記事末尾の店舗署名・住所・電話・ウェブサイト・フッターは書かないこと。\n"
        . "・地域名はテーマで指定された都道府県・市までにとどめ、番地や電話番号は書かない。";
}

// 記事末尾に差し込む「店舗情報＋担当者アイコン」カードのHTML
function carmel3_store_card_html($store) {
    if (!is_array($store) || empty($store['name'])) return '';
    $name  = esc_html($store['name']);
    $addr  = trim((isset($store['zip']) && $store['zip'] !== '' ? '〒' . $store['zip'] . ' ' : '') . (isset($store['address']) ? $store['address'] : ''));
    $tel   = isset($store['tel']) ? $store['tel'] : '';
    $hours = isset($store['hours']) ? $store['hours'] : '';
    $closed= isset($store['closed']) ? $store['closed'] : '';
    $sname = isset($store['staff_name']) ? $store['staff_name'] : '';
    $icon  = isset($store['staff_icon']) ? trim($store['staff_icon']) : '';

    $h  = '<div class="carmel-store-card" style="border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:24px 0;background:#fafafa">';
    $h .= '<div style="display:flex;align-items:center;gap:14px;margin-bottom:10px">';
    if ($icon !== '') {
        $h .= '<img src="' . esc_url($icon) . '" alt="' . esc_attr($sname) . '" width="64" height="64" style="width:64px;height:64px;border-radius:50%;object-fit:cover;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.15)">';
    }
    $h .= '<div>';
    if ($sname !== '') $h .= '<div style="font-size:13px;color:#666">担当：' . esc_html($sname) . '</div>';
    $h .= '<div style="font-size:18px;font-weight:700">' . $name . '</div>';
    $h .= '</div></div>';
    $h .= '<table style="width:100%;border-collapse:collapse;font-size:14px">';
    if ($addr !== '')   $h .= '<tr><th style="text-align:left;width:90px;padding:4px 8px;color:#555">住所</th><td style="padding:4px 8px">' . esc_html($addr) . '</td></tr>';
    if ($tel !== '')    $h .= '<tr><th style="text-align:left;padding:4px 8px;color:#555">電話</th><td style="padding:4px 8px"><a href="tel:' . esc_attr(preg_replace('/[^0-9+]/', '', $tel)) . '">' . esc_html($tel) . '</a></td></tr>';
    if ($hours !== '')  $h .= '<tr><th style="text-align:left;padding:4px 8px;color:#555">営業時間</th><td style="padding:4px 8px">' . esc_html($hours) . '</td></tr>';
    if ($closed !== '') $h .= '<tr><th style="text-align:left;padding:4px 8px;color:#555">定休日</th><td style="padding:4px 8px">' . esc_html($closed) . '</td></tr>';
    $h .= '</table></div>';
    return $h;
}

// AIが勝手に書いた「店舗署名・住所・電話・URLのフッター」を本文から除去
function carmel3_strip_store_footer($html) {
    if ($html === '') return $html;
    // <p>内に「住所」と「電話」（または ウェブサイト/URL）が両方ある段落＝店舗フッターとみなして削除
    $html = preg_replace('/<p>(?=[^<]*(?:住所|所在地))(?=[^<]*(?:電話|TEL|ウェブサイト|ウェブ|URL)).*?<\/p>/isu', '', $html);
    // 区切り線だけの段落（——/—/--- のみ）も掃除
    $html = preg_replace('/<p>\s*(?:&#8212;|—|―|--+|‐+)\s*<\/p>/u', '', $html);
    return $html;
}

// 本文中の伏字プレースホルダーを実在の店舗情報に置換（メディア記事の保険）
function carmel3_replace_placeholders($html, $store) {
    if (!is_array($store) || $html === '') return $html;
    $name = isset($store['name']) ? $store['name'] : '';
    $addr = trim((isset($store['zip']) && $store['zip'] !== '' ? '〒' . $store['zip'] . ' ' : '') . (isset($store['address']) ? $store['address'] : ''));
    $tel  = isset($store['tel']) ? $store['tel'] : '';

    $map = array();
    if ($name !== '') {
        // 「カーメル〇〇店 / カーメル○○店 / カーメル◯◯店」→ 実店舗名
        $html = preg_replace('/カーメル[〇○◯\x{25CB}\x{3007}]+店/u', $name, $html);
    }
    if ($tel !== '') {
        $html = preg_replace('/0120[-‐－]?X{2,}[-‐－]?X{2,}/u', $tel, $html);
        $html = preg_replace('/0\d{1,3}[-‐－]X{2,}[-‐－]X{2,}/u', $tel, $html);
    }
    if ($addr !== '') {
        $html = preg_replace('/〒?X{3}[-‐－]?X{4}\s*[〇○◯]*[県都道府].*?\d?[-‐－]?\d?[-‐－]?\d?/u', $addr, $html, 1);
    }
    return $html;
}

add_action('admin_post_carmel3_stores_save', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_stores_save');

    $in = (isset($_POST['store']) && is_array($_POST['store'])) ? $_POST['store'] : array();
    $out = array();
    foreach ($in as $row) {
        if (!is_array($row)) continue;
        $name = isset($row['name']) ? sanitize_text_field(wp_unslash($row['name'])) : '';
        if ($name === '') continue; // 名前が無い行は捨てる
        $out[] = array(
            'name'       => $name,
            'zip'        => isset($row['zip']) ? sanitize_text_field(wp_unslash($row['zip'])) : '',
            'address'    => isset($row['address']) ? sanitize_text_field(wp_unslash($row['address'])) : '',
            'tel'        => isset($row['tel']) ? sanitize_text_field(wp_unslash($row['tel'])) : '',
            'hours'      => isset($row['hours']) ? sanitize_text_field(wp_unslash($row['hours'])) : '',
            'closed'     => isset($row['closed']) ? sanitize_text_field(wp_unslash($row['closed'])) : '',
            'pref'       => isset($row['pref']) ? sanitize_text_field(wp_unslash($row['pref'])) : '',
            'city'       => isset($row['city']) ? sanitize_text_field(wp_unslash($row['city'])) : '',
            'staff_name' => isset($row['staff_name']) ? sanitize_text_field(wp_unslash($row['staff_name'])) : '',
            'staff_icon' => isset($row['staff_icon']) ? esc_url_raw(wp_unslash($row['staff_icon'])) : '',
        );
    }
    update_option('carmel3_stores', $out);
    wp_safe_redirect(admin_url('admin.php?page=carmel3-stores&saved=1'));
    exit;
});

function carmel3_stores_page() {
    $stores = carmel3_stores_get();
    // 入力枠は「既存＋空き2行」
    $rows = $stores;
    $rows[] = array(); $rows[] = array();
    $g = function ($r, $k) { return isset($r[$k]) ? $r[$k] : ''; };
    ?>
    <div style="max-width:1000px;margin:20px auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460);color:#fff;padding:24px;border-radius:16px;margin-bottom:18px">
            <h1 style="margin:0 0 6px;font-size:24px">店舗・担当者（記事に差し込み）</h1>
            <p style="margin:0;opacity:.9">ここに登録した店舗から<strong>記事ごとに1つをランダムで選び</strong>、本文の店舗名・住所・電話に使います。担当者アイコンも記事末尾に表示します（「カーメル〇〇店」などの伏字を防ぎます）。</p>
        </div>

        <?php if (isset($_GET['saved'])): ?>
            <div class="notice notice-success"><p>店舗情報を保存しました。</p></div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="carmel3_stores_save">
            <?php wp_nonce_field('carmel3_stores_save'); ?>

            <?php foreach ($rows as $i => $r): ?>
                <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:14px 16px;margin-bottom:12px">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
                        <label style="font-size:12px;color:#555;font-weight:700">店舗名（必須）<br>
                            <input type="text" name="store[<?php echo $i; ?>][name]" value="<?php echo esc_attr($g($r,'name')); ?>" placeholder="例: カーメル 大阪本店" style="width:100%;padding:7px"></label>
                        <label style="font-size:12px;color:#555;font-weight:700">電話<br>
                            <input type="text" name="store[<?php echo $i; ?>][tel]" value="<?php echo esc_attr($g($r,'tel')); ?>" placeholder="例: 06-1234-5678" style="width:100%;padding:7px"></label>
                        <label style="font-size:12px;color:#555;font-weight:700">郵便番号<br>
                            <input type="text" name="store[<?php echo $i; ?>][zip]" value="<?php echo esc_attr($g($r,'zip')); ?>" placeholder="例: 530-0001" style="width:100%;padding:7px"></label>
                        <label style="font-size:12px;color:#555;font-weight:700">住所<br>
                            <input type="text" name="store[<?php echo $i; ?>][address]" value="<?php echo esc_attr($g($r,'address')); ?>" placeholder="例: 大阪府大阪市北区…" style="width:100%;padding:7px"></label>
                        <label style="font-size:12px;color:#555;font-weight:700">営業時間<br>
                            <input type="text" name="store[<?php echo $i; ?>][hours]" value="<?php echo esc_attr($g($r,'hours')); ?>" placeholder="例: 10:00〜19:00" style="width:100%;padding:7px"></label>
                        <label style="font-size:12px;color:#555;font-weight:700">定休日<br>
                            <input type="text" name="store[<?php echo $i; ?>][closed]" value="<?php echo esc_attr($g($r,'closed')); ?>" placeholder="例: 水曜" style="width:100%;padding:7px"></label>
                        <label style="font-size:12px;color:#555;font-weight:700">都道府県（地域一致に使用）<br>
                            <input type="text" name="store[<?php echo $i; ?>][pref]" value="<?php echo esc_attr($g($r,'pref')); ?>" placeholder="例: 大阪府" style="width:100%;padding:7px"></label>
                        <label style="font-size:12px;color:#555;font-weight:700">市区町村<br>
                            <input type="text" name="store[<?php echo $i; ?>][city]" value="<?php echo esc_attr($g($r,'city')); ?>" placeholder="例: 大阪市" style="width:100%;padding:7px"></label>
                        <label style="font-size:12px;color:#555;font-weight:700">担当者名<br>
                            <input type="text" name="store[<?php echo $i; ?>][staff_name]" value="<?php echo esc_attr($g($r,'staff_name')); ?>" placeholder="例: 山田 太郎" style="width:100%;padding:7px"></label>
                        <label style="font-size:12px;color:#555;font-weight:700">担当者アイコンURL<br>
                            <input type="url" name="store[<?php echo $i; ?>][staff_icon]" value="<?php echo esc_attr($g($r,'staff_icon')); ?>" placeholder="メディアにアップしてURLを貼る" style="width:100%;padding:7px"></label>
                    </div>
                    <?php if ($g($r,'staff_icon') !== ''): ?>
                        <div style="margin-top:8px"><img src="<?php echo esc_url($g($r,'staff_icon')); ?>" width="48" height="48" style="border-radius:50%;object-fit:cover"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <p style="color:#666;font-size:12px">※ 行を増やしたい時は、空き枠を埋めて保存すると、次回さらに空き枠が増えます。店舗名が空の行は保存されません。</p>
            <p><button type="submit" class="button button-primary button-hero">店舗情報を保存</button></p>
        </form>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-top:8px">
            <h2 style="margin:0 0 8px;font-size:15px">担当者アイコンの入れ方</h2>
            <ol style="margin:0 0 0 18px;line-height:1.9;color:#444;font-size:14px">
                <li>左メニュー「メディア」→「新規追加」で担当者の写真／似顔絵をアップロード</li>
                <li>その画像の「ファイルのURL」をコピー</li>
                <li>上の「担当者アイコンURL」に貼り付けて保存</li>
            </ol>
        </div>
    </div>
    <?php
}

// アイキャッチ画像を1枚生成して設定（ニュース・加盟店ブログ用。文字なし）
function carmel3_img_set_featured($post_id, $s, $force = false) {
    if (!$force && get_post_thumbnail_id($post_id)) return '既存画像あり';
    if ($force) { delete_post_meta($post_id, '_carmel3_img_scene_en'); } // 作り直しは新しい構図で
    if (!function_exists('carmel3_img_generate_openrouter')) return '画像機能なし';
    $model = (isset($s['image_model']) && $s['image_model'] !== '') ? $s['image_model'] : CARMEL3_IMG_DEFAULT_MODEL;
    $ew = !empty($s['eyecatch_w']) ? (int)$s['eyecatch_w'] : 1200;
    $eh = !empty($s['eyecatch_h']) ? (int)$s['eyecatch_h'] : 630;
    $scene = carmel3_img_base_scene($post_id, $s);
    $p = 'Professional automotive editorial visual. Scene: ' . $scene
        . ' . 16:9 horizontal composition, the main subject centered' . carmel3_img_no_text_suffix();
    $img = carmel3_img_generate_openrouter($p, $model);
    if (is_wp_error($img)) return '画像失敗(' . $img->get_error_message() . ')';
    $a = carmel3_img_sideload($post_id, $img['bytes'], $img['mime'], 'eyecatch-' . $post_id, $ew, $eh);
    if (is_wp_error($a)) return '画像保存失敗';
    set_post_thumbnail($post_id, $a);
    if (carmel3_eyecatch_copy_ready($s)) {
        carmel3_eyecatch_overlay($a, $s['eyecatch_copy_text']);
    }
    return 'アイキャッチOK#' . $a;
}

// メタディスクリプションを主要SEOプラグインのフィールドに書き込む（どれが有効でも拾えるように）
function carmel3_write_meta_description($post_id, $desc) {
    $desc = trim(wp_strip_all_tags((string)$desc));
    if ($desc === '') return;
    $desc = mb_substr($desc, 0, 160);
    update_post_meta($post_id, '_yoast_wpseo_metadesc', $desc); // Yoast SEO
    update_post_meta($post_id, 'rank_math_description', $desc);  // Rank Math
    update_post_meta($post_id, '_aioseo_description', $desc);    // All in One SEO（メタ側）
    update_post_meta($post_id, '_genesis_description', $desc);   // Genesis系
    update_post_meta($post_id, '_carmel3_meta_description', $desc);
}

// 英語スラッグを整形。日本語等で空になる場合はキーワード→最終的に carmel-xxxx
function carmel3_make_slug($candidate, $fallback_title, $kw = '') {
    $try = function ($x) {
        $x = sanitize_title(remove_accents((string)$x));
        if ($x === '' || strpos($x, '%') !== false || !preg_match('/[a-z0-9]/', $x)) return '';
        // 長すぎるスラッグは詰める（語単位で最大60字程度）
        if (strlen($x) > 60) $x = substr($x, 0, 60);
        return trim($x, '-');
    };
    foreach (array($candidate, $kw, $fallback_title) as $src) {
        $s = $try($src);
        if ($s !== '') return $s;
    }
    return 'carmel-' . substr(md5($fallback_title . microtime()), 0, 8);
}

// 投稿のスラッグを安全に更新（重複は自動回避）
function carmel3_apply_slug($post_id, $slug) {
    if ($slug === '') return;
    $post = get_post($post_id);
    if (!$post) return;
    $unique = wp_unique_post_slug($slug, $post_id, $post->post_status, $post->post_type, $post->post_parent);
    wp_update_post(array('ID' => $post_id, 'post_name' => $unique));
}

// メディア記事用：タイトルから英語スラッグと日本語メタ説明をAIで作る
function carmel3_ai_slug_meta($title, $kw) {
    $out = array('slug' => '', 'meta' => '');
    if (!function_exists('carmel_call_openrouter_chat')) return $out;
    $sys = 'あなたはSEO編集者です。JSONのみ返答。';
    $usr = "次の日本語記事に対して、(1)英語のURLスラッグ（小文字・ハイフン区切り・5〜7語・記号や日本語なし）、"
        . "(2)日本語のメタディスクリプション（110〜140文字・検索結果に出る説明・誇大表現なし）を作成。\n"
        . "タイトル: {$title}\nキーワード: {$kw}\n"
        . "JSON: {\"slug\":\"\",\"meta_description\":\"\"}";
    $tm = function_exists('carmel3_auto_get_settings') ? (string)(carmel3_auto_get_settings()['text_model'] ?? '') : '';
    $res = carmel_call_openrouter_chat(array(
        array('role' => 'system', 'content' => $sys),
        array('role' => 'user',   'content' => $usr),
    ), $tm, 0.5, 40);
    if (!empty($res['error'])) return $out;
    $content = isset($res['content']) ? (string)$res['content'] : '';
    $json = function_exists('carmel_extract_json_from_text') ? carmel_extract_json_from_text($content) : json_decode(trim($content), true);
    if (is_array($json)) {
        $out['slug'] = isset($json['slug']) ? (string)$json['slug'] : '';
        $out['meta'] = isset($json['meta_description']) ? (string)$json['meta_description'] : '';
    }
    return $out;
}

function carmel3_gen_post($post_type, $item, $s, $existing_id = 0) {
    if (!function_exists('carmel_call_openrouter_chat')) {
        return new WP_Error('no_engine', '本体のAI関数(carmel_call_openrouter_chat)が見つかりません');
    }
    $kw    = ($item['keyword'] !== '') ? $item['keyword'] : $item['title'];
    $title = ($item['title'] !== '') ? $item['title'] : $kw;
    $area  = trim($item['prefecture'] . ' ' . $item['city']);

    $obj = get_post_type_object($post_type);
    $type_label = ($obj && !empty($obj->labels->singular_name)) ? $obj->labels->singular_name : $post_type;

    // 投稿タイプごとの「書き方（AI指示）」
    $instruction = '';
    if (isset($s['type_prompts'][$post_type]) && trim((string)$s['type_prompts'][$post_type]) !== '') {
        $instruction = trim((string)$s['type_prompts'][$post_type]);
    }

    $system = 'あなたは中古車販売店カーメルの日本語編集者です。自然な日本語で、JSONのみ返してください。中国語は禁止。';
    $user = "次の条件で「{$type_label}」向けの記事をJSONで作成してください。\n"
        . "【テーマ/タイトル案】{$title}\n"
        . "【キーワード】{$kw}\n"
        . "【対象地域】{$area}\n";
    if ($instruction !== '') {
        $user .= "【この媒体の書き方（最優先で従う）】\n{$instruction}\n";
    }
    // 店舗情報をランダムに1つ選んで差し込む（伏字・架空情報の防止）
    $store = carmel3_pick_store($item);
    $store_block = carmel3_store_prompt_block($store);
    if ($store_block !== '') {
        $user .= "\n" . $store_block . "\n";
    } else {
        // 店舗未登録：架空の店舗情報を書かせない
        $user .= "\n" . carmel3_no_store_prompt_block() . "\n";
    }
    $user .= "\nJSONキー:\n{\n  \"title\": \"\",\n  \"excerpt\": \"100〜140文字\",\n  \"content_html\": \"<h2>..</h2><p>..</p> 形式\",\n  \"slug\": \"英語・小文字・ハイフン区切り・5〜7語・記号や日本語なし\",\n  \"meta_description\": \"日本語110〜140文字・検索結果に出る説明\"\n}\n"
        . "注意: 自然な日本語 / 本文はHTML / 誇大表現は避ける / slugは必ず英語 / 伏字（〇〇店・XXX等）は禁止 / 上の『書き方』があれば最優先で従う。";

    $text_model = isset($s['text_model']) ? trim((string)$s['text_model']) : '';
    $res = carmel_call_openrouter_chat(array(
        array('role' => 'system', 'content' => $system),
        array('role' => 'user',   'content' => $user),
    ), $text_model, 0.7, 90);

    if (!empty($res['error'])) return new WP_Error('api', $res['error']);

    $content = isset($res['content']) ? (string)$res['content'] : '';
    $json = function_exists('carmel_extract_json_from_text') ? carmel_extract_json_from_text($content) : json_decode(trim($content), true);
    if (!is_array($json)) return new WP_Error('parse', 'AI応答の解析に失敗');

    $t  = sanitize_text_field(isset($json['title']) && $json['title'] !== '' ? $json['title'] : $title);
    $ex = sanitize_textarea_field(isset($json['excerpt']) ? $json['excerpt'] : '');
    $html = wp_kses_post(isset($json['content_html']) ? $json['content_html'] : '');
    // AIが書いた架空の店舗フッターは常に除去
    $html = carmel3_strip_store_footer($html);
    // 店舗登録があれば、伏字を実店舗名に置換し、末尾に正確な店舗カードを付ける
    if (is_array($store)) {
        $html = carmel3_replace_placeholders($html, $store);
        $html .= "\n" . carmel3_store_card_html($store);
    }
    $ai_slug = isset($json['slug']) ? (string)$json['slug'] : '';
    $ai_meta = isset($json['meta_description']) ? (string)$json['meta_description'] : '';
    if (function_exists('carmel_normalize_ja_text')) { $t = carmel_normalize_ja_text($t); }

    $status = !empty($s['publish']) ? 'publish' : 'draft';

    // スラッグ自動変換（英語URL）
    $post_name = '';
    if (!empty($s['auto_slug_meta'])) {
        $post_name = carmel3_make_slug($ai_slug, $t, $kw);
    }

    if ($existing_id) {
        // 作り直し：同じ記事を更新（公開状態は維持）
        $update = array(
            'ID'           => $existing_id,
            'post_title'   => $t,
            'post_content' => $html,
            'post_excerpt' => $ex,
        );
        $r = wp_update_post($update, true);
        if (is_wp_error($r)) return $r;
        $pid = $existing_id;
        if ($post_name !== '') carmel3_apply_slug($pid, $post_name);
    } else {
        $insert = array(
            'post_type'    => $post_type,
            'post_status'  => $status,
            'post_title'   => $t,
            'post_content' => $html,
            'post_excerpt' => $ex,
        );
        if ($post_name !== '') $insert['post_name'] = $post_name;

        $pid = wp_insert_post($insert, true);
        if (is_wp_error($pid)) return $pid;
    }

    update_post_meta($pid, '_carmel3_generated', 1);
    update_post_meta($pid, '_carmel_keyword', $kw);
    update_post_meta($pid, '_carmel3_item', $item); // 作り直し用にテーマを保存

    // メタディスクリプション自動書き込み（無ければ抜粋で代用）
    if (!empty($s['auto_slug_meta'])) {
        carmel3_write_meta_description($pid, $ai_meta !== '' ? $ai_meta : $ex);
    }

    // アイキャッチ画像（「画像も自動生成」がONのとき）。作り直し時は作り直す。
    if (!empty($s['gen_images'])) {
        carmel3_img_set_featured($pid, $s, (bool)$existing_id);
    }

    return $pid;
}

/* ===== 通知（Slack / LINE） ===== */

// 記事のプレビュー素材を作る
function carmel3_post_preview($post_id) {
    $title = get_the_title($post_id);
    $ex    = get_the_excerpt($post_id);
    if (trim($ex) === '') { $ex = wp_trim_words(wp_strip_all_tags(get_post_field('post_content', $post_id)), 60, '…'); }
    $status = get_post_status($post_id);
    $link = ($status === 'publish') ? get_permalink($post_id) : get_edit_post_link($post_id, 'raw');
    $img = '';
    $tid = get_post_thumbnail_id($post_id);
    if ($tid) { $img = wp_get_attachment_image_url($tid, 'large') ?: ''; }
    $ptlabel = '';
    $obj = get_post_type_object(get_post_type($post_id));
    if ($obj) { $ptlabel = $obj->labels->singular_name ?: $obj->label; }
    return array('title' => $title, 'excerpt' => $ex, 'link' => $link, 'image' => $img, 'status' => $status, 'type' => $ptlabel);
}

function carmel3_notify_slack($s, $text) {
    $url = trim((string)$s['slack_webhook']);
    if ($url === '') return 'Slack未設定';
    $r = wp_remote_post($url, array(
        'headers' => array('Content-Type' => 'application/json'),
        'body'    => wp_json_encode(array('text' => $text), JSON_UNESCAPED_UNICODE),
        'timeout' => 20,
    ));
    if (is_wp_error($r)) return 'Slack失敗(' . $r->get_error_message() . ')';
    $code = (int)wp_remote_retrieve_response_code($r);
    return ($code >= 200 && $code < 300) ? 'Slack送信OK' : ('Slack失敗(HTTP' . $code . ')');
}

function carmel3_notify_line($s, $text, $image_url = '') {
    $token = trim((string)$s['line_token']);
    $to    = trim((string)$s['line_to']);
    if ($token === '' || $to === '') return 'LINE未設定';

    $messages = array();
    if ($image_url !== '' && strpos($image_url, 'https://') === 0) {
        $messages[] = array('type' => 'image', 'originalContentUrl' => $image_url, 'previewImageUrl' => $image_url);
    }
    $messages[] = array('type' => 'text', 'text' => mb_substr($text, 0, 4900));

    $r = wp_remote_post('https://api.line.me/v2/bot/message/push', array(
        'headers' => array(
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ),
        'body'    => wp_json_encode(array('to' => $to, 'messages' => $messages), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'timeout' => 20,
    ));
    if (is_wp_error($r)) return 'LINE失敗(' . $r->get_error_message() . ')';
    $code = (int)wp_remote_retrieve_response_code($r);
    if ($code >= 200 && $code < 300) return 'LINE送信OK';
    return 'LINE失敗(HTTP' . $code . ': ' . wp_remote_retrieve_body($r) . ')';
}

// 新しい記事ができたら Slack / LINE に通知（テストアカウント宛のプレビュー）
function carmel3_notify_new_post($s, $post_id, $extra_note = '') {
    $p = carmel3_post_preview($post_id);
    $statusja = ($p['status'] === 'publish') ? '公開' : '下書き';
    $base = "【カーメル 自動生成】{$p['type']}を{$statusja}で作成\n■ {$p['title']}\n{$p['excerpt']}";
    if ($extra_note !== '') $base .= "\n" . $extra_note;
    if (!empty($p['link'])) $base .= "\n" . $p['link'];

    $out = array();
    if (!empty($s['slack_enabled'])) $out[] = carmel3_notify_slack($s, $base);
    if (!empty($s['line_enabled']))  $out[] = carmel3_notify_line($s, $base, $p['image']);
    return implode(' / ', $out);
}

/* ===== 管理画面メニュー（確実に出すため admin_menu 優先度を明示） ===== */

add_action('admin_menu', 'carmel3_auto_register_menu', 99);

function carmel3_auto_register_menu() {
    global $admin_page_hooks;
    $parent = 'carmel-manager';
    if (isset($admin_page_hooks[$parent])) {
        // 「カーメル管理」の中にサブメニューとして入れる
        add_submenu_page($parent, 'かんたんホーム', 'かんたんホーム', 'manage_options', 'carmel3-home', 'carmel3_home_page');
        add_submenu_page($parent, 'CARMEL 自動生成', '自動生成', 'manage_options', 'carmel3-auto', 'carmel3_auto_settings_page');
        add_submenu_page($parent, '店舗・担当者（記事に差し込み）', '店舗・担当者', 'manage_options', 'carmel3-stores', 'carmel3_stores_page');
        add_submenu_page($parent, 'その他掲載ページ（MEO対策）', 'その他掲載ページ（MEO対策）', 'manage_options', 'carmel3-meo', 'carmel3_meo_page');
    } else {
        // 親が見つからない場合は従来どおりトップに出す（消えない保険）
        add_menu_page('かんたんホーム', 'かんたんホーム', 'manage_options', 'carmel3-home', 'carmel3_home_page', 'dashicons-admin-home', 3);
        add_menu_page('CARMEL 自動生成', 'CARMEL自動生成', 'manage_options', 'carmel3-auto', 'carmel3_auto_settings_page', 'dashicons-update', 4);
        add_menu_page('店舗・担当者', '店舗・担当者', 'manage_options', 'carmel3-stores', 'carmel3_stores_page', 'dashicons-store', 5);
        add_menu_page('その他掲載ページ（MEO対策）', 'その他掲載ページ(MEO)', 'manage_options', 'carmel3-meo', 'carmel3_meo_page', 'dashicons-location-alt', 6);
    }
}

/* ===== 「カーメル管理」メニューの表示名から絵文字だけを消す（本体v5.7のコードは触らず、表示だけ整える） ===== */

// 選ばれたトップメニューを「カーメル管理」の中に移設（サブメニュー化）
add_action('admin_menu', 'carmel3_relocate_menus', 100);
add_action('admin_menu', 'carmel3_strip_menu_emoji', 9990);
// 左メニューの全項目を控えておく（隠す前のスナップショット：ホームのリンク集で使う）
add_action('admin_menu', 'carmel3_snapshot_menus', 9995);
// 選ばれたトップメニューを左サイドから隠す（スリム化＋移設元の非表示）
add_action('admin_menu', 'carmel3_hide_menus', 9999);

function carmel3_snapshot_menus() {
    global $menu;
    $GLOBALS['carmel3_all_menus'] = is_array($menu) ? $menu : array();
}

// 隠してはいけないメニュー（ホームへ戻れるよう常に残す）
function carmel3_protected_menus() {
    return array('carmel-manager', 'index.php');
}

// 「おすすめ移設」で既定でカーメル管理に入れるトップメニュー（加盟店ブログ・NEWS）
function carmel3_default_moved_menus() {
    return array('edit.php?post_type=shop_blog', 'edit.php?post_type=news');
}

// 実際に移設するスラッグ一覧（手動で選んだ分＋おすすめ移設ONなら既定分）
function carmel3_effective_moved_menus($s) {
    $moved = (isset($s['moved_menus']) && is_array($s['moved_menus'])) ? $s['moved_menus'] : array();
    if (!empty($s['tidy_default'])) {
        $moved = array_merge($moved, carmel3_default_moved_menus());
    }
    return array_values(array_unique($moved));
}

// 移設：選ばれたトップメニューを「カーメル管理」の中にサブメニューとして追加
function carmel3_relocate_menus() {
    global $menu, $admin_page_hooks;
    if (!isset($admin_page_hooks['carmel-manager'])) return; // 親が無ければ何もしない
    $s = carmel3_auto_get_settings();
    $moved = carmel3_effective_moved_menus($s);
    if (empty($moved)) return;
    $protected = carmel3_protected_menus();

    // スラッグ→ラベルの対応を作る
    $labels = array();
    if (is_array($menu)) {
        foreach ($menu as $m) {
            if (empty($m[2])) continue;
            $raw = preg_replace('/<span[^>]*>.*?<\/span>/u', '', $m[0]);
            $labels[$m[2]] = trim(wp_strip_all_tags($raw));
        }
    }
    foreach ($moved as $slug) {
        if (in_array($slug, $protected, true)) continue;
        $label = isset($labels[$slug]) && $labels[$slug] !== '' ? $labels[$slug] : $slug;
        // スラッグがそのままURL（edit.php?post_type=... 等）になる
        add_submenu_page('carmel-manager', $label, $label, 'manage_options', $slug);
    }
}

function carmel3_hide_menus() {
    $s = carmel3_auto_get_settings();
    $hidden = (isset($s['hidden_menus']) && is_array($s['hidden_menus'])) ? $s['hidden_menus'] : array();
    $moved  = carmel3_effective_moved_menus($s);
    $targets = array_unique(array_merge($hidden, $moved)); // 隠す＋移設元は元の場所から消す
    if (empty($targets)) return;
    $protected = carmel3_protected_menus();
    foreach ($targets as $slug) {
        if (in_array($slug, $protected, true)) continue;
        remove_menu_page($slug);
    }
}

function carmel3_strip_menu_emoji() {
    global $menu, $submenu;

    $strip = function ($label) {
        if (!is_string($label)) return $label;
        // 絵文字・記号・矢印・異体字セレクタ等を除去（日本語・英数字は残す）
        $label = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2190}-\x{21FF}\x{2300}-\x{23FF}\x{2B50}\x{FE0F}\x{200D}]/u', '', $label);
        return trim($label);
    };

    // 親メニュー「カーメル管理」のラベル
    if (is_array($menu)) {
        foreach ($menu as $k => $item) {
            if (isset($item[2]) && $item[2] === 'carmel-manager' && isset($item[0])) {
                $menu[$k][0] = $strip($item[0]);
            }
        }
    }
    // 「カーメル管理」配下のサブメニューのラベル
    if (isset($submenu['carmel-manager']) && is_array($submenu['carmel-manager'])) {
        foreach ($submenu['carmel-manager'] as $k => $item) {
            if (isset($item[0])) {
                $submenu['carmel-manager'][$k][0] = $strip($item[0]);
            }
        }
        // 並び替え（分かりやすい順に整理）。指定外は元の順で後ろに付ける
        $order = array(
            'carmel3-home',     // かんたんホーム
            'carmel-manager',   // 記事生成（親と同じスラッグ）
            'carmel3-auto',     // 自動生成
            'carmel-sns',       // SNS投稿
            'carmel-meo',       // （本体側にあれば）
            'carmel3-meo',      // その他掲載ページ（MEO）
            'carmel-history',   // 生成履歴
            'carmel-settings',  // 設定
        );
        $rows = $submenu['carmel-manager'];
        $bucket = array();
        foreach ($rows as $row) {
            $slug = isset($row[2]) ? $row[2] : '';
            $bucket[$slug][] = $row;
        }
        $sorted = array();
        foreach ($order as $slug) {
            if (!empty($bucket[$slug])) {
                foreach ($bucket[$slug] as $row) { $sorted[] = $row; }
                unset($bucket[$slug]);
            }
        }
        foreach ($bucket as $slug => $list) {
            foreach ($list as $row) { $sorted[] = $row; }
        }
        $submenu['carmel-manager'] = array_values($sorted);
    }
}

/* ===== かんたんホーム（カーメル管理をわかりやすく：本体v5.7には触れません） ===== */

function carmel3_home_page() {
    $s = carmel3_auto_get_settings();
    $next = wp_next_scheduled(CARMEL3_AUTO_HOOK);
    $engine_ok = function_exists('carmel_generate_article_api');
    $api_ok = function_exists('carmel_openrouter_is_ready') ? carmel_openrouter_is_ready() : (carmel3_img_api_key() !== '');
    $remaining = carmel3_auto_remaining_count($s);

    $counts = wp_count_posts('media_article');
    $draft_count = isset($counts->draft) ? (int)$counts->draft : 0;
    $pub_count   = isset($counts->publish) ? (int)$counts->publish : 0;

    $url_gen   = admin_url('admin.php?page=carmel-manager');        // 本体：手動で1記事
    $url_auto  = admin_url('admin.php?page=carmel3-auto');          // 自動生成の設定
    $url_sns   = admin_url('admin.php?page=carmel-sns');            // 本体：SNS投稿
    $url_set   = admin_url('admin.php?page=carmel-settings');       // 本体：設定（APIキー）
    $url_posts = admin_url('edit.php?post_type=media_article');     // 作った記事一覧
    $url_meo   = admin_url('admin.php?page=carmel3-meo');           // MEO（その他掲載ページ）

    // ニュース・加盟店ブログ（存在すれば先頭に大ボタンを出す）
    $quick_posts = array();
    foreach (array('news' => 'NEWS（ニュース）', 'shop_blog' => '加盟店ブログ') as $pt => $lbl) {
        if (!post_type_exists($pt)) continue;
        $obj = get_post_type_object($pt);
        $name = ($obj && !empty($obj->labels->name)) ? $obj->labels->name : $lbl;
        $c = wp_count_posts($pt);
        $quick_posts[$pt] = array(
            'label' => $name,
            'list'  => admin_url('edit.php?post_type=' . $pt),
            'new'   => admin_url('post-new.php?post_type=' . $pt),
            'draft' => isset($c->draft) ? (int)$c->draft : 0,
            'pub'   => isset($c->publish) ? (int)$c->publish : 0,
        );
    }

    $badge = function ($ok, $on = 'ON', $off = 'OFF') {
        $c = $ok ? '#16a34a' : '#9ca3af';
        return '<span style="display:inline-block;padding:2px 10px;border-radius:999px;background:' . $c . ';color:#fff;font-weight:700;font-size:12px">' . ($ok ? $on : $off) . '</span>';
    };
    ?>
    <div style="max-width:1000px;margin:20px auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460);color:#fff;padding:24px;border-radius:16px;margin-bottom:18px">
            <h1 style="margin:0 0 6px;font-size:24px">カーメル かんたんホーム</h1>
            <p style="margin:0;opacity:.9">迷ったらここから。「記事を作る」「自動で毎日作る」「作った記事を見る」がすぐできます。</p>
        </div>

        <?php if (isset($_GET['menusaved'])): ?>
            <div class="notice notice-success" style="margin:0 0 16px"><p>左メニューの表示設定を保存しました。サイドバーを確認してください。</p></div>
        <?php endif; ?>

        <!-- 今の状態 -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:18px">
            <h2 style="margin:0 0 12px;font-size:16px">今の状態</h2>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px">
                <div style="background:#f8fafc;border-radius:10px;padding:12px">
                    <div style="font-size:12px;color:#666">毎日自動生成</div>
                    <div style="margin-top:4px"><?php echo $badge(!empty($s['enabled'])); ?></div>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:12px">
                    <div style="font-size:12px;color:#666">次回の自動実行</div>
                    <div style="margin-top:4px;font-weight:700"><?php echo $next ? esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $next), 'n月j日 H:i')) : '—'; ?></div>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:12px">
                    <div style="font-size:12px;color:#666">未生成テーマ（残り）</div>
                    <div style="margin-top:4px;font-weight:700"><?php echo (int)$remaining; ?> 件</div>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:12px">
                    <div style="font-size:12px;color:#666">記事生成エンジン</div>
                    <div style="margin-top:4px"><?php echo $badge($engine_ok, '接続OK', '未接続'); ?></div>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:12px">
                    <div style="font-size:12px;color:#666">APIキー（OpenRouter）</div>
                    <div style="margin-top:4px"><?php echo $badge($api_ok, '設定済み', '未設定'); ?></div>
                </div>
                <div style="background:#f8fafc;border-radius:10px;padding:12px">
                    <div style="font-size:12px;color:#666">記事数</div>
                    <div style="margin-top:4px;font-weight:700">下書き <?php echo $draft_count; ?> ／ 公開 <?php echo $pub_count; ?></div>
                </div>
            </div>
            <?php if (!empty($s['last_msg'])): ?>
                <p style="margin:12px 0 0;color:#555;font-size:12px">自動生成の最終結果：<?php echo esc_html($s['last_run'] ?: '—'); ?> ／ <?php echo esc_html($s['last_msg'] ?: '—'); ?></p>
            <?php endif; ?>
        </div>

        <!-- やりたいこと（大ボタン） -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:18px">
            <h2 style="margin:0 0 12px;font-size:16px">やりたいことを選ぶ</h2>
            <?php if (!empty($quick_posts)): ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin-bottom:12px">
                <?php
                $qcolors = array('news' => '#0ea5e9', 'shop_blog' => '#db2777');
                foreach ($quick_posts as $pt => $q):
                    $bg = isset($qcolors[$pt]) ? $qcolors[$pt] : '#0f766e';
                ?>
                <div style="display:flex;flex-direction:column;background:<?php echo $bg; ?>;color:#fff;border-radius:12px;padding:14px 16px">
                    <a href="<?php echo esc_url($q['list']); ?>" style="text-decoration:none;color:#fff">
                        <div style="font-size:18px;font-weight:800"><?php echo esc_html($q['label']); ?>を見る</div>
                        <div style="font-size:12px;opacity:.92;margin-top:4px">下書き <?php echo $q['draft']; ?> ／ 公開 <?php echo $q['pub']; ?>　一覧・編集はこちら</div>
                    </a>
                    <a href="<?php echo esc_url($q['new']); ?>" style="margin-top:10px;align-self:flex-start;text-decoration:none;background:rgba(255,255,255,.22);color:#fff;font-weight:700;font-size:12px;padding:6px 12px;border-radius:999px">＋ 新規作成</a>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
                <a href="<?php echo esc_url($url_gen); ?>" style="text-decoration:none;display:block;background:#5b3df5;color:#fff;border-radius:12px;padding:16px">
                    <div style="font-size:18px;font-weight:800">今すぐ1記事つくる</div>
                    <div style="font-size:12px;opacity:.9;margin-top:4px">タイトルを入れてボタンを押すだけ（手動）</div>
                </a>
                <a href="<?php echo esc_url($url_auto); ?>" style="text-decoration:none;display:block;background:#0f766e;color:#fff;border-radius:12px;padding:16px">
                    <div style="font-size:18px;font-weight:800">自動生成の設定</div>
                    <div style="font-size:12px;opacity:.9;margin-top:4px">毎日自動で作る／テーマを登録する</div>
                </a>
                <a href="<?php echo esc_url($url_posts); ?>" style="text-decoration:none;display:block;background:#1f2937;color:#fff;border-radius:12px;padding:16px">
                    <div style="font-size:18px;font-weight:800">作った記事を見る</div>
                    <div style="font-size:12px;opacity:.9;margin-top:4px">下書き・公開記事の一覧／編集</div>
                </a>
                <a href="<?php echo esc_url($url_sns); ?>" style="text-decoration:none;display:block;background:#b45309;color:#fff;border-radius:12px;padding:16px">
                    <div style="font-size:18px;font-weight:800">SNS投稿</div>
                    <div style="font-size:12px;opacity:.9;margin-top:4px">記事をSNS用の文章にして投稿</div>
                </a>
                <a href="<?php echo esc_url($url_meo); ?>" style="text-decoration:none;display:block;background:#047857;color:#fff;border-radius:12px;padding:16px">
                    <div style="font-size:18px;font-weight:800">掲載・MEO対策</div>
                    <div style="font-size:12px;opacity:.9;margin-top:4px">Googleビジネス・Yahoo!プレイス等の管理</div>
                </a>
                <a href="<?php echo esc_url($url_set); ?>" style="text-decoration:none;display:block;background:#374151;color:#fff;border-radius:12px;padding:16px">
                    <div style="font-size:18px;font-weight:800">設定（APIキー）</div>
                    <div style="font-size:12px;opacity:.9;margin-top:4px">OpenRouterキー・ブランド設定</div>
                </a>
            </div>
        </div>

        <!-- 毎日いっしょに作る媒体（自動化の入口をわかりやすく） -->
        <?php
        $auto_extra = (isset($s['extra_types']) && is_array($s['extra_types'])) ? $s['extra_types'] : array();
        $auto_on = !empty($s['enabled']);
        $news_on = in_array('news', $auto_extra, true) || in_array('post', $auto_extra, true);
        $blog_on = in_array('shop_blog', $auto_extra, true);
        $chip = function ($label, $on) {
            $c = $on ? '#16a34a' : '#9ca3af';
            $t = $on ? 'ON' : 'OFF';
            return '<span style="display:inline-block;margin:2px 6px 2px 0;padding:4px 12px;border-radius:999px;background:' . $c . ';color:#fff;font-weight:700;font-size:12px">' . esc_html($label) . '：' . $t . '</span>';
        };
        ?>
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:18px">
            <h2 style="margin:0 0 6px;font-size:16px">毎日いっしょに作る媒体（自動化）</h2>
            <p style="margin:0 0 10px;color:#666;font-size:13px">毎日の自動生成で「ニュース」「加盟店ブログ」も<strong>メディア記事と一緒に</strong>作るかどうかの設定です。下のボタンから設定できます。</p>
            <div style="margin-bottom:12px">
                <?php
                echo $chip('毎日自動生成', $auto_on);
                echo $chip('メディア記事', true);
                echo $chip('ニュース', $news_on);
                echo $chip('加盟店ブログ', $blog_on);
                ?>
            </div>
            <a href="<?php echo esc_url($url_auto . '#media'); ?>" style="text-decoration:none;display:inline-block;background:#0f766e;color:#fff;font-weight:800;border-radius:10px;padding:12px 18px">ニュース・加盟店ブログの自動化を設定する →</a>
            <p style="margin:10px 0 0;color:#888;font-size:12px">設定の流れ：①このボタンを押す → ②作りたい媒体に<strong>チェック</strong>＋書き方を入力 → ③「設定を保存」 → ④上部の「自動生成を有効」をON＋頻度＝毎日。テストは「今すぐ1件だけ生成」。</p>
        </div>

        <!-- すべてのメニュー（左メニューをここに集約） -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:18px">
            <h2 style="margin:0 0 6px;font-size:16px">すべてのメニュー（ここから全部開けます）</h2>
            <p style="margin:0 0 12px;color:#666;font-size:12px">左メニューの項目を自動でまとめています。左がゴチャついても、ここから全機能に移動できます。</p>
            <?php
            $all_menus = isset($GLOBALS['carmel3_all_menus']) && is_array($GLOBALS['carmel3_all_menus']) ? $GLOBALS['carmel3_all_menus'] : $GLOBALS['menu'];
            $links = array();
            if (is_array($all_menus)) {
                foreach ($all_menus as $m) {
                    if (empty($m[0]) || empty($m[2])) continue;
                    if (strpos($m[2], 'separator') !== false) continue;
                    $raw = $m[0];
                    // 更新バッジ等の <span> を除去してからタグ除去
                    $raw = preg_replace('/<span[^>]*>.*?<\/span>/u', '', $raw);
                    $label = trim(wp_strip_all_tags($raw));
                    if ($label === '') continue;
                    $slug = $m[2];
                    if (strpos($slug, '.php') !== false || strpos($slug, '?') !== false) {
                        $url = admin_url($slug);
                    } else {
                        $url = admin_url('admin.php?page=' . $slug);
                    }
                    $links[$url] = $label; // URL重複は1つに
                }
            }
            ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px">
                <?php foreach ($links as $url => $label): ?>
                    <a href="<?php echo esc_url($url); ?>" style="text-decoration:none;display:inline-block;background:#f1f5f9;color:#1f2937;border:1px solid #e2e8f0;border-radius:9px;padding:8px 12px;font-size:13px;font-weight:600"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- 左メニューの整理（移設／非表示） -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:18px">
            <h2 style="margin:0 0 6px;font-size:16px">左メニューの整理（移設・スリム化）</h2>
            <p style="margin:0 0 12px;color:#666;font-size:12px">
                <strong>移設</strong>＝その項目を「カーメル管理」の中に入れて、元のトップは消します（例：加盟店ブログ・NEWS）。<br>
                <strong>隠す</strong>＝左メニューから消すだけ（移設しない）。<br>
                どちらも上の「すべてのメニュー」からは開けます。「カーメル管理」とダッシュボードは常に表示。
            </p>
            <?php
            $hidden_now = (isset($s['hidden_menus']) && is_array($s['hidden_menus'])) ? $s['hidden_menus'] : array();
            $moved_now  = (isset($s['moved_menus'])  && is_array($s['moved_menus']))  ? $s['moved_menus']  : array();
            $protected = function_exists('carmel3_protected_menus') ? carmel3_protected_menus() : array('carmel-manager', 'index.php');
            $menu_opts = array();
            if (is_array($all_menus)) {
                foreach ($all_menus as $m) {
                    if (empty($m[0]) || empty($m[2])) continue;
                    if (strpos($m[2], 'separator') !== false) continue;
                    $slug = $m[2];
                    if (in_array($slug, $protected, true)) continue;
                    $raw = preg_replace('/<span[^>]*>.*?<\/span>/u', '', $m[0]);
                    $lab = trim(wp_strip_all_tags($raw));
                    if ($lab === '') continue;
                    $menu_opts[$slug] = $lab;
                }
            }
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="carmel3_save_menu_visibility">
                <?php wp_nonce_field('carmel3_save_menu_visibility'); ?>
                <label style="display:flex;align-items:center;gap:8px;margin:0 0 12px;padding:10px 12px;border:1px solid #cfe9d6;border-radius:10px;background:#f0fdf4;font-size:13px;font-weight:600">
                    <input type="checkbox" name="tidy_default" value="1" <?php checked(!empty($s['tidy_default'])); ?>>
                    加盟店ブログ・NEWS を自動で「カーメル管理」の中に移設して、元のトップメニューは隠す（おすすめ・既定ON）
                </label>
                <div style="max-height:300px;overflow:auto;border:1px solid #eef2f7;border-radius:10px;background:#fafafa">
                    <table style="width:100%;border-collapse:collapse;font-size:13px">
                        <thead>
                            <tr style="text-align:left;background:#f1f5f9">
                                <th style="padding:8px 10px">メニュー</th>
                                <th style="padding:8px 10px;width:140px">カーメル管理に移設</th>
                                <th style="padding:8px 10px;width:90px">隠す</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($menu_opts as $slug => $lab): ?>
                            <tr style="border-top:1px solid #eef2f7">
                                <td style="padding:7px 10px;font-weight:600"><?php echo esc_html($lab); ?></td>
                                <td style="padding:7px 10px"><input type="checkbox" name="moved_menus[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $moved_now, true)); ?>></td>
                                <td style="padding:7px 10px"><input type="checkbox" name="hidden_menus[]" value="<?php echo esc_attr($slug); ?>" <?php checked(in_array($slug, $hidden_now, true)); ?>></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="margin-top:12px"><button type="submit" class="button button-primary">この内容で保存</button></p>
            </form>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin-top:4px">
                <input type="hidden" name="action" value="carmel3_save_menu_visibility">
                <input type="hidden" name="reset" value="1">
                <?php wp_nonce_field('carmel3_save_menu_visibility'); ?>
                <button type="submit" class="button">全部もとに戻す（移設・非表示を解除）</button>
            </form>
        </div>

        <!-- メニューの使い方ガイド -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px">
            <h2 style="margin:0 0 12px;font-size:16px">メニューの使い方（かんたん説明）</h2>
            <table style="width:100%;border-collapse:collapse;font-size:14px">
                <tr style="border-bottom:1px solid #eee"><td style="padding:10px 8px;width:170px;font-weight:700">かんたんホーム</td><td style="padding:10px 8px;color:#444">今この画面。状態の確認と、各機能への入口。迷ったらここ。</td></tr>
                <tr style="border-bottom:1px solid #eee"><td style="padding:10px 8px;font-weight:700">記事生成</td><td style="padding:10px 8px;color:#444">手動で1記事だけ作る。タイトル・カテゴリ・地域を入れて「記事を生成」。</td></tr>
                <tr style="border-bottom:1px solid #eee"><td style="padding:10px 8px;font-weight:700">自動生成</td><td style="padding:10px 8px;color:#444">テーマを登録しておくと、毎日自動で記事を作る（＋画像・CTA修正・公開）。<strong>毎日運用はここ。</strong></td></tr>
                <tr style="border-bottom:1px solid #eee"><td style="padding:10px 8px;font-weight:700">SNS投稿</td><td style="padding:10px 8px;color:#444">作った記事をSNS用の文章にして投稿（記録）。</td></tr>
                <tr style="border-bottom:1px solid #eee"><td style="padding:10px 8px;font-weight:700">生成履歴</td><td style="padding:10px 8px;color:#444">いつ何を作ったかの記録。</td></tr>
                <tr><td style="padding:10px 8px;font-weight:700">設定</td><td style="padding:10px 8px;color:#444">OpenRouterのAPIキー、ブランド設定（画像の雰囲気など）。最初に1回だけ。</td></tr>
            </table>
            <p style="margin:14px 0 0;color:#666;font-size:12px">※ この「かんたんホーム」は説明用の入口です。本体「カーメル管理 v5.7」の機能はそのまま使います（本体のコードは変更していません）。</p>
        </div>
    </div>
    <?php
}

/* ===== その他掲載ページ（MEO対策）：掲載先サイトの管理リスト（本体v5.7には触れません） ===== */

function carmel3_meo_sites() {
    return array(
        array('id'=>'google_business', 'name'=>'Googleビジネスプロフィール', 'cat'=>'地図検索（最重要）', 'use'=>'Googleマップ／検索の店舗情報・クチコミ。MEOの土台。', 'url'=>'https://business.google.com/'),
        array('id'=>'yahoo_place',     'name'=>'Yahoo!プレイス',           'cat'=>'地図検索', 'use'=>'Yahoo!地図・検索に店舗情報を掲載。', 'url'=>'https://business-place.yahoo.co.jp/'),
        array('id'=>'apple_business',  'name'=>'Appleビジネスコネクト',    'cat'=>'地図検索', 'use'=>'iPhoneのAppleマップに店舗情報を掲載。', 'url'=>'https://businessconnect.apple.com/'),
        array('id'=>'bing_places',     'name'=>'Bing Places',              'cat'=>'地図検索', 'use'=>'Bing地図・検索に店舗情報を掲載。', 'url'=>'https://www.bingplaces.com/'),
        array('id'=>'ekiten',          'name'=>'エキテン',                 'cat'=>'地域ポータル', 'use'=>'地域の口コミ・店舗情報ポータル。', 'url'=>'https://www.ekiten.jp/'),
        array('id'=>'itownpage',       'name'=>'iタウンページ',            'cat'=>'地域ポータル', 'use'=>'NTTタウンページの店舗情報。', 'url'=>'https://itp.ne.jp/'),
        array('id'=>'navitime',        'name'=>'NAVITIME',                 'cat'=>'地図／ナビ', 'use'=>'カーナビ・地図アプリの施設情報。', 'url'=>'https://www.navitime.co.jp/'),
        array('id'=>'mapfan',          'name'=>'MapFan（ゼンリン）',       'cat'=>'地図／ナビ', 'use'=>'ゼンリン地図の施設情報。', 'url'=>'https://mapfan.com/'),
        array('id'=>'carsensor',       'name'=>'カーセンサー',             'cat'=>'中古車ポータル', 'use'=>'リクルートの中古車掲載。集客の主力。', 'url'=>'https://www.carsensor.net/'),
        array('id'=>'goonet',          'name'=>'グーネット',               'cat'=>'中古車ポータル', 'use'=>'プロトの中古車掲載。', 'url'=>'https://www.goo-net.com/'),
        array('id'=>'google_reviews',  'name'=>'Googleクチコミ（返信運用）','cat'=>'口コミ', 'use'=>'クチコミ獲得と返信。MEO順位に影響。', 'url'=>'https://business.google.com/'),
        array('id'=>'instagram',       'name'=>'Instagram',                'cat'=>'SNS', 'use'=>'在庫・事例の発信。プロフィールに地図導線。', 'url'=>'https://www.instagram.com/'),
        array('id'=>'facebook',        'name'=>'Facebookページ',           'cat'=>'SNS', 'use'=>'店舗情報・投稿。Googleにも紐づく。', 'url'=>'https://www.facebook.com/'),
        array('id'=>'line',            'name'=>'LINE公式アカウント',       'cat'=>'SNS／集客', 'use'=>'問い合わせ・再来店の導線。', 'url'=>'https://www.linebiz.com/jp/'),
        array('id'=>'tiktok',          'name'=>'TikTok',                   'cat'=>'SNS', 'use'=>'動画での集客・認知。', 'url'=>'https://www.tiktok.com/'),
    );
}

function carmel3_meo_get_data() {
    $d = get_option('carmel3_meo_listings', array());
    return is_array($d) ? $d : array();
}

// 店舗の基本情報（NAP）。全サイトで統一するための“正”データ。
function carmel3_meo_get_nap() {
    $defaults = array(
        'name' => '', 'zip' => '', 'address' => '', 'tel' => '',
        'hours' => '', 'closed' => '', 'url' => '', 'parking' => '', 'pay' => '',
    );
    $d = get_option('carmel3_meo_nap', array());
    if (!is_array($d)) $d = array();
    return wp_parse_args($d, $defaults);
}

// MEOで使う状況の選択肢
function carmel3_meo_statuses() {
    return array('未対応', '申請中', '登録済み', '要更新');
}

add_action('admin_post_carmel3_meo_save', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_meo_save');

    // 店舗基本情報（NAP）
    $nap_in = (isset($_POST['nap']) && is_array($_POST['nap'])) ? $_POST['nap'] : array();
    $nap = array();
    foreach (array('name','zip','address','tel','hours','closed','parking','pay') as $k) {
        $nap[$k] = isset($nap_in[$k]) ? sanitize_text_field(wp_unslash($nap_in[$k])) : '';
    }
    $nap['url'] = isset($nap_in['url']) ? esc_url_raw(wp_unslash($nap_in['url'])) : '';
    update_option('carmel3_meo_nap', $nap);

    // 各サイトの管理項目
    $statuses = (isset($_POST['status']) && is_array($_POST['status'])) ? $_POST['status'] : array();
    $memos    = (isset($_POST['memo'])   && is_array($_POST['memo']))   ? $_POST['memo']   : array();
    $owners   = (isset($_POST['owner'])  && is_array($_POST['owner']))  ? $_POST['owner']  : array();
    $lurls    = (isset($_POST['lurl'])   && is_array($_POST['lurl']))   ? $_POST['lurl']   : array();
    $updated  = (isset($_POST['updated'])&& is_array($_POST['updated']))? $_POST['updated']: array();
    $valid_st = carmel3_meo_statuses();

    $data = array();
    foreach (carmel3_meo_sites() as $site) {
        $id = $site['id'];
        $st = isset($statuses[$id]) ? sanitize_text_field(wp_unslash($statuses[$id])) : '未対応';
        if (!in_array($st, $valid_st, true)) $st = '未対応';
        $up = isset($updated[$id]) ? sanitize_text_field(wp_unslash($updated[$id])) : '';
        if ($up !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $up)) $up = '';
        $data[$id] = array(
            'status'  => $st,
            'memo'    => isset($memos[$id])  ? sanitize_text_field(wp_unslash($memos[$id]))  : '',
            'owner'   => isset($owners[$id]) ? sanitize_text_field(wp_unslash($owners[$id])) : '',
            'lurl'    => isset($lurls[$id])  ? esc_url_raw(wp_unslash($lurls[$id]))          : '',
            'updated' => $up,
        );
    }
    update_option('carmel3_meo_listings', $data);

    wp_safe_redirect(admin_url('admin.php?page=carmel3-meo&saved=1'));
    exit;
});

function carmel3_meo_page() {
    $sites = carmel3_meo_sites();
    $data  = carmel3_meo_get_data();
    $nap   = carmel3_meo_get_nap();
    $statuses = carmel3_meo_statuses();
    $now = (int) current_time('timestamp');

    // 集計
    $count = array('未対応'=>0, '申請中'=>0, '登録済み'=>0, '要更新'=>0);
    $todo = array();   // 次のアクション
    foreach ($sites as $site) {
        $id = $site['id'];
        $st = isset($data[$id]['status']) ? $data[$id]['status'] : '未対応';
        if (!isset($count[$st])) $st = '未対応';
        $count[$st]++;
        $important = (strpos($site['cat'], '最重要') !== false);
        if ($st === '未対応') {
            $todo[] = array('p'=>$important?0:2, 'site'=>$site, 'msg'=>'未登録です。アカウントを作成して店舗情報を登録', 'url'=>$site['url']);
        } elseif ($st === '申請中') {
            $todo[] = array('p'=>$important?0:3, 'site'=>$site, 'msg'=>'申請中。承認状況を確認し、公開まで進める', 'url'=>$site['url']);
        } elseif ($st === '要更新') {
            $todo[] = array('p'=>1, 'site'=>$site, 'msg'=>'情報が古い可能性。最新のNAP・写真に更新', 'url'=>$site['url']);
        } elseif ($st === '登録済み') {
            $up = isset($data[$id]['updated']) ? $data[$id]['updated'] : '';
            $ts = $up !== '' ? strtotime($up . ' 00:00:00') : 0;
            if ($ts && ($now - $ts) > 60 * DAY_IN_SECONDS) {
                $days = floor(($now - $ts) / DAY_IN_SECONDS);
                $todo[] = array('p'=>4, 'site'=>$site, 'msg'=>'最終更新から' . $days . '日。写真追加・投稿で鮮度UP', 'url'=>($data[$id]['lurl'] !== '' ? $data[$id]['lurl'] : $site['url']));
            }
        }
    }
    usort($todo, function($a, $b){ return $a['p'] <=> $b['p']; });
    $done = $count['登録済み'];
    $total = count($sites);

    // NAPのコピー用：1行テキスト
    $nap_copy = trim(($nap['name'] ? $nap['name'] . "\n" : '')
        . ($nap['zip'] ? '〒' . $nap['zip'] . ' ' : '') . ($nap['address'] ? $nap['address'] . "\n" : '')
        . ($nap['tel'] ? 'TEL ' . $nap['tel'] . "\n" : '')
        . ($nap['hours'] ? '営業 ' . $nap['hours'] . "\n" : '')
        . ($nap['closed'] ? '定休 ' . $nap['closed'] . "\n" : '')
        . ($nap['url'] ? $nap['url'] : ''));

    $nap_fields = array(
        'name'    => array('店名', '例: カーメル 大阪店'),
        'zip'     => array('郵便番号', '例: 530-0001'),
        'address' => array('住所', '例: 大阪府大阪市北区…'),
        'tel'     => array('電話番号', '例: 06-1234-5678'),
        'hours'   => array('営業時間', '例: 10:00〜19:00'),
        'closed'  => array('定休日', '例: 水曜'),
        'url'     => array('サイトURL', 'https://carmelonline.jp/'),
        'parking' => array('駐車場', '例: あり（10台）'),
        'pay'     => array('支払い方法', '例: 現金/カード/ローン'),
    );

    $badge_st = function($st){
        $c = array('未対応'=>'#9ca3af', '申請中'=>'#d97706', '登録済み'=>'#16a34a', '要更新'=>'#dc2626');
        $col = isset($c[$st]) ? $c[$st] : '#9ca3af';
        return '<span style="display:inline-block;padding:2px 8px;border-radius:999px;background:'.$col.';color:#fff;font-size:11px;font-weight:700">'.esc_html($st).'</span>';
    };
    ?>
    <div style="max-width:1100px;margin:20px auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        <div style="background:linear-gradient(135deg,#1a1a2e,#0f3460);color:#fff;padding:24px;border-radius:16px;margin-bottom:18px">
            <h1 style="margin:0 0 6px;font-size:24px">掲載・MEO対策（その他掲載ページ）</h1>
            <p style="margin:0;opacity:.9">Googleマップなどで見つけてもらうための「掲載先の司令塔」。店舗情報を1か所で管理し、各サイトに同じ内容を登録・統一すると効果が出ます。</p>
        </div>

        <?php if (isset($_GET['saved'])): ?>
            <div class="notice notice-success"><p>保存しました。</p></div>
        <?php endif; ?>

        <!-- 進捗ダッシュボード -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin-bottom:16px">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:12px">
                <div style="background:#f0fdf4;border-radius:10px;padding:12px;text-align:center"><div style="font-size:12px;color:#666">登録済み</div><div style="font-size:22px;font-weight:800;color:#16a34a"><?php echo (int)$count['登録済み']; ?></div></div>
                <div style="background:#fef2f2;border-radius:10px;padding:12px;text-align:center"><div style="font-size:12px;color:#666">要更新</div><div style="font-size:22px;font-weight:800;color:#dc2626"><?php echo (int)$count['要更新']; ?></div></div>
                <div style="background:#fffbeb;border-radius:10px;padding:12px;text-align:center"><div style="font-size:12px;color:#666">申請中</div><div style="font-size:22px;font-weight:800;color:#d97706"><?php echo (int)$count['申請中']; ?></div></div>
                <div style="background:#f8fafc;border-radius:10px;padding:12px;text-align:center"><div style="font-size:12px;color:#666">未対応</div><div style="font-size:22px;font-weight:800;color:#6b7280"><?php echo (int)$count['未対応']; ?></div></div>
            </div>
            <strong>登録の進捗：</strong> <?php echo (int)$done; ?> / <?php echo (int)$total; ?> サイト
            <div style="height:10px;background:#eef2f7;border-radius:999px;margin-top:8px;overflow:hidden">
                <div style="height:10px;width:<?php echo $total ? round($done / $total * 100) : 0; ?>%;background:#16a34a"></div>
            </div>
        </div>

        <!-- 次のアクション（自動でやることを提案） -->
        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin-bottom:16px">
            <h2 style="margin:0 0 10px;font-size:15px">次にやること（優先順）</h2>
            <?php if (empty($todo)): ?>
                <p style="margin:0;color:#16a34a;font-weight:700">すべて対応済みです。定期的に写真・投稿を追加して鮮度を保ちましょう。</p>
            <?php else: ?>
                <ol style="margin:0;padding-left:20px;line-height:1.5">
                    <?php foreach (array_slice($todo, 0, 8) as $t): ?>
                        <li style="margin:0 0 8px">
                            <strong><?php echo esc_html($t['site']['name']); ?></strong>
                            <?php if (strpos($t['site']['cat'], '最重要') !== false): ?><span style="background:#dc2626;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;margin-left:4px">最重要</span><?php endif; ?>
                            <span style="color:#444">… <?php echo esc_html($t['msg']); ?></span>
                            <a href="<?php echo esc_url($t['url']); ?>" target="_blank" rel="noopener" style="margin-left:6px;font-size:12px">開く ↗</a>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (count($todo) > 8): ?><p style="margin:8px 0 0;color:#888;font-size:12px">ほか <?php echo count($todo) - 8; ?> 件</p><?php endif; ?>
            <?php endif; ?>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="carmel3_meo_save">
            <?php wp_nonce_field('carmel3_meo_save'); ?>

            <!-- 店舗の基本情報（NAP）：全サイトで統一する“正” -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px;margin-bottom:16px">
                <h2 style="margin:0 0 4px;font-size:15px">店舗の基本情報（NAP）— ここを“正”として全サイトに統一</h2>
                <p style="margin:0 0 12px;color:#666;font-size:12px">店名・住所・電話番号は<strong>一字一句そろえる</strong>のがMEOの基本。ここに入れておけば、各サイト登録時に「コピー」して貼るだけ。</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px">
                    <?php foreach ($nap_fields as $k => $f): ?>
                        <div>
                            <label style="display:block;font-size:12px;color:#555;font-weight:700;margin-bottom:3px"><?php echo esc_html($f[0]); ?></label>
                            <div style="display:flex;gap:6px">
                                <input type="<?php echo $k==='url'?'url':'text'; ?>" name="nap[<?php echo esc_attr($k); ?>]" value="<?php echo esc_attr($nap[$k]); ?>" placeholder="<?php echo esc_attr($f[1]); ?>" style="flex:1;border:1px solid #d1d5db;border-radius:8px;padding:7px 9px" data-copy>
                                <button type="button" class="button button-small" onclick="(function(b){var i=b.previousElementSibling;i.select();navigator.clipboard&&navigator.clipboard.writeText(i.value);b.textContent='済';setTimeout(function(){b.textContent='コピー';},1200);})(this)">コピー</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($nap_copy !== ''): ?>
                <div style="margin-top:12px">
                    <label style="display:block;font-size:12px;color:#555;font-weight:700;margin-bottom:3px">まとめてコピー（登録フォームに一括貼り付け用）</label>
                    <textarea id="carmel3-nap-all" readonly style="width:100%;min-height:90px;border:1px solid #d1d5db;border-radius:8px;padding:8px;font-size:13px;background:#fafafa"><?php echo esc_textarea($nap_copy); ?></textarea>
                    <button type="button" class="button button-small" style="margin-top:6px" onclick="(function(b){var t=document.getElementById('carmel3-nap-all');t.select();navigator.clipboard&&navigator.clipboard.writeText(t.value);b.textContent='コピーしました';setTimeout(function(){b.textContent='まとめてコピー';},1500);})(this)">まとめてコピー</button>
                </div>
                <?php endif; ?>
            </div>

            <!-- 各サイトの管理表 -->
            <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:8px 16px 16px;margin-bottom:16px">
                <h2 style="margin:10px 0 4px;font-size:15px">掲載先サイトの管理</h2>
                <p style="margin:0 0 8px;color:#666;font-size:12px">各サイトの「状況・自店ページURL・担当・最終更新日・メモ」を記録。<strong>最終更新日</strong>を入れておくと、上の「次にやること」が古いサイトを自動でお知らせします。</p>
                <div style="overflow-x:auto">
                <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:860px">
                    <thead>
                        <tr style="text-align:left;border-bottom:2px solid #e5e7eb">
                            <th style="padding:8px 6px;min-width:160px">掲載先</th>
                            <th style="padding:8px 6px;width:100px">状況</th>
                            <th style="padding:8px 6px;min-width:170px">自店ページURL</th>
                            <th style="padding:8px 6px;width:100px">担当</th>
                            <th style="padding:8px 6px;width:130px">最終更新日</th>
                            <th style="padding:8px 6px;min-width:140px">メモ</th>
                            <th style="padding:8px 6px;width:150px">リンク</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($sites as $site):
                        $id = $site['id'];
                        $cur  = isset($data[$id]['status'])  ? $data[$id]['status']  : '未対応';
                        $memo = isset($data[$id]['memo'])    ? $data[$id]['memo']    : '';
                        $own  = isset($data[$id]['owner'])   ? $data[$id]['owner']   : '';
                        $lurl = isset($data[$id]['lurl'])    ? $data[$id]['lurl']    : '';
                        $up   = isset($data[$id]['updated']) ? $data[$id]['updated'] : '';
                        $imp  = (strpos($site['cat'], '最重要') !== false);
                    ?>
                        <tr style="border-bottom:1px solid #f0f0f0;<?php echo $imp?'background:#fffdf5':''; ?>">
                            <td style="padding:8px 6px">
                                <div style="font-weight:700"><?php echo esc_html($site['name']); ?> <?php echo $badge_st($cur); ?></div>
                                <div style="color:#888;font-size:11px"><?php echo esc_html($site['cat']); ?> ／ <?php echo esc_html($site['use']); ?></div>
                            </td>
                            <td style="padding:8px 6px">
                                <select name="status[<?php echo esc_attr($id); ?>]" style="width:100%">
                                    <?php foreach ($statuses as $opt): ?>
                                        <option value="<?php echo esc_attr($opt); ?>" <?php selected($cur, $opt); ?>><?php echo esc_html($opt); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="padding:8px 6px">
                                <input type="url" name="lurl[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr($lurl); ?>" placeholder="自店の掲載ページURL" style="width:100%">
                            </td>
                            <td style="padding:8px 6px">
                                <input type="text" name="owner[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr($own); ?>" placeholder="担当者" style="width:100%">
                            </td>
                            <td style="padding:8px 6px">
                                <input type="date" name="updated[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr($up); ?>" style="width:100%">
                            </td>
                            <td style="padding:8px 6px">
                                <input type="text" name="memo[<?php echo esc_attr($id); ?>]" value="<?php echo esc_attr($memo); ?>" placeholder="ID/補足など" style="width:100%">
                            </td>
                            <td style="padding:8px 6px;white-space:nowrap">
                                <a href="<?php echo esc_url($site['url']); ?>" target="_blank" rel="noopener" class="button button-small">管理画面</a>
                                <?php if ($lurl !== ''): ?><a href="<?php echo esc_url($lurl); ?>" target="_blank" rel="noopener" class="button button-small">自店</a><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>

            <p style="margin:0 0 18px"><button type="submit" class="button button-primary button-hero">この内容で保存</button></p>
        </form>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:16px">
            <h2 style="margin:0 0 8px;font-size:15px">MEO対策のコツ（かんたん）</h2>
            <ul style="margin:0 0 0 18px;line-height:1.9;color:#444">
                <li><strong>店舗情報（NAP）を全サイトで統一</strong>：上の基本情報を“正”にして、各サイトへ「コピー」して貼る。</li>
                <li><strong>写真を多く・新しく</strong>：店舗外観、在庫車、スタッフ。Googleは更新を評価。最終更新日を記録して鮮度を管理。</li>
                <li><strong>クチコミを集めて返信</strong>：来店客にお願い→届いたら必ず返信。</li>
                <li><strong>Google投稿をこまめに</strong>：自動生成記事をGoogleビジネスにも投稿（自動生成のGoogle投稿機能）。</li>
            </ul>
        </div>
    </div>
    <?php
}

add_action('admin_post_carmel3_auto_save', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_auto_save');

    $s = carmel3_auto_get_settings();
    $s['enabled']    = isset($_POST['enabled']) ? 1 : 0;
    $s['publish']    = isset($_POST['publish']) ? 1 : 0;
    $s['gen_images'] = isset($_POST['gen_images']) ? 1 : 0;
    $s['gen_section_images'] = isset($_POST['gen_section_images']) ? 1 : 0;
    $s['eyecatch_copy'] = isset($_POST['eyecatch_copy']) ? 1 : 0;
    if (isset($_POST['eyecatch_copy_text'])) {
        $s['eyecatch_copy_text'] = sanitize_text_field(wp_unslash($_POST['eyecatch_copy_text']));
    }
    $s['fix_cta']    = isset($_POST['fix_cta']) ? 1 : 0;
    $s['gmb_post']   = isset($_POST['gmb_post']) ? 1 : 0;

    $cu = isset($_POST['contact_url']) ? esc_url_raw(wp_unslash($_POST['contact_url'])) : '';
    $s['contact_url'] = $cu;

    $s['eyecatch_w'] = isset($_POST['eyecatch_w']) ? max(200, (int)$_POST['eyecatch_w']) : 1200;
    $s['eyecatch_h'] = isset($_POST['eyecatch_h']) ? max(200, (int)$_POST['eyecatch_h']) : 630;
    $s['banner_w']   = isset($_POST['banner_w'])   ? max(200, (int)$_POST['banner_w'])   : 1200;
    $s['banner_h']   = isset($_POST['banner_h'])   ? max(150, (int)$_POST['banner_h'])   : 400;
    $s['sec_w']      = isset($_POST['sec_w'])      ? max(200, (int)$_POST['sec_w'])      : 1200;
    $s['sec_h']      = isset($_POST['sec_h'])      ? max(200, (int)$_POST['sec_h'])      : 675;

    $freq = isset($_POST['frequency']) ? sanitize_key($_POST['frequency']) : 'carmel_weekly';
    $s['frequency'] = in_array($freq, array('daily', 'carmel_weekly', 'carmel_twiceweekly'), true) ? $freq : 'carmel_weekly';

    $rt = isset($_POST['run_time']) ? trim((string) wp_unslash($_POST['run_time'])) : '09:00';
    $s['run_time'] = preg_match('/^(\d{1,2}):(\d{2})$/', $rt) ? $rt : '09:00';

    // max_tokens 上限（0=制限なし。0〜65536の範囲）
    if (isset($_POST['max_tokens_cap'])) {
        $mtc = (int) $_POST['max_tokens_cap'];
        $s['max_tokens_cap'] = max(0, min(65536, $mtc));
    }

    $s['auto_slug_meta'] = isset($_POST['auto_slug_meta']) ? 1 : 0;
    $s['text_model'] = isset($_POST['text_model']) ? sanitize_text_field(wp_unslash($_POST['text_model'])) : '';

    $im = isset($_POST['image_model']) ? sanitize_text_field(wp_unslash($_POST['image_model'])) : '';
    $s['image_model'] = $im !== '' ? $im : CARMEL3_IMG_DEFAULT_MODEL;

    $bf = isset($_POST['banner_field']) ? sanitize_key($_POST['banner_field']) : '';
    $s['banner_field'] = $bf !== '' ? $bf : 'hero_image';

    // 追加で生成する投稿タイプ（チェックされたものだけ・実在するものだけ）
    $sel = (isset($_POST['extra_types']) && is_array($_POST['extra_types'])) ? $_POST['extra_types'] : array();
    $valid = array();
    $allow = carmel3_selectable_post_types();
    foreach ($sel as $pt) {
        $pt = sanitize_key($pt);
        if (isset($allow[$pt])) $valid[] = $pt;
    }
    $s['extra_types'] = array_values(array_unique($valid));

    // 投稿タイプごとの書き方（AI指示プロンプト）
    $tp_in = (isset($_POST['type_prompt']) && is_array($_POST['type_prompt'])) ? $_POST['type_prompt'] : array();
    $tp = array();
    foreach ($allow as $pt => $label) {
        if (isset($tp_in[$pt])) {
            $v = sanitize_textarea_field(wp_unslash($tp_in[$pt]));
            if (trim($v) !== '') $tp[$pt] = $v;
        }
    }
    $s['type_prompts'] = $tp;

    // 通知設定
    $non = isset($_POST['notify_on']) ? sanitize_key($_POST['notify_on']) : 'draft';
    $s['notify_on']     = in_array($non, array('draft', 'publish'), true) ? $non : 'draft';
    $s['slack_enabled'] = isset($_POST['slack_enabled']) ? 1 : 0;
    $s['slack_webhook'] = isset($_POST['slack_webhook']) ? esc_url_raw(wp_unslash($_POST['slack_webhook'])) : '';
    $s['line_enabled']  = isset($_POST['line_enabled']) ? 1 : 0;
    $s['line_token']    = isset($_POST['line_token']) ? sanitize_text_field(wp_unslash($_POST['line_token'])) : '';
    $s['line_to']       = isset($_POST['line_to']) ? sanitize_text_field(wp_unslash($_POST['line_to'])) : '';

    $s['queue'] = carmel3_auto_parse_queue(isset($_POST['queue']) ? wp_unslash($_POST['queue']) : '');

    carmel3_auto_save_settings($s);
    carmel3_auto_reschedule();

    wp_safe_redirect(admin_url('admin.php?page=carmel3-auto&saved=1'));
    exit;
});

// テスト送信（Slack / LINE）
add_action('admin_post_carmel3_test_notify', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_test_notify');

    $s = carmel3_auto_get_settings();
    $which = isset($_POST['which']) ? sanitize_key($_POST['which']) : '';
    $msg = '【カーメル】通知テスト送信です。これが届けばOK。';
    $r = '';
    if ($which === 'slack') {
        $r = carmel3_notify_slack($s, $msg);
    } elseif ($which === 'line') {
        $r = carmel3_notify_line($s, $msg);
    }
    set_transient('carmel3_test_notify_msg', $which . ': ' . $r, 120);

    wp_safe_redirect(admin_url('admin.php?page=carmel3-auto&testnotify=1'));
    exit;
});

// 左メニューのスリム化（隠すメニューの保存）
add_action('admin_post_carmel3_save_menu_visibility', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_save_menu_visibility');

    $s = carmel3_auto_get_settings();
    if (!empty($_POST['reset'])) {
        $s['hidden_menus'] = array();
        $s['moved_menus']  = array();
        $s['tidy_default'] = 0; // 「全部もとに戻す」はおすすめ移設もOFFにする
    } else {
        $s['tidy_default'] = !empty($_POST['tidy_default']) ? 1 : 0;
        $protected = function_exists('carmel3_protected_menus') ? carmel3_protected_menus() : array('carmel-manager', 'index.php');
        $clean = function ($key) use ($protected) {
            $in = (isset($_POST[$key]) && is_array($_POST[$key])) ? $_POST[$key] : array();
            $out = array();
            foreach ($in as $slug) {
                $slug = sanitize_text_field(wp_unslash($slug));
                if ($slug === '' || in_array($slug, $protected, true)) continue;
                $out[] = $slug;
            }
            return array_values(array_unique($out));
        };
        $s['hidden_menus'] = $clean('hidden_menus');
        $s['moved_menus']  = $clean('moved_menus');
    }
    carmel3_auto_save_settings($s);

    wp_safe_redirect(admin_url('admin.php?page=carmel3-home&menusaved=1'));
    exit;
});

/* ===== 記事の作り直し（本文＋画像を作り直す） ===== */

// この投稿タイプは作り直し対応（メディア記事＝本体、その他＝アドオン生成）
function carmel3_regen_supported($post_type) {
    if ($post_type === 'media_article') return true;
    $sel = carmel3_selectable_post_types();
    return isset($sel[$post_type]);
}

// 記事を作り直す（同じ記事を更新。本文も画像も新しく作る）
function carmel3_regenerate_post($post_id) {
    @set_time_limit(300);
    $post_id = intval($post_id);
    $post = get_post($post_id);
    if (!$post) return false;
    $s = carmel3_auto_get_settings();

    // テーマ（作成時に保存）を復元。無ければ記事から最低限を組み立て
    $item = get_post_meta($post_id, '_carmel3_item', true);
    if (!is_array($item)) {
        $kw = (string) get_post_meta($post_id, '_carmel_keyword', true);
        if ($kw === '') $kw = get_the_title($post_id);
        $item = array('account'=>'main','category'=>'','keyword'=>$kw,'prefecture'=>'','city'=>'','title'=>get_the_title($post_id));
    }
    $item = wp_parse_args($item, array('account'=>'main','category'=>'','keyword'=>'','prefecture'=>'','city'=>'','title'=>''));

    $pt = $post->post_type;
    delete_post_meta($post_id, '_carmel3_img_scene_en'); // 画像も新しい構図で
    carmel3_progress_set('記事を作り直しています…（本文）', 20, 'running', array('post_id'=>$post_id,'title'=>get_the_title($post_id)));

    if ($pt === 'media_article') {
        if (!function_exists('carmel_generate_article_api')) {
            carmel3_progress_set('エラー: 本体プラグイン(v5.7)が必要です', 100, 'error');
            return false;
        }
        $title = $item['title'] !== '' ? $item['title'] : $item['keyword'];
        $req = new WP_REST_Request('POST', '/carmel/v1/generate');
        $req->set_header('Content-Type', 'application/json');
        $req->set_body(wp_json_encode(array(
            'title'=>$title, 'category'=>$item['category'], 'account'=>$item['account']!==''?$item['account']:'main',
            'prefecture'=>$item['prefecture'], 'city'=>$item['city'], 'keyword'=>$item['keyword'],
        ), JSON_UNESCAPED_UNICODE));
        $res = carmel_generate_article_api($req);
        $data = ($res instanceof WP_REST_Response) ? $res->get_data() : array();
        if (empty($data['success'])) {
            $msg = isset($data['message']) ? $data['message'] : '不明なエラー';
            carmel3_progress_set('エラー: 作り直しに失敗（' . $msg . '）', 100, 'error');
            carmel3_loglist_add('作り直し失敗 post#' . $post_id . ' / ' . $msg, false);
            return false;
        }
        $tmp = intval($data['post_id']);
        $tmpPost = get_post($tmp);
        if ($tmpPost) {
            // 本文・タイトル・抜粋を移植（同じ記事＝URLそのまま）
            wp_update_post(array('ID'=>$post_id,'post_title'=>$tmpPost->post_title,'post_content'=>$tmpPost->post_content,'post_excerpt'=>$tmpPost->post_excerpt));
            // メタ（ACF等）を移植
            $skip = array('_edit_lock','_edit_last','_carmel3_item','_carmel3_generated');
            $metas = get_post_meta($tmp);
            if (is_array($metas)) {
                foreach ($metas as $k=>$vs) {
                    if (in_array($k, $skip, true)) continue;
                    delete_post_meta($post_id, $k);
                    foreach ((array)$vs as $v) { add_post_meta($post_id, $k, maybe_unserialize($v)); }
                }
            }
            $thumb = get_post_thumbnail_id($tmp);
            if ($thumb) set_post_thumbnail($post_id, $thumb);
            wp_delete_post($tmp, true); // 一時記事は完全削除
        }
        update_post_meta($post_id, '_carmel3_item', $item);
        // 仕上げ：CTA／画像（作り直し）／slug・meta／店舗
        if (!empty($s['fix_cta'])) carmel3_auto_fix_cta($post_id, $s);
        carmel3_progress_set('画像を作り直しています…（1〜2分）', 60, 'running', array('post_id'=>$post_id));
        if (!empty($s['gen_images'])) {
            carmel3_img_attach_to_post($post_id); // アイキャッチ＋バナー作り直し
            if (!empty($s['gen_section_images'])) {
                // セクション画像も作り直す：既存を空にしてジョブを再構築
                foreach (array(CARMEL3_F_SEC1_IMG=>'section_1_image', CARMEL3_F_SEC2_IMG=>'section_2_image', CARMEL3_F_SEC3_IMG=>'section_3_image') as $key=>$name) {
                    carmel3_auto_set_image_field($post_id, $key, $name, '');
                }
                $jobs = carmel3_auto_build_section_jobs($post_id, $s);
                if (!empty($jobs)) {
                    update_post_meta($post_id, '_carmel_pending_img_jobs', $jobs);
                    if (!wp_next_scheduled(CARMEL3_AUTO_IMGHOOK, array($post_id))) wp_schedule_single_event(time()+10, CARMEL3_AUTO_IMGHOOK, array($post_id));
                    if (function_exists('spawn_cron')) spawn_cron();
                }
            }
        }
        if (!empty($s['auto_slug_meta'])) {
            $sm = carmel3_ai_slug_meta(get_the_title($post_id) ?: $title, $item['keyword']);
            $slug = carmel3_make_slug($sm['slug'], get_the_title($post_id) ?: $title, $item['keyword']);
            if ($slug !== '') carmel3_apply_slug($post_id, $slug);
            carmel3_write_meta_description($post_id, $sm['meta'] !== '' ? $sm['meta'] : get_the_excerpt($post_id));
        }
        $store = carmel3_pick_store($item);
        $body = (string) get_post_field('post_content', $post_id);
        $new  = carmel3_strip_store_footer($body);
        if (is_array($store)) {
            $new = carmel3_replace_placeholders($new, $store);
            if (strpos($new, 'carmel-store-card') === false) $new .= "\n" . carmel3_store_card_html($store);
        }
        if ($new !== $body) wp_update_post(array('ID'=>$post_id,'post_content'=>$new));

    } else {
        // ニュース・加盟店ブログ等：その場で作り直し（本文＋アイキャッチ）
        $r = carmel3_gen_post($pt, $item, $s, $post_id);
        if (is_wp_error($r)) {
            carmel3_progress_set('エラー: 作り直しに失敗（' . $r->get_error_message() . '）', 100, 'error');
            carmel3_loglist_add('作り直し失敗 post#' . $post_id . ' / ' . $r->get_error_message(), false);
            return false;
        }
    }

    carmel3_progress_set('作り直し完了：「' . get_the_title($post_id) . '」', 100, 'done', array('post_id'=>$post_id,'title'=>get_the_title($post_id)));
    carmel3_loglist_add('作り直し成功 post#' . $post_id . '（' . get_the_title($post_id) . '）', true);
    return true;
}

// 作り直しボタンの実行（裏側で実行して画面を固まらせない）
add_action('admin_post_carmel3_regen', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    $post_id = isset($_GET['post']) ? intval($_GET['post']) : 0;
    check_admin_referer('carmel3_regen_' . $post_id);
    if (!$post_id || !get_post($post_id)) wp_die('記事が見つかりません');

    carmel3_progress_set('作り直しを開始しました。準備中…', 3, 'running', array('post_id'=>$post_id));
    if (!wp_next_scheduled(CARMEL3_REGEN_HOOK, array($post_id))) {
        wp_schedule_single_event(time(), CARMEL3_REGEN_HOOK, array($post_id));
    }
    if (function_exists('spawn_cron')) spawn_cron();

    $back = wp_get_referer();
    if (!$back) $back = admin_url('edit.php?post_type=' . get_post_type($post_id));
    $back = add_query_arg('carmel3_regen', '1', $back);
    wp_safe_redirect($back);
    exit;
});

// 作り直しURL（ノンス付き）
function carmel3_regen_url($post_id) {
    return wp_nonce_url(admin_url('admin-post.php?action=carmel3_regen&post=' . intval($post_id)), 'carmel3_regen_' . intval($post_id));
}

// 投稿一覧に「作り直す」行アクションを追加
add_filter('post_row_actions', 'carmel3_regen_row_action', 10, 2);
add_filter('page_row_actions', 'carmel3_regen_row_action', 10, 2);
function carmel3_regen_row_action($actions, $post) {
    if (current_user_can('manage_options') && carmel3_regen_supported($post->post_type)) {
        $actions['carmel3_regen'] = '<a href="' . esc_url(carmel3_regen_url($post->ID)) . '" style="color:#0f766e;font-weight:600" onclick="return confirm(\'この記事を作り直します（本文も画像も作り直します）。よろしいですか？\');">カーメル：作り直す</a>';
    }
    return $actions;
}

// 編集画面に「作り直す」メタボックス
add_action('add_meta_boxes', function () {
    foreach (array_keys(carmel3_selectable_post_types()) as $pt) {
        add_meta_box('carmel3_regen_box', 'カーメル：作り直し', 'carmel3_regen_metabox', $pt, 'side', 'high');
    }
    add_meta_box('carmel3_regen_box', 'カーメル：作り直し', 'carmel3_regen_metabox', 'media_article', 'side', 'high');
});
function carmel3_regen_metabox($post) {
    if (!carmel3_regen_supported($post->post_type)) { echo '<p>この投稿タイプは未対応です。</p>'; return; }
    echo '<p style="margin:0 0 10px;color:#555;font-size:12px">内容を確認して気に入らない場合、AIで<strong>本文も画像も作り直し</strong>ます（同じURLのまま）。</p>';
    echo '<a href="' . esc_url(carmel3_regen_url($post->ID)) . '" class="button button-primary" style="width:100%;text-align:center" onclick="return confirm(\'本文も画像も作り直します。よろしいですか？\');">この記事を作り直す</a>';
    echo '<p style="margin:10px 0 0;color:#888;font-size:11px">裏側で実行します（画像ありは1〜3分）。完了後にこの画面を再読み込みしてください。</p>';
}

// 作り直し開始の通知
add_action('admin_notices', function () {
    if (isset($_GET['carmel3_regen'])) {
        echo '<div class="notice notice-success is-dismissible"><p>記事の作り直しを開始しました。本文と画像が新しく作られます（画像ありは1〜3分）。完了後にページを再読み込みしてください。</p></div>';
    }
});

add_action('admin_post_carmel3_auto_run_now', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_auto_run_now');

    // 画面を固まらせないよう、裏側で実行。進捗は下のバーに出る
    carmel3_progress_set('生成を開始しました。準備中…', 3, 'running');
    if (!wp_next_scheduled(CARMEL3_AUTO_RUNHOOK)) {
        wp_schedule_single_event(time(), CARMEL3_AUTO_RUNHOOK);
    }
    if (function_exists('spawn_cron')) { spawn_cron(); }

    wp_safe_redirect(admin_url('admin.php?page=carmel3-auto&started=1#carmel3-prog'));
    exit;
});

// 配列を再帰的にたどり、max_tokens（大きすぎる値）を上限まで下げる。戻り値: 変更件数
function carmel3_lower_max_tokens_deep(&$data, $cap) {
    $count = 0;
    if (!is_array($data)) return 0;
    foreach ($data as $k => &$v) {
        if (is_array($v)) {
            $count += carmel3_lower_max_tokens_deep($v, $cap);
        } else {
            $kl = is_string($k) ? strtolower($k) : '';
            if (($kl === 'max_tokens' || $kl === 'maxtokens' || $kl === 'max_output_tokens' || $kl === 'max_completion_tokens')
                && is_numeric($v) && (int)$v > $cap) {
                $v = $cap;
                $count++;
            }
        }
    }
    unset($v);
    return $count;
}

// 本体が保存している max_tokens を安全値まで下げる（クレジット不要・本体コードは無改変）
add_action('admin_post_carmel3_fix_tokens', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_fix_tokens');

    $s = carmel3_auto_get_settings();
    $cap = isset($s['max_tokens_cap']) && (int)$s['max_tokens_cap'] > 0 ? (int)$s['max_tokens_cap'] : 12000;

    global $wpdb;
    $like = '%' . $wpdb->esc_like('max_tokens') . '%';
    $rows = $wpdb->get_results(
        $wpdb->prepare("SELECT option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE %s LIMIT 100", $like)
    );

    $changed = array();
    foreach ((array)$rows as $r) {
        $name = $r->option_name;
        if (strpos($name, '_transient') !== false) continue; // 一時データは触らない
        $raw  = $r->option_value;

        // 1) シリアライズされた配列
        $val = maybe_unserialize($raw);
        if (is_array($val)) {
            $tmp = $val;
            $n = carmel3_lower_max_tokens_deep($tmp, $cap);
            if ($n > 0) { update_option($name, $tmp); $changed[] = $name . '（' . $n . '箇所）'; }
            continue;
        }
        // 2) JSON文字列
        if (is_string($raw)) {
            $j = json_decode($raw, true);
            if (is_array($j)) {
                $tmp = $j;
                $n = carmel3_lower_max_tokens_deep($tmp, $cap);
                if ($n > 0) {
                    update_option($name, wp_json_encode($tmp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                    $changed[] = $name . '（' . $n . '箇所・JSON）';
                }
            }
        }
    }

    $msg = !empty($changed)
        ? ('本体設定の最大トークンを ' . $cap . ' に下げました：' . implode('、', $changed) . '。もう一度「今すぐ1件だけ生成」をお試しください。')
        : ('対象の設定が見つかりませんでした。本体がモデルの上限に合わせて自動で65536を決めている可能性があります。その場合は「設定」でモデルを無料モデル（例 deepseek/deepseek-chat-v3-0324:free）に変更してください。');
    set_transient('carmel3_fix_tokens_msg', $msg, 120);

    wp_safe_redirect(admin_url('admin.php?page=carmel3-auto&fixtokens=1'));
    exit;
});

add_action('admin_post_carmel3_auto_reset_done', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_auto_reset_done');

    $s = carmel3_auto_get_settings();
    $s['done'] = array();
    carmel3_auto_save_settings($s);

    wp_safe_redirect(admin_url('admin.php?page=carmel3-auto&reset=1'));
    exit;
});

// 既存の全ページのCTAをまとめてチェック＆修正
add_action('admin_post_carmel3_auto_fix_cta_all', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_auto_fix_cta_all');

    $s = carmel3_auto_get_settings();
    $r = carmel3_auto_fix_cta_bulk($s);
    set_transient('carmel3_auto_cta_bulk_msg', $r, 120);

    wp_safe_redirect(admin_url('admin.php?page=carmel3-auto&ctaall=1'));
    exit;
});

// 本文画像（保留中ジョブ）を手動で1枚だけ進める
add_action('admin_post_carmel3_auto_process_image', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_auto_process_image');

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if ($post_id) {
        carmel3_auto_process_one_image($post_id);
    }

    wp_safe_redirect(admin_url('admin.php?page=carmel3-auto&imgran=1'));
    exit;
});

// 進捗パネル（ただいま生成中…）＋最近のログ。自動で更新（ポーリング）する。
function carmel3_render_progress_panel() {
    $p = carmel3_progress_get();
    $state = isset($p['state']) ? $p['state'] : 'idle';
    $step  = isset($p['step'])  ? $p['step']  : '待機中';
    $pct   = isset($p['pct'])   ? (int)$p['pct'] : 0;
    $when  = isset($p['time'])  ? $p['time']  : '';
    $ajax  = admin_url('admin-ajax.php');
    $list  = carmel3_loglist_get();
    ?>
    <div id="carmel3-prog" style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:16px;scroll-margin-top:40px">
        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap">
            <h2 style="margin:0;font-size:16px">生成の進捗</h2>
            <span id="carmel3-prog-badge" style="font-size:12px;font-weight:700;padding:3px 10px;border-radius:999px;background:#9ca3af;color:#fff">—</span>
        </div>
        <p id="carmel3-prog-step" style="margin:10px 0 6px;font-size:15px;font-weight:700;color:#111"><?php echo esc_html($step); ?></p>
        <div style="height:14px;background:#eef2f7;border-radius:999px;overflow:hidden">
            <div id="carmel3-prog-bar" style="height:14px;width:<?php echo (int)$pct; ?>%;background:#0f766e;transition:width .4s"></div>
        </div>
        <p id="carmel3-prog-meta" style="margin:8px 0 0;color:#777;font-size:12px"><?php echo $when ? '更新: ' . esc_html($when) : ''; ?></p>
        <p style="margin:8px 0 0;color:#999;font-size:12px">※ この画面は自動で更新されます。画像ありは完了まで1〜3分かかります。</p>

        <?php if (!empty($list)): ?>
        <details style="margin-top:14px">
            <summary style="cursor:pointer;font-weight:700;font-size:13px">最近の実行ログ（<?php echo count($list); ?>件）</summary>
            <ul style="margin:8px 0 0;padding-left:18px;line-height:1.6;font-size:12px;color:#444">
                <?php foreach ($list as $row):
                    $ok = !empty($row['ok']); ?>
                    <li style="margin:0 0 4px">
                        <span style="color:<?php echo $ok ? '#16a34a' : '#dc2626'; ?>;font-weight:700"><?php echo $ok ? '成功' : '失敗'; ?></span>
                        <span style="color:#888"><?php echo esc_html(isset($row['time']) ? $row['time'] : ''); ?></span>
                        — <?php echo esc_html(isset($row['msg']) ? $row['msg'] : ''); ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </details>
        <?php endif; ?>
    </div>
    <script>
    (function(){
        var ajax = <?php echo wp_json_encode($ajax); ?>;
        var bar = document.getElementById('carmel3-prog-bar');
        var stepEl = document.getElementById('carmel3-prog-step');
        var metaEl = document.getElementById('carmel3-prog-meta');
        var badge = document.getElementById('carmel3-prog-badge');
        var idle = 0;
        function paint(d){
            if(!d) return;
            var pct = parseInt(d.pct||0,10);
            bar.style.width = pct + '%';
            if(d.step) stepEl.textContent = d.step;
            if(d.time) metaEl.textContent = '更新: ' + d.time;
            var st = d.state || 'idle';
            var map = {running:['生成中…','#0ea5e9'], done:['完了','#16a34a'], error:['エラー','#dc2626'], idle:['待機中','#9ca3af']};
            var m = map[st] || map.idle;
            badge.textContent = m[0]; badge.style.background = m[1];
            bar.style.background = (st==='error') ? '#dc2626' : (st==='done' ? '#16a34a' : '#0f766e');
        }
        function tick(){
            fetch(ajax + '?action=carmel3_auto_progress', {credentials:'same-origin'})
              .then(function(r){return r.json();})
              .then(function(j){
                  if(j && j.success){
                      paint(j.data);
                      var st = (j.data && j.data.state) || 'idle';
                      if(st==='running'){ idle=0; }
                      else { idle++; }
                      // 完了/エラー後はしばらくしたらポーリング間隔を落とす
                      if(st==='done' || st==='error'){
                          if(idle>3){ return; } // 止める
                      }
                  }
              })
              .catch(function(){});
        }
        tick();
        setInterval(tick, 3000);
    })();
    </script>
    <?php
}

function carmel3_auto_settings_page() {
    $s = carmel3_auto_get_settings();
    $categories = function_exists('carmel_get_categories') ? carmel_get_categories() : array();
    $next = wp_next_scheduled(CARMEL3_AUTO_HOOK);
    $engine_ok = function_exists('carmel_generate_article_api');
    $acf_ok = function_exists('update_field');
    ?>
    <div class="carmel-wrap" style="max-width:1100px;margin:20px auto;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        <div class="carmel-header" style="background:linear-gradient(135deg,#1a1a2e,#0f3460);color:#fff;padding:24px;border-radius:16px;margin-bottom:20px">
            <h1 style="margin:0 0 6px">記事 自動生成（WP-Cron）＋ 画像2枚</h1>
            <p style="margin:0;opacity:.9">既存エンジン(v5.7)で記事を生成し、アイキャッチ＋トップバナーの画像も自動で付与します。</p>
        </div>

        <?php if (isset($_GET['saved'])): ?>
            <div class="notice notice-success"><p>設定を保存しました。</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['ran'])): ?>
            <div class="notice notice-info"><p>テスト実行しました。下の「最終実行」を確認してください。</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['started'])): ?>
            <div class="notice notice-success"><p>生成を開始しました。下の<strong>「生成の進捗」</strong>バーで状況が自動更新されます（画像ありは1〜3分かかります）。</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['fixtokens'])):
            $ft = get_transient('carmel3_fix_tokens_msg'); delete_transient('carmel3_fix_tokens_msg');
            if ($ft): ?>
            <div class="notice notice-info"><p><?php echo esc_html($ft); ?></p></div>
        <?php endif; endif; ?>
        <?php if (isset($_GET['reset'])): ?>
            <div class="notice notice-success"><p>生成済み記録をリセットしました。テーマキューを最初からもう一度生成できます。</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['imgran'])): ?>
            <div class="notice notice-info"><p>本文画像を1枚処理しました。残りがあれば、もう一度ボタンを押してください。</p></div>
        <?php endif; ?>
        <?php if (isset($_GET['ctaall'])):
            $cta_bulk = get_transient('carmel3_auto_cta_bulk_msg');
            delete_transient('carmel3_auto_cta_bulk_msg');
            if (is_array($cta_bulk)): ?>
            <div class="notice notice-success"><p>CTA一括チェック完了：<strong><?php echo (int)$cta_bulk['checked']; ?></strong>ページを確認し、<strong><?php echo (int)$cta_bulk['posts']; ?></strong>ページ（<strong><?php echo (int)$cta_bulk['fields']; ?></strong>箇所）を修正しました。</p></div>
        <?php endif; endif; ?>
        <?php if (isset($_GET['testnotify'])):
            $tn = get_transient('carmel3_test_notify_msg'); delete_transient('carmel3_test_notify_msg');
            if ($tn): ?>
            <div class="notice notice-info"><p>テスト送信結果：<strong><?php echo esc_html($tn); ?></strong></p></div>
        <?php endif; endif; ?>
        <?php if (!$engine_ok): ?>
            <div class="notice notice-error"><p><strong>注意：</strong>既存プラグイン（CARMEL統合管理 v5.7）が無効か、関数が見つかりません。先に有効化してください。これが無いと自動生成は動きません。</p></div>
        <?php endif; ?>

        <?php carmel3_render_progress_panel(); ?>

        <div style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:16px">
            <p style="margin:0 0 6px"><strong>ステータス</strong></p>
            <p style="margin:0">自動生成: <strong><?php echo !empty($s['enabled']) ? 'ON' : 'OFF'; ?></strong>
               ／ 画像: <strong><?php echo !empty($s['gen_images']) ? 'ON' : 'OFF'; ?></strong>
               ／ Google投稿: <strong><?php echo !empty($s['gmb_post']) ? 'ON' : 'OFF'; ?></strong>
               ／ エンジン: <strong><?php echo $engine_ok ? '接続OK' : '未接続'; ?></strong>
               ／ 次回実行: <strong><?php echo $next ? esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $next), 'Y-m-d H:i')) : '未スケジュール'; ?></strong></p>
            <?php
            $done_count = isset($s['done']) && is_array($s['done']) ? count($s['done']) : 0;
            $remaining_count = carmel3_auto_remaining_count($s);
            ?>
            <p style="margin:6px 0 0"><strong>進捗：</strong>生成済み <strong><?php echo (int)$done_count; ?></strong> 件 ／ 残り <strong><?php echo (int)$remaining_count; ?></strong> 件（同じテーマは二度作りません）</p>
            <?php
            $auto_extra = (isset($s['extra_types']) && is_array($s['extra_types'])) ? $s['extra_types'] : array();
            $auto_media_labels = array('メディア記事（常に作成）');
            foreach ($auto_extra as $ept) {
                $eo = get_post_type_object($ept);
                $auto_media_labels[] = ($eo && !empty($eo->labels->singular_name)) ? $eo->labels->singular_name : $ept;
            }
            ?>
            <p style="margin:6px 0 0"><strong>自動化する媒体：</strong><?php echo esc_html(implode(' ／ ', $auto_media_labels)); ?>
                <a href="#media" style="margin-left:6px;font-size:12px">▼ 媒体を追加・変更</a></p>
            <p style="margin:6px 0 0;color:#555">最終実行: <?php echo esc_html($s['last_run'] ?: '—'); ?> ／ <?php echo esc_html($s['last_msg'] ?: '—'); ?></p>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:16px">
            <input type="hidden" name="action" value="carmel3_auto_save">
            <?php wp_nonce_field('carmel3_auto_save'); ?>

            <p><label><input type="checkbox" name="enabled" value="1" <?php checked(!empty($s['enabled'])); ?>> 自動生成を有効にする（定期実行）</label></p>

            <p style="display:flex;flex-wrap:wrap;gap:18px;align-items:center">
                <label>頻度：
                    <select name="frequency">
                        <option value="daily" <?php selected($s['frequency'], 'daily'); ?>>毎日</option>
                        <option value="carmel_twiceweekly" <?php selected($s['frequency'], 'carmel_twiceweekly'); ?>>週2回</option>
                        <option value="carmel_weekly" <?php selected($s['frequency'], 'carmel_weekly'); ?>>毎週</option>
                    </select>
                </label>
                <label><strong>投稿時間（時刻）：</strong>
                    <input type="time" name="run_time" value="<?php echo esc_attr(isset($s['run_time']) ? $s['run_time'] : '09:00'); ?>" style="padding:4px 6px">
                </label>
            </p>
            <p style="margin:-6px 0 8px;color:#666;font-size:12px">
                指定した時刻に毎日（または週2回・毎週）自動生成します。タイムゾーンは <strong><?php echo esc_html(wp_timezone_string()); ?></strong>。
                現在時刻 <strong><?php echo esc_html(date_i18n('Y-m-d H:i')); ?></strong>。
                <?php $next_t = wp_next_scheduled(CARMEL3_AUTO_HOOK); ?>
                次回実行予定：<strong><?php echo $next_t ? esc_html(get_date_from_gmt(gmdate('Y-m-d H:i:s', $next_t), 'Y-m-d H:i')) : '未スケジュール（有効にして保存）'; ?></strong>
                <br>※ WP-Cronはサイトにアクセスがあった時に動くため、指定時刻ちょうどから数分〜遅れる場合があります（確実にするにはサーバーの本物cron推奨）。
            </p>

            <div style="border:1px solid #fed7aa;background:#fff7ed;border-radius:10px;padding:12px 14px;margin:6px 0 14px">
                <p style="margin:0 0 6px;font-weight:700;color:#9a3412">AIの最大トークン数（残高不足エラーを防ぐ）</p>
                <label>1回の生成で使う上限：
                    <input type="number" name="max_tokens_cap" value="<?php echo esc_attr(isset($s['max_tokens_cap']) ? (int)$s['max_tokens_cap'] : 12000); ?>" min="0" max="65536" step="1000" style="width:120px"> トークン
                </label>
                <p style="margin:6px 0 0;color:#7c2d12;font-size:12px">
                    本体が大きすぎる値（例:65536）を要求して「残高不足」で失敗するのを防ぎます。<strong>クレジットを足さずに今の残高で通せます</strong>。
                    記事1本には <strong>12000</strong> で十分（足りなければ16000程度に。<strong>0</strong>で制限なし）。値を小さくするほど安く・速くなります。
                </p>
                <p style="margin:10px 0 0;color:#7c2d12;font-size:12px">
                    それでも「You requested up to 65536 tokens」が消えない場合 → 本体側に保存された大きな値が原因です。下のボタンで自動修正できます（本体のコードは変更しません）。
                </p>
                <p style="margin:8px 0 0">
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=carmel3_fix_tokens'), 'carmel3_fix_tokens')); ?>" class="button button-secondary">残高不足エラーを自動修正（本体の最大トークンを下げる）</a>
                </p>
                <hr style="margin:12px 0;border:none;border-top:1px dashed #fed7aa">
                <p style="margin:0 0 4px;font-weight:700;color:#9a3412">文章モデルの上書き（残高がほぼ無い時は無料モデルに）</p>
                <label style="font-size:13px">モデルID（空欄＝本体まかせ）：
                    <input type="text" name="text_model" value="<?php echo esc_attr(isset($s['text_model']) ? $s['text_model'] : ''); ?>" style="width:340px" placeholder="例: deepseek/deepseek-chat-v3-0324:free">
                </label>
                <p style="margin:6px 0 0;color:#7c2d12;font-size:12px">
                    残高がほぼ0でも、<strong>料金$0の無料モデル</strong>を指定すれば生成できます（文章のみ。画像は別）。おすすめ：<br>
                    <code>deepseek/deepseek-chat-v3-0324:free</code> ／ <code>google/gemini-2.0-flash-exp:free</code> ／ <code>meta-llama/llama-3.3-70b-instruct:free</code><br>
                    ※ 無料モデルの利用には <a href="https://openrouter.ai/settings/privacy" target="_blank" rel="noopener">OpenRouterのプライバシー設定</a> で許可が必要な場合があります。空欄に戻すと元のモデルに戻ります。
                </p>
            </div>

            <p><label>
                <input type="checkbox" name="publish" value="1" <?php checked(!empty($s['publish'])); ?>>
                生成後すぐ公開する（チェックなしは「下書き」で保存・推奨）
            </label></p>

            <div id="media" style="border:2px solid #0f766e;border-radius:12px;padding:14px 16px;margin:16px 0;background:#f0fdfa;scroll-margin-top:40px">
                <p style="margin:0 0 4px;font-size:15px;font-weight:800;color:#0f766e">★ 自動化する媒体（ニュース・加盟店ブログもここで一緒に自動化）</p>
                <p style="margin:0 0 8px;color:#444;font-size:13px"><strong>メディア記事は常に自動生成</strong>します。さらに毎日いっしょに作りたい媒体に<strong>チェック</strong>を入れてください。チェックした媒体は、メディア記事と<strong>同じテーマ</strong>でAIが書き分けて自動生成・自動投稿します。</p>
                <p style="margin:0 0 10px;padding:8px 10px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;color:#9a3412;font-size:13px;font-weight:700">まずは「ニュース」と「加盟店ブログ」の2つだけチェックすればOK。書き方欄は空でも作れます（こだわりがあれば書いてください）。残りの項目は触らなくて大丈夫です。</p>
                <?php
                $pts = carmel3_selectable_post_types();
                $sel_types  = (isset($s['extra_types']) && is_array($s['extra_types'])) ? $s['extra_types'] : array();
                $type_prompts = (isset($s['type_prompts']) && is_array($s['type_prompts'])) ? $s['type_prompts'] : array();
                // ニュース・加盟店ブログを先頭に並べる
                $pref = array('news', 'shop_blog', 'post');
                uksort($pts, function ($a, $b) use ($pref) {
                    $ia = array_search($a, $pref, true); $ib = array_search($b, $pref, true);
                    $ia = ($ia === false) ? 99 : $ia; $ib = ($ib === false) ? 99 : $ib;
                    return $ia <=> $ib;
                });
                if (empty($pts)): ?>
                    <p style="color:#c2410c;font-size:12px">選べる投稿タイプが見つかりません。</p>
                <?php else: ?>
                    <p style="color:#666;font-size:12px;margin:0 0 8px">各媒体の<strong>「書き方（AI指示）」</strong>欄に、文字数・トーン・構成などを書くと、その通りに書き分けます（空欄なら標準）。</p>
                    <div style="display:flex;flex-direction:column;gap:10px">
                    <?php foreach ($pts as $pt => $label):
                        $cur_prompt = isset($type_prompts[$pt]) ? $type_prompts[$pt] : '';
                        $is_pref = in_array($pt, array('news', 'shop_blog', 'post'), true);
                        $ph = '例: ' . (($pt === 'post' || $pt === 'news')
                            ? 'お知らせ/NEWS。300〜500字、告知調、結論を先に、最後に来店・問い合わせ導線。'
                            : '加盟店(FC)オーナー向け。1000〜1300字、ビジネス視点で収益・運営メリットを具体的に。');
                    ?>
                        <div style="border:1px solid <?php echo $is_pref ? '#0f766e' : '#e5e7eb'; ?>;border-radius:10px;padding:10px 12px;background:#fff">
                            <label style="font-weight:700"><input type="checkbox" name="extra_types[]" value="<?php echo esc_attr($pt); ?>" <?php checked(in_array($pt, $sel_types, true)); ?>> <?php echo esc_html($label); ?> <span style="color:#999;font-size:11px;font-weight:400">(<?php echo esc_html($pt); ?>)</span><?php if ($is_pref): ?> <span style="background:#0f766e;color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:4px;margin-left:4px">おすすめ</span><?php endif; ?></label>
                            <textarea name="type_prompt[<?php echo esc_attr($pt); ?>]" placeholder="<?php echo esc_attr($ph); ?>" style="width:100%;min-height:64px;margin-top:8px;border:1px solid #d1d5db;border-radius:8px;padding:8px;font-size:13px"><?php echo esc_textarea($cur_prompt); ?></textarea>
                        </div>
                    <?php endforeach; ?>
                    </div>
                    <p style="color:#666;font-size:12px;margin:8px 0 0">※ チェックした媒体の数だけAIを使います（その分の料金がかかります）。</p>
                <?php endif; ?>
            </div>

            <hr style="margin:16px 0;border:none;border-top:1px solid #eee">

            <p><label>
                <input type="checkbox" name="gen_images" value="1" <?php checked(!empty($s['gen_images'])); ?>>
                <strong>画像も自動生成する（アイキャッチ＋トップバナーの2枚）</strong>
            </label><br>
            <span style="color:#666;font-size:12px">ONにすると、<strong>ニュース・加盟店ブログにもアイキャッチ画像</strong>を1枚ずつ自動で付けます（写真は<strong>日本人のみ</strong>・外国人は登場しません）。</span></p>

            <div style="border:1px solid #cfe9d6;background:#f0fdf4;border-radius:10px;padding:12px 14px;margin:6px 0 12px">
                <p style="margin:0"><label>
                    <input type="checkbox" name="eyecatch_copy" value="1" <?php checked(!empty($s['eyecatch_copy'])); ?>>
                    <strong>アイキャッチに訴求コピーを入れる</strong>
                </label></p>
                <p style="margin:8px 0 0">訴求コピー（<code>｜</code>で改行・最大2行）：
                    <input type="text" name="eyecatch_copy_text" value="<?php echo esc_attr(isset($s['eyecatch_copy_text']) ? $s['eyecatch_copy_text'] : ''); ?>" style="width:360px" placeholder="例: 自社ローンOK｜全国対応">
                </p>
                <p style="margin:6px 0 0;color:#555;font-size:12px">画像の下に帯＋日本語コピーを<strong>サーバー側でくっきり描画</strong>します（文字化けしません）。<br>
                <?php if (function_exists('imagettftext')): ?>
                    <span style="color:#16a34a">この環境は対応OK（GD/FreeType利用可）。</span>
                <?php else: ?>
                    <span style="color:#c2410c">※ この環境はサーバーのGD/FreeTypeが無いため描画できません。サーバー会社にGD(freetype)有効化を依頼してください。</span>
                <?php endif; ?></p>
            </div>

            <p><label>画像モデル（OpenRouter）：
                <input type="text" name="image_model" value="<?php echo esc_attr($s['image_model']); ?>" style="width:340px" placeholder="<?php echo esc_attr(CARMEL3_IMG_DEFAULT_MODEL); ?>">
            </label><br>
            <span style="color:#666;font-size:12px">画像対応モデルを指定。例: <code><?php echo esc_html(CARMEL3_IMG_DEFAULT_MODEL); ?></code></span></p>

            <p style="margin-top:12px"><strong>画像サイズ（生成後に必ずこの寸法へトリミングします）</strong></p>
            <p style="margin:6px 0">
                アイキャッチ：
                幅 <input type="number" name="eyecatch_w" value="<?php echo esc_attr($s['eyecatch_w']); ?>" style="width:90px" min="200"> ×
                高さ <input type="number" name="eyecatch_h" value="<?php echo esc_attr($s['eyecatch_h']); ?>" style="width:90px" min="200"> px
                <span style="color:#666;font-size:12px">（推奨 1200×630）</span>
            </p>
            <p style="margin:6px 0">
                トップバナー：
                幅 <input type="number" name="banner_w" value="<?php echo esc_attr($s['banner_w']); ?>" style="width:90px" min="200"> ×
                高さ <input type="number" name="banner_h" value="<?php echo esc_attr($s['banner_h']); ?>" style="width:90px" min="150"> px
                <span style="color:#666;font-size:12px">（推奨 1200×400）</span>
            </p>

            <p><label>トップバナーの保存先（ACFフィールド名）：
                <input type="text" name="banner_field" value="<?php echo esc_attr($s['banner_field']); ?>" style="width:220px" placeholder="hero_image">
            </label>
            <?php if (!$acf_ok): ?>
                <br><span style="color:#c2410c;font-size:12px">※ ACF(update_field)が見つかりません。バナーは同名のpost_metaに保存されます。</span>
            <?php endif; ?>
            </p>

            <p style="margin-top:12px"><label>
                <input type="checkbox" name="gen_section_images" value="1" <?php checked(!empty($s['gen_section_images'])); ?>>
                <strong>本文の各セクション画像も生成する（section_1 / 2 / 3）</strong>
            </label><br>
            <span style="color:#666;font-size:12px">タイムアウトを避けるため、記事生成のあと<strong>裏側で1枚ずつ順番に</strong>作ります。中身（見出し）のあるセクションだけ、画像が空のときに生成します。</span></p>

            <p style="margin:6px 0">
                セクション画像サイズ：
                幅 <input type="number" name="sec_w" value="<?php echo esc_attr($s['sec_w']); ?>" style="width:90px" min="200"> ×
                高さ <input type="number" name="sec_h" value="<?php echo esc_attr($s['sec_h']); ?>" style="width:90px" min="200"> px
                <span style="color:#666;font-size:12px">（推奨 1200×675）</span>
            </p>

            <hr style="margin:16px 0;border:none;border-top:1px solid #eee">

            <p><label>
                <input type="checkbox" name="auto_slug_meta" value="1" <?php checked(!empty($s['auto_slug_meta'])); ?>>
                <strong>スラッグ（英語URL）とメタディスクリプションを自動で作る（SEO対策）</strong>
            </label><br>
            <span style="color:#666;font-size:12px">日本語タイトルを<strong>英語のURLスラッグ</strong>に自動変換し、<strong>検索結果に出る説明文（メタディスクリプション）</strong>もAIが作成します。Yoast / Rank Math / All in One SEO のどれでも反映されるよう書き込みます。ニュース・加盟店ブログ・メディア記事すべてに適用。</span></p>

            <hr style="margin:16px 0;border:none;border-top:1px solid #eee">

            <p><label>
                <input type="checkbox" name="fix_cta" value="1" <?php checked(!empty($s['fix_cta'])); ?>>
                <strong>CTAボタンのURLが壊れていたら自動で直す</strong>
            </label><br>
            <span style="color:#666;font-size:12px">main_cta_url / cta_button_url が空・空白混入・<code>http://.</code> 等のときに、下の連絡先URLへ置き換えます。</span></p>

            <p><label>連絡先URL（CTAの修正先・空なら <code><?php echo esc_html(home_url('/contact/')); ?></code>）：
                <input type="text" name="contact_url" value="<?php echo esc_attr($s['contact_url']); ?>" style="width:340px" placeholder="<?php echo esc_attr(home_url('/contact/')); ?>">
            </label></p>

            <hr style="margin:16px 0;border:none;border-top:1px solid #eee">

            <?php $gmb_fn_ok = function_exists('carmel_post_to_google'); ?>
            <p><label>
                <input type="checkbox" name="gmb_post" value="1" <?php checked(!empty($s['gmb_post'])); ?> <?php echo $gmb_fn_ok ? '' : 'disabled'; ?>>
                <strong>Googleマイビジネスに自動投稿する</strong>
            </label><br>
            <?php if ($gmb_fn_ok): ?>
                <span style="color:#666;font-size:12px">記事生成後に、本体v5.7のGoogle投稿機能で自動投稿します。投稿文はSNS下書きがあればそれを、無ければタイトル＋リード文を使います。アイキャッチ画像を添付します。<br>
                ※ 事前に「カーメル管理 設定 SNS認証情報」でGoogleのアクセストークン・アカウントID・ロケーションIDを登録しておく必要があります。<br>
                ※ 「生成後すぐ公開」をONにしておくと、記事URLも投稿文に付きます（下書きのままだとURLは付きません）。</span>
            <?php else: ?>
                <span style="color:#c2410c;font-size:12px">本体v5.7のGoogle投稿関数が見つからないため、この機能は使えません。CARMEL統合管理 v5.7が有効か確認してください。</span>
            <?php endif; ?>
            </p>

            <hr style="margin:16px 0;border:none;border-top:1px solid #eee">

            <p style="margin:0 0 6px"><strong>通知（Slack ／ LINE）</strong><br>
            <span style="color:#666;font-size:12px">記事ができたら通知します。LINEはテストアカウント宛に「アイキャッチ＋タイトル＋抜粋＋リンク」を送ります（公開前レビュー用）。</span></p>

            <p><label>通知のタイミング：
                <select name="notify_on">
                    <option value="draft" <?php selected($s['notify_on'], 'draft'); ?>>下書きができた時（公開前レビュー・推奨）</option>
                    <option value="publish" <?php selected($s['notify_on'], 'publish'); ?>>公開した時だけ</option>
                </select>
            </label></p>

            <p style="margin:10px 0 4px"><label>
                <input type="checkbox" name="slack_enabled" value="1" <?php checked(!empty($s['slack_enabled'])); ?>>
                <strong>Slackに通知する</strong>
            </label></p>
            <p style="margin:0"><label>Slack Webhook URL：
                <input type="text" name="slack_webhook" value="<?php echo esc_attr($s['slack_webhook']); ?>" style="width:480px" placeholder="https://hooks.slack.com/services/XXXX/XXXX/XXXX">
            </label></p>

            <p style="margin:14px 0 4px"><label>
                <input type="checkbox" name="line_enabled" value="1" <?php checked(!empty($s['line_enabled'])); ?>>
                <strong>LINEに通知する（テストアカウント宛）</strong>
            </label></p>
            <p style="margin:0">LINEチャネルアクセストークン：
                <input type="text" name="line_token" value="<?php echo esc_attr($s['line_token']); ?>" style="width:480px" placeholder="Messaging APIのチャネルアクセストークン" autocomplete="off">
            </p>
            <p style="margin:6px 0 0">送信先ID（テストアカウントのuserId／groupId）：
                <input type="text" name="line_to" value="<?php echo esc_attr($s['line_to']); ?>" style="width:300px" placeholder="Uxxxxxxxx... または Cxxxxxxxx...">
            </p>
            <p style="color:#666;font-size:12px;margin:6px 0 0">※ LINEは「Messaging API」のチャネルを作り、トークンと、テスト用LINEのuserIdを入れてください。設定を<strong>保存してから</strong>、下の「テスト送信」で確認できます。</p>

            <hr style="margin:16px 0;border:none;border-top:1px solid #eee">

            <p><strong>テーマキュー</strong>（1行1テーマ・<code>|</code> 区切り）<br>
               <code>account | category | keyword | prefecture | city | title</code><br>
               <span style="color:#666;font-size:12px">account は <code>main</code> または <code>fc</code>。title は省略可（空ならkeywordを種にモデルが生成）。</span>
            </p>
            <textarea name="queue" style="width:100%;min-height:180px;font-family:monospace;font-size:13px;border:1px solid #d1d5db;border-radius:10px;padding:10px"><?php
                echo esc_textarea(carmel3_auto_queue_to_text($s['queue']));
            ?></textarea>

            <?php if (!empty($categories)): ?>
                <p style="margin-top:10px;color:#555;font-size:12px"><strong>利用可能な category キー：</strong><br>
                    <?php
                    $parts = array();
                    foreach ($categories as $k => $c) {
                        $parts[] = esc_html($k) . '（' . esc_html($c['label']) . '/' . esc_html($c['account']) . '）';
                    }
                    echo implode('　', $parts);
                    ?>
                </p>
            <?php endif; ?>

            <p style="margin-top:16px"><button type="submit" class="button button-primary">設定を保存</button></p>
        </form>

        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0">
                <input type="hidden" name="action" value="carmel3_auto_run_now">
                <?php wp_nonce_field('carmel3_auto_run_now'); ?>
                <button type="submit" class="button button-primary">今すぐ1件だけ生成（テスト・画像も）</button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0"
                  onsubmit="return confirm('生成済みの記録を消して、テーマキューを最初からもう一度生成できるようにします。よろしいですか？（作成済みの記事は消えません）');">
                <input type="hidden" name="action" value="carmel3_auto_reset_done">
                <?php wp_nonce_field('carmel3_auto_reset_done'); ?>
                <button type="submit" class="button">生成済み記録をリセット（最初から作り直す）</button>
            </form>

            <?php
            // 本文画像が「裏側待ち」になっている最新の記事を探す
            $pending = get_posts(array(
                'post_type'      => 'media_article',
                'post_status'    => 'any',
                'posts_per_page' => 1,
                'orderby'        => 'modified',
                'order'          => 'DESC',
                'fields'         => 'ids',
                'meta_key'       => '_carmel_pending_img_jobs',
            ));
            $pending_post_id = !empty($pending) ? (int) $pending[0] : 0;
            $pending_count = 0;
            if ($pending_post_id) {
                $pj = get_post_meta($pending_post_id, '_carmel_pending_img_jobs', true);
                $pending_count = is_array($pj) ? count($pj) : 0;
            }
            ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0">
                <input type="hidden" name="action" value="carmel3_auto_process_image">
                <input type="hidden" name="post_id" value="<?php echo esc_attr($pending_post_id); ?>">
                <?php wp_nonce_field('carmel3_auto_process_image'); ?>
                <button type="submit" class="button" <?php echo $pending_count > 0 ? '' : 'disabled'; ?>>本文画像を今すぐ1枚進める<?php echo $pending_count > 0 ? '（残り' . (int)$pending_count . '枚 / 記事#' . (int)$pending_post_id . '）' : '（待ち無し）'; ?></button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0"
                  onsubmit="return confirm('既存のメディア記事の中で、CTAボタン欄(main_cta_url / cta_button_url)のURLが壊れているものを探して、連絡先URLに一括修正します。よろしいですか？（正常なURL・記事本文は変更しません）');">
                <input type="hidden" name="action" value="carmel3_auto_fix_cta_all">
                <?php wp_nonce_field('carmel3_auto_fix_cta_all'); ?>
                <button type="submit" class="button">記事のCTAを一括チェック＆修正</button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0">
                <input type="hidden" name="action" value="carmel3_test_notify">
                <input type="hidden" name="which" value="slack">
                <?php wp_nonce_field('carmel3_test_notify'); ?>
                <button type="submit" class="button">Slackテスト送信</button>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0">
                <input type="hidden" name="action" value="carmel3_test_notify">
                <input type="hidden" name="which" value="line">
                <?php wp_nonce_field('carmel3_test_notify'); ?>
                <button type="submit" class="button">LINEテスト送信</button>
            </form>
        </div>
        <p style="color:#666;font-size:12px;margin-top:8px">※ テスト送信は<strong>保存後</strong>の設定で送ります。先に「設定を保存」してから押してください。</p>

        <p style="color:#666;font-size:12px;margin-top:18px">
            ※ 「今すぐ1件だけ生成」は WP-Cron を介さずその場で実行します。まずこれで動作確認してください。<br>
            ※ 定期実行（自動生成ON）は WP-Cron で動きます。アクセスが少ないサイトでは、サーバーの本物の cron で
            <code><?php echo esc_html(site_url('/wp-cron.php?doing_wp_cron')); ?></code> を定期実行すると確実です。
        </p>
    </div>
    <?php
}
