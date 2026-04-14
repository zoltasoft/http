<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony;

use Illuminate\Foundation\Application;
use Symfony\Component\HttpKernel\Kernel as SymfonyKernel;
use Zolta\Framework\FrameworkAdapterInterface;
use Zolta\Http\Controller\Controller as CoreBaseController;
use Zolta\Http\Request\BaseRequest as CoreBaseRequest;
use Zolta\Http\Symfony\Controllers\Controller;
use Zolta\Http\Symfony\Requests\BaseRequest;

final class SymfonyHttpAdapter implements FrameworkAdapterInterface
{
    public static function supports(): bool
    {
        // Avoid Laravel even if it has Symfony components
        if (class_exists(Application::class)) {
            return false;
        }

        // Check if a Symfony Kernel subclass exists
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, SymfonyKernel::class, true)) {
                return true;
            }
        }

        return false;
    }

    public static function priority(): int
    {
        return 100;
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
