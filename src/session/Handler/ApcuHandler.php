<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Handler;

use Flexis\Session\HandlerInterface;

/**
 * Обработчик хранилища сессии APCu.
 */
class ApcuHandler implements HandlerInterface {
    /**
     * Префикс идентификатора сессии, чтобы избежать конфликтов имен.
     *
     * @var    string
     */
    private string $prefix;

    /**
     * Конструктор.
     *
     * @param   array  $options  Ассоциативный массив опций для настройки обработчика.
     */
    public function __construct(array $options = []) {
        $this->prefix = $options['prefix'] ?? 'sfx';
    }

    /**
     * Закрывает сессию.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    #[\ReturnTypeWillChange]
    public function close(): bool {
        return true;
    }

    /**
     * Уничтожить сессию.
     *
     * @param   string  $id  Идентификатор сессии уничтожается.
     *
     * @return  boolean  True в случае успеха, иначе false.
     */
    public function destroy(string $id): bool {
        return apcu_delete($this->prefix . $id) || !apcu_exists($this->prefix . $id);
    }

    /**
     * Очистка старых сессий.
     *
     * @param   integer  $maxlifetime  Сессии, которые не обновлялись в течение последних секунд maxlifetime, будут удалены.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    #[\ReturnTypeWillChange]
    public function gc(int $maxlifetime): bool {
        return true;
    }

    /**
     * Проверяет, доступен ли HandlerInterface.
     *
     * @return  boolean  True в случае успеха, иначе false.
     */
    public static function isSupported(): bool {
        $supported = \extension_loaded('apcu') && ini_get('apc.enabled');

        if ($supported && PHP_SAPI === 'cli') {
            $supported = ini_get('apc.enable_cli');
        }

        return (bool) $supported;
    }

    /**
     * Инициализировать сессию.
     *
     * @param   string  $save_path   Путь для хранения/извлечения сессии.
     * @param   string  $session_id  Идентификатор сессии.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    #[\ReturnTypeWillChange]
    public function open(string $save_path, string $session_id): bool {
        return true;
    }

    /**
     * Чтение данных сессии.
     *
     * @param   string  $session_id  Идентификатор сессии для чтения данных.
     *
     * @return  string  Данные сессии.
     */
    #[\ReturnTypeWillChange]
    public function read(string $session_id): string {
        return (string) apcu_fetch($this->prefix . $session_id);
    }

    /**
     * Записывает данные сессии.
     *
     * @param   string  $session_id    Идентификатор сессии.
     * @param   string  $session_data  Закодированные данные сессии.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    #[\ReturnTypeWillChange]
    public function write(string $session_id, string $session_data): bool {
        return apcu_store($this->prefix . $session_id, $session_data, ini_get('session.gc_maxlifetime'));
    }
}
