<?php

/**
 * Часть пакета Flexis DIP Framework.
 */

namespace Flexis\DIP\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Класс исключений для обработки ошибок при разрешении зависимости.
 */
class DependencyResolutionException extends \RuntimeException implements ContainerExceptionInterface {}
