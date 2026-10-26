<?php
/**
 * Plugin Name: AUC-AGENT 顧客・オーダー管理
 * Description: 車オークション代行の顧客管理・オーダー管理・ステータス追跡システム
 * Version: 1.0.0
 * Author: 合同会社アイズ
 * Text Domain: auc-agent-crm
 */

if (!defined('ABSPATH')) exit;

define('AUC_CRM_VERSION', '1.0.0');
define('AUC_CRM_PATH', plugin_dir_path(__FILE__));
define('AUC_CRM_URL', plugin_dir_url(__FILE__));

class AUC_Agent_CRM {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'register_post_types']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_styles']);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_auc_order', [$this, 'save_order_meta']);
        add_action('manage_auc_order_posts_columns', [$this, 'order_columns']);
        add_action('manage_auc_order_posts_custom_column', [$this, 'order_column_content'], 10, 2);
        add_filter('manage_edit-auc_order_sortable_columns', [$this, 'order_sortable_columns']);

        // Ultimate Member連携: 登録時に顧客メタを追加
        add_action('um_registration_complete', [$this, 'on_um_registration'], 10, 2);
    }

    // ========== カスタム投稿タイプ ==========

    public function register_post_types() {
        // オーダー
        register_post_type('auc_order', [
            'labels' => [
                'name' => 'オーダー管理',
                'singular_name' => 'オーダー',
                'add_new' => '新規オーダー',
                'add_new_item' => '新規オーダーを追加',
                'edit_item' => 'オーダーを編集',
                'view_item' => 'オーダーを表示',
                'all_items' => 'オーダー一覧',
                'search_items' => 'オーダーを検索',
                'not_found' => 'オーダーが見つかりません',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
        ]);

        // 車両提案
        register_post_type('auc_proposal', [
            'labels' => [
                'name' => '車両提案',
                'singular_name' => '提案',
                'add_new' => '新規提案',
                'add_new_item' => '新規提案を追加',
                'edit_item' => '提案を編集',
                'all_items' => '提案一覧',
                'search_items' => '提案を検索',
                'not_found' => '提案が見つかりません',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title'],
            'capability_type' => 'post',
        ]);
    }

    // ========== 管理メニュー ==========

    public function admin_menu() {
        add_menu_page(
            'AUC-AGENT 管理',
            'AUC管理',
            'manage_options',
            'auc-dashboard',
            [$this, 'page_dashboard'],
            'dashicons-car',
            3
        );

        add_submenu_page('auc-dashboard', 'ダッシュボード', 'ダッシュボード', 'manage_options', 'auc-dashboard', [$this, 'page_dashboard']);
        add_submenu_page('auc-dashboard', '顧客一覧', '顧客一覧', 'manage_options', 'auc-customers', [$this, 'page_customers']);
        add_submenu_page('auc-dashboard', 'オーダー一覧', 'オーダー一覧', 'manage_options', 'edit.php?post_type=auc_order');
        add_submenu_page('auc-dashboard', '新規オーダー', '新規オーダー', 'manage_options', 'post-new.php?post_type=auc_order');
        add_submenu_page('auc-dashboard', '車両提案', '車両提案', 'manage_options', 'edit.php?post_type=auc_proposal');
    }

    // ========== スタイル ==========

    public function admin_styles($hook) {
        $screen = get_current_screen();
        if (!$screen) return;
        if (strpos($hook, 'auc-') !== false || $screen->post_type === 'auc_order' || $screen->post_type === 'auc_proposal') {
            wp_enqueue_style('auc-crm-admin', AUC_CRM_URL . 'assets/admin.css', [], AUC_CRM_VERSION);
        }
    }

    // ========== ダッシュボード ==========

    public function page_dashboard() {
        $total_customers = count(get_users(['role__in' => ['subscriber', 'um_member'], 'fields' => 'ID']));
        $statuses = $this->get_status_list();
        $order_counts = [];
        foreach ($statuses as $key => $label) {
            $order_counts[$key] = count(get_posts([
                'post_type' => 'auc_order',
                'post_status' => 'publish',
                'meta_query' => [['key' => '_auc_status', 'value' => $key]],
                'fields' => 'ids',
                'posts_per_page' => -1,
            ]));
        }
        $total_orders = array_sum($order_counts);

        ?>
        <div class="wrap auc-wrap">
            <h1>AUC-AGENT ダッシュボード</h1>
            <div class="auc-cards">
                <div class="auc-card auc-card--navy">
                    <div class="auc-card__num"><?php echo esc_html($total_customers); ?></div>
                    <div class="auc-card__label">登録顧客数</div>
                </div>
                <div class="auc-card auc-card--gold">
                    <div class="auc-card__num"><?php echo esc_html($total_orders); ?></div>
                    <div class="auc-card__label">オーダー総数</div>
                </div>
                <?php foreach ($statuses as $key => $label): if ($order_counts[$key] > 0): ?>
                <div class="auc-card">
                    <div class="auc-card__num"><?php echo esc_html($order_counts[$key]); ?></div>
                    <div class="auc-card__label"><?php echo esc_html($label); ?></div>
                </div>
                <?php endif; endforeach; ?>
            </div>

            <h2>最近のオーダー</h2>
            <?php
            $recent = get_posts([
                'post_type' => 'auc_order',
                'post_status' => 'publish',
                'posts_per_page' => 10,
                'orderby' => 'date',
                'order' => 'DESC',
            ]);
            if ($recent): ?>
            <table class="widefat auc-table">
                <thead>
                    <tr>
                        <th>オーダー名</th>
                        <th>顧客</th>
                        <th>希望車種</th>
                        <th>予算</th>
                        <th>ステータス</th>
                        <th>作成日</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $order):
                    $status_key = get_post_meta($order->ID, '_auc_status', true) ?: 'inquiry';
                    $customer_id = get_post_meta($order->ID, '_auc_customer_id', true);
                    $customer_name = $customer_id ? get_user_meta($customer_id, 'first_name', true) . ' ' . get_user_meta($customer_id, 'last_name', true) : '—';
                    $vehicle = get_post_meta($order->ID, '_auc_vehicle', true);
                    $budget = get_post_meta($order->ID, '_auc_budget', true);
                ?>
                    <tr>
                        <td><a href="<?php echo get_edit_post_link($order->ID); ?>"><?php echo esc_html($order->post_title); ?></a></td>
                        <td><?php echo esc_html(trim($customer_name)); ?></td>
                        <td><?php echo esc_html($vehicle ?: '—'); ?></td>
                        <td><?php echo $budget ? '¥' . number_format((int)$budget) : '—'; ?></td>
                        <td><span class="auc-status auc-status--<?php echo esc_attr($status_key); ?>"><?php echo esc_html($statuses[$status_key] ?? $status_key); ?></span></td>
                        <td><?php echo get_the_date('Y/m/d', $order); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
                <p>まだオーダーがありません。</p>
            <?php endif; ?>
        </div>
        <?php
    }

    // ========== 顧客一覧 ==========

    public function page_customers() {
        $users = get_users([
            'role__in' => ['subscriber', 'um_member', 'administrator'],
            'orderby' => 'registered',
            'order' => 'DESC',
        ]);
        ?>
        <div class="wrap auc-wrap">
            <h1>顧客一覧 <span class="auc-count">(<?php echo count($users); ?>名)</span></h1>
            <table class="widefat auc-table">
                <thead>
                    <tr>
                        <th>名前</th>
                        <th>メール</th>
                        <th>電話番号</th>
                        <th>希望車種</th>
                        <th>予算</th>
                        <th>エリア</th>
                        <th>登録日</th>
                        <th>オーダー数</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    $order_count = count(get_posts([
                        'post_type' => 'auc_order',
                        'post_status' => 'publish',
                        'meta_query' => [['key' => '_auc_customer_id', 'value' => $u->ID]],
                        'fields' => 'ids',
                        'posts_per_page' => -1,
                    ]));
                    $phone = get_user_meta($u->ID, 'auc_phone', true);
                    $wish_vehicle = get_user_meta($u->ID, 'auc_wish_vehicle', true);
                    $wish_budget = get_user_meta($u->ID, 'auc_wish_budget', true);
                    $wish_area = get_user_meta($u->ID, 'auc_wish_area', true);
                ?>
                    <tr>
                        <td><a href="<?php echo admin_url('user-edit.php?user_id=' . $u->ID); ?>"><?php echo esc_html($u->display_name); ?></a></td>
                        <td><?php echo esc_html($u->user_email); ?></td>
                        <td><?php echo esc_html($phone ?: '—'); ?></td>
                        <td><?php echo esc_html($wish_vehicle ?: '—'); ?></td>
                        <td><?php echo $wish_budget ? '¥' . number_format((int)$wish_budget) : '—'; ?></td>
                        <td><?php echo esc_html($wish_area ?: '—'); ?></td>
                        <td><?php echo date('Y/m/d', strtotime($u->user_registered)); ?></td>
                        <td><?php echo esc_html($order_count); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    // ========== オーダー メタボックス ==========

    public function add_meta_boxes() {
        add_meta_box('auc_order_details', 'オーダー詳細', [$this, 'render_order_meta_box'], 'auc_order', 'normal', 'high');
        add_meta_box('auc_order_notes', '対応メモ・履歴', [$this, 'render_notes_meta_box'], 'auc_order', 'normal', 'default');
        add_meta_box('auc_proposal_details', '提案詳細', [$this, 'render_proposal_meta_box'], 'auc_proposal', 'normal', 'high');
    }

    public function render_order_meta_box($post) {
        wp_nonce_field('auc_order_save', 'auc_order_nonce');
        $fields = $this->get_order_fields($post->ID);
        $statuses = $this->get_status_list();
        $users = get_users(['role__in' => ['subscriber', 'um_member', 'administrator'], 'orderby' => 'display_name']);
        ?>
        <div class="auc-meta-grid">
            <div class="auc-meta-field">
                <label>ステータス</label>
                <select name="auc_status">
                    <?php foreach ($statuses as $k => $v): ?>
                        <option value="<?php echo esc_attr($k); ?>" <?php selected($fields['status'], $k); ?>><?php echo esc_html($v); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="auc-meta-field">
                <label>顧客</label>
                <select name="auc_customer_id">
                    <option value="">— 選択 —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($fields['customer_id'], $u->ID); ?>><?php echo esc_html($u->display_name . ' (' . $u->user_email . ')'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="auc-meta-field">
                <label>希望車種</label>
                <input type="text" name="auc_vehicle" value="<?php echo esc_attr($fields['vehicle']); ?>" placeholder="例：トヨタ アルファード 2022年式">
            </div>
            <div class="auc-meta-field">
                <label>車両クラス</label>
                <select name="auc_class">
                    <option value="">— 選択 —</option>
                    <option value="kei" <?php selected($fields['class'], 'kei'); ?>>軽自動車</option>
                    <option value="compact" <?php selected($fields['class'], 'compact'); ?>>コンパクト</option>
                    <option value="sedan" <?php selected($fields['class'], 'sedan'); ?>>セダン/ミニバン</option>
                    <option value="suv" <?php selected($fields['class'], 'suv'); ?>>SUV/大型</option>
                    <option value="import" <?php selected($fields['class'], 'import'); ?>>輸入車</option>
                </select>
            </div>
            <div class="auc-meta-field">
                <label>予算上限</label>
                <input type="number" name="auc_budget" value="<?php echo esc_attr($fields['budget']); ?>" placeholder="例：1500000" step="10000">
            </div>
            <div class="auc-meta-field">
                <label>納車先（都道府県）</label>
                <input type="text" name="auc_area" value="<?php echo esc_attr($fields['area']); ?>" placeholder="例：東京都">
            </div>
            <div class="auc-meta-field">
                <label>落札価格</label>
                <input type="number" name="auc_bid_price" value="<?php echo esc_attr($fields['bid_price']); ?>" placeholder="落札後に入力" step="1000">
            </div>
            <div class="auc-meta-field">
                <label>代行手数料</label>
                <input type="number" name="auc_agent_fee" value="<?php echo esc_attr($fields['agent_fee']); ?>" step="100">
            </div>
            <div class="auc-meta-field">
                <label>陸送費</label>
                <input type="number" name="auc_shipping" value="<?php echo esc_attr($fields['shipping']); ?>" step="1000">
            </div>
            <div class="auc-meta-field">
                <label>総額</label>
                <input type="number" name="auc_total" value="<?php echo esc_attr($fields['total']); ?>" step="1">
            </div>
        </div>
        <?php
    }

    public function render_notes_meta_box($post) {
        $notes = get_post_meta($post->ID, '_auc_notes', true) ?: '';
        ?>
        <textarea name="auc_notes" rows="8" style="width:100%;" placeholder="対応履歴・メモを記入&#10;例：2026/10/25 お客様と電話。アルファード希望、予算150万。&#10;例：2026/10/26 候補3台提案済み。"><?php echo esc_textarea($notes); ?></textarea>
        <?php
    }

    public function render_proposal_meta_box($post) {
        wp_nonce_field('auc_order_save', 'auc_order_nonce');
        $order_id = get_post_meta($post->ID, '_auc_order_id', true);
        $auction_price = get_post_meta($post->ID, '_auc_auction_price', true);
        $est_total = get_post_meta($post->ID, '_auc_est_total', true);
        $eval_score = get_post_meta($post->ID, '_auc_eval_score', true);
        $condition = get_post_meta($post->ID, '_auc_condition', true);
        $proposal_status = get_post_meta($post->ID, '_auc_proposal_status', true) ?: 'pending';
        $proposal_note = get_post_meta($post->ID, '_auc_proposal_note', true);

        $orders = get_posts(['post_type' => 'auc_order', 'post_status' => 'publish', 'posts_per_page' => -1]);
        ?>
        <div class="auc-meta-grid">
            <div class="auc-meta-field">
                <label>紐づくオーダー</label>
                <select name="auc_order_id">
                    <option value="">— 選択 —</option>
                    <?php foreach ($orders as $o): ?>
                        <option value="<?php echo esc_attr($o->ID); ?>" <?php selected($order_id, $o->ID); ?>><?php echo esc_html($o->post_title); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="auc-meta-field">
                <label>オークション相場価格</label>
                <input type="number" name="auc_auction_price" value="<?php echo esc_attr($auction_price); ?>" step="1000">
            </div>
            <div class="auc-meta-field">
                <label>乗り出し見積り総額</label>
                <input type="number" name="auc_est_total" value="<?php echo esc_attr($est_total); ?>" step="1000">
            </div>
            <div class="auc-meta-field">
                <label>評価点</label>
                <input type="text" name="auc_eval_score" value="<?php echo esc_attr($eval_score); ?>" placeholder="例：4.5">
            </div>
            <div class="auc-meta-field">
                <label>車両状態メモ</label>
                <input type="text" name="auc_condition" value="<?php echo esc_attr($condition); ?>" placeholder="修復歴なし、走行5万km 等">
            </div>
            <div class="auc-meta-field">
                <label>提案ステータス</label>
                <select name="auc_proposal_status">
                    <option value="pending" <?php selected($proposal_status, 'pending'); ?>>提案待ち</option>
                    <option value="proposed" <?php selected($proposal_status, 'proposed'); ?>>提案済み</option>
                    <option value="approved" <?php selected($proposal_status, 'approved'); ?>>承認（入札OK）</option>
                    <option value="rejected" <?php selected($proposal_status, 'rejected'); ?>>見送り</option>
                    <option value="won" <?php selected($proposal_status, 'won'); ?>>落札済み</option>
                </select>
            </div>
            <div class="auc-meta-field" style="grid-column:1/-1">
                <label>備考</label>
                <textarea name="auc_proposal_note" rows="3" style="width:100%"><?php echo esc_textarea($proposal_note); ?></textarea>
            </div>
        </div>
        <?php
    }

    // ========== 保存 ==========

    public function save_order_meta($post_id) {
        if (!isset($_POST['auc_order_nonce']) || !wp_verify_nonce($_POST['auc_order_nonce'], 'auc_order_save')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

        $text_fields = ['status', 'customer_id', 'vehicle', 'class', 'budget', 'area', 'bid_price', 'agent_fee', 'shipping', 'total'];
        foreach ($text_fields as $f) {
            if (isset($_POST['auc_' . $f])) {
                update_post_meta($post_id, '_auc_' . $f, sanitize_text_field($_POST['auc_' . $f]));
            }
        }
        if (isset($_POST['auc_notes'])) {
            update_post_meta($post_id, '_auc_notes', sanitize_textarea_field($_POST['auc_notes']));
        }

        // 提案用
        $proposal_fields = ['order_id', 'auction_price', 'est_total', 'eval_score', 'condition', 'proposal_status', 'proposal_note'];
        foreach ($proposal_fields as $f) {
            if (isset($_POST['auc_' . $f])) {
                update_post_meta($post_id, '_auc_' . $f, sanitize_text_field($_POST['auc_' . $f]));
            }
        }
    }

    // ========== オーダー一覧カラム ==========

    public function order_columns($columns) {
        return [
            'cb' => $columns['cb'],
            'title' => 'オーダー名',
            'auc_customer' => '顧客',
            'auc_vehicle' => '希望車種',
            'auc_budget' => '予算',
            'auc_status' => 'ステータス',
            'date' => '日付',
        ];
    }

    public function order_column_content($column, $post_id) {
        $statuses = $this->get_status_list();
        switch ($column) {
            case 'auc_customer':
                $cid = get_post_meta($post_id, '_auc_customer_id', true);
                echo $cid ? esc_html(get_userdata($cid)->display_name ?? '—') : '—';
                break;
            case 'auc_vehicle':
                echo esc_html(get_post_meta($post_id, '_auc_vehicle', true) ?: '—');
                break;
            case 'auc_budget':
                $b = get_post_meta($post_id, '_auc_budget', true);
                echo $b ? '¥' . number_format((int)$b) : '—';
                break;
            case 'auc_status':
                $s = get_post_meta($post_id, '_auc_status', true) ?: 'inquiry';
                echo '<span class="auc-status auc-status--' . esc_attr($s) . '">' . esc_html($statuses[$s] ?? $s) . '</span>';
                break;
        }
    }

    public function order_sortable_columns($columns) {
        $columns['auc_status'] = 'auc_status';
        return $columns;
    }

    // ========== Ultimate Member 連携 ==========

    public function on_um_registration($user_id, $args) {
        if (!empty($args['auc_phone'])) update_user_meta($user_id, 'auc_phone', sanitize_text_field($args['auc_phone']));
        if (!empty($args['auc_wish_vehicle'])) update_user_meta($user_id, 'auc_wish_vehicle', sanitize_text_field($args['auc_wish_vehicle']));
        if (!empty($args['auc_wish_budget'])) update_user_meta($user_id, 'auc_wish_budget', sanitize_text_field($args['auc_wish_budget']));
        if (!empty($args['auc_wish_area'])) update_user_meta($user_id, 'auc_wish_area', sanitize_text_field($args['auc_wish_area']));
    }

    // ========== ヘルパー ==========

    public function get_status_list() {
        return [
            'inquiry'   => '問い合わせ',
            'ordered'   => 'オーダー受付',
            'proposing' => '車両提案中',
            'bidding'   => '入札中',
            'won'       => '落札済み',
            'payment'   => 'お支払い待ち',
            'repair'    => '整備中',
            'shipping'  => '陸送中',
            'delivered' => '納車済み',
            'cancelled' => 'キャンセル',
        ];
    }

    private function get_order_fields($post_id) {
        return [
            'status'      => get_post_meta($post_id, '_auc_status', true) ?: 'inquiry',
            'customer_id' => get_post_meta($post_id, '_auc_customer_id', true),
            'vehicle'     => get_post_meta($post_id, '_auc_vehicle', true),
            'class'       => get_post_meta($post_id, '_auc_class', true),
            'budget'      => get_post_meta($post_id, '_auc_budget', true),
            'area'        => get_post_meta($post_id, '_auc_area', true),
            'bid_price'   => get_post_meta($post_id, '_auc_bid_price', true),
            'agent_fee'   => get_post_meta($post_id, '_auc_agent_fee', true),
            'shipping'    => get_post_meta($post_id, '_auc_shipping', true),
            'total'       => get_post_meta($post_id, '_auc_total', true),
        ];
    }
}

AUC_Agent_CRM::get_instance();
