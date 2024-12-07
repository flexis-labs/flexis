<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language\Localise;

use Flexis\Language\LocaliseInterface;
use Flexis\Language\Transliterate;
use Flexis\String\StringHelper;

/**
 * Класс абстрактного обработчика локализации.
 */
abstract class AbstractLocalise implements LocaliseInterface {
    /**
     * Транслитерационная функция.
     *
     * Этот метод обрабатывает строку и заменяет все символы UTF-8 с диакритическими знаками эквивалентами ASCII-7 без акцентов.
     *
     * @param   string  $string  Строка для транслитерации.
     *
     * @return  string|boolean  Транслитерированная строка или логическое значение false в случае сбоя.
     */
    public function transliterate(string $string): string|bool {
        $string = (new Transliterate())->utf8_latin_to_ascii($string);

        return StringHelper::strtolower($string);
    }

    /**
     * Возвращает массив суффиксов для правил множественного числа.
     *
     * @param   integer  $count  Номер счета, для которого предназначено правило.
     *
     * @return  string[]  Массив суффиксов.
     */
    public function getPluralSuffixes(int $count): array {
        return [(string) $count];
    }
}
