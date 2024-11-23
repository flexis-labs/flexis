<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Loader;

use Flexis\Console\Command\AbstractCommand;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Загрузчик команд, совместимый с PSR-11.
 */
final class ContainerLoader implements LoaderInterface {
    /**
     * Сервисный контейнер.
     *
     * @var    ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * Имя команды для сопоставления идентификаторов службы.
     *
     * @var    string[]
     */
    private array $commandMap;

    /**
     * Конструктор.
     *
     * @param   ContainerInterface  $container   Контейнер, из которого загружаются командные службы.
     * @param   array               $commandMap  Массив с именами команд в качестве ключей и идентификаторами служб в качестве значений.
     */
    public function __construct(ContainerInterface $container, array $commandMap) {
        $this->container  = $container;
        $this->commandMap = $commandMap;
    }

    /**
     * Загружает команду.
     *
     * @param   string  $name  Команда для загрузки.
     *
     * @return  AbstractCommand
     *
     * @throws  CommandNotFoundException
     */
    public function get(string $name): AbstractCommand {
        if (!$this->has($name)) {
            throw new CommandNotFoundException(sprintf('Команда «%s» не существует.', $name));
        }

        return $this->container->get($this->commandMap[$name]);
    }

    /**
     * Возвращает имена зарегистрированных команд.
     *
     * @return  string[]
     */
    public function getNames(): array {
        return array_keys($this->commandMap);
    }

    /**
     * Проверяет, существует ли команда.
     *
     * @param   string  $name  Команда для проверки.
     *
     * @return  boolean
     */
    public function has(string $name): bool {
        return isset($this->commandMap[$name]) && $this->container->has($this->commandMap[$name]);
    }
}
