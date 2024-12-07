<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem;

/**
 * Класс Unified Diff Format Patcher.
 */
class Patcher {
    /**
     * Регулярное выражение для поиска исходных файлов.
     *
     * @var    string
     */
    public const string SRC_FILE = '/^---\\s+(\\S+)\s+\\d{1,4}-\\d{1,2}-\\d{1,2}\\s+\\d{1,2}:\\d{1,2}:\\d{1,2}(\\.\\d+)?\\s+(\+|-)\\d{4}/A';

    /**
     * Регулярное выражение для поиска файлов назначения.
     *
     * @var    string
     */
    public const string DST_FILE = '/^\\+\\+\\+\\s+(\\S+)\s+\\d{1,4}-\\d{1,2}-\\d{1,2}\\s+\\d{1,2}:\\d{1,2}:\\d{1,2}(\\.\\d+)?\\s+(\+|-)\\d{4}/A';

    /**
     * Регулярное выражение для поиска различий.
     *
     * @var    string
     */
    public const string HUNK = '/@@ -(\\d+)(,(\\d+))?\\s+\\+(\\d+)(,(\\d+))?\\s+@@($)/A';

    /**
     * Регулярное выражение для разделения строк.
     *
     * @var    string
     */
    public const string SPLIT = '/(\r\n)|(\r)|(\n)/';

    /**
     * Исходные файлы.
     *
     * @var    array
     */
    protected array $sources = [];

    /**
     * Файлы назначения.
     *
     * @var    array
     */
    protected array $destinations = [];

    /**
     * Удаление файлов.
     *
     * @var    array
     */
    protected array $removals = [];

    /**
     * Патчи.
     *
     * @var    array
     */
    protected array $patches = [];

    /**
     * Одиночный экземпляр этого класса.
     *
     * @var    Patcher
     */
    protected static Patcher $instance;

    /**
     * Конструктор.
     *
     * Конструктор защищен от принудительного использования.
     * Используется Patcher::getInstance()
     *
     */
    protected function __construct() {}

    /**
     * Способ получения патчера.
     *
     * @return  Patcher  экземпляр патчера.
     *
     */
    public static function getInstance(): self {
        if (!isset(static::$instance)) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * Сброс патчера.
     *
     * @return  Patcher  Этот объект для цепочки.
     *
     */
    public function reset(): self {
        $this->sources      = [];
        $this->destinations = [];
        $this->removals     = [];
        $this->patches      = [];

        return $this;
    }

    /**
     * Примените патчи.
     *
     * @return  integer  Количество исправленных файлов.
     *
     * @throws  \RuntimeException
     */
    public function apply(): int {
        foreach ($this->patches as $patch) {
            $lines = self::splitLines($patch['udiff']);

            while (self::findHeader($lines, $src, $dst)) {
                $done = false;

                if ($patch['strip'] === null) {
                    $src = $patch['root'] . preg_replace('#^([^/]*/)*#', '', $src);
                    $dst = $patch['root'] . preg_replace('#^([^/]*/)*#', '', $dst);
                } else {
                    $src = $patch['root'] . preg_replace('#^([^/]*/){' . (int) $patch['strip'] . '}#', '', $src);
                    $dst = $patch['root'] . preg_replace('#^([^/]*/){' . (int) $patch['strip'] . '}#', '', $dst);
                }

                while (self::findHunk($lines, $srcLine, $srcSize, $dstLine, $dstSize)) {
                    $done = true;

                    $this->applyHunk($lines, $src, $dst, $srcLine, $srcSize, $dstLine, $dstSize);
                }

                if (!$done) {
                    throw new \RuntimeException('Не верный Diff.');
                }
            }
        }

        $done = 0;

        foreach ($this->destinations as $file => $content) {
            $content = implode("\n", $content);

            if (File::write($file, $content)) {
                if (isset($this->sources[$file])) {
                    $this->sources[$file] = $content;
                }

                $done++;
            }
        }

        foreach ($this->removals as $file) {
            if (File::delete($file)) {
                if (isset($this->sources[$file])) {
                    unset($this->sources[$file]);
                }

                $done++;
            }
        }

        $this->destinations = [];
        $this->removals = [];
        $this->patches = [];

        return $done;
    }

    /**
     * Добавляет в патчер единый файл различий.
     *
     * @param   string   $filename  Путь к единому файлу различий.
     * @param   string   $root      Корневой путь файлов.
     * @param   integer  $strip     Количество символов '/' для удаления.
     *
     * @return  Patcher  $this для цепочки.
     *
     */
    public function addFile(string $filename, string $root, int $strip = 0): self {
        return $this->add(file_get_contents($filename), $root, $strip);
    }

    /**
     * Добавляет в патчер унифицированную строку различий.
     *
     * @param   string   $udiff  Единая входная строка различий.
     * @param   string   $root   Корневой путь файлов.
     * @param   integer  $strip  Количество символов '/' для удаления.
     *
     * @return  Patcher  $this для цепочки.
     *
     */
    public function add(string $udiff, string $root, int $strip = 0): self {
        $this->patches[] = [
            'udiff' => $udiff,
            'root'  => isset($root) ? rtrim($root, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR : '',
            'strip' => $strip,
        ];

        return $this;
    }

    /**
     * Отдельные строки CR или CRLF.
     *
     * @param   string  $data  Входная строка.
     *
     * @return  array  Строки входного файла назначения.
     *
     */
    protected static function splitLines(string $data): array {
        return preg_split(self::SPLIT, $data);
    }

    /**
     * Поиск разницы заголовков.
     *
     * Указатель внутреннего массива $lines находится на следующей строке после найденного.
     *
     * @param   array   $lines  Массив строк udiff.
     * @param   string  $src    Исходный файл.
     * @param   string  $dst    Файл назначения.
     *
     * @return  boolean  TRUE в случае успеха, FALSE в случае неудачи.
     *
     * @throws  \RuntimeException
     */
    protected static function findHeader(array &$lines, string &$src, string &$dst): bool {
        $line = current($lines);

        while ($line !== false && !preg_match(self::SRC_FILE, $line, $m)) {
            $line = next($lines);
        }

        if ($line === false) {
            return false;
        }

        $src  = $m[1];
        $line = next($lines);

        if ($line === false) {
            throw new \RuntimeException('Непредвиденный EOF.');
        }

        if (!preg_match(self::DST_FILE, $line, $m)) {
            throw new \RuntimeException('Неверный Diff файл.');
        }

        $dst = $m[1];

        if (next($lines) === false) {
            throw new \RuntimeException('Непредвиденный EOF.');
        }

        return true;
    }

    /**
     * Поиск следующей разницы.
     *
     * Указатель внутреннего массива $lines находится на следующей строке после найденного.
     *
     * @param   array   $lines    Массив строк udiff.
     * @param   string  $srcLine  Начало патча для исходного файла.
     * @param   string  $srcSize  Размер патча для исходного файла.
     * @param   string  $dstLine  Начало патча для целевого файла.
     * @param   string  $dstSize  Размер патча для целевого файла.
     *
     * @return  boolean  TRUE в случае успеха, false в случае неудачи.
     *
     * @throws  \RuntimeException
     */
    protected static function findHunk(
        array &$lines,
        string &$srcLine,
        string &$srcSize,
        string &$dstLine,
        string &$dstSize
    ): bool {
        $line = current($lines);

        if (preg_match(self::HUNK, $line, $m)) {
            $srcLine = (int) $m[1];

            if ($m[3] === '') {
                $srcSize = 1;
            } else {
                $srcSize = (int) $m[3];
            }

            $dstLine = (int) $m[4];

            if ($m[6] === '') {
                $dstSize = 1;
            } else {
                $dstSize = (int) $m[6];
            }

            if (next($lines) === false) {
                throw new \RuntimeException('Непредвиденный EOF.');
            }

            return true;
        }

        return false;
    }

    /**
     * Применение патча.
     *
     * @param   array   $lines    Массив строк udiff.
     * @param   string  $src      Исходный файл.
     * @param   string  $dst      Файл назначения.
     * @param   string  $srcLine  Начало патча для исходного файла.
     * @param   string  $srcSize  Размер патча для исходного файла.
     * @param   string  $dstLine  Начало патча для целевого файла.
     * @param   string  $dstSize  Размер патча для целевого файла.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    protected function applyHunk(
        array &$lines,
        string $src,
        string $dst,
        string $srcLine,
        string $srcSize,
        string $dstLine,
        string $dstSize
    ): void {

        $srcLine--;
        $dstLine--;
        $line = current($lines);

        $source = [];

        $destin  = [];
        $srcLeft = $srcSize;
        $dstLeft = $dstSize;

        do {
            if (!isset($line[0])) {
                $source[] = '';
                $destin[] = '';
                $srcLeft--;
                $dstLeft--;
            } elseif ($line[0] == '-') {
                if ($srcLeft == 0) {
                    throw new \RuntimeException('Неожиданное удаление строки за строкой ' . key($lines));
                }

                $source[] = substr($line, 1);
                $srcLeft--;
            } elseif ($line[0] == '+') {
                if ($dstLeft == 0) {
                    throw new \RuntimeException('Неожиданное добавление строки за строкой ' . key($lines));
                }

                $destin[] = substr($line, 1);
                $dstLeft--;
            } elseif ($line != '\\ No newline at end of file') {
                $line     = substr($line, 1);
                $source[] = $line;
                $destin[] = $line;
                $srcLeft--;
                $dstLeft--;
            }

            if ($srcLeft == 0 && $dstLeft == 0) {
                if ($srcSize > 0) {
                    $srcLines = & $this->getSource($src);

                    if (!isset($srcLines)) {
                        throw new \RuntimeException(
                            'Несуществующий исходный файл: ' . Path::removeRoot($src)
                        );
                    }
                }

                if ($dstSize > 0) {
                    if ($srcSize > 0) {
                        $dstLines  = & $this->getDestination($dst, $src);
                        $srcBottom = $srcLine + \count($source);

                        for ($l = $srcLine; $l < $srcBottom; $l++) {
                            if ($srcLines[$l] != $source[$l - $srcLine]) {
                                throw new \RuntimeException(
                                    sprintf(
                                        'Не удалось проверить источник файла %1$s в строке %2$s.',
                                        Path::removeRoot($src),
                                        $l
                                    )
                                );
                            }
                        }

                        array_splice($dstLines, $dstLine, \count($source), $destin);
                    } else {
                        $this->destinations[$dst] = $destin;
                    }
                } else {
                    $this->removals[] = $src;
                }

                next($lines);

                return;
            }

            $line = next($lines);
        } while ($line !== false);

        throw new \RuntimeException('Непредвиденный EOF.');
    }

    /**
     * Возвращает строки исходного файла.
     *
     * @param   string  $src  Путь к файлу.
     *
     * @return  array  Строки исходного файла.
     *
     */
    protected function &getSource(string $src): array {
        if (!isset($this->sources[$src])) {
            if (is_readable($src)) {
                $this->sources[$src] = self::splitLines(file_get_contents($src));
            } else {
                $this->sources[$src] = null;
            }
        }

        return $this->sources[$src];
    }

    /**
     * Возвращает строки файла назначения.
     *
     * @param   string  $dst  Путь к файлу назначения.
     * @param   string  $src  Путь к исходному файлу.
     *
     * @return  array  Строки файла назначения.
     *
     */
    protected function &getDestination(string $dst, string $src): array {
        if (!isset($this->destinations[$dst])) {
            $this->destinations[$dst] = $this->getSource($src);
        }

        return $this->destinations[$dst];
    }
}
