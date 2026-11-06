<?php
/**
 * Plugin Name: AUC-AGENT 顧客・オーダー管理
 * Description: 独自DB顧客管理・オーダー管理・車両提案・対応履歴システム
 * Version: 2.0.0
 * Author: 合同会社アイズ
 * Text Domain: auc-agent-crm
 */

if (!defined('ABSPATH')) exit;

define('AUC_CRM_VERSION', '2.0.0');
define('AUC_CRM_PATH', plugin_dir_path(__FILE__));
define('AUC_CRM_URL', plugin_dir_url(__FILE__));

register_activation_hook(__FILE__, ['AUC_Agent_CRM', 'activate']);

class AUC_Agent_CRM {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('admin_init', [$this, 'handle_form']);
    }

    // ========== テーブル作成 ==========

    public static function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = "
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}auc_customers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL DEFAULT '',
            name_kana VARCHAR(100) DEFAULT '',
            email VARCHAR(200) DEFAULT '',
            phone VARCHAR(30) DEFAULT '',
            postal VARCHAR(10) DEFAULT '',
            address TEXT,
            wish_vehicle VARCHAR(200) DEFAULT '',
            wish_class ENUM('','kei','compact','sedan','suv','import') DEFAULT '',
            wish_budget BIGINT DEFAULT 0,
            wish_area VARCHAR(50) DEFAULT '',
            source VARCHAR(50) DEFAULT '',
            memo TEXT,
            status ENUM('active','inactive','blocked') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_email (email)
        ) $charset;

        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}auc_orders (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            customer_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(200) NOT NULL DEFAULT '',
            vehicle VARCHAR(200) DEFAULT '',
            vehicle_class ENUM('','kei','compact','sedan','suv','import') DEFAULT '',
            year VARCHAR(10) DEFAULT '',
            budget_max BIGINT DEFAULT 0,
            delivery_area VARCHAR(50) DEFAULT '',
            status VARCHAR(30) DEFAULT 'inquiry',
            bid_price BIGINT DEFAULT 0,
            agent_fee BIGINT DEFAULT 0,
            auction_fee BIGINT DEFAULT 0,
            recycle_fee BIGINT DEFAULT 0,
            shipping_fee BIGINT DEFAULT 0,
            option_fee BIGINT DEFAULT 0,
            total BIGINT DEFAULT 0,
            payment_method VARCHAR(50) DEFAULT '',
            memo TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_customer (customer_id),
            INDEX idx_status (status)
        ) $charset;

        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}auc_proposals (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id BIGINT UNSIGNED NOT NULL,
            vehicle_name VARCHAR(200) DEFAULT '',
            auction_price BIGINT DEFAULT 0,
            est_total BIGINT DEFAULT 0,
            eval_score VARCHAR(10) DEFAULT '',
            mileage VARCHAR(30) DEFAULT '',
            condition_note TEXT,
            status ENUM('pending','proposed','approved','rejected','won') DEFAULT 'pending',
            memo TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order (order_id)
        ) $charset;

        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}auc_logs (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            target_type ENUM('customer','order','proposal') NOT NULL,
            target_id BIGINT UNSIGNED NOT NULL,
            action VARCHAR(50) DEFAULT '',
            content TEXT,
            staff VARCHAR(100) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_target (target_type, target_id)
        ) $charset;
        ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        update_option('auc_crm_version', AUC_CRM_VERSION);
    }

    // ========== メニュー ==========

    public function admin_menu() {
        add_menu_page('AUC-AGENT', 'AUC管理', 'manage_options', 'auc-dashboard', [$this, 'page_dashboard'], 'dashicons-car', 3);
        add_submenu_page('auc-dashboard', 'ダッシュボード', 'ダッシュボード', 'manage_options', 'auc-dashboard');
        add_submenu_page('auc-dashboard', '顧客管理', '顧客管理', 'manage_options', 'auc-customers', [$this, 'page_customers']);
        add_submenu_page('auc-dashboard', 'オーダー管理', 'オーダー管理', 'manage_options', 'auc-orders', [$this, 'page_orders']);
        add_submenu_page('auc-dashboard', '車両提案', '車両提案', 'manage_options', 'auc-proposals', [$this, 'page_proposals']);
        add_submenu_page('auc-dashboard', '対応履歴', '対応履歴', 'manage_options', 'auc-logs', [$this, 'page_logs']);
    }

    public function admin_assets($hook) {
        if (strpos($hook, 'auc-') === false) return;
        wp_enqueue_style('auc-crm', AUC_CRM_URL . 'assets/admin.css', [], AUC_CRM_VERSION);
    }

    // ========== ヘルパー ==========

    private function db() { global $wpdb; return $wpdb; }
    private function t($name) { return $this->db()->prefix . 'auc_' . $name; }

    public static function statuses() {
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

    private static function classes() {
        return ['' => '—', 'kei' => '軽自動車', 'compact' => 'コンパクト', 'sedan' => 'セダン/ミニバン', 'suv' => 'SUV/大型', 'import' => '輸入車'];
    }

    private function yen($n) { return $n ? '¥' . number_format((int)$n) : '—'; }

    private function add_log($type, $id, $action, $content = '') {
        $user = wp_get_current_user();
        $this->db()->insert($this->t('logs'), [
            'target_type' => $type, 'target_id' => $id, 'action' => $action,
            'content' => $content, 'staff' => $user->display_name,
        ]);
    }

    // ========== フォーム処理 ==========

    public function handle_form() {
        if (!current_user_can('manage_options')) return;
        if (empty($_POST['auc_action'])) return;
        if (!wp_verify_nonce($_POST['_auc_nonce'] ?? '', 'auc_crm_action')) return;

        $action = sanitize_text_field($_POST['auc_action']);
        $redirect = admin_url('admin.php?page=auc-dashboard');

        switch ($action) {
            case 'save_customer':
                $data = $this->sanitize_customer($_POST);
                $id = intval($_POST['customer_id'] ?? 0);
                if ($id > 0) {
                    $this->db()->update($this->t('customers'), $data, ['id' => $id]);
                    $this->add_log('customer', $id, 'update', '顧客情報を更新');
                    $redirect = admin_url('admin.php?page=auc-customers&action=edit&id=' . $id . '&msg=updated');
                } else {
                    $this->db()->insert($this->t('customers'), $data);
                    $id = $this->db()->insert_id;
                    $this->add_log('customer', $id, 'create', '新規顧客を登録');
                    $redirect = admin_url('admin.php?page=auc-customers&msg=created');
                }
                break;

            case 'save_order':
                $data = $this->sanitize_order($_POST);
                $id = intval($_POST['order_id'] ?? 0);
                if ($id > 0) {
                    $old = $this->db()->get_row($this->db()->prepare("SELECT status FROM {$this->t('orders')} WHERE id=%d", $id));
                    $this->db()->update($this->t('orders'), $data, ['id' => $id]);
                    if ($old && $old->status !== $data['status']) {
                        $s = self::statuses();
                        $this->add_log('order', $id, 'status_change', ($s[$old->status] ?? $old->status) . ' → ' . ($s[$data['status']] ?? $data['status']));
                    }
                    $redirect = admin_url('admin.php?page=auc-orders&action=edit&id=' . $id . '&msg=updated');
                } else {
                    $this->db()->insert($this->t('orders'), $data);
                    $id = $this->db()->insert_id;
                    $this->add_log('order', $id, 'create', '新規オーダー: ' . $data['title']);
                    $redirect = admin_url('admin.php?page=auc-orders&msg=created');
                }
                break;

            case 'save_proposal':
                $data = $this->sanitize_proposal($_POST);
                $id = intval($_POST['proposal_id'] ?? 0);
                if ($id > 0) {
                    $this->db()->update($this->t('proposals'), $data, ['id' => $id]);
                    $this->add_log('proposal', $id, 'update', '提案を更新');
                    $redirect = admin_url('admin.php?page=auc-proposals&action=edit&id=' . $id . '&msg=updated');
                } else {
                    $this->db()->insert($this->t('proposals'), $data);
                    $id = $this->db()->insert_id;
                    $this->add_log('proposal', $id, 'create', '新規提案: ' . $data['vehicle_name']);
                    $redirect = admin_url('admin.php?page=auc-proposals&msg=created');
                }
                break;

            case 'add_log':
                $this->add_log(
                    sanitize_text_field($_POST['log_type']),
                    intval($_POST['log_target_id']),
                    'memo',
                    sanitize_textarea_field($_POST['log_content'])
                );
                $redirect = wp_get_referer() ?: admin_url('admin.php?page=auc-logs');
                break;

            case 'delete_customer':
                $id = intval($_POST['delete_id']);
                $this->db()->delete($this->t('customers'), ['id' => $id]);
                $this->db()->delete($this->t('logs'), ['target_type' => 'customer', 'target_id' => $id]);
                $redirect = admin_url('admin.php?page=auc-customers&msg=deleted');
                break;

            case 'delete_order':
                $id = intval($_POST['delete_id']);
                $this->db()->delete($this->t('orders'), ['id' => $id]);
                $this->db()->delete($this->t('logs'), ['target_type' => 'order', 'target_id' => $id]);
                $redirect = admin_url('admin.php?page=auc-orders&msg=deleted');
                break;

            default:
                return;
        }

        wp_safe_redirect($redirect);
        exit;
    }

    private function sanitize_customer($p) {
        return [
            'name'         => sanitize_text_field($p['name'] ?? ''),
            'name_kana'    => sanitize_text_field($p['name_kana'] ?? ''),
            'email'        => sanitize_email($p['email'] ?? ''),
            'phone'        => sanitize_text_field($p['phone'] ?? ''),
            'postal'       => sanitize_text_field($p['postal'] ?? ''),
            'address'      => sanitize_textarea_field($p['address'] ?? ''),
            'wish_vehicle' => sanitize_text_field($p['wish_vehicle'] ?? ''),
            'wish_class'   => sanitize_text_field($p['wish_class'] ?? ''),
            'wish_budget'  => intval($p['wish_budget'] ?? 0),
            'wish_area'    => sanitize_text_field($p['wish_area'] ?? ''),
            'source'       => sanitize_text_field($p['source'] ?? ''),
            'memo'         => sanitize_textarea_field($p['memo'] ?? ''),
            'status'       => sanitize_text_field($p['cust_status'] ?? 'active'),
        ];
    }

    private function sanitize_order($p) {
        return [
            'customer_id'    => intval($p['customer_id'] ?? 0),
            'title'          => sanitize_text_field($p['title'] ?? ''),
            'vehicle'        => sanitize_text_field($p['vehicle'] ?? ''),
            'vehicle_class'  => sanitize_text_field($p['vehicle_class'] ?? ''),
            'year'           => sanitize_text_field($p['year'] ?? ''),
            'budget_max'     => intval($p['budget_max'] ?? 0),
            'delivery_area'  => sanitize_text_field($p['delivery_area'] ?? ''),
            'status'         => sanitize_text_field($p['status'] ?? 'inquiry'),
            'bid_price'      => intval($p['bid_price'] ?? 0),
            'agent_fee'      => intval($p['agent_fee'] ?? 0),
            'auction_fee'    => intval($p['auction_fee'] ?? 0),
            'recycle_fee'    => intval($p['recycle_fee'] ?? 0),
            'shipping_fee'   => intval($p['shipping_fee'] ?? 0),
            'option_fee'     => intval($p['option_fee'] ?? 0),
            'total'          => intval($p['total'] ?? 0),
            'payment_method' => sanitize_text_field($p['payment_method'] ?? ''),
            'memo'           => sanitize_textarea_field($p['memo'] ?? ''),
        ];
    }

    private function sanitize_proposal($p) {
        return [
            'order_id'       => intval($p['order_id'] ?? 0),
            'vehicle_name'   => sanitize_text_field($p['vehicle_name'] ?? ''),
            'auction_price'  => intval($p['auction_price'] ?? 0),
            'est_total'      => intval($p['est_total'] ?? 0),
            'eval_score'     => sanitize_text_field($p['eval_score'] ?? ''),
            'mileage'        => sanitize_text_field($p['mileage'] ?? ''),
            'condition_note' => sanitize_textarea_field($p['condition_note'] ?? ''),
            'status'         => sanitize_text_field($p['prop_status'] ?? 'pending'),
            'memo'           => sanitize_textarea_field($p['prop_memo'] ?? ''),
        ];
    }

    // ========== ダッシュボード ==========

    public function page_dashboard() {
        $db = $this->db();
        $cust_count = (int) $db->get_var("SELECT COUNT(*) FROM {$this->t('customers')} WHERE status='active'");
        $order_count = (int) $db->get_var("SELECT COUNT(*) FROM {$this->t('orders')}");
        $statuses = self::statuses();
        $status_counts = [];
        foreach ($statuses as $k => $v) {
            $c = (int) $db->get_var($db->prepare("SELECT COUNT(*) FROM {$this->t('orders')} WHERE status=%s", $k));
            if ($c > 0) $status_counts[$k] = $c;
        }
        $recent_orders = $db->get_results("SELECT o.*, c.name AS cust_name FROM {$this->t('orders')} o LEFT JOIN {$this->t('customers')} c ON o.customer_id=c.id ORDER BY o.updated_at DESC LIMIT 10");
        $recent_logs = $db->get_results("SELECT * FROM {$this->t('logs')} ORDER BY created_at DESC LIMIT 10");
        ?>
        <div class="wrap auc-wrap">
            <h1>AUC-AGENT ダッシュボード</h1>
            <div class="auc-cards">
                <div class="auc-card auc-card--navy">
                    <div class="auc-card__num"><?php echo $cust_count; ?></div>
                    <div class="auc-card__label">顧客数</div>
                </div>
                <div class="auc-card auc-card--gold">
                    <div class="auc-card__num"><?php echo $order_count; ?></div>
                    <div class="auc-card__label">オーダー総数</div>
                </div>
                <?php foreach ($status_counts as $k => $c): ?>
                <div class="auc-card">
                    <div class="auc-card__num"><?php echo $c; ?></div>
                    <div class="auc-card__label"><?php echo esc_html($statuses[$k]); ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="auc-grid-2">
                <div>
                    <h2>最近のオーダー</h2>
                    <?php if ($recent_orders): ?>
                    <table class="widefat auc-table">
                        <thead><tr><th>オーダー</th><th>顧客</th><th>車種</th><th>ステータス</th><th>更新日</th></tr></thead>
                        <tbody>
                        <?php foreach ($recent_orders as $o): ?>
                        <tr>
                            <td><a href="<?php echo admin_url('admin.php?page=auc-orders&action=edit&id=' . $o->id); ?>"><?php echo esc_html($o->title); ?></a></td>
                            <td><?php echo esc_html($o->cust_name ?: '—'); ?></td>
                            <td><?php echo esc_html($o->vehicle ?: '—'); ?></td>
                            <td><span class="auc-badge auc-badge--<?php echo esc_attr($o->status); ?>"><?php echo esc_html($statuses[$o->status] ?? $o->status); ?></span></td>
                            <td><?php echo date('m/d H:i', strtotime($o->updated_at)); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?><p>まだオーダーがありません。</p><?php endif; ?>
                </div>
                <div>
                    <h2>対応履歴</h2>
                    <?php if ($recent_logs): ?>
                    <div class="auc-timeline">
                        <?php foreach ($recent_logs as $l): ?>
                        <div class="auc-tl-item">
                            <div class="auc-tl-time"><?php echo date('m/d H:i', strtotime($l->created_at)); ?></div>
                            <div class="auc-tl-body">
                                <span class="auc-tl-tag"><?php echo esc_html($l->target_type); ?>#<?php echo $l->target_id; ?></span>
                                <span class="auc-tl-action"><?php echo esc_html($l->action); ?></span>
                                <?php if ($l->content): ?><br><span class="auc-tl-content"><?php echo esc_html($l->content); ?></span><?php endif; ?>
                                <?php if ($l->staff): ?><span class="auc-tl-staff">by <?php echo esc_html($l->staff); ?></span><?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?><p>まだ履歴がありません。</p><?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    // ========== 顧客管理 ==========

    public function page_customers() {
        $action = $_GET['action'] ?? 'list';
        if ($action === 'new' || $action === 'edit') {
            $this->page_customer_form();
        } else {
            $this->page_customer_list();
        }
    }

    private function page_customer_list() {
        $db = $this->db();
        $search = sanitize_text_field($_GET['s'] ?? '');
        $where = "1=1";
        if ($search) $where .= $db->prepare(" AND (name LIKE %s OR email LIKE %s OR phone LIKE %s)", "%$search%", "%$search%", "%$search%");
        $rows = $db->get_results("SELECT c.*, (SELECT COUNT(*) FROM {$this->t('orders')} WHERE customer_id=c.id) AS order_count FROM {$this->t('customers')} c WHERE $where ORDER BY c.created_at DESC");
        $msg = $_GET['msg'] ?? '';
        ?>
        <div class="wrap auc-wrap">
            <h1>顧客管理 <a href="<?php echo admin_url('admin.php?page=auc-customers&action=new'); ?>" class="page-title-action">新規顧客</a></h1>
            <?php if ($msg): ?><div class="notice notice-success is-dismissible"><p><?php echo $msg === 'created' ? '顧客を登録しました。' : ($msg === 'updated' ? '更新しました。' : '削除しました。'); ?></p></div><?php endif; ?>
            <form method="get" class="auc-search-bar">
                <input type="hidden" name="page" value="auc-customers">
                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="名前・メール・電話で検索" class="regular-text">
                <button type="submit" class="button">検索</button>
            </form>
            <table class="widefat auc-table">
                <thead><tr><th>名前</th><th>メール</th><th>電話</th><th>希望車種</th><th>予算</th><th>エリア</th><th>オーダー</th><th>流入元</th><th>登録日</th></tr></thead>
                <tbody>
                <?php if ($rows): foreach ($rows as $r): ?>
                <tr>
                    <td><a href="<?php echo admin_url('admin.php?page=auc-customers&action=edit&id=' . $r->id); ?>"><strong><?php echo esc_html($r->name); ?></strong></a>
                        <?php if ($r->status !== 'active'): ?><span class="auc-badge auc-badge--cancelled"><?php echo $r->status; ?></span><?php endif; ?>
                    </td>
                    <td><?php echo esc_html($r->email ?: '—'); ?></td>
                    <td><?php echo esc_html($r->phone ?: '—'); ?></td>
                    <td><?php echo esc_html($r->wish_vehicle ?: '—'); ?></td>
                    <td><?php echo $this->yen($r->wish_budget); ?></td>
                    <td><?php echo esc_html($r->wish_area ?: '—'); ?></td>
                    <td><?php echo $r->order_count; ?>件</td>
                    <td><?php echo esc_html($r->source ?: '—'); ?></td>
                    <td><?php echo date('Y/m/d', strtotime($r->created_at)); ?></td>
                </tr>
                <?php endforeach; else: ?><tr><td colspan="9">顧客がありません。</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function page_customer_form() {
        $id = intval($_GET['id'] ?? 0);
        $row = $id ? $this->db()->get_row($this->db()->prepare("SELECT * FROM {$this->t('customers')} WHERE id=%d", $id)) : null;
        $is_edit = (bool) $row;
        $classes = self::classes();
        $logs = $id ? $this->db()->get_results($this->db()->prepare("SELECT * FROM {$this->t('logs')} WHERE target_type='customer' AND target_id=%d ORDER BY created_at DESC LIMIT 30", $id)) : [];
        $orders = $id ? $this->db()->get_results($this->db()->prepare("SELECT * FROM {$this->t('orders')} WHERE customer_id=%d ORDER BY created_at DESC", $id)) : [];
        ?>
        <div class="wrap auc-wrap">
            <h1><?php echo $is_edit ? '顧客を編集' : '新規顧客を登録'; ?></h1>
            <form method="post" action="<?php echo admin_url('admin.php'); ?>">
                <?php wp_nonce_field('auc_crm_action', '_auc_nonce'); ?>
                <input type="hidden" name="auc_action" value="save_customer">
                <input type="hidden" name="customer_id" value="<?php echo $id; ?>">
                <div class="auc-form-grid">
                    <div class="auc-form-section">
                        <h3>基本情報</h3>
                        <div class="auc-meta-grid">
                            <div class="auc-f"><label>氏名 *</label><input type="text" name="name" value="<?php echo esc_attr($row->name ?? ''); ?>" required></div>
                            <div class="auc-f"><label>フリガナ</label><input type="text" name="name_kana" value="<?php echo esc_attr($row->name_kana ?? ''); ?>"></div>
                            <div class="auc-f"><label>メールアドレス</label><input type="email" name="email" value="<?php echo esc_attr($row->email ?? ''); ?>"></div>
                            <div class="auc-f"><label>電話番号</label><input type="tel" name="phone" value="<?php echo esc_attr($row->phone ?? ''); ?>"></div>
                            <div class="auc-f"><label>郵便番号</label><input type="text" name="postal" value="<?php echo esc_attr($row->postal ?? ''); ?>"></div>
                            <div class="auc-f" style="grid-column:1/-1"><label>住所</label><input type="text" name="address" value="<?php echo esc_attr($row->address ?? ''); ?>"></div>
                        </div>
                    </div>
                    <div class="auc-form-section">
                        <h3>ご希望</h3>
                        <div class="auc-meta-grid">
                            <div class="auc-f"><label>希望車種</label><input type="text" name="wish_vehicle" value="<?php echo esc_attr($row->wish_vehicle ?? ''); ?>" placeholder="例：アルファード"></div>
                            <div class="auc-f"><label>車両クラス</label>
                                <select name="wish_class"><?php foreach ($classes as $k => $v): ?><option value="<?php echo $k; ?>" <?php selected($row->wish_class ?? '', $k); ?>><?php echo $v; ?></option><?php endforeach; ?></select>
                            </div>
                            <div class="auc-f"><label>予算上限</label><input type="number" name="wish_budget" value="<?php echo esc_attr($row->wish_budget ?? ''); ?>" step="10000"></div>
                            <div class="auc-f"><label>納車エリア</label><input type="text" name="wish_area" value="<?php echo esc_attr($row->wish_area ?? ''); ?>" placeholder="例：東京都"></div>
                        </div>
                    </div>
                    <div class="auc-form-section">
                        <h3>管理</h3>
                        <div class="auc-meta-grid">
                            <div class="auc-f"><label>流入元</label>
                                <select name="source">
                                    <option value="">—</option>
                                    <?php foreach (['サイト','電話','紹介','SNS','広告','その他'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php selected($row->source ?? '', $s); ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="auc-f"><label>ステータス</label>
                                <select name="cust_status">
                                    <option value="active" <?php selected($row->status ?? 'active', 'active'); ?>>有効</option>
                                    <option value="inactive" <?php selected($row->status ?? '', 'inactive'); ?>>非アクティブ</option>
                                    <option value="blocked" <?php selected($row->status ?? '', 'blocked'); ?>>ブロック</option>
                                </select>
                            </div>
                            <div class="auc-f" style="grid-column:1/-1"><label>メモ</label><textarea name="memo" rows="3"><?php echo esc_textarea($row->memo ?? ''); ?></textarea></div>
                        </div>
                    </div>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">保存する</button>
                    <a href="<?php echo admin_url('admin.php?page=auc-customers'); ?>" class="button button-large">戻る</a>
                    <?php if ($is_edit): ?>
                    <span style="float:right">
                        <button type="submit" name="auc_action" value="delete_customer" class="button button-link-delete" onclick="return confirm('本当に削除しますか？')">
                            <input type="hidden" name="delete_id" value="<?php echo $id; ?>">この顧客を削除
                        </button>
                    </span>
                    <?php endif; ?>
                </p>
            </form>

            <?php if ($is_edit && $orders): ?>
            <h2>この顧客のオーダー</h2>
            <table class="widefat auc-table">
                <thead><tr><th>オーダー</th><th>車種</th><th>予算</th><th>ステータス</th><th>作成日</th></tr></thead>
                <tbody>
                <?php $sts = self::statuses(); foreach ($orders as $o): ?>
                <tr>
                    <td><a href="<?php echo admin_url('admin.php?page=auc-orders&action=edit&id=' . $o->id); ?>"><?php echo esc_html($o->title); ?></a></td>
                    <td><?php echo esc_html($o->vehicle ?: '—'); ?></td>
                    <td><?php echo $this->yen($o->budget_max); ?></td>
                    <td><span class="auc-badge auc-badge--<?php echo esc_attr($o->status); ?>"><?php echo esc_html($sts[$o->status] ?? $o->status); ?></span></td>
                    <td><?php echo date('Y/m/d', strtotime($o->created_at)); ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if ($is_edit): ?>
            <h2>対応履歴</h2>
            <form method="post" action="<?php echo admin_url('admin.php'); ?>" class="auc-log-form">
                <?php wp_nonce_field('auc_crm_action', '_auc_nonce'); ?>
                <input type="hidden" name="auc_action" value="add_log">
                <input type="hidden" name="log_type" value="customer">
                <input type="hidden" name="log_target_id" value="<?php echo $id; ?>">
                <textarea name="log_content" rows="2" placeholder="対応内容を入力（例：電話でヒアリング。アルファード希望、予算150万）" required></textarea>
                <button type="submit" class="button">メモを追加</button>
            </form>
            <?php if ($logs): ?>
            <div class="auc-timeline">
                <?php foreach ($logs as $l): ?>
                <div class="auc-tl-item">
                    <div class="auc-tl-time"><?php echo date('Y/m/d H:i', strtotime($l->created_at)); ?></div>
                    <div class="auc-tl-body">
                        <span class="auc-tl-action"><?php echo esc_html($l->action); ?></span>
                        <?php if ($l->content): ?><span class="auc-tl-content"><?php echo esc_html($l->content); ?></span><?php endif; ?>
                        <?php if ($l->staff): ?><span class="auc-tl-staff">by <?php echo esc_html($l->staff); ?></span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; endif; ?>
        </div>
        <?php
    }

    // ========== オーダー管理 ==========

    public function page_orders() {
        $action = $_GET['action'] ?? 'list';
        if ($action === 'new' || $action === 'edit') {
            $this->page_order_form();
        } else {
            $this->page_order_list();
        }
    }

    private function page_order_list() {
        $db = $this->db();
        $filter = sanitize_text_field($_GET['status'] ?? '');
        $where = "1=1";
        if ($filter) $where .= $db->prepare(" AND o.status=%s", $filter);
        $rows = $db->get_results("SELECT o.*, c.name AS cust_name FROM {$this->t('orders')} o LEFT JOIN {$this->t('customers')} c ON o.customer_id=c.id WHERE $where ORDER BY o.updated_at DESC");
        $statuses = self::statuses();
        $msg = $_GET['msg'] ?? '';
        ?>
        <div class="wrap auc-wrap">
            <h1>オーダー管理 <a href="<?php echo admin_url('admin.php?page=auc-orders&action=new'); ?>" class="page-title-action">新規オーダー</a></h1>
            <?php if ($msg): ?><div class="notice notice-success is-dismissible"><p>完了しました。</p></div><?php endif; ?>
            <div class="auc-filter-bar">
                <a href="<?php echo admin_url('admin.php?page=auc-orders'); ?>" class="button <?php echo !$filter ? 'button-primary' : ''; ?>">すべて</a>
                <?php foreach ($statuses as $k => $v): ?>
                <a href="<?php echo admin_url('admin.php?page=auc-orders&status=' . $k); ?>" class="button <?php echo $filter === $k ? 'button-primary' : ''; ?>"><?php echo $v; ?></a>
                <?php endforeach; ?>
            </div>
            <table class="widefat auc-table">
                <thead><tr><th>ID</th><th>オーダー名</th><th>顧客</th><th>車種</th><th>予算</th><th>落札価格</th><th>総額</th><th>ステータス</th><th>更新日</th></tr></thead>
                <tbody>
                <?php if ($rows): foreach ($rows as $o): ?>
                <tr>
                    <td><?php echo $o->id; ?></td>
                    <td><a href="<?php echo admin_url('admin.php?page=auc-orders&action=edit&id=' . $o->id); ?>"><strong><?php echo esc_html($o->title); ?></strong></a></td>
                    <td><?php echo esc_html($o->cust_name ?: '—'); ?></td>
                    <td><?php echo esc_html($o->vehicle ?: '—'); ?></td>
                    <td><?php echo $this->yen($o->budget_max); ?></td>
                    <td><?php echo $this->yen($o->bid_price); ?></td>
                    <td><?php echo $this->yen($o->total); ?></td>
                    <td><span class="auc-badge auc-badge--<?php echo esc_attr($o->status); ?>"><?php echo esc_html($statuses[$o->status] ?? $o->status); ?></span></td>
                    <td><?php echo date('m/d H:i', strtotime($o->updated_at)); ?></td>
                </tr>
                <?php endforeach; else: ?><tr><td colspan="9">オーダーがありません。</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    private function page_order_form() {
        $id = intval($_GET['id'] ?? 0);
        $row = $id ? $this->db()->get_row($this->db()->prepare("SELECT * FROM {$this->t('orders')} WHERE id=%d", $id)) : null;
        $is_edit = (bool) $row;
        $statuses = self::statuses();
        $classes = self::classes();
        $customers = $this->db()->get_results("SELECT id, name, email FROM {$this->t('customers')} WHERE status='active' ORDER BY name");
        $proposals = $id ? $this->db()->get_results($this->db()->prepare("SELECT * FROM {$this->t('proposals')} WHERE order_id=%d ORDER BY created_at DESC", $id)) : [];
        $logs = $id ? $this->db()->get_results($this->db()->prepare("SELECT * FROM {$this->t('logs')} WHERE target_type='order' AND target_id=%d ORDER BY created_at DESC LIMIT 30", $id)) : [];
        ?>
        <div class="wrap auc-wrap">
            <h1><?php echo $is_edit ? 'オーダーを編集 #' . $id : '新規オーダー'; ?></h1>
            <form method="post" action="<?php echo admin_url('admin.php'); ?>">
                <?php wp_nonce_field('auc_crm_action', '_auc_nonce'); ?>
                <input type="hidden" name="auc_action" value="save_order">
                <input type="hidden" name="order_id" value="<?php echo $id; ?>">
                <div class="auc-form-grid">
                    <div class="auc-form-section">
                        <h3>基本情報</h3>
                        <div class="auc-meta-grid">
                            <div class="auc-f"><label>オーダー名 *</label><input type="text" name="title" value="<?php echo esc_attr($row->title ?? ''); ?>" required placeholder="例：田中様 アルファード"></div>
                            <div class="auc-f"><label>顧客</label>
                                <select name="customer_id"><option value="0">— 選択 —</option>
                                <?php foreach ($customers as $c): ?><option value="<?php echo $c->id; ?>" <?php selected($row->customer_id ?? 0, $c->id); ?>><?php echo esc_html($c->name . ' (' . $c->email . ')'); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="auc-f"><label>ステータス</label>
                                <select name="status"><?php foreach ($statuses as $k => $v): ?><option value="<?php echo $k; ?>" <?php selected($row->status ?? 'inquiry', $k); ?>><?php echo $v; ?></option><?php endforeach; ?></select>
                            </div>
                            <div class="auc-f"><label>希望車種</label><input type="text" name="vehicle" value="<?php echo esc_attr($row->vehicle ?? ''); ?>"></div>
                            <div class="auc-f"><label>車両クラス</label>
                                <select name="vehicle_class"><?php foreach ($classes as $k => $v): ?><option value="<?php echo $k; ?>" <?php selected($row->vehicle_class ?? '', $k); ?>><?php echo $v; ?></option><?php endforeach; ?></select>
                            </div>
                            <div class="auc-f"><label>年式</label><input type="text" name="year" value="<?php echo esc_attr($row->year ?? ''); ?>" placeholder="例：2022"></div>
                            <div class="auc-f"><label>予算上限</label><input type="number" name="budget_max" value="<?php echo esc_attr($row->budget_max ?? ''); ?>" step="10000"></div>
                            <div class="auc-f"><label>納車先</label><input type="text" name="delivery_area" value="<?php echo esc_attr($row->delivery_area ?? ''); ?>"></div>
                        </div>
                    </div>
                    <div class="auc-form-section">
                        <h3>金額（落札後に入力）</h3>
                        <div class="auc-meta-grid">
                            <div class="auc-f"><label>落札価格</label><input type="number" name="bid_price" value="<?php echo esc_attr($row->bid_price ?? ''); ?>" step="1000"></div>
                            <div class="auc-f"><label>代行手数料</label><input type="number" name="agent_fee" value="<?php echo esc_attr($row->agent_fee ?? ''); ?>" step="100"></div>
                            <div class="auc-f"><label>落札料</label><input type="number" name="auction_fee" value="<?php echo esc_attr($row->auction_fee ?? 11000); ?>" step="100"></div>
                            <div class="auc-f"><label>リサイクル料等</label><input type="number" name="recycle_fee" value="<?php echo esc_attr($row->recycle_fee ?? ''); ?>" step="100"></div>
                            <div class="auc-f"><label>陸送費</label><input type="number" name="shipping_fee" value="<?php echo esc_attr($row->shipping_fee ?? ''); ?>" step="1000"></div>
                            <div class="auc-f"><label>オプション合計</label><input type="number" name="option_fee" value="<?php echo esc_attr($row->option_fee ?? ''); ?>" step="100"></div>
                            <div class="auc-f"><label>総額</label><input type="number" name="total" value="<?php echo esc_attr($row->total ?? ''); ?>" step="1" style="font-weight:900;font-size:18px"></div>
                            <div class="auc-f"><label>支払方法</label>
                                <select name="payment_method">
                                    <option value="">—</option>
                                    <?php foreach (['銀行振込','オンライン決済','オートローン','現金'] as $pm): ?>
                                    <option value="<?php echo $pm; ?>" <?php selected($row->payment_method ?? '', $pm); ?>><?php echo $pm; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="auc-form-section">
                        <h3>メモ</h3>
                        <textarea name="memo" rows="4" style="width:100%"><?php echo esc_textarea($row->memo ?? ''); ?></textarea>
                    </div>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">保存する</button>
                    <a href="<?php echo admin_url('admin.php?page=auc-orders'); ?>" class="button button-large">戻る</a>
                    <?php if ($is_edit): ?>
                    <span style="float:right">
                        <button type="submit" name="auc_action" value="delete_order" class="button button-link-delete" onclick="return confirm('削除しますか？')">
                            <input type="hidden" name="delete_id" value="<?php echo $id; ?>">削除
                        </button>
                    </span>
                    <?php endif; ?>
                </p>
            </form>

            <?php if ($is_edit && $proposals): ?>
            <h2>車両提案</h2>
            <table class="widefat auc-table">
                <thead><tr><th>車両</th><th>相場価格</th><th>見積総額</th><th>評価点</th><th>走行距離</th><th>ステータス</th></tr></thead>
                <tbody>
                <?php $ps = ['pending'=>'提案待ち','proposed'=>'提案済み','approved'=>'承認','rejected'=>'見送り','won'=>'落札済み'];
                foreach ($proposals as $p): ?>
                <tr>
                    <td><a href="<?php echo admin_url('admin.php?page=auc-proposals&action=edit&id=' . $p->id); ?>"><?php echo esc_html($p->vehicle_name); ?></a></td>
                    <td><?php echo $this->yen($p->auction_price); ?></td>
                    <td><?php echo $this->yen($p->est_total); ?></td>
                    <td><?php echo esc_html($p->eval_score ?: '—'); ?></td>
                    <td><?php echo esc_html($p->mileage ?: '—'); ?></td>
                    <td><span class="auc-badge"><?php echo esc_html($ps[$p->status] ?? $p->status); ?></span></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>

            <?php if ($is_edit): ?>
            <h2>対応履歴</h2>
            <form method="post" action="<?php echo admin_url('admin.php'); ?>" class="auc-log-form">
                <?php wp_nonce_field('auc_crm_action', '_auc_nonce'); ?>
                <input type="hidden" name="auc_action" value="add_log">
                <input type="hidden" name="log_type" value="order">
                <input type="hidden" name="log_target_id" value="<?php echo $id; ?>">
                <textarea name="log_content" rows="2" placeholder="対応内容を入力" required></textarea>
                <button type="submit" class="button">メモを追加</button>
            </form>
            <?php if ($logs): ?>
            <div class="auc-timeline">
                <?php foreach ($logs as $l): ?>
                <div class="auc-tl-item">
                    <div class="auc-tl-time"><?php echo date('Y/m/d H:i', strtotime($l->created_at)); ?></div>
                    <div class="auc-tl-body">
                        <span class="auc-tl-action"><?php echo esc_html($l->action); ?></span>
                        <?php if ($l->content): ?><span class="auc-tl-content"><?php echo esc_html($l->content); ?></span><?php endif; ?>
                        <?php if ($l->staff): ?><span class="auc-tl-staff">by <?php echo esc_html($l->staff); ?></span><?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; endif; ?>
        </div>
        <?php
    }

    // ========== 車両提案 ==========

    public function page_proposals() {
        $action = $_GET['action'] ?? 'list';
        if ($action === 'new' || $action === 'edit') {
            $this->page_proposal_form();
        } else {
            $db = $this->db();
            $rows = $db->get_results("SELECT p.*, o.title AS order_title FROM {$this->t('proposals')} p LEFT JOIN {$this->t('orders')} o ON p.order_id=o.id ORDER BY p.created_at DESC");
            $ps = ['pending'=>'提案待ち','proposed'=>'提案済み','approved'=>'承認','rejected'=>'見送り','won'=>'落札済み'];
            ?>
            <div class="wrap auc-wrap">
                <h1>車両提案 <a href="<?php echo admin_url('admin.php?page=auc-proposals&action=new'); ?>" class="page-title-action">新規提案</a></h1>
                <table class="widefat auc-table">
                    <thead><tr><th>車両</th><th>オーダー</th><th>相場</th><th>見積総額</th><th>評価点</th><th>走行</th><th>ステータス</th><th>日付</th></tr></thead>
                    <tbody>
                    <?php if ($rows): foreach ($rows as $p): ?>
                    <tr>
                        <td><a href="<?php echo admin_url('admin.php?page=auc-proposals&action=edit&id=' . $p->id); ?>"><?php echo esc_html($p->vehicle_name); ?></a></td>
                        <td><?php echo esc_html($p->order_title ?: '—'); ?></td>
                        <td><?php echo $this->yen($p->auction_price); ?></td>
                        <td><?php echo $this->yen($p->est_total); ?></td>
                        <td><?php echo esc_html($p->eval_score ?: '—'); ?></td>
                        <td><?php echo esc_html($p->mileage ?: '—'); ?></td>
                        <td><span class="auc-badge"><?php echo esc_html($ps[$p->status] ?? $p->status); ?></span></td>
                        <td><?php echo date('m/d', strtotime($p->created_at)); ?></td>
                    </tr>
                    <?php endforeach; else: ?><tr><td colspan="8">提案がありません。</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php
        }
    }

    private function page_proposal_form() {
        $id = intval($_GET['id'] ?? 0);
        $row = $id ? $this->db()->get_row($this->db()->prepare("SELECT * FROM {$this->t('proposals')} WHERE id=%d", $id)) : null;
        $is_edit = (bool) $row;
        $orders = $this->db()->get_results("SELECT id, title FROM {$this->t('orders')} ORDER BY created_at DESC");
        ?>
        <div class="wrap auc-wrap">
            <h1><?php echo $is_edit ? '提案を編集' : '新規提案'; ?></h1>
            <form method="post" action="<?php echo admin_url('admin.php'); ?>">
                <?php wp_nonce_field('auc_crm_action', '_auc_nonce'); ?>
                <input type="hidden" name="auc_action" value="save_proposal">
                <input type="hidden" name="proposal_id" value="<?php echo $id; ?>">
                <div class="auc-meta-grid">
                    <div class="auc-f"><label>紐づくオーダー *</label>
                        <select name="order_id" required><option value="">— 選択 —</option>
                        <?php foreach ($orders as $o): ?><option value="<?php echo $o->id; ?>" <?php selected($row->order_id ?? 0, $o->id); ?>><?php echo esc_html($o->title); ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="auc-f"><label>車両名 *</label><input type="text" name="vehicle_name" value="<?php echo esc_attr($row->vehicle_name ?? ''); ?>" required placeholder="例：トヨタ アルファード 2.5L S 2022"></div>
                    <div class="auc-f"><label>オークション相場価格</label><input type="number" name="auction_price" value="<?php echo esc_attr($row->auction_price ?? ''); ?>" step="1000"></div>
                    <div class="auc-f"><label>乗り出し見積り総額</label><input type="number" name="est_total" value="<?php echo esc_attr($row->est_total ?? ''); ?>" step="1000"></div>
                    <div class="auc-f"><label>評価点</label><input type="text" name="eval_score" value="<?php echo esc_attr($row->eval_score ?? ''); ?>" placeholder="例：4.5"></div>
                    <div class="auc-f"><label>走行距離</label><input type="text" name="mileage" value="<?php echo esc_attr($row->mileage ?? ''); ?>" placeholder="例：50,000km"></div>
                    <div class="auc-f" style="grid-column:1/-1"><label>車両状態</label><textarea name="condition_note" rows="2"><?php echo esc_textarea($row->condition_note ?? ''); ?></textarea></div>
                    <div class="auc-f"><label>提案ステータス</label>
                        <select name="prop_status">
                            <option value="pending" <?php selected($row->status ?? 'pending', 'pending'); ?>>提案待ち</option>
                            <option value="proposed" <?php selected($row->status ?? '', 'proposed'); ?>>提案済み</option>
                            <option value="approved" <?php selected($row->status ?? '', 'approved'); ?>>承認（入札OK）</option>
                            <option value="rejected" <?php selected($row->status ?? '', 'rejected'); ?>>見送り</option>
                            <option value="won" <?php selected($row->status ?? '', 'won'); ?>>落札済み</option>
                        </select>
                    </div>
                    <div class="auc-f" style="grid-column:1/-1"><label>備考</label><textarea name="prop_memo" rows="2"><?php echo esc_textarea($row->memo ?? ''); ?></textarea></div>
                </div>
                <p class="submit">
                    <button type="submit" class="button button-primary button-large">保存する</button>
                    <a href="<?php echo admin_url('admin.php?page=auc-proposals'); ?>" class="button button-large">戻る</a>
                </p>
            </form>
        </div>
        <?php
    }

    // ========== 対応履歴 ==========

    public function page_logs() {
        $logs = $this->db()->get_results("SELECT * FROM {$this->t('logs')} ORDER BY created_at DESC LIMIT 100");
        ?>
        <div class="wrap auc-wrap">
            <h1>対応履歴</h1>
            <table class="widefat auc-table">
                <thead><tr><th>日時</th><th>対象</th><th>アクション</th><th>内容</th><th>担当</th></tr></thead>
                <tbody>
                <?php if ($logs): foreach ($logs as $l): ?>
                <tr>
                    <td><?php echo date('Y/m/d H:i', strtotime($l->created_at)); ?></td>
                    <td><?php echo esc_html($l->target_type); ?> #<?php echo $l->target_id; ?></td>
                    <td><?php echo esc_html($l->action); ?></td>
                    <td><?php echo esc_html($l->content); ?></td>
                    <td><?php echo esc_html($l->staff); ?></td>
                </tr>
                <?php endforeach; else: ?><tr><td colspan="5">まだ履歴がありません。</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

AUC_Agent_CRM::get_instance();
