<?php
defined('ABSPATH') || exit;

class SBA_Dashboard {

    public static function render() {
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-01');
        $to   = isset($_GET['to'])   ? sanitize_text_field($_GET['to'])   : date('Y-m-d');

        $statuses = [
            'sln-b-pendingpayment' => 'Pending Payment',
            'sln-b-pending'        => 'Pending',
            'sln-b-paid'           => 'Paid',
            'sln-b-paylater'       => 'Pay Later',
            'sln-b-canceled'       => 'Canceled',
            'sln-b-confirmed'      => 'Confirmed',
            'sln-b-error'          => 'Error',
        ];

        $selected_statuses = isset($_GET['status']) ? (array) $_GET['status'] : array_keys($statuses);

        $data = self::get_bookings_grouped_by_day($from, $to, $selected_statuses);
        $labels = json_encode(array_keys($data));
        $counts = json_encode(array_values($data));
        ?>
        <div class="wrap">
            <h1>Bookings Per Day</h1>

            <form method="get">
                <input type="hidden" name="page" value="salon-booking-analytics" />

                <label for="from">From:</label>
                <input type="date" name="from" id="from" value="<?= esc_attr($from) ?>" required>

                <label for="to">To:</label>
                <input type="date" name="to" id="to" value="<?= esc_attr($to) ?>" required>

                <label for="status">Booking Status:</label><br>
                <select name="status[]" id="status" multiple style="height: 120px; width: 300px;">
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= esc_attr($value) ?>" <?= in_array($value, $selected_statuses) ? 'selected' : '' ?>>
                            <?= esc_html($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <p><input type="submit" class="button button-primary" value="Apply Filters"></p>
            </form>

            <canvas id="bookingsChart" height="100"></canvas>

            <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ctx = document.getElementById('bookingsChart').getContext('2d');
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?= $labels ?>,
                        datasets: [{
                            label: 'Bookings',
                            data: <?= $counts ?>,
                            borderWidth: 2,
                            fill: false,
                            tension: 0.2
                        }]
                    },
                    options: {
                        responsive: true,
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
            </script>

            <?php if (current_user_can('manage_options')): ?>
                <h3>Debug Info</h3>
                <pre style="background: #fff; border: 1px solid #ccc; padding: 10px; overflow-x: auto;">
From: <?= esc_html($from) ?>  
To: <?= esc_html($to) ?>  
Selected Statuses: <?= esc_html(implode(', ', $selected_statuses)) ?>  
Data Received (grouped by date):
<?= esc_html(print_r($data, true)) ?>
                </pre>

                <?php
                $raw_debug = get_option('sba_last_raw_api_debug');
                if ($raw_debug) {
                    echo '<h3>Raw API Response</h3><pre>' . esc_html(print_r($raw_debug, true)) . '</pre>';
                }

                $debug_token = get_option('sba_debug_token_from_dashboard');
                if ($debug_token) {
                    echo '<h3>Token Used in Dashboard</h3><p><code>' . esc_html($debug_token) . '</code></p>';
                }

                $last_url = get_option('sba_debug_last_url');
                if ($last_url) {
                    echo '<h3>Last API URL</h3><p><code>' . esc_html($last_url) . '</code></p>';
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function get_bookings_grouped_by_day($from, $to, $statuses = []) {
        $base_url = sba_get_api_base_url();
        $token = sba_get_api_token();

        if (empty($token)) {
            return [];
        }

        if (current_user_can('manage_options')) {
            update_option('sba_debug_token_from_dashboard', $token);
        }

        $query_args = [
            'from_date' => $from,
            'to_date'   => $to,
        ];

        if (!empty($statuses)) {
            $query_args['statuses'] = implode(',', array_map('sanitize_text_field', $statuses));
        }

        $url = add_query_arg($query_args, $base_url . '/bookings');

        if (current_user_can('manage_options')) {
            update_option('sba_debug_last_url', $url);
            error_log('[SBA DEBUG] Final API URL: ' . $url);
        }

        $response = wp_remote_get($url, [
            'headers' => [
                'Access-Token' => $token,
                'Accept'       => 'application/json',
            ],
            'timeout' => 20,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $body = ltrim($body, "+ \t\n\r\0\x0B");
        $data = json_decode($body, true);

        if (current_user_can('manage_options')) {
            update_option('sba_last_raw_api_debug', $data);
        }

        $bookings = $data['items'] ?? [];

        $grouped = [];

        foreach ($bookings as $booking) {
            $date = $booking['date'] ?? '';

            if ($date) {
                if (!isset($grouped[$date])) {
                    $grouped[$date] = 0;
                }
                $grouped[$date]++;
            }
        }

        ksort($grouped);
        return $grouped;
    }
}
