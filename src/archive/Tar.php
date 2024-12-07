<?php

/**
 * Часть пакета Flexis Archive Framework.
 */

namespace Flexis\Archive;

use ArrayAccess;
use Flexis\Filesystem\File;
use Flexis\Filesystem\Folder;
use Flexis\Filesystem\Path;

/**
 * Адаптер формата Tar для пакета Archive.
 */
class Tar implements ExtractableInterface {
    /**
     * Типы файлов Tar.
     *
     * @var    array
     */
    private const array TYPES = [
        0x0  => 'Unix file',
        0x30 => 'File',
        0x31 => 'Link',
        0x32 => 'Symbolic link',
        0x33 => 'Character special file',
        0x34 => 'Block special file',
        0x35 => 'Directory',
        0x36 => 'FIFO special file',
        0x37 => 'Contiguous file',
    ];

    /**
     * Буфер данных файла Tar
     *
     * @var    string
     */
    private string $data;

    /**
     * Массив метаданных файла Tar
     *
     * @var    array
     */
    private array $metadata;

    /**
     * Содержит массив параметров.
     *
     * @var    array|ArrayAccess
     */
    protected array|ArrayAccess $options = [];

    /**
     * Создаёт новый объект «Архив».
     *
     * @param   array|ArrayAccess  $options  Массив параметров или объект, реализующий ArrayAccess.
     *
     * @throws  \InvalidArgumentException
     */
    public function __construct(array|ArrayAccess $options = []) {
        if (!\is_array($options) && !($options instanceof ArrayAccess)) {
            throw new \InvalidArgumentException(
                'Параметр options должен быть массивом или реализовывать интерфейс ArrayAccess.'
            );
        }

        $this->options = $options;
    }

    /**
     * Извлекает сжатый ZIP-файл по заданному пути.
     *
     * @param   string  $archive      Путь к ZIP-архиву для распаковки.
     * @param   string  $destination  Путь для распаковки архива.
     *
     * @return  boolean True в случае успеха.
     *
     * @throws  \RuntimeException
     */
    public function extract(string $archive, string $destination): bool {
        $this->metadata = [];
        $this->data     = file_get_contents($archive);

        if (!$this->data) {
            throw new \RuntimeException('Не удалось прочитать архив');
        }

        $this->getTarInfo($this->data);

        for ($i = 0, $n = \count($this->metadata); $i < $n; $i++) {
            $type = strtolower($this->metadata[$i]['type']);

            if ($type == 'file' || $type == 'unix file') {
                $buffer = $this->metadata[$i]['data'];
                $path   = Path::clean($destination . '/' . $this->metadata[$i]['name']);

                if (!$this->isBelow($destination, $destination . '/' . $this->metadata[$i]['name'])) {
                    throw new \OutOfBoundsException('Невозможно писать за пределами пути назначения', 100);
                }

                if (!Folder::create(\dirname($path))) {
                    throw new \RuntimeException('Невозможно создать папку назначения ' . \dirname($path));
                }

                if (!File::write($path, $buffer)) {
                    throw new \RuntimeException('Невозможно записать в файл ' . $path);
                }
            }
        }

        return true;
    }

    /**
     * Проверяет, может ли этот адаптер распаковывать файлы на этом компьютере.
     *
     * @return  boolean  True если поддерживается.
     *
     */
    public static function isSupported(): bool {
        return true;
    }

    /**
     * Возвращает список файлов/данных из буфера архива Tar и создайте массив метаданных.
     *
     * Структура массива:
     * <pre>
     * KEY: Позиция в массиве
     * VALUES: 'attr'  --  Атрибуты файла
     *         'data'  --  Необработанное содержимое файла
     *         'date'  --  Время модификации файла
     *         'name'  --  Имя файла
     *         'size'  --  Исходный размер файла
     *         'type'  --  Тип файла
     * </pre>
     *
     * @param   string  $data  Буфер архива Tar.
     *
     * @return  void
     *
     * @throws  \RuntimeException
     */
    protected function getTarInfo(string &$data): void {
        $position    = 0;
        $returnArray = [];

        while ($position < \strlen($data)) {
            $info = @unpack(
                'Z100filename/Z8mode/Z8uid/Z8gid/Z12size/Z12mtime/Z8checksum/Ctypeflag/Z100link/Z6magic/Z2version/Z32uname/Z32gname/Z8devmajor/Z8devminor',
                $data,
                $position
            );

            if (isset($longlinkfilename)) {
                $info['filename'] = $longlinkfilename;
                unset($longlinkfilename);
            }

            if (!$info) {
                throw new \RuntimeException('Невозможно распаковать данные');
            }

            $position += 512;
            $contents = substr($data, $position, octdec($info['size']));
            $position += ceil(octdec($info['size']) / 512) * 512;

            if ($info['filename']) {
                $file = [
                    'attr' => null,
                    'data' => null,
                    'date' => octdec($info['mtime']),
                    'name' => trim($info['filename']),
                    'size' => octdec($info['size']),
                    'type' => self::TYPES[$info['typeflag']] ?? null,
                ];

                if (($info['typeflag'] == 0) || ($info['typeflag'] == 0x30) || ($info['typeflag'] == 0x35)) {
                    $file['data'] = $contents;
                    $mode         = hexdec(substr($info['mode'], 4, 3));
                    $file['attr'] = (($info['typeflag'] == 0x35) ? 'd' : '-')
                        . (($mode & 0x400) ? 'r' : '-')
                        . (($mode & 0x200) ? 'w' : '-')
                        . (($mode & 0x100) ? 'x' : '-')
                        . (($mode & 0x040) ? 'r' : '-')
                        . (($mode & 0x020) ? 'w' : '-')
                        . (($mode & 0x010) ? 'x' : '-')
                        . (($mode & 0x004) ? 'r' : '-')
                        . (($mode & 0x002) ? 'w' : '-')
                        . (($mode & 0x001) ? 'x' : '-');
                } elseif (\chr($info['typeflag']) == 'L' && $info['filename'] == '././@LongLink') {
                    $longlinkfilename = $contents;

                    continue;
                }

                $returnArray[] = $file;
            }
        }

        $this->metadata = $returnArray;
    }

    /**
     * Проверяет, находится ли путь ниже заданного пути назначения.
     *
     * @param   string  $destination  Путь назначения.
     * @param   string  $path         Путь, который необходимо проверить.
     *
     * @return  boolean
     */
    private function isBelow(string $destination, string $path): bool {
        $absoluteRoot = Path::clean(Path::resolve($destination));
        $absolutePath = Path::clean(Path::resolve($path));

        return str_starts_with($absolutePath, $absoluteRoot);
    }
}
