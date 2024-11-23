<?php

/**
 * Часть пакета Flexis Http Framework.
 */

namespace Flexis\Http;

use Flexis\Uri\UriInterface;
use Laminas\Diactoros\Response;

/**
 * Интерфейс транспортного класса HTTP.
 */
interface TransportInterface {
    /**
     * Отправляет запрос на сервер и возвращает объект Response с ответом.
     *
     * @param   string        $method     HTTP-метод отправки запроса.
     * @param   UriInterface  $uri        URI запрашиваемого ресурса.
     * @param   mixed         $data       Либо ассоциативный массив, либо строка, которая будет отправлена с запросом.
     * @param   array         $headers    Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null  $timeout    Чтение тайм-аута в секундах.
     * @param   string|null   $userAgent  Необязательная строка пользовательского агента, отправляемая вместе с запросом.
     *
     * @return  Response
     *
     */
    public function request(
        string $method,
        UriInterface $uri,
        mixed $data = null,
        array $headers = [],
        ?int $timeout = null,
        ?string $userAgent = null
    ): Response;

    /**
     * Метод проверки доступности транспортного уровня HTTP для использования.
     *
     * @return  boolean  True если доступно, иначе false.
     *
     */
    public static function isSupported(): bool;
}
