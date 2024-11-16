<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Monitor;

use Flexis\Database\QueryMonitorInterface;

/**
 * Монитор запросов, обрабатывающий журналирование запросов.
 */
final class DebugMonitor implements QueryMonitorInterface {
    /**
     * Журнал выполненных стеков вызовов операторов SQL драйвером базы данных.
     *
     * @var    array
     */
    private array $callStacks = [];

    /**
     * Журнал выполнения операторов SQL драйвером базы данных.
     *
     * @var    array
     */
    private array $logs = [];

    /**
     * Список связанных параметров, используемых в запросе.
     *
     * @var    array
     */
    private array $boundParams = [];

    /**
     * Журнал использования памяти выполненными операторами SQL (запуск и остановка Memory_get_usage) драйвером базы данных.
     *
     * @var    array
     */
    private array $memoryLogs = [];

    /**
     * Журнал выполнения операторов SQL (микровремя запуска и остановки) драйвером базы данных.
     *
     * @var    array
     */
    private array $timings = [];

    /**
     * Действуйте в соответствии с запущенным запросом.
     *
     * @param   string         $sql           SQL, который будет выполнен.
     * @param   object[]|null  $boundParams   Список связанных параметров, используемых в запросе.
     *                                        Каждый элемент представляет собой объект, который содержит: значение, тип данных.
     *
     * @return  void
     */
    public function startQuery(string $sql, ?array $boundParams = null): void {
        $this->logs[]        = $sql;
        // Разыменование связанных параметров, чтобы предотвратить сообщение о неправильном значении при повторном использовании одного и того же объекта запроса.
        $this->boundParams[] = unserialize(serialize($boundParams));

        $this->callStacks[]  = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $this->memoryLogs[]  = memory_get_usage();
        $this->timings[]     = microtime(true);
    }

    /**
     * Действуйте при остановленном запросе.
     *
     * @return  void
     */
    public function stopQuery(): void {
        $this->timings[]    = microtime(true);
        $this->memoryLogs[] = memory_get_usage();
    }

    /**
     * Возвращает зарегистрированные стеки вызовов.
     *
     * @return  array
     */
    public function getCallStacks(): array {
        return $this->callStacks;
    }

    /**
     * Возвращает зарегистрированные запросы.
     *
     * @return  array
     */
    public function getLogs(): array {
        return $this->logs;
    }

    /**
     * Возвращает зарегистрированные связанные параметры.
     *
     * @return  array
     */
    public function getBoundParams(): array {
        return $this->boundParams;
    }

    /**
     * Возвращает зарегистрированные журналы памяти.
     *
     * @return  array
     */
    public function getMemoryLogs(): array {
        return $this->memoryLogs;
    }

    /**
     * Возвращает зарегистрированные тайминги.
     *
     * @return  array
     */
    public function getTimings(): array {
        return $this->timings;
    }
}
