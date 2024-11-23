<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Query;

/**
 * Класс элемента запроса.
 */
class QueryElement {
    /**
     * Имя элемента.
     *
     * @var    string|null
     */
    protected ?string $name = null;

    /**
     * Массив элементов.
     *
     * @var    string[]
     */
    protected array $elements = [];

    /**
     * Часть объединения.
     *
     * @var    string|null
     */
    protected ?string $glue = null;

    /**
     * Конструктор.
     *
     * @param string          $name      Имя элемента.
     * @param string|string[] $elements  Строка или массив.
     * @param string          $glue      Клей для элементов.
     *
     */
    public function __construct(string $name, array|string $elements, string $glue = ',') {
        $this->name = $name;
        $this->glue = $glue;

        $this->append($elements);
    }

    /**
     * Магический метод для преобразования элемента запроса в строку.
     *
     * @return  string
     *
     */
    public function __toString() {
        if (str_ends_with($this->name, '()')) {
            return \PHP_EOL . substr($this->name, 0, -2) . '(' . implode($this->glue, $this->elements) . ')';
        }

        return \PHP_EOL . $this->name . ' ' . implode($this->glue, $this->elements);
    }

    /**
     * Добавляет части элемента во внутренний список.
     *
     * @param string|string[] $elements  Строка или массив.
     *
     * @return  void
     *
     */
    public function append(array|string $elements): void {
        if (\is_array($elements)) {
            $this->elements = array_merge($this->elements, $elements);
        } else {
            $this->elements = array_merge($this->elements, [$elements]);
        }
    }

    /**
     * Возвращает элементы этого элемента.
     *
     * @return  string[]
     *
     */
    public function getElements(): array {
        return $this->elements;
    }

    /**
     * Возвращает клей этого элемента.
     *
     * @return  string|null  Приклеивание элемента.
     */
    public function getGlue(): ?string {
        return $this->glue;
    }

    /**
     * Возвращает имя этого элемента.
     *
     * @return  string|null  Имя элемента.
     */
    public function getName(): ?string {
        return $this->name;
    }

    /**
     * Устанавливает имя этого элемента.
     *
     * @param string $name  Имя элемента.
     *
     * @return  $this
     */
    public function setName(string $name): static {
        $this->name = $name;

        return $this;
    }

    /**
     * Метод обеспечения базовой поддержки копирования.
     *
     * Любой объект, помещенный в данные этого класса, должен иметь собственную реализацию __clone().
     * Этот метод не поддерживает копирование объектов в многомерный массив.
     *
     * @return  void
     *
     */
    public function __clone() {
        foreach ($this as $k => $v) {
            if (\is_object($v)) {
                $this->{$k} = clone $v;
            } elseif (\is_array($v)) {
                foreach ($v as $i => $element) {
                    if (\is_object($element)) {
                        $this->{$k}[$i] = clone $element;
                    }
                }
            }
        }
    }
}
