<?php

/**
 * Часть пакета Flexis Uri Framework.
 */

namespace Flexis\Uri;

/**
 * Базовый класс Flexis Uri.
 */
abstract class AbstractUri implements UriInterface {
    /**
     * Исходный URI.
     *
     * @var    string|null
     */
    protected ?string $uri = null;

    /**
     * Протокол.
     *
     * @var    string|null
     */
    protected ?string $scheme = null;

    /**
     * Хост.
     *
     * @var    string|null
     */
    protected ?string $host = null;

    /**
     * Порт.
     *
     * @var    integer|null
     */
    protected ?int $port = null;

    /**
     * Логин пользователя.
     *
     * @var    string|null
     */
    protected ?string $user = null;

    /**
     * Пароль.
     *
     * @var    string|null
     */
    protected ?string $pass = null;

    /**
     * Путь.
     *
     * @var    string|null
     */
    protected ?string $path = null;

    /**
     * Запрос.
     *
     * @var    string|null
     */
    protected ?string $query = null;

    /**
     * Фрагмент.
     *
     * @var    string
     */
    protected string $fragment;

    /**
     * Хеш переменной.
     *
     * @var    array
     */
    protected array $vars = [];

    /**
     * Конструктор.
     *
     * Вы можете передать строку URI конструктору, чтобы инициализировать определенный URI.
     *
     * @param   string|null  $uri  Необязательная строка URI.
     */
    public function __construct(?string $uri = null) {
        if ($uri !== null) {
            $this->parse($uri);
        }
    }

    /**
     * Магический метод для получения строкового представления объекта UriInterface.
     *
     * @return  string
     */
    public function __toString() {
        return $this->toString();
    }

    /**
     * Возвращает полную строку URI.
     *
     * @param   array  $parts  Массив строк, определяющий детали для рендеринга.
     *
     * @return  string  Строка URI.
     */
    public function toString(array $parts = ['scheme', 'user', 'pass', 'host', 'port', 'path', 'query', 'fragment']): string {
        $bitmask = 0;

        foreach ($parts as $part) {
            $const = 'static::' . strtoupper($part);

            if (\defined($const)) {
                $bitmask |= \constant($const);
            }
        }

        return $this->render($bitmask);
    }

    /**
     * Возвращает полную строку URI.
     *
     * @param   integer  $parts  Битовая маска, определяющая детали для рендеринга.
     *
     * @return  string  Строка URI.
     */
    public function render(int $parts = self::ALL): string {
        $query = $this->getQuery();

        $uri = $parts & static::SCHEME ? (!empty($this->scheme) ? $this->scheme . '://' : '') : '';
        $uri .= $parts & static::USER ? $this->user : '';
        $uri .= $parts & static::PASS ? (!empty($this->pass) ? ':' : '') . $this->pass . (!empty($this->user) ? '@' : '') : '';
        $uri .= $parts & static::HOST ? $this->host : '';
        $uri .= $parts & static::PORT ? (!empty($this->port) ? ':' : '') . $this->port : '';
        $uri .= $parts & static::PATH ? $this->path : '';
        $uri .= $parts & static::QUERY ? (!empty($query) ? '?' . $query : '') : '';
        $uri .= $parts & static::FRAGMENT ? (!empty($this->fragment) ? '#' . $this->fragment : '') : '';

        return $uri;
    }

    /**
     * Проверяет, существует ли переменная.
     *
     * @param   string  $name  Имя переменной запроса для проверки.
     *
     * @return  boolean  True, если переменная существует.
     */
    public function hasVar(string $name): bool {
        return array_key_exists($name, $this->vars);
    }

    /**
     * Возвращает переменную запроса по имени.
     *
     * @param   string       $name     Имя переменной запроса, которую необходимо получить.
     * @param   string|null  $default  Значение по умолчанию, которое возвращается, если переменная не установлена.
     *
     * @return  mixed   Запрошенная переменная запроса, если она присутствует, иначе — значение по умолчанию.
     */
    public function getVar(string $name, ?string $default = null): mixed {
        if (array_key_exists($name, $this->vars)) {
            return $this->vars[$name];
        }

        return $default;
    }

    /**
     * Возвращает плоскую строку запроса.
     *
     * @param   boolean  $toArray  True, чтобы вернуть запрос в виде массива пар [ключ => значение].
     *
     * @return  string|array|null   Строка запроса или массив частей в строке запроса в зависимости от параметра функции.
     */
    public function getQuery(bool $toArray = false): string|array|null {
        if ($toArray) {
            return $this->vars;
        }

        if ($this->query === null) {
            $this->query = static::buildQuery($this->vars);
        }

        return $this->query;
    }

    /**
     * Возвращает схему URI (протокол)
     *
     * @return  string|null  Схема URI.
     */
    public function getScheme(): ?string {
        return $this->scheme;
    }

    /**
     * Возвращает логин пользователя URI
     *
     * @return  string|null  Логин пользователя или значение NULL, если логин пользователя не указан.
     */
    public function getUser(): ?string {
        return $this->user;
    }

    /**
     * Возвращает пароль URI
     *
     * @return  string|null  Пароль или значение NULL, если пароль не указан.
     */
    public function getPass(): ?string {
        return $this->pass;
    }

    /**
     * Возвращает хост URI
     *
     * @return  string|null  Имя хоста/IP-адрес или значение NULL, если имя хоста/IP-адрес не были указаны.
     */
    public function getHost(): ?string {
        return $this->host;
    }

    /**
     * Возвращает порт URI
     *
     * @return  integer|null  Номер порта или значение NULL, если порт не указан.
     */
    public function getPort(): ?int {
        return $this->port;
    }

    /**
     * Возвращает строку пути URI.
     *
     * @return  string|null Строка пути URI.
     */
    public function getPath(): ?string {
        return $this->path;
    }

    /**
     * Возвращает строку привязки URI
     *
     * @return  string|null  Строка привязки URI.
     */
    public function getFragment(): ?string {
        return $this->fragment;
    }

    /**
     * Проверяет, использует ли текущий URI HTTPS.
     *
     * @return  boolean  True при использовании SSL через HTTPS.
     */
    public function isSsl(): bool {
        return strtolower($this->getScheme()) === 'https';
    }

    /**
     * Создаёт запрос из массива (обратно PHP parse_str()).
     *
     * @param   array  $params  Массив пар [ключ => значение], возвращаемый в виде строки запроса.
     *
     * @return  string  Результирующая строка запроса.
     *
     * @see     parse_str()
     */
    protected static function buildQuery(array $params): string {
        return urldecode(http_build_query($params, '', '&'));
    }

    /**
     * Парсит заданный URI и заполняет свойства класса.
     *
     * @param   string  $uri  Строка URI для анализа.
     *
     * @return  boolean
     */
    protected function parse(string $uri): bool {
        $this->uri = $uri;

        $parts = UriHelper::parse_url($uri);

        if ($parts === false) {
            throw new \RuntimeException(sprintf('Не удалось проанализировать запрошенный URI %s.', $uri));
        }

        $retval = (bool) $parts;

        if (isset($parts['query']) && str_contains($parts['query'], '&amp;')) {
            $parts['query'] = str_replace('&amp;', '&', $parts['query']);
        }

        foreach ($parts as $key => $value) {
            $this->$key = $value;
        }

        if (isset($parts['query'])) {
            parse_str($parts['query'], $this->vars);
        }

        return $retval;
    }

    /**
     * Очищает //, ../и ./из пути и возвращает результат.
     *
     * Например:
     * /foo/bar/../boo.php  => /foo/boo.php
     * /foo/bar/../../boo.php => /boo.php
     * /foo/bar/.././/boo.php => /foo/boo.php
     *
     * @param   string  $path  Путь URI для очистки.
     *
     * @return  string  Очищен и разрешен путь URI.
     */
    protected function cleanPath(string $path): string {
        $path = explode('/', preg_replace('#(/+)#', '/', $path));

        for ($i = 0, $n = \count($path); $i < $n; $i++) {
            if ($path[$i] == '.' || $path[$i] == '..') {
                if (($path[$i] == '.') || ($path[$i] == '..' && $i == 1 && $path[0] == '')) {
                    unset($path[$i]);
                    $path = array_values($path);
                    $i--;
                    $n--;
                } elseif ($path[$i] == '..' && ($i > 1 || ($i == 1 && $path[0] != ''))) {
                    unset($path[$i], $path[$i - 1]);

                    $path = array_values($path);
                    $i -= 2;
                    $n -= 2;
                }
            }
        }

        return implode('/', $path);
    }
}
