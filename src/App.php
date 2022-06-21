<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp;

use Psr\Http\Message\{
    ResponseInterface as Response,
    ServerRequestInterface as Request,
};
use DI\{
    ContainerBuilder,
    Definition\Exception\InvalidDefinition,
    NotFoundException,
};
use Imefisto\PsrSwoole\{
    ServerRequest as PsrSwooleRequest,
    ResponseMerger,
};
use Slim\App as SlimApp;
use Slim\Psr7\Factory;
use Swoole\Http\{
    Request as SwooleRequest,
    Response as SwooleResponse,
};
use Swoole\WebSocket\Server;
use HBS\SwooleSlimApp\WebSocket\ConnectionManager;

final class App
{
    public const OPTION_WITH_REQUEST_LOGGER = 'withRequestLogger';

    /**
     * @var SlimApp
     */
    private $app = null;

    /**
     * @var Server
     */
    private $server = null;

    /**
     * @var ConnectionManager
     */
    private $connectionManager = null;

    /**
     * @var int
     */
    private $serverStartTimestamp = null;

    /**
     * SwooleSlimApp constructor.
     *
     * @param string|array|\DI\Definition\Source\DefinitionSource ...$diContainerDefinitions
     */
    public function __construct(...$diContainerDefinitions)
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions([
            App::class => $this,
        ]);
        $containerBuilder->addDefinitions(...$diContainerDefinitions);

        try {
            printf("Build DI container...\n");
            $container = $containerBuilder->build();
            printf("OK\n");
        } catch (\Exception $e) {
            printf("Failed to build DI container: %s\n", $e->getMessage());
            exit(1);
        }

        try {
            printf("Create Slim App Instance...\n");
            $this->app = $container->get(SlimApp::class);
            printf("OK\n");
        } catch (\Exception $e) {
            printf("Failed to create Slim App Instance: %s\n", $e->getMessage());
            exit(1);
        }

        try {
            printf("Create Swoole Server Instance...\n");
            $this->server = $container->get(Server::class);
            printf("OK\n");
        } catch (\Exception $e) {
            printf("Failed to create Swoole Server Instance: %s\n", $e->getMessage());
            exit(1);
        }

        /**
         * Need to instantiate Connection Manager here to get the same object in the future.
         * Due to some strangeness of the interaction between Swoole's request handlers
         * and PHP-DI container: multiple instances of the Connection Manager may be created
         * for an unknown reason.
         */
        try {
            printf("Create Connection Manager Instance...\n");
            $this->connectionManager = $container->get(ConnectionManager::class);
            printf("OK\n");
        } catch (NotFoundException $e) {
            printf("Connection Manager not found in DI container. Skip as not critical...\n");
        } catch (InvalidDefinition $e) {
            printf("Connection Manager not configured in DI container. Skip as not critical...\n");
        } catch (\Exception $e) {
            printf("Failed to create Connection Manager Instance: %s. Skip as not critical...\n", $e->getMessage());
        }
    }

    /**
     * Init Slim routes and middleware, register 'request' event handler
     *
     * @param callable $middlewareCallable
     * @param callable $routesCallable
     * @param array $options
     */
    public function init(callable $middlewareCallable, callable $routesCallable, array $options = []): void
    {
        $this->initMiddleware($middlewareCallable, boolval($options[static::OPTION_WITH_REQUEST_LOGGER] ?? false));
        $this->initRoutes($routesCallable);

        $uriFactory = new Factory\UriFactory();
        $streamFactory = new Factory\StreamFactory();
        $responseFactory = new Factory\ResponseFactory();
        $uploadedFileFactory = new Factory\UploadedFileFactory();
        $responseMerger = new ResponseMerger();

        $this->server->on('request', function (SwooleRequest $request, SwooleResponse $response) use (
            $uriFactory,
            $streamFactory,
            $uploadedFileFactory,
            $responseFactory,
            $responseMerger
        ) {
            $psrRequest = new PsrSwooleRequest($request, $uriFactory, $streamFactory, $uploadedFileFactory);

            $psrRequest = $psrRequest->withAttribute('remote_addr', $request->server['remote_addr'] ?? null);

            $psrResponse = $this->app->handle($psrRequest);

            $swooleResponse = $responseMerger->toSwoole($psrResponse, $response);

            if ($swooleResponse->isWritable()) {
                $swooleResponse->end();
            }
        });
    }

    /**
     * Returns Slim App
     *
     * @return SlimApp
     */
    public function getApp(): SlimApp
    {
        return $this->app;
    }

    /**
     * Returns Swoole Server
     *
     * @return Server
     */
    public function getServer(): Server
    {
        return $this->server;
    }

    /**
     * Starts Swoole Server
     */
    public function startServer(): void
    {
        $this->serverStartTimestamp = time();
        $this->server->start();
    }

    /**
     * @return ConnectionManager
     */
    public function getConnectionManager(): ConnectionManager
    {
        return $this->connectionManager;
    }

    public function getUptime(): int
    {
        if ($this->serverStartTimestamp === null) {
            return 0;
        }

        return time() - $this->serverStartTimestamp;
    }

    private function initMiddleware(callable $callable, bool $withRequestLogger): void
    {
        // Parse json, form data and xml
        $this->app->addBodyParsingMiddleware();

        // Add CORS middleware
        $this->app->add(Middleware\CorsMiddleware::class);

        // Add the Slim built-in routing middleware. Should be added after CORS middleware so routing is performed first
        $this->app->addRoutingMiddleware();

        $callable($this->app);

        if ($withRequestLogger) {
            $this->app->add(Middleware\RequestLoggerMiddlewareInterface::class);
        }
    }

    private function initRoutes(callable $callable): void
    {
        // Add CORS required routes
        $this->app->options('/{routes:.+}', function (Request $request, Response $response, array $args): Response {
            return $response;
        });

        $callable($this->app);
    }
}
