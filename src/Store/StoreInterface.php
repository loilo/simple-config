<?php namespace Loilo\SimpleConfig\Store;

use JsonSerializable;

/**
 * A simple key-value store
 */
interface StoreInterface extends JsonSerializable
{
    /**
     * Override the store data
     *
     * @param array $data The data to use as store
     * @return void
     */
    public function store(array $data): void;

    /**
     * Get the whole store as an array
     *
     * @return array
     */
    public function all(): array;

    /**
     * Check whether the store contains a value under the given key
     *
     * @param string $key The key in the store to look up
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Get a value from the store
     *
     * @param string $key     The key in the store to look up
     * @param mixed  $default The value that will be returned if the key is not found
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Set a value in the store
     *
     * @param string $key   The key in the store to override
     * @param mixed  $value The value written to the store
     * @return void
     */
    public function set(string $key, $value): void;

    /**
     * Merge a set of key-value pairs into the store
     *
     * @param array $data The key-value pairs to merge into the store
     * @return void
     */
    public function merge(array $data): void;

    /**
     * Remove an item from the store
     *
     * @param string $key The key to remove from the store
     * @return void
     */
    public function delete(string $key): void;

    /**
     * Clear the store and make it empty
     *
     * @return void
     */
    public function clear(): void;
}
