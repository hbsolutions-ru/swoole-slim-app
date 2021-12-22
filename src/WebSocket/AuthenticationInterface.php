<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp\WebSocket;

use Swoole\Http\Request;

interface AuthenticationInterface
{
    /**
     * Returns unique identifier of the connections group
     * For example: user id to serve connections from the same user
     *
     * @param Request $request Swoole Server Request
     * @param array $params Other params from register method of the Connection Manager
     * @return string
     * @throws \HBS\SwooleSlimApp\Exception\WebSocketConnectionException
     */
    public function authenticate(Request $request, ...$params): string;
}
