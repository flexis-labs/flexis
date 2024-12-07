<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem;

use Flexis\Filesystem\Exception\FilesystemException;
use UnexpectedValueException;

/**
 * Класс обработки файлов.
 */
class File {
    /**
     * Возвращает расширение имени файла.
     *
     * @param   string  $file  Имя файла.
     *
     * @return  string  Расширение файла.
     */
    public static function getExt(string $file): string {
        $dot = strrpos($file, '.');

        if ($dot === false) {
            return '';
        }

        $ext = substr($file, $dot + 1);

        if (str_contains($ext, '/') || (DIRECTORY_SEPARATOR === '\\' && str_contains($ext, '\\'))) {
            return '';
        }

        return $ext;
    }

    /**
     * Удаляет последнее расширение имени файла.
     *
     * @param   string  $file  Имя файла.
     *
     * @return  string  Имя файла без расширения.
     *
     */
    public static function stripExt(string $file): string {
        return preg_replace('#\.[^.]*$#', '', $file);
    }

    /**
     * Делает имя файла безопасным для использования.
     *
     * @param   string  $file        Имя файла [не полный путь].
     * @param   array   $stripChars  Массив регулярных выражений (по умолчанию удаляются все ведущие точки).
     *
     * @return  string  Продезинфицированная строка.
     *
     */
    public static function makeSafe(string $file, array $stripChars = ['#^\.#']): string {
        if (function_exists('transliterator_transliterate') && function_exists('iconv')) {
            $file = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", transliterator_transliterate('Any-Latin; Latin-ASCII', $file));
        }

        $regex = array_merge(['#(\.){2,}#', '#[^A-Za-zА-Яа-яЁё0-9\.\_\- ]#'], $stripChars);
        $file  = preg_replace($regex, '', $file);
        $file  = rtrim($file, '.');

        return trim($file);
    }

    /**
     * Копирует файл.
     *
     * @param   string        $src         Путь к исходному файлу.
     * @param   string        $dest        Путь к файлу назначения.
     * @param   string|null   $path        Необязательный базовый путь для префикса к именам файлов.
     * @param   boolean       $useStreams  True использовать потоки.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     * @throws  UnexpectedValueException
     */
    public static function copy(string $src, string $dest, ?string $path = null, bool $useStreams = false): bool {
        if ($path) {
            $src  = Path::clean($path . '/' . $src);
            $dest = Path::clean($path . '/' . $dest);
        }

        if (!is_readable($src)) {
            throw new UnexpectedValueException(
                sprintf(
                    "%s: Невозможно найти или прочитать файл: %s",
                    __METHOD__,
                    Path::removeRoot($src)
                )
            );
        }

        if ($useStreams) {
            $stream = Stream::getStream();

            if (!$stream->copy($src, $dest, null, false)) {
                throw new FilesystemException(sprintf('%1$s(%2$s, %3$s): %4$s', __METHOD__, $src, $dest, $stream->getError()));
            }

            self::invalidateFileCache($dest);

            return true;
        }

        if (!@ copy($src, $dest)) {
            throw new FilesystemException(__METHOD__ . ': Не удалось скопировать.');
        }

        self::invalidateFileCache($dest);

        return true;
    }

    /**
     * Удаляет файл или массив файлов.
     *
     * @param   mixed  $file  Имя файла или массив имен файлов.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public static function delete(mixed $file): bool {
        $files = (array) $file;

        foreach ($files as $file) {
            $file     = Path::clean($file);
            $filename = basename($file);

            if (!Path::canChmod($file)) {
                throw new FilesystemException(__METHOD__ . ': Не удалось удалить недоступный файл. ' . $filename);
            }

            @chmod($file, 0777);

            if (!@ unlink($file)) {
                throw new FilesystemException(__METHOD__ . ': Не удалось удалить ' . $filename);
            }

            self::invalidateFileCache($file);
        }

        return true;
    }

    /**
     * Перемещает файл.
     *
     * @param   string   $src         Путь к исходному файлу.
     * @param   string   $dest        Путь к файлу назначения.
     * @param   string   $path        Необязательный базовый путь для префикса к именам файлов.
     * @param   boolean  $useStreams  True использовать потоки.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public static function move(string $src, string $dest, string $path = '', bool $useStreams = false): bool {
        if ($path) {
            $src  = Path::clean($path . '/' . $src);
            $dest = Path::clean($path . '/' . $dest);
        }

        if (!is_readable($src)) {
            return 'Невозможно найти исходный файл.';
        }

        if ($useStreams) {
            $stream = Stream::getStream();

            if (!$stream->move($src, $dest, null, false)) {
                throw new FilesystemException(__METHOD__ . ': ' . $stream->getError());
            }

            self::invalidateFileCache($dest);

            return true;
        }

        if (!@ rename($src, $dest)) {
            throw new FilesystemException(__METHOD__ . ': Переименование не удалось.');
        }

        self::invalidateFileCache($dest);

        return true;
    }

    /**
     * Записывает содержимое в файл.
     *
     * @param   string   $file          Полный путь к файлу.
     * @param   string   $buffer        Буфер для записи.
     * @param   boolean  $useStreams    Используйте потоки.
     * @param   boolean  $appendToFile  Добавить к файлу и не перезаписывать его.
     *
     * @return  boolean
     *
     */
    public static function write(string $file, string $buffer, bool $useStreams = false, bool $appendToFile = false): bool {
        if (\function_exists('set_time_limit')) {
            set_time_limit(ini_get('max_execution_time'));
        }

        if (!file_exists(\dirname($file))) {
            Folder::create(\dirname($file));
        }

        if ($useStreams) {
            $stream = Stream::getStream();
            $stream->set('chunksize', (1024 * 1024));
            $stream->writeFile($file, $buffer, $appendToFile);

            self::invalidateFileCache($file);

            return true;
        }

        $file = Path::clean($file);

        if ($appendToFile === true) {
            $res = \is_int(file_put_contents($file, $buffer, \FILE_APPEND));
        } else {
            $res = \is_int(file_put_contents($file, $buffer));
        }

        self::invalidateFileCache($file);

        return $res;
    }

    /**
     * Перемещает загруженный файл в папку назначения.
     *
     * @param   string   $src         Имя загруженного файла php (временного).
     * @param   string   $dest        Путь (включая имя файла) для перемещения загруженного файла.
     * @param   boolean  $useStreams  True использовать потоки.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public static function upload(string $src, string $dest, bool $useStreams = false): bool {
        $dest = Path::clean($dest);

        $baseDir = \dirname($dest);

        if (!is_dir($baseDir)) {
            Folder::create($baseDir);
        }

        if ($useStreams) {
            $stream = Stream::getStream();

            if (!$stream->upload($src, $dest, null, false)) {
                throw new FilesystemException(sprintf('%1$s(%2$s, %3$s): %4$s', __METHOD__, $src, $dest, $stream->getError()));
            }

            self::invalidateFileCache($dest);

            return true;
        }

        if (is_writable($baseDir) && move_uploaded_file($src, $dest)) {
            if (Path::setPermissions($dest)) {
                self::invalidateFileCache($dest);

                return true;
            }

            throw new FilesystemException(__METHOD__ . ': Не удалось изменить права доступа к файлу.');
        }

        throw new FilesystemException(__METHOD__ . ': Не удалось переместить файл.');
    }

    /**
     * Немедленное аннулирование любой opcache для вновь записанного файла, если существуют функции opcache и если это файл PHP.
     *
     * @param   string  $file  Путь к только что записанному файлу для сброса из opcache.
     *
     * @return void
     */
    public static function invalidateFileCache(string $file): void {
        if (function_exists('opcache_invalidate')) {
            $info = pathinfo($file);

            if (isset($info['extension']) && $info['extension'] === 'php') {
                opcache_invalidate($file, true);
            }
        }
    }
}
