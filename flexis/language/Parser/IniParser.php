<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language\Parser;

use Flexis\Language\DebugParserInterface;

/**
 * Анализатор языковых файлов для INI-файлов.
 */
class IniParser implements DebugParserInterface {
    /**
     * Разберите файл и проверьте его содержимое на наличие допустимой структуры.
     *
     * @param   string  $filename  Имя файла.
     *
     * @return  array  Массив, содержащий список ошибок.
     */
    public function debugFile(string $filename): array {
        $blacklist = ['YES', 'NO', 'NULL', 'FALSE', 'ON', 'OFF', 'NONE', 'TRUE'];
        $errors    = [];

        foreach (new \SplFileObject($filename) as $lineNumber => $line) {
            if ($lineNumber == 0) {
                $line = str_replace("\xEF\xBB\xBF", '', $line);
            }

            $line = trim($line);

            if (!\strlen($line) || $line[0] == ';') {
                continue;
            }

            if (preg_match('#^\[[^\]]*\](\s*;.*)?$#', $line)) {
                continue;
            }

            $realNumber = $lineNumber + 1;

            if (strpos($line, '_QQ_') !== false) {
                $errors[] = 'Устаревшая константа `_QQ_` используется в строке. ' . $realNumber;

                continue;
            }

            if (substr_count($line, '"') % 2 != 0) {
                $errors[] = 'Нечетное количество котировок строке ' . $realNumber;

                continue;
            }

            if (!preg_match('#^[A-Z][A-Z0-9_\*\-\.]*\s*=\s*".*"(\s*;.*)?$#', $line)) {
                $errors[] = 'Языковой ключ не соответствует требуемому формату в строке ' . $realNumber;

                continue;
            }

            $key = strtoupper(trim(substr($line, 0, strpos($line, '='))));

            if (\in_array($key, $blacklist)) {
                $errors[] = 'Языковой ключ "' . $key . '" в черном списке в строке ' . $realNumber;

                $errors[] = $realNumber;
            }
        }

        return $errors;
    }

    /**
     * Возвращает тип парсера.
     *
     * @return  string
     */
    public function getType(): string {
        return 'ini';
    }

    /**
     * Загружает строки из файла.
     *
     * @param   string  $filename  Имя файла.
     *
     * @return  string[]
     * @throws  \RuntimeException при ошибке загрузки/анализа.
     */
    public function loadFile(string $filename): array {
        $result = @parse_ini_file($filename);

        if ($result === false) {
            $lastError = error_get_last();

            $errorMessage = $lastError['message'] ?? 'Неизвестная ошибка';

            throw new \RuntimeException(
                sprintf('Не удалось обработать файл `%s`: %s', $errorMessage, $filename)
            );
        }

        return $result;
    }
}
