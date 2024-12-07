<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Event;

use Flexis\Console\Application;
use Flexis\Console\Command\AbstractCommand;
use Flexis\Console\ConsoleEvents;
use Throwable;

/**
 * Событие срабатывает, когда приложение получает неперехваченный объект Throwable.
 */
class ApplicationErrorEvent extends ConsoleEvent {
    /**
     * Объект Throwable с данными об ошибке.
     *
     * @var    Throwable
     */
    private Throwable $error;

    /**
     * Код выхода, который будет использоваться в приложении.
     *
     * @var    integer|null
     */
    private ?int $exitCode = null;

    /**
     * Конструктор событий.
     *
     * @param   Throwable             $error        Объект Throwable с данными об ошибке.
     * @param   Application           $application  Активное приложение.
     * @param   AbstractCommand|null  $command      Выполняемая команда.
     */
    public function __construct(Throwable $error, Application $application, ?AbstractCommand $command = null) {
        parent::__construct(ConsoleEvents::APPLICATION_ERROR, $application, $command);

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
     * Возвращает код выхода.
     *
     * @return  integer
     */
    public function getExitCode(): int {
        return $this->exitCode ?: (\is_int($this->error->getCode()) && $this->error->getCode() !== 0 ? $this->error->getCode() : 1);
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

    /**
     * Устанавливает код выхода.
     *
     * @param integer $exitCode Код завершения команды.
     *
     * @return  void
     *
     * @throws \ReflectionException
     */
    public function setExitCode(int $exitCode): void {
        $this->exitCode = $exitCode;

        $r = new \ReflectionProperty($this->error, 'code');
        $r->setAccessible(true);
        $r->setValue($this->error, $this->exitCode);
    }
}
