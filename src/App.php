<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp;

use Psr\Http\Message\{
    ResponseInterface as Response,
    ServerRequestInterface as Request,
};
use DI\ContainerBuilder;
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

final class App
{
    /**
     * @var SlimApp
     */
    private $app = null;

    /**
     * @var Server
     */
    private $server = null;

    /**
     * SwooleSlimApp constructor.
     *
     * @param string $host
     * @param int $port
     * @param string|array|\DI\Definition\Source\DefinitionSource ...$diContainerDefinitions
     */
    public function __construct(string $host, int $port, ...$diContainerDefinitions)
    {
        $containerBuilder = new ContainerBuilder();
        $containerBuilder->addDefinitions(...$diContainerDefinitions);

        try {
            $this->app = $containerBuilder->build()->get(SlimApp::class);
        } catch (\Exception $e) {
            printf("Failed to create Slim App Instance: %s\n", $e->getMessage());
            exit(1);
        }

        $this->server = new Server($host, $port ?: null);
    }

    /**
     * Init Slim routes and middleware, register 'request' event handler
     *
     * @param callable $middlewareCallable
     * @param callable $routesCallable
     */
    public function init(callable $middlewareCallable, callable $routesCallable): void
    {
        $this->initMiddleware($middlewareCallable);
        $this->initRoutes($routesCallable);

        $uriFactory = new Factory\UriFactory();
        $streamFactory = new Factory\StreamFactory();
        $responseFactory = new Factory\ResponseFactory();
        $uploadedFileFactory = new Factory\UploadedFileFactory();
        $responseMerger = new ResponseMerger;

        $this->server->on('request', function (SwooleRequest $request, SwooleResponse $response) use (
            $uriFactory,
            $streamFactory,
            $uploadedFileFactory,
            $responseFactory,
            $responseMerger
        ) {
            $psrRequest = new PsrSwooleRequest($request, $uriFactory, $streamFactory, $uploadedFileFactory);

            $psrResponse = $this->app->handle($psrRequest);

            $responseMerger->toSwoole($psrResponse, $response)->end();
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
        $this->server->start();
    }

    private function initMiddleware(callable $callable): void
    {
        // Parse json, form data and xml
        $this->app->addBodyParsingMiddleware();

        // Add CORS middleware
        $this->app->add(Middleware\CorsMiddleware::class);

        // Add the Slim built-in routing middleware. Should be added after CORS middleware so routing is performed first
        $this->app->addRoutingMiddleware();

        $callable($this->app);
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
