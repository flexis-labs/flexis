<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Exception;

use Exception;
use RuntimeException;

/**
 * Класс исключения, определяющий ошибку подключения к платформе базы данных
 */
class ConnectionFailureException extends RuntimeException {
    /**
     * Создаёт исключение
     *
     * @param   string      $message   Сообщение об исключении, которое нужно выдать. [необязательный]
     * @param   integer     $code      Код исключения. [необязательный]
     * @param   ?Exception  $previous  Предыдущее исключение использовалось для цепочки исключений. [необязательный]
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null) {
        // PDO uses strings for exception codes, PHP forces numeric codes, so "force" the string code to be used
        parent::__construct($message, 0, $previous);

        $this->code = $code;
    }
}
