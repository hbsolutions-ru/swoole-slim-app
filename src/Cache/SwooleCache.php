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
}
