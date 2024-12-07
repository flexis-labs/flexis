<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session\Handler;

use Flexis\Session\HandlerInterface;

/**
 * Обработчик хранилища сессии Redis.
 */
class RedisHandler implements HandlerInterface {
    /**
     * Префикс идентификатора сессии, чтобы избежать конфликтов имен.
     *
     * @var    string
     */
    private string $prefix;

    /**
     * Драйвер Редиса.
     *
     * @var    \Redis
     */
    private \Redis $redis;

    /**
     * Время жить в секундах.
     *
     * @var    integer
     */
    private int $ttl;

    /**
     * Конструктор.
     *
     * @param   \Redis  $redis    Экземпляр Redis.
     * @param   array   $options  Ассоциативный массив опций для настройки обработчика.
     */
    public function __construct(\Redis $redis, array $options = []) {
        $this->redis = $redis;
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
     * Уничтожает сессию, вызываемый автоматически при запуске session_regenerate_id().
     *
     * @param   string  $id  Идентификатор сессии уничтожается.
     *
     * @return  boolean  True в случае успеха, иначе — false.
     */
    public function destroy(string $id): bool {
        $this->redis->del($this->prefix . $id);

        return true;
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
        return \extension_loaded('redis') && class_exists('Redis');
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
        return $this->redis->get($this->prefix . $session_id) ?: '';
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
        if ($this->ttl > 0) {
            return $this->redis->setex($this->prefix . $session_id, $this->ttl, $session_data);
        }

        return $this->redis->set($this->prefix . $session_id, $session_data);
    }
}
