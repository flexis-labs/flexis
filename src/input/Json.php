<?php

/**
 * Часть пакета Flexis Input Framework.
 */

namespace Flexis\Input;

/**
 * Input JSON-класс
 *
 * Этот класс декодирует строку JSON из необработанных данных запроса и делает ее доступной через стандартный интерфейс ввода.
 */
class Json extends Input {
    /**
     * Необработанная строка JSON из запроса.
     *
     * @var    string
     */
    private string $raw;

    /**
     * Конструктор.
     *
     * @param   array|null  $source   Исходные данные (необязательно, по умолчанию — необработанный HTTP-вход, декодированный из JSON).
     * @param   array       $options  Массив параметров конфигурации (необязательно)
     */
    public function __construct(?array $source = null, array $options = []) {
        if ($source === null) {
            $this->raw = file_get_contents('php://input');

            if (empty($this->raw) && isset($GLOBALS['HTTP_RAW_POST_DATA'])) {
                $this->raw = $GLOBALS['HTTP_RAW_POST_DATA'];
            }

            $source = json_decode($this->raw, true);

            if (!\is_array($source)) {
                $source = [];
            }
        }

        parent::__construct($source, $options);
    }

    /**
     * Возвращает необработанную строку JSON из запроса.
     *
     * @return  string  Необработанная строка JSON из запроса.
     */
    public function getRaw(): string {
        return $this->raw;
    }
}
