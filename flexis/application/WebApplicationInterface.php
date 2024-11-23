<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application;

use Flexis\Input\Input;
use Psr\Http\Message\ResponseInterface;

/**
 * Субинтерфейс приложения, определяющий класс веб-приложения
 */
interface WebApplicationInterface extends ApplicationInterface {
    /**
     * Метод для получения объекта ввода приложения.
     *
     * @return  Input
     */
    public function getInput(): Input;

    /**
     * Перенаправление на другой URL.
     *
     * Если заголовки не были отправлены, перенаправление будет выполнено с помощью кода "301 Moved Permanently"
     * или "303 See Other" код в заголовке, указывающий на новое местоположение.
     * Если заголовки уже были отправлены, это будет выполнено с помощью инструкции JavaScript.
     *
     * @param   string   $url     URL-адрес для перенаправления. Может быть только URL-адрес http/https.
     * @param   integer  $status  Код состояния HTTP, который необходимо предоставить. По умолчанию предполагается 303.
     *
     * @return  void
     * @throws  \InvalidArgumentException
     */
    public function redirect(string $url, int $status = 303): void;

    /**
     * Установить/получить кэшируемое состояние для ответа.
     *
     * Если установлен $alloy, устанавливает кэшируемое состояние ответа. Всегда возвращает текущее состояние.
     *
     * @param   boolean|null  $allow  True, чтобы разрешить кеширование браузера.
     *
     * @return  boolean
     */
    public function allowCache(?bool $allow = null): bool;

    /**
     * Метод установки заголовка ответа.
     *
     * Если флаг замены установлен, то все заголовки с данным именем будут заменены новыми.
     * Заголовки хранятся во внутреннем массиве и отправляются при отправке сайта в браузер.
     *
     * @param   string   $name     Имя заголовка, который нужно установить.
     * @param   string   $value    Значение заголовка, который необходимо установить.
     * @param   boolean  $replace  True для замены любых заголовков с тем же именем.
     *
     * @return  $this
     */
    public function setHeader(string $name, string $value, bool $replace = false): self;

    /**
     * Метод для получения массива заголовков ответа, который будет отправлен при отправке ответа клиенту.
     *
     * @return  array
     */
    public function getHeaders(): array;

    /**
     * Метод очистки всех заданных заголовков ответа.
     *
     * @return  $this
     */
    public function clearHeaders(): self;

    /**
     * Отправляет заголовки ответов.
     *
     * @return  $this
     */
    public function sendHeaders(): self;

    /**
     * Устанавливает содержимое тела.
     * Если содержимое тела уже определено, это заменит его.
     *
     * @param   string  $content  Содержимое, которое необходимо установить в качестве тела ответа.
     *
     * @return  $this
     */
    public function setBody(string $content): self;

    /**
     * Добавление содержимого в начало тела.
     *
     * @param   string  $content  Содержимое, добавляемое к телу ответа.
     *
     * @return  $this
     */
    public function prependBody(string $content): self;

    /**
     * Добавляет содержимое в конец тела.
     *
     * @param   string  $content  Содержимое, добавляемое в тело ответа.
     *
     * @return  $this
     */
    public function appendBody(string $content): self;

    /**
     * Возвращает содержимое тела
     *
     * @return  mixed  Тело ответа в виде строки.
     */
    public function getBody(): mixed;

    /**
     * Возвращает объект ответа PSR-7.
     *
     * @return  ResponseInterface
     */
    public function getResponse(): ResponseInterface;

    /**
     * Проверяет, является ли значение действительным кодом состояния HTTP.
     *
     * @param   integer  $code  Потенциальный код состояния
     *
     * @return  boolean
     */
    public function isValidHttpStatus(int $code): bool;

    /**
     * Устанавливает объект ответа PSR-7.
     *
     * @param   ResponseInterface  $response  Объект ответа
     *
     * @return  void
     */
    public function setResponse(ResponseInterface $response): void;

    /**
     * Определяет, используем ли мы безопасное (SSL) соединение.
     *
     * @return  boolean  True если используется SSL, иначе — false.
     */
    public function isSslConnection(): bool;
}
