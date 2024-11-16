<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

use Countable;
use InvalidArgumentException;
use Iterator;

/**
 * Класс драйвера базы данных Flexis Framework
 */
class DatabaseIterator implements Countable, Iterator {
    /**
     * Класс создаваемого объекта.
     *
     * @var    string
     */
    protected mixed $class;

    /**
     * Имя столбца, который будет использоваться в качестве ключа записи базы данных.
     *
     * @var    mixed
     */
    private mixed $column;

    /**
     * Текущая запись базы данных.
     *
     * @var    mixed
     */
    private mixed $current;

    /**
     * Числовой или строковый ключ для текущей записи базы данных.
     *
     * @var    scalar
     */
    private string|int|bool|float $key;

    /**
     * Количество полученных записей.
     *
     * @var    integer
     */
    private int $fetched = 0;

    /**
     * Оператор, содержащий набор результатов для повторения.
     *
     * @var    StatementInterface|null
     */
    protected ?StatementInterface $statement;

    /**
     * Конструктор итератора базы данных.
     *
     * @param StatementInterface  $statement  Оператор, содержащий набор результатов для повторения.
     * @param string|null         $column     Столбец параметров, который будет использоваться в качестве ключа итератора.
     * @param string              $class      Класс возвращаемого объекта.
     *
     * @throws  InvalidArgumentException
     */
    public function __construct(StatementInterface $statement, string $column = null, string $class = \stdClass::class) {
        if (!class_exists($class)) {
            throw new InvalidArgumentException(sprintf('новый %s(*%s*, курсор)', \get_class($this), \gettype($class)));
        }

        if ($statement) {
            $fetchMode = $class === \stdClass::class ? FetchMode::STANDARD_OBJECT : FetchMode::CUSTOM_OBJECT;

            if ($fetchMode === FetchMode::STANDARD_OBJECT) {
                $statement->setFetchMode($fetchMode);
            } else {
                $statement->setFetchMode($fetchMode, $class);
            }
        }

        $this->statement = $statement;
        $this->class     = $class;
        $this->column    = $column;
        $this->fetched   = 0;
        $this->next();
    }

    /**
     * Деструктор итератора базы данных.
     */
    public function __destruct() {
        if ($this->statement) {
            $this->freeResult();
        }
    }

    /**
     * Возвращает количество строк в наборе результатов для выполненного SQL, заданного курсором.
     *
     * @return  integer  Количество строк в наборе результатов.
     *
     * @see     Countable::count()
     */
    #[\ReturnTypeWillChange]
    public function count(): int {
        if ($this->statement) {
            return $this->statement->rowCount();
        }

        return 0;
    }

    /**
     * Текущий элемент в итераторе.
     *
     * @return  mixed
     *
     * @see     Iterator::current()
     */
    #[\ReturnTypeWillChange]
    public function current(): mixed {
        return $this->current;
    }

    /**
     * Ключ текущего элемента в итераторе.
     *
     * @return  string|int|bool|float
     *
     * @see     Iterator::key()
     */
    #[\ReturnTypeWillChange]
    public function key(): string|int|bool|float {
        return $this->key;
    }

    /**
     * Переходит к следующему результату SQL-запроса.
     *
     * @return  void
     *
     * @see     Iterator::next()
     */
    #[\ReturnTypeWillChange]
    public function next(): void {
        $this->key = $this->fetched;
        $this->current = $this->fetchObject();

        if ($this->current) {
            if ($this->column && isset($this->current->{$this->column})) {
                $this->key = $this->current->{$this->column};
            }

            $this->fetched++;
        }
    }

    /**
     * Перематывает итератор.
     *
     * Этот итератор не может быть перемотан.
     *
     * @return  void
     *
     * @see     Iterator::rewind()
     */
    #[\ReturnTypeWillChange]
    public function rewind() {}

    /**
     * Проверяет, действительна ли текущая позиция итератора.
     *
     * @return  boolean
     *
     * @see     Iterator::valid()
     */
    #[\ReturnTypeWillChange]
    public function valid(): bool {
        return (bool) $this->current;
    }

    /**
     * Метод для извлечения строки из курсора набора результатов как объекта.
     *
     * @return  mixed  Либо следующая строка из набора результатов, либо false, если строк больше нет.
     *
     */
    protected function fetchObject(): mixed {
        if ($this->statement) {
            return $this->statement->fetch();
        }

        return false;
    }

    /**
     * Метод освобождения памяти, используемой для набора результатов.
     *
     * @return  void
     *
     */
    protected function freeResult(): void {
        $this->statement?->closeCursor();
    }
}
