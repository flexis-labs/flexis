<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application;

use Flexis\Registry\Registry;

/**
 * Субинтерфейс приложения, определяющий класс приложения, который знает о своей конфигурации.
 */
interface ConfigurationAwareApplicationInterface extends ApplicationInterface {
    /**
     * Возвращает свойство объекта или значение по умолчанию, если свойство не установлено.
     *
     * @param   string  $key      Название объекта недвижимости.
     * @param   mixed   $default  Значение по умолчанию (необязательно), если оно не установлено.
     *
     * @return  mixed   Значение конфигурации.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Изменяет свойство объекта, создавая его, если оно еще не существует.
     *
     * @param   string  $key    Название объекта недвижимости.
     * @param   mixed   $value  Значение свойства, которое необходимо установить (необязательно).
     *
     * @return  mixed   Предыдущая стоимость недвижимости
     */
    public function set(string $key, mixed $value = null): mixed;

    /**
     * Устанавливает конфигурацию приложения.
     *
     * @param   Registry  $config  Объект реестра, содержащий конфигурацию.
     *
     * @return  $this
     */
    public function setConfiguration(Registry $config): self;
}
