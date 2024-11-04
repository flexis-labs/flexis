<?php

/**
 * Part of the Flexis Framework DI Package
 */

namespace Flexis\DIP\Exception;

use Psr\Container\NotFoundExceptionInterface;

/**
 * В контейнере не было обнаружено никаких записей.
 */
class KeyNotFoundException extends \InvalidArgumentException implements NotFoundExceptionInterface {}
