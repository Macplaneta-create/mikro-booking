<?php
/**
 * Repository Interface
 *
 * Defines standard CRUD operations for all repositories
 *
 * @package MikroPlaneta\Booking
 * @since 1.0.0
 */

namespace MikroPlaneta\Booking\Core\Repositories;

if (!defined('ABSPATH')) {
    exit;
}

interface RepositoryInterface {
    
    /**
     * Find entity by ID
     *
     * @param int $id Entity ID
     * @return object|null Entity or null if not found
     */
    public function find(int $id): ?object;
    
    /**
     * Get all entities
     *
     * @param array $args Query arguments
     * @return array Array of entities
     */
    public function all(array $args = []): array;
    
    /**
     * Create new entity
     *
     * @param array $data Entity data
     * @return object Created entity
     */
    public function create(array $data): object;
    
    /**
     * Update existing entity
     *
     * @param int $id Entity ID
     * @param array $data Updated data
     * @return object Updated entity
     */
    public function update(int $id, array $data): object;
    
    /**
     * Delete entity
     *
     * @param int $id Entity ID
     * @return bool Success status
     */
    public function delete(int $id): bool;
}
