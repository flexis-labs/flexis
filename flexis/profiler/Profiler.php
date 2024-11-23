<?php

/**
 * Часть пакета Flexis Profiler Framework.
 */

namespace Flexis\Profiler;

/**
 * Служебный класс, помогающий в процессе сравнительного анализа выполнения разделов кода,
 * чтобы понять, на что тратится время.
 */
class Profiler {
    /**
     * @var    integer  Время начала.
     */
    protected int $start = 0;

    /**
     * @var    string  Префикс для использования в выводе.
     */
    protected string $prefix = '';

    /**
     * @var    array|null  Буфер профилирующих сообщений.
     */
    protected ?array $buffer = null;

    /**
     * @var    array|null  Профилирующие сообщения.
     */
    protected ?array $marks = null;

    /**
     * @var    float  Предыдущий маркер времени.
     */
    protected float $previousTime = 0.0;

    /**
     * @var    float  Предыдущий маркер памяти.
     */
    protected float $previousMem = 0.0;

    /**
     * @var    array  Контейнер экземпляров профилировщика.
     */
    protected static array $instances = [];

    /**
     * Конструктор.
     *
     * @param   string  $prefix  Префикс для сообщений о пометке.
     */
    public function __construct(string $prefix = '') {
        $this->start  = microtime(1);
        $this->prefix = $prefix;
        $this->marks  = [];
        $this->buffer = [];
    }

    /**
     * Возвращает глобальный объект `Profiler`, создавая его, только если он еще не существует.
     *
     * @param   string  $prefix  Префикс, используемый для различения объектов профилировщика.
     *
     * @return  Profiler  Объект профайлера.
     */
    public static function getInstance(string $prefix = ''): self {
        if (empty(self::$instances[$prefix])) {
            self::$instances[$prefix] = new static($prefix);
        }

        return self::$instances[$prefix];
    }

    /**
     * Вывод отметки времени.
     *
     * @param   string  $label  Метка для отметки времени.
     *
     * @return  string
     */
    public function mark(string $label): string {
        $current    = microtime(1) - $this->start;
        $currentMem = memory_get_usage() / 1048576;

        $m = (object) [
            'prefix'      => $this->prefix,
            'time'        => ($current - $this->previousTime) * 1000,
            'totalTime'   => ($current * 1000),
            'memory'      => $currentMem - $this->previousMem,
            'totalMemory' => $currentMem,
            'label'       => $label,
        ];
        $this->marks[] = $m;

        $mark = sprintf(
            '%s %.3f seconds (%.3f); %0.2f MB (%0.3f) - %s',
            $m->prefix,
            $m->totalTime / 1000,
            $m->time / 1000,
            $m->totalMemory,
            $m->memory,
            $m->label
        );
        $this->buffer[] = $mark;

        $this->previousTime = $current;
        $this->previousMem  = $currentMem;

        return $mark;
    }

    /**
     * Получите все оценки профайлера.
     *
     * Возвращает массив всех меток, созданных с момента создания экземпляра объекта Profiler.
     * Метки являются объектами согласно {@see Profiler::mark}.
     *
     * @return  array  Массив меток профилировщика.
     */
    public function getMarks(): array {
        return $this->marks;
    }

    /**
     * Получите все буферы меток профилировщика.
     *
     * Возвращает массив всех буферов меток, созданных с момента создания экземпляра объекта Profiler.
     * Метки представляют собой строки согласно {@see Profiler::mark}.
     *
     * @return  array  Массив буфера профилировщика.
     */
    public function getBuffer(): array {
        return $this->buffer;
    }

    /**
     * Устанавливает время начала.
     *
     * @param   float     $startTime  Временная метка Unix в микросекундах для установки времени запуска профилировщика.
     * @param   integer   $startMem   Объем памяти в байтах для настройки стартовой памяти профилировщика.
     *
     * @return  $this   Для цепочки.
     */
    public function setStart(float $startTime = 0.0, int $startMem = 0): self {
        $this->start       = $startTime;
        $this->previousMem = $startMem / 1048576;

        return $this;
    }
}
