<?php

/**
 * Часть пакета Flexis Data Framework.
 */

namespace Flexis\Data;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use Iterator;
use SplObjectStorage;

/**
 * DataSet — это класс коллекции, который позволяет разработчику работать с набором объектов DataObject, 
 * как если бы они находились в типичном массиве PHP.
 *
 * @property integer|boolean|null $current
 * @property DataObject[]         $objects
 */
class DataSet implements DumpableInterface, ArrayAccess, Countable, Iterator {
    /**
     * Текущая позиция итератора.
     *
     * @var    integer|boolean|null
     */
    private bool|int|null $current = false;

    /**
     * Итератор возражает.
     *
     * @var    DataObject[]
     */
    private array $objects = [];

    /**
     * Конструктор класса.
     *
     * @param   DataObject[]  $objects  Массив объектов DataObject для привязки к набору данных.
     *
     * @throws  InvalidArgumentException если объект не является DataObject.
     */
    public function __construct(array $objects = []) {
        $this->initialise($objects);
    }

    /**
     * Метод магического вызова используется для вызова методов объекта с помощью итератора.
     *
     * <pre>
     * Пример:
     * $array = $objectList->foo('bar');
     * </pre>
     *
     * Список объектов будет перебирать свои объекты и проверять, есть ли у каждого объекта вызываемый метод «foo».
     * Если да, то он передаст список аргументов и соберет все возвращаемые значения. 
     * Если у объекта нет вызываемого метода, возвращаемое значение не записывается.
     * Ключи объектов и массив результатов сохраняются.
     *
     * @param   string  $method     Имя вызываемого метода.
     * @param   array   $arguments  Аргументы вызываемого метода.
     *
     * @return  array   Массив значений, возвращаемых методами, вызванными для объектов в наборе данных.
     *
     */
    public function __call(string $method, array $arguments = []): array {
        $return = [];

        foreach ($this->objects as $key => $object) {
            $callback = [$object, $method];

            if (\is_callable($callback)) {
                $return[$key] = \call_user_func_array($callback, $arguments);
            }
        }

        return $return;
    }

    /**
     * Магический метод get используется для получения списка свойств объектов в наборе данных.
     *
     * <pre>
     * Пример:
     * $array = $dataSet->foo;
     * </pre>
     *
     * Это вернет столбец значений свойства «foo» во всех объектах (или значений, определенных настраиваемыми установщиками свойств в отдельных объектах данных).
     * Массив результатов будет содержать запись для каждого объекта в списке (по сравнению с __call, который может этого не сделать).
     * Ключи объектов и массив результатов сохраняются.
     *
     * @param   string  $property  Имя свойства данных.
     *
     * @return  array  Ассоциативный массив значений.
     *
     */
    public function __get(string $property): array {
        return array_map(function ($object) use ($property) {
            return $object->$property;
        }, $this->objects);
    }

    /**
     * Магический метод isset используется для проверки состояния свойства объекта с помощью итератора.
     *
     * <pre>
     * Пример:
     * $array = isset($objectList->foo);
     * </pre>
     *
     * @param   string  $property  Название объекта недвижимости.
     *
     * @return  boolean  True если свойство установлено в любом из объектов в наборе данных.
     *
     */
    public function __isset(string $property): bool {
        $return = [];

        foreach ($this->objects as $object) {
            $return[] = isset($object->$property);
        }

        return \in_array(true, $return, true);
    }

    /**
     * Метод магического набора используется для установки свойства объекта с помощью итератора.
     *
     * <pre>
     * Пример:
     * $objectList->foo = 'bar';
     * </pre>
     *
     * Это приведет к тому, что для свойства «foo» будет установлено значение «bar» во всех объектах (или значение, определенное настройщиками пользовательских свойств в DataObject).
     *
     * @param   string  $property  Название объекта недвижимости.
     * @param   mixed   $value     Значение, передаваемое свойству данных.
     *
     * @return  void
     *
     */
    public function __set(string $property, mixed $value): void {
        foreach ($this->objects as $object) {
            $object->$property = $value;
        }
    }

    /**
     * Магический метод unset используется для отмены установки свойства объекта с помощью итератора.
     *
     * <pre>
     * Пример:
     * unset($objectList->foo);
     * </pre>
     *
     * Это приведет к отключению всех свойств «foo» в списке DataObject.
     *
     * @param   string  $property  Название объекта недвижимости.
     *
     * @return  void
     *
     */
    public function __unset(string $property): void {
        foreach ($this->objects as $object) {
            unset($object->$property);
        }
    }

    /**
     * Возвращает массив ключей, существующих в объектах
     *
     * @param   string  $type  Тип выбора «all» или «common»
     *
     * @return  array   Массив ключей
     *
     * @throws  InvalidArgumentException
     */
    public function getObjectsKeys(string $type = 'all'): array {
        $keys = null;

        if ($type == 'all') {
            $function = 'array_merge';
        } elseif ($type == 'common') {
            $function = 'array_intersect_key';
        } else {
            throw new InvalidArgumentException("Unknown selection type: $type");
        }

        foreach ($this->objects as $object) {
            $objectVars = json_decode(json_encode($object), true);

            $keys = ($keys === null) ? $objectVars : $function($keys, $objectVars);
        }

        return array_keys($keys);
    }

    /**
     * Возвращает все объекты в виде массива
     *
     * @param   boolean  $associative  Возможность установить режим возврата: ассоциативный или числовой массив.
     * @param   string   ...$keys      Неограниченное количество дополнительных имен свойств, которые можно извлечь из объектов.
     *
     * @return  array  Возвращает массив в соответствии с определенными параметрами.
     *
     */
    public function toArray(bool $associative = true, ...$keys): array {
        if (empty($keys)) {
            $keys = $this->getObjectsKeys();
        }

        $return = [];

        $i = 0;

        foreach ($this->objects as $key => $object) {
            $arrayItem = [];

            $key = ($associative) ? $key : $i++;

            $j = 0;

            foreach ($keys as $property) {
                $propertyKey             = ($associative) ? $property : $j++;
                $arrayItem[$propertyKey] = $object->$property ?? null;
            }

            $return[$key] = $arrayItem;
        }

        return $return;
    }

    /**
     * Возвращает количество объектов данных в наборе.
     *
     * @return  integer  Количество объектов.
     *
     */
    public function count(): int {
        return \count($this->objects);
    }

    /**
     * Очищает объекты в наборе данных.
     *
     * @return  DataSet  Возвращает себя, чтобы разрешить цепочку.
     *
     */
    public function clear(): DataSet {
        $this->objects = [];
        $this->rewind();

        return $this;
    }

    /**
     * Возвращает текущий объект данных в наборе.
     *
     * @return  DataObject|false  Текущий объект или значение false, если массив пуст или указатель находится за концом элементов.
     *
     */
    public function current(): DataObject|bool {
        return is_scalar($this->current) ? $this->objects[$this->current] : false;
    }

    /**
     * Выводит объект данных из набора, при необходимости рекурсивно.
     *
     * @param   integer                $depth   Максимальная глубина рекурсии (по умолчанию = 3).
     *                                          Например, глубина 0 вернет stdClass со всеми свойствами в собственной форме. Глубина 1 будет рекурсивно относиться только к первому уровню свойств.
     * @param   SplObjectStorage|null  $dumped  Массив уже сериализованных объектов, используемый во избежание бесконечных циклов.
     *
     * @return  array  Ассоциативный массив объектов данных в наборе, сохраненный как простой объект PHP stdClass.
     *
     * @see     DataObject::dump()
     */
    public function dump(int $depth = 3, SplObjectStorage $dumped = null): array {
        if ($dumped === null) {
            $dumped = new SplObjectStorage();
        }

        $dumped->attach($this);

        $objects = [];

        if ($depth > 0) {
            foreach ($this->objects as $key => $object) {
                $objects[$key] = $object->dump($depth, $dumped);
            }
        }

        return $objects;
    }

    /**
     * Возвращает набор данных в форме, которую можно сериализовать в формат JSON.
     *
     * Обратите внимание, что этот метод не возвращает ассоциативный массив, иначе он будет закодирован в объект.
     * Декодеры JSON не поддерживают последовательно порядок ассоциативных ключей, тогда как они поддерживают порядок массивов.
     *
     * @return  array
     *
     */
    public function jsonSerialize(): array {
        $return = [];

        foreach ($this->objects as $object) {
            $return[] = $object;
        }

        return $return;
    }

    /**
     * Возвращает ключ текущего объекта в итераторе.
     *
     * @return  integer|false  Ключ объекта на успех; false в случае неудачи.
     *
     */
    public function key(): int|bool {
        return $this->current;
    }

    /**
     * Возвращает массив ключей для всех объектов в итераторе (эмулирует array_keys).
     *
     * @return  array  Массив ключей
     *
     */
    public function keys(): array {
        return array_keys($this->objects);
    }

    /**
     * Применяет функцию к каждому объекту в наборе (эмулирует array_walk).
     *
     * @param   callable  $funcname  Функция обратного вызова.
     *
     * @return  boolean
     *
     * @throws  InvalidArgumentException
     */
    public function walk(callable $funcname): bool {
        foreach ($this->objects as $key => $object) {
            $funcname($object, $key);
        }

        return true;
    }

    /**
     * Перемещает итератор к следующему объекту в итераторе.
     *
     * @return  void
     *
     */
    public function next(): void {
        $keys = $this->keys();

        if ($this->current === false && isset($keys[0])) {
            $this->current = $keys[0];
        } else {
            $position = array_search($this->current, $keys);

            if ($position !== false && isset($keys[$position + 1])) {
                $this->current = $keys[$position + 1];
            } else {
                $this->current = null;
            }
        }
    }

    /**
     * Проверяет, существует ли смещение в итераторе.
     *
     * @param   mixed  $offset  Смещение объекта.
     *
     * @return  boolean
     *
     */
    public function offsetExists(mixed $offset): bool {
        return isset($this->objects[$offset]);
    }

    /**
     * Возвращает смещение в итераторе.
     *
     * @param   mixed  $offset  Смещение объекта.
     *
     * @return  DataObject|null
     *
     */
    public function offsetGet(mixed $offset): ?DataObject {
        return $this->objects[$offset] ?? null;
    }

    /**
     * Устанавливает смещение в итераторе.
     *
     * @param   mixed       $offset  Смещение объекта.
     * @param   mixed       $value   DataObject.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException если объект не является экземпляром DataObject.
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        if (!($value instanceof DataObject)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Аргумент $object должен быть экземпляром "%s", задан %s.',
                    DataObject::class,
                    \gettype($value) === 'object' ? \get_class($value) : \gettype($value)
                )
            );
        }

        if ($offset === null) {
            $this->objects[] = $value;
        } else {
            $this->objects[$offset] = $value;
        }
    }

    /**
     * Сбрасывает смещение в итераторе.
     *
     * @param   mixed  $offset  Смещение объекта.
     *
     * @return  void
     *
     */
    public function offsetUnset(mixed $offset): void {
        if (!isset($this[$offset])) {
            return;
        }

        if ($offset == $this->current) {
            $keys     = $this->keys();
            $position = array_search($this->current, $keys);

            if ($position > 0) {
                $this->current = $keys[$position - 1];
            } else {
                $this->current = false;
            }
        }

        unset($this->objects[$offset]);
    }

    /**
     * Перематывает итератор к первому объекту.
     *
     * @return  void
     *
     */
    public function rewind(): void {
        if (empty($this->objects)) {
            $this->current = false;
        } else {
            $keys          = $this->keys();
            $this->current = array_shift($keys);
        }
    }

    /**
     * Проверяет итератор.
     *
     * @return  boolean
     *
     */
    public function valid(): bool {
        if (!is_scalar($this->current) || !isset($this->objects[$this->current])) {
            return false;
        }

        return true;
    }

    /**
     * Инициализирует список массивом объектов.
     *
     * @param   array  $input  Массив объектов.
     *
     * @return  void
     *
     * @throws  InvalidArgumentException если объект не является DataObject.
     */
    private function initialise(array $input = []): void {
        foreach ($input as $key => $object) {
            if ($object !== null) {
                $this[$key] = $object;
            }
        }

        $this->rewind();
    }
}
