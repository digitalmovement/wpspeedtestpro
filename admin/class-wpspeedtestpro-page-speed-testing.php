<?php

/**
 * The page speed testing functionality of the plugin.
 *
 * @link       https://wpspeedtestpro.com
 * @since      1.0.0
 *
 * @package    Wpspeedtestpro
 * @subpackage Wpspeedtestpro/admin
 */

/**
 * The page speed testing functionality of the plugin.
 *
 * Defines the plugin name, version, and hooks for the page speed testing functionality.
 *
 * @package    Wpspeedtestpro
 * @subpackage Wpspeedtestpro/admin
 * @author     WP Speedtest Pro Team <support@wpspeedtestpro.com>
 */
class Wpspeedtestpro_Page_Speed_Testing {

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

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
     */
  public function __construct( $plugin_name, $version, $core ) {

        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->core = $core;

        $this->speedvitals_schedule_events();

    }
    /**
     * Register the stylesheets for the page speed testing area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name . '-page-speed-testing', plugin_dir_url(__FILE__) . 'css/wpspeedtestpro-page-speed-testing.css', array(), $this->version, 'all');
    }

    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name . '-page-speed-testing', plugin_dir_url(__FILE__) . 'js/wpspeedtestpro-page-speed-testing.js', array('jquery'), $this->version, false);
        wp_localize_script($this->plugin_name . '-page-speed-testing', 'wpspeedtestpro_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpspeedtestpro-page-speed-testing-nonce')
        ));
    }

    public function display_page_speed_testing() {
        $api_key = get_option('wpspeedtestpro_speedvitals_api_key');
        if (empty($api_key)) {
            echo '<div class="notice notice-error"><p>Please enter your SpeedVitals API key in the <a href="' . admin_url('admin.php?page=wpspeedtestpro-settings') . '">settings page</a> before running tests.</p></div>';
            return;
        }

        $data = array(
            'pages_and_posts' => $this->speedvitals_get_pages_and_posts(),
            'locations' => $this->speedvitals_get_test_locations(),
            'devices' => $this->speedvitals_get_test_devices(),
            'frequencies' => $this->speedvitals_get_test_frequencies(),
            'test_results' => $this->core->db->speedvitals_get_test_results(20),
            'scheduled_tests' => $this->core->db->speedvitals_get_scheduled_tests(),
            'credits' => $this->speedvitals_get_account_credits()
        );

        include(plugin_dir_path(__FILE__) . 'partials/wpspeedtestpro-page-speed-testing-display.php');
    }

    private function speedvitals_get_pages_and_posts() {
        $args = array(
            'post_type' => array('page', 'post'),
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        );
        $query = new WP_Query($args);
        $pages_and_posts = array();
        foreach ($query->posts as $post) {
            $pages_and_posts[$post->ID] = $post->post_title;
        }
        return $pages_and_posts;
    }

    private function speedvitals_get_test_locations() {
        return array(
            'us' => 'South Carolina, US',
            'ca' => 'Montreal, Canada',
            'br' => 'São Paulo, Brazil',
            'de' => 'Frankfurt, Germany',
            'uk' => 'London, UK',
            'nl' => 'Netherlands',
            'in' => 'Mumbai, India',
            'jp' => 'Tokyo, Japan',
            'au' => 'Sydney, Australia',
            'id' => 'Jakarta, Indonesia'
        );
    }

    private function speedvitals_get_test_devices() {
        return array(
            'mobile' => 'Mobile',
            'desktop' => 'Desktop',
            'highEndLaptop' => 'High-End Laptop',
            'macbookAirM1' => 'MacBook Air (2020)',
            'ipad102' => 'iPad 10.2',
            'galaxyTabS7' => 'Samsung Galaxy Tab S7',
            'iphone13ProMax' => 'iPhone 13 Pro Max',
            'redmiNote8Pro' => 'Xiaomi Redmi Note 8 Pro',
            'galaxyA50' => 'Samsung Galaxy A50',
            'redmi5A' => 'Xiaomi Redmi 5A'
        );
    }

    private function speedvitals_get_test_frequencies() {
        return array(
            'one_off' => 'One-off',
            'daily' => 'Once Daily',
            'weekly' => 'Once Weekly'
        );
    }

    private function speedvitals_get_account_credits() {
        $api_key = get_option('wpspeedtestpro_speedvitals_api_key');
        return $this->core->api->speedvitals_get_account_credits($api_key);
    }

    public function speedvitals_ajax_run_test() {
        check_ajax_referer('wpspeedtestpro-page-speed-testing-nonce', 'nonce');

        $url = esc_url_raw($_POST['url']);
        $location = sanitize_text_field($_POST['location']);
        $device = sanitize_text_field($_POST['device']);
        $frequency = sanitize_text_field($_POST['frequency']);

        $api_key = get_option('wpspeedtestpro_speedvitals_api_key');
        $result = $this->core->api->speedvitals_run_test($api_key, $url, $location, $device);

        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        } else {
            $test_id = $this->core->db->speedvitals_insert_test_result($result);
            if ($frequency !== 'one_off') {
                $this->core->db->speedvitals_schedule_test($url, $location, $device, $frequency);
            }
            wp_send_json_success(array('test_id' => $test_id));
        }
    }

    public function speedvitals_ajax_get_test_status() {
        check_ajax_referer('wpspeedtestpro-page-speed-testing-nonce', 'nonce');

        $test_id = intval($_POST['test_id']);
        $result = $this->core->db->speedvitals_get_test_result($test_id);

        if ($result) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error('Test not found');
        }
    }

    public function speedvitals_ajax_cancel_scheduled_test() {
        check_ajax_referer('wpspeedtestpro-page-speed-testing-nonce', 'nonce');

        $schedule_id = intval($_POST['schedule_id']);
        $result = $this->core->db->speedvitals_cancel_scheduled_test($schedule_id);

        if ($result) {
            wp_send_json_success('Scheduled test cancelled successfully');
        } else {
            wp_send_json_error('Failed to cancel scheduled test');
        }
    }

    public function speedvitals_ajax_delete_old_results() {
        check_ajax_referer('wpspeedtestpro-page-speed-testing-nonce', 'nonce');

        $days = intval($_POST['days']);
        $result = $this->core->db->speedvitals_delete_old_results($days);

        if ($result) {
            wp_send_json_success('Old results deleted successfully');
        } else {
            wp_send_json_error('Failed to delete old results');
        }
    }

    public function speedvitals_schedule_events() {
        if (!wp_next_scheduled('speedvitals_run_scheduled_tests')) {
            wp_schedule_event(time(), 'hourly', 'speedvitals_run_scheduled_tests');
        }

        if (!wp_next_scheduled('speedvitals_check_pending_tests')) {
            wp_schedule_event(time(), '5min', 'speedvitals_check_pending_tests');
        }
    }

    public function speedvitals_run_scheduled_tests() {
        $scheduled_tests = $this->core->db->speedvitals_get_due_scheduled_tests();

        foreach ($scheduled_tests as $test) {
            $api_key = get_option('wpspeedtestpro_speedvitals_api_key');
            $result = $this->core->api->speedvitals_run_test($api_key, $test['url'], $test['location'], $test['device']);

            if (!is_wp_error($result)) {
                $this->core->db->speedvitals_insert_test_result($result);
                $this->core->db->speedvitals_update_scheduled_test($test['id']);
            }
        }
    }

    public function speedvitals_check_pending_tests() {
        $pending_tests = $this->core->db->speedvitals_get_pending_tests();
        $api_key = get_option('wpspeedtestpro_speedvitals_api_key');

        foreach ($pending_tests as $test) {
            $result = $this->core->api->speedvitals_get_test_result($api_key, $test['id']);

            if (!is_wp_error($result)) {
                $this->core->db->speedvitals_update_test_result($test['id'], $result);
            }
        }
    }



}