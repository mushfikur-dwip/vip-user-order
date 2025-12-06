<?php
/**
 * Plugin Name: VIP User Order
 * Plugin URI: https://example.com/vip-user-order
 * Description: একই ফোন নাম্বার থেকে অর্ডারের সংখ্যা অনুযায়ী কাস্টমার ট্যাগ প্রদর্শন করে
 * Version: 1.0.0
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
define('VIP_USER_ORDER_VERSION', '1.0.0');
define('VIP_USER_ORDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VIP_USER_ORDER_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main VIP User Order Class
 */
class VIP_User_Order {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
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
        
        // Order status change hook
        add_action('woocommerce_order_status_changed', array($this, 'recalculate_customer_tags'), 10, 3);
    }
    
    /**
     * Get default tag settings
     */
    public function get_default_tags() {
        return array(
            array(
                'min' => 1,
                'max' => 1,
                'label' => 'নতুন কাস্টমার',
                'color' => '#3498db'
            ),
            array(
                'min' => 2,
                'max' => 2,
                'label' => 'রিপিট কাস্টমার',
                'color' => '#f39c12'
            ),
            array(
                'min' => 3,
                'max' => 5,
                'label' => 'লয়াল কাস্টমার',
                'color' => '#27ae60'
            ),
            array(
                'min' => 6,
                'max' => 999,
                'label' => 'ভিআইপি কাস্টমার',
                'color' => '#8e44ad'
            )
        );
    }
    
    /**
     * Get tag settings
     */
    public function get_tag_settings() {
        $tags = get_option('vip_user_order_tags', $this->get_default_tags());
        return $tags;
    }
    
    /**
     * Add admin menu
     */
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
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('vip_user_order_settings', 'vip_user_order_tags');
    }
    
    /**
     * Render settings page
     */
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
            
            // Sort by min value
            usort($tags, function($a, $b) {
                return $a['min'] - $b['min'];
            });
            
            update_option('vip_user_order_tags', $tags);
            echo '<div class="notice notice-success"><p>সেটিংস সফলভাবে সেভ হয়েছে!</p></div>';
        }
        
        $tags = $this->get_tag_settings();
        ?>
        <div class="wrap">
            <h1>VIP User Order সেটিংস</h1>
            <p>অর্ডার সংখ্যা অনুযায়ী কাস্টমার ট্যাগ কনফিগার করুন</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('vip_user_order_settings'); ?>
                
                <div id="vip-tags-container">
                    <?php foreach ($tags as $index => $tag): ?>
                    <div class="vip-tag-row" style="background: #fff; padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <div style="display: grid; grid-template-columns: 100px 100px 1fr 120px 50px; gap: 10px; align-items: center;">
                            <div>
                                <label>মিনিমাম</label>
                                <input type="number" name="tag_min[]" value="<?php echo esc_attr($tag['min']); ?>" min="1" class="small-text" required>
                            </div>
                            <div>
                                <label>ম্যাক্সিমাম</label>
                                <input type="number" name="tag_max[]" value="<?php echo esc_attr($tag['max']); ?>" min="1" class="small-text" required>
                            </div>
                            <div>
                                <label>ট্যাগ লেবেল</label>
                                <input type="text" name="tag_label[]" value="<?php echo esc_attr($tag['label']); ?>" class="regular-text" required>
                            </div>
                            <div>
                                <label>কালার</label>
                                <input type="color" name="tag_color[]" value="<?php echo esc_attr($tag['color']); ?>" required>
                            </div>
                            <div>
                                <button type="button" class="button remove-tag" style="margin-top: 20px;">মুছুন</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <p>
                    <button type="button" id="add-tag" class="button">নতুন ট্যাগ যোগ করুন</button>
                </p>
                
                <p class="submit">
                    <button type="submit" name="vip_user_order_save" class="button button-primary">সেভ করুন</button>
                </p>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Add new tag
            $('#add-tag').on('click', function() {
                var html = '<div class="vip-tag-row" style="background: #fff; padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px;">' +
                    '<div style="display: grid; grid-template-columns: 100px 100px 1fr 120px 50px; gap: 10px; align-items: center;">' +
                    '<div><label>মিনিমাম</label><input type="number" name="tag_min[]" value="1" min="1" class="small-text" required></div>' +
                    '<div><label>ম্যাক্সিমাম</label><input type="number" name="tag_max[]" value="1" min="1" class="small-text" required></div>' +
                    '<div><label>ট্যাগ লেবেল</label><input type="text" name="tag_label[]" value="" class="regular-text" required></div>' +
                    '<div><label>কালার</label><input type="color" name="tag_color[]" value="#3498db" required></div>' +
                    '<div><button type="button" class="button remove-tag" style="margin-top: 20px;">মুছুন</button></div>' +
                    '</div></div>';
                $('#vip-tags-container').append(html);
            });
            
            // Remove tag
            $(document).on('click', '.remove-tag', function() {
                $(this).closest('.vip-tag-row').remove();
            });
        });
        </script>
        <?php
    }
    
    /**
     * Add customer tag column
     */
    public function add_customer_tag_column($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            if ($key === 'order_status') {
                $new_columns['customer_tag'] = 'কাস্টমার ট্যাগ';
            }
        }
        return $new_columns;
    }
    
    /**
     * Display customer tag column (for classic orders)
     */
    public function display_customer_tag_column($column, $post_id) {
        if ($column === 'customer_tag') {
            $order = wc_get_order($post_id);
            if ($order) {
                echo $this->get_customer_tag_html($order);
            }
        }
    }
    
    /**
     * Display customer tag column (for HPOS)
     */
    public function display_customer_tag_column_hpos($column, $order) {
        if ($column === 'customer_tag') {
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            }
            if ($order) {
                echo $this->get_customer_tag_html($order);
            }
        }
    }
    
    /**
     * Get customer tag HTML
     */
    public function get_customer_tag_html($order) {
        $billing_phone = $order->get_billing_phone();
        
        if (empty($billing_phone)) {
            return '<span style="color: #999;">—</span>';
        }
        
        // Clean phone number
        $phone = $this->clean_phone_number($billing_phone);
        
        // Count orders with this phone number
        $order_count = $this->count_orders_by_phone($phone);
        
        // Get appropriate tag
        $tag = $this->get_tag_for_order_count($order_count);
        
        if ($tag) {
            return sprintf(
                '<span class="vip-customer-tag" style="background-color: %s; color: #fff; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600; display: inline-block;">%s (%d)</span>',
                esc_attr($tag['color']),
                esc_html($tag['label']),
                $order_count
            );
        }
        
        return '<span style="color: #999;">—</span>';
    }
    
    /**
     * Clean phone number
     */
    private function clean_phone_number($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Remove leading country code if present
        if (strlen($phone) > 11 && substr($phone, 0, 2) === '88') {
            $phone = substr($phone, 2);
        }
        
        return $phone;
    }
    
    /**
     * Count orders by phone number
     */
    private function count_orders_by_phone($phone) {
        global $wpdb;
        
        // Check if HPOS is enabled
        if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
            \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
            
            // HPOS query
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT o.id) 
                FROM {$wpdb->prefix}wc_orders o
                JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
                WHERE om.meta_key = '_billing_phone'
                AND REPLACE(REPLACE(REPLACE(REPLACE(om.meta_value, ' ', ''), '-', ''), '+88', ''), '+', '') LIKE %s
                AND o.status NOT IN ('trash', 'draft', 'auto-draft')",
                '%' . $wpdb->esc_like($phone) . '%'
            ));
        } else {
            // Classic orders query
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT p.ID) 
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND p.post_status NOT IN ('trash', 'draft', 'auto-draft')
                AND pm.meta_key = '_billing_phone'
                AND REPLACE(REPLACE(REPLACE(REPLACE(pm.meta_value, ' ', ''), '-', ''), '+88', ''), '+', '') LIKE %s",
                '%' . $wpdb->esc_like($phone) . '%'
            ));
        }
        
        return intval($count);
    }
    
    /**
     * Get tag for order count
     */
    private function get_tag_for_order_count($count) {
        $tags = $this->get_tag_settings();
        
        foreach ($tags as $tag) {
            if ($count >= $tag['min'] && $count <= $tag['max']) {
                return $tag;
            }
        }
        
        return null;
    }
    
    /**
     * Recalculate customer tags when order status changes
     */
    public function recalculate_customer_tags($order_id, $old_status, $new_status) {
        // This hook ensures tags are updated when order status changes
        // The actual calculation happens on display, so no action needed here
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        if ($hook === 'edit.php' || $hook === 'woocommerce_page_wc-orders') {
            wp_enqueue_style(
                'vip-user-order-admin',
                VIP_USER_ORDER_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                VIP_USER_ORDER_VERSION
            );
        }
    }
}

/**
 * Initialize the plugin
 */
function vip_user_order_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>VIP User Order</strong> এর জন্য WooCommerce প্লাগিন প্রয়োজন।</p></div>';
        });
        return;
    }
    
    VIP_User_Order::get_instance();
}
add_action('plugins_loaded', 'vip_user_order_init');
