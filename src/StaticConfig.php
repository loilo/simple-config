<?php namespace Loilo\SimpleConfig;

abstract class StaticConfig
{
    /**
     * Create a Config instance to use in the static interface
     *
     * @return Config
     */
    abstract public static function createConfig(): Config;

    /**
     * Config object
     *
     * @var Config
     */
    protected static $store = null;

    /**
     * Get the config instance
     *
     * @return Config
     */
    public static function getInstance(): Config
    {
        if (is_null(static::$store)) {
            static::$store = static::createConfig();
        }

        return static::$store;
    }

    /**
     * Get the config file path
     *
     * @return string
     */
    public static function getFilePath(): string
    {
        return static::getInstance()->getFilePath();
    }

    /**
     * Check whether the store contains a value under the given key
     *
     * @param string $key The key in the store to look up
     * @return bool
     */
    public static function has(string $key): bool
    {
        return static::getInstance()->has($key);
    }

    /**
     * Get a value from the store by key or return all values
     *
     * @param string|null $key     The key to look up in the store, yields the whole store if omitted
     * @param mixed       $default The value that is returned if the key can not be found in the store
     * @return mixed
     */
    public static function get(?string $key = null, $default = null)
    {
        return static::getInstance()->get($key, $default);
    }

    /**
     * Set one or multiple values in the store
     *
     * @param string|array $keyOrData Either the key to update or an array of key-value pairs to merge into the store
     *                                Note that if dot notation is enabled, keys of such key-value-pairs will be
     *                                resolved into actual arrays
     * @param mixed $value            The value to set, ignored if $keyOrData is passed an array
     *
     * @throws InvalidArgumentException When the first argument is invalid
     * @throws CryptoIOException When the in-memory data stream is not readable (should *never* happen)
     * @throws EnvironmentIsBrokenException When an invalid password format is used
     * @throws WrongKeyOrModifiedCiphertextException When an invalid password is used for encryption
     */
    public static function set($keyOrData, $value = null): void
    {
        static::getInstance()->set($keyOrData, $value);
    }

    /**
     * Delete a value from the store or clear the store altogether
     *
     * @param string|null $key If passed a key, that key is deleted from the store
     *                         If the key is omitted, the whole store will be cleared
     *
     * @throws CryptoIOException When the in-memory data stream is not readable (should *never* happen)
     * @throws EnvironmentIsBrokenException When an invalid password format is used
     * @throws WrongKeyOrModifiedCiphertextException When an invalid password is used for encryption
     */
    public static function delete(?string $key = null): void
    {
        static::getInstance()->delete($key);
    }
}
