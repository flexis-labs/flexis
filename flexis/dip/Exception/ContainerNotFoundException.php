<?php

/**
 * Часть пакета Flexis DIP Framework.
 */

namespace Flexis\DIP\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Контейнера нет в наличии.
 */
class ContainerNotFoundException extends \RuntimeException implements ContainerExceptionInterface {}
