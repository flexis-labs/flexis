<?php

/**
 * Часть пакета Flexis Archive Framework.
 */

namespace Flexis\Archive;

use ArrayAccess;
use Flexis\Filesystem\File;
use Flexis\Filesystem\Stream;

/**
 * Адаптер формата Gzip для пакета Archive
 */
class Gzip implements ExtractableInterface {
    /**
     * Флаги файлов Gzip.
     *
     * @var    array
     */
    private const array FLAGS = ['FTEXT' => 0x01, 'FHCRC' => 0x02, 'FEXTRA' => 0x04, 'FNAME' => 0x08, 'FCOMMENT' => 0x10];

    /**
     * Буфер данных файла Gzip
     *
     * @var    string
     */
    private string $data;

    /**
     * Содержит массив параметров.
     *
     * @var    array|ArrayAccess
     */
    protected array|ArrayAccess $options = [];

    /**
     * Создаёт новый объект «Архив».
     *
     * @param   array|ArrayAccess  $options  Массив опций
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
     * Извлекает сжатый файл Gzip по заданному пути.
     *
     * @param   string  $archive      Путь к ZIP-архиву для распаковки.
     * @param   string  $destination  Путь для распаковки архива.
     *
     * @return  boolean  True в случае успеха.
     *
     * @throws  \RuntimeException
     */
    public function extract(string $archive, string $destination): bool {
        $this->data = null;

        if (!isset($this->options['use_streams']) || !$this->options['use_streams']) {
            $this->data = file_get_contents($archive);

            if (!$this->data) {
                throw new \RuntimeException('Не удалось прочитать архив');
            }

            $position = $this->getFilePosition();
            $buffer   = gzinflate(substr($this->data, $position, \strlen($this->data) - $position));

            if (empty($buffer)) {
                throw new \RuntimeException('Невозможно распаковать данные');
            }

            if (!File::write($destination, $buffer)) {
                throw new \RuntimeException('Невозможно записать архив в файл ' . $destination);
            }
        } else {
            $input = Stream::getStream();

            $input->set('processingmethod', 'gz');

            if (!$input->open($archive)) {
                throw new \RuntimeException('Не удалось прочитать архив');
            }

            $output = Stream::getStream();

            if (!$output->open($destination, 'w')) {
                $input->close();

                throw new \RuntimeException('Невозможно открыть файл "' . $destination . '" для записи');
            }

            do {
                $this->data = $input->read($input->get('chunksize', 8196));

                if ($this->data) {
                    if (!$output->write($this->data)) {
                        $input->close();

                        throw new \RuntimeException('Невозможно записать архив в файл ' . $destination);
                    }
                }
            } while ($this->data);

            $output->close();
            $input->close();
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
        return \extension_loaded('zlib');
    }

    /**
     * Возвращает смещение данных файла для архива.
     *
     * @return  integer  Маркер положения данных для архива.
     *
     * @throws  \RuntimeException
     */
    public function getFilePosition(): int {
        $position = 0;
        $info     = @ unpack('CCM/CFLG/VTime/CXFL/COS', $this->data, $position + 2);

        if (!$info) {
            throw new \RuntimeException('Невозможно распаковать данные.');
        }

        $position += 10;

        if ($info['FLG'] & self::FLAGS['FEXTRA']) {
            $XLEN = unpack('vLength', $this->data, $position);
            $XLEN = $XLEN['Length'];
            $position += $XLEN + 2;
        }

        if ($info['FLG'] & self::FLAGS['FNAME']) {
            $filenamePos = strpos($this->data, "\x0", $position);
            $position    = $filenamePos + 1;
        }

        if ($info['FLG'] & self::FLAGS['FCOMMENT']) {
            $commentPos = strpos($this->data, "\x0", $position);
            $position   = $commentPos + 1;
        }

        if ($info['FLG'] & self::FLAGS['FHCRC']) {
            $hcrc = unpack('vCRC', $this->data, $position);
            $hcrc = $hcrc['CRC'];
            $position += 2;
        }

        return $position;
    }
}
