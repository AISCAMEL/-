<?php
/**
 * Plugin Name: CARMEL 自動生成（毎日自動）
 * Description: 本体「CARMEL統合管理 v5.7」を使って記事を自動生成・自動投稿するアドオン（WP-Cron）。カーメル管理メニューの中に表示。
 * Version: 6.3
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
        'publish'      => 0,
        'gen_images'   => 0,
        'gen_section_images' => 1,
        'fix_cta'      => 1,
        'contact_url'  => '',
        'image_model'  => CARMEL3_IMG_DEFAULT_MODEL,
        'banner_field' => 'hero_image',
        'gmb_post'     => 0,
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
// 本文セクション画像を裏側で1枚ずつ処理するワーカー
add_action(CARMEL3_AUTO_IMGHOOK, 'carmel3_auto_process_one_image');

function carmel3_auto_reschedule() {
    $s = carmel3_auto_get_settings();
    $existing = wp_next_scheduled(CARMEL3_AUTO_HOOK);
    if ($existing) {
        wp_unschedule_event($existing, CARMEL3_AUTO_HOOK);
    }
    if (!empty($s['enabled'])) {
        $recur = in_array($s['frequency'], array('daily', 'carmel_weekly', 'carmel_twiceweekly'), true)
            ? $s['frequency'] : 'carmel_weekly';
        wp_schedule_event(time() + 60, $recur, CARMEL3_AUTO_HOOK);
    }
}

add_action('init', function () {
    $s = carmel3_auto_get_settings();
    if (!empty($s['enabled']) && !wp_next_scheduled(CARMEL3_AUTO_HOOK)) {
        carmel3_auto_reschedule();
    }
});

/* ===== 自動生成の本体 ===== */

function carmel3_auto_run() {
    @set_time_limit(300);
    $s = carmel3_auto_get_settings();

    if (!function_exists('carmel_generate_article_api')) {
        carmel3_auto_log($s, '失敗: 既存プラグイン(carmel_generate_article_api)が見つかりません。CARMEL統合管理v5.7が有効か確認してください。');
        return false;
    }
    $queue = array_values((array)$s['queue']);
    if (empty($queue)) {
        carmel3_auto_log($s, '失敗: テーマキューが空です');
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
        return false;
    }

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
        return false;
    }

    $post_id = intval($data['post_id']);

    $done[] = $picked_key;
    $s['done'] = $done;

    // CTAボタンのURLが壊れていれば /contact/ に自動修正
    $cta_msg = '';
    if (!empty($s['fix_cta']) && $post_id) {
        $cta_n = carmel3_auto_fix_cta($post_id, $s);
        $cta_msg = ' | CTA: ' . ($cta_n > 0 ? "{$cta_n}箇所修正" : '修正不要');
    }

    $img_msg = '';
    if (!empty($s['gen_images']) && $post_id) {
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

    if (function_exists('carmel_history_add')) {
        carmel_history_add('auto_generate', $title, $post_id, array(
            'account'  => $item['account'],
            'category' => $item['category'],
        ));
    }

    $remaining = carmel3_auto_remaining_count($s);
    $status_label = !empty($s['publish']) ? '公開' : '下書き';
    carmel3_auto_log($s, "成功: {$title}（{$status_label}） post#{$post_id}{$cta_msg}{$img_msg}{$gmb_msg} | 残り{$remaining}件");
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

function carmel3_img_attach_to_post($post_id) {
    $s = carmel3_auto_get_settings();
    $model        = $s['image_model'] !== '' ? $s['image_model'] : CARMEL3_IMG_DEFAULT_MODEL;
    $banner_field = $s['banner_field'] !== '' ? $s['banner_field'] : 'hero_image';

    $ew = !empty($s['eyecatch_w']) ? (int)$s['eyecatch_w'] : 1200;
    $eh = !empty($s['eyecatch_h']) ? (int)$s['eyecatch_h'] : 630;
    $bw = !empty($s['banner_w'])   ? (int)$s['banner_w']   : 1200;
    $bh = !empty($s['banner_h'])   ? (int)$s['banner_h']   : 400;

    $base_prompt = (string) get_post_meta($post_id, '_carmel_main_image_prompt', true);
    if ($base_prompt === '') {
        $base_prompt = 'Professional Japanese automotive finance article visual, '
            . get_the_title($post_id) . ', clean, trustworthy, high quality';
    }

    $out = array();

    $p1  = $base_prompt . ' , 16:9 horizontal composition, high quality, photographic, no text, no watermark';
    $img = carmel3_img_generate_openrouter($p1, $model);
    if (is_wp_error($img)) {
        $out[] = 'アイキャッチ失敗(' . $img->get_error_message() . ')';
    } else {
        $a = carmel3_img_sideload($post_id, $img['bytes'], $img['mime'], 'eyecatch-' . $post_id, $ew, $eh);
        if (is_wp_error($a)) {
            $out[] = 'アイキャッチ保存失敗(' . $a->get_error_message() . ')';
        } else {
            set_post_thumbnail($post_id, $a);
            $out[] = "アイキャッチOK#{$a}({$ew}x{$eh})";
        }
    }

    $p2   = $base_prompt . ' , ultra-wide website hero banner, generous empty space on one side for headline text, no text, no watermark';
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

    $base_prompt = (string) get_post_meta($post_id, '_carmel_main_image_prompt', true);
    if ($base_prompt === '') {
        $base_prompt = 'Professional Japanese automotive finance article visual, '
            . get_the_title($post_id) . ', clean, trustworthy, high quality';
    }

    $fields = array(
        1 => array(CARMEL3_F_SEC1_IMG, 'section_1_image'),
        2 => array(CARMEL3_F_SEC2_IMG, 'section_2_image'),
        3 => array(CARMEL3_F_SEC3_IMG, 'section_3_image'),
    );

    $jobs = array();
    foreach ($fields as $n => $f) {
        $existing = carmel3_auto_get_acf_value($post_id, $f[0], $f[1]);
        if (trim($existing) !== '' && $existing !== '0') continue; // すでに画像あり飛ばす
        $heading = carmel3_auto_section_has_content($post_id, $n);
        if ($heading === '') continue; // 中身の無いセクションは作らない
        $prompt = $base_prompt . ' , section illustration about: ' . $heading
            . ' , 16:9 horizontal composition, photographic, no text, no watermark';
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

/* ===== 管理画面メニュー（確実に出すため admin_menu 優先度を明示） ===== */

add_action('admin_menu', 'carmel3_auto_register_menu', 99);

function carmel3_auto_register_menu() {
    global $admin_page_hooks;
    $parent = 'carmel-manager';
    if (isset($admin_page_hooks[$parent])) {
        // 「カーメル管理」の中にサブメニューとして入れる
        add_submenu_page($parent, 'かんたんホーム', 'かんたんホーム', 'manage_options', 'carmel3-home', 'carmel3_home_page');
        add_submenu_page($parent, 'CARMEL 自動生成', '自動生成', 'manage_options', 'carmel3-auto', 'carmel3_auto_settings_page');
    } else {
        // 親が見つからない場合は従来どおりトップに出す（消えない保険）
        add_menu_page('かんたんホーム', 'かんたんホーム', 'manage_options', 'carmel3-home', 'carmel3_home_page', 'dashicons-admin-home', 3);
        add_menu_page('CARMEL 自動生成', 'CARMEL自動生成', 'manage_options', 'carmel3-auto', 'carmel3_auto_settings_page', 'dashicons-update', 4);
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
                <a href="<?php echo esc_url($url_set); ?>" style="text-decoration:none;display:block;background:#374151;color:#fff;border-radius:12px;padding:16px">
                    <div style="font-size:18px;font-weight:800">設定（APIキー）</div>
                    <div style="font-size:12px;opacity:.9;margin-top:4px">OpenRouterキー・ブランド設定</div>
                </a>
            </div>
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

add_action('admin_post_carmel3_auto_save', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_auto_save');

    $s = carmel3_auto_get_settings();
    $s['enabled']    = isset($_POST['enabled']) ? 1 : 0;
    $s['publish']    = isset($_POST['publish']) ? 1 : 0;
    $s['gen_images'] = isset($_POST['gen_images']) ? 1 : 0;
    $s['gen_section_images'] = isset($_POST['gen_section_images']) ? 1 : 0;
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

    $im = isset($_POST['image_model']) ? sanitize_text_field(wp_unslash($_POST['image_model'])) : '';
    $s['image_model'] = $im !== '' ? $im : CARMEL3_IMG_DEFAULT_MODEL;

    $bf = isset($_POST['banner_field']) ? sanitize_key($_POST['banner_field']) : '';
    $s['banner_field'] = $bf !== '' ? $bf : 'hero_image';

    $s['queue'] = carmel3_auto_parse_queue(isset($_POST['queue']) ? wp_unslash($_POST['queue']) : '');

    carmel3_auto_save_settings($s);
    carmel3_auto_reschedule();

    wp_safe_redirect(admin_url('admin.php?page=carmel3-auto&saved=1'));
    exit;
});

add_action('admin_post_carmel3_auto_run_now', function () {
    if (!current_user_can('manage_options')) wp_die('権限がありません');
    check_admin_referer('carmel3_auto_run_now');

    carmel3_auto_run();

    wp_safe_redirect(admin_url('admin.php?page=carmel3-auto&ran=1'));
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
        <?php if (!$engine_ok): ?>
            <div class="notice notice-error"><p><strong>注意：</strong>既存プラグイン（CARMEL統合管理 v5.7）が無効か、関数が見つかりません。先に有効化してください。これが無いと自動生成は動きません。</p></div>
        <?php endif; ?>

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
            <p style="margin:6px 0 0;color:#555">最終実行: <?php echo esc_html($s['last_run'] ?: '—'); ?> ／ <?php echo esc_html($s['last_msg'] ?: '—'); ?></p>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:16px">
            <input type="hidden" name="action" value="carmel3_auto_save">
            <?php wp_nonce_field('carmel3_auto_save'); ?>

            <p><label><input type="checkbox" name="enabled" value="1" <?php checked(!empty($s['enabled'])); ?>> 自動生成を有効にする（定期実行）</label></p>

            <p><label>頻度：
                <select name="frequency">
                    <option value="daily" <?php selected($s['frequency'], 'daily'); ?>>毎日</option>
                    <option value="carmel_twiceweekly" <?php selected($s['frequency'], 'carmel_twiceweekly'); ?>>週2回</option>
                    <option value="carmel_weekly" <?php selected($s['frequency'], 'carmel_weekly'); ?>>毎週</option>
                </select>
            </label></p>

            <p><label>
                <input type="checkbox" name="publish" value="1" <?php checked(!empty($s['publish'])); ?>>
                生成後すぐ公開する（チェックなしは「下書き」で保存・推奨）
            </label></p>

            <hr style="margin:16px 0;border:none;border-top:1px solid #eee">

            <p><label>
                <input type="checkbox" name="gen_images" value="1" <?php checked(!empty($s['gen_images'])); ?>>
                <strong>画像も自動生成する（アイキャッチ＋トップバナーの2枚）</strong>
            </label></p>

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
        </div>

        <p style="color:#666;font-size:12px;margin-top:18px">
            ※ 「今すぐ1件だけ生成」は WP-Cron を介さずその場で実行します。まずこれで動作確認してください。<br>
            ※ 定期実行（自動生成ON）は WP-Cron で動きます。アクセスが少ないサイトでは、サーバーの本物の cron で
            <code><?php echo esc_html(site_url('/wp-cron.php?doing_wp_cron')); ?></code> を定期実行すると確実です。
        </p>
    </div>
    <?php
}
