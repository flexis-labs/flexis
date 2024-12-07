<?php

/**
 * Часть пакета Flexis Console Framework.
 */

namespace Flexis\Console\Loader;

use Flexis\Console\Command\AbstractCommand;
use Symfony\Component\Console\Exception\CommandNotFoundException;

/**
 * Интерфейс, определяющий загрузчик команд.
 */
interface LoaderInterface {
    /**
     * Загружает команду.
     *
     * @param   string  $name  Команда для загрузки.
     *
     * @return  AbstractCommand
     * @throws  CommandNotFoundException
     */
    public function get(string $name): AbstractCommand;

    /**
     * Возвращает имена зарегистрированных команд.
     *
     * @return  string[]
     */
    public function getNames(): array;

    /**
     * Проверяет, существует ли команда.
     *
     * @param   string  $name  Команда для проверки.
     *
     * @return  boolean
     */
    public function has(string $name): bool;
}
