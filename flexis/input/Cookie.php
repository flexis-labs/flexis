<?php

/**
 * Часть пакета Flexis Input Framework.
 */

namespace Flexis\Input;

/**
 * Input класс cookie.
 */
class Cookie extends Input {
    /**
     * Конструктор.
     *
     * @param   array|null  $source   Исходные данные (необязательно, по умолчанию $_COOKIE)
     * @param   array       $options  Массив параметров конфигурации (необязательно)
     */
    public function __construct(?array $source = null, array $options = []) {
        $source = $source ?? $_COOKIE;
        parent::__construct($source, $options);
    }

    /**
     * Устанавливает значение
     *
     * @param   string   $name      Имя значения, которое необходимо установить.
     * @param   mixed    $value     Значение, которое нужно присвоить входу.
     * @param   array    $options   Ассоциативный массив, который может иметь любой ключ: 
     *                              expires, path, Domain, Secure, httponly и Samesite. 
     *                              Значения имеют тот же смысл, что и описанный для параметров с тем же именем. 
     *                              Значение того же элемента сайта должно быть либо Lax, либо Strict. 
     *                              Если какая-либо из разрешенных опций не указана, их значения по умолчанию совпадают 
     *                              со значениями по умолчанию явных параметров. 
     *                              Если элемент SameSite опущен, атрибут cookie SameSite не устанавливается.
     *
     * @return  void
     *
     * @link    https://www.ietf.org/rfc/rfc2109.txt
     * @link    https://php.net/manual/ru/function.setcookie.php
     */
    public function set(string $name, mixed $value, array $options = []): void {
        if (version_compare(PHP_VERSION, '7.3', '>=')) {
            setcookie($name, $value, $options);
        } else {
            if (array_key_exists('expires', $options) === false) {
                $options['expires'] = 0;
            }

            if (array_key_exists('path', $options) === false) {
                $options['path'] = '';
            }

            if (array_key_exists('domain', $options) === false) {
                $options['domain'] = '';
            }

            if (array_key_exists('secure', $options) === false) {
                $options['secure'] = false;
            }

            if (array_key_exists('httponly', $options) === false) {
                $options['httponly'] = false;
            }

            setcookie($name, $value, $options['expires'], $options['path'], $options['domain'], $options['secure'], $options['httponly']);
        }

        $this->data[$name] = $value;
    }
}
