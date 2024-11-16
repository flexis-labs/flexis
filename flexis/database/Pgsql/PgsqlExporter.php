<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Pgsql;

use Flexis\Database\DatabaseExporter;

/**
 * Экспортер базы данных PDO PostgreSQL.
 */
class PgsqlExporter extends DatabaseExporter {
    /***Builds the XML data for the tables to export.
     *
     * @return  string  XML-строка
     *
     * @throws  \Exception если произойдет ошибка.
     */
    protected function buildXml(): string {
        $buffer = [];

        $buffer[] = '<?xml version="1.0"?>';
        $buffer[] = '<postgresqldump xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
        $buffer[] = ' <database name="">';

        if ($this->options->withStructure) {
            $buffer = array_merge($buffer, $this->buildXmlStructure());
        }

        if ($this->options->withData) {
            $buffer = array_merge($buffer, $this->buildXmlData());
        }

        $buffer[] = ' </database>';
        $buffer[] = '</postgresqldump>';

        return implode("\n", $buffer);
    }

    /**
     * Создаёт структуру XML для экспорта.
     *
     * @return  array  Массив XML-строк (string).
     *
     * @throws  \Exception если произойдет ошибка.
     */
    protected function buildXmlStructure(): array {
        $buffer = [];

        foreach ($this->from as $table) {
            $table     = $this->getGenericTableName($table);
            $fields    = $this->db->getTableColumns($table, false);
            $keys      = $this->db->getTableKeys($table);
            $sequences = $this->db->getTableSequences($table);

            $buffer[]  = '  <table_structure name="' . $table . '">';

            foreach ($sequences as $sequence) {
                $buffer[] = '   <sequence Name="' . $this->getGenericTableName($sequence->sequence) . '" Schema="' . $sequence->schema . '"' .
                    ' Table="' . $table . '" Column="' . $sequence->column . '" Type="' . $sequence->data_type . '"' .
                    ' Start_Value="' . $sequence->start_value . '" Min_Value="' . $sequence->minimum_value . '"' .
                    ' Max_Value="' . $sequence->maximum_value . '" Last_Value="' . $this->db->getSequenceLastValue($sequence->sequence) . '"' .
                    ' Increment="' . $sequence->increment . '" Cycle_option="' . $sequence->cycle_option . '"' .
                    ' Is_called="' . $this->db->getSequenceIsCalled($sequence->sequence) . '"' .
                    ' />';
            }

            foreach ($fields as $field) {
                $buffer[] = '   <field Field="' . $field->column_name . '" Type="' . $field->type . '" Null="' . $field->null . '"' .
                    ' Default="' . $field->Default . '" Comments="' . $field->comments . '" />';
            }

            foreach ($keys as $key) {
                $buffer[] = '   <key Index="' . $this->getGenericTableName($key->idxName) . '" is_primary="' . $key->isPrimary . '"' .
                    ' is_unique="' . $key->isUnique . '" Key_name="' . $this->db->getNamesKey($table, $key->indKey) . '"' .
                    ' Query=\'' . $key->Query . '\' />';
            }

            $buffer[] = '  </table_structure>';
        }

        return $buffer;
    }

    /**
     * Создаёт данные XML для экспорта.
     *
     * @return  array  Массив XML-строк (string).
     *
     * @throws  \Exception если произойдет ошибка.
     */
    protected function buildXmlData(): array {
        $buffer = [];

        foreach ($this->from as $table) {
            $table   = $this->getGenericTableName($table);
            $fields  = $this->db->getTableColumns($table, false);
            $colblob = [];

            foreach ($fields as $field) {
                if ($field->Type == 'bytea') {
                    $colblob[] = $field->Field;
                }
            }

            $query = $this->db->getQuery(true);
            $query->select($query->quoteName(array_keys($fields)))
                ->from($query->quoteName($table));
            $this->db->setQuery($query);

            $rows = $this->db->loadObjectList();

            if (!count($rows)) {
                continue;
            }

            $buffer[] = '  <table_data name="' . $table . '">';

            foreach ($rows as $row) {
                $buffer[] = '   <row>';

                foreach ($row as $key => $value) {
                    if (!in_array($key, $colblob)) {
                        $buffer[] = '    <field name="' . $key . '">' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '</field>';
                    } else {
                        $buffer[] = '    <field name="' . $key . '">' . stream_get_contents($value) . '</field>';
                    }
                }

                $buffer[] = '   </row>';
            }

            $buffer[] = '  </table_data>';
        }

        return $buffer;
    }

    /**
     * Перед экспортом проверяется, все ли данные и параметры в порядке.
     *
     * @return  $this
     *
     * @throws  \RuntimeException
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
}
