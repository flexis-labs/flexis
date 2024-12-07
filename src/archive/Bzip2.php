<?php

/**
 * Часть пакета Flexis Archive Framework.
 */

namespace Flexis\Archive;

use ArrayAccess;
use Flexis\Filesystem\File;
use Flexis\Filesystem\Stream;

/**
 * Адаптер формата Bzip2 для пакета Archive
 */
class Bzip2 implements ExtractableInterface {
    /**
     * Буфер данных файла Bzip2
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
     * @param   array|ArrayAccess  $options  Множество вариантов.
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
     * Извлекает сжатый файл Bzip2 по заданному пути.
     *
     * @param   string  $archive      Путь к архиву Bzip2 для распаковки.
     * @param   string  $destination  Путь для распаковки архива.
     *
     * @return  boolean  True в случае успеха.
     *
     * @throws  \RuntimeException
     */
    public function extract(string $archive, string$destination): bool {
        $this->data = null;

        if (!isset($this->options['use_streams']) || !$this->options['use_streams']) {
            $this->data = file_get_contents($archive);

            if (!$this->data) {
                throw new \RuntimeException('Не удалось прочитать архив');
            }

            $buffer = bzdecompress($this->data);
            unset($this->data);

            if (empty($buffer)) {
                throw new \RuntimeException('Невозможно распаковать данные');
            }

            if (!File::write($destination, $buffer)) {
                throw new \RuntimeException('Невозможно записать архив в файл ' . $destination);
            }
        } else {
            $input = Stream::getStream();

            $input->set('processingmethod', 'bz');

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
        return \extension_loaded('bz2');
    }
}
