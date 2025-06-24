<?php
class SBA_Dashboard {
    public static function render() {
        $statuses = [
            'sln-b-pendingpayment', 'sln-b-pending', 'sln-b-paid',
            'sln-b-paylater', 'sln-b-canceled', 'sln-b-confirmed', 'sln-b-error'
        ];

        $saved_statuses = isset($_GET['statuses']) ? explode(',', $_GET['statuses']) : $statuses;
        $from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
        $to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');

        $token = get_option('sba_access_token');
        $site_url = get_site_url();
        $base_url = rtrim($site_url, '/') . '/wp-json/salon/api/mobile/v1/bookings';

        $query = http_build_query([
            'from_date' => $from,
            'to_date' => $to,
            'statuses' => implode(',', $saved_statuses)
        ]);

        $url = $base_url . '?' . $query;

        $response = wp_remote_get($url, [
            'headers' => [
                'Access-Token' => $token
            ]
        ]);

        $bookings = [];
        if (!is_wp_error($response)) {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($body['status']) && $body['status'] === 'OK' && isset($body['items'])) {
                $bookings = $body['items'];
            }
        }

        $grouped = [];
        foreach ($bookings as $booking) {
            $date = $booking['date'];
            if (!isset($grouped[$date])) {
                $grouped[$date] = 0;
            }
            $grouped[$date]++;
        }

        echo '<div class="wrap">';
        echo '<h1>Salon Booking Analytics</h1>';
        echo '<form method="get">';
        echo '<input type="hidden" name="page" value="salon-booking-analytics">';
        echo 'From: <input type="date" name="from" value="' . esc_attr($from) . '">';
        echo ' To: <input type="date" name="to" value="' . esc_attr($to) . '">';

        echo '<select name="statuses[]" multiple style="width:300px; height:100px;">';
        foreach ($statuses as $status) {
            $selected = in_array($status, $saved_statuses) ? 'selected' : '';
            echo "<option value='$status' $selected>$status</option>";
        }
        echo '</select>';
        echo '<button type="submit" class="button">Filter</button>';
        echo '</form>';

        echo '<canvas id="bookingChart" height="100"></canvas>';

        echo '<script>
        const chartData = ' . json_encode($grouped) . ';
        </script>';

        echo '</div>';
    }
}