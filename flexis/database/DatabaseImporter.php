<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

use Exception;
use RuntimeException;
use SimpleXMLElement;
use stdClass;

/**
 * Класс импортера базы данных Flexis Framework
 */
abstract class DatabaseImporter {
    /**
     * Массив кэшированных данных.
     *
     * @var    array
     */
    protected array $cache = ['columns' => [], 'keys' => []];

    /**
     * Интерфейс базы данных, используемый для экспорта структуры и/или данных.
     *
     * @var    DatabaseInterface
     */
    protected DatabaseInterface $db;

    /**
     * Источник данных.
     *
     * @var    mixed
     */
    protected mixed $from = [];

    /**
     * Тип входного формата.
     *
     * @var    string
     */
    protected string $asFormat = 'xml';

    /**
     * Массив опций для экспортера.
     *
     * @var    stdClass
     */
    protected stdClass $options;

    /**
     * Конструктор.
     *
     * Устанавливает параметры по умолчанию для импортера.
     *
     */
    public function __construct() {
        $this->options = new stdClass();
        $this->withStructure();
        $this->asXml();
    }

    /**
     * Устанавливает параметр вывода для импортера в формат XML.
     *
     * @return  $this
     *
     */
    public function asXml(): static {
        $this->asFormat = 'xml';

        return $this;
    }

    /**
     * Перед импортером проверяется, все ли данные и опции в порядке.
     *
     * @return  $this
     *
     * @throws  RuntimeException
     */
    abstract public function check(): static;

    /**
     * Указывает источник данных для импорта.
     *
     * @param SimpleXMLElement|string $from  Источник данных для импорта: объект SimpleXMLElement или строка XML.
     *
     * @return  $this
     *
     */
    public function from(SimpleXMLElement|string $from): static {
        $this->from = $from;

        return $this;
    }

    /**
     * Возвращает синтаксис SQL для добавления столбца.
     *
     * @param string            $table  Имя таблицы.
     * @param SimpleXMLElement  $field  Определение поля XML.
     *
     * @return  string
     *
     */
    protected function getAddColumnSql(string $table, SimpleXMLElement $field): string {
        return 'ALTER TABLE ' . $this->db->quoteName($table) . ' ADD COLUMN ' . $this->getColumnSQL($field);
    }

    /**
     * Возвращает изменения для таблицы, если есть разница.
     *
     * @param   SimpleXMLElement  $structure  XML-структура таблицы.
     *
     * @return  array
     */
    abstract protected function getAlterTableSql(SimpleXMLElement $structure): array;

    /**
     * Возвращает синтаксис для изменения столбца.
     *
     * @param   string            $table  Имя таблицы базы данных, которую необходимо изменить.
     * @param   SimpleXMLElement  $field  XML-определение поля.
     *
     * @return  string
     *
     */
    protected function getChangeColumnSql(string $table, SimpleXMLElement $field): string {
        return 'ALTER TABLE ' . $this->db->quoteName($table) . ' CHANGE COLUMN ' . $this->db->quoteName((string) $field['Field']) . ' '
            . $this->getColumnSQL($field);
    }

    /**
     * Возвращает синтаксис SQL для одного столбца, который будет включен в оператор создания или изменения таблицы.
     *
     * @param   SimpleXMLElement  $field  Определение поля XML.
     *
     * @return  string
     *
     */
    abstract protected function getColumnSql(SimpleXMLElement $field): string;

    /**
     * Возвращает синтаксис SQL для удаления столбца.
     *
     * @param string $table  Имя таблицы.
     * @param string $name   Имя поля, которое необходимо удалить.
     *
     * @return  string
     *
     */
    protected function getDropColumnSql(string $table, string $name): string {
        return 'ALTER TABLE ' . $this->db->quoteName($table) . ' DROP COLUMN ' . $this->db->quoteName($name);
    }

    /**
     * Возвращает подробный список ключей для таблицы.
     *
     * @param array $keys  Массив объектов, составляющих ключи таблицы.
     *
     * @return  array  Массив поиска. array({key name} => array(object, ...))
     *
     */
    protected function getKeyLookup(array $keys): array {
        $lookup = [];

        foreach ($keys as $key) {
            if ($key instanceof SimpleXMLElement) {
                $kName = (string) $key['Key_name'];
            } else {
                $kName = $key->Key_name;
            }

            if (empty($lookup[$kName])) {
                $lookup[$kName] = [];
            }

            $lookup[$kName][] = $key;
        }

        return $lookup;
    }

    /**
     * Возвращает настоящее имя таблицы, преобразуя строку подстановочного знака префикса, если она присутствует.
     *
     * @param string $table  Имя таблицы.
     *
     * @return  string  Настоящее имя таблицы.
     *
     */
    protected function getRealTableName(string $table): string {
        $prefix = $this->db->getPrefix();

        return preg_replace('|^#__|', $prefix, $table);
    }

    /**
     * Импортируйте данные из источника в существующие таблицы.
     *
     * @return  void
     *
     * @note    В настоящее время поддерживается только формат XML.
     *
     * @throws  RuntimeException по ошибке.
     * @throws  Exception
     */
    public function importData(): void {
        if ($this->from instanceof SimpleXMLElement) {
            $xml = $this->from;
        } else {
            $xml = new SimpleXMLElement($this->from);
        }

        $xmlTables = $xml->xpath('database/table_data');

        foreach ($xmlTables as $table) {
            $tableName = $this->getRealTableName((string) $table['name']);

            $rows = $table->children();

            foreach ($rows as $row) {
                if ($row->getName() == 'row') {
                    $entry = new stdClass();

                    foreach ($row->children() as $data) {
                        if (isset($data['value_is_null'])) {
                            $entry->{(string) $data['name']} = null;
                        } else {
                            $entry->{(string) $data['name']} = (string) $data;
                        }
                    }

                    $this->db->insertObject($tableName, $entry);
                }
            }
        }
    }

    /**
     * Объединяет определение входящей структуры с существующей структурой.
     *
     * @return  void
     *
     * @note    В настоящее время поддерживается только формат XML.
     * @throws  RuntimeException по ошибке.
     * @throws  Exception
     */
    public function mergeStructure(): void {
        $tables = $this->db->getTableList();

        if ($this->from instanceof SimpleXMLElement) {
            $xml = $this->from;
        } else {
            $xml = new SimpleXMLElement($this->from);
        }

        $xmlTables = $xml->xpath('database/table_structure');

        foreach ($xmlTables as $table) {
            $tableName = $this->getRealTableName((string) $table['name']);

            if (\in_array($tableName, $tables, true)) {
                if ($queries = $this->getAlterTableSql($table)) {
                    foreach ($queries as $query) {
                        $this->db->setQuery((string) $query);
                        $this->db->execute();
                    }
                }
            } else {
                $sql     = $this->xmlToCreate($table);
                $queries = explode(';', (string) $sql);

                foreach ($queries as $query) {
                    if (!empty($query)) {
                        $this->db->setQuery((string) $query);
                        $this->db->execute();
                    }
                }
            }
        }
    }

    /**
     * Устанавливает соединитель базы данных, который будет использоваться для экспорта структуры и/или данных.
     *
     * @param   DatabaseInterface  $db  Коннектор базы данных.
     *
     * @return  $this
     *
     */
    public function setDbo(DatabaseInterface $db): static {
        $this->db = $db;
        return $this;
    }

    /**
     * Устанавливает внутреннюю опцию для объединения структуры на основе входных данных.
     *
     * @param   boolean  $setting  Значение true — импортировать структуру, значение false — нет.
     *
     * @return  $this
     *
     */
    public function withStructure($setting = true): static {
        $this->options->withStructure = (bool) $setting;
        return $this;
    }

    /**
     * Возвращает синтаксис SQL для добавления таблицы.
     *
     * @param   SimpleXMLElement  $table  Информация о таблице.
     *
     * @return  string
     * @throws  RuntimeException
     */
    abstract protected function xmlToCreate(SimpleXMLElement $table): string;
}
