<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp\Middleware;

use Psr\Http\Message\{
    ResponseInterface as Response,
    ServerRequestInterface as Request,
};
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use HBS\Helpers\DateTimeHelper;

final class RequestLoggerMiddleware implements RequestLoggerMiddlewareInterface
{
    private const DEFAULT_DATE_TIME_FORMAT = 'Y-m-d\\TH:i:s\\.uP';

    /**
     * @var string
     */
    private $dateTimeFormat;

    public function __construct(?string $dateTimeFormat = null)
    {
        $this->dateTimeFormat = $dateTimeFormat ?? self::DEFAULT_DATE_TIME_FORMAT;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $now = DateTimeHelper::now();

        $executionTime = -hrtime(true);

        $response = $handler->handle($request);

        $executionTime += hrtime(true);

        printf(
            "[%s] \"%s %s HTTP/%s\" %d %d %.3f" . PHP_EOL,
            $now->format($this->dateTimeFormat),
            strtoupper($request->getMethod()),
            $request->getUri()->getPath(),
            $request->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getBody()->getSize(),
            $executionTime / 1e+6, // Nanoseconds to Milliseconds
        );

        return $response;
    }
}
