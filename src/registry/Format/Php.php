<?php

/**
 * Часть пакета Flexis Framework Registry.
 */

namespace Flexis\Registry\Format;

use Flexis\Registry\FormatInterface;
use stdClass;

/**
 * Обработчик формата класса PHP для реестра
 */
class Php implements FormatInterface {
    /**
     * Преобразует объект в строку класса PHP.
     * - ПРИМЕЧАНИЕ. Поддерживается только один уровень глубины.
     *
     * @param  object  $object  Объект источника данных.
     * @param  array   $options Параметры, используемые форматером.
     *
     * @return  string  Форматированная строка класса конфигурации.
     *                  Формат PHP учитывает тип данных каждого значения при создании исходного кода PHP.
     */
    public function objectToString(object $object, array $options = []): string {
        // Класс должен быть указан
        $class = $options['class'] ?? 'Registry';

        // Создаём строку переменных объекта
        $vars = '';

        foreach (\get_object_vars($object) as $k => $v) {
            $vars .= "\tpublic \$$k = " . $this->formatValue($v) . ";\n";
        }

        $str = "<?php\n";

        // Если указано, добавим пространство имен к объекту класса.
        if (isset($options['namespace']) && $options['namespace'] !== '') {
            $str .= 'namespace ' . $options['namespace'] . ";\n\n";
        }

        $str .= "class $class {\n";
        $str .= $vars;
        $str .= '}';

        // Используем закрывающий тег, если в параметрах для него не установлено значение false.
        if (!isset($options['closingtag']) || $options['closingtag'] !== false) {
            $str .= "\n?>";
        }

        return $str;
    }

    /**
     * Разберает строку в формате класса PHP и преобразуйте ее в объект.
     *
     * @param string $data     Строка в формате класса PHP для преобразования.
     * @param  array   $options  Параметры, используемые форматером.
     *
     * @return  object   Объект данных.
     */
    public function stringToObject(string $data, array $options = []): object {
        return new stdClass();
    }

    /**
     * Форматирование значения для преобразования строк.
     *
     * @param  mixed  $value  Значение для форматирования.
     *
     * @return  mixed  Отформатированное значение.
     *
     */
    protected function formatValue(mixed $value): mixed {
        return match (\gettype($value)) {
            'string' => "'" . \addcslashes($value, '\\\'') . "'",
            'array', 'object' => $this->getArrayString((array)$value),
            'double', 'integer' => $value,
            'boolean' => $value ? 'true' : 'false',
            'NULL' => 'null',
            default => null,
        };
    }

    /**
     * Метод получения массива в виде экспортированной строки.
     *
     * @param array $a  Массив, который нужно получить в виде строки.
     *
     * @return  string
     */
    protected function getArrayString(array $a): string {
        $s = 'array(';
        $i = 0;

        foreach ($a as $k => $v) {
            $s .= $i ? ', ' : '';
            $s .= "'" . \addcslashes($k, '\\\'') . "' => ";
            $s .= $this->formatValue($v);

            $i++;
        }

        $s .= ')';

        return $s;
    }
}
