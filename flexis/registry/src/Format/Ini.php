<?php

/**
 * Часть пакета Flexis Framework Registry.
 */

namespace Flexis\Registry\Format;

use Flexis\Registry\FormatInterface;
use Flexis\Utilities\ArrayHelper;
use stdClass;

/**
 * Формат INI предназначен для реестра.
 */
class Ini implements FormatInterface {
    /**
     * Массив опций по умолчанию
     *
     * @var    array
     */
    protected static array $options = [
        'supportArrayValues' => false,
        'parseBooleanWords'  => false,
        'processSections'    => false,
    ];

    /**
     * Кэш, используемый stringToObject.
     *
     * @var    array
     */
    protected static array $cache = [];

    /**
     * Преобразует объект в строку в формате INI.
     * К сожалению, невозможно вложить значения ini глубже, чем на два уровня.
     * Поэтому мы пройдём только первые два уровня объекта.
     *
     * @param  object $object   Объект источника данных.
     * @param  array  $options  Параметры, используемые форматером.
     *
     * @return  string  Строка в формате INI.
     */
    public function objectToString(object $object, array $options = []): string {
        $options            = \array_merge(static::$options, $options);
        $supportArrayValues = $options['supportArrayValues'];

        $local  = [];
        $global = [];

        $variables = \get_object_vars($object);

        $last = \count($variables);
        // Предположим, что первый элемент находится в разделе
        $inSection = true;
        // Перейдём по объекту, чтобы установить свойства.
        foreach ($variables as $key => $value) {
            // Если значение является объектом, нам нужно поместить его в локальный раздел.
            if (\is_object($value)) {
                // Добавим пустую строку, если предыдущая строка не была в разделе.
                if (!$inSection) {
                    $local[] = '';
                }

                // Добавим раздел.
                $local[] = '[' . $key . ']';

                // Добавим свойства для этого раздела.
                foreach (\get_object_vars($value) as $k => $v) {
                    if (\is_array($v) && $supportArrayValues) {
                        $assoc = ArrayHelper::isAssociative($v);

                        foreach ($v as $arrayKey => $item) {
                            $arrayKey = $assoc ? $arrayKey : '';
                            $local[]  = $k . '[' . $arrayKey . ']=' . $this->getValueAsIni($item);
                        }
                    } else {
                        $local[] = $k . '=' . $this->getValueAsIni($v);
                    }
                }

                // Добавим пустую строку после раздела, если он не последний.
                if (--$last !== 0) {
                    $local[] = '';
                }
            } elseif (\is_array($value) && $supportArrayValues) {
                $assoc = ArrayHelper::isAssociative($value);

                foreach ($value as $arrayKey => $item) {
                    $arrayKey = $assoc ? $arrayKey : '';
                    $global[] = $key . '[' . $arrayKey . ']=' . $this->getValueAsIni($item);
                }
            } else {
                // Не в разделе, поэтому добавим свойство в глобальный массив.
                $global[]  = $key . '=' . $this->getValueAsIni($value);
                $inSection = false;
            }
        }

        return \implode("\n", \array_merge($global, $local));
    }

    /**
     * Разбирает строку в формате INI и преобразует её в объект.
     *
     * @param string $data     Строка в формате INI для преобразования.
     * @param  array   $options  Массив параметров, используемых форматировщиком, или логический параметр для обработки разделов.
     *
     * @return  object   Объект данных.
     */
    public function stringToObject(string $data, array $options = []): object {
        $options = \array_merge(static::$options, $options);
        // Проверим кэш памяти на наличие уже обработанных строк.
        $hash = \md5($data . ':' . (int) $options['processSections']);

        if (isset(static::$cache[$hash])) {
            return static::$cache[$hash];
        }

        // Если строк нет, просто вернём объект.
        if (empty($data)) {
            return new stdClass();
        }

        $obj     = new stdClass();
        $section = false;
        $array   = false;
        $lines   = \explode("\n", $data);

        // Обработаем строки.
        foreach ($lines as $line) {
            // Обрежем все ненужные пробелы.
            $line = \trim($line);

            // Игнорируем пустые строки и комментарии.
            if (empty($line) || ($line[0] === ';')) {
                continue;
            }

            if ($options['processSections']) {
                $length = \strlen($line);

                // Если мы обрабатываем разделы и строка является разделом, добавим объект и продолжим.
                if ($line[0] === '[' && ($line[$length - 1] === ']')) {
                    $section       = \substr($line, 1, $length - 2);
                    $obj->$section = new stdClass();

                    continue;
                }
            } elseif ($line[0] === '[') {
                continue;
            }

            // Убедимся, что знак равенства существует и не является первым символом строки.
            if (!\strpos($line, '=')) {
                // Может быть, выбросить исключение?
                continue;
            }

            // Получим ключ и значение для строки.
            [$key, $value] = \explode('=', $line, 2);

            // Если у нас есть элемент массива
            if (str_ends_with($key, ']') && ($openBrace = \strpos($key, '[', 1)) !== false) {
                if ($options['supportArrayValues']) {
                    $array    = true;
                    $arrayKey = \substr($key, $openBrace + 1, -1);

                    // Если у нас есть многомерный массив или неверный ключ
                    if (str_contains($arrayKey, '[') || str_contains($arrayKey, ']')) {
                        // Может быть, выбросить исключение?
                        continue;
                    }

                    $key = \substr($key, 0, $openBrace);
                } else {
                    continue;
                }
            }

            // Проверим ключ.
            if (\preg_match('/[^A-Z\d_]/i', $key)) {
                // Может быть, выбросить исключение?
                continue;
            }

            // Если значение заключено в кавычки, мы предполагаем, что это строка.
            $length = \strlen($value);

            if ($length && ($value[0] === '"') && ($value[$length - 1] === '"')) {
                // Удалим кавычки и преобразуем символы новой строки.
                $value = \stripcslashes(\substr($value, 1, $length - 2));
                $value = \str_replace('\n', "\n", $value);
            } else {
                // Если значение не заключено в кавычки, мы предполагаем, что оно не является строкой.

                // Если значение false, логическое значение false.
                if ($value === 'false') {
                    $value = false;
                } elseif ($value === 'true') {
                    // Если значение true, предполагается логическое значение true.
                    $value = true;
                } elseif ($options['parseBooleanWords'] && \in_array(\strtolower($value), ['yes', 'no'], true)) {
                    // Если значение yes или no и опция включена, примем соответствующее логическое значение.
                    $value = (\strtolower($value) === 'yes');
                } elseif (\is_numeric($value)) {
                    // Если значение является числовым, то оно является либо плавающим, либо целым.
                    // Если есть точка, мы предполагаем плавающую величину.
                    if (str_contains($value, '.')) {
                        $value = (float) $value;
                    } else {
                        $value = (int) $value;
                    }
                }
            }

            // Если раздел установлен, добавим ключ/значение в раздел, иначе - на верхний уровень.
            if ($section) {
                if ($array) {
                    if (!isset($obj->$section->$key)) {
                        $obj->$section->$key = [];
                    }

                    if (!empty($arrayKey)) {
                        $obj->$section->{$key}[$arrayKey] = $value;
                    } else {
                        $obj->$section->{$key}[] = $value;
                    }
                } else {
                    $obj->$section->$key = $value;
                }
            } else {
                if ($array) {
                    if (!isset($obj->$key)) {
                        $obj->$key = [];
                    }

                    if (!empty($arrayKey)) {
                        $obj->{$key}[$arrayKey] = $value;
                    } else {
                        $obj->{$key}[] = $value;
                    }
                } else {
                    $obj->$key = $value;
                }
            }

            $array = false;
        }

        // Кэшируем строку, чтобы сэкономить циклы процессора :)
        static::$cache[$hash] = clone $obj;

        return $obj;
    }

    /**
     * Метод получения значения в формате INI.
     *
     * @param  mixed  $value  Значение для преобразования в формат INI.
     *
     * @return  string  Значение в формате INI.
     */
    protected function getValueAsIni(mixed $value): string {
        $string = '';

        switch (\gettype($value)) {
            case 'integer':
            case 'double':
                $string = $value;

                break;

            case 'boolean':
                $string = $value ? 'true' : 'false';

                break;

            case 'string':
                // Очистим любые символы CRLF.
                $string = '"' . \str_replace(["\r\n", "\n"], '\\n', $value) . '"';

                break;
        }

        return $string;
    }
}
