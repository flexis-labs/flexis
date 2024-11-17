<?php

/**
 * Часть пакета Flexis Input Framework.
 */

namespace Flexis\Input;

/**
 * Input класс файлы.
 */
class Files extends Input {
    /**
     * Сводные данные из $_FILES или совместимого массива.
     *
     * @var    array
     */
    protected array $decodedData = [];

    /**
     * Конструктор класса.
     *
     * @param   array|null  $source   Исходные данные (необязательно, по умолчанию $_FILES)
     * @param   array       $options  Массив параметров конфигурации (необязательно)
     */
    public function __construct(?array $source = null, array $options = []) {
        $source = $source ?? $_FILES;
        parent::__construct($source, $options);
    }

    /**
     * Получает значение из входных данных.
     *
     * @param   string  $name     Имя входного свойства (обычно имя тега INPUT файла), которое необходимо получить.
     * @param   mixed   $default  Значение по умолчанию, которое возвращается, если именованное свойство не существует.
     * @param   string  $filter   Фильтр, применяемый к значению.
     *
     * @return  mixed  Отфильтрованное входное значение.
     *
     * @see     \Flexis\Filter\InputFilter::clean()
     */
    public function get(string $name, mixed $default = null, string $filter = 'cmd'): mixed {
        if (isset($this->data[$name])) {
            return $this->decodeData(
                [
                    $this->data[$name]['name'],
                    $this->data[$name]['type'],
                    $this->data[$name]['tmp_name'],
                    $this->data[$name]['error'],
                    $this->data[$name]['size'],
                ]
            );
        }

        return $default;
    }

    /**
     * Метод декодирования массива данных.
     *
     * @param   array  $data  Массив данных для декодирования.
     *
     * @return  array
     */
    protected function decodeData(array $data): array {
        $result = [];

        if (\is_array($data[0])) {
            foreach ($data[0] as $k => $v) {
                $result[$k] = $this->decodeData([$data[0][$k], $data[1][$k], $data[2][$k], $data[3][$k], $data[4][$k]]);
            }

            return $result;
        }

        return ['name' => $data[0], 'type' => $data[1], 'tmp_name' => $data[2], 'error' => $data[3], 'size' => $data[4]];
    }

    /**
     * Устанавливает значение.
     *
     * @param   string  $name   Имя входного свойства, которое необходимо установить.
     * @param   mixed   $value  Значение, которое необходимо присвоить входному свойству.
     *
     * @return  void
     */
    public function set(string $name, mixed $value): void {
        // Ограничивает использование родительского метода set.
    }
}
