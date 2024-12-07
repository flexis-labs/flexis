<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Реестр парсеров файлов.
 */
class ParserRegistry {
    /**
     * Карта зарегистрированных парсеров.
     *
     * @var    ParserInterface[]
     */
    private array $parserMap = [];

    /**
     * Зарегистрируйте парсер, переопределив ранее зарегистрированный парсер для данного типа.
     *
     * @param   ParserInterface  $parser  Парсер реестра.
     *
     * @return  void
     */
    public function add(ParserInterface $parser): void {
        $this->parserMap[$parser->getType()] = $parser;
    }

    /**
     * Возвращает парсер для данного типа.
     *
     * @param   string  $type  Тип парсера для получения.
     *
     * @return  ParserInterface
     */
    public function get(string $type): ParserInterface {
        if (!$this->has($type)) {
            throw new \InvalidArgumentException(sprintf('Для типа `%s` не зарегистрирован синтаксический анализатор.', $type));
        }

        return $this->parserMap[$type];
    }

    /**
     * Проверить, зарегистрирован ли парсер для данного типа.
     *
     * @param   string  $type  Тип анализатора для проверки (обычно расширение файла).
     *
     * @return  boolean
     */
    public function has(string $type): bool {
        return isset($this->parserMap[$type]);
    }
}
