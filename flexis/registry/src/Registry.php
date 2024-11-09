<?php

/**
 * Часть пакета Flexis Framework Registry.
 */

namespace Flexis\Registry;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Flexis\Utilities\ArrayHelper;
use IteratorAggregate;
use JsonSerializable;
use stdClass;

/**
 * Класс реестра.
 */
class Registry implements JsonSerializable, ArrayAccess, IteratorAggregate, Countable {
    /**
     * Объект реестра.
     *
     * @var    stdClass
     */
    protected stdClass $data;

    /**
     * Флаг, если объект данных реестра был инициализирован
     *
     * @var    boolean
     */
    protected bool $initialized = false;

    /**
     * Разделитель путей
     *
     * @var    string
     */
    protected string $separator = '.';

    /**
     * Конструктор.
     *
     * @param  mixed|null $data       Данные для привязки к новому объекту реестра.
     * @param  string     $separator  Разделитель путей.
     */
    public function __construct(mixed $data = null, string $separator = '.') {
        $this->separator = $separator;
        // Создаём экземпляр внутреннего объекта данных.
        $this->data = new stdClass();
        // При необходимости загрузим предоставленные данные.
        if ($data instanceof self) {
            $this->merge($data);
        } elseif (\is_array($data) || \is_object($data)) {
            $this->bindData($this->data, $data);
        } elseif (!empty($data) && \is_string($data)) {
            $this->loadString($data);
        }
    }

    /**
     * Магический метод для клонирования объекта реестра.
     *
     * @return  void
     */
    public function __clone() {
        $this->data = \unserialize(\serialize($this->data));
    }

    /**
     * Магический метод для отображения этого объекта в виде строки с использованием аргументов по умолчанию метода toString.
     *
     * @return  string
     */
    public function __toString() {
        return $this->toString();
    }

    /**
     * Подсчёт элементов объекта данных.
     *
     * @return  integer  Пользовательский счетчик в виде целого числа.
     *
     * @link    https://www.php.net/manual/ru/countable.count.php
     */
    #[\ReturnTypeWillChange]
    public function count(): int {
        return \count(\get_object_vars($this->data));
    }

    /**
     * Реализация интерфейса JsonSerializable.
     * Позволяет нам передавать объекты реестра в json_encode.
     *
     * @return  object
     * @note    Интерфейс присутствует только в PHP 5.4 и выше.
     */
    #[\ReturnTypeWillChange]
    public function jsonSerialize(): object {
        return $this->data;
    }

    /**
     * Устанавливает значение по умолчанию, если оно ещё не назначено.
     *
     * @param string       $key      Имя параметра.
     * @param null|string  $default  Необязательное значение параметра.
     *
     * @return  mixed  Установленное значение или значение по умолчанию, если значение ранее не было установлено (или равно null).
     */
    public function def(string $key, null|string $default = ''): mixed {
        $value = $this->get($key, $default);
        $this->set($key, $value);

        return $value;
    }

    /**
     * Проверяет, существует ли путь в реестре.
     *
     * @param string $path  Путь реестра (например, flexis.content.showauthor).
     *
     * @return  boolean
     */
    public function exists(string $path): bool {
        // Вернём значение по умолчанию, если путь пуст
        if (empty($path)) {
            return false;
        }

        // Разобьём путь реестра на массив
        if ($this->separator === '') {
            $nodes = [$path];
        } else {
            $nodes = \explode($this->separator, $path);
        }

        // Инициализируем текущий узел как корень реестра.
        $node  = $this->data;
        $found = false;

        // Просмотрим реестр, чтобы найти правильный узел для результата.
        foreach ($nodes as $n) {
            if (\is_array($node) && isset($node[$n])) {
                $node  = $node[$n];
                $found = true;

                continue;
            }

            if (!isset($node->$n)) {
                return false;
            }

            $node  = $node->$n;
            $found = true;
        }

        return $found;
    }

    /**
     * Возвращает значение реестра.
     *
     * @param string     $path     Путь реестра (например, flexis.content.showauthor).
     * @param mixed|null $default  Необязательное значение по умолчанию, возвращается, если внутреннее значение равно null.
     *
     * @return  mixed  Value of entry or null
     */
    public function get(string $path, mixed $default = null): mixed {
        // Вернём значение по умолчанию, если путь пуст
        if (empty($path)) {
            return $default;
        }

        if ($this->separator === '' || !\strpos($path, $this->separator)) {
            return (isset($this->data->$path) && $this->data->$path !== null && $this->data->$path !== '')
                ? $this->data->$path
                : $default;
        }

        // Разобьём путь реестра на массив
        $nodes = \explode($this->separator, \trim($path));

        // Инициализируем текущий узел как корень реестра.
        $node  = $this->data;
        $found = false;

        // Просмотрим реестр, чтобы найти правильный узел для результата.
        foreach ($nodes as $n) {
            if (\is_array($node) && isset($node[$n])) {
                $node  = $node[$n];
                $found = true;

                continue;
            }

            if (!isset($node->$n)) {
                return $default;
            }

            $node  = $node->$n;
            $found = true;
        }

        if (!$found || $node === '') {
            return $default;
        }

        return $node;
    }

    /**
     * Получает этот объект, представленный как ArrayIterator.
     *
     * Это позволяет получать доступ к свойствам данных через оператор foreach.
     *
     * @return  ArrayIterator  Этот объект представлен как ArrayIterator.
     *
     * @see     \IteratorAggregate::getIterator()
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->data);
    }

    /**
     * Загрузите ассоциативный массив значений в пространство имен по умолчанию.
     *
     * @param array       $array      Ассоциативный массив значений для загрузки.
     * @param boolean     $flattened  False (по умолчанию) загрузка из одномерного массива.
     * @param string|null $separator  Ключевой разделитель.
     *
     * @return  $this
     */
    public function loadArray(array $array, bool $flattened = false, string $separator = null): static {
        if (!$flattened) {
            $this->bindData($this->data, $array);

            return $this;
        }

        if (!empty($separator)) {
            $this->separator = $separator;
        }

        foreach ($array as $k => $v) {
            $this->set($k, $v);
        }

        return $this;
    }

    /**
     * Загружает общедоступные переменные объекта в пространство имен по умолчанию.
     *
     * @param object $object  Объект, удерживающий паблики для загрузки.
     *
     * @return  $this
     */
    public function loadObject(object $object): static {
        $this->bindData($this->data, $object);
        return $this;
    }

    /**
     * Загружает содержимое файла в реестр.
     *
     * @param string $file     Путь к файлу для загрузки.
     * @param string $format   Формат файла [необязательно: по умолчанию JSON].
     * @param array  $options  Параметры, используемые форматером.
     *
     * @return  $this
     */
    public function loadFile(string $file, string $format = 'JSON', array $options = []): static {
        $data = \file_get_contents($file);
        return $this->loadString($data, $format, $options);
    }

    /**
     * Загружает строку в реестр.
     *
     * @param string $data     Строка для загрузки в реестр.
     * @param string $format   Формат строки.
     * @param array  $options  Параметры, используемые форматером.
     *
     * @return  $this
     */
    public function loadString(string $data, string $format = 'JSON', array $options = []): static {
        // Загрузим строку в заданное пространство имен [или пространство имен по умолчанию, если оно не указано]
        $obj = Factory::getFormat($format, $options)->stringToObject($data, $options);
        // Если объект данных ещё не инициализирован, назначим объект напрямую.
        if (!$this->initialized) {
            $this->data        = $obj;
            $this->initialized = true;

            return $this;
        }

        $this->loadObject($obj);

        return $this;
    }

    /**
     * Объединяет объект реестра.
     *
     * @param  Registry  $source     Объект исходного реестра для объединения.
     * @param  boolean   $recursive  Значение true для поддержки рекурсивного слияния дочерних значений.
     *
     * @return  $this
     */
    public function merge(Registry $source, bool $recursive = false): static {
        $this->bindData($this->data, $source->toArray(), $recursive, false);
        return $this;
    }

    /**
     * Метод извлечения подреестра из пути.
     *
     * @param string $path  Путь реестра (например, flexis.content.showauthor).
     *
     * @return  Registry  Объект реестра (пустой, если данные отсутствуют).
     */
    public function extract(string $path): Registry {
        $data = $this->get($path);
        return new Registry($data);
    }

    /**
     * Проверяет, существует ли смещение в итераторе.
     *
     * @param  mixed  $offset  Смещение массива.
     *
     * @return  boolean  True, если смещение существует, иначе — false.
     */
    #[\ReturnTypeWillChange]
    public function offsetExists(mixed $offset): bool {
        return $this->exists($offset);
    }

    /**
     * Получает смещение в итераторе.
     *
     * @param  mixed  $offset  Смещение массива.
     *
     * @return  mixed  Значение массива, если оно существует, иначе — значение null.
     */
    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset): mixed {
        return $this->get($offset);
    }

    /**
     * Устанавливает смещение в итераторе.
     *
     * @param  mixed  $offset  Смещение массива.
     * @param  mixed  $value   Значение массива.
     *
     * @return  void
     */
    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void {
        $this->set($offset, $value);
    }

    /**
     * Сбрасывает смещение в итераторе.
     *
     * @param  mixed  $offset  Смещение массива.
     *
     * @return  void
     */
    #[\ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void {
        $this->remove($offset);
    }

    /**
     * Устанавливает значение реестра.
     *
     * @param  string      $path       Путь к реестру (например, flexis.content.showauthor)
     * @param  mixed       $value      Значение.
     *
     * @return  mixed  Установленное значение.
     */
    public function set(string $path, mixed $value): mixed {
        /*
         * Разобьём путь реестра на массив и удалим пустые узлы,
         * возникающие в результате двойного разделителя. Пример: flexis..test
         * Изменим настройки массива таким образом, чтобы они были последовательными.
         */
        if ($this->separator === '') {
            $nodes = [$path];
        } else {
            $nodes = \array_values(\array_filter(\explode($this->separator, $path), 'strlen'));
        }

        if (!$nodes) {
            return null;
        }

        // Инициализируем текущий узел как корень реестра.
        $node = $this->data;
        // Просмотрим реестр, чтобы найти правильный узел для результата.
        for ($i = 0, $n = \count($nodes) - 1; $i < $n; $i++) {
            if (\is_object($node)) {
                if (!isset($node->{$nodes[$i]})) {
                    $node->{$nodes[$i]} = new stdClass();
                }

                // Передаём дочерний элемент как указатель, если это объект
                $node = &$node->{$nodes[$i]};

                continue;
            }

            if (\is_array($node)) {
                if (!isset($node[$nodes[$i]])) {
                    $node[$nodes[$i]] = new stdClass();
                }

                // Передаём дочерний элемент как указатель, если это массив
                $node = &$node[$nodes[$i]];
            }
        }

        // Получим старое значение, если оно существует, чтобы мы могли его вернуть.
        switch (true) {
            case (\is_object($node)):
                $result             = $node->{$nodes[$i]} ?? null;
                $node->{$nodes[$i]} = $value;
                break;

            case (\is_array($node)):
                $result           = $node[$nodes[$i]] ?? null;
                $node[$nodes[$i]] = $value;
                break;

            default:
                $result = null;
                break;
        }

        return $result;
    }

    /**
     * Добавляет значение к пути в реестре.
     *
     * @param string   $path   Путь родительского реестра (например, flexis.content.showauthor).
     * @param  mixed   $value  Значение.
     *
     * @return  mixed  Установленное значение.
     */
    public function append(string $path, mixed $value): mixed {
        $result = null;

        /*
         * Разобьём путь реестра на массив и удалим пустые узлы,
         * возникающие в результате двойного разделителя. Пример: flexis..test
         * Изменим настройки массива таким образом, чтобы они были последовательными.
         */
        if ($this->separator === '') {
            $nodes = [$path];
        } else {
            $nodes = \array_values(\array_filter(\explode($this->separator, $path), 'strlen'));
        }

        if ($nodes) {
            // Инициализируем текущий узел как корень реестра.
            $node = $this->data;

            // Просмотрим реестр, чтобы найти правильный узел для результата.
            // TODO Создать новый приватный метод из части кода ниже, так как он почти равен методу set.
            for ($i = 0, $n = \count($nodes) - 1; $i <= $n; $i++) {
                if (\is_object($node)) {
                    if (!isset($node->{$nodes[$i]}) && ($i !== $n)) {
                        $node->{$nodes[$i]} = new stdClass();
                    }

                    // Передаём дочерний элемент как указатель, если это объект
                    $node = &$node->{$nodes[$i]};
                } elseif (\is_array($node)) {
                    if (($i !== $n) && !isset($node[$nodes[$i]])) {
                        $node[$nodes[$i]] = new stdClass();
                    }

                    // Передаём дочерний элемент как указатель, если это массив
                    $node = &$node[$nodes[$i]];
                }
            }

            if (!\is_array($node)) {
                // Преобразуем узел в массив, чтобы сделать возможным добавление
                $node = \get_object_vars($node);
            }

            $node[] = $value;
            $result = $value;
        }

        return $result;
    }

    /**
     * Удаление значения реестра.
     *
     * @param string $path  Путь к реестру (например, flexis.content.showauthor)
     *
     * @return  mixed  Значение удаленного узла или ноль, если не установлено.
     */
    public function remove(string $path): mixed {
        // Простая оптимизация для прямого удаления узла, если нет разделителя.
        if ($this->separator === '' || !\strpos($path, $this->separator)) {
            $result = (isset($this->data->$path) && $this->data->$path !== null && $this->data->$path !== '')
                ? $this->data->$path
                : null;

            unset($this->data->$path);

            return $result;
        }

        /*
         * Разобьём путь реестра на массив и удалим пустые узлы,
         * возникающие в результате двойного разделителя. Пример: flexis..test
         * Изменим настройки массива таким образом, чтобы они были последовательными.
         */
        $nodes = \array_values(\array_filter(\explode($this->separator, $path), 'strlen'));

        if (!$nodes) {
            return null;
        }

        // Инициализируем текущий узел как корень реестра.
        $node   = $this->data;
        $parent = null;

        // Просмотрим реестр, чтобы найти правильный узел для результата.
        for ($i = 0, $n = \count($nodes) - 1; $i < $n; $i++) {
            if (\is_object($node)) {
                if (!isset($node->{$nodes[$i]})) continue;

                $parent = &$node;
                $node   = $node->{$nodes[$i]};

                continue;
            }

            if (\is_array($node)) {
                if (!isset($node[$nodes[$i]])) continue;

                $parent = &$node;
                $node   = $node[$nodes[$i]];
            }
        }

        // Получим старое значение, если оно существует, чтобы мы могли его вернуть.
        switch (true) {
            case \is_object($node):
                $result = $node->{$nodes[$i]} ?? null;
                unset($parent->{$nodes[$i]});
                break;

            case \is_array($node):
                $result = $node[$nodes[$i]] ?? null;
                unset($parent[$nodes[$i]]);
                break;

            default:
                $result = null;
                break;
        }

        return $result;
    }

    /**
     * Преобразует пространство имен в массив.
     *
     * @return  array  Ассоциативный массив, содержащий данные пространства имен.
     */
    public function toArray(): array {
        return $this->asArray($this->data);
    }

    /**
     * Преобразует пространство имен в объект.
     *
     * @return  object   Объект, содержащий данные пространства имен.
     */
    public function toObject(): object {
        return $this->data;
    }

    /**
     * Получает пространство имен в заданном строковом формате.
     *
     * @param  string  $format   Формат возврата строки.
     * @param  array   $options  Параметры, используемые форматировщиком, дополнительную информацию см. в разделе форматировщике.
     *
     * @return  string   Пространство имен в строковом формате.
     */
    public function toString(string $format = 'JSON', array $options = []): string {
        return Factory::getFormat($format, $options)->objectToString($this->data, $options);
    }

    /**
     * Метод рекурсивной привязки данных к родительскому объекту.
     *
     * @param  object  $parent     Родительский объект, к которому можно прикрепить значения данных.
     * @param  mixed   $data       Массив или объект данных для привязки к родительскому объекту.
     * @param  boolean $recursive  Значение true для поддержки рекурсивного связывания данных.
     * @param  boolean $allowNull  Значение true, чтобы разрешить нулевые значения.
     *
     * @return  void
     */
    protected function bindData(object $parent, mixed $data, bool $recursive = true, bool $allowNull = true): void {
        // Объект данных теперь инициализирован.
        $this->initialized = true;
        // Убедимся, что входные данные представляют собой массив.
        $data = \is_object($data) ? \get_object_vars($data) : (array) $data;

        foreach ($data as $k => $v) {
            if (!$allowNull && !(($v !== null) && ($v !== ''))) continue;

            if ($recursive && ((\is_array($v) && ArrayHelper::isAssociative($v)) || \is_object($v))) {
                if (!isset($parent->$k)) {
                    $parent->$k = new stdClass();
                }

                $this->bindData($parent->$k, $v);

                continue;
            }

            $parent->$k = $v;
        }
    }

    /**
     * Метод рекурсивного преобразования объекта данных в массив.
     *
     * @param object $data  Объект данных, возвращаемый в виде массива.
     *
     * @return  array  Представление массива входного объекта.
     */
    protected function asArray(object $data): array {
        $array = [];

        if (\is_object($data)) {
            $data = \get_object_vars($data);
        }

        foreach ($data as $k => $v) {
            if (\is_object($v) || \is_array($v)) {
                $array[$k] = $this->asArray($v);

                continue;
            }

            $array[$k] = $v;
        }

        return $array;
    }

    /**
     * Дамп в одномерный массив.
     *
     * @param string|null $separator  Ключевой разделитель.
     *
     * @return  string[]  Сброшенный массив.
     *
     */
    public function flatten(string $separator = null): array {
        $array = [];

        if (empty($separator)) {
            $separator = $this->separator;
        }

        $this->toFlatten($separator, $this->data, $array);

        return $array;
    }

    /**
     * Метод рекурсивного преобразования данных в одномерный массив.
     *
     * @param  string|null       $separator  Ключевой разделитель.
     * @param  object|array|null $data       Источник данных этой области.
     * @param  array             $array      Массив результатов, он передается по ссылке.
     * @param  string            $prefix     Префикс ключа последнего уровня.
     *
     * @return  void
     */
    protected function toFlatten(string $separator = null, object|array $data = null, array &$array = [], string $prefix = ''): void {
        $data = (array) $data;

        if (empty($separator)) {
            $separator = $this->separator;
        }

        foreach ($data as $k => $v) {
            $key = $prefix ? $prefix . $separator . $k : $k;

            if (\is_object($v) || \is_array($v)) {
                $this->toFlatten($separator, $v, $array, $key);

                continue;
            }

            $array[$key] = $v;
        }
    }
}
