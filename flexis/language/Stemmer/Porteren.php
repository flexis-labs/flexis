<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language\Stemmer;

use Flexis\Language\StemmerInterface;

/**
 * Класс Портера по английскому вокалу.
 *
 */
class Porteren implements StemmerInterface {
    /**
     * Внутренний кеш стеблевых токенов.
     *
     * @var    array
     */
    protected array $cache = [];

    /**
     * Регулярное выражение для сопоставления согласной.
     *
     * @var    string
     */
    private string $regexConsonant = '(?:[bcdfghjklmnpqrstvwxz]|(?<=[aeiou])y|^y)';

    /**
     * Регулярное выражение для сопоставления гласной.
     *
     * @var    string
     */
    private string $regexVowel = '(?:[aeiou]|(?<![aeiou])y)';

    /**
     * Метод для проверки токена и возврата корня.
     *
     * @param   string  $token  Токен, который нужно остановить.
     * @param   string  $lang   Язык токена.
     *
     * @return  string  Корневой токен.
     */
    public function stem(string $token, string $lang): string {
        if (\strlen($token) <= 2) {
            return $token;
        }

        if ($lang !== 'en') {
            return $token;
        }

        if (!isset($this->cache[$lang][$token])) {
            $result = $token;
            $result = $this->step1ab($result);
            $result = $this->step1c($result);
            $result = $this->step2($result);
            $result = $this->step3($result);
            $result = $this->step4($result);
            $result = $this->step5($result);

            $this->cache[$lang][$token] = $result;
        }

        return $this->cache[$lang][$token];
    }

    /**
     * Шаг 1.
     *
     * @param   string  $word  Токен, который нужно остановить.
     *
     * @return  string
     */
    private function step1ab(string $word): string {
        if (substr($word, -1) == 's') {
            $this->replace($word, 'sses', 'ss')
            || $this->replace($word, 'ies', 'i')
            || $this->replace($word, 'ss', 'ss')
            || $this->replace($word, 's', '');
        }

        if (substr($word, -2, 1) != 'e' || !$this->replace($word, 'eed', 'ee', 0)) {
            $v = $this->regexVowel;

            if (
                preg_match("#$v+#", substr($word, 0, -3)) && $this->replace($word, 'ing', '')
                || preg_match("#$v+#", substr($word, 0, -2)) && $this->replace($word, 'ed', '')
            ) {
                if (!$this->replace($word, 'at', 'ate') && !$this->replace($word, 'bl', 'ble') && !$this->replace($word, 'iz', 'ize')) {
                    if ($this->doubleConsonant($word) && !str_ends_with($word, 'll') && !str_ends_with($word, 'ss') && !str_ends_with($word, 'zz')) {
                        $word = substr($word, 0, -1);
                    } elseif ($this->m($word) == 1 && $this->cvc($word)) {
                        $word .= 'e';
                    }
                }
            }
        }

        return $word;
    }

    /**
     * Шаг 1в.
     *
     * @param   string  $word  Токен, который нужно остановить.
     *
     * @return  string
     */
    private function step1c(string $word): string {
        $v = $this->regexVowel;

        if (substr($word, -1) == 'y' && preg_match("#$v+#", substr($word, 0, -1))) {
            $this->replace($word, 'y', 'i');
        }

        return $word;
    }

    /**
     * Шаг 2.
     *
     * @param   string  $word  Токен, который нужно остановить.
     *
     * @return  string
     */
    private function step2(string $word): string {
        switch (substr($word, -2, 1)) {
            case 'a':
                $this->replace($word, 'ational', 'ate', 0)
                || $this->replace($word, 'tional', 'tion', 0);

                break;

            case 'c':
                $this->replace($word, 'enci', 'ence', 0)
                || $this->replace($word, 'anci', 'ance', 0);

                break;

            case 'e':
                $this->replace($word, 'izer', 'ize', 0);

                break;

            case 'g':
                $this->replace($word, 'logi', 'log', 0);

                break;

            case 'l':
                $this->replace($word, 'entli', 'ent', 0)
                || $this->replace($word, 'ousli', 'ous', 0)
                || $this->replace($word, 'alli', 'al', 0)
                || $this->replace($word, 'bli', 'ble', 0)
                || $this->replace($word, 'eli', 'e', 0);

                break;

            case 'o':
                $this->replace($word, 'ization', 'ize', 0)
                || $this->replace($word, 'ation', 'ate', 0)
                || $this->replace($word, 'ator', 'ate', 0);

                break;

            case 's':
                $this->replace($word, 'iveness', 'ive', 0)
                || $this->replace($word, 'fulness', 'ful', 0)
                || $this->replace($word, 'ousness', 'ous', 0)
                || $this->replace($word, 'alism', 'al', 0);

                break;

            case 't':
                $this->replace($word, 'biliti', 'ble', 0)
                || $this->replace($word, 'aliti', 'al', 0)
                || $this->replace($word, 'iviti', 'ive', 0);

                break;
        }

        return $word;
    }

    /**
     * Шаг 3.
     *
     * @param   string  $word  Токен, который нужно остановить.
     *
     * @return  string
     */
    private function step3(string $word): string {
        switch (substr($word, -2, 1)) {
            case 'a':
                $this->replace($word, 'ical', 'ic', 0);

                break;

            case 's':
                $this->replace($word, 'ness', '', 0);

                break;

            case 't':
                $this->replace($word, 'icate', 'ic', 0)
                || $this->replace($word, 'iciti', 'ic', 0);

                break;

            case 'u':
                $this->replace($word, 'ful', '', 0);

                break;

            case 'v':
                $this->replace($word, 'ative', '', 0);

                break;

            case 'z':
                $this->replace($word, 'alize', 'al', 0);

                break;
        }

        return $word;
    }

    /**
     * Шаг 4.
     *
     * @param   string  $word  Токен, который нужно остановить.
     *
     * @return  string
     */
    private function step4(string $word): string {
        switch (substr($word, -2, 1)) {
            case 'a':
                $this->replace($word, 'al', '', 1);

                break;

            case 'c':
                $this->replace($word, 'ance', '', 1)
                || $this->replace($word, 'ence', '', 1);

                break;

            case 'e':
                $this->replace($word, 'er', '', 1);

                break;

            case 'i':
                $this->replace($word, 'ic', '', 1);

                break;

            case 'l':
                $this->replace($word, 'able', '', 1)
                || $this->replace($word, 'ible', '', 1);

                break;

            case 'n':
                $this->replace($word, 'ant', '', 1)
                || $this->replace($word, 'ement', '', 1)
                || $this->replace($word, 'ment', '', 1)
                || $this->replace($word, 'ent', '', 1);

                break;

            case 'o':
                if (substr($word, -4) == 'tion' || substr($word, -4) == 'sion') {
                    $this->replace($word, 'ion', '', 1);
                } else {
                    $this->replace($word, 'ou', '', 1);
                }

                break;

            case 's':
                $this->replace($word, 'ism', '', 1);

                break;

            case 't':
                $this->replace($word, 'ate', '', 1)
                || $this->replace($word, 'iti', '', 1);

                break;

            case 'u':
                $this->replace($word, 'ous', '', 1);

                break;

            case 'v':
                $this->replace($word, 'ive', '', 1);

                break;

            case 'z':
                $this->replace($word, 'ize', '', 1);

                break;
        }

        return $word;
    }

    /**
     * Шаг 5
     *
     * @param   string  $word  Токен, который нужно остановить.
     *
     * @return  string
     */
    private function step5(string $word): string {
        // Часть а
        if (str_ends_with($word, 'e')) {
            if ($this->m(substr($word, 0, -1)) > 1) {
                $this->replace($word, 'e', '');
            } elseif ($this->m(substr($word, 0, -1)) == 1) {
                if (!$this->cvc(substr($word, 0, -1))) {
                    $this->replace($word, 'e', '');
                }
            }
        }

        if ($this->m($word) > 1 && $this->doubleConsonant($word) && str_ends_with($word, 'l')) {
            $word = substr($word, 0, -1);
        }

        return $word;
    }

    /**
     * Заменяет первую строку второй, находящейся в конце строки. 
     * Если указан третий аргумент, то предыдущая строка должна соответствовать как минимум этому числу m.
     *
     * @param   string        $str    Строка для проверки.
     * @param   string        $check  Окончание проверки.
     * @param   string        $repl   Запасная строка.
     * @param   integer|null  $m      Необязательное минимальное количество m() для удовлетворения.
     *
     * @return  boolean  Находится ли строка $check в конце строки $str. Правда не обязательно означает, что его заменили.
     */
    private function replace(string &$str, string $check, string $repl, ?int $m = null): bool {
        $len = 0 - \strlen($check);

        if (substr($str, $len) == $check) {
            $substr = substr($str, 0, $len);

            if ($m === null || $this->m($substr) > $m) {
                $str = $substr . $repl;
            }

            return true;
        }

        return false;
    }

    /**
     * m() измеряет количество последовательностей согласных в $str. 
     * если c — последовательность согласных, 
     * а v — последовательность гласных, 
     * а <..> указывает на произвольное присутствие,
     *
     * <c><v>       gives 0
     * <c>vc<v>     gives 1
     * <c>vcvc<v>   gives 2
     * <c>vcvcvc<v> gives 3
     *
     * @param   string  $str  Строка, возвращающая количество m.
     *
     * @return  integer  Количество m.
     */
    private function m(string $str): int {
        $c = $this->regexConsonant;
        $v = $this->regexVowel;

        $str = preg_replace("#^$c+#", '', $str);
        $str = preg_replace("#$v+$#", '', $str);

        preg_match_all("#($v+$c+)#", $str, $matches);

        return \count($matches[1]);
    }

    /**
     * Возвращает значение true/false в зависимости от того, содержит ли данная строка две одинаковые согласные рядом друг с другом в конце строки.
     *
     * @param   string  $str  Строка для проверки.
     *
     * @return  boolean  Результат.
     */
    private function doubleConsonant(string $str): bool {
        $c = $this->regexConsonant;

        return preg_match("#$c{2}$#", $str, $matches) && $matches[0][0] === $matches[0][1];
    }

    /**
     * Проверяет окончание последовательности CVC, где второй C не является W, X или Y.
     *
     * @param   string  $str  Строка для проверки.
     *
     * @return  boolean  Результат.
     */
    private function cvc(string $str): bool {
        $c = $this->regexConsonant;
        $v = $this->regexVowel;

        return preg_match("#($c$v$c)$#", $str, $matches)
            && \strlen($matches[1]) === 3
            && $matches[1][2] !== 'w'
            && $matches[1][2] !== 'x'
            && $matches[1][2] !== 'y';
    }
}
