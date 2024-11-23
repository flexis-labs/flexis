<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt\Exception;

/**
 * Исключение, представляющее ошибку при создании ключа шифрования.
 */
class InvalidKeyException extends \RuntimeException implements CryptExceptionInterface {}
