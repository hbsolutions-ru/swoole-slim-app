# swoole-slim-app
Basis for REST API application on Slim 4 and Swoole Server.

> The library is in development so interfaces may change: do not use in production!

## Installation
`composer require hbsolutions/swoole-slim-app`

## Usage

App constructor takes the same parameters as [PHP-DI](https://php-di.org/doc/php-definitions.html)'s Container Builder method `addDefinitions`.
```php
use HBS\SwooleSlimApp\App;
use Slim\App as SlimApp;
use Swoole\WebSocket\Server;

$swooleSlimApp = new App(
    'path/to/your/php-di/definitions/config.php',
    [
        'some-definitions' => 'in-array',
    ]
);

$swooleSlimApp->init(
    function (SlimApp $app) {
        // Add some Slim middleware here
    },
    function (SlimApp $app) {
        // Add some Slim routes here
    },
    [
        App::OPTION_WITH_REQUEST_LOGGER => true,
    ]
);

// Get Swoole Server and register some events if needed
$swooleServer = $swooleSlimApp->getServer();

$swooleServer->on('start', function(Server $server)
{
    echo "Swoole Server is started!\n";
});

// Start the Server
$swooleSlimApp->startServer();
```