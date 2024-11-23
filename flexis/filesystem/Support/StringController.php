<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem\Support;

/**
 * Строковый контроллер.
 */
class StringController {
    /**
     * Внутренние ссылки на строки.
     *
     * @var     array
     */
    private static array $strings = [];

    /**
     * Определяет переменную как массив.
     *
     * @return  array
     */
    public static function getArray(): array {
        return self::$strings;
    }

    /**
     * Создаёт ссылку.
     *
     * @param   string  $reference  Ключ
     * @param   string  $string     Значение
     *
     * @return  void
     *
     */
    public static function createRef(string $reference, string &$string): void {
        self::$strings[$reference] = & $string;
    }

    /**
     * Возвращает ссылку.
     *
     * @param   string  $reference  Ключ.
     *
     * @return  mixed  False, если не установлено. Ссылка, если она существует.
     *
     */
    public static function getRef(string $reference): mixed {
        if (isset(self::$strings[$reference])) {
            return self::$strings[$reference];
        }

        return false;
    }
}
