<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Pgsql;

use Flexis\Database\DatabaseImporter;

/**
 * Импортер базы данных PDO PostgreSQL.
 */
class PgsqlImporter extends DatabaseImporter {
    /**
     * Перед экспортом проверяется, все ли данные и параметры в порядке.
     *
     * @return  $this
     *
     * @throws  \RuntimeException если возникла ошибка.
     */
    public function check(): static {
        if (!($this->db instanceof PgsqlDriver)) {
            throw new \RuntimeException('Неправильный тип подключения к базе данных.');
        }

        if (empty($this->from)) {
            throw new \RuntimeException('Ошибка: таблицы не указаны.');
        }

        return $this;
    }

    /**
     * Возвращает синтаксис SQL для добавления индекса.
     *
     * @param   \SimpleXMLElement  $field  Определение индекса XML.
     *
     * @return  string
     *
     */
    protected function getAddIndexSql(\SimpleXMLElement $field): string {
        return (string) $field['Query'];
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
        $table       = $this->getRealTableName($structure['name']);
        $oldFields   = $this->db->getTableColumns($table);
        $oldKeys     = $this->db->getTableKeys($table);
        $oldSequence = $this->db->getTableSequences($table);
        $alters      = [];
        $newFields   = $structure->xpath('field');
        $newKeys     = $structure->xpath('key');
        $newSequence = $structure->xpath('sequence');

        $oldSeq          = $this->getSeqLookup($oldSequence);
        $newSequenceLook = $this->getSeqLookup($newSequence);

        foreach ($newSequenceLook as $kSeqName => $vSeq) {
            if (isset($oldSeq[$kSeqName])) {
                $column = $oldSeq[$kSeqName][0];
                $change = ((string) $vSeq[0]['Type'] !== $column->Type)
                    || ((string) $vSeq[0]['Start_Value'] !== $column->Start_Value)
                    || ((string) $vSeq[0]['Min_Value'] !== $column->Min_Value)
                    || ((string) $vSeq[0]['Max_Value'] !== $column->Max_Value)
                    || ((string) $vSeq[0]['Increment'] !== $column->Increment)
                    || ((string) $vSeq[0]['Cycle_option'] !== $column->Cycle_option)
                    || ((string) $vSeq[0]['Table'] !== $column->Table)
                    || ((string) $vSeq[0]['Column'] !== $column->Column)
                    || ((string) $vSeq[0]['Schema'] !== $column->Schema)
                    || ((string) $vSeq[0]['Name'] !== $column->Name);

                if ($change) {
                    $alters[] = $this->getChangeSequenceSql($kSeqName, $vSeq);
                    $alters[] = $this->getSetvalSequenceSql($kSeqName, $vSeq);
                }

                unset($oldSeq[$kSeqName]);
            } else {
                $alters[] = $this->getAddSequenceSql($newSequenceLook[$kSeqName][0]);
                $alters[] = $this->getSetvalSequenceSql($newSequenceLook[$kSeqName][0]);
            }
        }

        foreach ($oldSeq as $name => $column) {
            $alters[] = $this->getDropSequenceSql($name);
        }

        foreach ($newFields as $field) {
            $fName = (string) $field['Field'];

            if (isset($oldFields[$fName])) {
                $column = $oldFields[$fName];
                $change = ((string) $field['Type'] !== $column->Type) || ((string) $field['Null'] !== $column->Null)
                    || ((string) $field['Default'] !== $column->Default);

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
                        $same = ((string) $newLookup[$name][$i]['Query'] === $oldLookup[$name][$i]->Query);

                        if (!$same) {
                            break;
                        }
                    }
                } else {
                    $same = false;
                }

                if (!$same) {
                    $alters[] = $this->getDropIndexSql($name);
                    $alters[] = (string) $newLookup[$name][0]['Query'];
                }

                unset($oldLookup[$name]);
            } else {
                $alters[] = (string) $newLookup[$name][0]['Query'];
            }
        }

        foreach ($oldLookup as $name => $keys) {
            if ($oldLookup[$name][0]->is_primary === 'TRUE') {
                $alters[] = $this->getDropPrimaryKeySql($table, $oldLookup[$name][0]->Index);
            } else {
                $alters[] = $this->getDropIndexSql($name);
            }
        }

        return $alters;
    }

    /**
     * Возвращает синтаксис SQL для удаления последовательности.
     *
     * @param   string  $name  Имя последовательности, которую нужно удалить.
     *
     * @return  string
     *
     */
    protected function getDropSequenceSql(string $name): string {
        return 'DROP SEQUENCE ' . $this->db->quoteName($name);
    }

    /**
     * Возвращает синтаксис для добавления последовательности.
     *
     * @param   \SimpleXMLElement  $field  XML-определение последовательности.
     *
     * @return  string
     *
     */
    protected function getAddSequenceSql(\SimpleXMLElement $field): string {
        return 'CREATE SEQUENCE IF NOT EXISTS ' . (string) $field['Name']
            . ' INCREMENT BY ' . (string) $field['Increment'] . ' MINVALUE ' . $field['Min_Value']
            . ' MAXVALUE ' . (string) $field['Max_Value'] . ' START ' . (string) $field['Start_Value']
            . (((string) $field['Cycle_option'] === 'NO') ? ' NO' : '') . ' CYCLE'
            . ' OWNED BY ' . $this->db->quoteName((string) $field['Schema'] . '.' . (string) $field['Table'] . '.' . (string) $field['Column']);
    }

    /**
     * Возвращает синтаксис для изменения последовательности.
     *
     * @param   \SimpleXMLElement  $field  XML-определение последовательности.
     *
     * @return  string
     *
     */
    protected function getChangeSequenceSql(\SimpleXMLElement $field): string {
        return 'ALTER SEQUENCE ' . (string) $field['Name']
            . ' INCREMENT BY ' . (string) $field['Increment'] . ' MINVALUE ' . (string) $field['Min_Value']
            . ' MAXVALUE ' . (string) $field['Max_Value'] . ' START ' . (string) $field['Start_Value']
            . ' OWNED BY ' . $this->db->quoteName((string) $field['Schema'] . '.' . (string) $field['Table'] . '.' . (string) $field['Column']);
    }

    /**
     * Возвращает синтаксис для установки последовательности.
     *
     * @param   \SimpleXMLElement  $field  XML-определение последовательности.
     *
     * @return  string
     */
    protected function getSetvalSequenceSql(\SimpleXMLElement $field): string {
        $is_called = $field['Is_called'] == 't' || $field['Is_called'] == '1' ? 'TRUE' : 'FALSE';

        return 'SELECT setval(\'' . (string) $field['Name'] . '\', ' . (string) $field['Last_Value'] . ', ' . $is_called . ')';
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
        return 'ALTER TABLE ' . $this->db->quoteName($table) . ' ALTER COLUMN ' . $this->db->quoteName((string) $field['Field']) . ' '
            . $this->getAlterColumnSql($table, $field);
    }

    /**
     * Возвращает синтаксис SQL для одного столбца, который будет включен в оператор создания таблицы.
     *
     * @param   string             $table  Имя таблицы базы данных, которую необходимо изменить.
     * @param   \SimpleXMLElement  $field  Определение поля XML.
     *
     * @return  string
     *
     */
    protected function getAlterColumnSql(string $table, \SimpleXMLElement $field): string {
        $blobs = ['text', 'smalltext', 'mediumtext', 'largetext'];

        $fName = (string) $field['Field'];
        $fType = (string) $field['Type'];
        $fNull = (string) $field['Null'];

        $fDefault = (isset($field['Default']) && $field['Default'] != 'NULL') ?
            preg_match('/^[0-9]$/', $field['Default']) ? $field['Default'] : $this->db->quote((string) $field['Default'])
            : null;

        $sql = ' TYPE ' . $fType;

        if ($fNull === 'NO') {
            if ($fDefault === null || \in_array($fType, $blobs, true)) {
                $sql .= ",\nALTER COLUMN " . $this->db->quoteName($fName) . ' SET NOT NULL'
                    . ",\nALTER COLUMN " . $this->db->quoteName($fName) . ' DROP DEFAULT';
            } else {
                $sql .= ",\nALTER COLUMN " . $this->db->quoteName($fName) . ' SET NOT NULL'
                    . ",\nALTER COLUMN " . $this->db->quoteName($fName) . ' SET DEFAULT ' . $fDefault;
            }
        } else {
            if ($fDefault !== null) {
                $sql .= ",\nALTER COLUMN " . $this->db->quoteName($fName) . ' DROP NOT NULL'
                    . ",\nALTER COLUMN " . $this->db->quoteName($fName) . ' SET DEFAULT ' . $fDefault;
            }
        }

        if (str_contains($fDefault, 'nextval')) {
            $sequence = $table . '_' . $fName . '_seq';
            $owner    = $table . '.' . $fName;

            $sql .= ";\nALTER SEQUENCE " . $this->db->quoteName($sequence) . ' OWNED BY ' . $this->db->quoteName($owner);
        }

        return $sql;
    }

    /**
     * Возвращает синтаксис SQL для одного столбца, который будет включен в оператор создания таблицы.
     *
     * @param   \SimpleXMLElement  $field  Определение поля XML.
     *
     * @return  string
     *
     */
    protected function getColumnSql(\SimpleXMLElement $field): string {
        $fName = (string) $field['Field'];
        $fType = (string) $field['Type'];
        $fNull = (string) $field['Null'];

        if (strpos($field['Default'], '::')) {
            $fDefault = strstr($field['Default'], '::', true);
        } else {
            $fDefault = isset($field['Default']) && strlen($field['Default']) > 0
                ? preg_match('/^[0-9]$/', $field['Default']) ? $field['Default'] : $this->db->quote((string) $field['Default'])
                : null;
        }

        if (str_contains($fDefault, 'nextval')) {
            $sql = $this->db->quoteName($fName) . ' SERIAL';
        } else {
            $sql = $this->db->quoteName($fName) . ' ' . $fType;

            if ($fNull == 'NO') {
                if ($fDefault === null) {
                    $sql .= ' NOT NULL';
                } else {
                    $sql .= ' NOT NULL DEFAULT ' . $fDefault;
                }
            } else {
                if ($fDefault !== null) {
                    $sql .= ' DEFAULT ' . $fDefault;
                }
            }
        }

        return $sql;
    }

    /**
     * Возвращает синтаксис SQL для удаления индекса.
     *
     * @param   string  $name  Имя ключа, который нужно сбросить.
     *
     * @return  string
     *
     */
    protected function getDropIndexSql(string $name): string {
        return 'DROP INDEX ' . $this->db->quoteName($name);
    }

    /**
     * Возвращает синтаксис SQL для удаления ключа.
     *
     * @param   string  $table  Имя таблицы.
     * @param   string  $name   Имя ограничения.
     *
     * @return  string
     *
     */
    protected function getDropPrimaryKeySql(string $table, string $name): string {
        return 'ALTER TABLE ONLY ' . $this->db->quoteName($table) . ' DROP CONSTRAINT ' . $this->db->quoteName($name);
    }

    /**
     * Возвращает подробный список ключей для таблицы.
     *
     * @param array $keys  Массив объектов, составляющих ключи таблицы.
     *
     * @return  array  Массив поиска. array({key name} => array(object, ...))
     */
    protected function getKeyLookup(array $keys): array {
        $lookup = [];

        foreach ($keys as $key) {
            if ($key instanceof \SimpleXMLElement) {
                $kName = (string) $key['Index'];
            } else {
                $kName = $key->Index;
            }

            if (empty($lookup[$kName])) {
                $lookup[$kName] = [];
            }

            $lookup[$kName][] = $key;
        }

        return $lookup;
    }

    /**
     * Возвращает синтаксис SQL, чтобы добавить ограничение уникальности для ключа таблицы.
     *
     * @param   string  $table  Имя таблицы.
     * @param   mixed   $key    Ключ.
     *
     * @return  string
     */
    protected function getAddUniqueSql(string $table, mixed $key): string {
        if ($key instanceof \SimpleXMLElement) {
            $kName  = (string) $key['Key_name'];
            $kIndex = (string) $key['Index'];
        } else {
            $kName  = $key->Key_name;
            $kIndex = $key->Index;
        }

        $unique = $kIndex . ' UNIQUE (' . $kName . ')';

        return 'ALTER TABLE ' . $this->db->quoteName($table) . ' ADD CONSTRAINT ' . $unique;
    }

    /**
     * Возвращает подробный список последовательностей для таблицы.
     *
     * @param   array  $sequences  Массив объектов, составляющих последовательности для таблицы.
     *
     * @return  array  Массив поиска. array({key name} => array(object, ...))
     *
     */
    protected function getSeqLookup(array $sequences): array {
        $lookup = [];

        foreach ($sequences as $seq) {
            if ($seq instanceof \SimpleXMLElement) {
                $sName = (string) $seq['Name'];
            } else {
                $sName = $seq->Name;
            }

            if (empty($lookup[$sName])) {
                $lookup[$sName] = [];
            }

            $lookup[$sName][] = $seq;
        }

        return $lookup;
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

        if (in_array($tableName, $existingTables)) {
            throw new \RuntimeException('Таблица, которую вы пытаетесь создать, уже существует.');
        }

        $createTableStatement = 'CREATE TABLE ' . $this->db->quoteName($tableName) . ' (';

        foreach ($table->xpath('field') as $field) {
            $createTableStatement .= $this->getColumnSql($field) . ', ';
        }

        $createTableStatement = rtrim($createTableStatement, ', ');
        $createTableStatement .= ');';

        foreach ($table->xpath('sequence') as $seq) {
            $createTableStatement .= $this->getAddSequenceSql($seq) . ';';
            $createTableStatement .= $this->getSetvalSequenceSql($seq) . ';';
        }

        foreach ($table->xpath('key') as $key) {
            if ((($key['is_primary'] == 'f') || ($key['is_primary'] == '')) && (($key['is_unique'] == 't') || ($key['is_unique'] == '1'))) {
                $createTableStatement .= $this->getAddUniqueSql($tableName, $key) . ';';
            } else {
                $createTableStatement .= $this->getAddIndexSql($key) . ';';
            }
        }

        return $createTableStatement;
    }
}
