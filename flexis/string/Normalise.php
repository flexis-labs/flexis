<?php

/**
 * Часть пакета Flexis Framework String.
 */

namespace Flexis\String;

/**
 * Flexis Framework String Normalise Class
 */
abstract class Normalise {
    /**
     * Метод преобразования строки из верблюжьего регистра.
     *
     * Этот метод предлагает два режима. 
     * Сгруппировано позволяет разбивать группы символов верхнего регистра следующим образом:
     *
     * "FooBarABCDef"            становится  array("Foo", "Bar", "ABC", "Def")
     * "JFooBar"                 становится  array("J", "Foo", "Bar")
     * "J001FooBar002"           становится  array("J001", "Foo", "Bar002")
     * "abcDef"                  становится  array("abc", "Def")
     * "abc_defGhi_Jkl"          становится  array("abc_def", "Ghi_Jkl")
     * "ThisIsA_NASAAstronaut"   становится  array("This", "Is", "A_NASA", "Astronaut"))
     * "JohnFitzgerald_Kennedy"  становится  array("John", "Fitzgerald_Kennedy"))
     *
     * Несгруппированные строки будут разделяться по каждому символу верхнего регистра.
     *
     * @param string  $input    Строковый ввод (только ASCII).
     * @param boolean $grouped  Опционально позволяет разбивать на группы символов верхнего регистра.
     *
     * @return  array|string  Строка, разделенная пробелами, в виде массива, если она сгруппирована.
     */
    public static function fromCamelCase(string $input, bool $grouped = false): array|string {
        return $grouped
            ? preg_split('/(?<=[^A-Z_])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][^A-Z_])/x', $input)
            : trim(preg_replace('#([A-Z])#', ' $1', $input));
    }

    /**
     * Метод преобразования строки в верблюжий регистр.
     *
     * @param string $input  Строковый ввод (только ASCII).
     *
     * @return  string  Верблюжья строка.
     */
    public static function toCamelCase(string $input): string {
        // Преобразуем слова в верхний регистр, а затем удалим пробелы.
        $input = static::toSpaceSeparated($input);
        $input = ucwords($input);
        $input = str_ireplace(' ', '', $input);

        return $input;
    }

    /**
     * Метод для преобразования строки в форму, разделенную тире.
     *
     * @param string $input  Строковый ввод (только ASCII).
     *
     * @return  string  Строка, разделенная тире.
     */
    public static function toDashSeparated(string $input): string {
        // Преобразуем пробелы и подчеркивания в тире.
        return preg_replace('#[ \-_]+#', '-', $input);
    }

    /**
     * Метод преобразования строки в форму, разделенную пробелами.
     *
     * @param string $input  Строковый ввод (только ASCII).
     *
     * @return  string  Строка, разделенная пробелом.
     */
    public static function toSpaceSeparated(string $input): string {
        // Преобразуем подчеркивания и тире в пробелы.
        return preg_replace('#[ \-_]+#', ' ', $input);
    }

    /**
     * Метод для преобразования строки в форму, разделенную подчеркиванием.
     *
     * @param string $input  Строковый ввод (только ASCII).
     *
     * @return  string  Строка, разделенная подчеркиванием.
     */
    public static function toUnderscoreSeparated(string $input): string {
        // Преобразуем пробелы и тире в подчеркивания.
        return preg_replace('#[ \-_]+#', '_', $input);
    }

    /**
     * Метод преобразования строки в форму переменной.
     *
     * @param string $input Строковый ввод (только ASCII).
     *
     * @return  string  Переменная строка.
     */
    public static function toVariable(string $input): string {
        // Удалим тире и подчеркивания, затем преобразуем в верблюжий регистр.
        $input = static::toCamelCase($input);
        // Удалим ведущие цифры.
        $input = preg_replace('#^[0-9]+#', '', $input);
        // В нижнем регистре первый символ.
        $input = lcfirst($input);

        return $input;
    }

    /**
     * Метод преобразования строки в ключевую форму.
     *
     * @param string $input  Строковый ввод (только ASCII).
     *
     * @return  string  Ключевая строка.
     */
    public static function toKey(string $input): string {
        // Удалим пробелы и тире, затем преобразуем в нижний регистр.
        return strtolower(static::toUnderscoreSeparated($input));
    }
}
