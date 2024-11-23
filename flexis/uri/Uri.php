<?php

/**
 * Часть пакета Flexis Uri Framework.
 */

namespace Flexis\Uri;

/**
 * Uri Класс.
 *
 * Класс анализирует URI и предоставляет Flexis Framework общий интерфейс для доступа к URI и управления им.
 */
class Uri extends AbstractUri {
    /**
     * Добавляет переменную запроса и значение, заменяя значение, если оно уже существует, и возвращая старое значение.
     *
     * @param   string  $name   Имя переменной запроса, которую необходимо установить.
     * @param   string  $value  Значение переменной запроса.
     *
     * @return  string|null  Предыдущее значение переменной запроса.
     */
    public function setVar(string $name, string $value): ?string {
        $tmp = $this->vars[$name] ?? null;

        $this->vars[$name] = $value;

        $this->query = null;

        return $tmp;
    }

    /**
     * Удаляет элемент из переменных строки запроса, если он существует.
     *
     * @param   string  $name  Имя переменной, которую нужно удалить.
     *
     * @return  void
     */
    public function delVar(string $name): void {
        if (array_key_exists($name, $this->vars)) {
            unset($this->vars[$name]);

            $this->query = null;
        }
    }

    /**
     * Устанавливает запрос в предоставленную строку в формате foo=bar&x=y.
     *
     * @param   array|string  $query  Строка или массив запроса.
     *
     * @return  void
     */
    public function setQuery(array|string $query): void {
        if (\is_array($query)) {
            $this->vars = $query;
        } else {
            if (str_contains($query, '&amp;')) {
                $query = str_replace('&amp;', '&', $query);
            }

            parse_str($query, $this->vars);
        }

        $this->query = null;
    }

    /**
     * Устанавливает схему URI (протокол).
     *
     * @param   string  $scheme  Схема URI.
     *
     * @return  Uri  Этот метод поддерживает цепочку.
     */
    public function setScheme(string $scheme): self {
        $this->scheme = $scheme;

        return $this;
    }

    /**
     * Устанавливает логин пользователя URI.
     *
     * @param   string  $user  Логин пользователя URI.
     *
     * @return  Uri  Этот метод поддерживает цепочку.
     */
    public function setUser(string $user): self {
        $this->user = $user;

        return $this;
    }

    /**
     * Устанавливает пароль URI.
     *
     * @param   string  $pass  Пароль URI.
     *
     * @return  Uri  Этот метод поддерживает цепочку.
     */
    public function setPass(string $pass): self {
        $this->pass = $pass;

        return $this;
    }

    /**
     * Устанавливает хост URI.
     *
     * @param   string  $host  Хост URI.
     *
     * @return  Uri  Этот метод поддерживает цепочку.
     */
    public function setHost(string $host): self {
        $this->host = $host;

        return $this;
    }

    /**
     * Устанавливает порт URI.
     *
     * @param   integer  $port  Номер порта URI.
     *
     * @return  Uri  Этот метод поддерживает цепочку.
     */
    public function setPort(int $port): self {
        $this->port = $port;

        return $this;
    }

    /**
     * Устанавливает строку пути URI.
     *
     * @param   string  $path  Строка пути URI.
     *
     * @return  Uri  Этот метод поддерживает цепочку.
     */
    public function setPath(string $path): self {
        $this->path = $this->cleanPath($path);

        return $this;
    }

    /**
     * Устанавливает строку привязки URI.
     *
     * @param   string  $anchor  Строка привязки URI.
     *
     * @return  Uri  Этот метод поддерживает цепочку.
     */
    public function setFragment(string $anchor): self {
        $this->fragment = $anchor;

        return $this;
    }
}
