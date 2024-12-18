<?php

/**
 * The settings functionality of the plugin.
 *
 * @link       https://wpspeedtestpro.com
 * @since      1.0.0
 *
 * @package    Wpspeedtestpro
 * @subpackage Wpspeedtestpro/admin
 */

/**
 * The settings functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the settings functionality.
 *
 * @package    Wpspeedtestpro
 * @subpackage Wpspeedtestpro/admin
 * @author     WP Speedtest Pro Team <support@wpspeedtestpro.com>
 */
class Wpspeedtestpro_Settings {

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    private $core;
    private $api;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
    public function __construct($plugin_name, $version, $core) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->core = $core;
        $this->init_components();
        $this->add_hooks(); // Make sure add_hooks is called
    }
    
    private function init_components() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    private function add_hooks() {
        add_action('wp_ajax_wpspeedtestpro_get_provider_packages', array($this, 'ajax_get_provider_packages'));
        add_action('wp_ajax_wpspeedtestpro_get_hosting_providers', array($this, 'ajax_get_hosting_providers'));
        add_action('wp_ajax_wpspeedtestpro_get_gcp_endpoints', array($this, 'ajax_get_gcp_endpoints'));
    }

    private function is_this_the_right_plugin_page() {
        if ( function_exists( 'get_current_screen' ) ) {
            $screen = get_current_screen();
            return $screen && $screen->id === 'wp-speed-test-pro_page_wpspeedtestpro-settings';    
        }
    }


    /**
     * Register the stylesheets for the settings area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        if ($this->is_this_the_right_plugin_page()) {
            wp_enqueue_style( $this->plugin_name . '-settings', plugin_dir_url( __FILE__ ) . 'css/wpspeedtestpro-settings.css', array(), $this->version, 'all' );
        }
    }

    /**
     * Register the JavaScript for the settings area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        if ($this->is_this_the_right_plugin_page()) {
            wp_enqueue_script($this->plugin_name . '-settings', 
                plugin_dir_url(__FILE__) . 'js/wpspeedtestpro-settings.js', 
                array('jquery'), 
                $this->version, 
                false
            );
            
            wp_localize_script($this->plugin_name . '-settings', 'wpspeedtestpro_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpspeedtestpro_ajax_nonce'),
                'selected_region' => get_option('wp_hosting_benchmarking_selected_region'),
                'hosting_providers' => $this->core->api->get_hosting_providers_json()
            ));
        }
    }
    


    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_settings() {
     //   $this->enqueue_styles();
     //   $this->enqueue_scripts();
       include_once( 'partials/wpspeedtestpro-settings-display.php' );
    }

    /**
     * Register settings for the plugin
     *
     * @since    1.0.0
     */
    public function register_settings() {
        // Register settings
 
        register_setting(
            'wpspeedtestpro_settings_group',
            'wpspeedtestpro_options',
            array(
                'type' => 'array',
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );

        register_setting('wpspeedtestpro_settings_group', 'wpspeedtestpro_selected_region');
        register_setting('wpspeedtestpro_settings_group', 'wpspeedtestpro_selected_provider');
        register_setting('wpspeedtestpro_settings_group', 'wpspeedtestpro_selected_package');
        register_setting('wpspeedtestpro_settings_group', 'wpspeedtestpro_allow_data_collection', array(
            'type' => 'boolean',
            'default' => true,
            'sanitize_callback' => 'boolval'
        ));
        register_setting( 'wpspeedtestpro_settings_group', 'wpspeedtestpro_uptimerobot_api_key' );
        register_setting('wpspeedtestpro_settings_group', 'wpspeedtestpro_pagespeed_api_key');

        // Add settings section
        add_settings_section(
            'wpspeedtestpro_section',
            'General Settings',
            null,
            'wpspeedtestpro-settings'
        );

        // Add settings fields
        add_settings_field(
            'wpspeedtestpro_selected_region',
            'Select Closest GCP Region',
            array($this, 'gcp_region_dropdown_callback'),
            'wpspeedtestpro-settings',
            'wpspeedtestpro_section'
        );

        add_settings_field(
            'wpspeedtestpro_selected_provider',
            'Select Hosting Provider',
            array($this, 'hosting_provider_dropdown_callback'),
            'wpspeedtestpro-settings',
            'wpspeedtestpro_section'
        );

        add_settings_field(
            'wpspeedtestpro_selected_package',
            'Select Package',
            array($this, 'hosting_package_dropdown_callback'),
            'wpspeedtestpro-settings',
            'wpspeedtestpro_section'
        );

        add_settings_field(
            'wpspeedtestpro_allow_data_collection',
            'Allow anonymous data collection',
            array($this, 'render_data_collection_field'),
            'wpspeedtestpro-settings',
            'wpspeedtestpro_section'
        );

        add_settings_field(
            'wpspeedtestpro_uptimerobot_api_key',
            'UptimeRobot API Key',
            array($this, 'uptimerobot_api_key_callback'),
            'wpspeedtestpro-settings',
            'wpspeedtestpro_section'
        );

        add_settings_section(
            'pagespeed_settings_section',
            'PageSpeed Insights Settings',
            null,
            'wpspeedtestpro-settings'
        );

        add_settings_field(
            'pagespeed_api_key',
            'PageSpeed Insights API Key',
            [self::class, 'render_api_key_field'],
            'wpspeedtestpro-settings',
            'pagespeed_settings_section'
        );




    }

    public function sanitize_settings($input) {
        $sanitized_input = array();
        
        if (isset($input['wpspeedtestpro_selected_region'])) {
            $sanitized_input['wpspeedtestpro_selected_region'] = sanitize_text_field($input['wpspeedtestpro_selected_region']);
        }
        
        if (isset($input['wpspeedtestpro_selected_provider'])) {
            $sanitized_input['wpspeedtestpro_selected_provider'] = sanitize_text_field($input['wpspeedtestpro_selected_provider']);
        }
        
        if (isset($input['wpspeedtestpro_selected_package'])) {
            $sanitized_input['wpspeedtestpro_selected_package'] = sanitize_text_field($input['wpspeedtestpro_selected_package']);
        }
        
        if (isset($input['wpspeedtestpro_allow_data_collection'])) {
            $sanitized_input['wpspeedtestpro_allow_data_collection'] = (bool) $input['wpspeedtestpro_allow_data_collection'];
        }
        
        if (isset($input['wpspeedtestpro_pagespeed_api_key'])) {
            $sanitized_input['wpspeedtestpro_pagespeed_api_key'] = sanitize_text_field($input['wpspeedtestpro_pagespeed_api_key']);
        }

        if (isset($input['wpspeedtestpro_uptimerobot_api_key'])) {
            $sanitized_input['wpspeedtestpro_uptimerobot_api_key'] = sanitize_text_field($input['wpspeedtestpro_uptimerobot_api_key']);
        }

        return $sanitized_input;
    }



    // Callback to display the GCP region dropdown
    public function gcp_region_dropdown_callback() {
        $selected_region = get_option('wpspeedtestpro_selected_region');
        $gcp_endpoints = $this->core->api->get_gcp_endpoints();

        if (!empty($gcp_endpoints)) {
            echo '<select name="wpspeedtestpro_selected_region">';
            foreach ($gcp_endpoints as $endpoint) {
                $region_name = esc_attr($endpoint['region_name']);
                echo '<option value="' . $region_name . '"' . selected($selected_region, $region_name, false) . '>';
                echo esc_html($region_name);
                echo '</option>';
            }
            echo '</select>';
        } else {
            echo '<p>No GCP endpoints available. Please check your internet connection or try again later.</p>';
        }
        echo '<p class="description">Please select the region closest to where most of your customers or visitors are based.</p>';
    }

    public function hosting_provider_dropdown_callback() {
        $selected_provider_id = get_option('wpspeedtestpro_selected_provider');
        $providers = $this->core->api->get_hosting_providers();
    
        if (!empty($providers)) {
            echo '<select id="wpspeedtestpro_selected_provider" name="wpspeedtestpro_selected_provider">';
            echo '<option value="">Select a provider</option>';
            foreach ($providers as $provider) {
                $provider_id = esc_attr($provider['id']);
                echo '<option value="' . $provider_id . '"' . selected($selected_provider_id, $provider_id, false) . '>';
                echo esc_html($provider['name']);
                echo '</option>';
            }
            echo '</select>';
        } else {
            echo '<p class="wpspeedtestpro-error">No hosting providers available. Please check your internet connection or try again later.</p>';
        }
    }
    
    public function hosting_package_dropdown_callback() {
        $selected_provider_id = get_option('wpspeedtestpro_selected_provider');
        $selected_package_id = get_option('wpspeedtestpro_selected_package');
        $providers = $this->core->api->get_hosting_providers();
    
        echo '<select id="wpspeedtestpro_selected_package" name="wpspeedtestpro_selected_package">';
        echo '<option value="">Select a package</option>';
    
        if ($selected_provider_id && !empty($providers)) {
            foreach ($providers as $provider) {
                if ($provider['id'] == $selected_provider_id) {
                    foreach ($provider['packages'] as $package) {
                        $package_id = esc_attr($package['Package_ID']);
                        echo '<option value="' . $package_id . '"' . selected($selected_package_id, $package_id, false) . '>';
                        echo esc_html($package['type'] . ' - ' . $package['description']);
                        echo '</option>';
                    }
                    break;
                }
            }
        }
        echo '</select>';
    
        if (!$selected_provider_id) {
            echo '<p class="description">Please select a provider first.</p>';
        }
    }

    public function render_data_collection_field() {
        $option = get_option('wpspeedtestpro_allow_data_collection', true);
        ?>
        <input type="checkbox" id="wpspeedtestpro_allow_data_collection" name="wpspeedtestpro_allow_data_collection" value="1" <?php checked($option, true); ?>>
        <label for="wpspeedtestpro_allow_data_collection">Allow anonymous data collection</label>
        <p class="description">Help improve our plugin by allowing anonymous data collection. <a href="https://wpspeedtestpro.com/privacy-policy" target="_blank">Learn more about our privacy policy</a>.</p>
        <?php
    }

    private function get_gcp_endpoints() {
        try {
            $gcp_endpoints = $this->core->api->get_gcp_endpoints();
            if (empty($gcp_endpoints)) {
                throw new Exception('No GCP endpoints returned from API');
            }
            return $gcp_endpoints;
        } catch (Exception $e) {
            error_log('Error fetching GCP endpoints: ' . $e->getMessage());
            // Return some default regions if API call fails
            return array(
                array('region_name' => 'us-central1'),
                array('region_name' => 'europe-west1'),
                array('region_name' => 'asia-east1')
            );
        }
    }

    public function ajax_get_gcp_endpoints() {
        check_ajax_referer('wpspeedtestpro_ajax_nonce', 'nonce');

        try {
            $gcp_endpoints = $this->core->api->get_gcp_endpoints();
            if (empty($gcp_endpoints)) {
                throw new Exception('No GCP endpoints returned from API');
            }
            return wp_send_json_success($gcp_endpoints);
        } catch (Exception $e) {
            error_log('Error fetching GCP endpoints: ' . $e->getMessage());
            // Return some default regions if API call fails
            return wp_send_json_error(array(
                array('region_name' => 'us-central1'),
                array('region_name' => 'europe-west1'),
                array('region_name' => 'asia-east1')
            ));
        }
    }


    private function get_hosting_providers() {
        try {
            $providers = $this->core->api->get_hosting_providers();
            if (empty($providers)) {
                throw new Exception('No hosting providers returned from API');
            }
            return $providers;
        } catch (Exception $e) {
            error_log('Error fetching hosting providers: ' . $e->getMessage());
            // Return some default providers if API call fails
            return array(
                array('name' => 'Provider A', 'packages' => array(array('type' => 'Basic'), array('type' => 'Pro'))),
                array('name' => 'Provider B', 'packages' => array(array('type' => 'Starter'), array('type' => 'Business')))
            );
        }
    }

    public function ajax_get_provider_packages() {
        check_ajax_referer('wpspeedtestpro_ajax_nonce', 'nonce');
    
        $provider_id = absint($_POST['provider']); // Convert to integer and sanitize
        $providers = $this->core->api->get_hosting_providers();
    
        $packages = array();
        foreach ($providers as $provider) {
            if ($provider['id'] === $provider_id) {
                $packages = $provider['packages'];
                break;
            }
        }
    
        wp_send_json_success($packages);
    }
    

    public function ajax_get_hosting_providers() {
        check_ajax_referer('wpspeedtestpro_ajax_nonce', 'nonce');

        $hosting_providers = $this->core->api->get_hosting_providers();

        wp_send_json_success($hosting_providers);
    }
    


    
    public function uptimerobot_api_key_callback() {
        $api_key = get_option('wpspeedtestpro_uptimerobot_api_key');
        echo '<input type="text" id="wpspeedtestpro_uptimerobot_api_key" name="wpspeedtestpro_uptimerobot_api_key" value="' . esc_attr($api_key) . '" class="regular-text">';
        echo '<p class="description">Enter your UptimeRobot API key. You can find your API key in your <a href="https://dashboard.uptimerobot.com/integrations?rid=97f3dfd4e3a8a6" target="_blank">Uptime account settings</a>. <br /> Please create a <b>Main API key</b></p>';
    }

    public static function render_api_key_field() {
        $api_key = get_option('wpspeedtestpro_pagespeed_api_key', '');
        ?>
        <input type="text" 
               name="wpspeedtestpro_pagespeed_api_key" 
               value="<?php echo esc_attr($api_key); ?>" 
               class="regular-text">
        <p class="description">
            Enter your Google PageSpeed Insights API key. 
            <a href="https://developers.google.com/speed/docs/insights/v5/get-started" 
               target="_blank">Get an API key</a>
        </p>
        <?php
    }
}

