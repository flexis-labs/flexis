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

    // Остальные методы
    // ...
}
