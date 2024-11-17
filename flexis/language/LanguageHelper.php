<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Вспомогательный класс для языкового пакета
 */
class LanguageHelper {
    /**
     * Проверяет, существует ли язык.
     *
     * Это простая и быстрая проверка каталога, в котором должны находиться языковые файлы для данного пользователя.
     *
     * @param   string  $lang      Язык для проверки.
     * @param   string  $basePath  Каталог для проверки указанного языка.
     *
     * @return  boolean  True если язык существует.
     */
    public function exists(string $lang, string $basePath): bool {
        return is_dir($this->getLanguagePath($basePath, $lang));
    }

    /**
     * Возвращает ассоциативный массив, содержащий метаданные.
     *
     * @param   string  $lang  Название языка.
     * @param   string  $path  Путь к языковой папке.
     *
     * @return  array|null  Если $lang существует, верните пару ключ/значение с метаданными языка, в противном случае верните NULL.
     */
    public function getMetadata(string $lang, string $path): ?array {
        $path = $this->getLanguagePath($path, $lang);
        $file = $lang . '.xml';

        $result = null;

        if (is_file("$path/$file")) {
            $result = $this->parseXMLLanguageFile("$path/$file");
        }

        if (empty($result)) {
            return null;
        }

        return $result;
    }

    /**
     * Возвращает список известных языков для региона.
     *
     * @param   string  $basePath  Базовый путь для использования
     *
     * @return  array  key/value пара с языковым файлом и настоящим именем.
     */
    public function getKnownLanguages(string $basePath): array {
        return $this->parseLanguageFiles($this->getLanguagePath($basePath));
    }

    /**
     * Возвращает путь к языку
     *
     * @param   string  $basePath  Базовый путь, который нужно использовать.
     * @param   string  $language  Языковой тег.
     *
     * @return  string  Путь к языковой папке
     */
    public function getLanguagePath(string $basePath, string $language = ''): string {
        $dir = $basePath . '/language';

        if (!empty($language)) {
            $dir .= '/' . $language;
        }

        return $dir;
    }

    /**
     * Ищет языковые каталоги в определенном базовом каталоге.
     *
     * @param   string  $dir  каталог файлов.
     *
     * @return  array  Массив, содержащий найденные языки в виде пар [имя файла] => [настоящие имена].
     */
    public function parseLanguageFiles(string $dir = ''): array {
        $languages = [];

        foreach (glob($dir . '/*', GLOB_NOSORT | GLOB_ONLYDIR) as $directory) {
            // Только каталоги с форматом языкового кода.
            if (preg_match('#/[a-z]{2,3}-[A-Z]{2}$#', $directory)) {
                $dirPathParts = pathinfo($directory);
                $file         = $directory . '/' . $dirPathParts['filename'] . '.xml';

                if (!is_file($file)) {
                    continue;
                }

                try {
                    if ($metadata = $this->parseXMLLanguageFile($file)) {
                        $languages = array_replace($languages, [$dirPathParts['filename'] => $metadata]);
                    }
                } catch (\RuntimeException $e) {
                }
            }
        }

        return $languages;
    }

    /**
     * Анализ XML-файла на предмет информации о языке.
     *
     * @param   string  $path  Путь к XML-файлам.
     *
     * @return  array|null  Массив, содержащий найденные метаданные в виде пары ключ => значение.
     *
     * @throws  \RuntimeException
     */
    public function parseXmlLanguageFile(string $path): ?array {
        if (!is_readable($path)) {
            throw new \RuntimeException('Файл не найден или не читается.');
        }

        $xml = simplexml_load_file($path);

        if (!$xml) {
            return null;
        }

        if ($xml->getName() != 'metafile') {
            return null;
        }

        $metadata = [];

        /** @var \SimpleXMLElement $child */
        foreach ($xml->metadata->children() as $child) {
            $metadata[$child->getName()] = (string) $child;
        }

        return $metadata;
    }
}
