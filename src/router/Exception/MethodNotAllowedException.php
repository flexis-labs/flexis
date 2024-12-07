<?php

/**
 * Часть пакета Flexis Router Framework.
 */

namespace Flexis\Router\Exception;

use Exception;

/**
 * Исключение, определяющее метод, не допускает ошибку.
 */
class MethodNotAllowedException extends \RuntimeException {
    /**
     * Разрешенные методы для данного маршрута
     *
     * @var    string[]
     */
    protected array $allowedMethods = [];

    /**
     * Конструктор.
     *
     * @param   array        $allowedMethods  Разрешенные методы для маршрута.
     * @param   string|null  $message         Сообщение об исключении, которое нужно выдать.
     * @param   integer      $code            Код исключения.
     * @param   ?Exception  $previous        Предыдущий объект throw, используемый для цепочки исключений.
     */
    public function __construct(
        array $allowedMethods,
        ?string $message = null,
        int $code = 405,
        ?Exception $previous = null
    ) {

        $this->allowedMethods = array_map('strtoupper', $allowedMethods);

        parent::__construct($message, $code, $previous);
    }

    /**
     * Возвращает разрешенные методы HTTP.
     *
     * @return  array
     *
     */
    public function getAllowedMethods(): array {
        return $this->allowedMethods;
    }
}
