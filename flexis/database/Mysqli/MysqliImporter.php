<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Mysqli;

use Flexis\Database\DatabaseImporter;

/**
 * Импортер баз данных MySQLi.
 */
class MysqliImporter extends DatabaseImporter {
    /**
     * Перед экспортом проверяется, все ли данные и параметры в порядке.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
     */
    public function check(): static {
        if (!($this->db instanceof MysqliDriver)) {
            throw new \RuntimeException('Неправильный тип подключения к базе данных.');
        }

        if (empty($this->from)) {
            throw new \RuntimeException('Ошибка: таблицы не указаны.');
        }

        return $this;
    }

    /**
     * Возвращает синтаксис SQL для добавления таблицы.
     *
     * @param   \SimpleXMLElement  $table  Информация о таблице.
     *
     * @return  string
     * @throws  \RuntimeException
     */
    protected function xmlToCreate(\SimpleXMLElement $table): string {
        $existingTables = $this->db->getTableList();
        $tableName      = (string) $table['name'];

        if (\in_array($tableName, $existingTables, true)) {
            throw new \RuntimeException('Таблица, которую вы пытаетесь создать, уже существует.');
        }

        $createTableStatement = 'CREATE TABLE ' . $this->db->quoteName($tableName) . ' (';
        foreach ($table->xpath('field') as $field) {
            $createTableStatement .= $this->getColumnSql($field) . ', ';
        }

        $newLookup = $this->getKeyLookup($table->xpath('key'));
        foreach ($newLookup as $key) {
            $createTableStatement .= $this->getKeySql($key) . ', ';
        }

        $createTableStatement = rtrim($createTableStatement, ', ');
        $createTableStatement .= ')';

        return $createTableStatement;
    }

    /**
     * Возвращает синтаксис SQL для добавления ключа.
     *
     * @param   string  $table  Имя таблицы.
     * @param   array   $keys   Массив полей, относящихся к этому ключу.
     *
     * @return  string
     *
     */
    protected function getAddKeySql(string $table, array $keys): string {
        return 'ALTER TABLE ' . $this->db->quoteName($table) . ' ADD ' . $this->getKeySql($keys);
    }

    /**
     * Возвращает изменения для таблицы, если есть разница.
     *
     * @param   \SimpleXMLElement  $structure  XML-структура таблицы.
     *
     * @return  array
     *
     */
    protected function getAlterTableSql(\SimpleXMLElement $structure): array {
        $table     = $this->getRealTableName($structure['name']);
        $oldFields = $this->db->getTableColumns($table, false);
        $oldKeys   = $this->db->getTableKeys($table);
        $alters    = [];

        $newFields = $structure->xpath('field');
        $newKeys   = $structure->xpath('key');

        foreach ($newFields as $field) {
            $fName = (string) $field['Field'];

            if (isset($oldFields[$fName])) {
                $column = $oldFields[$fName];
                $change = ((string) $field['Type'] !== $column->Type) || ((string) $field['Null'] !== $column->Null)
                    || ((string) $field['Default'] !== $column->Default) || ((string) $field['Extra'] !== $column->Extra);

                if ($change) {
                    $alters[] = $this->getChangeColumnSql($table, $field);
                }

                unset($oldFields[$fName]);
            } else {
                $alters[] = $this->getAddColumnSql($table, $field);
            }
        }

        foreach ($oldFields as $name => $column) {
            $alters[] = $this->getDropColumnSql($table, $name);
        }

        $oldLookup = $this->getKeyLookup($oldKeys);
        $newLookup = $this->getKeyLookup($newKeys);

        foreach ($newLookup as $name => $keys) {
            if (isset($oldLookup[$name])) {
                $same     = true;
                $newCount = \count($newLookup[$name]);
                $oldCount = \count($oldLookup[$name]);

                if ($newCount === $oldCount) {
                    for ($i = 0; $i < $newCount; $i++) {
                        $same = (((string) $newLookup[$name][$i]['Non_unique'] === $oldLookup[$name][$i]->Non_unique)
                            && ((string) $newLookup[$name][$i]['Column_name'] === $oldLookup[$name][$i]->Column_name)
                            && ((string) $newLookup[$name][$i]['Seq_in_index'] === $oldLookup[$name][$i]->Seq_in_index)
                            && ((string) $newLookup[$name][$i]['Collation'] === $oldLookup[$name][$i]->Collation)
                            && ((string) $newLookup[$name][$i]['Sub_part'] == $oldLookup[$name][$i]->Sub_part)
                            && ((string) $newLookup[$name][$i]['Index_type'] === $oldLookup[$name][$i]->Index_type));

                        if (!$same) {
                            break;
                        }
                    }
                } else {
                    $same = false;
                }

                if (!$same) {
                    $alters[] = $this->getDropKeySql($table, $name);
                    $alters[] = $this->getAddKeySql($table, $keys);
                }

                unset($oldLookup[$name]);
            } else {
                $alters[] = $this->getAddKeySql($table, $keys);
            }
        }

        foreach ($oldLookup as $name => $keys) {
            if (strtoupper($name) === 'PRIMARY') {
                $alters[] = $this->getDropPrimaryKeySql($table);
            } else {
                $alters[] = $this->getDropKeySql($table, $name);
            }
        }

        return $alters;
    }

    /**
     * Возвращает синтаксис для изменения столбца.
     *
     * @param   string             $table  Имя таблицы базы данных, которую необходимо изменить.
     * @param   \SimpleXMLElement  $field  XML-определение поля.
     *
     * @return  string
     *
     */
    protected function getChangeColumnSql(string $table, \SimpleXMLElement $field): string {
        return 'ALTER TABLE ' . $this->db->quoteName($table) . ' CHANGE COLUMN ' . $this->db->quoteName((string) $field['Field']) . ' '
            . $this->getColumnSql($field);
    }

    /**
     * Возвращает синтаксис SQL для одного столбца, который будет включен в оператор создания или изменения таблицы.
     *
     * @param   \SimpleXMLElement  $field  Определение поля XML.
     *
     * @return  string
     *
     */
    protected function getColumnSql(\SimpleXMLElement $field): string {
        $blobs = ['text', 'smalltext', 'mediumtext', 'largetext'];

        $fName    = (string) $field['Field'];
        $fType    = (string) $field['Type'];
        $fNull    = (string) $field['Null'];
        $fDefault = isset($field['Default']) ? (string) $field['Default'] : null;
        $fExtra   = (string) $field['Extra'];

        $sql = $this->db->quoteName($fName) . ' ' . $fType;

        if ($fNull === 'NO') {
            if ($fDefault === null || \in_array($fType, $blobs, true)) {
                $sql .= ' NOT NULL';
            } else {
                if (stristr($fDefault, 'CURRENT') !== false) {
                    $sql .= ' NOT NULL DEFAULT CURRENT_TIMESTAMP()';
                } else {
                    $sql .= ' NOT NULL DEFAULT ' . $this->db->quote($fDefault);
                }
            }
        } else {
            if ($fDefault === null) {
                $sql .= ' DEFAULT NULL';
            } else {
                $sql .= ' DEFAULT ' . $this->db->quote($fDefault);
            }
        }

        if ($fExtra) {
            if (stristr($fExtra, 'DEFAULT_GENERATED') !== false) {
                $sql .= ' ' . strtoupper(str_ireplace('DEFAULT_GENERATED', 'DEFAULT ' . $fDefault, $fExtra));
            } else {
                $sql .= ' ' . strtoupper($fExtra);
            }
        }

        return $sql;
    }

    /**
     * Возвращает синтаксис SQL для удаления ключа.
     *
     * @param   string  $table  Имя таблицы.
     * @param   string  $name   Имя ключа, который нужно сбросить.
     *
     * @return  string
     *
     */
    protected function getDropKeySql(string $table, string $name): string {
        return 'ALTER TABLE ' . $this->db->quoteName($table) . ' DROP KEY ' . $this->db->quoteName($name);
    }

    /**
     * Возвращает синтаксис SQL для удаления ключа.
     *
     * @param string $table  Имя таблицы.
     *
     * @return  string
     *
     */
    protected function getDropPrimaryKeySql(string $table): string {
        return 'ALTER TABLE ' . $this->db->quoteName($table) . ' DROP PRIMARY KEY';
    }

    /**
     * Возвращает подробный список ключей для таблицы.
     *
     * @param array $keys  Массив объектов, составляющих ключи таблицы.
     *
     * @return  array  Поиск array.array({key name} => array(object, ...))
     *
     */
    protected function getKeyLookup(array $keys): array {
        $lookup = [];

        foreach ($keys as $key) {
            if ($key instanceof \SimpleXMLElement) {
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
     * Возвращает синтаксис SQL для ключа.
     *
     * @param array $columns  Массив объектов SimpleXMLElement, содержащий ключ.
     *
     * @return  string
     *
     */
    protected function getKeySql(array $columns): string {
        $kNonUnique = (string) $columns[0]['Non_unique'];
        $kName      = (string) $columns[0]['Key_name'];
        $prefix     = '';

        if ($kName === 'PRIMARY') {
            $prefix = 'PRIMARY ';
        } elseif ($kNonUnique == 0) {
            $prefix = 'UNIQUE ';
        }

        $kColumns = [];

        foreach ($columns as $column) {
            $kLength = '';

            if (!empty($column['Sub_part'])) {
                $kLength = '(' . $column['Sub_part'] . ')';
            }

            $kColumns[] = $this->db->quoteName((string) $column['Column_name']) . $kLength;
        }

        return $prefix . 'KEY ' . ($kName !== 'PRIMARY' ? $this->db->quoteName($kName) : '') . ' (' . implode(',', $kColumns) . ')';
    }
}
