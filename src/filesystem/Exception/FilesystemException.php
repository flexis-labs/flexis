<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem\Exception;

use Flexis\Filesystem\Path;

/**
 * Класс исключений для обработки ошибок в пакете файловой системы.
 */
class FilesystemException extends \RuntimeException {
    /**
     * Конструктор.
     *
     * @param   string           $message   Сообщение.
     * @param   integer          $code      Код.
     * @param   \Throwable|null  $previous  Предыдущее исключение.
     */
    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null) {
        parent::__construct(
            Path::removeRoot($message),
            $code,
            $previous
        );
    }
}
