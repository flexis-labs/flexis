<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Handler;

use Flexis\Session\HandlerInterface;

/**
 * Обработчик хранилища сессии Memcached.
 */
class MemcachedHandler implements HandlerInterface {
    /**
     * Драйвер Memcached.
     *
     * @var    \Memcached
     */
    private \Memcached $memcached;

    /**
     * Префикс идентификатора сессии, чтобы избежать конфликтов имен.
     *
     * @var    string
     */
    private string $prefix;

    /**
     * Время жить в секундах.
     *
     * @var    integer
     */
    private int $ttl;

    /**
     * Конструктор.
     *
     * @param   \Memcached  $memcached  Экземпляр Memcached.
     * @param   array       $options    Ассоциативный массив опций для настройки обработчика.
     */
    public function __construct(\Memcached $memcached, array $options = []) {
        $this->memcached = $memcached;
        $this->ttl = isset($options['ttl']) ? (int) $options['ttl'] : 900;
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
     * @return  boolean  True в случае успеха, иначе — false.
     */
    public function destroy(string $id): bool {
        return $this->memcached->delete($this->prefix . $id);
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
     * @return  boolean  True в случае успеха, иначе — false.
     */
    public static function isSupported(): bool {
        return class_exists('Memcached');
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
        return $this->memcached->get($this->prefix . $session_id) ?: '';
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
        return $this->memcached->set($this->prefix . $session_id, $session_data, time() + $this->ttl);
    }
}
