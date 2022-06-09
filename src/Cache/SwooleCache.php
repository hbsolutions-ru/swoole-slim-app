<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp\Cache;

class SwooleCache extends SimpleCache
{
    public function getRowsCount(): int
    {
        return $this->table->count();
    }

    public function getMemoryUsage(): int
    {
        return $this->table->getMemorySize();
    }
}
