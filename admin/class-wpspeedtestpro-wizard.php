<?php
/**
 * The setup wizard functionality of the plugin.
 *
 * @link       https://wpspeedtestpro.com
 * @since      1.0.0
 *
 * @package    Wpspeedtestpro
 * @subpackage Wpspeedtestpro/admin
 */

class Wpspeedtestpro_Wizard {
    private $plugin_name;
    private $version;
    private $core;

    public function __construct($plugin_name, $version, $core) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->core = $core;

        $this->init_hooks();
    }

    private function init_hooks() {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_footer', array($this, 'render_wizard'));
        
        // AJAX handlers
        add_action('wp_ajax_wpspeedtestpro_save_wizard_settings', array($this, 'save_wizard_settings'));
        add_action('wp_ajax_wpspeedtestpro_get_wizard_data', array($this, 'get_wizard_data'));
        add_action('wp_ajax_wpspeedtestpro_dismiss_wizard', array($this, 'dismiss_wizard'));
    }

    public function enqueue_styles() {
        if (!$this->should_show_wizard()) {
            return;
        }

        wp_enqueue_style(
            $this->plugin_name . '-wizard', 
            plugin_dir_url(__FILE__) . 'css/wpspeedtestpro-wizard.css',
            array(),
            $this->version
        );
    }

    public function enqueue_scripts() {
        if (!$this->should_show_wizard()) {
            return;
        }

        wp_enqueue_script(
            $this->plugin_name . '-wizard',
            plugin_dir_url(__FILE__) . 'js/wpspeedtestpro-wizard.js',
            array('jquery'),
            $this->version,
            true
        );

        wp_localize_script(
            $this->plugin_name . '-wizard',
            'wpspeedtestpro_wizard',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpspeedtestpro_ajax_nonce'),
                'regions' => $this->core->api->get_gcp_endpoints(),
                'providers' => $this->core->api->get_hosting_providers()
            )
        );
    }

    private function should_show_wizard() {
        if (!current_user_can('manage_options')) {
            return false;
        }

        // Check if we're on a WP Speed Test Pro admin page
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'wpspeedtestpro') === false) {
            return false;
        }

        // Check if wizard has been completed or dismissed
        return !get_option('wpspeedtestpro_setup_completed', false);
    }

    public function render_wizard() {
        if (!$this->should_show_wizard()) {
            return;
        }

        include plugin_dir_path(__FILE__) . 'partials/wpspeedtestpro-wizard-display.php';
    }

    public function save_wizard_settings() {
        check_ajax_referer('wpspeedtestpro_ajax_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
    
        $settings = array(
            'gcp_region' => isset($_POST['region']) ? sanitize_text_field(wp_unslash($_POST['region'])) : '',
            'user_country' => isset($_POST['user_country']) ? sanitize_text_field(wp_unslash($_POST['user_country'])) : '',
            'provider_id' => isset($_POST['provider_id']) ? absint($_POST['provider_id']) : 0,
            'package_id' => isset($_POST['package_id']) ? sanitize_text_field(wp_unslash($_POST['package_id'])) : '',
            'allow_data_collection' => isset($_POST['allow_data_collection']) ? 
                (bool) sanitize_text_field(wp_unslash($_POST['allow_data_collection'])) : false,
            'uptimerobot_api_key' => isset($_POST['uptimerobot_key']) ? sanitize_text_field(wp_unslash($_POST['uptimerobot_key'])) : '',
            'pagespeed_api_key' => isset($_POST['pagespeed_api_key']) ? sanitize_text_field(wp_unslash($_POST['pagespeed_api_key'])) : ''
        );
    
        // Define default values for options
        $default_values = array(
            'wpspeedtestpro_selected_region' => '',
            'wpspeedtestpro_user_country' => '',
            'wpspeedtestpro_selected_provider' => 0,
            'wpspeedtestpro_selected_package' => '',
            'wpspeedtestpro_allow_data_collection' => false,
            'wpspeedtestpro_uptimerobot_api_key' => '',
            'wpspeedtestpro_pagespeed_api_key' => '',
            'wpspeedtestpro_setup_completed' => true
        );
    
        // Initialize options if they don't exist
        foreach ($default_values as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                add_option($option_name, $default_value);
            }
        }
        if (isset($_POST['allow_data_collection'])) {
            // Convert various truthy values to boolean
            $value = strtolower(sanitize_text_field(wp_unslash($_POST['allow_data_collection'])));
            $allow_data_collection = in_array($value, ['true', '1', 'yes', 'on'], true);
        }
        


        try {
            // Save settings with error checking
            $update_results = array(
                update_option('wpspeedtestpro_selected_region', $settings['gcp_region']),
                update_option('wpspeedtestpro_user_country', $settings['user_country']),
                update_option('wpspeedtestpro_selected_provider', $settings['provider_id']),
                update_option('wpspeedtestpro_selected_package', $settings['package_id']),
                update_option('wpspeedtestpro_allow_data_collection', $allow_data_collection)
            );
    
            // Only update UptimeRobot API key if provided
            if (!empty($settings['uptimerobot_api_key'])) {
                $update_results[] = update_option('wpspeedtestpro_uptimerobot_api_key', $settings['uptimerobot_api_key']);
            }
    
            // Only update PageSpeed API key if provided
            if (!empty($settings['pagespeed_api_key'])) {
                $update_results[] = update_option('wpspeedtestpro_pagespeed_api_key', $settings['pagespeed_api_key']);
            }
    
            // Mark setup as completed
            update_option('wpspeedtestpro_setup_completed', true);
    
            // Check if any updates failed
            if (in_array(false, $update_results, true)) {
                wp_send_json_error('One or more settings failed to save');
                return;
            }
    
            wp_send_json_success('Settings saved successfully');
    
        } catch (Exception $e) {
            wp_send_json_error('Error saving settings: ' . $e->getMessage());
        }
    }
    

    public function get_wizard_data() {
        check_ajax_referer('wpspeedtestpro_ajax_nonce', 'nonce');
    
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }
    
        $data = array(
            'gcp_region' => get_option('wpspeedtestpro_selected_region'),
            'provider_id' => get_option('wpspeedtestpro_selected_provider_id'),
            'package_id' => get_option('wpspeedtestpro_selected_package_id'),
            'allow_data_collection' => get_option('wpspeedtestpro_allow_data_collection', true),
            'uptimerobot_api_key' => get_option('wpspeedtestpro_uptimerobot_api_key'),
            'pagespeed_api_key' => get_option('wpspeedtestpro_pagespeed_api_key')
        );
    
        wp_send_json_success($data);
    }
    
    public function dismiss_wizard() {
        check_ajax_referer('wpspeedtestpro_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
            return;
        }

        update_option('wpspeedtestpro_setup_completed', true);
        wp_send_json_success('Wizard dismissed');
    }
}