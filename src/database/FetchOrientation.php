<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

/**
 * Класс, определяющий ориентацию выборки для подготовленных операторов.
 *
 * Значения констант в этом классе соответствуют константам `PDO::FETCH_ORI_*`.
 */
final class FetchOrientation {
    /**
     * Задать следующую строку в наборе результатов.
     * Действительно только для прокручиваемых курсоров.
     *
     * @var    integer
     */
    public const int NEXT = 0;

    /**
     * Задать предыдущую строку в наборе результатов.
     * Действительно только для прокручиваемых курсоров.
     *
     * @var    integer
     */
    public const int PRIOR = 1;

    /**
     * Задать первую строку в наборе результатов.
     * Действительно только для прокручиваемых курсоров.
     *
     * @var    integer
     */
    public const int FIRST = 2;

    /**
     * Задать последнюю строку в наборе результатов.
     * Действительно только для прокручиваемых курсоров.
     *
     * @var    integer
     */
    public const int LAST = 3;

    /**
     * Задать запрошенную строку за номером строки из набора результатов.
     * Действительно только для прокручиваемых курсоров.
     *
     * @var    integer
     */
    public const int ABS = 4;

    /**
     * Задать запрошенную строку по относительной позиции от текущей позиции курсора в наборе результатов.
     * Действительно только для прокручиваемых курсоров.
     *
     * @var    integer
     */
    public const int REL = 5;

    /**
     * Приватный конструктор для предотвращения создания экземпляра этого класса.
     */
    private function __construct() {}
}