<?php

/**
 * Часть пакета Flexis Uri Framework.
 */

namespace Flexis\Uri;

/**
 * Интерфейс Uri
 *
 * Интерфейс для доступа к URI только для чтения.
 */
interface UriInterface {
    /**
     * Включить схему (http, https, etc.)
     *
     * @var    integer
     */
    public const int SCHEME = 1;

    /**
     * Включить пользователя
     *
     * @var    integer
     */
    public const int USER = 2;

    /**
     * Включить пароль
     *
     * @var    integer
     */
    public const int PASS = 4;

    /**
     * Включить хост
     *
     * @var    integer
     */
    public const int HOST = 8;

    /**
     * Включить порт
     *
     * @var    integer
     */
    public const int PORT = 16;

    /**
     * Включить путь
     *
     * @var    integer
     */
    public const int PATH = 32;

    /**
     * Включить строку запроса
     *
     * @var    integer
     */
    public const int QUERY = 64;

    /**
     * Включить фрагмент
     *
     * @var    integer
     */
    public const int FRAGMENT = 128;

    /**
     * Включить все доступные части URL (scheme, user, pass, host, port, path, query, fragment)
     *
     * @var    integer
     */
    public const int ALL = 255;

    /**
     * Магический метод для получения строкового представления объекта URI.
     *
     * @return  string
     */
    public function __toString();

    /**
     * Возвращает полную строку URI.
     *
     * @param   array  $parts  Массив строк, определяющий детали для рендеринга.
     *
     * @return  string  Строка URI.
     */
    public function toString(array $parts = ['scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment']): string;

    /**
     * Проверяет, существует ли переменная.
     *
     * @param   string  $name  Имя переменной запроса для проверки.
     *
     * @return  boolean  True, если переменная существует.
     */
    public function hasVar(string $name): bool;

    /**
     * Возвращает переменную запроса по имени.
     *
     * @param   string       $name     Имя переменной запроса, которую необходимо получить.
     * @param   string|null  $default  Значение по умолчанию, которое возвращается, если переменная не установлена.
     *
     * @return  mixed  Запрошенная переменная запроса, если она присутствует, иначе — значение по умолчанию.
     */
    public function getVar(string $name, ?string $default = null): mixed;

    /**
     * Возвращает плоскую строку запроса.
     *
     * @param   boolean  $toArray  True, чтобы вернуть запрос в виде массива пар [ключ => значение].
     *
     * @return  array|string|null   Строка запроса (необязательно в виде массива).
     */
    public function getQuery(bool $toArray = false): array|string|null;

    /**
     * Возвращает схему URI (протокол)
     *
     * @return  string|null  Схема URI.
     */
    public function getScheme(): ?string;

    /**
     * Возвращает логин пользователя URI
     *
     * @return  string|null  Логин пользователя или значение NULL, если логин пользователя не указан.
     */
    public function getUser(): ?string;

    /**
     * Возвращает пароль URI
     *
     * @return  string|null  Пароль или значение NULL, если пароль не указан.
     */
    public function getPass(): ?string;

    /**
     * Возвращает хост URI
     *
     * @return  string|null  Имя хоста/IP-адрес или значение NULL, если имя хоста/IP-адрес не были указаны.
     */
    public function getHost(): ?string;

    /**
     * Возвращает порт URI
     *
     * @return  integer|null  Номер порта или значение NULL, если порт не указан.
     */
    public function getPort(): ?int;

    /**
     * Возвращает строку пути URI.
     *
     * @return  string|null  Строка пути URI.
     */
    public function getPath(): ?string;

    /**
     * Возвращает строку привязки URI
     *
     * @return  string|null  Строка привязки URI.
     */
    public function getFragment(): ?string;

    /**
     * Проверяет, использует ли текущий URI HTTPS.
     *
     * @return  boolean  Верно, если используется SSL через HTTPS.
     */
    public function isSsl(): bool;
}
