<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Command;

use Flexis\Console\Command\AbstractCommand;
use Flexis\Database\DatabaseInterface;
use Flexis\Session\Exception\CreateSessionTableException;
use Flexis\Session\Exception\UnsupportedDatabaseDriverException;
use Flexis\Session\Handler\DatabaseHandler;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Команда, используемая для создания таблицы базы данных сессии.
 */
class CreateSessionTableCommand extends AbstractCommand {
    /**
     * Имя команды по умолчанию.
     *
     * @var    string|null
     */
    protected static ?string $defaultName = 'session:create-table';

    /**
     * Коннектор базы данных.
     *
     * @var    DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * Создаёт экземпляр команды.
     *
     * @param DatabaseInterface $db Коннектор базы данных.
     *
     * @throws \ReflectionException
     */
    public function __construct(DatabaseInterface $db) {
        $this->db = $db;

        parent::__construct();
    }

    /**
     * Настраивает команду.
     *
     * @return  void
     */
    protected function configure(): void {
        $this->setDescription('Создаёт таблицу базы данных сессии, если она ещё не существует.');
    }

    /**
     * Внутренняя функция для выполнения команды.
     *
     * @param   InputInterface   $input   Входные данные для внедрения в команду.
     * @param   OutputInterface  $output  Вывод для внедрения в команду.
     *
     * @return  integer  Код завершения команды
     */
    protected function doExecute(InputInterface $input, OutputInterface $output): int {
        $io = new SymfonyStyle($input, $output);

        $io->title('Создание таблицы сессии');

        if (\in_array($this->db->replacePrefix('#__session'), $this->db->getTableList())) {
            $io->success('Таблица сессии уже существует.');

            return 0;
        }

        try {
            (new DatabaseHandler($this->db))->createDatabaseTable();
        } catch (UnsupportedDatabaseDriverException $exception) {
            $io->error($exception->getMessage());

            return 1;
        } catch (CreateSessionTableException $exception) {
            $io->error(\sprintf('Не удалось создать таблицу сессии: %s', $exception->getMessage()));

            return 1;
        }

        $io->success('Таблица сессии создана.');

        return 0;
    }
}
