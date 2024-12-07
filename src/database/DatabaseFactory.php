<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

/**
 * Класс фабрики базы данных Flexis Framework
 */
class DatabaseFactory {
    /**
     * Метод для возврата драйвера базы данных на основе заданных параметров.
     *
     * Существует три глобальных параметра, а остальные зависят от драйвера базы данных.
     * Опция «database» определяет, какая база данных будет использоваться для соединения.
     * Опция «select» определяет, должен ли коннектор автоматически выбирать выбранную базу данных.
     *
     * @param   string  $name     Имя драйвера базы данных, экземпляр которого вы хотите создать.
     * @param   array   $options  Параметры, которые будут переданы драйверу базы данных.
     *
     * @return  DatabaseInterface
     *
     * @throws  Exception\UnsupportedAdapterException если нет совместимого драйвера базы данных.
     */
    public function getDriver(string $name = 'mysqli', array $options = []): DatabaseInterface {
        $options['driver']   = preg_replace('/[^A-Z0-9_\.-]/i', '', $name);
        $options['database'] = $options['database'] ?? null;
        $options['select']   = $options['select'] ?? true;
        $options['factory']  = $options['factory'] ?? $this;

        $class = __NAMESPACE__ . '\\' . ucfirst(strtolower($options['driver'])) . '\\' . ucfirst(strtolower($options['driver'])) . 'Driver';

        if (!class_exists($class)) {
            throw new Exception\UnsupportedAdapterException(sprintf('Невозможно загрузить драйвер базы данных: %s', $options['driver']));
        }

        return new $class($options);
    }

    /**
     * Возвращает объект класса экспортера.
     *
     * @param   string                  $name  Имя драйвера, для которого вам нужен экспортер.
     * @param   DatabaseInterface|null  $db    Дополнительный драйвер базы данных для внедрения в объект запроса.
     *
     * @return  DatabaseExporter
     *
     * @throws  Exception\UnsupportedAdapterException если нет совместимого экспортера базы данных
     */
    public function getExporter(string $name, ?DatabaseInterface $db = null): DatabaseExporter {
        $class = __NAMESPACE__ . '\\' . ucfirst(strtolower($name)) . '\\' . ucfirst(strtolower($name)) . 'Exporter';

        if (!class_exists($class)) {
            throw new Exception\UnsupportedAdapterException('Экспортер базы данных не найден.');
        }

        /** @var DatabaseExporter $o */
        $o = new $class();

        if ($db) {
            $o->setDbo($db);
        }

        return $o;
    }

    /**
     * Возвращает объект класса импортера.
     *
     * @param   string                  $name  Имя драйвера, для которого вам нужен импортер.
     * @param   DatabaseInterface|null  $db    Дополнительный драйвер базы данных для внедрения в объект запроса.
     *
     * @return  DatabaseImporter
     *
     * @throws  Exception\UnsupportedAdapterException если нет совместимого импортера базы данных
     */
    public function getImporter(string $name, ?DatabaseInterface $db = null): DatabaseImporter {
        $class = __NAMESPACE__ . '\\' . ucfirst(strtolower($name)) . '\\' . ucfirst(strtolower($name)) . 'Importer';

        if (!class_exists($class)) {
            throw new Exception\UnsupportedAdapterException('Импортер базы данных не найден.');
        }

        /** @var DatabaseImporter $o */
        $o = new $class();

        if ($db) {
            $o->setDbo($db);
        }

        return $o;
    }

    /**
     * Возвращает новый итератор для текущего запроса.
     *
     * @param   string              $name       Имя драйвера, для которого требуется итератор.
     * @param   StatementInterface  $statement  Оператор, содержащий набор результатов для повторения.
     * @param   string|null         $column     Необязательный столбец, используемый в качестве ключа итератора.
     * @param   string              $class      Класс возвращаемого объекта.
     *
     * @return  DatabaseIterator
     */
    public function getIterator(
        string $name,
        StatementInterface $statement,
        ?string $column = null,
        string $class = \stdClass::class
    ): DatabaseIterator {

        $iteratorClass = __NAMESPACE__ . '\\' . ucfirst($name) . '\\' . ucfirst($name) . 'Iterator';

        if (!class_exists($iteratorClass)) {
            $iteratorClass = DatabaseIterator::class;
        }

        return new $iteratorClass($statement, $column, $class);
    }

    /**
     * Возвращает текущий объект запроса или новый объект запроса.
     *
     * @param   string                  $name  Имя драйвера, для которого требуется объект запроса.
     * @param   DatabaseInterface|null  $db    Дополнительный драйвер базы данных для внедрения в объект запроса.
     *
     * @return  QueryInterface
     *
     * @throws  Exception\UnsupportedAdapterException если нет совместимого объекта запроса к базе данных
     */
    public function getQuery(string $name, ?DatabaseInterface $db = null): QueryInterface {
        $class = __NAMESPACE__ . '\\' . ucfirst(strtolower($name)) . '\\' . ucfirst(strtolower($name)) . 'Query';

        if (!class_exists($class)) {
            throw new Exception\UnsupportedAdapterException('Класс запроса к базе данных не найден.');
        }

        return new $class($db);
    }
}
