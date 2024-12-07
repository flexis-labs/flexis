<?php

/**
 * Часть пакета Flexis Framework Utilities.
 */

namespace Flexis\Utilities;

use ArrayAccess;
use Flexis\String\StringHelper;
use InvalidArgumentException;

/**
 * ArrayHelper — это служебный класс для работы с массивами,
 * предназначенный для выполнения всевозможных операций с массивами.
 */
final class ArrayHelper {
    /**
     * Приватный конструктор для предотвращения создания экземпляра этого класса.
     */
    private function __construct() {}

    /**
     * Функция для преобразования массива в целочисленные значения.
     *
     * @param array          $array    Исходный массив для преобразования.
     * @param int|array|null $default  Значение по умолчанию, которое следует назначить, если $array не является массивом.
     *
     * @return  array
     */
    public static function toInteger(array $array, int|array $default = null): array {
        if (\is_array($array)) {
            return array_map('intval', $array);
        }

        if ($default === null) {
            return [];
        }

        if (\is_array($default)) {
            return self::toInteger($default, null);
        }

        return [(int) $default];
    }

    /**
     * Служебная функция для сопоставления массива с объектом stdClass.
     *
     * @param   array    $array      Массив для сопоставления.
     * @param   string   $class      Имя класса, который нужно создать.
     * @param   boolean  $recursive  Преобразовать также любой массив внутри основного массива.
     *
     * @return  object
     */
    public static function toObject(array $array, string $class = 'stdClass', bool $recursive = true): object {
        $obj = new $class();

        foreach ($array as $k => $v) {
            if ($recursive && \is_array($v)) {
                $obj->$k = self::toObject($v, $class);
            } else {
                $obj->$k = $v;
            }
        }

        return $obj;
    }

    /**
     * Служебная функция для сопоставления массива со строкой.
     *
     * @param   array    $array         Массив для сопоставления.
     * @param   string   $innerGlue     Связка (необязательно, по умолчанию =) между ключом и значением.
     * @param   string   $outerGlue     Связка (необязательно, по умолчанию '') между элементами массива.
     * @param   boolean  $keepOuterKey  True, если следует сохранить окончательный ключ.
     *
     * @return  string
     */
    public static function toString(array $array, string $innerGlue = '=', string $outerGlue = ' ', bool $keepOuterKey = false): string {
        $output = [];

        foreach ($array as $key => $item) {
            if (\is_array($item)) {
                if ($keepOuterKey) {
                    $output[] = $key;
                }

                // Это значение представляет собой массив, выполним это снова
                $output[] = self::toString($item, $innerGlue, $outerGlue, $keepOuterKey);
            } else {
                $output[] = $key . $innerGlue . '"' . $item . '"';
            }
        }

        return implode($outerGlue, $output);
    }

    /**
     * Служебная функция для сопоставления объекта с массивом.
     *
     * @param mixed        $source   Исходный объект.
     * @param boolean      $recurse  True для рекурсии через многоуровневые объекты.
     * @param string|null  $regex    Необязательное регулярное выражение для сопоставления имен полей.
     *
     * @return  array
     */
    public static function fromObject(mixed $source, bool $recurse = true, string $regex = null): array {
        if (\is_object($source) || \is_array($source)) {
            return self::arrayFromObject($source, $recurse, $regex);
        }

        return [];
    }

    /**
     * Приватная функция для сопоставления объекта или массива с массивом.
     *
     * @param mixed      $item     Исходный объект или массив.
     * @param boolean    $recurse  True для рекурсии через многоуровневые объекты.
     * @param string     $regex    Необязательное регулярное выражение для сопоставления имен полей.
     *
     * @return  array
     */
    private static function arrayFromObject(mixed $item, bool $recurse, string $regex): array {
        if (\is_object($item)) {
            $result = [];

            foreach (get_object_vars($item) as $k => $v) {
                if (!$regex || preg_match($regex, $k)) {
                    if ($recurse) {
                        $result[$k] = self::arrayFromObject($v, $recurse, $regex);
                    } else {
                        $result[$k] = $v;
                    }
                }
            }

            return $result;
        }

        if (\is_array($item)) {
            $result = [];

            foreach ($item as $k => $v) {
                $result[$k] = self::arrayFromObject($v, $recurse, $regex);
            }

            return $result;
        }

        return $item;
    }

    /**
     * Добавляет столбец в массив массивов или объектов.
     *
     * @param   array              $array    Исходный массив или объект.
     * @param   array              $column   Массив, который будет использоваться в качестве нового столбца.
     * @param   string             $colName  Индекс нового столбца или имя нового свойства объекта.
     * @param   int|string|null    $keyCol   Индекс столбца или имя свойства объекта, которое будет использоваться для сопоставления с новым столбцом.
     *
     * @return  array Массив с новым столбцом, добавленным в исходный массив.
     *
     * @see     https://www.php.net/manual/ru/language.types.array.php
     */
    public static function addColumn(array $array, array $column, string $colName, int|string $keyCol = null): array {
        $result = [];

        foreach ($array as $i => $item) {
            $value = null;

            if (!isset($keyCol)) {
                $value = self::getValue($column, $i);
            } else {
                // Преобразуем объект в массив
                $subject = \is_object($item) ? self::fromObject($item) : $item;

                if (isset($subject[$keyCol]) && is_scalar($subject[$keyCol])) {
                    $value = self::getValue($column, $subject[$keyCol]);
                }
            }

            // Добавим столбец
            if (\is_object($item)) {
                if (isset($colName)) {
                    $item->$colName = $value;
                }
            } else {
                if (isset($colName)) {
                    $item[$colName] = $value;
                } else {
                    $item[] = $value;
                }
            }

            $result[$i] = $item;
        }

        return $result;
    }

    /**
     * Удаляет столбец из массива массивов или объектов.
     *
     * @param   array   $array    Исходный массив.
     * @param   string  $colName  Индекс столбца или имя свойства объекта, которое необходимо удалить.
     *
     * @return  array  Столбец значений из исходного массива.
     *
     * @see     https://www.php.net/manual/ru/language.types.array.php
     */
    public static function dropColumn(array $array, string $colName): array {
        $result = [];

        foreach ($array as $i => $item) {
            if (\is_object($item) && isset($item->$colName)) {
                unset($item->$colName);
            } elseif (\is_array($item) && isset($item[$colName])) {
                unset($item[$colName]);
            }

            $result[$i] = $item;
        }

        return $result;
    }

    /**
     * Извлекает столбец из массива массивов или объектов.
     *
     * @param   array           $array     Исходный массив.
     * @param   string          $valueCol  Индекс столбца или имя свойства объекта, которое будет использоваться в качестве значения.
     *                                     Также может быть NULL для возврата полных массивов или объектов
     *                                     (это полезно вместе с <var>$keyCol</var> для переиндексации массива).
     * @param   int|string|null $keyCol    Индекс столбца или имя свойства объекта, которое будет использоваться в качестве ключа.
     *
     * @return  array  Столбец значений из исходного массива.
     *
     * @see     https://www.php.net/manual/ru/language.types.array.php
     * @see     https://www.php.net/manual/ru/function.array-column.php
     */
    public static function getColumn(array $array, string $valueCol, int|string $keyCol = null): array {
        return \array_reduce(
            $array,
            function ($result, $item) use ($keyCol, $valueCol) {
                $array = \is_object($item) ? get_object_vars($item) : $item;

                if ($valueCol === null) {
                    $value = $item;
                } else {
                    if (!array_key_exists($valueCol, $array)) {
                        return $result;
                    }

                    $value = $array[$valueCol];
                }

                if ($keyCol !== null && \array_key_exists($keyCol, $array) && \is_scalar($array[$keyCol])) {
                    $result[$array[$keyCol]] = $value;
                } else {
                    $result[] = $value;
                }

                return $result;
            },
            []
        );
    }

    /**
     * Служебная функция для возврата значения из именованного массива или указанного значения по умолчанию.
     *
     * @param ArrayAccess|array $array    Именованный массив или объект, реализующий ArrayAccess.
     * @param string            $name     Ключ для поиска (это может быть индекс массива или последовательность ключей, разделенных точками, как в реестре).
     * @param mixed|null        $default  Значение по умолчанию, которое выдается, если ключ не найден.
     * @param string            $type     Тип возвращаемого значения переменной (INT, FLOAT, STRING, WORD, BOOLEAN, ARRAY).
     *
     * @return  mixed
     * @throws  InvalidArgumentException
     */
    public static function getValue(ArrayAccess|array $array, string $name, mixed $default = null, string $type = ''): mixed {
        if (!\is_array($array) && !($array instanceof ArrayAccess)) {
            throw new InvalidArgumentException('Объект должен быть массивом или объектом, реализующим ArrayAccess.');
        }

        $result = null;

        if (isset($array[$name])) {
            $result = $array[$name];
        } elseif (strpos($name, '.')) {
            list($name, $subset) = explode('.', $name, 2);

            if (isset($array[$name]) && \is_array($array[$name])) {
                return self::getValue($array[$name], $subset, $default, $type);
            }
        }

        // Обработка случая по умолчанию
        if ($result === null) {
            $result = $default;
        }

        // Обработка ограничения типа
        switch (strtoupper($type)) {
            case 'INT':
            case 'INTEGER':
                // Используем только первое целое значение
                @preg_match('/-?[0-9]+/', $result, $matches);
                $result = @(int) $matches[0];

                break;

            case 'FLOAT':
            case 'DOUBLE':
                // Используем только первое значение с плавающей запятой
                @preg_match('/-?[0-9]+(\.[0-9]+)?/', $result, $matches);
                $result = @(float) $matches[0];

                break;

            case 'BOOL':
            case 'BOOLEAN':
                $result = (bool) $result;

                break;

            case 'ARRAY':
                if (!\is_array($result)) {
                    $result = [$result];
                }

                break;

            case 'STRING':
                $result = (string) $result;

                break;

            case 'WORD':
                $result = (string) preg_replace('#\W#', '', $result);

                break;

            case 'NONE':
            default:
                // Обработка не требуется
                break;
        }

        return $result;
    }

    /**
     * Принимает ассоциативный массив массивов и инвертирует ключи массива в значения, используя значения массива в качестве ключей.
     *
     * Пример:
     * $input = array(
     *     'New' => array('1000', '1500', '1750'),
     *     'Used' => array('3000', '4000', '5000', '6000')
     * );
     * $output = ArrayHelper::invert($input);
     *
     * Выход будет равен:
     * $output = array(
     *     '1000' => 'New',
     *     '1500' => 'New',
     *     '1750' => 'New',
     *     '3000' => 'Used',
     *     '4000' => 'Used',
     *     '5000' => 'Used',
     *     '6000' => 'Used'
     * );
     *
     * @param   array  $array  Исходный массив.
     *
     * @return  array
     */
    public static function invert(array $array): array {
        $return = [];

        foreach ($array as $base => $values) {
            if (!\is_array($values)) {
                continue;
            }

            foreach ($values as $key) {
                // Если ключ не скалярный, игнорируем его.
                if (is_scalar($key)) {
                    $return[$key] = $base;
                }
            }
        }

        return $return;
    }

    /**
     * Метод определения того, является ли массив ассоциативным массивом.
     *
     * @param array $array  Массив для тестирования.
     *
     * @return  boolean
     */
    public static function isAssociative(array $array): bool {
        if (\is_array($array)) {
            foreach (array_keys($array) as $k => $v) {
                if ($k !== $v) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Поворачивает массив для создания обратного поиска массива скаляров, массивов или объектов.
     *
     * @param   array           $source  Исходный массив.
     * @param   int|string|null $key     Если элементами исходного массива являются объекты или массивы, ключ для поворота.
     *
     * @return  array  Массив массивов, основанный либо на значении ключей, либо на отдельном ключе объекта или массива.
     */
    public static function pivot(array $source, int|string $key = null): array {
        $result  = [];
        $counter = [];

        foreach ($source as $index => $value) {
            // Определим имя сводного ключа и его значение.
            if (\is_array($value)) {
                // Если ключ не существует, игнорируем его.
                if (!isset($value[$key])) {
                    continue;
                }

                $resultKey   = $value[$key];
                $resultValue = $source[$index];
            } elseif (\is_object($value)) {
                // Если ключ не существует, игнорируем его.
                if (!isset($value->$key)) {
                    continue;
                }

                $resultKey   = $value->$key;
                $resultValue = $source[$index];
            } else {
                // Просто скалярное значение.
                $resultKey   = $value;
                $resultValue = $index;
            }

            // Счетчик отслеживает, сколько раз был использован ключ.
            if (empty($counter[$resultKey])) {
                // В первый раз мы просто присваиваем значение ключу.
                $result[$resultKey]  = $resultValue;
                $counter[$resultKey] = 1;
            } elseif ($counter[$resultKey] == 1) {
                // Если есть второй раз, мы конвертируем значение в массив.
                $result[$resultKey] = [
                    $result[$resultKey],
                    $resultValue,
                ];
                $counter[$resultKey]++;
            } else {
                // После второго раза отслеживать уже не нужно. Просто добавим к существующему массиву.
                $result[$resultKey][] = $resultValue;
            }
        }

        unset($counter);

        return $result;
    }

    /**
     * Служебная функция для сортировки массива объектов по заданному полю.
     *
     * @param   array      $a              Массив объектов.
     * @param   mixed      $k              Ключ (строка) или массив ключей для сортировки.
     * @param   int        $direction      Направление (целое число) или массив направлений для сортировки
     *                                     [1 = по возрастанию] [-1 = по убыванию]
     * @param   boolean    $caseSensitive  Логическое значение или массив логических значений,
     *                                     позволяющий выполнять сортировку с учетом или без учета регистра.
     * @param   boolean    $locale         Логическое значение или массив логических значений,
     *                                     позволяющие выполнять сортировку с использованием языка локали или нет.
     *
     * @return  array
     */
    public static function sortObjects(array $a, $k, int $direction = 1, bool $caseSensitive = true, bool $locale = false): array {
        if (!\is_array($locale) || !\is_array($locale[0])) {
            $locale = [$locale];
        }

        $sortCase      = (array) $caseSensitive;
        $sortDirection = (array) $direction;
        $key           = (array) $k;
        $sortLocale    = $locale;

        usort(
            $a,
            function ($a, $b) use ($sortCase, $sortDirection, $key, $sortLocale) {
                for ($i = 0, $count = \count($key); $i < $count; $i++) {
                    if (isset($sortDirection[$i])) {
                        $direction = $sortDirection[$i];
                    }

                    if (isset($sortCase[$i])) {
                        $caseSensitive = $sortCase[$i];
                    }

                    if (isset($sortLocale[$i])) {
                        $locale = $sortLocale[$i];
                    }

                    $va = $a->{$key[$i]};
                    $vb = $b->{$key[$i]};

                    if ((\is_bool($va) || is_numeric($va)) && (\is_bool($vb) || is_numeric($vb))) {
                        $cmp = $va - $vb;
                    } elseif ($caseSensitive) {
                        $cmp = StringHelper::strcmp($va, $vb, $locale);
                    } else {
                        $cmp = StringHelper::strcasecmp($va, $vb, $locale);
                    }

                    if ($cmp > 0) {
                        return $direction;
                    }

                    if ($cmp < 0) {
                        return -$direction;
                    }
                }

                return 0;
            }
        );

        return $a;
    }

    /**
     * Уникальный тест на безопасность многомерных массивов.
     *
     * @param   array  $array  Массив, который нужно сделать уникальным.
     *
     * @return  array
     *
     * @see     https://www.php.net/manual/ru/function.array-unique.php
     */
    public static function arrayUnique(array $array): array {
        $array = array_map('serialize', $array);
        $array = array_unique($array);
        $array = array_map('unserialize', $array);

        return $array;
    }

    /**
     * Улучшенный array_search, позволяющий частично сопоставлять значения строк в ассоциативных массивах.
     *
     * @param   string   $needle         Текст для поиска в массиве.
     * @param   array    $haystack       Ассоциативный массив для поиска $needle.
     * @param   boolean  $caseSensitive  True для поиска с учетом регистра (по умолчанию), иначе — false.
     *
     * @return  string|int|false    Возвращает соответствующий массив $key, если он найден, иначе — false.
     */
    public static function arraySearch(string $needle, array $haystack, bool $caseSensitive = true): string|int|false {
        foreach ($haystack as $key => $value) {
            $searchFunc = ($caseSensitive) ? 'strpos' : 'stripos';

            if ($searchFunc($value, $needle) === 0) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Метод рекурсивного преобразования данных в одномерный массив.
     *
     * @param object|array $array      Массив или объект для преобразования.
     * @param string       $separator  Ключевой разделитель.
     * @param string       $prefix     Префикс ключа последнего уровня.
     *
     * @return  array
     */
    public static function flatten(object|array $array, string $separator = '.', string $prefix = ''): array {
        if ($array instanceof \Traversable) {
            $array = iterator_to_array($array);
        } elseif (\is_object($array)) {
            $array = get_object_vars($array);
        }

        $result = [];

        foreach ($array as $k => $v) {
            $key = $prefix ? $prefix . $separator . $k : $k;

            if (\is_object($v) || \is_array($v)) {
                $result[] = self::flatten($v, $separator, $key);
            } else {
                $result[] = [$key => $v];
            }
        }

        return array_merge(...$result);
    }

    /**
     * Рекурсивно объединить массив.
     *
     * @param   array  ...$args  Список массивов для объединения.
     *
     * @return  array  Объединенный массив.
     *
     * @throws  InvalidArgumentException
     */
    public static function mergeRecursive(...$args): array {
        $result = [];

        foreach ($args as $i => $array) {
            if (!\is_array($array)) {
                throw new InvalidArgumentException(sprintf('Аргумент #%d не является массивом.', $i + 2));
            }

            foreach ($array as $key => &$value) {
                if (\is_array($value) && isset($result[$key]) && \is_array($result[$key])) {
                    $result[$key] = self::mergeRecursive($result [$key], $value);
                } else {
                    $result[$key] = $value;
                }
            }
        }

        return $result;
    }
}
