<?php

/**
 * Часть пакета Flexis Archive Framework.
 */

namespace Flexis\Archive;

use ArrayAccess;
use Flexis\Archive\Exception\UnknownArchiveException;
use Flexis\Archive\Exception\UnsupportedArchiveException;
use Flexis\Filesystem\File;
use Flexis\Filesystem\Folder;

/**
 * Класс обработки архива
 */
class Archive {
    /**
     * Массив созданных адаптеров архива.
     *
     * @var    ExtractableInterface[]
     */
    protected array $adapters = [];

    /**
     * Содержит массив параметров.
     *
     * @var    array|ArrayAccess
     */
    public array|ArrayAccess $options = [];

    /**
     * Создаёт новый объект «Архив».
     *
     * @param   array|ArrayAccess  $options  Параметры.
     *
     * @throws  \InvalidArgumentException
     */
    public function __construct(array|ArrayAccess $options = []) {
        if (!\is_array($options) && !($options instanceof ArrayAccess)) {
            throw new \InvalidArgumentException(
                'Параметр options должен быть массивом или реализовывать интерфейс ArrayAccess.'
            );
        }

        isset($options['tmp_path']) || $options['tmp_path'] = realpath(sys_get_temp_dir());

        $this->options = $options;
    }

    /**
     * Извлекает архивный файл в каталог.
     *
     * @param   string  $archivename  Имя файла архива.
     * @param   string  $extractdir   Каталог для распаковки.
     *
     * @return  boolean  True если успех.
     *
     * @throws  UnknownArchiveException если тип архива не поддерживается.
     */
    public function extract(string $archivename, string $extractdir): bool {
        $ext      = pathinfo($archivename, \PATHINFO_EXTENSION);
        $path     = pathinfo($archivename, \PATHINFO_DIRNAME);
        $filename = pathinfo($archivename, \PATHINFO_FILENAME);

        switch (strtolower($ext)) {
            case 'zip':
                $result = $this->getAdapter('zip')->extract($archivename, $extractdir);

                break;

            case 'tar':
                $result = $this->getAdapter('tar')->extract($archivename, $extractdir);

                break;

            case 'tgz':
            case 'gz':
            case 'gzip':
                $tmpfname = $this->options['tmp_path'] . '/' . uniqid('gzip');

                try {
                    $this->getAdapter('gzip')->extract($archivename, $tmpfname);
                } catch (\RuntimeException $exception) {
                    @unlink($tmpfname);

                    return false;
                }

                if ($ext === 'tgz' || stripos($filename, '.tar') !== false) {
                    $result = $this->getAdapter('tar')->extract($tmpfname, $extractdir);
                } else {
                    Folder::create($extractdir);
                    $result = File::copy($tmpfname, $extractdir . '/' . $filename, null, false);
                }

                @unlink($tmpfname);

                break;

            case 'tbz2':
            case 'bz2':
            case 'bzip2':
                $tmpfname = $this->options['tmp_path'] . '/' . uniqid('bzip2');

                try {
                    $this->getAdapter('bzip2')->extract($archivename, $tmpfname);
                } catch (\RuntimeException $exception) {
                    @unlink($tmpfname);

                    return false;
                }

                if ($ext === 'tbz2' || stripos($filename, '.tar') !== false) {
                    $result = $this->getAdapter('tar')->extract($tmpfname, $extractdir);
                } else {
                    Folder::create($extractdir);
                    $result = File::copy($tmpfname, $extractdir . '/' . $filename);
                }

                @unlink($tmpfname);

                break;

            default:
                throw new UnknownArchiveException(sprintf('Неподдерживаемый тип архива: %s', $ext));
        }

        return $result;
    }

    /**
     * Метод переопределения предоставленного адаптера собственной реализацией.
     *
     * @param   string   $type      Имя устанавливаемого адаптера.
     * @param   string   $class     FQCN вашего класса, реализующего ExtractableInterface.
     * @param   boolean  $override  True для принудительного переопределения типа адаптера.
     *
     * @return  $this
     *
     * @throws  UnsupportedArchiveException если тип адаптера не поддерживается
     */
    public function setAdapter(string $type, string $class, bool $override = true): self {
        if ($override || !isset($this->adapters[$type])) {
            if (!\is_object($class) && !class_exists($class)) {
                throw new UnsupportedArchiveException($type, sprintf('Адаптер архива "%s" (класс "%s") не найден.', $type, $class));
            }

            if (!$class::isSupported()) {
                throw new UnsupportedArchiveException($type, sprintf('Адаптер архива "%s" (класс "%s") не поддерживается.', $type, $class));
            }

            $object = new $class($this->options);

            if (!($object instanceof ExtractableInterface)) {
                throw new UnsupportedArchiveException(
                    $type,
                    sprintf(
                        'Предоставленный адаптер "%s" (класс "%s") должен реализовывать %s',
                        $type,
                        $class,
                        ExtractableInterface::class
                    )
                );
            }

            $this->adapters[$type] = $object;
        }

        return $this;
    }

    /**
     * Возвращает адаптер сжатия файлов.
     *
     * @param   string  $type  Тип адаптера (bzip2|gzip|tar|zip).
     *
     * @return  ExtractableInterface  Адаптер требуемого типа.
     *
     */
    public function getAdapter(string $type): ExtractableInterface {
        $type = strtolower($type);

        if (!isset($this->adapters[$type])) {
            $this->setAdapter($type, __NAMESPACE__ . '\\' . ucfirst($type));
        }

        return $this->adapters[$type];
    }
}
