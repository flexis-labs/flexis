<?php

/**
 * Part of the Flexis Framework DI Package
 */

namespace Flexis\DIP\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Класс исключений для обработки ошибок при разрешении зависимости.
 */
class DependencyResolutionException extends \RuntimeException implements ContainerExceptionInterface {}
