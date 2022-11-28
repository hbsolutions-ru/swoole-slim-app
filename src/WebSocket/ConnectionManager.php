<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp\WebSocket;

use Swoole\Http\Request;
use Swoole\WebSocket\Server;
use HBS\SwooleSlimApp\Exception\WebSocketConnectionException;

class ConnectionManager
{
    protected Server $server;

    protected ConnectionTable $connectionTable;

    protected AuthenticationInterface $authService;

    public function __construct(
        Server $server,
        ConnectionTable $connectionTable,
        AuthenticationInterface $authService
    ) {
        $this->server = $server;
        $this->connectionTable = $connectionTable;
        $this->authService = $authService;
    }

    public function register(Request $request, ...$params): ?string
    {
        try {
            $uid = $this->authService->authenticate($request, ...$params);
        } catch (WebSocketConnectionException $e) {
            $this->server->disconnect($request->fd, $e->getCode(), $e->getMessage());
            return null;
        }

        $this->registerConnection($uid, $request->fd);
        return $uid;
    }

    public function push(string $uid, $data): void
    {
        $connections = $this->filterDisconnected($uid);

        foreach ($connections as $id) {
            $this->server->push($id, $data);
        }
    }

    public function getConnectionsAmount(): int
    {
        $connectionsAmount = 0;

        $this->connectionTable->rewind();
        for ($i = 0; $i < $this->connectionTable->count(); $i++) {
            $connectionsAmount += count($this->connectionTable->getCurrent());
            $this->connectionTable->next();
        }

        return $connectionsAmount;
    }

    protected function setConnections(string $uid, array $connections): array
    {
        $connections = array_filter(array_map('intval', $connections));
        return $this->connectionTable->setConnections($uid, $connections);
    }

    protected function filterDisconnected(string $uid): array
    {
        $connections = $this->connectionTable->getConnections($uid);

        $connections = array_filter($connections, function (int $connectionId) {
            return boolval($this->server->isEstablished($connectionId));
        });

        return $this->setConnections($uid, $connections);
    }

    protected function registerConnection(string $uid, int $connectionId): void
    {
        $connections = $this->connectionTable->getConnections($uid);

        if (in_array($connectionId, $connections, true)) {
            return;
        }

        $connections[] = $connectionId;
        $this->setConnections($uid, $connections);
    }
}
