<?php

/**
 * Часть пакета Flexis Data Framework.
 */

namespace Flexis\Data;

use ArrayIterator;
use Countable;
use Flexis\Registry\Registry;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use SplObjectStorage;

/**
 * DataObject — это класс, который используется для хранения данных, но позволяет вам получать к ним доступ, имитируя способ обработки свойств класса PHP.
 *
 * @property array $properties
 */
class DataObject implements DumpableInterface, IteratorAggregate, JsonSerializable, Countable {
    /**
     * Свойства объекта данных.
     *
     * @var    array
     */
    private array $properties = [];

    /**
     * Конструктор класса.
     *
     * @param   mixed  $properties  Либо ассоциативный массив, либо другой объект, с помощью которого можно задать начальные свойства нового объекта.
     *
     * @throws  InvalidArgumentException
     */
    public function __construct(mixed $properties = []) {
        if (!empty($properties)) {
            $this->bind($properties);
        }
    }

    /**
     * Магический метод get используется для получения свойства данных.
     *
     * Этот метод является общедоступным прокси для защищенного метода getProperty.
     *
     * Примечание: Магический метод __get не допускает рекурсивных вызовов. Это может быть сложно, потому что ошибка,
     * генерируемая при рекурсии в __get - это "Неопределенное свойство: {CLASS}::{PROPERTY}", что вводит в заблуждение.
     * Это актуально для данного класса, поскольку запрос невидимого свойства может вызвать вызов подфункции.
     * Если это ссылается на свойство непосредственно в объекте, это вызовет рекурсию в __get.
     *
     * @param   string  $property  Имя свойства данных.
     *
     * @return  mixed  Значение свойства данных или значение NULL, если свойство данных не существует.
     *
     * @see     DataObject::getProperty()
     */
    public function __get(string $property) {
        return $this->getProperty($property);
    }

    /**
     * Магический метод isset используется для проверки состояния свойства объекта.
     *
     * @param   string  $property  Имя свойства данных.
     *
     * @return  boolean
     *
     */
    public function __isset(string $property) {
        return isset($this->properties[$property]);
    }

    /**
     * Метод магического набора используется для установки свойства данных.
     *
     * Это общедоступный прокси для защищенного метода setProperty.
     *
     * @param   string  $property  Имя свойства данных.
     * @param   mixed   $value     Значение, передаваемое свойству данных.
     *
     * @return  void
     *
     * @see     DataObject::setProperty()
     */
    public function __set(string $property, mixed $value) {
        $this->setProperty($property, $value);
    }

    /**
     * Магический метод unset используется для отмены установки свойства данных.
     *
     * @param   string  $property  Имя свойства данных.
     *
     * @return  void
     *
     */
    public function __unset(string $property) {
        unset($this->properties[$property]);
    }

    /**
     * Привязывает массив или объект к этому объекту.
     *
     * @param   mixed    $properties   Ассоциативный массив свойств или объект.
     * @param   boolean  $updateNulls  True для привязки нулевых значений, false для игнорирования нулевых значений.
     *
     * @return  $this
     *
     * @throws  InvalidArgumentException
     */
    public function bind(mixed $properties, bool $updateNulls = true): self {
        if (!\is_array($properties) && !\is_object($properties)) {
            throw new InvalidArgumentException(
                sprintf('Аргумент $properties должен быть массивом или объектом, задан %s.', \gettype($properties))
            );
        }

        if ($properties instanceof \Traversable) {
            $properties = iterator_to_array($properties);
        } elseif (\is_object($properties)) {
            $properties = (array) $properties;
        }

        foreach ($properties as $property => $value) {
            if ($value === null && !$updateNulls) {
                continue;
            }

            $this->setProperty($property, $value);
        }

        return $this;
    }

    /**
     * Сбрасывает свойства данных в объект, при необходимости рекурсивно.
     *
     * @param   integer                 $depth  Максимальная глубина рекурсии (по умолчанию = 3).
     *                                          Например, глубина 0 вернет stdClass со всеми свойствами в собственной форме. Глубина 1 будет рекурсивно относиться только к первому уровню свойств.
     * @param   SplObjectStorage|null  $dumped  Массив уже сериализованных объектов, используемый во избежание бесконечных циклов.
     *
     * @return  \stdClass
     *
     */
    public function dump(int $depth = 3, SplObjectStorage $dumped = null): \stdClass {
        if ($dumped === null) {
            $dumped = new SplObjectStorage();
        }

        $dumped->attach($this);

        $dump = new \stdClass();

        foreach (array_keys($this->properties) as $property) {
            $dump->$property = $this->dumpProperty($property, $depth, $dumped);
        }

        return $dump;
    }

    /**
     * Возвращает этот объект, представленный как ArrayIterator.
     *
     * Это позволяет получить доступ к свойствам данных через оператор foreach.
     *
     * @return  ArrayIterator
     *
     * @see     IteratorAggregate::getIterator()
     */
    public function getIterator(): ArrayIterator {
        return new ArrayIterator($this->dump(0));
    }

    /**
     * Возвращает свойства данных в форме, которую можно сериализовать в формат JSON.
     *
     * @return  \stdClass
     *
     */
    public function jsonSerialize(): \stdClass {
        return $this->dump();
    }

    /**
     * Выводит свойство данных.
     *
     * Если установлена рекурсия, этот метод выгрузит любой объект, реализующий DumpableInterface (например, DataObject и DataSet);
     * он преобразует объект DateTimeInterface в строку; и он преобразует Flexis\Registry\Registry в объект.
     *
     * @param   string             $property  Имя свойства данных.
     * @param   integer            $depth     Текущая глубина рекурсии (значение 0 будет игнорировать рекурсию).
     * @param   SplObjectStorage   $dumped    Массив уже сериализованных объектов, используемый во избежание бесконечных циклов.
     *
     * @return  mixed
     *
     */
    protected function dumpProperty(string $property, int $depth, SplObjectStorage $dumped): mixed {
        $value = $this->getProperty($property);

        if ($depth > 0) {
            if ($value instanceof DumpableInterface) {
                if (!$dumped->contains($value)) {
                    $value = $value->dump($depth - 1, $dumped);
                }
            }

            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            } elseif ($value instanceof Registry) {
                $value = $value->toObject();
            }
        }

        return $value;
    }

    /**
     * Возвращает свойство данных.
     *
     * @param   string  $property  Имя свойства данных.
     *
     * @return  mixed  Значение свойства данных.
     *
     * @see     DataObject::__get()
     */
    protected function getProperty(string $property): mixed {
        return $this->properties[$property] ?? null;
    }

    /**
     * Устанавливает свойство данных.
     *
     * Если имя свойства начинается с нулевого байта, этот метод вернет значение null.
     *
     * @param   string  $property  Имя свойства данных.
     * @param   mixed   $value     Значение, передаваемое свойству данных.
     *
     * @return  mixed  Значение свойства данных.
     *
     * @see     DataObject::__set()
     */
    protected function setProperty(string $property, mixed $value): mixed {
        if (str_starts_with($property, "\0")) {
            return null;
        }

        $this->properties[$property] = $value;

        return $value;
    }

    /**
     * Возвращает количество свойств данных.
     *
     * @return  integer  Количество свойств данных.
     *
     */
    public function count(): int {
        return \count($this->properties);
    }
}
