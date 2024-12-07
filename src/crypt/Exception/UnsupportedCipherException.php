<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt\Exception;

/**
 * Исключение, представляющее ошибку шифрования данных.
 */
class UnsupportedCipherException extends \LogicException implements CryptExceptionInterface {
    /**
     * Конструктор UnsupportedCipherException.
     *
     * @param   string  $class  Имя класса неподдерживаемого шифра.
     */
    public function __construct(string $class) {
        parent::__construct("Шифр $class не поддерживается в этой среде.");
    }
}
