<?php
/**
 * Plugin Name: VIP User Order
 * Plugin URI: https://example.com/vip-user-order
 * Description: Display customer tags based on order count from the same phone number
 * Version: 1.0.1
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

// Prevent fatal errors by checking dependencies
if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

// Define plugin constants
define('VIP_USER_ORDER_VERSION', '1.0.1');
define('VIP_USER_ORDER_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('VIP_USER_ORDER_PLUGIN_URL', plugin_dir_url(__FILE__));

// Error handling
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', false);
}

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
        
        // Make column sortable (optional)
        add_filter('manage_edit-shop_order_sortable_columns', array($this, 'make_customer_tag_sortable'));
        
        // Add meta box to order edit page
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        
        // For HPOS order edit page
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_customer_tag_on_order_page'), 10, 1);
        
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
                'label' => 'New Customer',
                'color' => '#3498db'
            ),
            array(
                'min' => 2,
                'max' => 2,
                'label' => 'Repeat Customer',
                'color' => '#f39c12'
            ),
            array(
                'min' => 3,
                'max' => 5,
                'label' => 'Loyal Customer',
                'color' => '#27ae60'
            ),
            array(
                'min' => 6,
                'max' => 999,
                'label' => 'VIP Customer',
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
        if (!function_exists('add_submenu_page')) {
            return;
        }
        
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
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $tags = $this->get_tag_settings();
        ?>
        <div class="wrap">
            <h1>VIP User Order Settings</h1>
            <p>Configure customer tags based on order count</p>
            
            <form method="post" action="">
                <?php wp_nonce_field('vip_user_order_settings'); ?>
                
                <div id="vip-tags-container">
                    <?php foreach ($tags as $index => $tag): ?>
                    <div class="vip-tag-row" style="background: #fff; padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <div style="display: grid; grid-template-columns: 100px 100px 1fr 120px 50px; gap: 10px; align-items: center;">
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
            <p>Enter a phone number to check how many orders are found (Format: +8801XXXXXXXXX or 01XXXXXXXXX)</p>
            
            <form method="get" action="" style="margin: 20px 0;">
                <input type="hidden" name="page" value="vip-user-order">
                <input type="text" name="test_phone" placeholder="Enter phone number" value="<?php echo isset($_GET['test_phone']) ? esc_attr($_GET['test_phone']) : ''; ?>" style="width: 300px;">
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
                
                // Show matching orders
                $this->debug_phone_matching($test_phone);
                
                echo '</div>';
            }
            ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Add new tag
            $('#add-tag').on('click', function() {
                var html = '<div class="vip-tag-row" style="background: #fff; padding: 15px; margin-bottom: 10px; border: 1px solid #ddd; border-radius: 5px;">' +
                    '<div style="display: grid; grid-template-columns: 100px 100px 1fr 120px 50px; gap: 10px; align-items: center;">' +
                    '<div><label>Minimum</label><input type="number" name="tag_min[]" value="1" min="1" class="small-text" required></div>' +
                    '<div><label>Maximum</label><input type="number" name="tag_max[]" value="1" min="1" class="small-text" required></div>' +
                    '<div><label>Tag Label</label><input type="text" name="tag_label[]" value="" class="regular-text" required></div>' +
                    '<div><label>Color</label><input type="color" name="tag_color[]" value="#3498db" required></div>' +
                    '<div><button type="button" class="button remove-tag" style="margin-top: 20px;">Remove</button></div>' +
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
            // Add after order_status column
            if ($key === 'order_status') {
                $new_columns['customer_tag'] = 'Customer Tag';
            }
        }
        // If order_status not found, add at the end before actions
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
    
    /**
     * Make customer tag column sortable
     */
    public function make_customer_tag_sortable($columns) {
        $columns['customer_tag'] = 'customer_tag';
        return $columns;
    }
    
    /**
     * Display customer tag column (for classic orders)
     */
    public function display_customer_tag_column($column, $post_id) {
        if ($column !== 'customer_tag') {
            return;
        }
        
        try {
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
        } catch (Exception $e) {
            echo '<span style="color: #999;">—</span>';
        }
    }
    
    /**
     * Display customer tag column (for HPOS)
     */
    public function display_customer_tag_column_hpos($column, $order) {
        if ($column !== 'customer_tag') {
            return;
        }
        
        try {
            if (!function_exists('wc_get_order')) {
                echo '<span style="color: #999;">—</span>';
                return;
            }
            
            // Handle both order object and order ID
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            } elseif (!is_a($order, 'WC_Order')) {
                echo '<span style="color: #999;">—</span>';
                return;
            }
            
            if ($order) {
                echo $this->get_customer_tag_html($order);
            } else {
                echo '<span style="color: #999;">—</span>';
            }
        } catch (Exception $e) {
            echo '<span style="color: #999;">—</span>';
        }
    }
    
    /**
     * Add meta box to order edit page
     */
    public function add_order_meta_box() {
        $screen = class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
                  \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() 
                  ? wc_get_page_screen_id('shop-order') 
                  : 'shop_order';
        
        add_meta_box(
            'vip_customer_tag_meta_box',
            'Customer Tag',
            array($this, 'render_order_meta_box'),
            $screen,
            'side',
            'high'
        );
    }
    
    /**
     * Render order meta box
     */
    public function render_order_meta_box($post_or_order) {
        // Get order object
        $order = $post_or_order instanceof WP_Post ? wc_get_order($post_or_order->ID) : $post_or_order;
        
        if (!$order) {
            return;
        }
        
        $billing_phone = $order->get_billing_phone();
        
        if (empty($billing_phone)) {
            echo '<p style="color: #999;">No phone number found</p>';
            return;
        }
        
        $phone = $this->clean_phone_number($billing_phone);
        $order_count = $this->count_orders_by_phone($billing_phone);
        $tag = $this->get_tag_for_order_count($order_count);
        
        echo '<div style="padding: 10px;">';
        echo '<p><strong>Phone Number:</strong> ' . esc_html($billing_phone) . '</p>';
        
        // Debug info
        if (defined('WP_DEBUG') && WP_DEBUG) {
            echo '<p style="font-size: 11px; color: #666;"><em>Cleaned: ' . esc_html($phone) . '</em></p>';
        }
        
        echo '<p><strong>Total Orders:</strong> ' . esc_html($order_count) . '</p>';
        
        if ($tag) {
            echo '<p><strong>Status:</strong></p>';
            echo '<div style="margin-top: 10px;">';
            echo sprintf(
                '<span class="vip-customer-tag" style="background-color: %s; color: #fff; padding: 8px 15px; border-radius: 4px; font-size: 13px; font-weight: 600; display: inline-block;">%s</span>',
                esc_attr($tag['color']),
                esc_html($tag['label'])
            );
            echo '</div>';
        } else {
            echo '<p style="color: #999;">No tag found</p>';
        }
        
        echo '</div>';
    }
    
    /**
     * Display customer tag on order page (for HPOS)
     */
    public function display_customer_tag_on_order_page($order) {
        if (!$order) {
            return;
        }
        
        $billing_phone = $order->get_billing_phone();
        
        if (empty($billing_phone)) {
            return;
        }
        
        $phone = $this->clean_phone_number($billing_phone);
        $order_count = $this->count_orders_by_phone($phone);
        $tag = $this->get_tag_for_order_count($order_count);
        
        if ($tag) {
            echo '<div class="vip-customer-tag-box" style="margin-top: 15px; padding: 12px; background: #f8f9fa; border-left: 4px solid ' . esc_attr($tag['color']) . '; border-radius: 4px;">';
            echo '<h4 style="margin: 0 0 10px 0; font-size: 14px;">Customer Tag</h4>';
            echo '<p style="margin: 0 0 8px 0;"><strong>Total Orders:</strong> ' . esc_html($order_count) . '</p>';
            echo sprintf(
                '<span class="vip-customer-tag" style="background-color: %s; color: #fff; padding: 6px 12px; border-radius: 3px; font-size: 12px; font-weight: 600; display: inline-block;">%s</span>',
                esc_attr($tag['color']),
                esc_html($tag['label'])
            );
            echo '</div>';
        }
    }
    
    /**
     * Get customer tag HTML
     */
    public function get_customer_tag_html($order) {
        try {
            if (!is_object($order) || !method_exists($order, 'get_billing_phone')) {
                return '<span style="color: #999;">—</span>';
            }
            
            $billing_phone = $order->get_billing_phone();
            
            if (empty($billing_phone)) {
                return '<span style="color: #999;">—</span>';
            }
            
            // Count orders with this phone number
            $order_count = $this->count_orders_by_phone($billing_phone);
            
            // Get appropriate tag
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
            
        } catch (Exception $e) {
            return '<span style="color: #999;">—</span>';
        }
    }
    
    /**
     * Clean phone number for Bangladesh format
     * Supports: +8801XXXXXXXXX, 8801XXXXXXXXX, 01XXXXXXXXX
     */
    private function clean_phone_number($phone) {
        if (empty($phone)) {
            return '';
        }
        
        // Convert Bengali numbers to English
        $bengali_numbers = array('০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯');
        $english_numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');
        $phone = str_replace($bengali_numbers, $english_numbers, $phone);
        
        // Remove all non-numeric characters (spaces, dashes, plus signs, etc.)
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle Bangladesh country code (880)
        // Remove 880 prefix if exists to normalize to 11-digit format
        if (strlen($phone) === 13 && substr($phone, 0, 3) === '880') {
            $phone = '0' . substr($phone, 3); // Convert 8801712345678 to 01712345678
        } elseif (strlen($phone) === 12 && substr($phone, 0, 2) === '88') {
            $phone = '0' . substr($phone, 2); // Convert 88171234567 to 0171234567 (rare case)
        }
        
        // Ensure 11-digit format starting with 0
        if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            return $phone; // Perfect format: 01XXXXXXXXX
        }
        
        // If 10 digits, add leading 0
        if (strlen($phone) === 10) {
            return '0' . $phone;
        }
        
        // If more than 11 digits, take last 11
        if (strlen($phone) > 11) {
            $phone = substr($phone, -11);
            // Ensure starts with 0
            if (substr($phone, 0, 1) !== '0') {
                $phone = '0' . substr($phone, 1);
            }
        }
        
        return $phone;
    }
    
    /**
     * Count orders by phone number with caching
     */
    private function count_orders_by_phone($phone) {
        if (empty($phone)) {
            return 0;
        }
        
        $cleaned_target = $this->clean_phone_number($phone);
        
        if (empty($cleaned_target) || strlen($cleaned_target) < 10) {
            return 0;
        }
        
        // Try cache first
        $cache_key = 'vip_order_count_' . md5($cleaned_target);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return intval($cached);
        }
        
        global $wpdb;
        
        try {
            // Get all orders with billing phone
            $results = array();
            
            // Check if HPOS is enabled
            if (class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
                \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                
                // HPOS query - get all phone numbers
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
                // Classic orders query - get all phone numbers
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
            
            // Count matching phone numbers
            $count = 0;
            
            if (!empty($results) && is_array($results)) {
                foreach ($results as $result) {
                    if (empty($result['phone'])) {
                        continue;
                    }
                    
                    $cleaned_result = $this->clean_phone_number($result['phone']);
                    
                    // Exact match
                    if ($cleaned_result === $cleaned_target) {
                        $count++;
                    }
                }
            }
            
            // Cache for 5 minutes
            set_transient($cache_key, $count, 300);
            
            return intval($count);
            
        } catch (Exception $e) {
            // Log error if WP_DEBUG is enabled
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('VIP User Order Error: ' . $e->getMessage());
            }
            return 0;
        }
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
     * Debug function to check phone matching (for admin only)
     */
    public function debug_phone_matching($phone) {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }
        
        global $wpdb;
        
        echo '<div style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccc;">';
        echo '<h3>Debug Info for: ' . esc_html($phone) . '</h3>';
        echo '<p><strong>Cleaned:</strong> ' . esc_html($this->clean_phone_number($phone)) . '</p>';
        
        // Get all phone numbers from orders
        if (class_exists('Automattic\\WooCommerce\\Utilities\\OrderUtil') && 
            \\Automattic\\WooCommerce\\Utilities\\OrderUtil::custom_orders_table_usage_is_enabled()) {
            $results = $wpdb->get_results(
                "SELECT o.id, om.meta_value as phone, o.status
                FROM {$wpdb->prefix}wc_orders o
                INNER JOIN {$wpdb->prefix}wc_orders_meta om ON o.id = om.order_id
                WHERE om.meta_key = '_billing_phone'
                AND o.type = 'shop_order'
                ORDER BY o.id DESC
                LIMIT 20"
            );
        } else {
            $results = $wpdb->get_results(
                "SELECT p.ID as id, pm.meta_value as phone, p.post_status as status
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE p.post_type = 'shop_order'
                AND pm.meta_key = '_billing_phone'
                ORDER BY p.ID DESC
                LIMIT 20"
            );
        }
        
        echo '<h4>Recent Orders:</h4><ul>';
        foreach ($results as $result) {
            $cleaned = $this->clean_phone_number($result->phone);
            $match = ($cleaned === $this->clean_phone_number($phone)) ? '✓ MATCH' : '';
            echo '<li>Order #' . $result->id . ': ' . esc_html($result->phone) . ' → ' . esc_html($cleaned) . ' ' . $match . '</li>';
        }
        echo '</ul></div>';
    }
    
    /**
     * Enqueue admin styles
     */
    public function enqueue_admin_styles($hook) {
        // Load on order list and edit pages
        $order_screens = array('edit.php', 'post.php', 'post-new.php', 'woocommerce_page_wc-orders', 'shop_order');
        
        $load_assets = false;
        foreach ($order_screens as $screen) {
            if (strpos($hook, $screen) !== false) {
                $load_assets = true;
                break;
            }
        }
        
        // Also check if we're on order edit page
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

/**
 * Initialize the plugin
 */
function vip_user_order_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p><strong>VIP User Order</strong> requires WooCommerce plugin to be installed and activated.</p></div>';
        });
        return;
    }
    
    // Initialize plugin
    try {
        VIP_User_Order::get_instance();
    } catch (Exception $e) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('VIP User Order Init Error: ' . $e->getMessage());
        }
    }
}
add_action('plugins_loaded', 'vip_user_order_init', 20);

/**
 * Clear cache when order status changes
 */
function vip_user_order_clear_cache($order_id) {
    try {
        $order = wc_get_order($order_id);
        if ($order) {
            $phone = $order->get_billing_phone();
            if (!empty($phone)) {
                // Clear cache for this phone number
                delete_transient('vip_order_count_' . md5($phone));
            }
        }
    } catch (Exception $e) {
        // Silently fail
    }
}
add_action('woocommerce_order_status_changed', 'vip_user_order_clear_cache', 10, 1);
add_action('woocommerce_new_order', 'vip_user_order_clear_cache', 10, 1);
