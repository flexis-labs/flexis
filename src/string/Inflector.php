<?php

/**
 * Часть пакета Flexis Framework String.
 */

namespace Flexis\String;

use Doctrine\Inflector\Inflector as DoctrineInflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\Inflector\LanguageInflectorFactory;
use Doctrine\Inflector\Rules\Pattern;
use Doctrine\Inflector\Rules\Patterns;
use Doctrine\Inflector\Rules\Ruleset;
use Doctrine\Inflector\Rules\Substitution;
use Doctrine\Inflector\Rules\Substitutions;
use Doctrine\Inflector\Rules\Transformation;
use Doctrine\Inflector\Rules\Transformations;
use Doctrine\Inflector\Rules\Word;

/**
 * Flexis Framework String Inflector Class.
 *
 * Инфлектор преобразования слов.
 */
class Inflector extends DoctrineInflector {
    /**
     * @var LanguageInflectorFactory|null
     */
    private static ?LanguageInflectorFactory $factory = null;
    /**
     * Счётчик.
     *
     * @var    array
     */
    private static array $countable = [
        'rules' => [
            'id',
            'hits',
            'clicks',
        ],
    ];

    /**
     * Добавляет правила регулярных выражений в инфлектор.
     *
     * @param   mixed   $data      Строка или массив строк, или правил регулярных выражений для добавления.
     * @param   string  $ruleType  Тип правила: singular | plural | countable
     * @param   bool    $reset     Если значение true, то будут отменены изменения по умолчанию
     *                             для всех новых правил, которые определяются в $rules.
     *
     * @return  void
     *
     * @throws  \InvalidArgumentException
     */
    private function addRule(mixed $data, string $ruleType, bool $reset = false): void {
        if (\is_string($data)) {
            $data = [$data];
        } elseif (!\is_array($data)) {
            throw new \InvalidArgumentException('Неверные данные правила инфлектора.');
        } elseif (!\in_array($ruleType, ['singular', 'plural', 'countable'])) {
            throw new \InvalidArgumentException('Неподдерживаемый тип правила.');
        }

        if ($ruleType === 'countable') {
            foreach ($data as $rule) {
                self::$countable['rules'][] = (string)$rule;
            }
        } else {
            if (self::$factory === null) {
                self::$factory = self::createFactory();
            }

            switch ($ruleType) {
                case 'singular':
                    self::$factory->withSingularRules(self::buildRuleset($data), $reset);
                    break;
                case 'plural':
                    self::$factory->withPluralRules(self::buildRuleset($data), $reset);
                    break;
            }
        }
    }

    /**
     * @param array<string,mixed>|iterable<string,mixed> $rules Массив правил, которые необходимо добавить.
     */
    private static function buildRuleset(iterable $rules) : Ruleset {
        $regular = [];
        $irregular = [];
        $uninflected = [];

        foreach ($rules as $rule => $pattern) {
            if ( ! is_array($pattern)) {
                $regular[$rule] = $pattern;

                continue;
            }

            switch ($rule) {
                case 'uninflected':
                    $uninflected = $pattern;
                    break;
                case 'irregular':
                    $irregular = $pattern;
                    break;
                case 'rules':
                    $regular = $pattern;
                    break;
            }
        }

        return new Ruleset(
            new Transformations(...array_map(
                static function (string $pattern, string $replacement) : Transformation {
                    return new Transformation(new Pattern($pattern), $replacement);
                },
                array_keys($regular),
                array_values($regular)
            )),
            new Patterns(...array_map(
                static function (string $pattern) : Pattern {
                    return new Pattern($pattern);
                },
                $uninflected
            )),
            new Substitutions(...array_map(
                static function (string $word, string $to) : Substitution {
                    return new Substitution(new Word($word), new Word($to));
                },
                array_keys($irregular),
                array_values($irregular)
            ))
        );
    }

    private static function createFactory(): LanguageInflectorFactory {
        return InflectorFactory::create();
    }

    /**
     * Добавляет счетное слово.
     *
     * @param   mixed  $data  Строка или массив строк для добавления.
     *
     * @return  $this
     */
    public function addCountableRule(mixed $data): self {
        $this->addRule($data, 'countable');

        return $this;
    }

    /**
     * Проверяет, является ли слово счетным.
     *
     * @param   string  $word  Строковый ввод.
     *
     * @return  boolean  True, если слово счетное, иначе false.
     */
    public function isCountable(string $word): bool {
        return \in_array($word, self::$countable['rules']);
    }

    /**
     * Проверяет, находится ли слово в форме множественного числа.
     *
     * @param   string  $word  Строковый ввод.
     *
     * @return  boolean  True, если слово во множественном числе, иначе false.
     */
    public function isPlural(string $word): bool {
        return self::pluralize(self::singularize($word)) === $word;
    }

    /**
     * Проверяет, находится ли слово в форме единственного числа.
     *
     * @param   string  $word  Строковый ввод.
     *
     * @return  boolean  True, если слово в единственном числе, иначе false.
     */
    public function isSingular(string $word): bool {
        return self::singularize($word) === $word;
    }
}
