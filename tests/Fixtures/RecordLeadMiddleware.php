<?php

namespace DigitalCardKit\Laravel\Tests\Fixtures;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RecordLeadMiddleware
{
    /** @var array<int, string> */
    public static array $cards = [];

    /** @var array<int, string> */
    public static array $routeNames = [];

    public static bool $deny = false;

    public function handle(Request $request, Closure $next): Response
    {
        self::$cards[] = (string) $request->route('card');
        self::$routeNames[] = (string) $request->route()?->getName();

        if (self::$deny) {
            return response('Denied by configured lead middleware.', 403);
        }

        return $next($request);
    }
}
