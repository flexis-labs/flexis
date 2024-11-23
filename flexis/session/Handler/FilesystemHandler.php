<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Handler;

use Flexis\Session\HandlerInterface;

/**
 * Обработчик хранилища сессии файловой системы.
 */
class FilesystemHandler extends \SessionHandler implements HandlerInterface {
    /**
     * Конструктор.
     *
     * @param   string  $path  Путь к каталогу для сохранения файлов сессии.
     *                         Оставьте значение null, чтобы использовать путь, настроенный PHP.
     *
     * @throws  \InvalidArgumentException
     * @throws  \RuntimeException
     */
    public function __construct(string $path = '') {
        $pathConfig = ini_get('session.save_path');

        if (empty($path) && empty($pathConfig)) {
            throw new \InvalidArgumentException('Неверный аргумент $path');
        }

        if (empty($path) || $path === $pathConfig) {
            if (!headers_sent()) {
                ini_set('session.save_handler', 'files');
            }

            return;
        }

        $baseDir = $path;

        if ($count = substr_count($path, ';')) {
            if ($count > 2) {
                throw new \InvalidArgumentException(sprintf('Неверный аргумент $path "%s"', $path));
            }

            $baseDir = ltrim(strrchr($path, ';'), ';');
        }

        if (!is_dir($baseDir)) {
            if (!mkdir($baseDir, 0755)) {
                throw new \RuntimeException(sprintf('Не удалось создать каталог сессии "%s"', $baseDir));
            }
        }

        if (!headers_sent()) {
            ini_set('session.save_path', $path);
            ini_set('session.save_handler', 'files');
        }
    }

    /**
     * Проверяет, доступен ли HandlerInterface.
     *
     * @return  boolean  True в случае успеха, иначе false.
     */
    public static function isSupported(): bool {
        return true;
    }
}
