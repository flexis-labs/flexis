<?php

/**
 * Часть пакета Flexis Event Framework.
 */

namespace Flexis\Event;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;

/**
 * Декоратор для прослушивателя событий, извлекаемого из сервисного контейнера.
 */
final class LazyServiceEventListener {
    /**
     * Контейнер службы, из которого загружается служба.
     *
     * @var    ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Идентификатор службы из контейнера, который будет использоваться.
     *
     * @var    string
     */
    private string $serviceId;

    /**
     * Метод из службы, которую нужно вызвать
     *
     * @var    string
     */
    private string $method;

    /**
     * Конструктор.
     *
     * @param   ContainerInterface  $container  Контейнер службы для загрузки службы, когда она должна быть выполнена.
     * @param   string              $serviceId  Идентификатор службы из контейнера, который будет использоваться.
     * @param   string              $method     Метод службы, который будет вызываться при необходимости.
     *                                          Если оставить пустым, служба должна быть вызываемой;
     *                                          (т. е. иметь в классе метод `__invoke()`)
     * @throws  \InvalidArgumentException если идентификатор службы пуст
     */
    public function __construct(ContainerInterface $container, string $serviceId, string $method = '') {
        if (empty($serviceId)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Параметр $serviceId не может быть пустым в %s',
                    self::class
                )
            );
        }

        $this->container = $container;
        $this->serviceId = $serviceId;
        $this->method    = $method;
    }

    /**
     * Загружает службу из контейнера, чтобы прослушать событие.
     *
     * @param EventInterface $event Событие для обработки
     *
     * @return  void
     * @throws  \InvalidArgumentException если параметр $method конструктора пуст, когда не выполняется вызываемая служба
     * @throws  \RuntimeException если услуга не может быть выполнена
     * @throws ContainerExceptionInterface
     */
    public function __invoke(EventInterface $event): void {
        if (!$this->container->has($this->serviceId)) {
            throw new \RuntimeException(
                sprintf(
                    'Служба «%s» не зарегистрирована в контейнере служб.',
                    $this->serviceId
                )
            );
        }

        $service = $this->container->get($this->serviceId);

        // Если службу можно вызвать самостоятельно, просто запустим её.
        if (\is_callable($service)) {
            \call_user_func($service, $event);

            return;
        }

        if (empty($this->method)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Аргумент $method необходим при создании «%s» для вызова метода из службы «%s».',
                    self::class,
                    $this->serviceId
                )
            );
        }

        if (!method_exists($service, $this->method)) {
            throw new \RuntimeException(
                sprintf(
                    'Метод «%s» не существует в «%s» (из службы «%s»)',
                    $this->method,
                    \get_class($service),
                    $this->serviceId
                )
            );
        }

        \call_user_func([$service, $this->method], $event);
    }
}
