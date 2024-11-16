<?php

/**
 * Part of the Flexis Framework DI Package
 */

namespace Flexis\DIP\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Контейнера нет в наличии.
 */
class ContainerNotFoundException extends \RuntimeException implements ContainerExceptionInterface {}
