<?php

/**
 * Часть пакета Flexis Http Framework.
 */

namespace Flexis\Http;

use ArrayAccess;
use RuntimeException;

/**
 * Абстрактный транспортный класс.
 */
abstract class AbstractTransport implements TransportInterface {
    /**
     * Варианты клиента.
     *
     * @var    array|ArrayAccess
     */
    protected array|ArrayAccess $options;

    /**
     * Конструктор.
     *
     * @param   array|ArrayAccess  $options  Массив опций клиента.
     * @throws  RuntimeException
     */
    public function __construct(array|ArrayAccess $options = []) {
        if (!static::isSupported()) {
            throw new RuntimeException(sprintf('Транспорт %s не поддерживается в этой среде.', \get_class($this)));
        }

        if (!\is_array($options) && !($options instanceof ArrayAccess)) {
            throw new \InvalidArgumentException(
                'Параметр options должен быть массивом или реализовывать интерфейс ArrayAccess.'
            );
        }

        $this->options = $options;
    }

    /**
     * Возвращает опцию от HTTP-транспорта.
     *
     * @param   string  $key      Имя опции, которую нужно получить.
     * @param   mixed   $default  Значение по умолчанию, если опция не установлена.
     *
     * @return  mixed  Значение опции.
     */
    protected function getOption(string $key, mixed $default = null): mixed {
        return $this->options[$key] ?? $default;
    }

    /**
     * Обрабатывает заголовки из данных ответа транспорта.
     *
     * @param   array  $headers  Заголовки для обработки.
     *
     * @return  array
     */
    protected function processHeaders(array $headers): array {
        $verifiedHeaders = [];

        foreach ($headers as $header) {
            $pos     = strpos($header, ':');
            $keyName = trim(substr($header, 0, $pos));

            if (!isset($verifiedHeaders[$keyName])) {
                $verifiedHeaders[$keyName] = [];
            }

            $verifiedHeaders[$keyName][] = trim(substr($header, ($pos + 1)));
        }

        return $verifiedHeaders;
    }
}
