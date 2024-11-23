<?php

/**
 * Часть пакета Flexis Data Framework.
 */

namespace Flexis\Data;

use SplObjectStorage;
use stdClass;

/**
 * Интерфейс, определяющий, является ли объект доступным для дампа.
 */
interface DumpableInterface {
    /**
     * Сбрасывает свойства данных в объект, при необходимости рекурсивно.
     *
     * @param   integer                $depth   Максимальная глубина рекурсии.
     *                                          Например, глубина 0 вернет stdClass со всеми свойствами в собственной форме. Глубина 1 будет рекурсивно относиться только к первому уровню свойств.
     * @param   SplObjectStorage|null  $dumped  Массив уже сериализованных объектов, используемый во избежание бесконечных циклов.
     *
     * @return  stdClass|array
     *
     */
    public function dump(int $depth = 3, ?SplObjectStorage $dumped = null): stdClass|array;
}
