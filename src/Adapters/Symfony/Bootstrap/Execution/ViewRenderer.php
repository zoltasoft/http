<?php

declare(strict_types=1);

namespace Zolta\Http\Symfony\Bootstrap\Execution;

use Psr\Container\ContainerInterface;
use Zolta\Http\Exceptions\ControllerConfigurationException;

final readonly class ViewRenderer
{
    public function __construct(private ContainerInterface $container) {}

    public function render(string $view, array $context): string
    {
        if (! $this->container->has('twig')) {
            throw new ControllerConfigurationException(
                errorCode: 'view.renderer.missing'
            );
        }

        return $this->container->get('twig')->render($view, $context);
    }
}
