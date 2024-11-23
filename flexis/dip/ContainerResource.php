<?php

/**
 * Часть пакета Flexis DIP Framework.
 */

namespace Flexis\DIP;

/**
 * Определяет представление ресурса.
 */
final class ContainerResource {
    /**
     * Определяет ресурс как недоступный для общего доступа
     *
     * @const  integer
     */
    public const int NO_SHARE = 0;

    /**
     * Определяет ресурс как общий
     *
     * @const  integer
     */
    public const int SHARE = 1;

    /**
     * Определяет ресурс как незащищённый
     *
     * @const  integer
     */
    public const int NO_PROTECT = 0;

    /**
     * Определяет ресурс как защищённый
     *
     * @const  integer
     */
    public const int PROTECT = 2;

    /**
     * Контейнер, которому назначен ресурс
     *
     * @var    Container
     */
    private Container $container;

    /**
     * Экземпляр объекта для общего объекта
     *
     * @var    mixed
     */
    private mixed $instance = null;

    /**
     * Объект фабрики
     *
     * @var    callable
     */
    private $factory;

    /**
     * Флаг, является ли ресурс общим
     *
     * @var    boolean
     */
    private bool $shared = false;

    /**
     * Флаг, защищен ли ресурс
     *
     * @var    boolean
     */
    private bool $protected = false;

    /**
     * Создание представление ресурса
     *
     * @param   Container  $container  Контейнер
     * @param   mixed      $value      Ресурс или закрытие его фабрики
     * @param   integer    $mode       Ресурсный режим, по умолчанию используется Resource::NO_SHARE | Resource::NO_PROTECT
     */
    public function __construct(Container $container, mixed $value, int $mode = 0) {
        $this->container = $container;
        $this->shared    = ($mode & self::SHARE) === self::SHARE;
        $this->protected = ($mode & self::PROTECT) === self::PROTECT;

        if (\is_callable($value)) {
            $this->factory = $value;
        } else {
            if ($this->shared) {
                $this->instance = $value;
            }

            if (\is_object($value)) {
                $this->factory = function () use ($value) {
                    return clone $value;
                };
            } else {
                $this->factory = function () use ($value) {
                    return $value;
                };
            }
        }
    }

    /**
     * Проверка, является ли ресурс общим.
     *
     * @return  boolean
     */
    public function isShared():bool {
        return $this->shared;
    }

    /**
     * Проверка, защищен ли ресурс
     *
     * @return  boolean
     */
    public function isProtected():bool {
        return $this->protected;
    }

    /**
     * Получение экземпляра ресурса.
     *
     * Если была предоставлена фабрика, ресурс создаётся и, если это общий ресурс, кэшируется внутри системы.
     * Если ресурс был предоставлен напрямую, этот ресурс возвращается.
     *
     * @return  mixed
     */
    public function getInstance():mixed {
        $callable = $this->factory;

        if ($this->isShared()) {
			if ($this->instance === null) {
                $this->instance = $callable($this->container);
            }

            return $this->instance;
        }

        return $callable($this->container);
    }

    /**
     * Получение фабрики.
     *
     * @return  callable
     */
    public function getFactory():callable {
        return $this->factory;
    }

    /**
     * Сброс ресурса.
     *
     * Кэш экземпляра очищается, так что следующий вызов функции get() возвращает новый экземпляр.
     * Это влияет только на общие незащищенные ресурсы.
     *
     * @return  boolean  True если ресурс был сброшен, иначе значение false
     */
    public function reset():bool {
        if ($this->isShared() && !$this->isProtected()) {
            $this->instance = null;

            return true;
        }

        return false;
    }
}
