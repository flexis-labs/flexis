<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Языковой интерфейс Flexis Framework
 */
interface LocaliseInterface {
    /**
     * Функция транслитерации.
     *
     * Этот метод обрабатывает строку и заменяет все акцентированные символы UTF-8 безударными «эквивалентами» ASCII-7.
     *
     * @param   string  $string  Строка для транслитерации.
     *
     * @return  string|boolean  Транслитерированная строка или логическое значение false в случае сбоя.
     */
    public function transliterate(string $string): string|boolean;

    /**
     * Возвращает массив суффиксов для правил множественного числа.
     *
     * @param   integer  $count  Номер счета, для которого предназначено правило.
     *
     * @return  array[]  Массив суффиксов.
     */
    public function getPluralSuffixes(int $count): array;
}
