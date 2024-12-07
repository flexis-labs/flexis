<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application\Event;

use Flexis\Application\AbstractApplication;
use Flexis\Application\ApplicationEvents;
use Throwable;

/**
 * Класс события, вызываемый при возникновении ошибки приложения.
 */
class ApplicationErrorEvent extends ApplicationEvent {
    /**
     * Объект Throwable с данными об ошибке.
     *
     * @var    Throwable
     */
    private Throwable $error;

    /**
     * Конструктор событий.
     *
     * @param   Throwable            $error        Объект Throwable с данными об ошибке.
     * @param   AbstractApplication  $application  Активное приложение.
     */
    public function __construct(Throwable $error, AbstractApplication $application) {
        parent::__construct(ApplicationEvents::ERROR, $application);

        $this->error = $error;
    }

    /**
     * Возвращает объект ошибки.
     *
     * @return  Throwable
     */
    public function getError(): Throwable {
        return $this->error;
    }

    /**
     * Устанавливает объект ошибки.
     *
     * @param   Throwable  $error  Объект ошибки, который нужно установить в событии.
     *
     * @return  void
     */
    public function setError(Throwable $error): void {
        $this->error = $error;
    }
}
