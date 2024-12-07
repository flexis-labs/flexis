<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Event;

use Flexis\Console\Application;
use Flexis\Console\Command\AbstractCommand;
use Flexis\Console\ConsoleEvents;

/**
 * Событие срабатывает непосредственно перед завершением процесса.
 */
class TerminateEvent extends ConsoleEvent {
    /**
     * Код выхода, который будет использоваться в приложении.
     *
     * @var    integer
     */
    private int $exitCode;

    /**
     * Конструктор событий.
     *
     * @param   integer               $exitCode     Объект Throwable с данными об ошибке.
     * @param   Application           $application  Активное приложение.
     * @param   AbstractCommand|null  $command      Выполняемая команда.
     */
    public function __construct(int $exitCode, Application $application, ?AbstractCommand $command = null) {
        parent::__construct(ConsoleEvents::TERMINATE, $application, $command);

        $this->exitCode = $exitCode;
    }

    /**
     * Возвращает код выхода.
     *
     * @return  integer
     */
    public function getExitCode(): int {
        return $this->exitCode;
    }

    /**
     * Устанавливает код выхода.
     *
     * @param   integer  $exitCode  Код завершения команды.
     *
     * @return  void
     */
    public function setExitCode(int $exitCode): void {
        $this->exitCode = $exitCode;
    }
}
