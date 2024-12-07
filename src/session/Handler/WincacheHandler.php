<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Handler;

use Flexis\Session\HandlerInterface;

/**
 * Обработчик хранилища сессии Wincache.
 */
class WincacheHandler extends \SessionHandler implements HandlerInterface {
    /**
     * Конструктор.
     */
    public function __construct() {
        if (!headers_sent()) {
            ini_set('session.save_handler', 'wincache');
        }
    }

    /**
     * Проверяет, доступен ли HandlerInterface.
     *
     * @return  boolean  True в случае успеха, иначе false.
     */
    public static function isSupported(): bool {
        return \extension_loaded('wincache') && \function_exists('wincache_ucache_get') && !strcmp(ini_get('wincache.ucenabled'), '1');
    }
}
