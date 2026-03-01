<?php
/**
 * Check Payment Options in Database
 * 
 * Access: http://gorytajemnic/wp-content/plugins/mikro-booking/check-options.php
 */

require_once dirname(__DIR__, 3) . '/wp-load.php';

if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Sprawdź Opcje</title>';
echo '<style>body{font-family:Arial,sans-serif;padding:40px;background:#f5f5f5}.card{background:#fff;padding:30px;border-radius:12px;max-width:800px;margin:0 auto;box-shadow:0 2px 10px rgba(0,0,0,0.1)}h1{color:#111827}table{width:100%;border-collapse:collapse;margin:20px 0}th,td{padding:12px;text-align:left;border-bottom:1px solid #e5e7eb}th{background:#f9fafb;font-weight:600}.yes{color:#10b981;font-weight:700}.no{color:#ef4444}.btn{display:inline-block;padding:10px 20px;background:#6366f1;color:#fff;text-decoration:none;border-radius:8px;margin:5px 5px 5px 0}</style></head><body>';
echo '<div class="card">';
echo '<h1>🔍 Sprawdzenie opcji płatności</h1>';

$options = [
    'mikroplaneta_booking_deposit_enabled' => 'Czy zaliczka włączona',
    'mikroplaneta_booking_deposit_percent' => 'Procent zaliczki (%)',
    'mikroplaneta_booking_payment_account' => 'Nr konta bankowego',
    'mikroplaneta_booking_payment_bank_name' => 'Nazwa banku',
    'mikroplaneta_booking_payment_additional_info' => 'Dodatkowe informacje',
    'mikroplaneta_booking_payment_options_added' => 'Czy opcje zostały dodane',
];

echo '<table>';
echo '<thead><tr><th>Nazwa opcji</th><th>Opis</th><th>Wartość</th><th>Status</th></tr></thead>';
echo '<tbody>';

foreach ($options as $name => $desc) {
    $value = get_option($name, '--- BRAK ---');
    $exists = ($value !== '--- BRAK ---' && $value !== false);
    $status = $exists ? '<span class="yes">✓ Jest</span>' : '<span class="no">✗ Brak</span>';
    $display_value = is_bool($value) ? ($value ? 'TAK' : 'NIE') : (string) $value;
    
    echo "<tr>";
    echo "<td><code>{$name}</code></td>";
    echo "<td>{$desc}</td>";
    echo "<td>" . ($display_value ?: '(puste)') . "</td>";
    echo "<td>{$status}</td>";
    echo "</tr>";
}

echo '</tbody></table>';

// Check if any are missing
$missing = [];
foreach ($options as $name => $desc) {
    $value = get_option($name);
    if ($value === false || $value === '') {
        $missing[] = $name;
    }
}

if (!empty($missing)) {
    echo '<div style="background:#fef2f2;border:2px solid #ef4444;color:#991b1b;padding:20px;border-radius:8px;margin:20px 0;">';
    echo '<h2 style="margin:0 0 10px;">❌ Brakujące opcje:</h2>';
    echo '<p>Następujące opcje nie zostały dodane do bazy:</p>';
    echo '<ul>';
    foreach ($missing as $name) {
        echo "<li><code>{$name}</code></li>";
    }
    echo '</ul>';
    echo '<a href="add-payment-options-now.php" class="btn">Dodaj opcje teraz</a>';
    echo '</div>';
} else {
    echo '<div style="background:#ecfdf5;border:2px solid #10b981;color:#065f46;padding:20px;border-radius:8px;margin:20px 0;">';
    echo '<h2 style="margin:0 0 10px;">✅ Wszystkie opcje są w bazie!</h2>';
    echo '<p>Możesz przejść do ustawień i skonfigurować płatności.</p>';
    echo '<a href="' . admin_url('admin.php?page=mikroplaneta-booking') . '" class="btn">Przejdź do Settings</a>';
    echo '</div>';
}

echo '</div></body></html>';
