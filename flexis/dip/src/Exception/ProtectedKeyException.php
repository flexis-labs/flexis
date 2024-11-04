<?php

/**
 * Part of the Flexis Framework DI Package
 */

namespace Flexis\DIP\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Попытка установить значение защищенного ключа, которое уже установлено.
 */
class ProtectedKeyException extends \OutOfBoundsException implements ContainerExceptionInterface {}
