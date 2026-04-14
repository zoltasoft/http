<?php

declare(strict_types=1);

namespace Zolta\Http\Exceptions;

use Zolta\Exceptions\BaseException;

class ControllerConfigurationException extends BaseException
{
    protected function exceptionMessage(): string
    {
        $context = $this->context();
        $errorCode = $this->getErrorCode();

        if ($errorCode === 'controller.configuration.invalid_handler' && isset($context['controller'], $context['method'])) {
            return sprintf(
                'Invalid handler %s::%s(). Class-level route attributes require the controller to implement __invoke() or register method-level routes.',
                $context['controller'],
                $context['method']
            );
        }

        if (isset($context['controller']) && isset($context['method'])) {
            return sprintf(
                'Controller configuration conflict in %s::%s(). Remove #[Request] attribute or use service pattern.',
                $context['controller'],
                $context['method']
            );
        }

        return 'Controller configuration conflict detected.';
    }
}
