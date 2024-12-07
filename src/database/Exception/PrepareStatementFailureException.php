<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Exception;

use Exception;
use RuntimeException;

/**
 * Класс исключения, определяющий ошибку при подготовке оператора SQL к выполнению.
 */
class PrepareStatementFailureException extends RuntimeException {
    /**
     * Создаёт исключение
     *
     * @param   string       $message   Сообщение об исключении, которое нужно выдать. [необязательный]
     * @param   integer      $code      Код исключения. [необязательный]
     * @param   ?Exception   $previous  Предыдущее исключение использовалось для цепочки исключений. [необязательный]
     */
    public function __construct(string $message = '', int $code = 0, ?Exception $previous = null) {
        // PDO использует строки для кодов исключений, PHP требует числовых кодов, поэтому «принудительно» используем строковый код.
        parent::__construct($message, 0, $previous);

        $this->code = $code;
    }
}
