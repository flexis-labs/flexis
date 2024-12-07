<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

/**
 * Класс, определяющий типы параметров для операторов.
 */
final class ParameterType {
    /**
     * Определяет логический параметр.
     *
     * @var    string
     */
    public const string BOOLEAN = 'boolean';

    /**
     * Определяет целочисленный параметр.
     *
     * @var    string
     */
    public const string INTEGER = 'int';

    /**
     * Определяет параметр большого объекта.
     *
     * @var    string
     */
    public const string LARGE_OBJECT = 'lob';

    /**
     * Определяет пустой параметр.
     *
     * @var    string
     */
    public const string NULL = 'null';

    /**
     * Определяет строковый параметр.
     *
     * @var    string
     */
    public const string STRING = 'string';

    /**
     * Приватный конструктор для предотвращения создания экземпляра этого класса.
     */
    private function __construct() {}
}
