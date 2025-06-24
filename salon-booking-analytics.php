<?php
/*
Plugin Name: Salon Booking Analytics
Description: Add-on for Salon Booking System providing analytics and reporting features.
Version: 1.0
Author: Your Name
*/

defined('ABSPATH') || exit;

define('SBA_PATH', plugin_dir_path(__FILE__));
define('SBA_URL', plugin_dir_url(__FILE__));

require_once SBA_PATH . 'includes/class-sba-dashboard.php';

add_action('admin_menu', function () {
    add_submenu_page(
        'salon',
        'Analytics',
        'Analytics',
        'manage_options',
        'salon-booking-analytics',
        ['SBA_Dashboard', 'render']
    );

    add_submenu_page(
        'salon',
        'Analytics Settings',
        'Analytics Settings',
        'manage_options',
        'sba-settings',
        'sba_render_settings_page'
    );
});

add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'salon-booking-analytics') !== false) {
        // Load Chart.js from CDN (or enqueue your own if you prefer)
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        
        // Then load your own script (optional)
        wp_enqueue_script('sba-charts', SBA_URL . 'assets/js/charts.js', ['chartjs'], '1.0', true);
    }
});


add_action('admin_init', function () {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    if (!is_plugin_active('salon-booking-plugin-pro/salon.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Salon Booking System must be installed and active.');
    }
});

function sba_get_api_base_url() {
    return trailingslashit(get_site_url()) . 'wp-json/salon/api/mobile/v1';
}

function sba_get_api_token() {
    return get_option('sba_api_token');
}

function sba_request_api_token($username, $password) {
    $base_url = sba_get_api_base_url();
    $token_url = add_query_arg([
        'name'     => $username,
        'password' => $password,
    ], $base_url . '/login');

    $response = wp_remote_get($token_url, [
        'headers' => [
            'Accept' => 'application/json',
        ],
        'timeout' => 15,
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('api_error', 'Unable to contact login API.');
    }

    $body_raw = wp_remote_retrieve_body($response);
    $body = ltrim($body_raw, "+ \t\n\r\0\x0B"); // Strip unexpected "+" and whitespace
    $data = json_decode($body, true);

    if (empty($data) || $data['status'] !== 'OK' || empty($data['access_token'])) {
        return new WP_Error(
            'api_error',
            'Invalid token response: ' . json_encode($data),
            [
                'status_code' => wp_remote_retrieve_response_code($response),
                'body'        => $body_raw,
                'parsed'      => $data,
            ]
        );
    }

    update_option('sba_api_token', sanitize_text_field($data['access_token']));
    update_option('sba_admin_email', sanitize_text_field($username));

    return sanitize_text_field($data['access_token']);
}


function sba_render_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized');
    }

    $message = '';
    $error = '';
    $response_debug = '';

    if (isset($_POST['sba_submit'])) {
        check_admin_referer('sba_settings_nonce');

        $username = sanitize_text_field($_POST['sba_admin_email'] ?? '');
        $password = $_POST['sba_admin_password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $token = sba_request_api_token($username, $password);
            if (is_wp_error($token)) {
                $error = $token->get_error_message();
                if ($token->get_error_data()) {
                    $response_debug = '<pre>' . esc_html(print_r($token->get_error_data(), true)) . '</pre>';
                }
            } else {
                $message = 'API token saved successfully.';
            }
        }
    }

    $saved_username = get_option('sba_admin_email', '');
    $current_token = get_option('sba_api_token');
    $token_status = empty($current_token) ? '❌ Not connected' : '✅ Connected';

    $token = sba_get_api_token();

if (!empty($token)) {
    echo '<p><strong>Token Status:</strong> ✅ Connected</p>';

    if (current_user_can('manage_options')) {
        echo '<p><strong>Token Value:</strong> <code>' . esc_html($token) . '</code></p>';
    }
} else {
    echo '<p><strong>Token Status:</strong> ❌ Not Connected</p>';
}


    ?>
    <div class="wrap">
        <h1>Salon Booking Analytics Settings</h1>



        <h2>Token Status: <?= $token_status ?></h2>

        <?php if ($message): ?>
            <div class="notice notice-success"><p><?= esc_html($message) ?></p></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="notice notice-error"><p><?= esc_html($error) ?></p></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php wp_nonce_field('sba_settings_nonce'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><label for="sba_admin_email">Admin Username</label></th>
                    <td><input name="sba_admin_email" type="text" id="sba_admin_email" value="<?= esc_attr($saved_username) ?>" class="regular-text" required></td>
                </tr>
                <tr>
                    <th scope="row"><label for="sba_admin_password">Admin Password</label></th>
                    <td><input name="sba_admin_password" type="password" id="sba_admin_password" class="regular-text" required></td>
                </tr>
            </table>

            <?php submit_button('Save and Get Token', 'primary', 'sba_submit'); ?>
        </form>

        <?php if ($response_debug): ?>
            <h3>Debug Info</h3>
            <div class="notice notice-info"><?= $response_debug ?></div>
        <?php endif; ?>
    </div>
    <?php
}
