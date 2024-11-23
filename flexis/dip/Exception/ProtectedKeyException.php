<?php

/**
 * Часть пакета Flexis DIP Framework.
 */

namespace Flexis\DIP\Exception;

use Psr\Container\ContainerExceptionInterface;

/**
 * Попытка установить значение защищенного ключа, которое уже установлено.
 */
class ProtectedKeyException extends \OutOfBoundsException implements ContainerExceptionInterface {}
