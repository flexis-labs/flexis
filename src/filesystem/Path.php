<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem;

use Flexis\Filesystem\Exception\FilesystemException;
use Random\RandomException;

/**
 * Класс обработки пути.
 */
class Path {
    /**
     * Проверяет, можно ли изменить разрешения пути.
     *
     * @param   string  $path  Путь для проверки.
     *
     * @return  boolean  True, если путь может иметь режим записи.
     *
     */
    public static function canChmod(string $path): bool {
        if (!file_exists($path)) {
            return false;
        }

        $perms = @fileperms($path);

        if ($perms !== false) {
            if (@chmod($path, $perms ^ 0001)) {
                @chmod($path, $perms);

                return true;
            }
        }

        return false;
    }

    /**
     * Chmods файлы и каталоги рекурсивно в соответствии с заданными разрешениями.
     *
     * @param   string  $path        Корневой путь для начала изменения режима [без косой черты].
     * @param   string  $filemode    Восьмеричное представление значения для изменения режима файла [null = без изменений].
     * @param   string  $foldermode  Восьмеричное представление значения для изменения режима папки [null = без изменений].
     *
     * @return  boolean  True в случае успеха [один сбой означает, что вся операция не удалась].
     *
     */
    public static function setPermissions(string $path, string $filemode = '0644', string $foldermode = '0755'): bool {
        $ret = true;

        if (is_dir($path)) {
            $dh = @opendir($path);

            if ($dh) {
                while ($file = readdir($dh)) {
                    if ($file != '.' && $file != '..') {
                        $fullpath = $path . '/' . $file;

                        if (is_dir($fullpath)) {
                            if (!static::setPermissions($fullpath, $filemode, $foldermode)) {
                                $ret = false;
                            }
                        } else {
                            if (isset($filemode)) {
                                if (!static::canChmod($fullpath) || !@ chmod($fullpath, octdec($filemode))) {
                                    $ret = false;
                                }
                            }
                        }
                    }
                }

                closedir($dh);
            }

            if (isset($foldermode)) {
                if (!static::canChmod($path) || !@ chmod($path, octdec($foldermode))) {
                    $ret = false;
                }
            }
        } else {
            if (isset($filemode)) {
                if (!static::canChmod($path) || !@ chmod($path, octdec($filemode))) {
                    $ret = false;
                }
            }
        }

        return $ret;
    }

    /**
     * Возвращает права доступа к файлу/папке по указанному пути.
     *
     * @param   string  $path  Путь к файлу/папке.
     *
     * @return  string  Разрешения файловой системы.
     *
     */
    public static function getPermissions(string $path): string {
        $path = self::clean($path);
        $mode = @ decoct(@ fileperms($path) & 0777);

        if (\strlen($mode) < 3) {
            return '---------';
        }

        $parsedMode = '';

        for ($i = 0; $i < 3; $i++) {
            // Чтение
            $parsedMode .= ($mode[$i] & 04) ? 'r' : '-';
            // Запись
            $parsedMode .= ($mode[$i] & 02) ? 'w' : '-';
            // Выполнение
            $parsedMode .= ($mode[$i] & 01) ? 'x' : '-';
        }

        return $parsedMode;
    }

    /**
     * Проверяет отслеживание за пределами корня файловой системы.
     *
     * @param   string  $path      Путь к файловой системе для проверки.
     * @param   string  $basePath  Базовый путь системы.
     *
     * @return  string  Очищенная версия пути или выход при ошибке.
     *
     * @throws  FilesystemException
     */
    public static function check(string $path, string $basePath = ''): string {
        if (str_contains($path, '..')) {
            throw new FilesystemException(
                sprintf(
                    '%s() - Использование относительных путей не разрешено.',
                    __METHOD__
                ),
                20
            );
        }

        $path = static::clean($path);

        if (($basePath != '') && !str_starts_with($path, static::clean($basePath))) {
            throw new FilesystemException(
                sprintf(
                    '%1$s() - Выход за пределы @ %2$s',
                    __METHOD__,
                    $path
                ),
                20
            );
        }

        return $path;
    }

    /**
     * Функция для удаления дополнительных символов /или \ в пути.
     *
     * @param   string  $path  Путь к чистоте.
     * @param   string  $ds    Разделитель каталогов (необязательно).
     *
     * @return  string  Расчищенный путь.
     *
     * @throws  \UnexpectedValueException Если $path не является строкой.
     */
    public static function clean(string $path, string $ds = \DIRECTORY_SEPARATOR): string {
        if ($path === '') {
            return '';
        }

        if (!\is_string($path)) {
            throw new \InvalidArgumentException('Вы должны указать непустой путь для очистки.');
        }

        $stream = explode('://', $path, 2);
        $scheme = '';
        $path   = $stream[0];

        if (\count($stream) >= 2) {
            $scheme = $stream[0] . '://';
            $path   = $stream[1];
        }

        $path = trim($path);

        if (($ds == '\\') && ($path[0] == '\\') && ($path[1] == '\\')) {
            $path = '\\' . preg_replace('#[/\\\\]+#', $ds, $path);
        } else {
            $path = preg_replace('#[/\\\\]+#', $ds, $path);
        }

        return $scheme . $path;
    }

    /**
     * Метод определения того, владеет ли сценарий путем.
     *
     * @param string $path Путь для проверки владения.
     *
     * @return  boolean  True если php-скрипт владеет переданным путем.
     *
     * @throws RandomException
     */
    public static function isOwner(string $path): bool {
        $tmp = md5(random_bytes(16));
        $ssp = ini_get('session.save_path');
        $dir = is_writable('/tmp') ? '/tmp' : false;
        $dir = !$dir && is_writable('.') ? '.' : $dir;
        $dir = !$dir && is_writable($ssp) ? $ssp : $dir;

        if ($dir) {
            $test  = $dir . '/' . $tmp;
            $blank = '';
            File::write($test, $blank, false);

            $return = fileowner($test) === fileowner($path);

            File::delete($test);

            return $return;
        }

        return false;
    }

    /**
     * Ищет пути к каталогам для данного файла.
     *
     * @param   mixed   $paths  Строка пути или массив строк пути для поиска.
     * @param   string  $file   Имя файла для поиска.
     *
     * @return  string|boolean   Полный путь и имя файла для целевого файла или логическое значение false, если файл не найден ни по одному из путей.
     *
     */
    public static function find(mixed $paths, string $file): string|bool {
        if (!\is_array($paths) && !($paths instanceof \Iterator)) {
            settype($paths, 'array');
        }

        foreach ($paths as $path) {
            $fullname = $path . '/' . $file;

            if (!str_contains($path, '://')) {
                $path     = realpath($path);
                $fullname = realpath($fullname);
            }

            if (file_exists($fullname) && str_starts_with($fullname, $path)) {
                return $fullname;
            }
        }

        return false;
    }

    /**
     * Разрешает /./, /../и несколько /в строке и возвращает результирующий абсолютный путь, вдохновленный Flysystem.
     * Удаляет конечные косые черты.
     *
     * @param   string  $path  Путь к решению.
     *
     * @return  string  Решенный путь.
     */
    public static function resolve(string $path): string {
        $path = static::clean($path);

        $startCharacter = ($path[0] === DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : '';

        $parts = [];

        foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
            switch ($part) {
                case '':
                case '.':
                    break;

                case '..':
                    if (empty($parts)) {
                        throw new FilesystemException('Путь находится за пределами определенного корня.');
                    }

                    array_pop($parts);
                    break;

                default:
                    $parts[] = $part;
                    break;
            }
        }

        return $startCharacter . implode(DIRECTORY_SEPARATOR, $parts);
    }

    /**
     * Удаляет все ссылки на путь к корневому каталогу и путь к системному tmp из сообщения.
     *
     * @param   string        $message        Сообщение, которое нужно очистить.
     * @param   string|null   $rootDirectory  Необязательный корневой каталог, по умолчанию PATH_ROOT.
     *
     * @return  string
     */
    public static function removeRoot(string $message, ?string $rootDirectory = null): string {
        if (empty($rootDirectory)) {
            $rootDirectory = PATH_ROOT;
        }

        $makePattern = static function ($dir) {
            return '~' . str_replace('~', '\\~', preg_replace('~[/\\\\]+~', '.', $dir)) . '~';
        };

        $replacements = [
            $makePattern(static::clean($rootDirectory)) => '[ROOT]',
            $makePattern(sys_get_temp_dir())            => '[TMP]',
        ];

        return preg_replace(array_keys($replacements), array_values($replacements), $message);
    }

    /**
     * Объединяет два или более путей вместе.
     *
     * @params $path1, $path2, ...
	 *
     * @return string правильный путь (с автоматически вставленным слэшей)
     *
     */
    public static function join(): string {
        $arguments = func_get_args();

        $paths = array_filter($arguments, function ($v) {
            if (is_string($v) || is_numeric($v) || is_float($v)) {
                return true;
            } else {
                throw new \FilesystemException('Аргументы должны быть строками.');
            }
        });

        return static::clean(implode(DIRECTORY_SEPARATOR, $paths));
    }

}
