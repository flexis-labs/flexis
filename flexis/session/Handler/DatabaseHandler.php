<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Handler;

use Flexis\Database\DatabaseDriver;
use Flexis\Database\DatabaseInterface;
use Flexis\Database\Exception\ExecutionFailureException;
use Flexis\Database\ParameterType;
use Flexis\Session\Exception\CreateSessionTableException;
use Flexis\Session\Exception\UnsupportedDatabaseDriverException;
use Flexis\Session\HandlerInterface;

/**
 * Обработчик хранилища сессии базы данных.
 */
class DatabaseHandler implements HandlerInterface {
    /**
     * Коннектор базы данных.
     *
     * @var  DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * Флаг, был ли вызван gc().
     *
     * @var    boolean
     */
    private bool $gcCalled = false;

    /**
     * Пожизненный сбор мусора.
     *
     * @var    integer|null
     */
    private ?int $gcLifetime = null;

    private array $meta = array(
        'mysql'  => 'CREATE TABLE IF NOT EXISTS `#__session` (`session_id` VARBINARY(128) NOT NULL,`time` INTEGER UNSIGNED NOT NULL,`data` BLOB NOT NULL,PRIMARY KEY (`session_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8;',
        'pgsql'  => 'CREATE TABLE IF NOT EXISTS "#__session" ("session_id" VARCHAR(128) NOT NULL,"time" INTEGER NOT NULL,"data" BYTEA NOT NULL,PRIMARY KEY ("session_id"));',
        'sqlite' => 'CREATE TABLE IF NOT EXISTS `#__session` (`session_id` TEXT NOT NULL,`time` INTEGER NOT NULL,`data` BLOB NOT NULL,CONSTRAINT `idx_session` PRIMARY KEY (`session_id`));',
        'sqlsrv' => 'IF OBJECT_ID ([#__session], U) IS NULL CREATE TABLE [#__session] ([session_id] VARCHAR(128) NOT NULL,[time] INTEGER NOT NULL,[data] VARBINARY(MAX) NOT NULL,CONSTRAINT [PK_#__session_session_id] PRIMARY KEY CLUSTERED([session_id] ASC) WITH (PAD_INDEX  = OFF, STATISTICS_NORECOMPUTE  = OFF, IGNORE_DUP_KEY = OFF, ALLOW_ROW_LOCKS  = ON, ALLOW_PAGE_LOCKS  = ON) ON [PRIMARY]) ON [PRIMARY];'
    );

    /**
     * Конструктор.
     *
     * @param   DatabaseInterface  $db  Коннектор базы данных.
     */
    public function __construct(DatabaseInterface $db) {
        /** @var  $this->db  DatabaseInterface */
        $this->db = $db;
    }

    /**
     * Закрывает сессию.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    #[\ReturnTypeWillChange]
    public function close(): bool {
        if ($this->gcCalled) {
            $query = $this->db->createQuery()
                ->delete($this->db->quoteName('#__session'))
                ->where($this->db->quoteName('time') . ' < ?')
                ->bind(1, $this->gcLifetime, ParameterType::INTEGER);

            $this->db->setQuery($query)->execute();

            $this->gcCalled   = false;
            $this->gcLifetime = null;
        }

        $this->db->disconnect();

        return true;
    }

    /**
     * Создаёт таблицу базы данных сессии.
     *
     * @return  boolean
     * @throws  CreateSessionTableException
     * @throws  UnsupportedDatabaseDriverException
     */
    public function createDatabaseTable(): bool {
        $schema_name = match ($this->db->getName()) {
            'mysql', 'mysqli' => 'mysql',
            'postgresql' => 'pgsql',
            'sqlsrv', 'sqlazure' => 'sqlsrv',
            'sqlite' => 'sqlite',
            default => throw new UnsupportedDatabaseDriverException(sprintf('Драйвер базы данных %s не поддерживается.', $this->db->getName())),
        };

        $queries = DatabaseDriver::splitSql($this->meta[$schema_name]);

        foreach ($queries as $query) {
            $query = trim($query);

            if ($query !== '') {
                try {
                    $this->db->setQuery($query)->execute();
                } catch (ExecutionFailureException $exception) {
                    throw new CreateSessionTableException('Не удалось создать таблицу сессии.', 0, $exception);
                }
            }
        }

        return true;
    }

    /**
     * Уничтожить сессию.
     *
     * @param   string  $id  Идентификатор сессии уничтожается.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    public function destroy(string $id): bool {
        try {
            $query = $this->db->createQuery()
                ->delete($this->db->quoteName('#__session'))
                ->where($this->db->quoteName('session_id') . ' = ' . $this->db->quote($id));

            $this->db->setQuery($query)->execute();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Очистка старых сессий.
     *
     * @param   integer  $maxlifetime  Сессии, которые не обновлялись в течение последних секунд maxlifetime, будут удалены.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    #[\ReturnTypeWillChange]
    public function gc(int $maxlifetime): bool {
        $this->gcLifetime = time() - $maxlifetime;
        $this->gcCalled   = true;

        return true;
    }

    /**
     * Проверяет, доступен ли HandlerInterface.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    public static function isSupported(): bool {
        return interface_exists(DatabaseInterface::class);
    }

    /**
     * Инициализировать сессию.
     *
     * @param   string  $save_path   Путь для хранения/извлечения сессии.
     * @param   string  $session_id  Идентификатор сессии.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    #[\ReturnTypeWillChange]
    public function open(string $save_path, string $session_id): bool {
        $this->db->connect();

        return true;
    }

    /**
     * Чтение данных сессии.
     *
     * @param   string  $session_id  Идентификатор сессии для чтения данных.
     *
     * @return  string  Данные сессии.
     */
    #[\ReturnTypeWillChange]
    public function read(string $session_id): string {
        try {
            $query = $this->db->createQuery()
                ->select($this->db->quoteName('data'))
                ->from($this->db->quoteName('#__session'))
                ->where($this->db->quoteName('session_id') . ' = ?')
                ->bind(1, $session_id);

            $this->db->setQuery($query);

            return (string) $this->db->loadResult();
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Записывает данные сессии.
     *
     * @param   string  $session_id    Идентификатор сессии.
     * @param   string  $session_data  Закодированные данные сессии.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    #[\ReturnTypeWillChange]
    public function write(string $session_id, string $session_data): bool {
        try {
            $query = $this->db->createQuery()
                ->select($this->db->quoteName('session_id'))
                ->from($this->db->quoteName('#__session'))
                ->where($this->db->quoteName('session_id') . ' = ?')
                ->bind(1, $session_id);

            $idExists = $this->db->setQuery($query)->loadResult();

            $query = $this->db->createQuery();

            $time = time();

            if ($idExists) {
                $query->update($this->db->quoteName('#__session'))
                    ->set($this->db->quoteName('data') . ' = ?')
                    ->set($this->db->quoteName('time') . ' = ?')
                    ->where($this->db->quoteName('session_id') . ' = ?')
                    ->bind(1, $session_data)
                    ->bind(2, $time, ParameterType::INTEGER)
                    ->bind(3, $session_id);
            } else {
                $query->insert($this->db->quoteName('#__session'))
                    ->columns([$this->db->quoteName('data'), $this->db->quoteName('time'), $this->db->quoteName('session_id')])
                    ->values('?, ?, ?')
                    ->bind(1, $session_data)
                    ->bind(2, $time, ParameterType::INTEGER)
                    ->bind(3, $session_id);
            }

            $this->db->setQuery($query)->execute();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
