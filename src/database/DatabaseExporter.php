<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

use Exception;
use InvalidArgumentException;
use stdClass;

/**
 * Класс экспортера базы данных Flexis Framework
 */
abstract class DatabaseExporter {
    /**
     * Тип выходного формата.
     *
     * @var    string
     */
    protected string $asFormat = 'xml';

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
     * Источники ввода массива (имена таблиц).
     *
     * @var    string[]
     */
    protected array $from = [];

    /**
     * Массив опций для экспортера.
     *
     * @var    stdClass
     */
    protected stdClass $options;

    /**
     * Конструктор.
     *
     * Устанавливает параметры по умолчанию для экспортера.
     *
     */
    public function __construct() {
        $this->options = new stdClass();

        $this->withStructure();
        $this->withData();

        $this->asXml();
    }

    /**
     * Магический метод для экспорта данных в строку.
     *
     * @return  string
     *
     */
    public function __toString() {
        $buffer = '';

        try {
            $this->check();

            switch ($this->asFormat) {
                case 'xml':
                default:
                    $buffer = $this->buildXml();

                    break;
            }
        } catch (Exception $e) {
            // ничего не делать
        }

        return $buffer;
    }

    /**
     * Устанавливает параметр вывода для экспортера в формат XML.
     *
     * @return  $this
     *
     */
    public function asXml(): static {
        $this->asFormat = 'xml';
        return $this;
    }

    /**
     * Создаёт данные XML для экспортируемых таблиц.
     *
     * @return  string  XML-строка
     *
     * @throws  Exception если произойдет ошибка.
     */
    abstract protected function buildXml(): string;

    /**
     * Создаёт структуру XML для экспорта.
     *
     * @return  array  Массив XML-строк (строк).
     *
     * @throws  Exception если произойдет ошибка.
     */
    abstract protected function buildXmlStructure(): array;

    /**
     * Перед экспортом проверяется, все ли данные и параметры в порядке.
     *
     * @return  $this
     *
     * @throws  Exception если возникла ошибка.
     */
    abstract public function check(): static;

    /**
     * Задает список имен таблиц для экспорта.
     *
     * @param string|string[] $from  Имя одной таблицы или массива имен таблиц для экспорта.
     *
     * @return  $this
     *
     * @throws  InvalidArgumentException
     */
    public function from(array|string $from): static {
        if (\is_string($from)) {
            $this->from = [$from];
        } elseif (\is_array($from)) {
            $this->from = $from;
        } else {
            throw new InvalidArgumentException('Экспортеру требуется либо одно имя таблицы, либо массив имен таблиц.');
        }

        return $this;
    }

    /**
     * Возвращает общее имя таблицы, преобразовав префикс базы данных в строку с подстановочными знаками.
     *
     * @param string $table  Имя таблицы.
     *
     * @return  string  Имя таблицы с префиксом базы данных, замененным на #__.
     *
     */
    protected function getGenericTableName(string $table): string {
        $prefix = $this->db->getPrefix();
        return preg_replace("|^$prefix|", '#__', $table);
    }

    /**
     * Устанавливает соединитель базы данных, который будет использоваться для импорта структуры и/или данных.
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
     * Устанавливает внутреннюю опцию для экспорта структуры входных таблиц.
     *
     * @param boolean $setting  Значение true — экспортировать структуру, значение false — нет.
     *
     * @return  $this
     *
     */
    public function withStructure(bool $setting = true): static {
        $this->options->withStructure = (bool) $setting;
        return $this;
    }

    /**
     * Устанавливает внутреннюю опцию для экспорта данных входных таблиц.
     *
     * @param boolean $setting  Значение true для экспорта данных и значение false для отказа.
     *
     * @return  $this
     */
    public function withData(bool $setting = false): static {
        $this->options->withData = $setting;
        return $this;
    }

    /**
     * Создаёт данные XML для экспорта.
     *
     * @return  array  Массив XML-строк (строк).
     * @throws  Exception если произойдет ошибка.
     */
    protected function buildXmlData(): array {
        $buffer = [];
        foreach ($this->from as $table) {
            $table   = $this->getGenericTableName($table);
            $fields  = $this->db->getTableColumns($table, false);
            $colblob = [];

            foreach ($fields as $field) {
                if ($field->Type == 'mediumblob') {
                    $colblob[] = $field->Field;
                }
            }

            $this->db->setQuery(
                $this->db->getQuery(true)
                    ->select($this->db->quoteName(array_keys($fields)))
                    ->from($this->db->quoteName($table))
            );

            $rows = $this->db->loadObjectList();

            if (!count($rows)) {
                continue;
            }

            $buffer[] = '  <table_data name="' . $table . '">';
            foreach ($rows as $row) {
                $buffer[] = '   <row>';
                foreach ($row as $key => $value) {
                    if (!in_array($key, $colblob)) {
                        if (is_null($value)) {
                            $buffer[] = '    <field name="' . $key . '" value_is_null></field>';
                        } else {
                            $buffer[] = '    <field name="' . $key . '">' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8') . '</field>';
                        }
                    } else {
                        $buffer[] = '    <field name="' . $key . '">' . base64_encode($value) . '</field>';
                    }
                }
                $buffer[] = '   </row>';
            }
            $buffer[] = '  </table_data>';
        }

        return $buffer;
    }
}
