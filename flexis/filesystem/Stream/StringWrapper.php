<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem\Stream;

use Flexis\Filesystem\Support\StringController;

/**
 * Оболочка строкового потока.
 *
 * Этот класс позволяет вам использовать строку PHP так же, как вы обычно используете обычную оболочку потока.
 */
class StringWrapper {
    /**
     * Текущая строка.
     *
     * @var   string
     */
    protected string $currentString;

    /**
     * Путь.
     *
     * @var   string
     */
    protected string $path;

    /**
     * Не используется.
     *
     * @var   string
     */
    protected string $mode;

    /**
     * Не используется.
     *
     * @var   string
     */
    protected string $options;

    /**
     * Не используется.
     *
     * @var   string
     */
    protected string $openedPath;

    /**
     * Текущая позиция.
     *
     * @var   integer
     */
    protected int $pos;

    /**
     * Длина строки.
     *
     * @var   string
     */
    protected string $len;

    /**
     * Статистика по файлу.
     *
     * @var    array
     * @link   https://www.php.net/manual/ru/function.stat.php
     */
    protected array $stat;

    /**
     * Метод открытия файла или URL-адреса.
     *
     * @param   string   $path        Путь потока.
     * @param   string   $mode        Не используется.
     * @param   integer  $options     Не используется.
     * @param   ?string  $openedPath  Не используется.
     *
     * @return  boolean
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool {
        $refPath = StringController::getRef(str_replace('string://', '', $path));

        $this->currentString = &$refPath;

        if ($this->currentString) {
            $this->len  = \strlen($this->currentString);
            $this->pos  = 0;
            $this->stat = $this->url_stat($path, 0);

            return true;
        }

        return false;
    }

    /**
     * Метод получения информации из файлового ресурса.
     *
     * @return  array
     *
     * @link    https://www.php.net/manual/en/streamwrapper.stream-stat.php
     */
    public function stream_stat(): array {
        return $this->stat;
    }

    /**
     * Получение информации о файле.
     *
     * @param   string   $path   Путь к файлу или URL-адрес для статистики.
     * @param   integer  $flags  Дополнительные флаги, установленные API потоков.
     *
     * @return  array
     *
     * @link    https://www.php.net/manual/ru/streamwrapper.url-stat.php
     */
    public function url_stat(string $path, int $flags = 0): array {
        $now     = time();
        $refPath = StringController::getRef(str_replace('string://', '', $path));
        $string  = &$refPath;
        return [
            'dev'     => 0,
            'ino'     => 0,
            'mode'    => 0,
            'nlink'   => 1,
            'uid'     => 0,
            'gid'     => 0,
            'rdev'    => 0,
            'size'    => \strlen($string),
            'atime'   => $now,
            'mtime'   => $now,
            'ctime'   => $now,
            'blksize' => '512',
            'blocks'  => ceil(\strlen($string) / 512),
        ];
    }

    /**
     * Метод для чтения заданного количества байтов,
     * начиная с текущей позиции и перемещаясь до конца строки,
     * определенной текущей позицией плюс заданное число.
     *
     * @param   integer  $count  Должны быть возвращены байты данных из текущей позиции.
     *
     * @return  string
     *
     * @link    https://www.php.net/manual/ru/streamwrapper.stream-read.php
     */
    public function stream_read(int $count): string {
        $result = substr($this->currentString, $this->pos ?? 0, $count);
        $this->pos += $count;

        return $result;
    }

    /**
     * Потоковая запись, всегда возвращающая false.
     *
     * @param   string  $data  Данные для записи.
     *
     * @return  boolean
     * @note    Обновление строки не поддерживается.
     */
    public function stream_write(string $data): bool {
        return false;
    }

    /**
     * Метод получения текущей позиции.
     *
     * @return  integer  Позиция.
     */
    public function stream_tell(): int {
        return $this->pos;
    }

    /**
     * Конец полевой проверки.
     *
     * @return  boolean  True если в конце поля.
     */
    public function stream_eof(): bool {
        if ($this->pos >= $this->len) {
            return true;
        }

        return false;
    }

    /**
     * Смещение потока.
     *
     * @param   integer  $offset  Начальное смещение.
     * @param   integer  $whence  SEEK_SET, SEEK_CUR, SEEK_END
     *
     * @return  boolean
     */
    public function stream_seek(int $offset, int $whence): bool {
        if ($offset > $this->len) {
            return false;
        }

        switch ($whence) {
            case \SEEK_SET:
                $this->pos = $offset;

                break;

            case \SEEK_CUR:
                if (($this->pos + $offset) > $this->len) {
                    return false;
                }

                $this->pos += $offset;

                break;

            case \SEEK_END:
                $this->pos = $this->len - $offset;

                break;
        }

        return true;
    }

    /**
     * Потоковая очистка всегда возвращает true.
     *
     * @return  boolean
     * @note    Хранение данных не поддерживается.
     */
    public function stream_flush(): bool {
        return true;
    }
}

// phpcs:disable PSR1.Files.SideEffects
if (!stream_wrapper_register('string', '\\Flexis\\Filesystem\\Stream\\StringWrapper')) {
    die('\\Flexis\\Filesystem\\Stream\\StringWrapper Ошибка регистрации оболочки.');
}
// phpcs:enable PSR1.Files.SideEffects
