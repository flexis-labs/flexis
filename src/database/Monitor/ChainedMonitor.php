<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Monitor;

use Flexis\Database\QueryMonitorInterface;

/**
 * Монитор цепных запросов, позволяющий выполнять несколько мониторов.
 */
class ChainedMonitor implements QueryMonitorInterface {
    /**
     * Мониторы запросов, хранящиеся в этой цепочке
     *
     * @var    QueryMonitorInterface[]
     */
    private array $monitors = [];

    /**
     * Зарегистрируйте монитор в цепочке.
     *
     * @param   QueryMonitorInterface  $monitor  Монитор, который нужно добавить.
     *
     * @return  void
     */
    public function addMonitor(QueryMonitorInterface $monitor): void {
        $this->monitors[] = $monitor;
    }

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
        foreach ($this->monitors as $monitor) {
            $monitor->startQuery($sql, $boundParams);
        }
    }

    /**
     * Действуйте при остановленном запросе.
     *
     * @return  void
     */
    public function stopQuery(): void {
        foreach ($this->monitors as $monitor) {
            $monitor->stopQuery();
        }
    }
}
