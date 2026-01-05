<?php
/**
 * Plugin Name: WooCommerce Формуляр за запитване
 * Description: Замяна на бутона "Добави в количката" с формуляр за запитване за ВСИЧКИ продукти
 * Version: 1.0.1
 * Author: Ivelina
 * Text Domain: wc-inquiry-form
 * License: MIT
 * Author URI: https://github.com/BeLightning
 * License URI: https://opensource.org/licenses/MIT
 * Requires PHP: 8.2
 * WooCommerce tested up to: 10.4
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {echo '<div class="error"><p><strong>WooCommerce Формуляр за запитване</strong> изисква WooCommerce да бъде инсталиран и активиран.</p></div>';});
    return;
}

class WC_Inquiry_Form {
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'register_inquiry_order_status'));
        add_filter('wc_order_statuses', array($this, 'add_inquiry_to_order_statuses'));
        /* ==== PER-PRODUCT TOGGLE => За конкретни продукти само ====
        add_action('add_meta_boxes', array($this, 'add_inquiry_meta_box'));
        add_action('save_post', array($this, 'save_inquiry_meta_box'));
        ==== */
        add_action('wp', array($this, 'remove_add_to_cart_button'));
        add_action('woocommerce_before_single_product', array($this, 'remove_add_to_cart_hooks'));
        add_action('woocommerce_single_product_summary', array($this, 'show_inquiry_form'), 30);
        
        add_filter('woocommerce_is_purchasable', array($this, 'disable_purchasable_for_inquiry'), 10, 2);
        add_filter('woocommerce_product_is_in_stock', array($this, 'mark_as_out_of_stock_for_inquiry'), 10, 2);
        
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        add_action('wp_ajax_submit_inquiry_form', array($this, 'handle_inquiry_submission'));
        add_action('wp_ajax_nopriv_submit_inquiry_form', array($this, 'handle_inquiry_submission'));
        
        add_action('woocommerce_order_status_inquiry', array($this, 'send_admin_notification'), 10, 2);
        
        add_action('woocommerce_email', array($this, 'disable_wc_emails_for_inquiry_orders'));
        
        add_filter('woocommerce_reports_order_statuses', array($this, 'add_inquiry_to_reports'));
    }
    
    public function register_inquiry_order_status() {
        register_post_status('wc-inquiry', array(
            'label'                     => _x('Запитване', 'Order status', 'wc-inquiry-form'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Запитване <span class="count">(%s)</span>', 'Запитвания <span class="count">(%s)</span>', 'wc-inquiry-form')
        ));
    }

    public function add_inquiry_to_order_statuses($order_statuses) {
        $new_statuses = array();
        foreach ($order_statuses as $key => $status) {
            $new_statuses[$key] = $status;
            if ('wc-pending' === $key) {
                $new_statuses['wc-inquiry'] = _x('Запитване', 'Order status', 'wc-inquiry-form');
            }
        }
        return $new_statuses;
    }
  
    public function add_inquiry_to_reports($statuses) {
        $statuses[] = 'inquiry';
        return $statuses;
    }

    public function disable_wc_emails_for_inquiry_orders($email_class) {
        remove_action('woocommerce_order_status_pending_to_inquiry_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
        remove_action('woocommerce_order_status_failed_to_inquiry_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
        remove_action('woocommerce_order_status_cancelled_to_inquiry_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
        remove_action('woocommerce_order_status_inquiry_notification', array($email_class->emails['WC_Email_New_Order'], 'trigger'));
        
        remove_action('woocommerce_order_status_pending_to_inquiry_notification', array($email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger'));
        remove_action('woocommerce_order_status_inquiry_notification', array($email_class->emails['WC_Email_Customer_Processing_Order'], 'trigger'));
        
        add_filter('woocommerce_email_enabled_new_order', array($this, 'check_order_status_for_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_processing_order', array($this, 'check_order_status_for_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_completed_order', array($this, 'check_order_status_for_email'), 10, 2);
        add_filter('woocommerce_email_enabled_customer_invoice', array($this, 'check_order_status_for_email'), 10, 2);
    }

    public function check_order_status_for_email($enabled, $order) {
        if (is_a($order, 'WC_Order') && $order->get_status() === 'inquiry') {
            return false;
        }
        return $enabled;
    }

    /* ====FUNCTIONS ====
    
    public function add_inquiry_meta_box() {
        add_meta_box(
            'wc_inquiry_form_meta_box',
            __('Формуляр за запитване', 'wc-inquiry-form'),
            array($this, 'render_inquiry_meta_box'),
            'product',
            'side',
            'default'
        );
    }

    public function render_inquiry_meta_box($post) {
        wp_nonce_field('wc_inquiry_meta_box', 'wc_inquiry_meta_box_nonce');
        $enabled = get_post_meta($post->ID, '_inquiry_form_enabled', true);
        ?>
        <p>
            <label>
                <input type="checkbox" name="_inquiry_form_enabled" value="yes" <?php checked($enabled, 'yes'); ?>>
                <?php _e('Активирай формуляр за запитване за този продукт', 'wc-inquiry-form'); ?>
            </label>
        </p>
        <p class="description">
            <?php _e('Когато е активирано, бутонът "Добави в количката" ще бъде заменен с формуляр за запитване.', 'wc-inquiry-form'); ?>
        </p>
        <?php
    }
    
    public function save_inquiry_meta_box($post_id) {
        if (!isset($_POST['wc_inquiry_meta_box_nonce'])) {
            return;
        }
        
        if (!wp_verify_nonce($_POST['wc_inquiry_meta_box_nonce'], 'wc_inquiry_meta_box')) {
            return;
        }
        
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        $enabled = isset($_POST['_inquiry_form_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_inquiry_form_enabled', $enabled);
    }
    
 */
    
    //* Премахва бутона Добави в количката за *абсолютно ВСИЧКИ продукти без изключение
    public function remove_add_to_cart_button() {
        if (!is_product()) {
            return;
        }
        
        /* 
        $product_id = get_queried_object_id();
        if (!$product_id) {
            return;
        }
        $inquiry_enabled = get_post_meta($product_id, '_inquiry_form_enabled', true);
        if ($inquiry_enabled === 'yes') { */
        
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        remove_action('woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', 30);
        remove_action('woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', 30);
        remove_action('woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', 30);
        remove_action('woocommerce_external_add_to_cart', 'woocommerce_external_add_to_cart', 30);
        remove_action('woocommerce_single_variation', 'woocommerce_single_variation_add_to_cart_button', 20);
        
        //}
    }

    public function remove_add_to_cart_hooks() {
        global $product;
        if (!$product) {
            $product_id = get_queried_object_id();
            if ($product_id) {
                $product = wc_get_product($product_id);
            }
        }
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        /* 
        $inquiry_enabled = get_post_meta($product->get_id(), '_inquiry_form_enabled', true);
        if ($inquiry_enabled === 'yes') {*/
        
        remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
        
        //}
    }
    
    public function show_inquiry_form() {
        global $product;
        
        if (!$product || !is_a($product, 'WC_Product')) {
            return;
        }
        
        //$inquiry_enabled = get_post_meta($product->get_id(), '_inquiry_form_enabled', true);if ($inquiry_enabled === 'yes') {
        
        $this->render_inquiry_form();
        
        //   }

    }
    
    public function disable_purchasable_for_inquiry($purchasable, $product) {
        if (!is_a($product, 'WC_Product')) {
            return $purchasable;
        }
        
        if (is_product() || is_shop() || is_product_category()) {
            // $inquiry_enabled = get_post_meta($product->get_id(), '_inquiry_form_enabled', true);
           // if ($inquiry_enabled === 'yes') {
          //      return false;
         //   }
            return false;
        }
        return $purchasable;
    }
    
    public function mark_as_out_of_stock_for_inquiry($is_in_stock, $product) {
        if (!is_a($product, 'WC_Product')) {
            return $is_in_stock;
        }
        
        if (is_product()) {
            /* 
            $inquiry_enabled = get_post_meta($product->get_id(), '_inquiry_form_enabled', true);
            if ($inquiry_enabled === 'yes') {
                return false; 
            }*/
            
            return false;
        }
        return $is_in_stock;
    }

    public function render_inquiry_form() {
        global $product;
        ?>
        <div class="wc-inquiry-form-wrapper">
            <form class="wc-inquiry-form" id="wc-inquiry-form" method="post">
                <?php wp_nonce_field('wc_inquiry_form_submit', 'wc_inquiry_nonce'); ?>
                <input type="hidden" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>">
                
                <?php if ($product->is_type('variable')) : ?>
                    <div class="variations_form cart">
                        <?php 
                        $available_variations = $product->get_available_variations();
                        $attributes = $product->get_variation_attributes();
                        
                        if (!empty($attributes)) : ?>
                            <table class="variations">
                                <tbody>
                                    <?php foreach ($attributes as $attribute_name => $options) : ?>
                                        <tr>
                                            <td class="label">
                                                <label for="<?php echo esc_attr(sanitize_title($attribute_name)); ?>">
                                                    <?php echo wc_attribute_label($attribute_name); ?>
                                                </label>
                                            </td>
                                            <td class="value">
                                                <?php
                                                    wc_dropdown_variation_attribute_options(array(
                                                        'options'   => $options,
                                                        'attribute' => $attribute_name,
                                                        'product'   => $product,
                                                    ));
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <input type="hidden" name="variation_id" value="">
                    </div>
                <?php endif; ?>
                
                <div class="wc-inquiry-form-fields">
                    <p class="form-row form-row-wide">
                        <label for="inquiry_full_name">
                            <?php _e('Имена', 'wc-inquiry-form'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" class="input-text" name="full_name" id="inquiry_full_name" required>
                    </p>
                    
                    <p class="form-row form-row-wide">
                        <label for="inquiry_quantity">
                            <?php _e('Количество', 'wc-inquiry-form'); ?> <span class="required">*</span>
                        </label>
                        <input type="number" class="input-text" name="quantity" id="inquiry_quantity" min="1" max="10" value="1" required>
                        <small><?php _e('Максимално количество: 10', 'wc-inquiry-form'); ?></small>
                    </p>
                    
                    <p class="form-row form-row-wide">
                        <label for="inquiry_email">
                            <?php _e('Имейл', 'wc-inquiry-form'); ?> <span class="required">*</span>
                        </label>
                        <input type="email" class="input-text" name="email" id="inquiry_email" required>
                    </p>
                    
                    <p class="form-row form-row-wide">
                        <label for="inquiry_phone">
                            <?php _e('Телефон', 'wc-inquiry-form'); ?> <span class="required">*</span>
                        </label>
                        <input type="tel" class="input-text" name="phone" id="inquiry_phone" required>
                    </p>
                </div>
                <div class="wc-inquiry-form-messages"></div>
                
                <button type="submit" class="single_add_to_cart_button button alt">
                    <?php _e('Изпрати запитване', 'wc-inquiry-form'); ?>
                </button>
            </form>
        </div>
        <?php
    }
    
    public function enqueue_scripts() {
        if (is_product()) {
            /*global $product;
            if ($product) {
                $inquiry_enabled = get_post_meta($product->get_id(), '_inquiry_form_enabled', true);
                if ($inquiry_enabled === 'yes') {*/
            
            wp_enqueue_style('wc-inquiry-form', plugin_dir_url(__FILE__) . 'assets/css/inquiry_css.css', array(), '1.0.1');
            wp_enqueue_script('wc-inquiry-form', plugin_dir_url(__FILE__) . 'assets/js/inquiry_js.js', array('jquery'), '1.0.1', true);
            
            wp_localize_script('wc-inquiry-form', 'wcInquiryForm', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_inquiry_form_submit'),
                'messages' => array(
                    'success' => __('Благодарим Ви! Вашето запитване беше изпратено успешно.', 'wc-inquiry-form'),
                    'error' => __('Възникна грешка. Моля, опитайте отново.', 'wc-inquiry-form'),
                    'processing' => __('Обработване...', 'wc-inquiry-form')
                )
            ));            
            // }            }
        }
    }

    public function handle_inquiry_submission() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wc_inquiry_form_submit')) {
            wp_send_json_error(array('message' => __('Проверката за сигурност не успя.', 'wc-inquiry-form')));
            return;
        }
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $full_name = isset($_POST['full_name']) ? sanitize_text_field($_POST['full_name']) : '';
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? sanitize_text_field($_POST['phone']) : '';
        $phone_digits = preg_replace('/\D/', '', $phone); 
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        
        $errors = array();
        
        if (empty($product_id) || !wc_get_product($product_id)) {
            $errors[] = __('Невалиден продукт.', 'wc-inquiry-form');
        }
        
        if (empty($full_name)) {
            $errors[] = __('Полетата са задължителни', 'wc-inquiry-form');
        }
        
        if ($quantity < 1 || $quantity > 10) {
            $errors[] = __('Количеството трябва да бъде между 1 и 10.', 'wc-inquiry-form');
        }
        
        if (empty($email) || !is_email($email)) {
            $errors[] = __('Валиден имейл е задължителен.', 'wc-inquiry-form');
        }
        
        if (empty($phone) || strlen($phone_digits) < 10 || !(strpos($phone, '0') === 0 || strpos($phone, '+359') === 0)) {
        $errors[] = __('Въведете валиден телефонен номер (мин. 10 цифри, започва с 0 или +359)', 'wc-inquiry-form');
        }
        
        if (!empty($errors)) {
            wp_send_json_error(array('message' => implode(' ', $errors)));
            return;
        }
        
        try {
            $order = wc_create_order();
            
            if (is_wp_error($order)) {
                throw new Exception(__('Неуспешно създаване на поръчка.', 'wc-inquiry-form'));
            }
            
            $product = wc_get_product($variation_id > 0 ? $variation_id : $product_id);
            
            if (!$product) {
                throw new Exception(__('Продуктът не е намерен.', 'wc-inquiry-form'));
            }
            
            $order->add_product($product, $quantity, array(
                'subtotal' => 0,
                'total' => 0
            ));
            
            $order->set_billing_first_name($full_name);
            $order->set_billing_email($email);
            $order->set_billing_phone($phone);
            
            $order->update_meta_data('_inquiry_full_name', $full_name);
            $order->update_meta_data('_inquiry_quantity', $quantity);
            $order->update_meta_data('_inquiry_email', $email);
            $order->update_meta_data('_inquiry_phone', $phone);
            $order->update_meta_data('_is_inquiry', 'yes');
            
            $order->calculate_totals();
            $order->set_status('inquiry', __('Запитването е изпратено.', 'wc-inquiry-form'), false);
            $order->save();
            
            do_action('woocommerce_order_status_inquiry', $order->get_id(), $order);
            
            wp_send_json_success(array(
                'message' => __('Благодарим Ви! Вашето запитване беше изпратено успешно. Търпение', 'wc-inquiry-form'),
                'order_id' => $order->get_id()
            ));
            
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    public function send_admin_notification($order_id, $order) {
        $email_sent_key = 'wc_inquiry_email_sent_' . $order_id;
        if (get_transient($email_sent_key)) {
            return; 
        }
        set_transient($email_sent_key, true, 3600); 
        $admin_email = get_option('admin_email');
        
        $full_name = $order->get_meta('_inquiry_full_name');
        $email = $order->get_meta('_inquiry_email');
        $phone = $order->get_meta('_inquiry_phone');
        $quantity = $order->get_meta('_inquiry_quantity');
        
        $items = $order->get_items();
        $product_name = '';
        foreach ($items as $item) {
            $product_name = $item->get_name();
            break;
        }
        
        $subject = sprintf(__('Нова заявка за продукт - Поръчка #%s', 'wc-inquiry-form'), $order->get_order_number());
        
        $message = sprintf(
            __("Изпратена е нова заявка за продукт:\n\nПоръчка #: %s\nПродукт: %s\nКоличество: %s\n\nДанни на клиента:\nИме: %s\nИмейл: %s\nТелефон: %s\n\nПрегледайте поръчката: %s", 'wc-inquiry-form'),
            $order->get_order_number(),
            $product_name,
            $quantity,
            $full_name,
            $email,
            $phone,
            admin_url('post.php?post=' . $order_id . '&action=edit')
        );
        
        wp_mail($admin_email, $subject, $message);
    }
}

add_action('plugins_loaded', function() {
    WC_Inquiry_Form::get_instance();
});
