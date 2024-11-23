<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Интерфейс, описывающий загрузчик языковых файлов.
 */
interface ParserInterface {
    /**
     * Узнать тип погрузчика.
     *
     * @return  string
     */
    public function getType(): string;

    /**
     * Загружает строки из файла.
     *
     * @param   string  $filename  Имя файла.
     *
     * @return  string[]
     * @throws  \RuntimeException при ошибке загрузки/анализа.
     */
    public function loadFile(string $filename): array;
}
