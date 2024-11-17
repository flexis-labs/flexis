<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Интерфейс, описывающий анализатор языковых файлов, способный отлаживать файл.
 */
interface DebugParserInterface extends ParserInterface {
    /**
     * Разберите файл и проверьте его содержимое на наличие допустимой структуры.
     *
     * @param   string  $filename  Имя файла.
     *
     * @return  array  Массив, содержащий список ошибок.
     */
    public function debugFile(string $filename): array;
}
