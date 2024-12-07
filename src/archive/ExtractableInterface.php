<?php

/**
 * Часть пакета Flexis Archive Framework.
 */

namespace Flexis\Archive;

/**
 * Интерфейс класса архива.
 */
interface ExtractableInterface {
    /**
     * Извлекает сжатый файл по заданному пути.
     *
     * @param   string  $archive      Путь к архиву для распаковки.
     * @param   string  $destination  Путь для распаковки архива.
     *
     * @return  boolean  True в случае успеха.
     *
     * @throws  \RuntimeException
     */
    public function extract(string $archive, string $destination): bool;

    /**
     * Проверяет, может ли этот адаптер распаковывать файлы на этом компьютере.
     *
     * @return  boolean  True если поддерживается.
     *
     */
    public static function isSupported(): bool;
}
