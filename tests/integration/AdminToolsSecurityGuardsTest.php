<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

class AdminToolsSecurityGuardsTest extends TestCase {
    public function testForceRepairDbHasSecurityGuards(): void {
        $content = (string) file_get_contents(__DIR__ . '/../../force-repair-db.php');

        $this->assertStringContainsString("PHP_SAPI !== 'cli'", $content);
        $this->assertStringContainsString('current_user_can', $content);
        $this->assertStringContainsString('wp_verify_nonce', $content);
        $this->assertStringContainsString("__DIR__ . '/../../../wp-load.php'", $content);
    }

    public function testForceUpdateHasCapabilityNonceAndDebugGuards(): void {
        $content = (string) file_get_contents(__DIR__ . '/../../force-update.php');

        $this->assertStringContainsString("defined('WP_DEBUG')", $content);
        $this->assertStringContainsString('current_user_can', $content);
        $this->assertStringContainsString('wp_verify_nonce', $content);
    }

    public function testRunMigrationHasCapabilityNonceAndDebugGuards(): void {
        $content = (string) file_get_contents(__DIR__ . '/../../run-migration-017.php');

        $this->assertStringContainsString("__DIR__ . '/../../../wp-load.php'", $content);
        $this->assertStringContainsString("defined('WP_DEBUG')", $content);
        $this->assertStringContainsString('current_user_can', $content);
        $this->assertStringContainsString('wp_verify_nonce', $content);
    }
}
