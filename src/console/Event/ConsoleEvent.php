<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Event;

use Flexis\Console\Application;
use Flexis\Console\Command\AbstractCommand;
use Flexis\Event\Event;

/**
 * Базовый класс событий для событий консоли.
 */
class ConsoleEvent extends Event {
    /**
     * Активное приложение.
     *
     * @var    Application
     */
    private Application $application;

    /**
     * Выполняемая команда.
     *
     * @var    AbstractCommand|null
     */
    private ?AbstractCommand $command = null;

    /**
     * Конструктор.
     *
     * @param   string                $name         Название события.
     * @param   Application           $application  Активное приложение.
     * @param   AbstractCommand|null  $command      Выполняемая команда.
     */
    public function __construct(string $name, Application $application, ?AbstractCommand $command = null) {
        parent::__construct($name);

        $this->application = $application;
        $this->command     = $command;
    }

    /**
     * Возвращает активное приложение.
     *
     * @return  Application
     */
    public function getApplication(): Application {
        return $this->application;
    }

    /**
     * Возвращает выполняемую команду.
     *
     * @return  AbstractCommand|null
     */
    public function getCommand(): ?AbstractCommand {
        return $this->command;
    }
}
