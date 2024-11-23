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
 * Адаптер формата ZIP для пакета «Архив».
 */
class Zip implements ExtractableInterface {
    /**
     * Методы сжатия ZIP.
     *
     * @var    array
     */
    private const array METHODS = [
        0x0 => 'None',
        0x1 => 'Shrunk',
        0x2 => 'Super Fast',
        0x3 => 'Fast',
        0x4 => 'Normal',
        0x5 => 'Maximum',
        0x6 => 'Imploded',
        0x8 => 'Deflated',
    ];

    /**
     * Начало записи центрального каталога.
     *
     * @var    string
     */
    private const string CTRL_DIR_HEADER = "\x50\x4b\x01\x02";

    /**
     * Конец записи центрального каталога.
     *
     * @var    string
     */
    private const string CTRL_DIR_END = "\x50\x4b\x05\x06\x00\x00\x00\x00";

    /**
     * Начало содержимого файла.
     *
     * @var    string
     */
    private const string FILE_HEADER = "\x50\x4b\x03\x04";

    /**
     * Буфер данных ZIP-файла
     *
     * @var    string
     */
    private string $data;

    /**
     * Массив метаданных ZIP-файла
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
     * Создаёт сжатый ZIP-файл из массива данных файла.
     *
     * @param   string  $archive  Путь для сохранения архива.
     * @param   array   $files    Массив файлов для добавления в архив.
     *
     * @return  boolean  True в случае успеха.
     *
     * @todo    Завершить реализацию
     */
    public function create(string $archive, array $files): bool {
        $contents = [];
        $ctrldir  = [];

        foreach ($files as $file) {
            $this->addToZipFile($file, $contents, $ctrldir);
        }

        return $this->createZipFile($contents, $ctrldir, $archive);
    }

    /**
     * Извлекает сжатый ZIP-файл по заданному пути.
     *
     * @param   string  $archive      Путь к ZIP-архиву для распаковки.
     * @param   string  $destination  Путь для распаковки архива в.
     *
     * @return  boolean  True в случае успеха.
     *
     * @throws  \RuntimeException
     */
    public function extract(string $archive, string $destination): bool {
        if (!is_file($archive)) {
            throw new \RuntimeException('Архив не существует: ' . $archive);
        }

        if (static::hasNativeSupport()) {
            return $this->extractNative($archive, $destination);
        }

        return $this->extractCustom($archive, $destination);
    }

    /**
     * Проверяет, может ли этот адаптер распаковывать файлы на этом компьютере.
     *
     * @return  boolean  True если поддерживается.
     *
     */
    public static function isSupported(): bool {
        return self::hasNativeSupport() || \extension_loaded('zlib');
    }

    /**
     * Метод определения наличия на сервере встроенной поддержки zip для более быстрой обработки.
     *
     * @return  boolean  True если php имеет встроенную поддержку ZIP.
     *
     */
    public static function hasNativeSupport(): bool {
        return \extension_loaded('zip');
    }

    /**
     * Проверяет, являются ли данные действительным ZIP-файлом.
     *
     * @param   string  $data  Буфер данных ZIP-архива.
     *
     * @return  boolean  True если действительно, ложь, если недействительно.
     *
     */
    public function checkZipData(string $data): bool {
        return str_contains($data, self::FILE_HEADER);
    }

    /**
     * Извлекает сжатый ZIP-файл по заданному пути, используя алгоритм на основе PHP, который требует только поддержки zlib.
     *
     * @param   string  $archive      Путь к ZIP-архиву для распаковки.
     * @param   string  $destination  Путь для распаковки архива.
     *
     * @return  boolean  True в случае успеха.
     *
     * @throws  \RuntimeException
     */
    protected function extractCustom(string $archive, string $destination): bool {
        $this->metadata = [];
        $this->data     = file_get_contents($archive);

        if (!$this->data) {
            throw new \RuntimeException('Не удалось прочитать архив');
        }

        if (!$this->readZipInfo($this->data)) {
            throw new \RuntimeException('Не удалось получить ZIP-информацию');
        }

        foreach ($this->metadata as $i => $metadata) {
            $lastPathCharacter = substr($metadata['name'], -1, 1);

            if ($lastPathCharacter !== '/' && $lastPathCharacter !== '\\') {
                $buffer = $this->getFileData($i);
                $path   = Path::clean($destination . '/' . $metadata['name']);

                if (!$this->isBelow($destination, $destination . '/' . $metadata['name'])) {
                    throw new \OutOfBoundsException('Невозможно записать за пределами пути назначения', 100);
                }

                if (!Folder::create(\dirname($path))) {
                    throw new \RuntimeException('Невозможно создать папку назначения');
                }

                if (!File::write($path, $buffer)) {
                    throw new \RuntimeException('Невозможно записать файл');
                }
            }
        }

        return true;
    }

    /**
     * Извлекает сжатый ZIP-файл по заданному пути, используя собственные вызовы PHP API для повышения скорости.
     *
     * @param   string  $archive      Путь к ZIP-архиву.
     * @param   string  $destination  Путь для распаковки архива.
     *
     * @return  boolean  True если успех.
     *
     * @throws  \RuntimeException
     */
    protected function extractNative(string $archive, string $destination): bool {
        $zip = new \ZipArchive();

        if ($zip->open($archive) !== true) {
            throw new \RuntimeException('Невозможно открыть архив');
        }

        if (!Folder::create($destination)) {
            throw new \RuntimeException('Невозможно создать папку назначения ' . \dirname($destination));
        }

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $file = $zip->getNameIndex($index);

            if (str_ends_with($file, '/')) {
                continue;
            }

            $buffer = $zip->getFromIndex($index);

            if ($buffer === false) {
                throw new \RuntimeException('Невозможно прочитать ZIP-данные');
            }

            if (!$this->isBelow($destination, $destination . '/' . $file)) {
                throw new \RuntimeException('Невозможно записать за пределами пути назначения', 100);
            }

            if (File::write($destination . '/' . $file, $buffer) === false) {
                throw new \RuntimeException('Невозможно записать ZIP в файл ' . $destination . '/' . $file);
            }
        }

        $zip->close();

        return true;
    }

    /**
     * Возвращает список файлов/данных из буфера ZIP-архива.
     *
     * <pre>
     * KEY: Позиция в zip-файле
     * VALUES: 'attr'  --  Атрибуты файла
     *         'crc'   --  Контрольная сумма CRC
     *         'csize' --  Размер сжатого файла
     *         'date'  --  Время модификации файла
     *         'name'  --  Имя файла
     *         'method'--  Метод сжатия
     *         'size'  --  Исходный размер файла
     *         'type'  --  Тип файла
     * </pre>
     *
     * @param   string  $data  Буфер ZIP-архива.
     *
     * @return  boolean True если успех.
     *
     * @throws  \RuntimeException
     */
    private function readZipInfo(string $data): bool {
        $entries = [];

        $fhLast = strpos($data, self::CTRL_DIR_END);

        do {
            $last = $fhLast;
        } while (($fhLast = strpos($data, self::CTRL_DIR_END, $fhLast + 1)) !== false);

        $offset = 0;

        if ($last) {
            $endOfCentralDirectory = unpack(
                'vNumberOfDisk/vNoOfDiskWithStartOfCentralDirectory/vNoOfCentralDirectoryEntriesOnDisk/' .
                'vTotalCentralDirectoryEntries/VSizeOfCentralDirectory/VCentralDirectoryOffset/vCommentLength',
                $data,
                $last + 4
            );
            $offset = $endOfCentralDirectory['CentralDirectoryOffset'];
        }

        $fhStart    = strpos($data, self::CTRL_DIR_HEADER, $offset);
        $dataLength = \strlen($data);

        do {
            if ($dataLength < $fhStart + 31) {
                throw new \RuntimeException('Неверные ZIP-данные');
            }

            $info = unpack('vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength', $data, $fhStart + 10);
            $name = substr($data, $fhStart + 46, $info['Length']);

            $entries[$name] = [
                'attr'       => null,
                'crc'        => sprintf('%08s', dechex($info['CRC32'])),
                'csize'      => $info['Compressed'],
                'date'       => null,
                '_dataStart' => null,
                'name'       => $name,
                'method'     => self::METHODS[$info['Method']],
                '_method'    => $info['Method'],
                'size'       => $info['Uncompressed'],
                'type'       => null,
            ];

            $entries[$name]['date'] = mktime(
                ($info['Time'] >> 11) & 0x1f,
                ($info['Time'] >> 5) & 0x3f,
                ($info['Time'] << 1) & 0x3e,
                ($info['Time'] >> 21) & 0x07,
                ($info['Time'] >> 16) & 0x1f,
                (($info['Time'] >> 25) & 0x7f) + 1980
            );

            if ($dataLength < $fhStart + 43) {
                throw new \RuntimeException('Неверные ZIP-данные');
            }

            $info = unpack('vInternal/VExternal/VOffset', $data, $fhStart + 36);

            $entries[$name]['type'] = ($info['Internal'] & 0x01) ? 'text' : 'binary';
            $entries[$name]['attr'] = (($info['External'] & 0x10) ? 'D' : '-') . (($info['External'] & 0x20) ? 'A' : '-')
                . (($info['External'] & 0x03) ? 'S' : '-') . (($info['External'] & 0x02) ? 'H' : '-') . (($info['External'] & 0x01) ? 'R' : '-');
            $entries[$name]['offset'] = $info['Offset'];

            $lfhStart = strpos($data, self::FILE_HEADER, $entries[$name]['offset']);

            if ($dataLength < $lfhStart + 34) {
                throw new \RuntimeException('Неверные ZIP-данные');
            }

            $info                         = unpack('vMethod/VTime/VCRC32/VCompressed/VUncompressed/vLength/vExtraLength', $data, $lfhStart + 8);
            $name                         = substr($data, $lfhStart + 30, $info['Length']);
            $entries[$name]['_dataStart'] = $lfhStart + 30 + $info['Length'] + $info['ExtraLength'];

            @set_time_limit(ini_get('max_execution_time'));
        } while (($fhStart = strpos($data, self::CTRL_DIR_HEADER, $fhStart + 46)) !== false);

        $this->metadata = array_values($entries);

        return true;
    }

    /**
     * Возвращает данные файла по смещению в ZIP-архиве.
     *
     * @param   integer  $key  Положение файла в архиве.
     *
     * @return  string  Буфер данных несжатого файла.
     *
     */
    private function getFileData(int $key): string {
        if ($this->metadata[$key]['_method'] == 0x8) {
            return gzinflate(substr($this->data, $this->metadata[$key]['_dataStart'], $this->metadata[$key]['csize']));
        }

        if ($this->metadata[$key]['_method'] == 0x0) {
            return substr($this->data, $this->metadata[$key]['_dataStart'], $this->metadata[$key]['csize']);
        }

        if ($this->metadata[$key]['_method'] == 0x12) {
            if (\extension_loaded('bz2')) {
                return bzdecompress(substr($this->data, $this->metadata[$key]['_dataStart'], $this->metadata[$key]['csize']));
            }
        }

        return '';
    }

    /**
     * Преобразует временную метку UNIX в 4-байтовый формат даты и времени DOS (дата в старших 2 байтах, время в младших 2 байтах, что позволяет сравнивать величины).
     *
     * @param   integer|null  $unixtime  Текущая временная метка UNIX.
     *
     * @return  integer  Текущая дата в 4-байтовом формате DOS.
     *
     */
    protected function unix2DosTime(?int $unixtime = null): int {
        $timearray = $unixtime === null ? getdate() : getdate($unixtime);

        if ($timearray['year'] < 1980) {
            $timearray['year']    = 1980;
            $timearray['mon']     = 1;
            $timearray['mday']    = 1;
            $timearray['hours']   = 0;
            $timearray['minutes'] = 0;
            $timearray['seconds'] = 0;
        }

        return (($timearray['year'] - 1980) << 25) | ($timearray['mon'] << 21) | ($timearray['mday'] << 16) | ($timearray['hours'] << 11) |
            ($timearray['minutes'] << 5) | ($timearray['seconds'] >> 1);
    }

    /**
     * Добавляет «файл» в ZIP-архив.
     *
     * @param   array  $file      Массив данных файла для добавления
     * @param   array  $contents  Массив существующих заархивированных файлов.
     * @param   array  $ctrldir   Массив информации центрального каталога.
     *
     * @return  void
     *
     * @todo    Проверка и завершение реализации
     */
    private function addToZipFile(array &$file, array &$contents, array &$ctrldir): void {
        $data = &$file['data'];
        $name = str_replace('\\', '/', $file['name']);

        $ftime = null;

        if (isset($file['time'])) {
            $ftime = $file['time'];
        }

        $dtime    = dechex($this->unix2DosTime($ftime));
        $hexdtime = \chr(hexdec($dtime[6] . $dtime[7])) . \chr(hexdec($dtime[4] . $dtime[5])) . \chr(hexdec($dtime[2] . $dtime[3]))
            . \chr(hexdec($dtime[0] . $dtime[1]));

        $fr = self::FILE_HEADER;

        $fr .= "\x14\x00";
        $fr .= "\x00\x00";
        $fr .= "\x08\x00";
        $fr .= $hexdtime;
        $uncLen = \strlen($data);
        $crc    = crc32($data);
        $zdata  = gzcompress($data);
        $zdata  = substr(substr($zdata, 0, -4), 2);
        $cLen   = \strlen($zdata);

        $fr .= pack('V', $crc);
        $fr .= pack('V', $cLen);
        $fr .= pack('V', $uncLen);
        $fr .= pack('v', \strlen($name));
        $fr .= pack('v', 0);
        $fr .= $name;
        $fr .= $zdata;

        $oldOffset  = \strlen(implode('', $contents));
        $contents[] = &$fr;

        $cdrec = self::CTRL_DIR_HEADER;
        $cdrec .= "\x00\x00";
        $cdrec .= "\x14\x00";
        $cdrec .= "\x00\x00";
        $cdrec .= "\x08\x00";
        $cdrec .= $hexdtime;
        $cdrec .= pack('V', $crc);
        $cdrec .= pack('V', $cLen);
        $cdrec .= pack('V', $uncLen);
        $cdrec .= pack('v', \strlen($name));
        $cdrec .= pack('v', 0);
        $cdrec .= pack('v', 0);
        $cdrec .= pack('v', 0);
        $cdrec .= pack('v', 0);
        $cdrec .= pack('V', 32);
        $cdrec .= pack('V', $oldOffset);
        $cdrec .= $name;

        $ctrldir[] = &$cdrec;
    }

    /**
     * Создаёт ZIP-файл.
     *
     * Официальный формат файла ZIP: http://www.pkware.com/appnote.txt
     *
     * @param   array   $contents  Массив существующих заархивированных файлов.
     * @param   array   $ctrlDir   Массив информации центрального каталога.
     * @param   string  $path      Путь для хранения архива.
     *
     * @return  boolean  True в случае успеха
     *
     * @todo    Проверка и завершение реализации
     */
    private function createZipFile(array $contents, array $ctrlDir, string $path): bool {
        $data = implode('', $contents);
        $dir  = implode('', $ctrlDir);

        $buffer = $data . $dir . self::CTRL_DIR_END .
        pack('v', \count($ctrlDir)) .
        pack('v', \count($ctrlDir)) .
        pack('V', \strlen($dir)) .
        pack('V', \strlen($data)) .
        "\x00\x00";

        return File::write($path, $buffer);
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
