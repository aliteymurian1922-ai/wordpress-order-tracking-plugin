<?php
/**
 * Plugin Name: سیستم رهگیری سفارش سفارشی
 * Plugin URI: https://github.com/your-username/wordpress-order-tracking-plugin
 * Description: افزونه رهگیری سفارش با دکمه شیشه‌ای و پاپ‌آپ زیبا
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/your-username
 * Text Domain: order-tracking-custom
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit; // جلوگیری از دسترسی مستقیم
}

// تعریف ثابت‌ها
define('OTC_VERSION', '1.0.0');
define('OTC_PATH', plugin_dir_path(__FILE__));
define('OTC_URL', plugin_dir_url(__FILE__));
define('OTC_BASENAME', plugin_basename(__FILE__));

/**
 * کلاس اصلی افزونه
 */
class Order_Tracking_Custom {
    
    /**
     * نمونه تکی کلاس
     */
    private static $instance = null;
    
    /**
     * دریافت نمونه تکی
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * سازنده کلاس
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * راه‌اندازی هوک‌ها
     */
    private function init_hooks() {
        // بررسی وجود WooCommerce
        add_action('plugins_loaded', array($this, 'check_woocommerce'));
        
        // بارگذاری assets
        add_action('wp_enqueue_scripts', array($this, 'load_assets'));
        
        // ثبت شورت‌کد
        add_shortcode('order_tracking_button', array($this, 'tracking_button_shortcode'));
        
        // اضافه کردن پاپ‌آپ به فوتر
        add_action('wp_footer', array($this, 'tracking_popup'));
        
        // AJAX handlers
        add_action('wp_ajax_search_order', array($this, 'search_order'));
        add_action('wp_ajax_nopriv_search_order', array($this, 'search_order'));
        
        // متا باکس ادمین
        add_action('add_meta_boxes', array($this, 'add_tracking_metabox'));
        add_action('save_post', array($this, 'save_tracking_code'));
        
        // لینک تنظیمات در لیست افزونه‌ها
        add_filter('plugin_action_links_' . OTC_BASENAME, array($this, 'add_action_links'));
    }
    
    /**
     * بررسی فعال بودن WooCommerce
     */
    public function check_woocommerce() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            deactivate_plugins(OTC_BASENAME);
        }
    }
    
    /**
     * نوتیس عدم وجود WooCommerce
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><strong>سیستم رهگیری سفارش:</strong> این افزونه نیاز به WooCommerce دارد. لطفا ابتدا WooCommerce را نصب و فعال کنید.</p>
        </div>
        <?php
    }
    
    /**
     * بارگذاری فایل‌های CSS و JavaScript
     */
    public function load_assets() {
        // بارگذاری Dashicons برای آیکون‌ها
        wp_enqueue_style('dashicons');
        
        // استایل اصلی
        wp_enqueue_style(
            'otc-style',
            OTC_URL . 'assets/css/style.css',
            array(),
            OTC_VERSION
        );
        
        // اسکریپت اصلی
        wp_enqueue_script(
            'otc-script',
            OTC_URL . 'assets/js/script.js',
            array('jquery'),
            OTC_VERSION,
            true
        );
        
        // ارسال داده‌ها به JavaScript
        wp_localize_script('otc-script', 'otc_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('otc_nonce')
        ));
    }
    
    /**
     * شورت‌کد دکمه رهگیری
     */
    public function tracking_button_shortcode($atts) {
        $atts = shortcode_atts(array(
            'text' => 'رهگیری سفارش',
            'icon' => 'dashicons-location',
            'class' => ''
        ), $atts);
        
        ob_start();
        ?>
        <button class="otc-glass-button <?php echo esc_attr($atts['class']); ?>" id="otc-open-popup">
            <span class="dashicons <?php echo esc_attr($atts['icon']); ?>"></span>
            <?php echo esc_html($atts['text']); ?>
        </button>
        <?php
        return ob_get_clean();
    }
    
    /**
     * HTML پاپ‌آپ رهگیری
     */
    public function tracking_popup() {
        ?>
        <div id="otc-popup-overlay" class="otc-popup-overlay">
            <div class="otc-popup-container">
                <button class="otc-close-popup" aria-label="بستن">&times;</button>
                
                <div class="otc-popup-header">
                    <h2>🔍 رهگیری سفارش</h2>
                    <p>کد رهگیری یا شماره سفارش خود را وارد کنید</p>
                </div>
                
                <div class="otc-popup-body">
                    <form id="otc-tracking-form" method="post">
                        <div class="otc-input-group">
                            <input 
                                type="text" 
                                id="otc-tracking-code" 
                                name="tracking_code" 
                                placeholder="مثال: 12345 یا ORD-2024-001" 
                                required
                                autocomplete="off"
                            >
                            <button type="submit" class="otc-search-btn">
                                <span class="dashicons dashicons-search"></span>
                                جستجو
                            </button>
                        </div>
                    </form>
                    
                    <div id="otc-result" class="otc-result"></div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * جستجوی سفارش با AJAX
     */
    public function search_order() {
        // بررسی nonce امنیتی
        check_ajax_referer('otc_nonce', 'nonce');
        
        $tracking_code = sanitize_text_field($_POST['tracking_code']);
        
        if (empty($tracking_code)) {
            wp_send_json_error(array(
                'message' => 'لطفا کد رهگیری را وارد کنید'
            ));
        }
        
        // جستجو با متا کوئری
        $args = array(
            'limit' => 1,
            'meta_key' => '_tracking_code',
            'meta_value' => $tracking_code,
            'meta_compare' => '=',
        );
        
        $orders = wc_get_orders($args);
        
        // جستجو با شماره سفارش
        if (empty($orders)) {
            $orders = wc_get_orders(array(
                'limit' => 1,
                'order_number' => $tracking_code
            ));
        }
        
        if (!empty($orders)) {
            $order = $orders[0];
            $tracking_info = $this->get_tracking_info($order);
            wp_send_json_success($tracking_info);
        } else {
            wp_send_json_error(array(
                'message' => 'سفارشی با این کد پیدا نشد! لطفا کد رهگیری یا شماره سفارش را بررسی کنید.'
            ));
        }
    }
    
    /**
     * دریافت اطلاعات رهگیری سفارش
     */
    private function get_tracking_info($order) {
        $order_id = $order->get_id();
        
        $tracking_code = get_post_meta($order_id, '_tracking_code', true);
        $tracking_company = get_post_meta($order_id, '_tracking_company', true);
        $tracking_link = get_post_meta($order_id, '_tracking_link', true);
        
        $status_labels = array(
            'pending' => 'در انتظار پرداخت',
            'processing' => 'در حال پردازش',
            'on-hold' => 'معلق',
            'completed' => 'تکمیل شده',
            'cancelled' => 'لغو شده',
            'refunded' => 'بازگشت داده شده',
            'failed' => 'ناموفق'
        );
        
        $status = $order->get_status();
        $status_label = isset($status_labels[$status]) ? $status_labels[$status] : $status;
        
        return array(
            'order_number' => $order->get_order_number(),
            'order_date' => $order->get_date_created()->date('Y/m/d H:i'),
            'status' => $status,
            'status_label' => $status_label,
            'total' => wc_price($order->get_total()),
            'tracking_code' => $tracking_code ? $tracking_code : 'هنوز ثبت نشده',
            'tracking_company' => $tracking_company ? $tracking_company : 'نامشخص',
            'tracking_link' => $tracking_link ? $tracking_link : '',
            'items' => $this->get_order_items($order),
            'customer_note' => $order->get_customer_note()
        );
    }
    
    /**
     * دریافت آیتم‌های سفارش
     */
    private function get_order_items($order) {
        $items = array();
        foreach ($order->get_items() as $item) {
            $items[] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => wc_price($item->get_total())
            );
        }
        return $items;
    }
    
    /**
     * اضافه کردن متا باکس در صفحه سفارش
     */
    public function add_tracking_metabox() {
        add_meta_box(
            'order_tracking_info',
            '📦 اطلاعات رهگیری سفارش',
            array($this, 'tracking_metabox_content'),
            'shop_order',
            'side',
            'high'
        );
        
        // پشتیبانی از HPOS (High-Performance Order Storage)
        add_meta_box(
            'order_tracking_info',
            '📦 اطلاعات رهگیری سفارش',
            array($this, 'tracking_metabox_content'),
            'woocommerce_page_wc-orders',
            'side',
            'high'
        );
    }
    
    /**
     * محتوای متا باکس
     */
    public function tracking_metabox_content($post_or_order) {
        $order_id = $post_or_order instanceof WC_Order ? $post_or_order->get_id() : $post_or_order->ID;
        
        $tracking_code = get_post_meta($order_id, '_tracking_code', true);
        $tracking_company = get_post_meta($order_id, '_tracking_company', true);
        $tracking_link = get_post_meta($order_id, '_tracking_link', true);
        
        wp_nonce_field('save_tracking_code', 'tracking_code_nonce');
        ?>
        <style>
            .otc-metabox-field { margin-bottom: 15px; }
            .otc-metabox-field label { display: block; font-weight: bold; margin-bottom: 5px; }
            .otc-metabox-field input { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
            .otc-metabox-field small { color: #666; display: block; margin-top: 3px; }
        </style>
        
        <div class="otc-metabox-field">
            <label for="tracking_code">کد رهگیری:</label>
            <input type="text" id="tracking_code" name="tracking_code" value="<?php echo esc_attr($tracking_code); ?>">
            <small>کد رهگیری مرسوله پستی</small>
        </div>
        
        <div class="otc-metabox-field">
            <label for="tracking_company">شرکت پست:</label>
            <input type="text" id="tracking_company" name="tracking_company" value="<?php echo esc_attr($tracking_company); ?>" placeholder="مثال: پست پیشتاز">
            <small>نام شرکت حمل و نقل</small>
        </div>
        
        <div class="otc-metabox-field">
            <label for="tracking_link">لینک رهگیری:</label>
            <input type="url" id="tracking_link" name="tracking_link" value="<?php echo esc_attr($tracking_link); ?>" placeholder="https://tracking.post.ir">
            <small>لینک مستقیم رهگیری در سایت پست</small>
        </div>
        <?php
    }
    
    /**
     * ذخیره کد رهگیری
     */
    public function save_tracking_code($post_id) {
        // بررسی nonce
        if (!isset($_POST['tracking_code_nonce']) || !wp_verify_nonce($_POST['tracking_code_nonce'], 'save_tracking_code')) {
            return;
        }
        
        // بررسی autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // ذخیره فیلدها
        if (isset($_POST['tracking_code'])) {
            update_post_meta($post_id, '_tracking_code', sanitize_text_field($_POST['tracking_code']));
        }
        
        if (isset($_POST['tracking_company'])) {
            update_post_meta($post_id, '_tracking_company', sanitize_text_field($_POST['tracking_company']));
        }
        
        if (isset($_POST['tracking_link'])) {
            update_post_meta($post_id, '_tracking_link', esc_url_raw($_POST['tracking_link']));
        }
    }
    
    /**
     * اضافه کردن لینک تنظیمات
     */
    public function add_action_links($links) {
        $plugin_links = array(
            '<a href="https://github.com/your-username/wordpress-order-tracking-plugin" target="_blank">مستندات</a>',
            '<a href="https://github.com/your-username/wordpress-order-tracking-plugin/issues" target="_blank">پشتیبانی</a>'
        );
        return array_merge($plugin_links, $links);
    }
}

/**
 * راه‌اندازی افزونه
 */
function otc_init() {
    return Order_Tracking_Custom::get_instance();
}

// شروع افزونه
add_action('plugins_loaded', 'otc_init');

/**
 * فعال‌سازی افزونه
 */
register_activation_hook(__FILE__, function() {
    // بررسی نسخه PHP
    if (version_compare(PHP_VERSION, '7.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('این افزونه به PHP نسخه 7.0 یا بالاتر نیاز دارد.');
    }
    
    // بررسی نسخه وردپرس
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('این افزونه به وردپرس نسخه 5.0 یا بالاتر نیاز دارد.');
    }
});
