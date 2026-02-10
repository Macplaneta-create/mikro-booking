<?php
/**
 * Database Migration Admin Page
 *
 * Simple admin page to run database migrations
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

// Check if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Check user permissions
if (!current_user_can('manage_options')) {
    wp_die('Unauthorized access');
}

use MikroPlaneta\Booking\Core\Database\Database;

// Handle migration request
if (isset($_POST['run_migrations']) && check_admin_referer('mikroplaneta_run_migrations')) {
    try {
        $db = new Database();
        $db->migrate();
        echo '<div class="notice notice-success"><p>Migracje zostały pomyślnie wykonane!</p></div>';
    } catch (Exception $e) {
        echo '<div class="notice notice-error"><p>Błąd podczas migracji: ' . esc_html($e->getMessage()) . '</p></div>';
    }
}

// Get migration status
$db = new Database();
$status = $db->get_status();
?>

<div class="wrap">
    <h1>MikroPlaneta Booking - Migracje Bazy Danych</h1>
    
    <div class="card" style="max-width: 800px;">
        <h2>Status Migracji</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>Migracja</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status['migrations'] as $migration): ?>
                    <tr>
                        <td><?php echo esc_html($migration['name']); ?></td>
                        <td>
                            <?php if ($migration['executed']): ?>
                                <span style="color: green;">✓ Wykonano</span>
                            <?php else: ?>
                                <span style="color: orange;">⏳ Oczekuje</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <p style="margin-top: 20px;">
            <strong>Podsumowanie:</strong><br>
            Łącznie: <?php echo $status['total']; ?> migracji<br>
            Wykonanych: <?php echo $status['executed']; ?><br>
            Oczekujących: <?php echo $status['pending']; ?>
        </p>
        
        <?php if ($status['pending'] > 0): ?>
            <form method="post" style="margin-top: 20px;">
                <?php wp_nonce_field('mikroplaneta_run_migrations'); ?>
                <button type="submit" name="run_migrations" class="button button-primary button-large">
                    Uruchom Oczekujące Migracje (<?php echo $status['pending']; ?>)
                </button>
            </form>
        <?php else: ?>
            <p style="margin-top: 20px; color: green;">
                <strong>✓ Wszystkie migracje zostały wykonane!</strong>
            </p>
        <?php endif; ?>
    </div>
    
    <div class="card" style="max-width: 800px; margin-top: 20px; border-left: 4px solid #d63638;">
        <h2>🆘 Awaryjna Aktualizacja Bazy</h2>
        <p>
            Jeśli masz problemy z zapisywaniem danych lub widzisz błędy bazy danych, użyj tego przycisku, aby wymusić aktualizację struktury tabel.
        </p>
        <p>
            <a href="<?php echo admin_url('admin-post.php?action=mikroplaneta_force_update'); ?>" class="button button-secondary" onclick="return confirm('Czy na pewno chcesz wymusić aktualizację bazy danych?');">
                Wymuś Pełną Aktualizację Bazy Danych
            </a>
        </p>
    </div>
</div>
