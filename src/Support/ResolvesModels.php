<?php

namespace DigitalCardKit\Laravel\Support;

use DigitalCardKit\Laravel\Models\DigitalBusinessCard;
use Illuminate\Database\Eloquent\Model;

trait ResolvesModels
{
    /** @return class-string<Model> */
    protected function modelClass(string $key): string
    {
        return config("digital-business-cards.models.{$key}");
    }

    protected function resolveCard(string $routeKey): DigitalBusinessCard
    {
        $class = $this->modelClass('card');
        $model = new $class;

        return $class::query()
            ->where($model->getRouteKeyName(), $routeKey)
            ->firstOrFail();
    }
}
