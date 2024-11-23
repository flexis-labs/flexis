<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Event;

use Flexis\Console\Application;
use Flexis\Console\Command\AbstractCommand;
use Flexis\Console\ConsoleEvents;

/**
 * Событие срабатывает до выполнения команды.
 */
class BeforeCommandExecuteEvent extends ConsoleEvent {
    /**
     * Код возврата для команды, отключенной этим событием.
     *
     * @var    integer
     */
    public const int RETURN_CODE_DISABLED = 113;

    /**
     * Флаг, указывающий, что команда включена
     *
     * @var    boolean
     */
    private bool $commandEnabled = true;

    /**
     * Конструктор событий.
     *
     * @param   Application           $application  Активное приложение.
     * @param   AbstractCommand|null  $command      Выполняемая команда.
     */
    public function __construct(Application $application, ?AbstractCommand $command = null) {
        parent::__construct(ConsoleEvents::BEFORE_COMMAND_EXECUTE, $application, $command);

        if ($command) {
            $this->commandEnabled = $command->isEnabled();
        }
    }

    /**
     * Отключение команду.
     *
     * @return  void
     */
    public function disableCommand(): void {
        $this->commandEnabled = false;
    }

    /**
     * Включение команды.
     *
     * @return  void
     */
    public function enableCommand(): void {
        $this->commandEnabled = false;
    }

    /**
     * Проверяет, включена ли команда.
     *
     * @return    boolean
     */
    public function isCommandEnabled(): bool {
        return $this->commandEnabled;
    }
}
