<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Descriptor;

use Flexis\Console\Application;
use Flexis\Console\Command\AbstractCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Описывает приложение.
 */
final class ApplicationDescription {
    /**
     * Заполнитель для команд в глобальном пространстве имен.
     *
     * @var    string
     */
    public const string GLOBAL_NAMESPACE = '_global';

    /**
     * Псевдонимы команд приложения.
     *
     * @var    AbstractCommand[]
     */
    private array $aliases;

    /**
     * Описываемое приложение.
     *
     * @var    Application
     */
    private Application $application;

    /**
     * Команды приложения.
     *
     * @var    AbstractCommand[]|null
     */
    private ?array $commands = null;

    /**
     * Пространство имен команды для обработки.
     *
     * @var    string
     */
    private string $namespace = '';

    /**
     * Пространства имен команд приложения.
     *
     * @var    array[]|null
     */
    private ?array $namespaces = null;

    /**
     * Флаг, указывающий на скрытые команды.
     *
     * @var    boolean
     */
    private bool $showHidden;

    /**
     * Конструктор.
     *
     * @param   Application  $application  Описываемое приложение.
     * @param   string       $namespace    Пространство имен команды для обработки.
     * @param   boolean      $showHidden   Флаг, указывающий на скрытые команды.
     */
    public function __construct(Application $application, string $namespace = '', bool $showHidden = false) {
        $this->application = $application;
        $this->namespace   = $namespace;
        $this->showHidden  = $showHidden;
    }

    /**
     * Возвращает пространства имен команд приложения.
     *
     * @return  array[]
     */
    public function getNamespaces(): array {
        if ($this->namespaces === null) {
            $this->inspectApplication();
        }

        return $this->namespaces;
    }

    /**
     * Возвращает команды приложения.
     *
     * @return  AbstractCommand[]
     */
    public function getCommands(): array {
        if ($this->commands === null) {
            $this->inspectApplication();
        }

        return $this->commands;
    }

    /**
     * Возвращает команду по имени.
     *
     * @param   string  $name  Имя команды для получения.
     *
     * @return  AbstractCommand
     * @throws  CommandNotFoundException
     */
    public function getCommand(string $name): AbstractCommand {
        if (!isset($this->commands[$name]) && !isset($this->aliases[$name])) {
            throw new CommandNotFoundException(sprintf('Команда %s не существует.', $name));
        }

        return $this->commands[$name] ?? $this->aliases[$name];
    }

    /**
     * Возвращает часть пространства имен имени команды.
     *
     * @param   string    $name   Имя команды для обработки
     * @param   ?integer  $limit  Максимальное количество частей пространства имен
     *
     * @return  string
     */
    private function extractNamespace(string $name, ?int $limit = null): string {
        $parts = explode(':', $name);
        array_pop($parts);

        return implode(':', $limit === null ? $parts : \array_slice($parts, 0, $limit));
    }

    /**
     * Проверяет приложение.
     *
     * @return  void
     */
    private function inspectApplication(): void {
        $this->commands   = [];
        $this->namespaces = [];

        $all = $this->application->getAllCommands($this->namespace ? $this->application->findNamespace($this->namespace) : '');

        foreach ($this->sortCommands($all) as $namespace => $commands) {
            $names = [];

            foreach ($commands as $name => $command) {
                if (!$command->getName() || (!$this->showHidden && $command->isHidden())) {
                    continue;
                }

                if ($command->getName() === $name) {
                    $this->commands[$name] = $command;
                } else {
                    $this->aliases[$name] = $command;
                }

                $names[] = $name;
            }

            $this->namespaces[$namespace] = ['id' => $namespace, 'commands' => $names];
        }
    }

    /**
     * Сортировка набора команд.
     *
     * @param   AbstractCommand[]  $commands  Команды для сортировки.
     *
     * @return  AbstractCommand[][]
     */
    private function sortCommands(array $commands): array {
        $namespacedCommands = [];
        $globalCommands     = [];

        foreach ($commands as $name => $command) {
            $key = $this->extractNamespace($name, 1);

            if (!$key) {
                $globalCommands[self::GLOBAL_NAMESPACE][$name] = $command;
            } else {
                $namespacedCommands[$key][$name] = $command;
            }
        }

        ksort($namespacedCommands);
        $namespacedCommands = array_merge($globalCommands, $namespacedCommands);

        foreach ($namespacedCommands as &$commandsSet) {
            ksort($commandsSet);
        }

        unset($commandsSet);

        return $namespacedCommands;
    }
}
