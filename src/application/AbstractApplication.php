<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application;

use Flexis\Application\Event\ApplicationErrorEvent;
use Flexis\Application\Event\ApplicationEvent;
use Flexis\Event\DispatcherAwareInterface;
use Flexis\Event\DispatcherAwareTrait;
use Flexis\Event\EventInterface;
use Flexis\Registry\Registry;
use JetBrains\PhpStorm\NoReturn;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Класс базового приложения Flexis Framework.
 */
abstract class AbstractApplication implements
    ConfigurationAwareApplicationInterface,
    LoggerAwareInterface,
    DispatcherAwareInterface
{
    use LoggerAwareTrait;
    use DispatcherAwareTrait;

    /**
     * Объект конфигурации приложения.
     *
     * @var    Registry
     */
    protected Registry $config;

    /**
     * Конструктор класса.
     *
     * @param  Registry|null  $config   Необязательный аргумент, обеспечивающий внедрение зависимостей
     *                                  для объекта конфигурации приложения.  Если аргументом является объект реестра,
     *                                  этот объект станет объектом конфигурации приложения, иначе создаётся объект конфигурации по умолчанию.
     */
    public function __construct(?Registry $config = null) {
        $this->config = $config ?: new Registry();

        $this->set('execution.datetime', \gmdate('Y-m-d H:i:s'));
        $this->set('execution.timestamp', \time());
        $this->set('execution.microtimestamp', \microtime(true));

        $this->initialise();
    }

    /**
     * Способ закрытия приложения.
     *
     * @param  integer  $code  Код выхода (необязательно; по умолчанию — 0).
     *
     * @return  void
     *
     * @codeCoverageIgnore
     */
    #[NoReturn]
    public function close(int $code = 0): void {
        exit($code);
    }

    /**
     * Отправляет событие приложения, если был установлен диспетчер.
     *
     * @param  string               $eventName  Событие для отправки.
     * @param  EventInterface|null  $event      Объект события.
     *
     * @return  EventInterface|null  Отправленное событие или значение NULL, если диспетчер не установлен.
     */
    protected function dispatchEvent(string $eventName, ?EventInterface $event = null): ?EventInterface {
        try {
            $dispatcher = $this->getDispatcher();
        } catch (\UnexpectedValueException $exception) {
            return null;
        }

        return $dispatcher->dispatch(new ApplicationEvent($eventName, $this));
    }

    /**
     * Метод для запуска подпрограмм приложения.
     *
     * Скорее всего, вам захочется создать экземпляр контроллера и выполнить его или выполнить какую-то задачу напрямую.
     *
     * @return  void
     */
    abstract protected function doExecute(): void;

    /**
     * Выполняет приложение.
     *
     * @return  void
     */
    public function execute(): void {
        try {
            $this->dispatchEvent(ApplicationEvents::BEFORE_EXECUTE);
            $this->doExecute();
            $this->dispatchEvent(ApplicationEvents::AFTER_EXECUTE);
        } catch (\Throwable $throwable) {
            $this->dispatchEvent(ApplicationEvents::ERROR, new ApplicationErrorEvent($throwable, $this));
        }
    }

    /**
     * Возвращает свойство объекта или значение по умолчанию, если свойство не установлено.
     *
     * @param  string  $key      Название объекта недвижимости.
     * @param  mixed   $default  Значение по умолчанию (необязательно), если оно не установлено.
     *
     * @return  mixed   Значение конфигурации.
     */
    public function get(string $key, mixed $default = null): mixed {
        return $this->config->get($key, $default);
    }

    /**
     * Возвращает регистратор.
     *
     * @return  LoggerInterface
     */
    public function getLogger(): LoggerInterface {
        if (!($this->logger instanceof LoggerInterface)) {
            $this->setLogger(new NullLogger());
        }

        return $this->logger;
    }

    /**
     * Пользовательский метод инициализации.
     *
     * Вызывается в конце метода AbstractApplication::__construct.
     * Это позволяет разработчикам внедрять код инициализации для своих классов приложений.
     *
     * @return  void
     *
     * @codeCoverageIgnore
     */
    protected function initialise() {}

    /**
     * Изменяет свойство объекта, создавая его, если оно еще не существует.
     *
     * @param  string  $key    Название объекта недвижимости.
     * @param  mixed   $value  Значение свойства, которое необходимо установить (необязательно).
     *
     * @return  mixed   Предыдущая стоимость недвижимости
     */
    public function set(string $key, mixed $value = null): mixed {
        $previous = $this->config->get($key);
        $this->config->set($key, $value);

        return $previous;
    }

    /**
     * Устанавливает конфигурацию приложения.
     *
     * @param  Registry  $config  Объект реестра, содержащий конфигурацию.
     *
     * @return  $this
     */
    public function setConfiguration(Registry $config): self {
        $this->config = $config;

        return $this;
    }
}
