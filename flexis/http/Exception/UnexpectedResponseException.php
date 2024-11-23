<?php

/**
 * Часть пакета Flexis Http Framework.
 */

namespace Flexis\Http\Exception;

use Exception;
use Laminas\Diactoros\Response;
use Psr\Http\Client\ClientExceptionInterface;

/**
 * Исключение, представляющее неожиданный ответ.
 */
class UnexpectedResponseException extends \DomainException implements ClientExceptionInterface {
    /**
     * Объект Ответ.
     *
     * @var    Response
     */
    private Response $response;

    /**
     * Конструктор.
     *
     * @param   Response        $response  Объект Ответ.
     * @param   string          $message   Сообщение об исключении, которое нужно выдать.
     * @param   integer         $code      Код исключения.
     * @param   Exception|null  $previous  Предыдущее исключение использовалось для цепочки исключений.
     */
    public function __construct(Response $response, string $message = '', int $code = 0, ?Exception $previous = null) {
        parent::__construct($message, $code, $previous);

        $this->response = $response;
    }

    /**
     * Возвращает объект Response.
     *
     * @return  Response
     */
    public function getResponse(): Response {
        return $this->response;
    }
}
