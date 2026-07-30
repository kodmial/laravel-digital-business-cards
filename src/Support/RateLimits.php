<?php

namespace DigitalCardKit\Laravel\Support;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

/**
 * Named limiters for the public write endpoints.
 *
 * A bare "throttle:10,1" is keyed by route and client address, so every card
 * in an installation shares one budget: traffic to a popular card locks
 * visitors out of an unrelated one. These limiters key on the card as well,
 * and keep a wider per-address cap so spreading requests across many cards is
 * still bounded.
 */
final class RateLimits
{
    public const LEADS = 'digital-business-cards-leads';

    public const EVENTS = 'digital-business-cards-events';

    private const DEFAULTS = [
        self::LEADS => ['per_card' => 10, 'per_ip' => 30],
        self::EVENTS => ['per_card' => 120, 'per_ip' => 600],
    ];

    public static function register(): void
    {
        foreach ([self::LEADS => 'leads', self::EVENTS => 'events'] as $name => $key) {
            RateLimiter::for($name, static fn (Request $request): array => self::limits($key, $request));
        }
    }

    public static function ensureLeadSubmissionIsAllowed(Request $request, string $card): void
    {
        $address = (string) $request->ip();
        $limits = [
            'leads|'.$card.'|'.$address => self::attempts('leads', 'per_card'),
            'leads|'.$address => self::attempts('leads', 'per_ip'),
        ];

        try {
            Cache::lock('digital-business-cards:lead-rate-limit:'.hash('sha256', $address), 10)
                ->block(1, static function () use ($limits): void {
                    foreach ($limits as $key => $attempts) {
                        if (RateLimiter::tooManyAttempts($key, $attempts)) {
                            throw new TooManyRequestsHttpException(RateLimiter::availableIn($key));
                        }
                    }

                    foreach (array_keys($limits) as $key) {
                        RateLimiter::hit($key, 60);
                    }
                });
        } catch (LockTimeoutException) {
            throw new TooManyRequestsHttpException(1);
        }
    }

    /** @return array<int, Limit> */
    private static function limits(string $key, Request $request): array
    {
        $address = (string) $request->ip();
        $card = (string) $request->route('card');

        return [
            Limit::perMinute(self::attempts($key, 'per_card'))->by($key.'|'.$card.'|'.$address),
            Limit::perMinute(self::attempts($key, 'per_ip'))->by($key.'|'.$address),
        ];
    }

    private static function attempts(string $key, string $scope): int
    {
        $name = $key === 'leads' ? self::LEADS : self::EVENTS;

        return max(1, (int) Config::get("rate_limits.{$key}.{$scope}", self::DEFAULTS[$name][$scope]));
    }
}
