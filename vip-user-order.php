<?php
/**
 * Plugin Name: VIP User Order
 * Plugin URI: https://example.com/vip-user-order
 * Description: Display customer tags based on order count from the same phone number
 * Version: 1.0.2
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: vip-user-order
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('VIP_USER_ORDER_VERSION', '1.0.2');
define('VIP_USER_ORDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VIP_USER_ORDER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main VIP User Order Class
 */
class VIP_User_Order {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add custom column to orders list
        add_filter('manage_edit-shop_order_columns', array($this, 'add_customer_tag_column'), 20);
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_customer_tag_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'display_customer_tag_column'), 20, 2);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'display_customer_tag_column_hpos'), 20, 2);
        
        // Enqueue styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_styles'));
    }
    
    public function get_default_tags() {
        return array(
            array('min' => 1, 'max' => 1, 'label' => 'New Customer', 'color' => '#3498db'),
            array('min' => 2, 'max' => 2, 'label' => 'Repeat Customer', 'color' => '#f39c12'),
            array('min' => 3, 'max' => 5, 'label' => 'Loyal Customer', 'color' => '#27ae60'),
            array('min' => 6, 'max' => 999, 'label' => 'VIP Customer', 'color' => '#8e44ad')
        );
    }
    
    public function get_tag_settings() {
        $tags = get_option('vip_user_order_tags', $this->get_default_tags());
        return $tags;
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'VIP User Order Settings',
            'VIP User Tags',
            'manage_woocommerce',
            'vip-user-order',
            array($this, 'render_settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('vip_user_order_settings', 'vip_user_order_tags');
    }
    
    public function render_settings_page() {
        if (isset($_POST['vip_user_order_save'])) {
            check_admin_referer('vip_user_order_settings');
            
            $tags = array();
            if (isset($_POST['tag_min']) && is_array($_POST['tag_min'])) {
                foreach ($_POST['tag_min'] as $index => $min) {
                    if (!empty($min) && !empty($_POST['tag_max'][$index]) && !empty($_POST['tag_label'][$index])) {
                        $tags[] = array(
                            'min' => intval($min),
                            'max' => intval($_POST['tag_max'][$index]),
                            'label' => sanitize_text_field($_POST['tag_label'][$index]),
                            'color' => sanitize_hex_color($_POST['tag_color'][$index])
                        );
                    }
                }
            }
            
            usort($tags, function($a, $b) {
                return $a['min'] - $b['min'];
            });
            
            update_option('vip_user_order_tags', $tags);
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $tags = $this->get_tag_settings();
        ?>
        <div class="wrap">
            <h1>VIP User Order Settings</h1>
            <p>Configure customer tags based on order count. Phone format: <code>+8801XXXXXXXXX</code> or <code>01XXXXXXXXX</code></p>
            
            <form method="post" action="">
                <?php wp_nonce_field('vip_user_order_settings'); ?>
                
                <div id="vip-tags-container">
                    <?php foreach ($tags as $index => $tag): ?>
                    <div class="vip-tag-row" style="background: #fff; padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <div style="display: grid; grid-template-columns: 100px 100px 1fr 120px 80px; gap: 10px; align-items: center;">
                            <div>
                                <label>Minimum</label>
                                <input type="number" name="tag_min[]" value="<?php echo esc_attr($tag['min']); ?>" min="1" class="small-text" required>
                            </div>
                            <div>
                                <label>Maximum</label>
                                <input type="number" name="tag_max[]" value="<?php echo esc_attr($tag['max']); ?>" min="1" class="small-text" required>
                            </div>
                            <div>
                                <label>Tag Label</label>
                                <input type="text" name="tag_label[]" value="<?php echo esc_attr($tag['label']); ?>" class="regular-text" required>
                            </div>
                            <div>
                                <label>Color</label>
                                <input type="color" name="tag_color[]" value="<?php echo esc_attr($tag['color']); ?>" required>
                            </div>
                            <div>
                                <button type="button" class="button remove-tag" style="margin-top: 20px;">Remove</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <p>
                    <button type="button" id="add-tag" class="button">Add New Tag</button>
                </p>
                
                <p class="submit">
                    <button type="submit" name="vip_user_order_save" class="button button-primary">Save Settings</button>
                </p>
            </form>
            
            <hr style="margin: 40px 0;">
            
            <h2>Test Phone Number</h2>
            <p>Enter a phone number to check how many orders are found</p>
            
            <form method="get" action="" style="margin: 20px 0;">
                <input type="hidden" name="page" value="vip-user-order">
                <input type="text" name="test_phone" placeholder="e.g., +8801712345678 or 01712345678" value="<?php echo isset($_GET['test_phone']) ? esc_attr($_GET['test_phone']) : ''; ?>" style="width: 300px;">
                <button type="submit" class="button">Test Now</button>
            </form>
            
            <?php
            if (isset($_GET['test_phone']) && !empty($_GET['test_phone'])) {
                $test_phone = sanitize_text_field($_GET['test_phone']);
                $cleaned = $this->clean_phone_number($test_phone);
                $count = $this->count_orders_by_phone($test_phone);
                $tag = $this->get_tag_for_order_count($count);
                
                echo '<div style="background: #f0f0f1; padding: 20px; border-radius: 5px;">';
                echo '<h3>Test Results:</h3>';
                echo '<p><strong>Original Number:</strong> ' . esc_html($test_phone) . '</p>';
                echo '<p><strong>Cleaned Number:</strong> ' . esc_html($cleaned) . '</p>';
                echo '<p><strong>Orders Found:</strong> ' . esc_html($count) . '</p>';
                
                if ($tag) {
                    echo '<p><strong>Tag:</strong> ';
                    echo sprintf(
                        '<span class="vip-customer-tag" style="background-color: %s; color: #fff; padding: 6px 12px; border-radius: 3px; font-size: 12px; font-weight: 600; display: inline-block;">%s</span>',
                        esc_attr($tag['color']),
                        esc_html($tag['label'])
                    );
                    echo '</p>';
                } else {
                    echo '<p style="color: #d63638;"><strong>No tag found</strong></p>';
                }
                
                echo '</div>';
            }
            ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#add-tag').on('click', function() {
                var html = '<div class="vip-tag-row" style="background: #fff; padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px;">' +
                    '<div style="display: grid; grid-template-columns: 100px 100px 1fr 120px 80px; gap: 10px; align-items: center;">' +
                    '<div><label>Minimum</label><input type="number" name="tag_min[]" value="1" min="1" class="small-text" required></div>' +
                    '<div><label>Maximum</label><input type="number" name="tag_max[]" value="1" min="1" class="small-text" required></div>' +
                    '<div><label>Tag Label</label><input type="text" name="tag_label[]" value="" class="regular-text" required></div>' +
                    '<div><label>Color</label><input type="color" name="tag_color[]" value="#3498db" required></div>' +
                    '<div><button type="button" class="button remove-tag" style="margin-top: 20px;">Remove</button></div>' +
                    '</div></div>';
                $('#vip-tags-container').append(html);
            });
            
            $(document).on('click', '.remove-tag', function() {
                $(this).closest('.vip-tag-row').remove();
            });
        });
        </script>
        <?php
    }
    
    public function add_customer_tag_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_status') {
                $new_columns['customer_tag'] = 'Customer Tag';
            }
        }
        if (!isset($new_columns['customer_tag'])) {
            $actions = isset($new_columns['wc_actions']) ? $new_columns['wc_actions'] : null;
            unset($new_columns['wc_actions']);
            $new_columns['customer_tag'] = 'Customer Tag';
            if ($actions) {
                $new_columns['wc_actions'] = $actions;
            }
        }
        return $new_columns;
    }
    
    public function display_customer_tag_column($column, $post_id) {
        if ($column !== 'customer_tag') {
            return;
        }
        
        if (!function_exists('wc_get_order')) {
            echo '<span style="color: #999;">—</span>';
            return;
        }
        
        $order = wc_get_order($post_id);
        if ($order) {
            echo $this->get_customer_tag_html($order);
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
    
    public function display_customer_tag_column_hpos($column, $order) {
        if ($column !== 'customer_tag') {
            return;
        }
        
        if (!function_exists('wc_get_order')) {
            echo '<span style="color: #999;">—</span>';
            return;
        }
        
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }
        
        if ($order && is_a($order, 'WC_Order')) {
            echo $this->get_customer_tag_html($order);
        } else {
            echo '<span style="color: #999;">—</span>';
        }
    }
    
    public function get_customer_tag_html($order) {
        if (!is_object($order) || !method_exists($order, 'get_billing_phone')) {
            return '<span style="color: #999;">—</span>';
        }
        
        $billing_phone = $order->get_billing_phone();
        
        if (empty($billing_phone)) {
            return '<span style="color: #999;">—</span>';
        }
        
        $order_count = $this->count_orders_by_phone($billing_phone);
        $tag = $this->get_tag_for_order_count($order_count);
        
        if ($tag && $order_count > 0) {
            return sprintf(
                '<span class="vip-customer-tag" style="background-color: %s; color: #fff; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600; display: inline-block;">%s (%d)</span>',
                esc_attr($tag['color']),
                esc_html($tag['label']),
                $order_count
            );
        }
        
        return '<span style="color: #999;">—</span>';
    }
    
    private function clean_phone_number($phone) {
        if (empty($phone)) {
            return '';
        }
        
        // Convert Bengali numbers to English
        $bengali = array('০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯');
        $english = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $phone = str_replace($bengali, $english, $phone);
        
        // Remove non-numeric
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle Bangladesh format: +8801XXXXXXXXX (13 digits) -> 01XXXXXXXXX (11 digits)
        if (strlen($phone) === 13 && substr($phone, 0, 3) === '880') {
            $phone = '0' . substr($phone, 3);
        } elseif (strlen($phone) === 12 && substr($phone, 0, 2) === '88') {
            $phone = '0' . substr($phone, 2);
        }
        
        // Ensure 11 digits starting with 0
        if (strlen($phone) === 10) {
            $phone = '0' . $phone;
        } elseif (strlen($phone) > 11) {
            $phone = substr($phone, -11);
        }
        
        return $phone;
    }
    
    private function count_orders_by_phone($phone) {
        if (empty($phone)) {
            return 0;
        }
        
        $cleaned_target = $this->clean_phone_number($phone);
        
        if (empty($cleaned_target) || strlen($cleaned_target) < 10) {
            return 0;
        }
        
        // Check cache
        $cache_key = 'vip_order_count_' . md5($cleaned_target);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return intval($cached);
        }
        
        global $wpdb;
        
        $results = array();
        
        // Check HPOS - safer method
        $hpos_enabled = false;
        if (function_exists('wc_get_container')) {
            try {
                if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
                    if (method_exists('Automattic\WooCommerce\Utilities\OrderUtil', 'custom_orders_table_usage_is_enabled')) {
                        $hpos_enabled = Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
                    }
                }
            } catch (Exception $e) {
                $hpos_enabled = false;
            }
        }
        
        if ($hpos_enabled) {
            // HPOS query
            $results = $wpdb->get_results(
                "SELECT DISTINCT o.id, om.meta_value as phone
                FROM {$wpdb->prefix}wc_orders o
                INNER JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
                WHERE om.meta_key = '_billing_phone'
                AND o.type = 'shop_order'
                AND o.status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
                AND om.meta_value IS NOT NULL
                AND om.meta_value != ''",
                ARRAY_A
            );
        } else {
            // Classic query
            $results = $wpdb->get_results(
                "SELECT DISTINCT p.ID as id, pm.meta_value as phone
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending')
                AND pm.meta_key = '_billing_phone'
                AND pm.meta_value IS NOT NULL
                AND pm.meta_value != ''",
                ARRAY_A
            );
        }
        
        $count = 0;
        
        if (!empty($results) && is_array($results)) {
            foreach ($results as $result) {
                if (empty($result['phone'])) {
                    continue;
                }
                
                $cleaned_result = $this->clean_phone_number($result['phone']);
                
                if ($cleaned_result === $cleaned_target) {
                    $count++;
                }
            }
        }
        
        // Cache for 5 minutes
        set_transient($cache_key, $count, 300);
        
        return intval($count);
    }
    
    private function get_tag_for_order_count($count) {
        $tags = $this->get_tag_settings();
        
        foreach ($tags as $tag) {
            if ($count >= $tag['min'] && $count <= $tag['max']) {
                return $tag;
            }
        }
        
        return null;
    }
    
    public function enqueue_admin_styles($hook) {
        $order_screens = array('edit.php', 'post.php', 'post-new.php', 'woocommerce_page_wc-orders', 'shop_order');
        
        $load_assets = false;
        foreach ($order_screens as $screen) {
            if (strpos($hook, $screen) !== false) {
                $load_assets = true;
                break;
            }
        }
        
        if (isset($_GET['post']) && get_post_type($_GET['post']) === 'shop_order') {
            $load_assets = true;
        }
        
        if ($load_assets && file_exists(VIP_USER_ORDER_PLUGIN_DIR . 'assets/css/admin.css')) {
            wp_enqueue_style(
                'vip-user-order-admin',
                VIP_USER_ORDER_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                VIP_USER_ORDER_VERSION
            );
        }
    }
}

// Initialize plugin
function vip_user_order_init() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>VIP User Order</strong> requires WooCommerce to be installed and activated.</p></div>';
        });
        return;
    }
    
    if (!function_exists('wc_get_order')) {
        return;
    }
    
    try {
        VIP_User_Order::get_instance();
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('VIP User Order Error: ' . $e->getMessage());
        }
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p><strong>VIP User Order Error:</strong> ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}
add_action('plugins_loaded', 'vip_user_order_init', 20);

// Clear cache on order status change
function vip_user_order_clear_cache($order_id) {
    if (!function_exists('wc_get_order')) {
        return;
    }
    
    try {
        $order = wc_get_order($order_id);
        if ($order && method_exists($order, 'get_billing_phone')) {
            $phone = $order->get_billing_phone();
            if (!empty($phone)) {
                delete_transient('vip_order_count_' . md5($phone));
            }
        }
    } catch (Exception $e) {
        // Silently fail
    }
}
add_action('woocommerce_order_status_changed', 'vip_user_order_clear_cache', 10, 1);
add_action('woocommerce_new_order', 'vip_user_order_clear_cache', 10, 1);
