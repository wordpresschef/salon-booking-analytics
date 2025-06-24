<?php
/*
Plugin Name: Salon Booking Analytics
Description: Add-on for Salon Booking System providing analytics and reporting features.
Version: 1.0
Author: wordpresschef
*/

defined('ABSPATH') || exit;

define('SBA_PATH', plugin_dir_path(__FILE__));
define('SBA_URL', plugin_dir_url(__FILE__));

// Ensure Salon Booking is active
add_action('admin_init', function () {
    if (!function_exists('is_plugin_active')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    if (!is_plugin_active('salon-booking-plugin-pro/salon.php')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Salon Booking System must be installed and active.');
    }
});

// Load plugin classes
require_once SBA_PATH . 'includes/class-sba-dashboard.php';
require_once SBA_PATH . 'includes/class-sba-reports.php';

// Register submenu page
add_action('admin_menu', function () {
    add_submenu_page(
        'salon', // Parent slug for Salon Booking System
        'Analytics',
        'Analytics',
        'manage_options',
        'salon-booking-analytics',
        ['SBA_Dashboard', 'render']
    );
});

// Load assets for dashboard
add_action('admin_enqueue_scripts', function ($hook) {
    if (strpos($hook, 'salon-booking-analytics') !== false) {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);
        wp_enqueue_script('sba-charts', SBA_URL . 'assets/js/charts.js', ['chart-js'], '1.0', true);
    }
});