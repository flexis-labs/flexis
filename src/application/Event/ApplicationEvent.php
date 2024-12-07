<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application\Event;

use Flexis\Application\AbstractApplication;
use Flexis\Event\Event;

/**
 * Базовый класс событий для событий приложения.
 */
class ApplicationEvent extends Event {
    /**
     * Активное приложение.
     *
     * @var    AbstractApplication
     */
    private AbstractApplication $application;

    /**
     * Конструктор событий.
     *
     * @param   string               $name         Название события.
     * @param   AbstractApplication  $application  Активное приложение.
     */
    public function __construct(string $name, AbstractApplication $application) {
        parent::__construct($name);

        $this->application = $application;
    }

    /**
     * Возвращает активное приложение.
     *
     * @return  AbstractApplication
     */
    public function getApplication(): AbstractApplication {
        return $this->application;
    }
}
