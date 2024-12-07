<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console;

/**
 * Класс, определяющий события, доступные в консольном приложении.
 */
final class ConsoleEvents {
    /**
     * Событие APPLICATION_ERROR — это событие, инициируемое, когда неперехваченный объект Throwable получен основным исполнителем приложения.
     *
     * Это событие позволяет разработчикам обрабатывать Throwable.
     *
     * @var    string
     */
    public const string APPLICATION_ERROR = 'console.application_error';

    /**
     * Событие BEFORE_COMMAND_EXECUTE — это событие, которое срабатывает перед выполнением команды.
     *
     * Это событие позволяет разработчикам изменять информацию о команде или ее зависимостях до ее выполнения.
     *
     * @var    string
     */
    public const string BEFORE_COMMAND_EXECUTE = 'console.before_command_execute';

    /**
     * Событие COMMAND_ERROR — это событие, инициируемое при получении неперехваченного объекта Throwable из команды.
     *
     * Это событие позволяет разработчикам обрабатывать Throwable.
     *
     * @var    string
     */
    public const string COMMAND_ERROR = 'console.command_error';

    /**
     * Событие TERMINATE — это событие, которое срабатывает непосредственно перед выходом из приложения.
     *
     * Это событие позволяет разработчикам выполнять любые действия после обработки и манипулировать кодом завершения процесса.
     *
     * @var    string
     */
    public const string TERMINATE = 'console.terminate';
}
