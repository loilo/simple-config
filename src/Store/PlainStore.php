<?php namespace Loilo\SimpleConfig\Store;

/**
 * A simplistic, unnested key-value store
 */
class PlainStore implements StoreInterface
{
    /**
     * @var array
     */
    protected $store;

    /**
     * {@inheritdoc}
     */
    public function store(array $data): void
    {
        $this->store = $data;
    }

    /**
     * {@inheritdoc}
     */
    public function all(): array
    {
        return $this->store;
    }

    /**
     * {@inheritdoc}
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->store);
    }

    /**
     * {@inheritdoc}
     */
    public function get(string $key, $default = null)
    {
        if (isset($this->store[$key])) {
            return $this->store[$key];
        } else {
            return $default;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $key, $value): void
    {
        $this->store[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function merge(array $data): void
    {
        $this->store = array_merge($this->store, $data);
    }

    /**
     * {@inheritdoc}
     */
    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    /**
     * {@inheritdoc}
     */
    public function clear(): void
    {
        $this->store([]);
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        return $this->store;
    }
}
