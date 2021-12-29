<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp\WebSocket;

use Swoole\Table;

class ConnectionTable extends Table
{
    public const COLUMN_NAME = 'connections';
    public const COLUMN_SIZE = 1024;

    public function __construct(int $tableSize, float $conflictProportion = 0.2)
    {
        parent::__construct($tableSize, $conflictProportion);
        $this->initConnectionTable();
    }

    protected function initConnectionTable()
    {
        $this->column(
            static::COLUMN_NAME,
            Table::TYPE_STRING,
            static::COLUMN_SIZE
        );

        $this->create();
    }

    public function getConnections(string $key): array
    {
        $connections = $this->get($key, static::COLUMN_NAME);
        return ($connections && is_string($connections)) ? $this->decodeConnections($connections) : [];
    }

    public function getCurrent(): array
    {
        $row = $this->current();
        $connections = $row[static::COLUMN_NAME] ?? null;
        return ($connections && is_string($connections)) ? $this->decodeConnections($connections) : [];
    }

    /**
     * Saves connections for specified key and returns actual value
     *
     * @param string $key
     * @param array $connections
     * @return array
     */
    public function setConnections(string $key, array $connections): array
    {
        $connectionsString = json_encode($connections);

        if (strlen($connectionsString) >= static::COLUMN_SIZE) {
            $connections = array_filter([ array_pop($connections) ]);
            $connectionsString = json_encode($connections);
        }

        $this->set($key, [ static::COLUMN_NAME => $connectionsString ]);

        return $connections;
    }

    private function decodeConnections(string $connections): array
    {
        $connections = json_decode($connections, true);
        return is_array($connections) ? $connections : [];
    }
}
