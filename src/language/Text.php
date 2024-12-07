<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Класс обработки текста.
 */
class Text {
    /**
     * Экземпляр языка.
     *
     * @var    Language
     */
    private Language $language;

    /**
     * Конструктор.
     *
     * @param   Language  $language  Экземпляр языка для использования в переводах.
     */
    public function __construct(Language $language) {
        $this->setLanguage($language);
    }

    /**
     * Возвращает текущий экземпляр языка.
     *
     * @return  Language
     */
    public function getLanguage(): Language {
        return $this->language;
    }

    /**
     * Устанавливает объект «Язык».
     *
     * @param   Language  $language  Языковой экземпляр.
     *
     * @return  $this
     */
    public function setLanguage(Language $language): self {
        $this->language = $language;

        return $this;
    }

    /**
     * Переводит строку на текущий язык.
     *
     * @param   string   $string                Строка для перевода.
     * @param   array    $parameters            Массив параметров строки.
     * @param   boolean  $jsSafe                True чтобы экранировать строку для вывода JavaScript.
     * @param   boolean  $interpretBackSlashes  Для интерпретации обратной косой черты (\\=\, \n=возврат каретки, \t=табуляция)
     *
     * @return  string  Переведенная строка или ключ, если $script имеет значение true.
     */
    public function translate(
        string $string,
        array $parameters = [],
        bool $jsSafe = false,
        bool $interpretBackSlashes = true
    ): string {

        $translated = $this->getLanguage()->translate($string, $jsSafe, $interpretBackSlashes);

        if (!empty($parameters)) {
            $translated = strtr($translated, $parameters);
        }

        return $translated;
    }

    /**
     * Переводит строку на текущий язык.
     *
     * @param   string   $string                Строка для перевода.
     * @param   string   $alt                   Альтернативный вариант для глобальной строки
     * @param   array    $parameters            Массив параметров для строки
     * @param   boolean  $jsSafe                Логическое значение: сделать результат безопасным для JavaScript.
     * @param   boolean  $interpretBackSlashes  Для интерпретации обратной косой черты (\\=\, \n=возврат каретки, \t=табуляция)
     *
     * @return  string  The translated string or the key if $script is true
     */
    public function alt(
        string $string,
        string $alt,
        array $parameters = [],
        bool $jsSafe = false,
        bool $interpretBackSlashes = true
    ): string {

        if ($this->getLanguage()->hasKey($string . '_' . $alt)) {
            return $this->translate($string . '_' . $alt, $parameters, $jsSafe, $interpretBackSlashes);
        }

        return $this->translate($string, $parameters, $jsSafe, $interpretBackSlashes);
    }

    /**
     * Преобразует строку на текущем языке во множественное число.
     *
     * Последний аргумент может принимать массив опций для настройки вызова Flexis\Language\Language::translate():
     *
     * array(
     *   'jsSafe' => boolean,
     *   'interpretBackSlashes' => boolean
     * )
     *
     * где:
     * jsSafe — это логическое значение, указывающее, следует ли сделать результат безопасным для JavaScript.
     * interpretBackSlashes — это логическое значение, указывающее, интерпретируются ли обратные косые черты (\\ -> \, \n -> новая строка, \t -> символ табуляции).
     *
     * @param   string   $string  Строка формата.
     * @param   integer  $n       Количество предметов
     *
     * @return  string  Переведенная строка
     *
     * @note    Этот метод может принимать смешанное количество аргументов для функции sprintf.
     */
    public function plural(string $string, int $n): string {
        $lang  = $this->getLanguage();
        $args  = \func_get_args();
        $count = \count($args);
        $found = false;
        $key   = '';

        $suffixes = $lang->getPluralSuffixes($n);
        array_unshift($suffixes, $n);

        foreach ($suffixes as $suffix) {
            $key = $string . '_' . $suffix;

            if ($lang->hasKey($key)) {
                $found = true;

                break;
            }
        }

        if (!$found) {
            $key = $string;
        }

        if (\is_array($args[$count - 1])) {
            $args[0] = $lang->translate(
                $key,
                $args[$count - 1]['jsSafe'] ?? false,
                $args[$count - 1]['interpretBackSlashes'] ?? true
            );
        } else {
            $args[0] = $lang->translate($key);
        }

        return \sprintf(...$args);
    }

    /**
     * Передает строку через sprintf.
     *
     * Последний аргумент может принимать массив опций для настройки вызова Flexis\Language\Language::translate():
     *
     * array(
     *   'jsSafe' => boolean,
     *   'interpretBackSlashes' => boolean
     * )
     *
     * где:
     * jsSafe — это логическое значение, указывающее, следует ли сделать результат безопасным для JavaScript.
     * interpretBackSlashes — логическое значение, указывающее, интерпретируются ли обратные косые черты (\\ -> \, \n -> новая строка, \t -> символ табуляции).
     *
     * @param   string  $string  The format string.
     *
     * @return  string|null  Переведенная строка
     *
     * @note    Этот метод может принимать смешанное количество аргументов для функции sprintf.
     */
    public function sprintf(string $string): ?string {
        $lang  = $this->getLanguage();
        $args  = \func_get_args();
        $count = \count($args);

        if (\is_array($args[$count - 1])) {
            $args[0] = $lang->translate(
                $string,
                $args[$count - 1]['jsSafe'] ?? false,
                $args[$count - 1]['interpretBackSlashes'] ?? true
            );
        } else {
            $args[0] = $lang->translate($string);
        }

        return \sprintf(...$args);
    }

    /**
     * Передает строку через printf.
     *
     * Последний аргумент может принимать массив опций для настройки вызова Flexis\Language\Language::translate():
     *
     * array(
     *   'jsSafe' => boolean,
     *   'interpretBackSlashes' => boolean
     * )
     *
     * где:
     * jsSafe — логическое значение, указывающее, следует ли сделать результат безопасным для JavaScript.
     * interpretBackSlashes — логическое значение, указывающее, интерпретируются ли обратные косые черты (\\ -> \, \n -> новая строка, \t -> символ табуляции).
     *
     * @param   string  $string  Строка формата.
     *
     * @return  string|null  Переведенная строка
     *
     * @note    Этот метод может принимать смешанное количество аргументов для функции printf.
     */
    public function printf(string $string): ?string {
        $lang  = $this->getLanguage();
        $args  = \func_get_args();
        $count = \count($args);

        if (\is_array($args[$count - 1])) {
            $args[0] = $lang->translate(
                $string,
                $args[$count - 1]['jsSafe'] ?? false,
                $args[$count - 1]['interpretBackSlashes'] ?? true
            );
        } else {
            $args[0] = $lang->translate($string);
        }

        return \printf(...$args);
    }
}
