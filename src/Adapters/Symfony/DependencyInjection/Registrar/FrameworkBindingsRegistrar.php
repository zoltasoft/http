<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\DependencyInjection\Registrar;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zolta\Http\Authorization\AuthorizationMatrix;
use Zolta\Http\Authorization\Interfaces\AuthorizationServiceInterface;
use Zolta\Http\Authorization\Symfony\SymfonyAuthorizationService;
use Zolta\Http\Request\Contracts\RequestPort;
use Zolta\Http\Request\Interfaces\ValidatorInterface;
use Zolta\Http\Service\Contracts\HTTP;
use Zolta\Http\Symfony\Services\Serialization\Normalizer;
use Zolta\Http\Symfony\Services\SymfonyHTTP;
use Zolta\Http\Symfony\Services\SymfonyRequestPort;
use Zolta\Http\Symfony\Services\SymfonyValidatorAdapter;
use Zolta\Support\Application\DTO\Output\ResponseDTO;
use Zolta\Support\ContainerRegistry;
use Zolta\Support\Contracts\NormalizerInterface;
use Zolta\Support\ZoltaForgeContainer;

final class FrameworkBindingsRegistrar
{
    public function register(ContainerBuilder $containerBuilder): void
    {
        ContainerRegistry::set(new ZoltaForgeContainer($containerBuilder));

        // Configure authorization matrix from Symfony parameters when available.
        if ($containerBuilder->hasParameter('zolta.security')) {
            $authConfig = $containerBuilder->getParameter('zolta.security');
            if (is_array($authConfig)) {
                AuthorizationMatrix::configure($authConfig);
            }
        }

        $containerBuilder->register(RequestPort::class, SymfonyRequestPort::class)
            ->addArgument(new Reference('request_stack'))
            ->setPublic(false);

        $containerBuilder->register(ValidatorInterface::class, SymfonyValidatorAdapter::class)
            ->addArgument(new Reference('validator'))
            ->setPublic(false);

        $containerBuilder->register(AuthorizationServiceInterface::class, SymfonyAuthorizationService::class)
            ->addArgument(new Reference('security.authorization_checker'))
            ->addArgument(new Reference('security.token_storage'))
            ->setPublic(false);

        $containerBuilder->register(HTTP::class, SymfonyHTTP::class)
            ->addArgument(new Reference('request_stack'))
            ->setPublic(false);

        $containerBuilder->register(NormalizerInterface::class, Normalizer::class)
            ->setPublic(true);

        ResponseDTO::setNormalizerFactory(static fn (): NormalizerInterface => new Normalizer);
    }
}
