<?php
declare(strict_types=1);

namespace App\Session;

interface SessionStoreInterface
{
    /**
     * Gets an attribute by key.
     *
     * @param string $key The key name or null to get all values
     * @param mixed $default The default value
     *
     * @return mixed The value. Returns default if the key is not found
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Sets an attribute by key.
     *
     * @param string $key The key of the element to set
     * @param mixed $value The data to set
     *
     * @return void
     */
    public function set(string $key, mixed $value): void;

    /**
     * Check if an attribute key exists.
     *
     * @param string $key The key
     *
     * @return bool True if the key is set or not
     */
    public function has(string $key): bool;

    /**
     * Removes an attribute by key.
     *
     * @param string $key The key to remove
     */
    public function remove(string $key): void;

    /**
     * Gets all values as array.
     *
     * @return array<string, mixed> The session values
     */
    public function all(): array;
}
