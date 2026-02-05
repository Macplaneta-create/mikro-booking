<?php
/**
 * Database Migration Runner
 *
 * Executes database migrations in order
 * Tracks which migrations have been run
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

class Database {
    
    /**
     * Option name for tracking migrations
     */
    private const MIGRATIONS_OPTION = 'mikroplaneta_booking_migrations';
    
    /**
     * Run all pending migrations
     */
    public function migrate(): void {
        $executed = $this->get_executed_migrations();
        $migrations = $this->get_migration_files();
        
        foreach ($migrations as $migration) {
            if (!in_array($migration, $executed, true)) {
                try {
                    $this->run_migration($migration);
                    $this->mark_as_executed($migration);
                } catch (\Exception $e) {
                    error_log('Migration failed: ' . $migration . ' - ' . $e->getMessage());
                    throw $e;
                }
            }
        }
    }
    
    /**
     * Get list of executed migrations
     */
    private function get_executed_migrations(): array {
        return get_option(self::MIGRATIONS_OPTION, []);
    }
    
    /**
     * Get list of migration files
     */
    private function get_migration_files(): array {
        $migrations_dir = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/migrations/';
        
        if (!is_dir($migrations_dir)) {
            return [];
        }
        
        $files = glob($migrations_dir . '*.php');
        
        if ($files === false) {
            return [];
        }
        
        // Sort by filename (001-, 002-, etc.)
        sort($files);
        
        // Extract just the filenames
        return array_map(function($file) {
            return basename($file);
        }, $files);
    }
    
    /**
     * Run a single migration
     */
    private function run_migration(string $migration): void {
        $migration_file = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/migrations/' . $migration;
        
        if (!file_exists($migration_file)) {
            throw new \Exception("Migration file not found: {$migration}");
        }
        
        require_once $migration_file;
        
        // Extract class name from filename (e.g., 001-create-rooms.php -> Migration_001_Create_Rooms)
        $class_name = $this->get_migration_class_name($migration);
        $full_class_name = 'MikroPlaneta\\Booking\\Core\\Database\\Migrations\\' . $class_name;
        
        if (!class_exists($full_class_name)) {
            throw new \Exception("Migration class not found: {$full_class_name}");
        }
        
        // Run the migration
        call_user_func([$full_class_name, 'up']);
    }
    
    /**
     * Get migration class name from filename
     */
    private function get_migration_class_name(string $filename): string {
        // Remove .php extension
        $name = str_replace('.php', '', $filename);
        
        // Convert to class name (001-create-rooms -> Migration_001_Create_Rooms)
        $parts = explode('-', $name);
        $class_parts = array_map('ucfirst', $parts);
        
        return 'Migration_' . implode('_', $class_parts);
    }
    
    /**
     * Mark migration as executed
     */
    private function mark_as_executed(string $migration): void {
        $executed = $this->get_executed_migrations();
        $executed[] = $migration;
        update_option(self::MIGRATIONS_OPTION, $executed);
    }
    
    /**
     * Rollback last migration
     */
    public function rollback(): void {
        $executed = $this->get_executed_migrations();
        
        if (empty($executed)) {
            return;
        }
        
        $last_migration = array_pop($executed);
        $migration_file = MIKROPLANETA_BOOKING_PLUGIN_DIR . 'core/database/migrations/' . $last_migration;
        
        if (!file_exists($migration_file)) {
            return;
        }
        
        require_once $migration_file;
        
        $class_name = $this->get_migration_class_name($last_migration);
        $full_class_name = 'MikroPlaneta\\Booking\\Core\\Database\\Migrations\\' . $class_name;
        
        if (class_exists($full_class_name)) {
            call_user_func([$full_class_name, 'down']);
            update_option(self::MIGRATIONS_OPTION, $executed);
        }
    }
    
    /**
     * Get migration status
     */
    public function get_status(): array {
        $executed = $this->get_executed_migrations();
        $all_migrations = $this->get_migration_files();
        
        return [
            'total' => count($all_migrations),
            'executed' => count($executed),
            'pending' => count($all_migrations) - count($executed),
            'migrations' => array_map(function($migration) use ($executed) {
                return [
                    'name' => $migration,
                    'executed' => in_array($migration, $executed, true),
                ];
            }, $all_migrations),
        ];
    }
}
