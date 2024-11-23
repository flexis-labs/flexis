<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem;

use Flexis\Filesystem\Exception\FilesystemException;

/**
 * Гибкий потоковый класс.
 *
 * Потоковый интерфейс Flexis предназначен для обработки файлов как потоков, 
 * тогда как устаревший статический класс File обрабатывал файлы довольно атомарным образом.
 *
 * Этот класс придерживается операций-оболочек потока:
 *
 * @link   https://www.php.net/manual/ru/function.stream-get-wrappers.php
 * @link   https://www.php.net/manual/ru/intro.stream.php Руководство по PHP-потоку.
 * @link   https://www.php.net/manual/ru/wrappers.php Обертки потоков.
 * @link   https://www.php.net/manual/ru/filters.php Потоковые фильтры.
 * @link   https://www.php.net/manual/ru/transports.php Транспорты сокетов (используется некоторыми опциями, особенно HTTP-прокси).
 */
class Stream {
    /**
     * Файловый режим.
     *
     * @var    integer
     */
    protected int $filemode = 0644;

    /**
     * Режим каталога.
     *
     * @var    integer
     */
    protected int $dirmode = 0755;

    /**
     * Размер чанка по умолчанию.
     *
     * @var    integer
     */
    protected int $chunksize = 8192;

    /**
     * Имя файла.
     *
     * @var    string
     */
    protected string $filename;

    /**
     * Префикс соединения для записи.
     *
     * @var    string
     */
    protected string $writeprefix = '';

    /**
     * Префикс соединения для чтения.
     *
     * @var    string
     */
    protected string $readprefix = '';

    /**
     * Читать метод обработки
     *
     * @var    string  gz, bz, f
     * Если схема обнаружена, fopen будет использоваться по умолчанию.
     * Чтобы использовать сжатие сетевого потока, используйте фильтр.
     */
    protected string $processingmethod = 'f';

    /**
     * Фильтры, примененные к текущему потоку.
     *
     * @var    array
     */
    protected array $filters = [];

    /**
     * Дескриптор файла.
     *
     * @var    resource
     */
    protected $fh;

    /**
     * Размер файла.
     *
     * @var    integer
     */
    protected int $filesize;

    /**
     * Контекст, который будет использоваться при открытии соединения.
     *
     * @var    string
     */
    protected string $context;

    /**
     * Опции контекста; используется для восстановления контекста.
     *
     * @var    array
     */
    protected array $contextOptions = [];

    /**
     * Режим, в котором был открыт файл.
     *
     * @var    string
     */
    protected string $openmode;

    /**
     * Конструктор.
     *
     * @param   string  $writeprefix  Префикс потока (необязательно). В отличие от контант PATH_*, здесь есть последний разделитель пути!
     * @param   string  $readprefix   Префикс чтения (необязательно).
     * @param   array   $context      Параметры контекста (необязательно).
     *
     */
    public function __construct(string $writeprefix = '', string $readprefix = '', array $context = []) {
        $this->writeprefix    = $writeprefix;
        $this->readprefix     = $readprefix;
        $this->contextOptions = $context;
        $this->_buildContext();
    }

    /**
     * Деструктор
     *
     */
    public function __destruct() {
        if ($this->fh) {
            @$this->close();
        }
    }

    /**
     * Создаёт новый объект потока с соответствующим префиксом.
     *
     * @param   boolean       $usePrefix  Префикс соединений для записи.
     * @param   string|null   $ua         Пользовательский агент UA для использования.
     * @param   boolean       $uamask     Маскирование пользовательского агента (префикс Mozilla).
     *
     * @return  Stream
     *
     * @see     Stream
     */
    public static function getStream(bool $usePrefix = true, ?string $ua = null, bool $uamask = false): self {
        $context = [];

        $context['http']['user_agent'] = $ua ?: 'Flexis Framework Stream';

        if ($usePrefix) {
            return new static(PATH_ROOT . '/', PATH_ROOT, $context);
        }

        return new static('', '', $context);
    }

    /**
     * Общие операции с файлами.
     *
     * Откройте поток, используя некоторые хитрости отложенной загрузки.
     *
     * @param   string    $filename              Имя файла.
     * @param   string    $mode                  Строка режима для использования.
     * @param   boolean   $useIncludePath        Используйте путь включения PHP.
     * @param   resource  $context               Контекст для использования при открытии.
     * @param   boolean   $usePrefix             Используйте префикс для открытия файла.
     * @param   boolean   $relative              Имя файла — это относительный путь (если false, PATH_ROOT удаляется, чтобы сделать его относительным).
     * @param   boolean   $detectprocessingmode  Определите метод обработки файла и используйте соответствующую функцию для автоматической обработки вывода.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public function open(
        string $filename,
        string $mode = 'r',
        bool $useIncludePath = false,
        $context = null,
        bool $usePrefix = false,
        bool $relative = false,
        bool $detectprocessingmode = false
    ): bool {

        $filename = $this->_getFilename($filename, $mode, $usePrefix, $relative);

        if (!$filename) {
            throw new FilesystemException('Имя файла не установлено.');
        }

        $this->filename = $filename;
        $this->openmode = $mode;

        $url = parse_url($filename);

        if (isset($url['scheme'])) {
            $scheme = ucfirst($url['scheme']);

            if ($scheme === 'String') {
                $scheme = 'StringWrapper';
            }

            if (Helper::isFlexisStream($scheme)) {
                require_once __DIR__ . '/Stream/' . $scheme . '.php';
            }

            $this->processingmethod = 'f';
        } elseif ($detectprocessingmode) {
            $ext = strtolower(pathinfo($this->filename, \PATHINFO_EXTENSION));

            $this->processingmethod = match ($ext) {
                'tgz', 'gz', 'gzip' => 'gz',
                'tbz2', 'bz2', 'bzip2' => 'bz',
                default => 'f',
            };
        }

        error_clear_last();

        switch ($this->processingmethod) {
            case 'gz':
                $this->fh = gzopen($filename, $mode, $useIncludePath);
                break;

            case 'bz':
                $this->fh = bzopen($filename, $mode);
                break;
            case 'f':
            default:
                if ($context) {
                    $this->fh = @fopen($filename, $mode, $useIncludePath, $context);
                } elseif ($this->context) {
                    $this->fh = @fopen($filename, $mode, $useIncludePath, $this->context);
                } else {
                    $this->fh = @fopen($filename, $mode, $useIncludePath);
                }

                break;
        }

        if (!$this->fh) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => sprintf('Неизвестная ошибка при открытии файла %s.', $filename),
                ];
            }

            throw new FilesystemException($error['message']);
        }

        return true;
    }

    /**
     * Попытка закрыть дескриптор файла.
     *
     * Вернет false, если это не удалось, и true в случае успеха.
     * Если файл не открыт, система вернет true, эта функция также уничтожает дескриптор файла.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public function close(): bool {
        if (!$this->fh) {
            throw new FilesystemException('Файл не открыт.');
        }

        error_clear_last();

        $res = match ($this->processingmethod) {
            'gz' => gzclose($this->fh),
            'bz' => bzclose($this->fh),
            default => fclose($this->fh),
        };

        if (!$res) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => 'Невозможно закрыть поток.',
                ];
            }

            throw new FilesystemException($error['message']);
        }

        $this->fh = null;

        if ($this->openmode[0] == 'w') {
            $this->chmod();
        }

        return true;
    }

    /**
     * Определить, находимся ли мы в конце файла потока.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public function eof(): bool {
        if (!$this->fh) {
            throw new FilesystemException('Файл не открыт.');
        }

        error_clear_last();

        $res = match ($this->processingmethod) {
            'gz' => gzeof($this->fh),
            default => feof($this->fh),
        };

        $error = error_get_last();

        if ($error !== null && $error['message'] !== '') {
            throw new FilesystemException($error['message']);
        }

        return $res;
    }

    /**
     * Возвращает размер файла по пути
     *
     * @return  integer|boolean
     *
     * @throws  FilesystemException
     */
    public function filesize(): int|bool {
        if (!$this->filename) {
            throw new FilesystemException('Файл не открыт.');
        }

        error_clear_last();

        $res = @filesize($this->filename);

        if (!$res) {
            $res = Helper::remotefsize($this->filename);
        }

        if (!$res) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => 'Не удалось получить размер файла. Это может работать не для всех потоков.',
                ];
            }

            throw new FilesystemException($error['message']);
        }

        $this->filesize = $res;

        return $this->filesize;
    }

    /**
     * Возвращает строку из источника потока.
     *
     * @param   integer  $length  Количество байтов (необязательно) для чтения.
     *
     * @return  string
     *
     * @throws  FilesystemException
     */
    public function gets(int $length = 0): string {
        if (!$this->fh) {
            throw new FilesystemException('Файл не открыт.');
        }

        error_clear_last();

        $res = match ($this->processingmethod) {
            'gz' => $length ? gzgets($this->fh, $length) : gzgets($this->fh),
            default => $length ? fgets($this->fh, $length) : fgets($this->fh),
        };

        if (!$res) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => 'Невозможно прочитать из потока.',
                ];
            }

            throw new FilesystemException($error['message']);
        }

        return $res;
    }

    /**
     * Чтение файла.
     *
     * Обрабатывает потоки пользовательского пространства соответствующим образом, иначе любое чтение вернет 8192.
     *
     * @param   integer  $length  Длина данных для чтения.
     *
     * @return  string
     *
     * @link    https://www.php.net/manual/ru/function.fread.php
     * @throws  FilesystemException
     */
    public function read(int $length = 0): string {
        if (!$this->fh) {
            throw new FilesystemException('Файл не открыт.');
        }

        if (!$this->filesize && !$length) {
            $this->filesize();

            if (!$this->filesize) {
                $length = -1;
            } else {
                $length = $this->filesize;
            }
        }

        $retval = false;

        error_clear_last();

        $remaining = $length;

        do {
            $res = match ($this->processingmethod) {
                'bz' => ($remaining > 0) ? bzread($this->fh, $remaining) : bzread($this->fh, $this->chunksize),
                'gz' => ($remaining > 0) ? gzread($this->fh, $remaining) : gzread($this->fh, $this->chunksize),
                default => ($remaining > 0) ? fread($this->fh, $remaining) : fread($this->fh, $this->chunksize),
            };

            if (!$res) {
                $error = error_get_last();

                if ($error === null || $error['message'] === '') {
                    $error = [
                        'message' => 'Невозможно прочитать из потока.',
                    ];
                }

                throw new FilesystemException($error['message']);
            }

            if (!$retval) {
                $retval = '';
            }

            $retval .= $res;

            if (!$this->eof()) {
                $len = \strlen($res);
                $remaining -= $len;
            } else {
                $remaining = 0;
                $length    = \strlen($retval);
            }
        } while ($remaining || !$length);

        return $retval;
    }

    /**
     * Искать файл.
     *
     * Примечание: возвращаемое значение отличается от значения fseek.
     *
     * @param   integer  $offset  Смещение для использования при поиске.
     * @param   integer  $whence  Режим поиска для использования.
     *
     * @return  boolean  True при успехе, false при неудаче
     *
     * @link    https://www.php.net/manual/ru/function.fseek.php
     * @throws  FilesystemException
     */
    public function seek(int $offset, int $whence = \SEEK_SET): bool {
        if (!$this->fh) {
            throw new FilesystemException('Файл не открыт.');
        }

        error_clear_last();

        $res = match ($this->processingmethod) {
            'gz' => gzseek($this->fh, $offset, $whence),
            default => fseek($this->fh, $offset, $whence),
        };

        if ($res == -1) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => 'Невозможно выполнить поиск в потоке.',
                ];
            }

            throw new FilesystemException($error['message']);
        }

        return true;
    }

    /**
     * Возвращает текущую позицию указателя чтения/записи файла.
     *
     * @return  integer
     *
     * @throws  FilesystemException
     */
    public function tell(): int {
        if (!$this->fh) {
            throw new FilesystemException('Файл не открыт.');
        }

        error_clear_last();

        $res = match ($this->processingmethod) {
            'gz' => gztell($this->fh),
            default => ftell($this->fh),
        };

        if ($res === false) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => 'Невозможно определить текущую позицию в потоке.',
                ];
            }

            throw new FilesystemException($error['message']);
        }

        return $res;
    }

    /**
     * Запись файла.
     *
     * Хотя эта функция принимает ссылку, базовая функция fwrite выполнит копирование!
     * Это примерно удвоит выделение памяти для любой операции записи.
     * Указание chunked позволяет обойти эту проблему, записывая только фрагменты определенного размера. 
     * По умолчанию это 8192, что является разумным числом, которое можно использовать большую часть времени 
     * (измените значение по умолчанию с помощью Stream::set('chunksize', newsize);)
     * 
     * Примечание. Это не поддерживает запись gzip/bzip2, как чтение.
     *
     * @param   string   $string  Ссылка на строку для записи.
     * @param   integer  $length  Длина строки для записи.
     * @param   integer  $chunk   Размер блоков для записи.
     *
     * @return  boolean
     *
     * @link    https://www.php.net/manual/ru/function.fwrite.php
     * @throws  FilesystemException
     */
    public function write(string &$string, int $length = 0, int $chunk = 0): bool {
        if (!$this->fh) {
            throw new FilesystemException('Файл не открыт');
        }

        if ($this->openmode == 'r') {
            throw new \RuntimeException('Файл находится в режиме только для чтения.');
        }

        if (!$length) {
            $length = \strlen($string);
        }

        if (!$chunk) {
            $chunk = $this->chunksize;
        }

        $retval = true;

        error_clear_last();

        $remaining = $length;
        $start     = 0;

        do {
            $amount = ($remaining > $chunk) ? $chunk : $remaining;
            $res    = fwrite($this->fh, substr($string, $start), $amount);

            if ($res === false) {
                $error = error_get_last();

                if ($error === null || $error['message'] === '') {
                    $error = [
                        'message' => 'Невозможно записать в поток.',
                    ];
                }

                throw new FilesystemException($error['message']);
            }

            if ($res === 0) {
                throw new FilesystemException('Внимание: данные не записаны.');
            }

            $start += $amount;
            $remaining -= $res;
        } while ($remaining);

        return $retval;
    }

    /**
     * Обертка Chmod.
     *
     * @param   string  $filename  Имя файла.
     * @param   integer $mode      Режим использования.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public function chmod(string $filename = '', int $mode = 0): bool {
        if (!$filename) {
            if (!isset($this->filename) || !$this->filename) {
                throw new FilesystemException('Имя файла не установлено.');
            }

            $filename = $this->filename;
        }

        if (!$mode) {
            $mode = $this->filemode;
        }

        error_clear_last();

        $sch = parse_url($filename, \PHP_URL_SCHEME);

        $res = match ($sch) {
            'ftp', 'ftps' => Helper::ftpChmod($filename, $mode),
            default => chmod($filename, $mode),
        };

        if ($res === false) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => 'Невозможно изменить режим потока.',
                ];
            }

            throw new FilesystemException($error['message']);
        }

        return true;
    }

    /**
     * Извлекает заголовок или метаданные из потоков или файловых указателей.
     *
     * @return  array  header/metadata.
     *
     * @throws  FilesystemException
     *@link    https://www.php.net/manual/ru/function.stream-get-meta-data.php
     */
    public function get_meta_data(): array {
        if (!$this->fh) {
            throw new FilesystemException('Файл не открыт');
        }

        return stream_get_meta_data($this->fh);
    }

    /**
     * Контексты потока.
     * Строит контекст из массива.
     *
     * @return  void
     *
     */
    public function _buildContext(): void {
        if (\count($this->contextOptions)) {
            $this->context = @stream_context_create($this->contextOptions);
        } else {
            $this->context = null;
        }
    }

    /**
     * Обновляет контекст массива.
     *
     * Формат такой же, как параметры дляstream_context_create.
     *
     * @param   array  $context  Опции для создания контекста с помощью.
     *
     * @return  void
     *
     * @link    https://www.php.net/stream_context_create
     */
    public function setContextOptions(array $context): void {
        $this->contextOptions = $context;
        $this->_buildContext();
    }

    /**
     * Добавляет определенные параметры в контекст.
     *
     * @param   string  $wrapper  Обертка, которую нужно использовать.
     * @param   string  $name     Название.
     * @param   string  $value    Значение.
     *
     * @return  void
     *
     * @link    https://www.php.net/stream_context_create Создание контекста потока.
     * @link    https://www.php.net/manual/en/context.php Параметры контекста для различных потоков.
     */
    public function addContextEntry(string $wrapper, string $name, string $value): void {
        $this->contextOptions[$wrapper][$name] = $value;
        $this->_buildContext();
    }

    /**
     * Удаляет определенный параметр из контекста.
     *
     * @param   string  $wrapper  Обертка, которую нужно использовать.
     * @param   string  $name     Возможность снять настройки.
     *
     * @return  void
     *
     * @link    https://www.php.net/stream_context_create
     */
    public function deleteContextEntry(string $wrapper, string $name): void {
        if (isset($this->contextOptions[$wrapper])) {
            if (isset($this->contextOptions[$wrapper][$name])) {
                unset($this->contextOptions[$wrapper][$name]);

                if (!\count($this->contextOptions[$wrapper])) {
                    unset($this->contextOptions[$wrapper]);
                }
            }
        }

        $this->_buildContext();
    }

    /**
     * Применяет текущий контекст к потоку.
     *
     * Используйте это, чтобы изменить значения контекста после открытия потока.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public function applyContextToStream(): bool {
        $retval = false;

        if ($this->fh) {
            error_clear_last();

            $retval = @stream_context_set_option($this->fh, $this->contextOptions);

            if (!$retval) {
                $error = error_get_last();

                if ($error === null || $error['message'] === '') {
                    $error = [
                        'message' => 'Невозможно применить контекст к потоку.',
                    ];
                }

                throw new FilesystemException($error['message']);
            }
        }

        return $retval;
    }

    /**
     * Потоковые фильтры.
     * Добавляет фильтр в цепочку.
     *
     * @param   string   $filtername  Ключевое имя фильтра.
     * @param   integer  $readWrite   Необязательный. По умолчанию STREAM_FILTER_READ.
     * @param   array    $params      Массив параметров для вызоваstream_filter_append.
     *
     * @return  resource|boolean
     *
     * @link    https://www.php.net/manual/ru/function.stream-filter-append.php
     * @throws  FilesystemException
     */
    public function appendFilter(string $filtername, int $readWrite = \STREAM_FILTER_READ, array $params = []): mixed {
        $res = false;

        if ($this->fh) {
            error_clear_last();

            $res = @stream_filter_append($this->fh, $filtername, $readWrite, $params);

            if (!$res) {
                $error = error_get_last();

                if ($error !== null && $error['message'] !== '') {
                    throw new FilesystemException($error['message']);
                }
            }

            $this->filters[] = &$res;
        }

        return $res;
    }

    /**
     * Подсоедините фильтр к цепочке.
     *
     * @param   string   $filtername  Ключевое имя фильтра.
     * @param   integer  $readWrite   Необязательный. По умолчанию STREAM_FILTER_READ.
     * @param   array    $params      Массив параметров для вызоваstream_filter_prepend.
     *
     * @return  resource|boolean
     *
     * @link    https://www.php.net/manual/ru/function.stream-filter-prepend.php
     * @throws  FilesystemException
     */
    public function prependFilter(string $filtername, int $readWrite = \STREAM_FILTER_READ, array $params = []): mixed {
        $res = false;

        if ($this->fh) {
            error_clear_last();

            $res = @stream_filter_prepend($this->fh, $filtername, $readWrite, $params);

            if (!$res) {
                $error = error_get_last();

                if ($error !== null && $error['message'] !== '') {
                    throw new FilesystemException($error['message']);
                }
            }

            array_unshift($this->filters, '');
            $this->filters[0] = &$res;
        }

        return $res;
    }

    /**
     * Удаляет фильтр либо по ресурсу (выдаваемому функцией добавления или добавления), либо путем получения списка фильтров).
     *
     * @param   resource  $resource  Ресурс.
     * @param   boolean   $byindex   Индекс фильтра.
     *
     * @return  boolean   Результат операции.
     *
     * @throws  FilesystemException
     */
    public function removeFilter(&$resource, bool $byindex = false): bool {
        error_clear_last();

        if ($byindex) {
            $res = stream_filter_remove($this->filters[$resource]);
        } else {
            $res = stream_filter_remove($resource);
        }

        if (!$res) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => 'Невозможно удалить фильтр из потока.',
                ];
            }

            throw new FilesystemException($error['message']);
        }

        return $res;
    }

    /**
     * Скопируйте файл из src в dest.
     *
     * @param   string    $src        Путь к файлу, из которого нужно скопировать.
     * @param   string    $dest       Путь к файлу, в который необходимо скопировать.
     * @param   resource  $context    Действительный ресурс контекста (необязательно), созданный с помощьюstream_context_create.
     * @param   boolean   $usePrefix  Управляет использованием префикса (необязательно).
     * @param   boolean   $relative   Определяет, является ли указанное имя файла относительным. Относительные пути не удаляются из PATH_ROOT.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public function copy(string $src, string $dest, $context = null, bool $usePrefix = true, bool $relative = false): bool {
        error_clear_last();

        $chmodDest = $this->_getFilename($dest, 'w', $usePrefix, $relative);
        $src       = $this->_getFilename($src, 'w', $usePrefix, $relative);
        $dest      = $this->_getFilename($dest, 'w', $usePrefix, $relative);

        if ($context) {
            $res = @copy($src, $dest, $context);
        } elseif ($this->context) {
            $res = @copy($src, $dest, $this->context);
        } else {
            $res = @copy($src, $dest);
        }

        if (!$res) {
            $error = error_get_last();

            if ($error !== null && $error['message'] !== '') {
                throw new FilesystemException($error['message']);
            }
        }

        $this->chmod($chmodDest);

        return $res;
    }

    /**
     * Перемещает файл.
     *
     * @param   string    $src        Путь к файлу, из которого нужно перейти.
     * @param   string    $dest       Путь к файлу, к которому необходимо перейти.
     * @param   resource  $context    Действительный ресурс контекста (необязательно), созданный с помощьюstream_context_create.
     * @param   boolean   $usePrefix  Управляет использованием префикса (необязательно).
     * @param   boolean   $relative   Определяет, является ли указанное имя файла относительным. Относительные пути не удаляются из PATH_ROOT.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public function move(
        string $src,
        string $dest,
        $context = null,
        bool $usePrefix = true,
        bool $relative = false
    ): bool {

        error_clear_last();

        $src  = $this->_getFilename($src, 'w', $usePrefix, $relative);
        $dest = $this->_getFilename($dest, 'w', $usePrefix, $relative);

        if ($context) {
            $res = @rename($src, $dest, $context);
        } elseif ($this->context) {
            $res = @rename($src, $dest, $this->context);
        } else {
            $res = @rename($src, $dest);
        }

        if (!$res) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => 'Невозможно переместить поток.',
                ];
            }

            throw new FilesystemException($error['message']);
        }

        $this->chmod($dest);

        return $res;
    }

    /**
     * Удаляет файл.
     *
     * @param   string    $filename   Путь к файлу, который нужно удалить.
     * @param   resource  $context    Действительный ресурс контекста (необязательно), созданный с помощьюstream_context_create.
     * @param   boolean   $usePrefix  Управляет использованием префикса (необязательно).
     * @param   boolean   $relative   Определяет, является ли указанное имя файла относительным. Относительные пути не удаляются из PATH_ROOT.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public function delete(string $filename, $context = null, bool $usePrefix = true, bool $relative = false): bool {
        error_clear_last();

        $filename = $this->_getFilename($filename, 'w', $usePrefix, $relative);

        if ($context) {
            $res = @unlink($filename, $context);
        } elseif ($this->context) {
            $res = @unlink($filename, $this->context);
        } else {
            $res = @unlink($filename);
        }

        if (!$res) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => 'Невозможно удалить поток.',
                ];
            }

            throw new FilesystemException($error['message']);
        }

        return $res;
    }

    /**
     * Загрузить файл.
     *
     * @param   string    $src        Путь к файлу для копирования (обычно это временная папка).
     * @param   string    $dest       Путь к файлу, в который необходимо скопировать.
     * @param   resource  $context    Действительный ресурс контекста (необязательно), созданный с помощьюstream_context_create.
     * @param   boolean   $usePrefix  Управляет использованием префикса (необязательно).
     * @param   boolean   $relative   Определяет, является ли указанное имя файла относительным. Относительные пути не удаляются из PATH_ROOT.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public function upload(
        string $src,
        string $dest,
        $context = null,
        bool $usePrefix = true,
        bool $relative = false
    ): bool {
        if (is_uploaded_file($src)) {
            return $this->copy($src, $dest, $context, $usePrefix, $relative);
        }

        throw new FilesystemException('Не загруженный файл.');
    }

    /**
     * Записывает фрагмент данных в файл.
     *
     * @param   string   $filename      Имя файла.
     * @param   string   $buffer        Данные для записи в файл.
     * @param   boolean  $appendToFile  Добавить к файлу и не перезаписывать его.
     *
     * @return  boolean
     *
     */
    public function writeFile(string $filename, string &$buffer, bool $appendToFile = false): bool {
        $fileMode = 'w';

        if ($appendToFile) {
            $fileMode = 'a';
        }

        if ($this->open($filename, $fileMode)) {
            $result = $this->write($buffer);
            $this->chmod();
            $this->close();

            return $result;
        }

        return false;
    }

    /**
     * Определяет подходящее «имя файла» файла.
     *
     * @param   string   $filename   Исходное имя файла.
     * @param   string   $mode       Строка режима для получения имени файла.
     * @param   boolean  $usePrefix  Контролирует использование префикса.
     * @param   boolean  $relative   Определяет, является ли указанное имя файла относительным. Относительные пути не удаляются из PATH_ROOT.
     *
     * @return  string
     *
     */
    public function _getFilename(string $filename, string $mode, bool $usePrefix, bool $relative): string {
        if ($usePrefix) {
            $tmode = trim($mode, 'btf123456789');

            $stream   = explode('://', $filename, 2);
            $scheme   = '';
            $filename = $stream[0];

            if (\count($stream) >= 2) {
                $scheme   = $stream[0] . '://';
                $filename = $stream[1];
            }

            if (\in_array($tmode, Helper::getWriteModes())) {
                $prefixToUse = $this->writeprefix;
            } else {
                $prefixToUse = $this->readprefix;
            }

            if (!$relative && $prefixToUse) {
                $pos = strpos($filename, PATH_ROOT);

                if ($pos !== false) {
                    $filename = substr_replace($filename, '', $pos, \strlen(PATH_ROOT));
                }
            }

            $filename = ($prefixToUse ? $prefixToUse : '') . $filename;
        }

        return $filename;
    }

    /**
     * Возвращает внутренний дескриптор файла.
     *
     * @return  File обработчик.
     *
     */
    public function getFileHandle(): File {
        return $this->fh;
    }

    /**
     * Изменяет свойство объекта, создавая его, если оно еще не существует.
     *
     * @param   string  $property  Название объекта недвижимости.
     * @param   mixed   $value     Значение свойства, которое необходимо установить.
     *
     * @return  mixed  Предыдущая стоимость недвижимости.
     *
     */
    public function set(string $property, mixed $value = null): mixed {
        $previous        = $this->$property ?? null;
        $this->$property = $value;

        return $previous;
    }

    /**
     * Возвращает свойство объекта или значение по умолчанию, если свойство не установлено.
     *
     * @param   string  $property  Название объекта недвижимости.
     * @param   mixed   $default   Значение по умолчанию.
     *
     * @return  mixed    Стоимость недвижимости.
     *
     */
    public function get(string $property, mixed $default = null): mixed {
        if (isset($this->$property)) {
            return $this->$property;
        }

        return $default;
    }
}
