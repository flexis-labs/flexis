<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt\Exception;

/**
 * Исключение, представляющее ошибку расшифровки данных.
 */
class DecryptionException extends \RuntimeException implements CryptExceptionInterface {}
