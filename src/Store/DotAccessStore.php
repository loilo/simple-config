<?php namespace Loilo\SimpleConfig\Store;

use Adbar\Dot;

/**
 * A store implementation that allows to get/set/delete
 * deeply nested data via dot-notation paths
 */
class DotAccessStore implements StoreInterface
{
    /**
     * @var Dot
     */
    protected $store;

    /**
     * {@inheritdoc}
     */
    public function store(array $data): void
    {
        $this->store = new Dot($data);
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->store->all();
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return $this->store->has($key);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        return $this->store->get($key, $default);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        $this->store->set($key, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function merge(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->store->set($key, $value);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        $this->store->delete($key);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->store->clear();
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->store->jsonSerialize();
    }
}
