<?php

/**
 * Часть пакета Flexis Uri Framework.
 */

namespace Flexis\Uri;

/**
 * Класс UriImmutable
 *
 * Это неизменяемая версия класса AbstractUri.
 */
final class UriImmutable extends AbstractUri {
    /**
     * Отметить, был ли создан экземпляр класса
     *
     * @var    boolean
     */
    private bool $constructed = false;

    /**
     * Запретить установку необъявленных свойств.
     *
     * @param   string  $name   Это неизменяемый объект, установка $name не разрешена.
     * @param   mixed   $value  Это неизменяемый объект, установка $value не допускается.
     *
     * @return  void  Этот метод всегда генерирует исключение.
     * @throws  \BadMethodCallException
     */
    public function __set(string $name, mixed $value) {
        throw new \BadMethodCallException('Это неизменяемый объект');
    }

    /**
     * This is a special constructor that prevents calling the __construct method again.
     *
     * @param   string  $uri  The optional URI string
     * @throws  \BadMethodCallException
     */
    public function __construct(?string $uri = null) {
        if ($this->constructed === true) {
            throw new \BadMethodCallException('Это неизменяемый объект');
        }

        $this->constructed = true;

        parent::__construct($uri);
    }
}
