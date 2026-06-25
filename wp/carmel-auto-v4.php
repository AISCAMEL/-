<?php
/**
 * CARMEL自動生成 アドオン v4（WPCodeスニペット / Run Everywhere / PHP Snippet）
 * ──────────────────────────────────────────────────────────────
 * ※ 貼り付け時は先頭の「<?php」を除く（WPCode仕様）。末尾に「?>」は付けない。
 * ※ 本体プラグイン「CARMEL統合管理 v5.7」の関数は呼ぶだけ。再定義しない。
 *
 * v4 追加点（v3からの差分）:
 *   - 各セクション画像(section_1〜3_image)＋メイン画像(hero_image)を
 *     「裏側で1枚ずつ」生成してACFフィールドへ自動セット（時間切れ回避）。
 *   - 壊れたCTA URL（http://. home_url('/contact/') . など）を自動修正。
 *   - 「画像を今すぐ1枚進める」テストボタン（WP-Cron不発の環境でも目視可）。
 *   - v3機能（タイムアウト180秒＋リトライ・画像サイズ統一・下書き既定・
 *     アイキャッチ/バナー・Google投稿）は全て継続。
 *
 * ※ ACFフィールドキーはカーメルの「量産記事テンプレート」(group_69feeee3aa314)に準拠。
 */

if (!defined('ABSPATH')) { return; }

/* 重複スニペットでの再定義・二重起動を防ぐ安全装置 */
if (function_exists('carmel_auto_do_one')) { return; }

if (!defined('CARMEL_AUTO_HOOK'))   { define('CARMEL_AUTO_HOOK',   'carmel_auto_generate_cron'); }
if (!defined('CARMEL_AUTO_IMGHOOK')){ define('CARMEL_AUTO_IMGHOOK','carmel_auto_img_worker'); }
if (!defined('CARMEL_AUTO_OPT'))    { define('CARMEL_AUTO_OPT',    'carmel_auto_settings'); }
if (!defined('CARMEL_AUTO_DONE'))   { define('CARMEL_AUTO_DONE',   'carmel_auto_done_keys'); }
if (!defined('CARMEL_AUTO_ACTIVE')) { define('CARMEL_AUTO_ACTIVE', 'carmel_auto_active_post'); }

/* ACFフィールド（カーメルの量産記事テンプレートに対応） */
if (!defined('CARMEL_F_SEC1_IMG')) { define('CARMEL_F_SEC1_IMG', 'field_69ffb5a4d372b'); }
if (!defined('CARMEL_F_SEC2_IMG')) { define('CARMEL_F_SEC2_IMG', 'field_69ffb5e5d372e'); }
if (!defined('CARMEL_F_SEC3_IMG')) { define('CARMEL_F_SEC3_IMG', 'field_69ffb8a0c02c3'); }
if (!defined('CARMEL_F_HERO_IMG')) { define('CARMEL_F_HERO_IMG', 'field_69feef136cbf0'); }
if (!defined('CARMEL_F_CTA1_URL')) { define('CARMEL_F_CTA1_URL', 'field_69fef07b6cbf3'); } /* main_cta_url */
if (!defined('CARMEL_F_CTA2_URL')) { define('CARMEL_F_CTA2_URL', 'field_69ffb66cd3733'); } /* cta_button_url */

/* =========================================================
 * 設定（デフォルト値）
 * =======================================================*/
function carmel_auto_defaults() {
    return array(
        'enabled'            => 0,
        'schedule'           => 'daily',                              // daily / twice / weekly
        'publish_status'     => 'draft',                             // ★下書き既定
        'gen_images'         => 1,                                    // アイキャッチ(同時生成)
        'gen_banner'         => 0,                                    // トップバナー(初期OFF)
        'gen_section_images' => 1,                                    // ★各セクション画像(裏側で1枚ずつ)
        'fix_cta'            => 1,                                    // ★壊れたCTA URLを自動修正
        'contact_url'        => '',                                   // 空なら home_url('/contact/')
        'openrouter_key'     => '',
        'image_model'        => 'google/gemini-2.5-flash-image-preview',
        'feat_w'             => 1200,
        'feat_h'             => 630,
        'banner_w'           => 1200,
        'banner_h'           => 400,
        'sec_w'              => 1200,                                 // セクション画像 幅
        'sec_h'              => 675,                                  // セクション画像 高
        'gmb_post'           => 0,
        'theme_queue'        => '',
        'last_run'           => '',
    );
}

function carmel_auto_get_settings() {
    $s = get_option(CARMEL_AUTO_OPT, array());
    if (!is_array($s)) { $s = array(); }
    return array_merge(carmel_auto_defaults(), $s);
}

function carmel_auto_set_last($msg) {
    $s = carmel_auto_get_settings();
    $s['last_run'] = '[' . current_time('Y-m-d H:i') . '] ' . $msg;
    update_option(CARMEL_AUTO_OPT, $s, false);
}

function carmel_auto_contact_url($s) {
    $u = isset($s['contact_url']) ? trim($s['contact_url']) : '';
    if ($u !== '') { return $u; }
    return home_url('/contact/');
}

/* =========================================================
 * テーマキュー1行を解析
 *   account | category | keyword | prefecture | city | title
 * =======================================================*/
function carmel_auto_parse_row($line) {
    $parts = array_map('trim', explode('|', $line));
    $g = function ($i) use ($parts) { return isset($parts[$i]) ? $parts[$i] : ''; };

    $account = $g(0); if ($account === '') { $account = 'main'; }
    $keyword = $g(2);
    $title   = $g(5);
    if ($title === '' && $keyword !== '') { $title = $keyword . '｜カーメル'; }

    return array(
        'account'    => ($account === 'fc') ? 'fc' : 'main',
        'category'   => $g(1),
        'keyword'    => $keyword,
        'prefecture' => $g(3),
        'city'       => $g(4),
        'title'      => $title,
    );
}

/* =========================================================
 * 本体v5.7の記事生成エンジンを呼ぶ
 * =======================================================*/
function carmel_auto_call_engine($row, $s) {
    if (!function_exists('carmel_generate_article_api')) {
        return new WP_Error('no_engine', '本体プラグイン(v5.7)の生成エンジン carmel_generate_article_api が見つかりません');
    }
    $args = array(
        'account'        => $row['account'],
        'category'       => $row['category'],
        'keyword'        => $row['keyword'],
        'target_keyword' => $row['keyword'],
        'prefecture'     => $row['prefecture'],
        'city'           => $row['city'],
        'title'          => $row['title'],
        'post_type'      => 'media_article',
        'post_status'    => $s['publish_status'],
    );
    return carmel_generate_article_api($args);
}

function carmel_auto_extract_post_id($res) {
    if (is_wp_error($res)) { return $res; }
    if (is_int($res) && $res > 0) { return $res; }
    if (is_numeric($res) && (int)$res > 0) { return (int)$res; }
    if (is_array($res)) {
        foreach (array('post_id', 'id', 'ID') as $k) {
            if (!empty($res[$k]) && is_numeric($res[$k])) { return (int)$res[$k]; }
        }
        if (!empty($res['data']['post_id']) && is_numeric($res['data']['post_id'])) { return (int)$res['data']['post_id']; }
    }
    if (is_object($res)) {
        foreach (array('post_id', 'id', 'ID') as $k) {
            if (!empty($res->$k) && is_numeric($res->$k)) { return (int)$res->$k; }
        }
    }
    return new WP_Error('no_id', '記事は生成されましたが投稿IDを取得できませんでした');
}

/* =========================================================
 * 画像生成（OpenRouter / Gemini画像モデル）
 *   set_time_limit(300) / timeout 180 / 最大2回リトライ
 *   成功: URL(http... or data:...) を返す / 失敗: 「画像API...」文字列
 * =======================================================*/
function carmel_img_generate_openrouter($prompt, $s) {
    @set_time_limit(300);

    $key = isset($s['openrouter_key']) ? trim($s['openrouter_key']) : '';
    if ($key === '') { return '画像API: OpenRouter APIキーが未設定です'; }

    $model    = !empty($s['image_model']) ? $s['image_model'] : 'google/gemini-2.5-flash-image-preview';
    $endpoint = 'https://openrouter.ai/api/v1/chat/completions';

    $payload = array(
        'model'      => $model,
        'messages'   => array(array('role' => 'user', 'content' => $prompt)),
        'modalities' => array('image', 'text'),
    );

    $request = array(
        'timeout' => 180,
        'headers' => array(
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/json',
            'HTTP-Referer'  => home_url('/'),
            'X-Title'       => 'CARMEL Auto',
        ),
        'body' => wp_json_encode($payload),
    );

    $last_err = '';
    for ($try = 0; $try < 3; $try++) {       // 1回目＋リトライ最大2回
        if ($try > 0) { sleep(3); }

        $resp = wp_remote_post($endpoint, $request);
        if (is_wp_error($resp)) { $last_err = $resp->get_error_message(); continue; }

        $code = (int) wp_remote_retrieve_response_code($resp);
        if ($code === 429 || $code >= 500) { $last_err = 'HTTP ' . $code; continue; }

        $json = json_decode(wp_remote_retrieve_body($resp), true);
        $url  = carmel_img_extract_url($json);
        if ($url !== '') { return $url; }

        $last_err = 'HTTP ' . $code . ' / 画像データを取得できませんでした';
    }

    return '画像API通信失敗(リトライ後): ' . $last_err;
}

function carmel_img_extract_url($json) {
    if (!is_array($json)) { return ''; }
    if (!empty($json['choices'][0]['message']['images'][0]['image_url']['url'])) {
        return $json['choices'][0]['message']['images'][0]['image_url']['url'];
    }
    if (!empty($json['choices'][0]['message']['images'][0]['url'])) {
        return $json['choices'][0]['message']['images'][0]['url'];
    }
    if (!empty($json['data'][0]['url'])) { return $json['data'][0]['url']; }
    if (!empty($json['data'][0]['b64_json'])) { return 'data:image/png;base64,' . $json['data'][0]['b64_json']; }
    return '';
}

/* =========================================================
 * 画像を取り込み → 中央クロップで指定サイズ統一 → 添付化
 *   戻り値: 添付ID(int) または WP_Error
 * =======================================================*/
function carmel_img_sideload($source, $post_id, $target_w, $target_h) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $filename = 'carmel-' . (int)$post_id . '-' . substr(md5($source . microtime()), 0, 8) . '.png';
    $tmp      = '';

    if (strpos($source, 'data:') === 0) {
        $comma = strpos($source, ',');
        if ($comma === false) { return new WP_Error('img', '画像データ(data URL)が不正です'); }
        $bin = base64_decode(substr($source, $comma + 1));
        if ($bin === false) { return new WP_Error('img', '画像のデコードに失敗しました'); }
        $tmp = wp_tempnam($filename);
        file_put_contents($tmp, $bin);
    } else {
        $tmp = download_url($source, 300);
        if (is_wp_error($tmp)) { return $tmp; }
    }

    $editor = wp_get_image_editor($tmp);
    if (!is_wp_error($editor)) {
        $editor->resize((int)$target_w, (int)$target_h, true);
        $editor->save($tmp);
    }

    $file_array = array('name' => $filename, 'tmp_name' => $tmp);
    $att_id     = media_handle_sideload($file_array, (int)$post_id);
    if (is_wp_error($att_id)) {
        if (file_exists($tmp)) { @unlink($tmp); }
        return $att_id;
    }
    return (int) $att_id;
}

/* ACF画像フィールドへ添付IDをセット（ACF有/無どちらでも） */
function carmel_auto_set_image_field($post_id, $field_name, $field_key, $att_id) {
    if (function_exists('update_field')) {
        update_field($field_key, $att_id, $post_id);
    } else {
        update_post_meta($post_id, $field_name, $att_id);
        update_post_meta($post_id, '_' . $field_name, $field_key);
    }
}

/* =========================================================
 * アイキャッチ（＋任意でバナー）を即時生成
 * =======================================================*/
function carmel_img_attach_to_post($post_id, $s) {
    $msgs = array();

    $main_prompt = get_post_meta($post_id, '_carmel_main_image_prompt', true);
    if (!$main_prompt) {
        $main_prompt = 'プロが撮影した日本の中古車販売店の、明るく清潔で安心感のある写真。文字やロゴは入れない。記事タイトル: ' . get_the_title($post_id);
    }
    $src = carmel_img_generate_openrouter($main_prompt, $s);
    if (is_string($src) && (strpos($src, 'http') === 0 || strpos($src, 'data:') === 0)) {
        $att = carmel_img_sideload($src, $post_id, $s['feat_w'], $s['feat_h']);
        if (is_wp_error($att)) {
            $msgs[] = 'アイキャッチ失敗:' . $att->get_error_message();
        } else {
            set_post_thumbnail($post_id, $att);
            $msgs[] = 'アイキャッチOK#' . $att . '(' . (int)$s['feat_w'] . 'x' . (int)$s['feat_h'] . ')';
        }
    } else {
        $msgs[] = 'アイキャッチ失敗:' . $src;
    }

    if (!empty($s['gen_banner'])) {
        $banner_prompt = get_post_meta($post_id, '_carmel_sns_image_prompt', true);
        if (!$banner_prompt) {
            $banner_prompt = '横長バナー用。日本の中古車販売の安心感ある写真。文字やロゴは入れない。';
        }
        $bsrc = carmel_img_generate_openrouter($banner_prompt, $s);
        if (is_string($bsrc) && (strpos($bsrc, 'http') === 0 || strpos($bsrc, 'data:') === 0)) {
            $batt = carmel_img_sideload($bsrc, $post_id, $s['banner_w'], $s['banner_h']);
            if (is_wp_error($batt)) {
                $msgs[] = 'バナー失敗:' . $batt->get_error_message();
            } else {
                update_post_meta($post_id, '_carmel_banner_image_id', $batt);
                $msgs[] = 'バナーOK#' . $batt . '(' . (int)$s['banner_w'] . 'x' . (int)$s['banner_h'] . ')';
            }
        } else {
            $msgs[] = 'バナー失敗:' . $bsrc;
        }
    }

    return implode(' / ', $msgs);
}

/* =========================================================
 * セクション画像＋メイン画像の「ジョブ」を作る（裏側処理用）
 * =======================================================*/
function carmel_auto_build_image_jobs($post_id, $s) {
    $jobs = array();
    if (empty($s['gen_section_images'])) { return $jobs; }

    $secs = array(
        array('section_1_title', 'section_1_body', 'section_1_image', CARMEL_F_SEC1_IMG),
        array('section_2_title', 'section_2_body', 'section_2_image', CARMEL_F_SEC2_IMG),
        array('section_3_title', 'section_3_body', 'section_3_image', CARMEL_F_SEC3_IMG),
    );
    foreach ($secs as $sec) {
        $title    = get_post_meta($post_id, $sec[0], true);
        $existing = get_post_meta($post_id, $sec[2], true);
        if ($title !== '' && empty($existing)) {
            $body = (string) get_post_meta($post_id, $sec[1], true);
            $prompt = 'プロが撮影した日本の中古車販売・自動車ローン相談に関する、自然で安心感のある写真。文字やロゴは入れない。テーマ: '
                    . $title . ' ' . mb_substr($body, 0, 80);
            $jobs[] = array(
                'name'   => $sec[2],
                'key'    => $sec[3],
                'w'      => (int)$s['sec_w'],
                'h'      => (int)$s['sec_h'],
                'prompt' => $prompt,
            );
        }
    }

    /* メイン画像(hero)が空なら追加 */
    $hero = get_post_meta($post_id, 'hero_image', true);
    if (empty($hero)) {
        $jobs[] = array(
            'name'   => 'hero_image',
            'key'    => CARMEL_F_HERO_IMG,
            'w'      => (int)$s['feat_w'],
            'h'      => (int)$s['feat_h'],
            'prompt' => '記事トップ用のメイン画像。日本の中古車販売店の明るく安心感のある写真。文字やロゴは入れない。タイトル: ' . get_the_title($post_id),
        );
    }

    return $jobs;
}

/* 裏側ワーカー：保留ジョブを1枚だけ処理 */
function carmel_auto_process_one_image($post_id) {
    $post_id = (int) $post_id;
    if ($post_id <= 0) { return '画像処理: 対象なし'; }

    $s    = carmel_auto_get_settings();
    $jobs = get_post_meta($post_id, '_carmel_pending_img_jobs', true);
    if (!is_array($jobs) || empty($jobs)) {
        carmel_auto_set_last('画像処理: 残りなし（ID#' . $post_id . '）');
        return '画像処理: 残りなし';
    }

    $job = array_shift($jobs);
    @set_time_limit(300);

    $src = carmel_img_generate_openrouter($job['prompt'], $s);
    if (is_string($src) && (strpos($src, 'http') === 0 || strpos($src, 'data:') === 0)) {
        $att = carmel_img_sideload($src, $post_id, $job['w'], $job['h']);
        if (is_wp_error($att)) {
            $msg = $job['name'] . '失敗:' . $att->get_error_message();
        } else {
            carmel_auto_set_image_field($post_id, $job['name'], $job['key'], $att);
            $msg = $job['name'] . 'OK#' . $att . '(' . (int)$job['w'] . 'x' . (int)$job['h'] . ')';
        }
    } else {
        $msg = $job['name'] . '失敗:' . $src;
    }

    update_post_meta($post_id, '_carmel_pending_img_jobs', $jobs);

    if (!empty($jobs)) {
        if (!wp_next_scheduled(CARMEL_AUTO_IMGHOOK, array($post_id))) {
            wp_schedule_single_event(time() + 20, CARMEL_AUTO_IMGHOOK, array($post_id));
        }
    } else {
        delete_post_meta($post_id, '_carmel_pending_img_jobs');
    }

    carmel_auto_set_last('画像処理: ' . $msg . ' / 残り' . count($jobs) . '枚（ID#' . $post_id . '）');
    return $msg;
}
add_action(CARMEL_AUTO_IMGHOOK, 'carmel_auto_process_one_image');

/* =========================================================
 * 壊れたCTA URLを自動修正
 *   例: http://. home_url('/contact/') .  → home_url('/contact/')
 * =======================================================*/
function carmel_auto_fix_cta($post_id, $s) {
    $contact = carmel_auto_contact_url($s);
    $fields  = array(
        'main_cta_url'   => CARMEL_F_CTA1_URL,
        'cta_button_url' => CARMEL_F_CTA2_URL,
    );
    $fixed = 0;
    foreach ($fields as $name => $key) {
        $val = (string) get_post_meta($post_id, $name, true);
        $bad = ($val === '')
            || (strpos($val, 'home_url') !== false)
            || (strpos($val, '%20') !== false)
            || (strpos($val, '://.') !== false)
            || (strpos($val, ' ') !== false)
            || (strpos($val, 'http') !== 0);
        if ($bad) {
            carmel_auto_set_image_field($post_id, $name, $key, $contact); /* 文字列もこのヘルパで保存可 */
            $fixed++;
        }
    }
    return $fixed;
}

/* =========================================================
 * 1件だけ生成（キュー先頭の未生成行を処理）
 * =======================================================*/
function carmel_auto_do_one() {
    $s = carmel_auto_get_settings();

    $lines = preg_split('/\r\n|\r|\n/', (string)$s['theme_queue']);
    $done  = get_option(CARMEL_AUTO_DONE, array());
    if (!is_array($done)) { $done = array(); }

    $picked = null; $key = '';
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) { continue; }
        $k = md5($line);
        if (in_array($k, $done, true)) { continue; }
        $picked = $line; $key = $k; break;
    }

    if ($picked === null) {
        $msg = '対象なし: 未生成のテーマ行がありません（「生成済みをリセット」で再生成できます）';
        carmel_auto_set_last($msg);
        return $msg;
    }

    $row = carmel_auto_parse_row($picked);
    if ($row['keyword'] === '') {
        $msg = '失敗: 行の書式が不正です（account | category | keyword | prefecture | city | title）';
        carmel_auto_set_last($msg);
        return $msg;
    }

    @set_time_limit(300);

    $res = carmel_auto_call_engine($row, $s);
    $pid = carmel_auto_extract_post_id($res);
    if (is_wp_error($pid)) {
        $msg = '失敗: ' . $row['keyword'] . ' / ' . $pid->get_error_message();
        carmel_auto_set_last($msg);
        return $msg;
    }

    /* CTA URL 自動修正（軽い処理なので即時） */
    $cta_msg = '';
    if (!empty($s['fix_cta'])) {
        $n = carmel_auto_fix_cta($pid, $s);
        $cta_msg = ($n > 0) ? ' / CTA修正' . $n . '件' : '';
    }

    /* アイキャッチ（＋任意バナー）を即時生成 */
    if (!empty($s['gen_images'])) {
        $img_msg = carmel_img_attach_to_post($pid, $s);
    } else {
        $img_msg = '画像生成OFF';
    }

    /* セクション画像＋メイン画像は「裏側で1枚ずつ」キューに積む */
    $queue_msg = '';
    $jobs = carmel_auto_build_image_jobs($pid, $s);
    if (!empty($jobs)) {
        update_post_meta($pid, '_carmel_pending_img_jobs', $jobs);
        update_option(CARMEL_AUTO_ACTIVE, $pid, false);
        if (!wp_next_scheduled(CARMEL_AUTO_IMGHOOK, array($pid))) {
            wp_schedule_single_event(time() + 10, CARMEL_AUTO_IMGHOOK, array($pid));
        }
        if (function_exists('spawn_cron')) { spawn_cron(); }
        $queue_msg = ' / 本文画像' . count($jobs) . '枚を裏側で生成中';
    }

    /* Googleマイビジネス（公開記事のときだけ） */
    $gmb_msg = '';
    if (!empty($s['gmb_post']) && $s['publish_status'] === 'publish') {
        $gmb_msg = ' / ' . carmel_auto_post_to_gmb($pid, $row['account']);
    }

    $done[] = $key;
    update_option(CARMEL_AUTO_DONE, $done, false);

    $msg = '成功: ' . $row['keyword'] . ' (投稿ID#' . $pid . ' / ' . $s['publish_status'] . ') / '
         . $img_msg . $queue_msg . $cta_msg . $gmb_msg;
    carmel_auto_set_last($msg);
    return $msg;
}

/* =========================================================
 * Googleマイビジネス自動投稿（本体の carmel_post_to_google を呼ぶ）
 * =======================================================*/
function carmel_auto_post_to_gmb($post_id, $account) {
    if (!function_exists('carmel_post_to_google')) {
        return 'GMB:本体のGoogle投稿関数なし';
    }
    $acc = ($account === 'fc') ? 'fc' : 'main';
    $r   = carmel_post_to_google((int)$post_id, $acc);
    if (is_wp_error($r)) { return 'GMB失敗:' . $r->get_error_message(); }
    return 'GMB投稿OK';
}

/* =========================================================
 * WP-Cron（定期生成）
 * =======================================================*/
add_filter('cron_schedules', 'carmel_auto_cron_schedules');
function carmel_auto_cron_schedules($schedules) {
    if (!isset($schedules['carmel_twice_weekly'])) {
        $schedules['carmel_twice_weekly'] = array(
            'interval' => 302400,            // 3.5日（週2回相当）
            'display'  => '週2回(3.5日ごと)',
        );
    }
    return $schedules;
}

add_action(CARMEL_AUTO_HOOK, 'carmel_auto_cron_run');
function carmel_auto_cron_run() {
    carmel_auto_do_one();
}

function carmel_auto_reschedule($s) {
    $ts = wp_next_scheduled(CARMEL_AUTO_HOOK);
    if ($ts) { wp_unschedule_event($ts, CARMEL_AUTO_HOOK); }

    if (!empty($s['enabled'])) {
        $map = array('daily' => 'daily', 'twice' => 'carmel_twice_weekly', 'weekly' => 'weekly');
        $rec = isset($map[$s['schedule']]) ? $map[$s['schedule']] : 'daily';
        wp_schedule_event(time() + 60, $rec, CARMEL_AUTO_HOOK);
    }
}

/* =========================================================
 * 管理メニュー
 * =======================================================*/
add_action('admin_menu', 'carmel_auto_menu', 99);
function carmel_auto_menu() {
    add_menu_page(
        'CARMEL自動生成',
        'CARMEL自動生成',
        'manage_options',
        'carmel-auto',
        'carmel_auto_page',
        'dashicons-update',
        99
    );
}

function carmel_auto_page() {
    if (!current_user_can('manage_options')) { return; }
    $s = carmel_auto_get_settings();

    $engine_ok = function_exists('carmel_generate_article_api');
    $acf_ok    = function_exists('update_field');
    $gmb_ok    = function_exists('carmel_post_to_google');
    $next      = wp_next_scheduled(CARMEL_AUTO_HOOK);
    $active    = (int) get_option(CARMEL_AUTO_ACTIVE, 0);
    $pending   = 0;
    if ($active) {
        $pj = get_post_meta($active, '_carmel_pending_img_jobs', true);
        $pending = is_array($pj) ? count($pj) : 0;
    }
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-update"></span> CARMEL自動生成</h1>

        <?php if (isset($_GET['saved'])) { echo '<div class="notice notice-success is-dismissible"><p>設定を保存しました。</p></div>'; } ?>
        <?php if (isset($_GET['ran']))   { echo '<div class="notice notice-info is-dismissible"><p>テスト生成を実行しました。下の「最終実行」をご確認ください。</p></div>'; } ?>
        <?php if (isset($_GET['img']))   { echo '<div class="notice notice-info is-dismissible"><p>本文画像を1枚処理しました。残りがある場合はもう一度押すか、しばらくお待ちください。</p></div>'; } ?>
        <?php if (isset($_GET['reset'])) { echo '<div class="notice notice-warning is-dismissible"><p>生成済みリストをリセットしました。</p></div>'; } ?>

        <div style="background:#fff;border:1px solid #ccd0d4;padding:12px 16px;margin:12px 0;">
            <p style="margin:.3em 0;"><strong>エンジン接続：</strong>
                <?php echo $engine_ok ? '<span style="color:#1a7f37;">● 接続OK（本体v5.7を検出）</span>' : '<span style="color:#b32d2e;">● 未接続（本体プラグインが見つかりません）</span>'; ?>
                ／ <strong>ACF：</strong><?php echo $acf_ok ? '<span style="color:#1a7f37;">● あり</span>' : '<span style="color:#888;">● なし(メタ直書きで対応)</span>'; ?>
                ／ <strong>Google投稿関数：</strong><?php echo $gmb_ok ? '<span style="color:#1a7f37;">● あり</span>' : '<span style="color:#888;">● なし</span>'; ?>
            </p>
            <p style="margin:.3em 0;"><strong>次回の自動実行：</strong>
                <?php echo $next ? esc_html(date_i18n('Y-m-d H:i', $next)) : '未設定（自動運転OFF）'; ?>
            </p>
            <p style="margin:.3em 0;"><strong>本文画像キュー：</strong>
                <?php
                if ($active && $pending > 0) {
                    echo '投稿ID#' . $active . ' に残り <strong>' . $pending . '枚</strong>（裏側で生成中）';
                } else {
                    echo '残りなし';
                }
                ?>
            </p>
            <p style="margin:.6em 0 .2em;"><strong>最終実行：</strong></p>
            <p style="margin:.2em 0;padding:8px;background:#f6f7f7;border-left:4px solid #2271b1;">
                <?php echo $s['last_run'] !== '' ? esc_html($s['last_run']) : '（まだ実行していません）'; ?>
            </p>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="carmel_auto_save">
            <?php wp_nonce_field('carmel_auto_save'); ?>

            <h2 class="title">基本</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">自動運転</th>
                    <td><label><input type="checkbox" name="enabled" value="1" <?php checked(!empty($s['enabled'])); ?>> 自動生成（WP-Cron）を有効にする</label></td>
                </tr>
                <tr>
                    <th scope="row">実行頻度</th>
                    <td>
                        <select name="schedule">
                            <option value="daily"  <?php selected($s['schedule'], 'daily'); ?>>毎日</option>
                            <option value="twice"  <?php selected($s['schedule'], 'twice'); ?>>週2回</option>
                            <option value="weekly" <?php selected($s['schedule'], 'weekly'); ?>>毎週</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row">公開方法</th>
                    <td>
                        <select name="publish_status">
                            <option value="draft"   <?php selected($s['publish_status'], 'draft'); ?>>下書きで保存（推奨：人の目を通す）</option>
                            <option value="publish" <?php selected($s['publish_status'], 'publish'); ?>>すぐ公開する</option>
                        </select>
                    </td>
                </tr>
            </table>

            <h2 class="title">画像</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">画像生成</th>
                    <td>
                        <p><label><input type="checkbox" name="gen_images" value="1" <?php checked(!empty($s['gen_images'])); ?>> アイキャッチ画像を生成する（記事と同時）</label></p>
                        <p><label><input type="checkbox" name="gen_section_images" value="1" <?php checked(!empty($s['gen_section_images'])); ?>> <strong>本文の各セクション画像も生成する</strong>（section_1〜3＋メイン画像／裏側で1枚ずつ・時間切れ回避）</label></p>
                        <p><label><input type="checkbox" name="gen_banner" value="1" <?php checked(!empty($s['gen_banner'])); ?>> トップバナー画像も生成する（OFF推奨）</label></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">OpenRouter APIキー</th>
                    <td><input type="text" name="openrouter_key" value="<?php echo esc_attr($s['openrouter_key']); ?>" class="regular-text" placeholder="sk-or-..."></td>
                </tr>
                <tr>
                    <th scope="row">画像モデル</th>
                    <td><input type="text" name="image_model" value="<?php echo esc_attr($s['image_model']); ?>" class="regular-text">
                        <p class="description">既定: google/gemini-2.5-flash-image-preview（失敗が続くなら軽い画像対応モデルへ）</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">アイキャッチ寸法</th>
                    <td>幅 <input type="number" name="feat_w" value="<?php echo (int)$s['feat_w']; ?>" style="width:90px;"> × 高 <input type="number" name="feat_h" value="<?php echo (int)$s['feat_h']; ?>" style="width:90px;"> px</td>
                </tr>
                <tr>
                    <th scope="row">セクション画像寸法</th>
                    <td>幅 <input type="number" name="sec_w" value="<?php echo (int)$s['sec_w']; ?>" style="width:90px;"> × 高 <input type="number" name="sec_h" value="<?php echo (int)$s['sec_h']; ?>" style="width:90px;"> px</td>
                </tr>
                <tr>
                    <th scope="row">バナー寸法</th>
                    <td>幅 <input type="number" name="banner_w" value="<?php echo (int)$s['banner_w']; ?>" style="width:90px;"> × 高 <input type="number" name="banner_h" value="<?php echo (int)$s['banner_h']; ?>" style="width:90px;"> px</td>
                </tr>
            </table>

            <h2 class="title">CTA（お問い合わせリンク）</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">CTA自動修正</th>
                    <td><label><input type="checkbox" name="fix_cta" value="1" <?php checked(!empty($s['fix_cta'])); ?>> 壊れたCTAリンク（http://. home_url(...) . など）を自動で直す</label></td>
                </tr>
                <tr>
                    <th scope="row">お問い合わせURL</th>
                    <td><input type="text" name="contact_url" value="<?php echo esc_attr($s['contact_url']); ?>" class="regular-text" placeholder="<?php echo esc_attr(home_url('/contact/')); ?>">
                        <p class="description">空欄なら <code><?php echo esc_html(home_url('/contact/')); ?></code> を使用します。</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">Googleマイビジネス</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">自動投稿</th>
                    <td><label><input type="checkbox" name="gmb_post" value="1" <?php checked(!empty($s['gmb_post'])); ?>> 公開記事をGoogleへ自動投稿する</label>
                        <p class="description">外部プラグイン「Auto Publish for Google My Business」と併用すると二重投稿になります。どちらか一方に。</p>
                    </td>
                </tr>
            </table>

            <h2 class="title">テーマキュー</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">生成テーマ（1行1件）</th>
                    <td>
                        <textarea name="theme_queue" rows="8" class="large-text code" placeholder="main | 自社ローン | 任意整理中 車 購入 方法 | 東京都 | 新宿区 | "><?php echo esc_textarea($s['theme_queue']); ?></textarea>
                        <p class="description">書式： <code>account | category | keyword | prefecture | city | title</code><br>
                            account は main / fc。title 空欄なら keyword から自動生成。先頭 <code>#</code> の行は無視。</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('設定を保存'); ?>
        </form>

        <hr>

        <h2 class="title">テスト・メンテナンス</h2>
        <p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                <input type="hidden" name="action" value="carmel_auto_run_now">
                <?php wp_nonce_field('carmel_auto_run_now'); ?>
                <?php submit_button('今すぐ1件だけ生成（テスト）', 'primary', 'submit', false); ?>
            </form>
            &nbsp;
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                <input type="hidden" name="action" value="carmel_auto_process_images">
                <?php wp_nonce_field('carmel_auto_process_images'); ?>
                <?php submit_button('本文画像を今すぐ1枚進める', 'secondary', 'submit', false, $pending > 0 ? array() : array('disabled' => 'disabled')); ?>
            </form>
            &nbsp;
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;" onsubmit="return confirm('生成済みリストを空にします。同じテーマを再生成できるようになります。よろしいですか？');">
                <input type="hidden" name="action" value="carmel_auto_reset_done">
                <?php wp_nonce_field('carmel_auto_reset_done'); ?>
                <?php submit_button('生成済みをリセット', 'secondary', 'submit', false); ?>
            </form>
        </p>
    </div>
    <?php
}

/* =========================================================
 * 保存処理
 * =======================================================*/
add_action('admin_post_carmel_auto_save', 'carmel_auto_save');
function carmel_auto_save() {
    if (!current_user_can('manage_options')) { wp_die('権限がありません'); }
    check_admin_referer('carmel_auto_save');

    $s = carmel_auto_get_settings();

    $s['enabled']            = isset($_POST['enabled']) ? 1 : 0;

    $sch = isset($_POST['schedule']) ? $_POST['schedule'] : 'daily';
    $s['schedule']           = in_array($sch, array('daily', 'twice', 'weekly'), true) ? $sch : 'daily';

    $ps = isset($_POST['publish_status']) ? $_POST['publish_status'] : 'draft';
    $s['publish_status']     = ($ps === 'publish') ? 'publish' : 'draft';

    $s['gen_images']         = isset($_POST['gen_images']) ? 1 : 0;
    $s['gen_section_images'] = isset($_POST['gen_section_images']) ? 1 : 0;
    $s['gen_banner']         = isset($_POST['gen_banner']) ? 1 : 0;
    $s['fix_cta']            = isset($_POST['fix_cta']) ? 1 : 0;
    $s['gmb_post']           = isset($_POST['gmb_post']) ? 1 : 0;

    $s['contact_url']        = isset($_POST['contact_url']) ? esc_url_raw(trim($_POST['contact_url'])) : '';
    $s['openrouter_key']     = isset($_POST['openrouter_key']) ? sanitize_text_field($_POST['openrouter_key']) : '';
    $s['image_model']        = isset($_POST['image_model']) ? sanitize_text_field($_POST['image_model']) : $s['image_model'];

    $s['feat_w']             = max(1, (int)(isset($_POST['feat_w']) ? $_POST['feat_w'] : 1200));
    $s['feat_h']             = max(1, (int)(isset($_POST['feat_h']) ? $_POST['feat_h'] : 630));
    $s['banner_w']           = max(1, (int)(isset($_POST['banner_w']) ? $_POST['banner_w'] : 1200));
    $s['banner_h']           = max(1, (int)(isset($_POST['banner_h']) ? $_POST['banner_h'] : 400));
    $s['sec_w']              = max(1, (int)(isset($_POST['sec_w']) ? $_POST['sec_w'] : 1200));
    $s['sec_h']              = max(1, (int)(isset($_POST['sec_h']) ? $_POST['sec_h'] : 675));

    $s['theme_queue']        = isset($_POST['theme_queue']) ? sanitize_textarea_field(wp_unslash($_POST['theme_queue'])) : '';

    update_option(CARMEL_AUTO_OPT, $s, false);
    carmel_auto_reschedule($s);

    wp_safe_redirect(admin_url('admin.php?page=carmel-auto&saved=1'));
    exit;
}

/* =========================================================
 * 即時実行（テスト）
 * =======================================================*/
add_action('admin_post_carmel_auto_run_now', 'carmel_auto_run_now');
function carmel_auto_run_now() {
    if (!current_user_can('manage_options')) { wp_die('権限がありません'); }
    check_admin_referer('carmel_auto_run_now');

    carmel_auto_do_one();

    wp_safe_redirect(admin_url('admin.php?page=carmel-auto&ran=1'));
    exit;
}

/* =========================================================
 * 本文画像を今すぐ1枚進める（テスト用・WP-Cron不発の保険）
 * =======================================================*/
add_action('admin_post_carmel_auto_process_images', 'carmel_auto_process_images_now');
function carmel_auto_process_images_now() {
    if (!current_user_can('manage_options')) { wp_die('権限がありません'); }
    check_admin_referer('carmel_auto_process_images');

    $active = (int) get_option(CARMEL_AUTO_ACTIVE, 0);
    if ($active > 0) {
        carmel_auto_process_one_image($active);
    } else {
        carmel_auto_set_last('画像処理: 対象の投稿がありません');
    }

    wp_safe_redirect(admin_url('admin.php?page=carmel-auto&img=1'));
    exit;
}

/* =========================================================
 * 生成済みリセット
 * =======================================================*/
add_action('admin_post_carmel_auto_reset_done', 'carmel_auto_reset_done');
function carmel_auto_reset_done() {
    if (!current_user_can('manage_options')) { wp_die('権限がありません'); }
    check_admin_referer('carmel_auto_reset_done');

    update_option(CARMEL_AUTO_DONE, array(), false);

    wp_safe_redirect(admin_url('admin.php?page=carmel-auto&reset=1'));
    exit;
}
