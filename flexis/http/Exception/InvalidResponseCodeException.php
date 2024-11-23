<?php

/**
 * Часть пакета Flexis Http Framework.
 */

namespace Flexis\Http\Exception;

use Psr\Http\Client\ClientExceptionInterface;

/**
 * Исключение, представляющее недопустимый или неопределенный код ответа HTTP.
 */
class InvalidResponseCodeException extends \UnexpectedValueException implements ClientExceptionInterface {}
