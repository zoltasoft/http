<?php

declare(strict_types=1);

namespace Zolta\Http\Controller;

use Zolta\Framework\FrameworkRegistry;

$bridgeClass = __NAMESPACE__.'\\FrameworkBoundController';
if (! class_exists($bridgeClass, false)) {
    $implementation = FrameworkRegistry::resolveBinding(Controller::class);

    if ($implementation !== null && class_exists($implementation)) {
        class_alias($implementation, $bridgeClass);
    } else {
        class FrameworkControllerFallback {}

        class_alias(FrameworkControllerFallback::class, $bridgeClass);
    }
}

// Provide a non-runtime stub for static analysis.
if (false) { // @phpstan-ignore-line
    abstract class FrameworkBoundController {}
}

class Controller extends FrameworkBoundController {}
