<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem;

/**
 * Обработчик потока общего буфера.
 *
 * Этот класс предоставляет общий буферный поток.
 * Его можно использовать для хранения/извлечения/манипулирования строковыми буферами
 * с помощью стандартных методов ввода-вывода файловой системы PHP.
 */
class Buffer {
    /**
     * Позиция потока.
     *
     * @var    integer
     */
    public int $position = 0;

    /**
     * Имя буфера.
     *
     * @var    string
     */
    public string $name;

    /**
     * Буферный хэш.
     *
     * @var    array
     */
    public array $buffers = [];

    /**
     * Открывает файл или URL.
     *
     * @param   string   $path        URL-адрес, который был передан.
     * @param   string   $mode        Режим, используемый для открытия файла @see fopen.
     * @param   integer  $options     Флаги, используемые API, могут быть STREAM_USE_PATH и STREAM_REPORT_ERRORS.
     * @param   ?string  $openedPath  Полный путь к ресурсу. Используется с опцией STREAN_USE_PATH.
     *
     * @return  boolean
     *
     * @see     streamWrapper::stream_open
     * @link    https://www.php.net/manual/ru/streamwrapper.stream-open.php
     */
    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool {
        $url                        = parse_url($path);
        $this->name                 = $url['host'];
        $this->buffers[$this->name] = null;
        $this->position             = 0;

        return true;
    }

    /**
     * Чтение из потока.
     *
     * @param   integer  $count  Сколько байт данных из текущей позиции должно быть возвращено.
     *
     * @return  string|boolean   Данные из потока размером до указанного количества байт.
     *                           Все данные, если общее количество байт в потоке меньше, чем $count.
     *                           False, если поток пуст.
     *
     * @see     streamWrapper::stream_read
     * @link    https://www.php.net/manual/ru/streamwrapper.stream-read.php
     */
    public function stream_read(int $count): string|bool {
        $ret = substr($this->buffers[$this->name], $this->position, $count);
        $this->position += \strlen($ret);

        return !empty($ret) ? $ret : false;
    }

    /**
     * Запись в поток.
     *
     * @param   string  $data  Данные для записи в поток.
     *
     * @return  integer
     *
     * @see     streamWrapper::stream_write
     * @link    https://www.php.net/manual/ru/streamwrapper.stream-write.php
     */
    public function stream_write(string $data): int {
        $left  = substr($this->buffers[$this->name], 0, $this->position);
        $right = substr($this->buffers[$this->name], $this->position + \strlen($data));

        $this->buffers[$this->name] = $left . $data . $right;

        $this->position += \strlen($data);

        return \strlen($data);
    }

    /**
     * Определение текущей позиции потока.
     *
     * @return  integer
     *
     * @see     streamWrapper::stream_tell
     * @link    https://www.php.net/manual/ru/streamwrapper.stream-tell.php
     */
    public function stream_tell(): int {
        return $this->position;
    }

    /**
     * Проверяет достижение конца файла по файловому указателю.
     *
     * @return  boolean  True если указатель находится в конце потока.
     *
     * @see     streamWrapper::stream_eof
     * @link    https://www.php.net/manual/ru/streamwrapper.stream-eof.php
     */
    public function stream_eof(): bool {
        return $this->position >= \strlen($this->buffers[$this->name]);
    }

    /**
     * Перемещает положение файлового указателя в потоке.
     *
     * @param   integer  $offset  Смещение в байтах.
     * @param   integer  $whence  Позиция, к которой добавляется смещение.
     *                            Возможные варианты: SEEK_SET, SEEK_CUR и SEEK_END.
     *
     * @return  boolean  True если обновлено.
     *
     * @see     streamWrapper::stream_seek
     * @link    https://www.php.net/manual/ru/streamwrapper.stream-seek.php
     */
    public function stream_seek(int $offset, int $whence): bool {
        switch ($whence) {
            case \SEEK_SET:
                if ($offset < \strlen($this->buffers[$this->name]) && $offset >= 0) {
                    $this->position = $offset;

                    return true;
                }

                return false;

            case \SEEK_CUR:
                if ($offset >= 0) {
                    $this->position += $offset;

                    return true;
                }

                return false;

            case \SEEK_END:
                if (\strlen($this->buffers[$this->name]) + $offset >= 0) {
                    $this->position = \strlen($this->buffers[$this->name]) + $offset;

                    return true;
                }

                return false;

            default:
                return false;
        }
    }
}

// Зарегистрировать поток
// phpcs:disable PSR1.Files.SideEffects
stream_wrapper_register('buffer', 'Flexis\\Filesystem\\Buffer');
// phpcs:enable PSR1.Files.SideEffects
