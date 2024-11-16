<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database\Mysqli;

use Flexis\Database\DatabaseExporter;

/**
 * Экспортер баз данных MySQLi.
 */
class MysqliExporter extends DatabaseExporter {
    /**
     * Создаёт данные XML для экспортируемых таблиц.
     *
     * @return  string  XML-строка
     *
     * @throws  \Exception если произойдет ошибка.
     */
    protected function buildXml(): string {
        $buffer = [];

        $buffer[] = '<?xml version="1.0"?>';
        $buffer[] = '<mysqldump xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
        $buffer[] = ' <database name="">';

        if ($this->options->withStructure) {
            $buffer = array_merge($buffer, $this->buildXmlStructure());
        }

        if ($this->options->withData) {
            $buffer = array_merge($buffer, $this->buildXmlData());
        }

        $buffer[] = ' </database>';
        $buffer[] = '</mysqldump>';

        return implode("\n", $buffer);
    }

    /**
     * Создаёт структуру XML для экспорта.
     *
     * @return  array  Массив XML-строк.
     *
     * @throws  \Exception если произойдет ошибка.
     */
    protected function buildXmlStructure(): array {
        $buffer = [];

        foreach ($this->from as $table) {
            $table = $this->getGenericTableName($table);

            $fields = $this->db->getTableColumns($table, false);
            $keys   = $this->db->getTableKeys($table);

            $buffer[] = '  <table_structure name="' . $table . '">';

            foreach ($fields as $field) {
                $buffer[] = '   <field Field="' . $field->Field . '" Type="' . $field->Type . '" Null="' . $field->Null . '" Key="' .
                    $field->Key . '"' . (isset($field->Default) ? ' Default="' . $field->Default . '"' : '') . ' Extra="' . $field->Extra . '"' .
                    ' />';
            }

            foreach ($keys as $key) {
                $buffer[] = '   <key Table="' . $table . '" Non_unique="' . $key->Non_unique . '" Key_name="' . $key->Key_name . '"' .
                    ' Seq_in_index="' . $key->Seq_in_index . '" Column_name="' . $key->Column_name . '" Collation="' . $key->Collation . '"' .
                    ' Null="' . $key->Null . '" Index_type="' . $key->Index_type . '"' .
                    ' Sub_part="' . $key->Sub_part . '"' .
                    ' Comment="' . htmlspecialchars($key->Comment, \ENT_COMPAT, 'UTF-8') . '"' .
                    ' />';
            }

            $buffer[] = '  </table_structure>';
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
        // Check if the db connector has been set.
        if (!($this->db instanceof MysqliDriver)) {
            throw new \RuntimeException('Неправильный тип подключения к базе данных.');
        }

        // Check if the tables have been specified.
        if (empty($this->from)) {
            throw new \RuntimeException('Ошибка: таблицы не указаны.');
        }

        return $this;
    }
}
