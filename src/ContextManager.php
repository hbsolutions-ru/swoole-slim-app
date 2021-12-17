<?php declare(strict_types=1);

namespace HBS\SwooleSlimApp;

use Swoole\Coroutine;

/**
 * Class ContextManager
 *
 * @package HBS\SwooleSlimApp
 * @see https://www.swoole.co.uk/article/isolating-variables-with-coroutine-context
 */
final class ContextManager
{
    /**
     * Set is used to save a new value under the context
     *
     * @param string $key
     * @param mixed $value
     */
    public static function set(string $key, $value): void
    {
        Coroutine::getContext()[$key] = $value;
    }

    /**
     * Navigate the coroutine tree and search for the requested key
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        // Get the current coroutine ID
        $cid = Coroutine::getCid();

        do {
            // Get the context object using the current coroutine ID and check if our key exists, looping through the coroutine tree if we are deep inside sub coroutines.
            if (isset(Coroutine::getContext($cid)[$key])) {
                return Coroutine::getContext($cid)[$key];
            }

            // We may be inside a child coroutine, let's check the parent ID for a context
            $cid = Coroutine::getPcid($cid);

        } while ($cid !== -1 && $cid !== false);

        // The requested context variable and value could not be found
        if ($default !== null) {
            return $default;
        }

        throw new \InvalidArgumentException(
            sprintf("Could not find `%s` in current coroutine context", $key)
        );
    }
}
