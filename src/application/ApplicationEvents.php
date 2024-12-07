<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application;

/**
 * Класс, определяющий события, доступные в приложении.
 */
final class ApplicationEvents {
    /**
     * Событие ERROR — это событие, которое срабатывает, когда Throwable не перехвачен.
     *
     * Это событие позволяет вам проверить Throwable и реализовать дополнительные механизмы обработки/отчетов об ошибках.
     *
     * @var    string
     */
    public const string ERROR = 'application.error';

    /**
     * Событие BEFORE_EXECUTE — это событие, которое срабатывает перед выполнением приложения.
     *
     * @var    string
     */
    public const string BEFORE_EXECUTE = 'application.before_execute';

    /**
     * Событие AFTER_EXECUTE — это событие, которое срабатывает после выполнения приложения.
     *
     * @var    string
     */
    public const string AFTER_EXECUTE = 'application.after_execute';

    /**
     * Событие BEFORE_RESPOND — это событие, которое срабатывает перед отправкой ответа приложения.
     *
     * @var    string
     */
    public const string BEFORE_RESPOND = 'application.before_respond';

    /**
     * Событие AFTER_RESPOND — это событие, которое срабатывает после отправки ответа приложения.
     *
     * @var    string
     */
    public const string AFTER_RESPOND = 'application.after_respond';
}
