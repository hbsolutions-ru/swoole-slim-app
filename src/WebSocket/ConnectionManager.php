<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp\WebSocket;

use Swoole\Http\Request;
use Swoole\WebSocket\Server;
use HBS\SwooleSlimApp\Exception\WebSocketConnectionException;

class ConnectionManager
{
    /**
     * @var AuthenticationInterface
     */
    protected $authService;

    /**
     * @var ConnectionTable
     */
    protected $connectionTable;

    /**
     * @var Server
     */
    protected $server;

    public function __construct(
        AuthenticationInterface $authService,
        ConnectionTable $connectionTable,
        Server $server
    ) {
        $this->authService = $authService;
        $this->connectionTable = $connectionTable;
        $this->server = $server;
    }

    public function register(Request $request, ...$params): void
    {
        try {
            $uid = $this->authService->authenticate($request, ...$params);
        } catch (WebSocketConnectionException $e) {
            $this->server->disconnect($request->fd, $e->getCode(), $e->getMessage());
            return;
        }

        $this->registerConnection($uid, $request->fd);
    }

    public function push(string $uid, $data): void
    {
        $connections = $this->filterDisconnected($uid);

        foreach ($connections as $id) {
            $this->server->push($id, $data);
        }
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

        if (array_search($connectionId, $connections, true) !== false) {
            return;
        }

        array_push($connections, $connectionId);
        $this->setConnections($uid, $connections);
    }
}
