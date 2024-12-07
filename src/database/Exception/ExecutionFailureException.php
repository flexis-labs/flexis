<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Exception;

use Exception;
use RuntimeException;

/**
 * Класс исключений, определяющий ошибку при выполнении оператора
 */
class ExecutionFailureException extends RuntimeException {
    /**
     * Выполненный оператор SQL.
     *
     * @var    string
     */
    private string $query;

    /**
     * Создаёт исключение.
     *
     * @param   string       $query     Выполненный оператор SQL.
     * @param   string       $message   Сообщение об исключении, которое нужно выдать. [необязательный]
     * @param   integer      $code      Код исключения. [необязательный]
     * @param   ?Exception   $previous  Предыдущее исключение использовалось для цепочки исключений. [необязательный]
     *
     */
    public function __construct(string $query, string $message = '', int $code = 0, ?Exception $previous = null) {
        // PDO использует строки для кодов исключений, PHP требует числовых кодов, поэтому «принудительно» используем строковый код.
        parent::__construct($message, 0, $previous);

        $this->code  = $code;
        $this->query = $query;
    }

    /**
     * Возвращает оператор SQL, который был выполнен
     *
     * @return  string
     *
     */
    public function getQuery(): string {
        return $this->query;
    }
}
