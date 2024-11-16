<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

/**
 * Класс, определяющий режим выборки для подготовленных операторов
 *
 * Значения констант в этом классе соответствуют константам `PDO::FETCH_*`.
 */
final class FetchMode {
    /**
     * Указывает, что метод выборки должен возвращать каждую строку в виде массива, 
     * индексированного по имени столбца, возвращаемого в соответствующем наборе результатов.
     *
     * Если набор результатов содержит несколько столбцов с одинаковым именем, 
     * оператор возвращает только одно значение для каждого имени столбца.
     *
     * @var    integer
     * @see    \PDO::FETCH_ASSOC
     */
    public const int ASSOCIATIVE = 2;

    /**
     * Указывает, что метод выборки должен возвращать каждую строку в виде массива, 
     * индексированного по номеру столбца, возвращаемого в соответствующем наборе результатов, 
     * начиная со столбца 0.
     *
     * @var    integer
     * @see    \PDO::FETCH_NUM
     */
    public const int NUMERIC = 3;

    /**
     * Указывает, что метод выборки должен возвращать каждую строку в виде массива, 
     * индексированного как по имени столбца, так и по номеру, 
     * возвращаемому в соответствующем наборе результатов, 
     * начиная со столбца 0.
     *
     * @var    integer
     * @see    \PDO::FETCH_BOTH
     */
    public const int MIXED = 4;

    /**
     * Указывает, что метод выборки должен возвращать каждую строку как объект с именами свойств, 
     * соответствующими именам столбцов, возвращаемых в наборе результатов.
     *
     * @var    integer
     * @see    \PDO::FETCH_OBJ
     */
    public const int STANDARD_OBJECT = 5;

    /**
     * Указывает, что метод выборки должен возвращать только один запрошенный столбец из следующей строки в наборе результатов.
     *
     * @var    integer
     * @see    \PDO::FETCH_COLUMN
     */
    public const int COLUMN = 7;

    /**
     * Указывает, что метод выборки должен возвращать новый экземпляр запрошенного класса, 
     * сопоставляя столбцы с именованными свойствами в классе.
     *
     * @var    integer
     * @see    \PDO::FETCH_CLASS
     */
    public const int CUSTOM_OBJECT = 8;

    /**
     * Приватный конструктор для предотвращения создания экземпляра этого класса.
     */
    private function __construct() {}
}