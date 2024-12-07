<?php

/**
 * Часть пакета Flexis Framework String.
 */

namespace Flexis\String;

// Локальная конфигурация PHP mbstring и iconv
@ini_set('default_charset', 'UTF-8');

/**
 * Класс обработки строк для данных UTF-8, обертывающий библиотеку phputf8. 
 * Все функции предполагают допустимость строк UTF-8.
 */
abstract class StringHelper {
    /**
     * Стили приращения.
     *
     * @var    array
     */
    protected static array $incrementStyles = [
        'dash' => [
            '#-(\d+)$#',
            '-%d',
        ],
        'default' => [
            ['#\((\d+)\)$#', '#\(\d+\)$#'],
            [' (%d)', '(%d)'],
        ]
    ];

    /**
     * Увеличивает конечное число в строке.
     *
     * Используется для легкого создания отдельных меток при копировании объектов. 
     * Метод имеет следующие стили:
     *
     * default: "Label" становится "Label (2)"
     * dash:    "Label" становится "Label-2"
     *
     * @param string      $string  Исходная строка.
     * @param string|null $style   Стиль (default | dash).
     * @param integer     $n       Если этот номер указан, он используется для копии, инче это «следующий» номер.
     *
     * @return  string  Увеличенная строка.
     */
    public static function increment(string $string, ?string $style = 'default', int $n = 0): string {
        $styleSpec = static::$incrementStyles[$style] ?? static::$incrementStyles['default'];
        // Поиск по регулярным выражениям и шаблоны замены.
        if (\is_array($styleSpec[0])) {
            $rxSearch  = $styleSpec[0][0];
            $rxReplace = $styleSpec[0][1];
        } else {
            $rxSearch = $rxReplace = $styleSpec[0];
        }

        // Новые и старые (существующие) форматы sprintf.
        if (\is_array($styleSpec[1])) {
            $newFormat = $styleSpec[1][0];
            $oldFormat = $styleSpec[1][1];
        } else {
            $newFormat = $oldFormat = $styleSpec[1];
        }

        // Проверим, увеличиваем ли мы существующий шаблон или добавляем новый.
        if (preg_match($rxSearch, $string, $matches)) {
            $n      = empty($n) ? ($matches[1] + 1) : $n;
            $string = preg_replace($rxReplace, sprintf($oldFormat, $n), $string);
        } else {
            $n = empty($n) ? 2 : $n;
            $string .= sprintf($newFormat, $n);
        }

        return $string;
    }

    /**
     * Проверяет, содержит ли строка только 7-битные байты ASCII.
     *
     * Вы можете использовать это для условной проверки, требует ли строка обработки как UTF-8 или нет, 
     * что потенциально дает преимущества в производительности за счет использования собственного эквивалента PHP, 
     * если это просто ASCII, например:
     *
     * <code>
     * if (StringHelper::is_ascii($someString)) {
     *     // Это просто ASCII — используем собственный метод PHP.
     *     $someString = strtolower($someString);
     * } else {
     *     $someString = StringHelper::strtolower($someString);
     * }
     * </code>
     *
     * @param string $str  Строка для проверки.
     *
     * @return  boolean True, если строка полностью состоит из ASCII.
     */
    public static function is_ascii(string $str): bool {
        return utf8_is_ascii($str);
    }

    /**
     * Альтернатива ord() с поддержкой UTF-8
     *
     * Возвращает порядковый номер Юникода для символа от 0 до 255.
     *
     * @param string $chr  Символ в кодировке UTF-8
     *
     * @return  integer Порядковый номер Юникода для символа.
     *
     * @link    https://www.php.net/ord
     */
    public static function ord(string $chr): int {
        return utf8_ord($chr);
    }

    /**
     * Альтернатива strpos() с поддержкой UTF-8
     *
     * Находит позицию первого вхождения подстроки.
     *
     * @param string                $str     Проверяемая строка.
     * @param string                $search  Строка для поиска.
     * @param boolean|integer|null  $offset  Необязательно, указывает позицию, с которой должен выполняться поиск.
     *
     * @return  integer|boolean  Количество символов до первого совпадения или FALSE в случае неудачи.
     *
     * @link    https://www.php.net/strpos
     */
    public static function strpos(string $str, string $search, bool|int|null $offset = false): bool|int {
        if ($offset === false) {
            return utf8_strpos($str, $search);
        }

        return utf8_strpos($str, $search, $offset);
    }

    /**
     * Альтернатива strrpos() с поддержкой UTF-8
     *
     * Возвращает позицию последнего вхождения подстроки в строке.
     *
     * @param string  $str     Проверяемая строка.
     * @param string  $search  Строка для поиска.
     * @param integer $offset  Смещение слева от строки.
     *
     * @return  integer|boolean  Количество символов перед последним совпадением или значение false в случае неудачи.
     *
     * @link    https://www.php.net/strrpos
     */
    public static function strrpos(string $str, string $search, int $offset = 0): bool|int {
        return utf8_strrpos($str, $search, $offset);
    }

    /**
     * Альтернатива substr() с поддержкой UTF-8
     *
     * Возвращает часть строки с заданным смещением символов (и, возможно, длиной).
     *
     * @param string               $str     Проверяемая строка.
     * @param integer              $offset  Смещение количества символов UTF-8 (слева).
     * @param boolean|integer|null $length  Необязательная длина в символах UTF-8 от смещения.
     *
     * @return  string|boolean
     *
     * @link    https://www.php.net/suubstr
     */
    public static function substr(string $str, int $offset, bool|int|null $length = false): bool|string {
        if ($length === false) {
            return utf8_substr($str, $offset);
        }

        return utf8_substr($str, $offset, $length);
    }

    /**
     * Альтернатива strtolower() с поддержкой UTF-8
     *
     * Приводит строку к нижнему регистру.
     *
     * Примечание: Понятие «регистр» символов существует только в некоторых алфавитах, 
     * таких как латинский, греческий, кириллица, армянский и архаичный грузинский алфавит. 
     * Например, оно не существует в китайском алфавите. См. Приложение № 21 к стандарту Unicode: Сопоставление регистров.
     *
     * @param string $str  Проверяемая строка.
     *
     * @return  string|boolean  Строка в нижнем регистре или FALSE, если недействительна в кодировке UTF-8.
     *
     * @link    https://www.php.net/strtolower
     */
    public static function strtolower(string $str): bool|string {
        return utf8_strtolower($str);
    }

    /**
     * Альтернатива strtoupper() с поддержкой UTF-8
     *
     * Приводит строку к верхнему регистру.
     *
     * Примечание: Понятие «регистр» символов существует только в некоторых алфавитах, 
     * таких как латинский, греческий, кириллица, армянский и архаичный грузинский алфавит. 
     * Например, оно не существует в китайском алфавите. См. Приложение № 21 к стандарту Unicode: Сопоставление регистров.
     *
     * @param string $str  Входная строка.
     *
     * @return  string|boolean  Строка в верхнем регистре или FALSE, если недействительна в кодировке UTF-8.
     *
     * @link    https://www.php.net/strtoupper
     */
    public static function strtoupper(string $str): bool|string {
        return utf8_strtoupper($str);
    }

    /**
     * Альтернатива strlen() с поддержкой UTF-8
     *
     * Возвращает количество символов в строке (НЕ КОЛИЧЕСТВО БАЙТОВ).
     *
     * @param string $str  Строка UTF-8.
     *
     * @return  integer  Количество символов UTF-8 в строке.
     *
     * @link    https://www.php.net/strlen
     */
    public static function strlen(string $str): int {
        return utf8_strlen($str);
    }

    /**
     * Альтернатива str_ireplace() с поддержкой UTF-8
     *
     * Заменяет вхождения строки поиска, без учета регистра, строкой замены.
     *
     * @param string|string[]      $search   Строка для поиска.
     * @param string|string[]      $replace  Существующая строка для замены.
     * @param string               $str      Новая строка для замены.
     * @param boolean|integer|null $count    Необязательное значение счетчика.
     *
     * @return string  Строка UTF-8.
     *
     * @link    https://www.php.net/str_ireplace
     */
    public static function str_ireplace(array|string $search, array|string $replace, string $str, bool|int $count = null): string {
        if ($count === false) {
            return utf8_ireplace($search, $replace, $str);
        }

        return utf8_ireplace($search, $replace, $str, $count);
    }

    /**
     * Альтернатива str_pad() с поддержкой UTF-8
     *
     * Дополнить строку до определенной длины другой строкой.
     * $padStr может содержать многобайтовые символы.
     *
     * @param string  $input   Входная строка.
     * @param integer $length  Если значение отрицательное, меньше или равно длине входной строки, заполнение не выполняется.
     * @param string  $padStr  Строка может быть усечена, если количество символов заполнения не может быть разделено на длину строки поровну.
     * @param integer $type    Тип применяемого заполнения.
     *
     * @return  string
     *
     * @link    https://www.php.net/str_pad
     */
    public static function str_pad(string $input, int $length, string $padStr = ' ', int $type = STR_PAD_RIGHT): string {
        return utf8_str_pad($input, $length, $padStr, $type);
    }

    /**
     * Альтернатива str_split() с поддержкой UTF-8
     *
     * Преобразует строку в массив.
     *
     * @param string  $str       Строка в кодировке UTF-8 для обработки.
     * @param integer $splitLen  Число символов, по которым нужно разделить строку.
     *
     * @return  array|string|boolean
     *
     * @link    https://www.php.net/str_split
     */
    public static function str_split(string $str, int $splitLen = 1): bool|array|string {
        return utf8_str_split($str, $splitLen);
    }

    /**
     * Альтернатива strcasecmp() с поддержкой UTF-8/LOCALE
     *
     * Сравнение строк без учета регистра.
     *
     * @param string         $str1    Строка 1 для сравнения.
     * @param string         $str2    Строка 2 для сравнения.
     * @param boolean|string $locale  Локаль, используемая strcoll, или false для использования классического сравнения.
     *
     * @return  integer   < 0, если str1 меньше str2; > 0, если str1 больше str2, и 0, если они равны.
     *
     * @link    https://www.php.net/strcasecmp
     * @link    https://www.php.net/strcoll
     * @link    https://www.php.net/setlocale
     */
    public static function strcasecmp(string $str1, string $str2, bool|string $locale = false): int {
        if ($locale === false) {
            return utf8_strcasecmp($str1, $str2);
        }

        // Получим текущую локаль
        $locale0 = setlocale(LC_COLLATE, 0);

        if (!$locale = setlocale(LC_COLLATE, $locale)) {
            $locale = $locale0;
        }

        // Посмотрим, успешно ли мы установили локаль UTF-8.
        if (!stristr($locale, 'UTF-8') && stristr($locale, '_') && preg_match('~\.(\d+)$~', $locale, $m)) {
            $encoding = 'CP' . $m[1];
        } elseif (stristr($locale, 'UTF-8') || stristr($locale, 'utf8')) {
            $encoding = 'UTF-8';
        } else {
            $encoding = 'nonrecodable';
        }

        // Если мы успешно установили кодировку utf-8 или кодировка какая-то странная, не перекодируем
        if ($encoding == 'UTF-8' || $encoding == 'nonrecodable') {
            return strcoll(utf8_strtolower($str1), utf8_strtolower($str2));
        }

        return strcoll(
            static::transcode(utf8_strtolower($str1), 'UTF-8', $encoding),
            static::transcode(utf8_strtolower($str2), 'UTF-8', $encoding)
        );
    }

    /**
     * Альтернатива strcmp() с поддержкой UTF-8/LOCALE
     *
     * Сравнение строк с учетом регистра.
     *
     * @param string         $str1    Строка 1 для сравнения.
     * @param string         $str2    Строка 2 для сравнения.
     * @param boolean|string $locale  Локаль, используемая strcoll, или false для использования классического сравнения.
     *
     * @return  integer   < 0, если str1 меньше str2; > 0, если str1 больше str2, и 0, если они равны.
     *
     * @link    https://www.php.net/strcmp
     * @link    https://www.php.net/strcoll
     * @link    https://www.php.net/setlocale
     */
    public static function strcmp(string $str1, string $str2, false|null $locale = false): int {
        if ($locale) {
            // Получим текущую локаль
            $locale0 = setlocale(LC_COLLATE, 0);

            if (!$locale = setlocale(LC_COLLATE, $locale)) {
                $locale = $locale0;
            }

            // Посмотрим, успешно ли мы установили локаль UTF-8.
            if (!stristr($locale, 'UTF-8') && stristr($locale, '_') && preg_match('~\.(\d+)$~', $locale, $m)) {
                $encoding = 'CP' . $m[1];
            } elseif (stristr($locale, 'UTF-8') || stristr($locale, 'utf8')) {
                $encoding = 'UTF-8';
            } else {
                $encoding = 'nonrecodable';
            }

            // Если мы успешно установили кодировку utf-8 или кодировка какая-то странная, не перекодируем
            if ($encoding == 'UTF-8' || $encoding == 'nonrecodable') {
                return strcoll($str1, $str2);
            }

            return strcoll(static::transcode($str1, 'UTF-8', $encoding), static::transcode($str2, 'UTF-8', $encoding));
        }

        return strcmp($str1, $str2);
    }

    /**
     * Альтернатива strcspn() с поддержкой UTF-8
     *
     * Находит длину начального фрагмента строки, который не соответствует маске.
     *
     * @param string               $str     Строка для обработки.
     * @param string               $mask    Маска.
     * @param boolean|integer|null $start   Необязательная начальная позиция символа (в символах).
     * @param boolean|integer|null $length  Дополнительная длина.
     *
     * @return  integer  Длина начального сегмента строки str1, который не содержит символов строки str2.
     *
     * @link    https://www.php.net/strcspn
     */
    public static function strcspn(string $str, string $mask, bool|int $start = null, bool|int $length = null): int {
        if ($start === false && $length === false) {
            return utf8_strcspn($str, $mask);
        }

        if ($length === false) {
            return utf8_strcspn($str, $mask, $start);
        }

        return utf8_strcspn($str, $mask, $start, $length);
    }

    /**
     * Альтернатива stristr() с поддержкой UTF-8
     *
     * Находит первое вхождение подстроки без учета регистра.
     *
     * @param string $str     Входная строка.
     * @param string $search  Строка поиска.
     *
     * @return  string|boolean
     *
     * @link    https://www.php.net/stristr
     */
    public static function stristr(string $str, string $search): bool|string {
        return utf8_stristr($str, $search);
    }

    /**
     * Альтернатива strrev() с поддержкой UTF-8
     *
     * Перевернуть строку.
     *
     * @param string $str  Строка, которую нужно перевернуть.
     *
     * @return  string   Строка в обратном порядке символов.
     *
     * @link    https://www.php.net/strrev
     */
    public static function strrev(string $str): string {
        return utf8_strrev($str);
    }

    /**
     * Альтернатива strspn() с поддержкой UTF-8
     *
     * Возвращает длину участка в начале строки, которая полностью соответствует маске.
     *
     * @param string       $str     Исследуемая строка.
     * @param string       $mask    Маска.
     * @param integer|null $start   Позиция начала поиска (опционально).
     * @param integer|null $length  Длина фрагмента строки (опционально).
     *
     * @return  integer
     *
     * @link    https://www.php.net/strspn
     */
    public static function strspn(string $str, string $mask, int $start = null, int $length = null): int {
        if (is_null($start) && is_null($length)) {
            return utf8_strspn($str, $mask);
        }

        if (is_null($length)) {
            return utf8_strspn($str, $mask, $start);
        }

        return utf8_strspn($str, $mask, $start, $length);
    }

    /**
     * Альтернатива substr_replace() с поддержкой UTF-8
     *
     * Заменяет текст внутри части строки.
     *
     * @param string                $str     Входная строка.
     * @param string                $repl    Строка замены.
     * @param integer               $start   Начало.
     * @param boolean|integer|null  $length  Длина (необязательный).
     *
     * @return  string
     *
     * @link    https://www.php.net/substr_replace
     */
    public static function substr_replace(string $str, string $repl, int $start, bool|int $length = false): string {
        if ($length === false) {
            return utf8_substr_replace($str, $repl, $start);
        }

        return utf8_substr_replace($str, $repl, $start, $length);
    }

    /**
     * Альтернатива ltrim() с поддержкой UTF-8
     *
     * Удаляет пробелы (или другие символы) из начала строки. 
     * Вам нужно использовать это только в том случае, если вы предоставляете 
     * необязательный аргумент charlist и он содержит символы UTF-8. 
     * Иначе ltrim будет нормально работать со строкой UTF-8.
     *
     * @param string         $str       Строка, которую нужно обрезать.
     * @param boolean|string $charlist  Необязательный список дополнительных символов для обрезки.
     *
     * @return  string  Обрезанная строка.
     *
     * @link    https://www.php.net/ltrim
     */
    public static function ltrim(string $str, bool|string $charlist = false): string {
        if (empty($charlist) && $charlist !== false) {
            return ltrim($str);
        }

        if ($charlist === false) {
            return utf8_ltrim($str);
        }

        return utf8_ltrim($str, $charlist);
    }

    /**
     * Альтернатива rtrim() с поддержкой UTF-8
     *
     * Удаляет пробелы (или другие символы) из конца строки. 
     * Вам нужно использовать это только в том случае, если вы предоставляете 
     * необязательный аргумент charlist и он содержит символы UTF-8. 
     * Иначе rtrim будет нормально работать со строкой UTF-8.
     *
     * @param string         $str       Строка, которую нужно обрезать.
     * @param boolean|string $charlist  Необязательный список дополнительных символов для обрезки.
     *
     * @return  string  Обрезанная строка.
     *
     * @link    https://www.php.net/rtrim
     */
    public static function rtrim(string $str, bool|string $charlist = false): string {
        if (empty($charlist) && $charlist !== false) {
            return rtrim($str);
        }

        if ($charlist === false) {
            return utf8_rtrim($str);
        }

        return utf8_rtrim($str, $charlist);
    }

    /**
     * Замена с поддержкой UTF-8 для функции trim()
     *
     * Удаляет пробелы (или другие символы) из начала и конца строки. 
     * Вам нужно использовать это только в том случае, если вы предоставляете 
     * необязательный аргумент charlist и он содержит символы UTF-8. 
     * Иначе trim будет нормально работать со строкой UTF-8.
     *
     * @param string         $str       Строка, которую нужно обрезать.
     * @param boolean|string $charlist  Необязательный список дополнительных символов для обрезки.
     *
     * @return  string  Обрезанная строка.
     *
     * @link    https://www.php.net/trim
     */
    public static function trim(string $str, bool|string $charlist = false): string {
        if (empty($charlist) && $charlist !== false) {
            return trim($str);
        }

        if ($charlist === false) {
            return utf8_trim($str);
        }

        return utf8_trim($str, $charlist);
    }

    /**
     * Альтернатива ucfirst() с поддержкой UTF-8
     *
     * Преобразовывает первый символ строки в верхний регистр или первый символ всех слов в верхний регистр.
     *
     * @param string      $str           Строка для обработки.
     * @param string|null $delimiter     Разделитель слов (ноль означает, что строка не разбивается).
     * @param string|null $newDelimiter  Новый разделитель слов (нуль означает равен $delimiter).
     *
     * @return  string  Если $delimiter равен нулю, вернёт строку с первым символом в верхнем регистре (если применимо), 
     *                  иначе рассмотрит строку слов, разделенных разделителем, применит ucfirst к каждому слову и вернтёт строку с новым разделителем.
     *
     * @link    https://www.php.net/ucfirst
     */
    public static function ucfirst(string $str, string $delimiter = null, string $newDelimiter = null): string {
        if ($delimiter === null) {
            return utf8_ucfirst($str);
        }

        if ($newDelimiter === null) {
            $newDelimiter = $delimiter;
        }

        return implode($newDelimiter, array_map('utf8_ucfirst', explode($delimiter, $str)));
    }

    /**
     * Альтернатива ucwords() с поддержкой UTF-8
     *
     * Преобразовывает в верхний регистр первый символ каждого слова в строке.
     *
     * @param string $str  Строка для обработки.
     *
     * @return  string  Строка с первым символом каждого слова в верхнем регистре.
     *
     * @link    https://www.php.net/ucwords
     */
    public static function ucwords(string $str): string {
        return utf8_ucwords($str);
    }

    /**
     * Перекодировать строку.
     *
     * @param string $source        Строка для перекодирования.
     * @param string $fromEncoding  Исходная кодировка.
     * @param string $toEncoding    Целевая кодировка.
     *
     * @return  string|null  Перекодированная строка или значение NULL, если источником не была строка.
     *
     * @link    https://bugs.php.net/bug.php?id=48147
     */
    public static function transcode(string $source, string $fromEncoding, string $toEncoding): ?string {
        return match (ICONV_IMPL) {
            'glibc' => @iconv($fromEncoding, $toEncoding . '//TRANSLIT,IGNORE', $source),
            default => iconv($fromEncoding, $toEncoding . '//IGNORE//TRANSLIT', $source),
        };
    }

    /**
     * Проверяет строку на предмет допустимости UTF-8 и поддержки стандарта Unicode.
     *
     * Примечание: эта функция была изменена для простого возврата true или false.
     *
     * @param string $str  Строка в кодировке UTF-8.
     *
     * @return  boolean  True, если действительно.
     *
     * @author  <hsivonen@iki.fi>
     * @link    https://hsivonen.fi/php-utf8/
     * @see     compliant
     */
    public static function valid(string $str): bool {
        return utf8_is_valid($str);
    }

    /**
     * Проверяет, соответствует ли строка UTF-8.
     *
     * Это будет намного быстрее, чем StringHelper::valid(), но будет передавать пяти-и шестиоктетные последовательности UTF-8, 
     * которые не поддерживаются Unicode и поэтому не могут корректно отображаться в браузере. 
     * Другими словами, он не такой строгий, как StringHelper::valid(), но быстрее. 
     * 
     * Если вы используете его для проверки ввода пользователя, вы подвергаете себя риску того, 
     * что злоумышленники смогут внедрить последовательности 5 и 6 байтов 
     * (что может быть или не быть значительным риском, в зависимости от того, что вы делаете).
     *
     * @param string $str  Строка UTF-8 для проверки.
     *
     * @return  boolean  TRUE, если строка действительна UTF-8.
     *
     * @see     StringHelper::valid
     * @link    https://www.php.net/manual/en/reference.pcre.pattern.modifiers.php#54805
     */
    public static function compliant(string $str): bool {
        return utf8_compliant($str);
    }

    /**
     * Преобразует последовательности Юникода в строку UTF-8.
     *
     * @param string $str  Строка Unicode для преобразования.
     *
     * @return  string  Строка UTF-8.
     */
    public static function unicode_to_utf8(string $str): string {
        if (\extension_loaded('mbstring')) {
            return preg_replace_callback(
                '/\\\\u([0-9a-fA-F]{4})/',
                static function ($match) {
                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                },
                $str
            );
        }

        return $str;
    }

    /**
     * Преобразует последовательности Юникода в строку UTF-16.
     *
     * @param string $str  Строка Unicode для преобразования.
     *
     * @return  string Строка UTF-16.
     */
    public static function unicode_to_utf16(string $str): string {
        if (\extension_loaded('mbstring')) {
            return preg_replace_callback(
                '/\\\\u([0-9a-fA-F]{4})/',
                static function ($match) {
                    return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UTF-16BE');
                },
                $str
            );
        }

        return $str;
    }
}
