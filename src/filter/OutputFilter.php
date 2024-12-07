<?php

/**
 * Часть пакета Flexis Filter Framework.
 */

namespace Flexis\Filter;

use Flexis\Language\Language;
use Flexis\Language\Transliterate;
use Flexis\String\StringHelper;

/**
 * OutputFilter — класс для обработки выходной строки для «безопасного» отображения.
 */
class OutputFilter {
    /**
     * Экземпляр языка для обеспечения безопасности строкового URL-адреса.
     *
     * @var    Language|null
     */
    private static ?Language $language = null;

    /**
     * Делает объект безопасным для отображения в формах.
     *
     * Параметры объекта, которые не являются строкой, массивом, объектом или начинаются с подчеркивания, будут преобразованы.
     *
     * @param   object        $mixed        Объект для анализа.
     * @param   integer|null  $quoteStyle   Необязательный стиль кавычек для функции htmlspecialchars.
     * @param   mixed         $excludeKeys  Необязательное строковое имя одного поля или массив имен полей, которые не будут анализироваться (например, для текстовой области).
     *
     * @return  void
     */
    public static function objectHtmlSafe(object &$mixed, ?int $quoteStyle = \ENT_QUOTES, mixed $excludeKeys = ''): void {
        if (\is_null($quoteStyle)) {
            $quoteStyle = \ENT_QUOTES;
        }

        if (\is_object($mixed)) {
            foreach (get_object_vars($mixed) as $k => $v) {
                if (\is_array($v) || \is_object($v) || $v == null || substr($k, 1, 1) == '_') {
                    continue;
                }

                if (\is_string($excludeKeys) && $k == $excludeKeys) {
                    continue;
                }

                if (\is_array($excludeKeys) && \in_array($k, $excludeKeys)) {
                    continue;
                }

                $mixed->$k = htmlspecialchars($v, $quoteStyle, 'UTF-8');
            }
        }
    }

    /**
     * Делает строку безопасной для вывода XHTML, экранируя амперсанды в ссылках.
     *
     * @param   string  $input  Строка для обработки.
     *
     * @return  string  Обработанная строка.
     */
    public static function linkXhtmlSafe(string $input): string {
        $regex = 'href="([^"]*(&(amp;){0})[^"]*)*?"';

        return preg_replace_callback(
            "#$regex#i",
            function ($m) {
                return preg_replace('#&(?!amp;)#', '&amp;', $m[0]);
            },
            $input
        );
    }

    /**
     * Обрабатывает строку и экранирует её для использования в JavaScript.
     *
     * @param   string  $string  Строка для обработки.
     *
     * @return  string  Обработанный текст.
     */
    public static function stringJSSafe(string $string): string {
        $chars   = preg_split('//u', $string, -1, PREG_SPLIT_NO_EMPTY);
        $newStr = '';

        foreach ($chars as $chr) {
            $code = str_pad(dechex(StringHelper::ord($chr)), 4, '0', STR_PAD_LEFT);

            if (strlen($code) < 5) {
                $newStr .= '\\u' . $code;
            } else {
                $newStr .= '\\u{' . $code . '}';
            }
        }

        return $newStr;
    }

    /**
     * Создаёт URL-безопасную версию указанной строки с языковой транслитерацией.
     *
     * Этот метод обрабатывает строку и заменяет все акцентированные символы UTF-8 безударными «эквивалентами» ASCII-7; пробелы заменяются дефисами, а строка пишется строчными буквами.
     *
     * @param   string  $string    Строка для обработки.
     * @param   string  $language  Язык для транслитерации.
     *
     * @return  string  Обработанная строка.
     */
    public static function stringUrlSafe(string $string, string $language = ''): string {
        $str = str_replace('-', ' ', $string);

        if (self::$language) {
            if (empty($language) || $language === '*' || self::$language->getLanguage() === $language) {
                $str = self::$language->transliterate($str);
            } else {
                $str = (new Language(self::$language->getBasePath(), $language, self::$language->getDebug()))->transliterate($str);
            }
        } else {
            $str = StringHelper::strtolower((new Transliterate())->utf8_latin_to_ascii($string));
        }

        $str = trim(StringHelper::strtolower($str));
        $str = str_replace("'", '', $str);
        $str = preg_replace('/(\s|[^A-Za-z0-9\-])+/', '-', $str);

        return trim($str, '-');
    }

    /**
     * Создаёт URL-безопасную версию указанной строки с заменой символов Юникода.
     *
     * @param   string  $string  Строка для обработки.
     *
     * @return  string  Обработанная строка.
     */
    public static function stringUrlUnicodeSlug(string $string): string {
        $str = preg_replace('/\xE3\x80\x80/', ' ', $string);
        $str = str_replace('-', ' ', $str);
        $str = preg_replace('#[:\#\*"@+=;!><&\.%()\]\/\'\\\\|\[]#', "\x20", $str);
        $str = str_replace('?', '', $str);
        $str = trim(StringHelper::strtolower($str));

        return preg_replace('#\x20+#', '-', $str);
    }

    /**
     * Делает строку безопасной для вывода XHTML, экранируя амперсанды.
     *
     * @param   string  $text  Текст для обработки.
     *
     * @return  string  Обработанная строка.
     */
    public static function ampReplace(string $text): string {
        return preg_replace('/(?<!&)&(?!&|#|[\w]+;)/', '&amp;', $text);
    }

    /**
     * Очищает текст от всего кода форматирования и сценариев.
     *
     * @param   string  $text  Текст для очистки.
     *
     * @return  string  Очищенный текст.
     */
    public static function cleanText(string &$text): string {
        $text = preg_replace("'<script[^>]*>.*?</script>'si", '', $text);
        $text = preg_replace('/<a\s+.*?href="([^"]+)"[^>]*>([^<]+)<\/a>/is', '\2 (\1)', $text);
        $text = preg_replace('/<!--.+?-->/', '', $text);
        $text = preg_replace('/{.+?}/', '', $text);
        $text = preg_replace('/&nbsp;/', ' ', $text);
        $text = preg_replace('/&amp;/', ' ', $text);
        $text = preg_replace('/&quot;/', ' ', $text);
        $text = strip_tags($text);

        return htmlspecialchars($text, \ENT_COMPAT, 'UTF-8');
    }

    /**
     * Устанавливает экземпляр языка для использования.
     *
     * @param   Language  $language  Экземпляр языка, который нужно использовать.
     *
     * @return  void
     */
    public static function setLanguage(Language $language): void {
        self::$language = $language;
    }

    /**
     * Удаляет теги `<img>` из строки.
     *
     * @param   string  $string  Строка для очистки.
     *
     * @return  string  Очищенная строка.
     */
    public static function stripImages(string $string): string {
        while (preg_match('#(<[/]?img.*>)#Ui', $string)) {
            $string = preg_replace('#(<[/]?img.*>)#Ui', '', $string);
        }

        return $string;
    }

    /**
     * Удаляет теги `<iframe>` из строки.
     *
     * @param   string  $string  Строка для очистки.
     *
     * @return  string  Очищенная строка.
     */
    public static function stripIframes(string $string): string {
        while (preg_match('#(<[/]?iframe.*>)#Ui', $string)) {
            $string = preg_replace('#(<[/]?iframe.*>)#Ui', '', $string);
        }

        return $string;
    }
}
