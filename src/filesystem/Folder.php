<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem;

use Flexis\Filesystem\Exception\FilesystemException;

/**
 * Класс обработки папок.
 */
abstract class Folder {
    /**
     * Копирование папки.
     *
     * @param   string   $src         Путь к исходной папке.
     * @param   string   $dest        Путь к папке назначения.
     * @param   string   $path        Необязательный базовый путь для префикса к именам файлов.
     * @param   boolean  $force       Принудительное копирование.
     * @param   boolean  $useStreams  При необходимости принудительно перезапишите папку/файл.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public static function copy(string $src, string $dest, string $path = '', bool $force = false, bool $useStreams = false): bool {
        if (\function_exists('set_time_limit')) {
            set_time_limit(ini_get('max_execution_time'));
        }

        if ($path) {
            $src  = Path::clean($path . '/' . $src);
            $dest = Path::clean($path . '/' . $dest);
        }

        $src  = rtrim($src, \DIRECTORY_SEPARATOR);
        $dest = rtrim($dest, \DIRECTORY_SEPARATOR);

        if (!is_dir(Path::clean($src))) {
            throw new FilesystemException('Исходная папка не найдена.', -1);
        }

        if (is_dir(Path::clean($dest)) && !$force) {
            throw new FilesystemException('Папка назначения не найдена.', -1);
        }

        if (!self::create($dest)) {
            throw new FilesystemException('Невозможно создать папку назначения.', -1);
        }

        if (!($dh = @opendir($src))) {
            throw new FilesystemException('Невозможно открыть исходную папку.', -1);
        }

        while (($file = readdir($dh)) !== false) {
            $sfid = $src . '/' . $file;
            $dfid = $dest . '/' . $file;

            switch (filetype($sfid)) {
                case 'dir':
                    if ($file != '.' && $file != '..') {
                        $ret = self::copy($sfid, $dfid, null, $force, $useStreams);

                        if ($ret !== true) {
                            return $ret;
                        }
                    }

                    break;

                case 'file':
                    if ($useStreams) {
                        Stream::getStream()->copy($sfid, $dfid);
                    } else {
                        if (!@copy($sfid, $dfid)) {
                            throw new FilesystemException('Не удалось скопировать файл.', -1);
                        }
                    }

                    break;
            }
        }

        return true;
    }

    /**
     * Создаёт папку и все необходимые родительские папки.
     *
     * @param   string   $path  Путь, который нужно создать на основе базового пути.
     * @param   integer  $mode  Разрешения каталога, которые можно установить для созданных папок. 0755 по умолчанию.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     */
    public static function create(string $path = '', int $mode = 0755): bool {
        static $nested = 0;

        $path = Path::clean($path);

        $parent = \dirname($path);

        if (!is_dir(Path::clean($parent))) {
            $nested++;

            if (($nested > 20) || ($parent == $path)) {
                throw new FilesystemException(__METHOD__ . ': Обнаружен бесконечный цикл.');
            }

            try {
                if (self::create($parent, $mode) !== true) {
                    $nested--;

                    return false;
                }
            } catch (FilesystemException $exception) {
                $nested--;

                throw $exception;
            }

            $nested--;
        }

        if (is_dir(Path::clean($path))) {
            return true;
        }

        $obd = ini_get('open_basedir');

        if ($obd != null) {
            if (\defined('PHP_WINDOWS_VERSION_MAJOR')) {
                $obdSeparator = ';';
            } else {
                $obdSeparator = ':';
            }

            $obdArray  = explode($obdSeparator, $obd);
            $inBaseDir = false;

            foreach ($obdArray as $test) {
                $test = Path::clean($test);

                if (str_starts_with($path, $test) || str_starts_with($path, realpath($test))) {
                    $inBaseDir = true;

                    break;
                }
            }

            if (!$inBaseDir) {
                throw new FilesystemException(__METHOD__ . ': Путь отсутствует в путях open_basedir.');
            }
        }

        $origmask = @umask(0);

        if (!$ret = @mkdir($path, $mode)) {
            @umask($origmask);

            throw new FilesystemException(__METHOD__ . ': Не удалось создать каталог. Путь: ' . $path);
        }

        @umask($origmask);

        return $ret;
    }

    /**
     * Удаляет папку.
     *
     * @param   string  $path  Путь к папке, которую необходимо удалить.
     *
     * @return  boolean
     *
     * @throws  FilesystemException
     * @throws  \UnexpectedValueException
     */
    public static function delete(string $path): bool {
        if (\function_exists('set_time_limit')) {
            set_time_limit(ini_get('max_execution_time'));
        }

        if (!$path) {
            throw new FilesystemException(__METHOD__ . ': Вы не можете удалить базовый каталог.');
        }

        $path = Path::clean($path);

        if (!is_dir($path)) {
            throw new \UnexpectedValueException(
                sprintf(
                    '%1$s: Путь не является папкой. Путь: %2$s',
                    __METHOD__,
                    Path::removeRoot($path)
                )
            );
        }

        $files = self::files($path, '.', false, true, [], []);

        if (!empty($files)) {
            if (File::delete($files) !== true) {
                return false;
            }
        }

        $folders = self::folders($path, '.', false, true, [], []);

        foreach ($folders as $folder) {
            if (is_link($folder)) {
                if (File::delete($folder) !== true) {
                    return false;
                }
            } elseif (self::delete($folder) !== true) {
                return false;
            }
        }

        if (@rmdir($path)) {
            return true;
        }

        throw new FilesystemException(sprintf('%1$s: Не удалось удалить папку. Путь: %2$s', __METHOD__, $path));
    }

    /**
     * Перемещает папку.
     *
     * @param   string   $src         Путь к исходной папке.
     * @param   string   $dest        Путь к папке назначения.
     * @param   string   $path        Необязательный базовый путь для префикса к именам файлов.
     * @param   boolean  $useStreams  При желании используйте потоки.
     *
     * @return  string|boolean  Сообщение об ошибке - false или логическое true в случае успеха.
     *
     */
    public static function move(
        string $src,
        string $dest,
        string $path = '',
        bool $useStreams = false
    ): string|bool {

        if ($path) {
            $src  = Path::clean($path . '/' . $src);
            $dest = Path::clean($path . '/' . $dest);
        }

        if (!is_dir(Path::clean($src))) {
            return 'Не могу найти исходную папку.';
        }

        if (is_dir(Path::clean($dest))) {
            return 'Папка уже существует.';
        }

        if ($useStreams) {
            Stream::getStream()->move($src, $dest);

            return true;
        }

        if (!@rename($src, $dest)) {
            return 'Переименование не удалось.';
        }

        return true;
    }

    /**
     * Утилита для чтения файлов в папке.
     *
     * @param   string   $path           Путь к папке для чтения.
     * @param   string   $filter         Фильтр имен файлов.
     * @param   boolean  $recurse        True для рекурсивного поиска во вложенных папках или целое число для указания максимальной глубины.
     * @param   boolean  $full           True, чтобы вернуть полный путь к файлу.
     * @param   array    $exclude        Массив с именами файлов, которые не должны отображаться в результате.
     * @param   array    $excludeFilter  Массив фильтра для исключения.
     * @param   boolean  $naturalSort    False для сортировки, верно для естественной сортировки.
     *
     * @return  array  Файлы в указанной папке.
     *
     * @throws  \UnexpectedValueException
     */
    public static function files(
        string $path,
        string $filter = '.',
        bool $recurse = false,
        bool $full = false,
        array $exclude = ['.svn', 'CVS', '.DS_Store', '__MACOSX'],
        array $excludeFilter = ['^\..*', '.*~'],
        bool $naturalSort = false
    ) {

        $path = Path::clean($path);

        if (!is_dir($path)) {
            throw new \UnexpectedValueException(
                sprintf(
                    '%1$s: Путь не является папкой. Путь: %2$s',
                    __METHOD__,
                    Path::removeRoot($path)
                )
            );
        }

        if (\count($excludeFilter)) {
            $excludeFilterString = '/(' . implode('|', $excludeFilter) . ')/';
        } else {
            $excludeFilterString = '';
        }

        $arr = self::_items($path, $filter, $recurse, $full, $exclude, $excludeFilterString, true);

        if ($naturalSort) {
            natsort($arr);
        } else {
            asort($arr);
        }

        return array_values($arr);
    }

    /**
     * Утилита для чтения папок в папке.
     *
     * @param   string   $path           Путь к папке для чтения.
     * @param   string   $filter         Фильтр имен папок.
     * @param   boolean  $recurse        True для рекурсивного поиска во вложенных папках или целое число для указания максимальной глубины.
     * @param   boolean  $full           True, чтобы вернуть полный путь к папкам.
     * @param   array    $exclude        Массив с именами папок, которые не должны отображаться в результате.
     * @param   array    $excludeFilter  Массив с регулярными выражениями, соответствующими папкам, которые не должны отображаться в результате.
     *
     * @return  array  Папки в данной папке.
     *
     * @throws  \UnexpectedValueException
     */
    public static function folders(
        string $path,
        string $filter = '.',
        bool $recurse = false,
        bool $full = false,
        array $exclude = ['.svn', 'CVS', '.DS_Store', '__MACOSX'],
        array $excludeFilter = ['^\..*']
    ): array {

        $path = Path::clean($path);

        if (!is_dir($path)) {
            throw new \UnexpectedValueException(
                sprintf(
                    '%1$s: Путь не является папкой. Путь: %2$s',
                    __METHOD__,
                    Path::removeRoot($path)
                )
            );
        }

        if (\count($excludeFilter)) {
            $excludeFilterString = '/(' . implode('|', $excludeFilter) . ')/';
        } else {
            $excludeFilterString = '';
        }

        $arr = self::_items($path, $filter, $recurse, $full, $exclude, $excludeFilterString, false);
        asort($arr);

        return array_values($arr);
    }

    /**
     * Функция чтения файлов/папок в папке.
     *
     * @param   string   $path                 Путь к папке для чтения.
     * @param   string   $filter               Фильтр имен файлов.
     * @param   boolean  $recurse              True для рекурсивного поиска во вложенных папках или целое число для указания максимальной глубины.
     * @param   boolean  $full                 True, чтобы вернуть полный путь к файлу.
     * @param   array    $exclude              Массив с именами файлов, которые не должны отображаться в результате.
     * @param   string   $excludeFilterString  Регулярное выражение файлов для исключения
     * @param   boolean  $findfiles            True для чтения файлов, false для чтения папок
     *
     * @return  array  Файлы.
     *
     */
    protected static function _items(
        string $path,
        string $filter,
        bool $recurse,
        bool $full,
        array $exclude,
        string $excludeFilterString,
        bool $findfiles
    ): array {
        if (\function_exists('set_time_limit')) {
            set_time_limit(ini_get('max_execution_time'));
        }

        $arr = [];

        if (!($handle = @opendir($path))) {
            return $arr;
        }

        while (($file = readdir($handle)) !== false) {
            if (
                $file != '.' && $file != '..' && !\in_array($file, $exclude)
                && (empty($excludeFilterString) || !preg_match($excludeFilterString, $file))
            ) {
                $fullpath = Path::clean($path . '/' . $file);
                $isDir    = is_dir($fullpath);

                if (($isDir xor $findfiles) && preg_match("/$filter/", $file)) {
                    if ($full) {
                        $arr[] = $fullpath;
                    } else {
                        $arr[] = $file;
                    }
                }

                if ($isDir && $recurse) {
                    if (\is_int($recurse)) {
                        $arr = array_merge($arr, self::_items($fullpath, $filter, $recurse - 1, $full, $exclude, $excludeFilterString, $findfiles));
                    } else {
                        $arr = array_merge($arr, self::_items($fullpath, $filter, $recurse, $full, $exclude, $excludeFilterString, $findfiles));
                    }
                }
            }
        }

        closedir($handle);

        return $arr;
    }

    /**
     * Папка списков в формате, подходящем для отображения в виде дерева.
     *
     * @param   string   $path      Путь к папке для чтения.
     * @param   string   $filter    Фильтр имен папок.
     * @param   integer  $maxLevel  Максимальное количество уровней для рекурсивного чтения по умолчанию равно трем.
     * @param   integer  $level     Текущий уровень, необязательно.
     * @param   integer  $parent    Уникальный идентификатор родительской папки, если таковой имеется.
     *
     * @return  array  Папки в данной папке.
     *
     */
    public static function listFolderTree(
        string $path,
        string $filter,
        int $maxLevel = 3,
        int $level = 0,
        int $parent = 0
    ): array {

        $dirs = [];

        if ($level == 0) {
            $GLOBALS['_Flexis_folder_tree_index'] = 0;
        }

        if ($level < $maxLevel) {
            $folders = self::folders($path, $filter);

            foreach ($folders as $name) {
                $id       = ++$GLOBALS['_Flexis_folder_tree_index'];
                $fullName = Path::clean($path . '/' . $name);
                $dirs[]   = [
                    'id'       => $id,
                    'parent'   => $parent,
                    'name'     => $name,
                    'fullname' => $fullName,
                    'relname'  => str_replace(PATH_ROOT, '', $fullName),
                ];
                $dirs2 = self::listFolderTree($fullName, $filter, $maxLevel, $level + 1, $id);
                $dirs  = array_merge($dirs, $dirs2);
            }
        }

        return $dirs;
    }

    /**
     * Делает имя пути безопасным для использования.
     *
     * @param   string  $path  Полный путь.
     *
     * @return  string  Безопасная строка.
     *
     */
    public static function makeSafe(string $path): string {
        $regex = ['#[^A-Za-zА-Яа-яЁё0-9_\\\/\(\)\[\]\{\}\#\$\^\+\.\'~`!@&=;,-]#'];
        return preg_replace($regex, '', $path);
    }
}
