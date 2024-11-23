<?php

/**
 * Часть пакета Flexis DIP Framework.
 */

namespace Flexis\DIP\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * В контейнере не было обнаружено никаких записей.
 */
class KeyNotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface {}
