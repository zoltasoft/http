<?php

declare(strict_types=1);

namespace Zolta\Http\Service\Laravel;

use Illuminate\Foundation\Application;
use Zolta\Framework\FrameworkAdapterInterface;
use Zolta\Http\Controller\Controller as CoreBaseController;
use Zolta\Http\Controller\Laravel\Controller;
use Zolta\Http\Request\BaseRequest as CoreBaseRequest;
use Zolta\Http\Request\Laravel\BaseRequest;

final class LaravelHttpAdapter implements FrameworkAdapterInterface
{
    public static function supports(): bool
    {
        return class_exists(Application::class)
            && function_exists('app');
    }

    public static function priority(): int
    {
        return 90;
    }

    /**
     * @return array<class-string, class-string>
     */
    public static function bindings(): array
    {
        return [
            CoreBaseRequest::class => BaseRequest::class,
            CoreBaseController::class => Controller::class,
        ];
    }
}
