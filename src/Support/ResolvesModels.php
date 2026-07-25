<?php

namespace DigitalCardKit\Laravel\Support;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait ResolvesModels
{
    /** @return class-string<Model> */
    protected function modelClass(string $key): string
    {
        return Config::model($key);
    }

    protected function resolveCard(string $routeKey): DigitalBusinessCard
    {
        return $this->cardQuery()->where($this->cardRouteKeyName(), $routeKey)->firstOrFail();
    }

    /**
     * Resolve a card the public routes are allowed to serve.
     *
     * An unpublished card is indistinguishable from one that does not exist,
     * and enforcing that in the query keeps every public endpoint from having
     * to remember its own visibility check.
     */
    protected function resolvePublishedCard(string $routeKey): DigitalBusinessCard
    {
        return $this->cardQuery()
            ->published()
            ->where($this->cardRouteKeyName(), $routeKey)
            ->firstOrFail();
    }

    /** @return Builder<DigitalBusinessCard> */
    private function cardQuery(): Builder
    {
        return Config::cardModel()::query();
    }

    private function cardRouteKeyName(): string
    {
        $class = Config::cardModel();

        return (new $class)->getRouteKeyName();
    }
}
