<?php

namespace Flexis\Language\Stemmer;

use Flexis\Language\StemmerInterface;

class Russian implements StemmerInterface
{
    
	// Уменьшительно-ласкательные формы
	private const DIMINUTIVE_SUFFIXES = [
		'ек', 'очк', 'ушк', 'юшк', 'ышк'
	];
	// Гласные и согласные русского языка
    private const VOWELS = 'аеиоуыэюя';
    private const PERFECT_GERUND_SUFFIXES = [
        'ившись', 'ывшись', 'вшись', 'ивши', 'ывши', 'вши', 'ив', 'ыв', 'в'
    ];

    private const ADJECTIVE_SUFFIXES = [
        'ее', 'ие', 'ые', 'ое', 'ими', 'ыми', 'ей', 'ий', 
        'ый', 'ой', 'ем', 'им', 'ым', 'ом', 'его', 'ого',
        'ему', 'ому', 'их', 'ых', 'ую', 'юю', 'ая', 'яя'
    ];

    private const VERB_SUFFIXES = [
        'ила', 'ыла', 'ена', 'ейте', 'уйте', 'ите', 'или', 'ыли', 'ей', 
        'уй', 'ил', 'ыл', 'им', 'ым', 'ен', 'ило', 'ыло', 'ено', 'ят', 
        'ует', 'уют', 'ит', 'ыт', 'ены', 'ить', 'ыть', 'ишь', 'ую', 'ю'
    ];

    private const NOUN_SUFFIXES = [
        'а', 'ев', 'ов', 'ие', 'ье', 'е', 'иями', 'ями', 'ами', 'еи',
        'ии', 'и', 'ией', 'ей', 'ой', 'ий', 'й', 'иям', 'ям', 'ием',
        'ем', 'ам', 'ом', 'о', 'у', 'ах', 'ях', 'иях', 'ы', 'ь', 'ию',
        'ью', 'ю', 'ия', 'ья', 'я'
    ];

    private const DERIVATIONAL_SUFFIXES = [
        'ост', 'ость'
    ];

    private const SUPERLATIVE_SUFFIXES = [
        'ейш', 'ейше'
    ];

    public function stem(string $token, string $lang): string
    {
        if ($lang !== 'ru') {
            return $token;
        }

        $token = mb_strtolower($token, 'UTF-8');
        $token = $this->normalize($token);
        $token = $this->removePerfectiveGerund($token);
        $token = $this->removeReflexive($token);
        $token = $this->removeAdjectival($token);
        $token = $this->removeVerbEndings($token);
        $token = $this->removeNounEndings($token);
        $token = $this->removeDerivational($token);
        $token = $this->removeSuperlative($token);
        $token = $this->undoubleN($token);
        $token = $this->removeSoftSign($token);
        
        return $token;
    }

    private function normalize(string $word): string
    {
        $replacements = [
            'ё' => 'е',
            'ъ' => '',
            'ь' => ''
        ];
        
        return strtr($word, $replacements);
    }

    private function removePerfectiveGerund(string $word): string
    {
        foreach (self::PERFECT_GERUND_SUFFIXES as $suffix) {
            if (mb_substr($word, -mb_strlen($suffix)) === $suffix) {
                return mb_substr($word, 0, -mb_strlen($suffix));
            }
        }
        return $word;
    }

    private function removeReflexive(string $word): string
    {
        if (mb_substr($word, -2) === 'ся' || mb_substr($word, -2) === 'сь') {
            return mb_substr($word, 0, -2);
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
