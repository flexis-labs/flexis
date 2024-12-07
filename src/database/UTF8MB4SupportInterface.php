<?php

/**
 * Часть пакета Flexis Framework Database.
 */

namespace Flexis\Database;

/**
 * Интерфейс, определяющий драйвер, который поддерживает набор символов MySQL `utf8mb4`.
 */
interface UTF8MB4SupportInterface {
    /**
     * Автоматически понизить версию запроса CREATE TABLE или ALTER TABLE с utf8mb4 (много байтовая UTF-8) до обычного utf8.
     *
     * Используется, когда сервер не поддерживает много байтовую кодировку UTF-8.
     *
     * @param string $query  Запрос на конвертацию.
     *
     * @return  string  Преобразованный запрос.
     */
    public function convertUtf8mb4QueryToUtf8(string $query): string;

    /**
     * Проверяет, поддерживает ли ядро базы данных много байтовую кодировку UTF-8 (utf8mb4).
     *
     * @return  boolean  True если ядро базы данных поддерживает много байтовую кодировку UTF-8.
     */
    public function hasUtf8mb4Support(): bool;
}
