<?php

declare(strict_types=1);

namespace Tests\Integration;

use MikroPlaneta\Booking\Core\Models\PaymentTransaction;
use MikroPlaneta\Booking\Core\Repositories\PaymentTransactionRepository;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../core/models/class-payment-transaction.php';
require_once __DIR__ . '/../../core/repositories/class-payment-transaction-repository.php';

class PaymentTransactionRepositoryTest extends TestCase {

    private function makeFakeWpdb(array $config = []): object {
        return new class($config) {
            public string $prefix = 'wp_';
            public int $insert_id = 0;
            private array $config;

            public function __construct(array $config) {
                $this->config = $config;
                $this->insert_id = $config['insert_id'] ?? 0;
            }

            public function prepare($query, ...$args): string {
                return (string) $query;
            }

            public function get_charset_collate(): string {
                return '';
            }

            /** @return int|false */
            public function insert(string $table, array $data, array $formats = []) {
                $result = $this->config['insert_result'] ?? 1;
                if ($result) {
                    $this->insert_id = $this->config['insert_id'] ?? 1;
                }
                return $result;
            }

            public function get_row($query, string $output = 'OBJECT') {
                return $this->config['get_row'] ?? null;
            }

            public function get_results($query, string $output = 'OBJECT'): array {
                return $this->config['get_results'] ?? [];
            }

            /** @return int|false */
            public function update(string $table, array $data, array $where, array $fmts = [], array $wFmts = []) {
                return $this->config['update_result'] ?? 1;
            }
        };
    }

    protected function setUp(): void {
        $GLOBALS['__mb_options'] = [];
    }

    public function testCreateReturnsTransactionWithCorrectData(): void {
        $row = [
            'id'                     => 42,
            'reservation_id'         => 10,
            'gateway'                => 'bank_transfer',
            'gateway_transaction_id' => null,
            'amount'                 => 150.00,
            'currency'               => 'PLN',
            'status'                 => PaymentTransaction::STATUS_PENDING,
            'payment_method'         => null,
            'idempotency_key'        => 'key-abc',
            'raw_response'           => null,
            'ip_address'             => null,
            'created_at'             => '2026-04-09 12:00:00',
            'updated_at'             => '2026-04-09 12:00:00',
        ];

        $GLOBALS['wpdb'] = $this->makeFakeWpdb([
            'insert_id'     => 42,
            'insert_result' => 1,
            'get_row'       => $row,
        ]);

        $repo = new PaymentTransactionRepository();
        $tx = $repo->create([
            'reservation_id'  => 10,
            'gateway'         => 'bank_transfer',
            'amount'          => 150.00,
            'currency'        => 'PLN',
            'idempotency_key' => 'key-abc',
        ]);

        $this->assertInstanceOf(PaymentTransaction::class, $tx);
        $this->assertSame(42, $tx->id);
        $this->assertSame('bank_transfer', $tx->gateway);
        $this->assertSame(150.00, $tx->amount);
        $this->assertSame('PLN', $tx->currency);
        $this->assertSame(PaymentTransaction::STATUS_PENDING, $tx->status);
    }

    public function testCreateReturnsNullWhenInsertFails(): void {
        $GLOBALS['wpdb'] = $this->makeFakeWpdb([
            'insert_result' => false,
            'insert_id'     => 0,
        ]);

        $repo = new PaymentTransactionRepository();
        $result = $repo->create([
            'reservation_id'  => 1,
            'gateway'         => 'bank_transfer',
            'amount'          => 100.0,
            'idempotency_key' => 'x',
        ]);

        $this->assertNull($result);
    }

    public function testFindReturnsNullWhenRowNotFound(): void {
        $GLOBALS['wpdb'] = $this->makeFakeWpdb(['get_row' => null]);

        $repo = new PaymentTransactionRepository();
        $result = $repo->find(999);

        $this->assertNull($result);
    }

    public function testFindReturnsTransactionWhenRowExists(): void {
        $row = [
            'id'                     => 5,
            'reservation_id'         => 20,
            'gateway'                => 'bank_transfer',
            'gateway_transaction_id' => null,
            'amount'                 => 200.0,
            'currency'               => 'PLN',
            'status'                 => PaymentTransaction::STATUS_COMPLETED,
            'payment_method'         => null,
            'idempotency_key'        => 'idem-1',
            'raw_response'           => null,
            'ip_address'             => null,
            'created_at'             => '2026-04-09 10:00:00',
            'updated_at'             => '2026-04-09 10:00:00',
        ];

        $GLOBALS['wpdb'] = $this->makeFakeWpdb(['get_row' => $row]);

        $repo = new PaymentTransactionRepository();
        $tx = $repo->find(5);

        $this->assertInstanceOf(PaymentTransaction::class, $tx);
        $this->assertSame(5, $tx->id);
        $this->assertSame(PaymentTransaction::STATUS_COMPLETED, $tx->status);
    }

    public function testUpdateStatusReturnsTrueOnSuccess(): void {
        $GLOBALS['wpdb'] = $this->makeFakeWpdb(['update_result' => 1]);

        $repo = new PaymentTransactionRepository();
        $result = $repo->updateStatus(1, PaymentTransaction::STATUS_COMPLETED);

        $this->assertTrue($result);
    }

    public function testUpdateStatusReturnsFalseOnDatabaseError(): void {
        $GLOBALS['wpdb'] = $this->makeFakeWpdb(['update_result' => false]);

        $repo = new PaymentTransactionRepository();
        $result = $repo->updateStatus(1, PaymentTransaction::STATUS_FAILED);

        $this->assertFalse($result);
    }

    public function testFindByReservationIdReturnsMultipleTransactions(): void {
        $rows = [
            [
                'id'                     => 1,
                'reservation_id'         => 7,
                'gateway'                => 'bank_transfer',
                'gateway_transaction_id' => null,
                'amount'                 => 100.0,
                'currency'               => 'PLN',
                'status'                 => PaymentTransaction::STATUS_PENDING,
                'payment_method'         => null,
                'idempotency_key'        => 'k1',
                'raw_response'           => null,
                'ip_address'             => null,
                'created_at'             => '2026-04-09 09:00:00',
                'updated_at'             => '2026-04-09 09:00:00',
            ],
            [
                'id'                     => 2,
                'reservation_id'         => 7,
                'gateway'                => 'bank_transfer',
                'gateway_transaction_id' => null,
                'amount'                 => 50.0,
                'currency'               => 'PLN',
                'status'                 => PaymentTransaction::STATUS_FAILED,
                'payment_method'         => null,
                'idempotency_key'        => 'k2',
                'raw_response'           => null,
                'ip_address'             => null,
                'created_at'             => '2026-04-09 08:00:00',
                'updated_at'             => '2026-04-09 08:00:00',
            ],
        ];

        $GLOBALS['wpdb'] = $this->makeFakeWpdb(['get_results' => $rows]);

        $repo = new PaymentTransactionRepository();
        $transactions = $repo->findByReservationId(7);

        $this->assertCount(2, $transactions);
        $this->assertInstanceOf(PaymentTransaction::class, $transactions[0]);
        $this->assertSame(PaymentTransaction::STATUS_PENDING, $transactions[0]->status);
    }
}
