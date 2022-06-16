<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp\Cache;

class SwooleCache extends SimpleCache
{
    public function getKeys(): array
    {
        $keys = [];
        $this->table->rewind();

        if (!$this->table->valid()) {
            return [];
        }

        for ($i = 0; $i < $this->table->count(); $i++) {
            $keys[] = $this->table->key();

            $this->table->next();
            if (!$this->table->valid()) {
                break;
            }
        }

        return $keys;
    }

    public function getMemoryUsage(): int
    {
        return $this->table->getMemorySize();
    }

    public function getRowsCount(): int
    {
        return $this->table->count();
    }

    public function toArray(?callable $filterFunction = null): array
    {
        $data = [];
        $this->table->rewind();

        if (!$this->table->valid()) {
            return [];
        }

        if ($filterFunction === null) {
            $filterFunction = function (string $key, $value): bool {
                return true;
            };
        }

        for ($i = 0; $i < $this->table->count(); $i++) {
            $key = $this->table->key();
            $row = $this->table->current();
            $value = $this->extractValue($row);

            if ($filterFunction($key, $value)) {
                $data[$key] = $value;
            }

            $this->table->next();
            if (!$this->table->valid()) {
                break;
            }
        }

        return $data;
    }

    /**
     * @param array|null $row
     * @return mixed|null
     */
    protected function extractValue(?array $row)
    {
        if (!is_array($row)) {
            return null;
        }

        $serialized = $row[self::COLUMN_NAME] ?? null;

        if (!is_string($serialized)) {
            return null;
        }

        $value = unserialize($serialized);

        if ($value === false && $serialized !== serialize(false)) {
            return null;
        }

        return $value;
    }
}
