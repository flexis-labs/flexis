<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Monitor;

use Flexis\Database\QueryMonitorInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;

/**
 * Монитор запросов, обрабатывающий журналирование запросов.
 */
class LoggingMonitor implements QueryMonitorInterface, LoggerAwareInterface {
    use LoggerAwareTrait;

    /**
     * Действуйте в соответствии с запущенным запросом.
     *
     * @param   string         $sql          SQL, который будет выполнен.
     * @param   object[]|null  $boundParams  Список связанных параметров, используемых в запросе.
     *                                       Каждый элемент представляет собой объект, который содержит: значение, тип данных.
     * @return  void
     */
    public function startQuery(string $sql, ?array $boundParams = null): void {
        if ($this->logger) {
            // Добавляем запрос в очередь объектов.
            $this->logger->info(
                'Query Executed: {sql}',
                ['sql' => $sql, 'trace' => debug_backtrace()]
            );
        }
    }

    /**
     * Действуйте при остановленном запросе.
     *
     * @return  void
     */
    public function stopQuery(): void {
        // Ни чего не делаем
    }
}
