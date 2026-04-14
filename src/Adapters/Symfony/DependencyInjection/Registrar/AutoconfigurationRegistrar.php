<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Registrar;

use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zolta\Support\Application\Attributes\AsApplicationService;

final class AutoconfigurationRegistrar
{
    public function register(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->registerAttributeForAutoconfiguration(AsApplicationService::class, static function (ChildDefinition $childDefinition): void {
            $childDefinition->addTag('zolta.cqrs.app_service');
        });
    }
}
