<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt\Exception;

/**
 * Исключение, представляющее недопустимый тип Flexis\Crypt\Key для шифрования.
 */
class InvalidKeyTypeException extends \InvalidArgumentException implements CryptExceptionInterface {
    /**
     * Конструктор InvalidKeyTypeException.
     *
     * @param   string  $expectedKeyType  Ожидаемый тип ключа.
     * @param   string  $actualKeyType    Фактический тип ключа.
     */
    public function __construct(string $expectedKeyType, string $actualKeyType) {
        parent::__construct("Неверный тип ключа: $actualKeyType. Ожидание: $expectedKeyType.");
    }
}
