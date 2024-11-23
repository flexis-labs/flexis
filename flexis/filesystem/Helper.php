<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem;

/**
 * Помощник по файловой системе.
 *
 * Содержит функции поддержки файловой системы, особенно потока.
 */
class Helper {
    /**
     * Функция получения размера удаленного файла для потоков.
     *
     * @param   string  $url  Путь к файлу.
     *
     * @return  mixed
     *
     * @link    https://www.php.net/manual/ru/function.filesize.php#71098
     */
    public static function remotefsize(string $url): mixed {
        $sch = parse_url($url, \PHP_URL_SCHEME);

        if (!\in_array($sch, ['http', 'https', 'ftp', 'ftps'], true)) {
            return false;
        }

        if (\in_array($sch, ['http', 'https'], true)) {
            $headers = @ get_headers($url, 1);

            if (!$headers || (!\array_key_exists('Content-Length', $headers))) {
                return false;
            }

            return $headers['Content-Length'];
        }

        if (\in_array($sch, ['ftp', 'ftps'], true)) {
            $server = parse_url($url, \PHP_URL_HOST);
            $port   = parse_url($url, \PHP_URL_PORT);
            $path   = parse_url($url, \PHP_URL_PATH);
            $user   = parse_url($url, \PHP_URL_USER);
            $pass   = parse_url($url, \PHP_URL_PASS);

            if ((!$server) || (!$path)) {
                return false;
            }

            if (!$port) {
                $port = 21;
            }

            if (!$user) {
                $user = 'anonymous';
            }

            if (!$pass) {
                $pass = '';
            }

            $ftpid = null;

            switch ($sch) {
                case 'ftp':
                    $ftpid = @ftp_connect($server, $port);

                    break;

                case 'ftps':
                    $ftpid = @ftp_ssl_connect($server, $port);

                    break;
            }

            if (!$ftpid) {
                return false;
            }

            $login = @ftp_login($ftpid, $user, $pass);

            if (!$login) {
                return false;
            }

            $ftpsize = ftp_size($ftpid, $path);
            ftp_close($ftpid);

            if ($ftpsize == -1) {
                return false;
            }

            return $ftpsize;
        }

        return false;
    }

    /**
     * Быстрый FTP chmod.
     *
     * @param   string   $url   Путь к файлу.
     * @param   integer  $mode  Новые разрешения, заданные в виде восьмеричного значения.
     *
     * @return  integer|boolean
     *
     * @link    https://www.php.net/manual/en/function.ftp-chmod.php
     */
    public static function ftpChmod(string $url, int $mode): int|bool {
        $sch = parse_url($url, \PHP_URL_SCHEME);

        if (($sch != 'ftp') && ($sch != 'ftps')) {
            return false;
        }

        $server = parse_url($url, \PHP_URL_HOST);
        $port   = parse_url($url, \PHP_URL_PORT);
        $path   = parse_url($url, \PHP_URL_PATH);
        $user   = parse_url($url, \PHP_URL_USER);
        $pass   = parse_url($url, \PHP_URL_PASS);

        if ((!$server) || (!$path)) {
            return false;
        }

        if (!$port) {
            $port = 21;
        }

        if (!$user) {
            $user = 'anonymous';
        }

        if (!$pass) {
            $pass = '';
        }

        $ftpid = null;

        switch ($sch) {
            case 'ftp':
                $ftpid = @ftp_connect($server, $port);

                break;

            case 'ftps':
                $ftpid = @ftp_ssl_connect($server, $port);

                break;
        }

        if (!$ftpid) {
            return false;
        }

        $login = @ftp_login($ftpid, $user, $pass);

        if (!$login) {
            return false;
        }

        $res = @ftp_chmod($ftpid, $mode, $path);
        ftp_close($ftpid);

        return $res;
    }

    /**
     * Режимы, требующие операции записи.
     *
     * @return  array
     *
     */
    public static function getWriteModes(): array {
        return ['w', 'w+', 'a', 'a+', 'r+', 'x', 'x+'];
    }

    /**
     * Операции поддержки потоков и фильтров.
     *
     * Возвращает поддерживаемые потоки в дополнение к прямому доступу к файлам.
     * Также включает потоки Flexis и потоки PHP.
     *
     * @return  array  Потоки.
     *
     */
    public static function getSupported(): array {
        static $streams;

        if (!$streams) {
            $streams = array_merge(stream_get_wrappers(), self::getFlexisStreams());
        }

        return $streams;
    }

    /**
     * Возвращает список транспорта.
     *
     * @return  array
     *
     */
    public static function getTransports(): array {
        return stream_get_transports();
    }

    /**
     * Возвращает список фильтров.
     *
     * @return  array
     *
     */
    public static function getFilters(): array {
        return stream_get_filters();
    }

    /**
     * Возвращает список потоков.
     *
     * @return  array
     *
     */
    public static function getFlexisStreams(): array {
        static $streams = [];

        if (!$streams) {
            $files = new \DirectoryIterator(__DIR__ . '/Stream');

            /** @var \DirectoryIterator $file */
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() != 'php') {
                    continue;
                }

                $streams[] = $file->getBasename('.php');
            }
        }

        return $streams;
    }

    /**
     * Определяет, является ли поток потоком Flexis.
     *
     * @param   string  $streamname  Название потока.
     *
     * @return  boolean  True для Flexis Stream.
     *
     */
    public static function isFlexisStream(string $streamname): bool {
        return \in_array($streamname, self::getFlexisStreams());
    }
}
