<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp\Cache;

use Psr\SimpleCache\CacheInterface;
use Swoole\Table;

class SimpleCache implements CacheInterface
{
    protected const COLUMN_NAME = 'value';
    protected const COLUMN_SIZE = 2048;

    protected $table;

    public function __construct(int $tableSize, float $conflictProportion = 0.2, int $columnSize = 0)
    {
        $this->table = new Table($tableSize, $conflictProportion);

        $this->table->column(
            static::COLUMN_NAME,
            Table::TYPE_STRING,
            $columnSize ?: static::COLUMN_SIZE
        );

        $this->table->create();
    }

    public function get($key, $default = null)
    {
        $serialized = $this->table->get($key, static::COLUMN_NAME);

        if ($serialized === false) {
            return $default;
        }

        return unserialize($serialized);
    }

    public function set($key, $value, $ttl = null)
    {
        $serialized = serialize($value);

        return $this->table->set($key, [
            static::COLUMN_NAME => $serialized
        ]);
    }

    public function delete($key)
    {
        return $this->table->delete($key);
    }

    public function clear()
    {
        $keys = [];
        $this->table->rewind();

        for ($i = 0; $i < $this->table->count(); $i++) {
            $keys = $this->table->key();
            $this->table->next();
        }

        $keys = array_filter($keys);

        foreach ($keys as $key) {
            $this->table->delete($key);
        }

        return true;
    }

    public function getMultiple($keys, $default = null)
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    public function setMultiple($values, $ttl = null)
    {
        $result = true;

        foreach ($values as $key => $value) {
            $success = $this->set($key, $value);
            $result = $result && $success;
        }

        return $result;
    }

    public function deleteMultiple($keys)
    {
        $result = true;

        foreach ($keys as $key) {
            $success = $this->delete($key);
            $result = $result && $success;
        }

        return $result;
    }

    public function has($key)
    {
        return $this->table->exist($key);
    }
}
