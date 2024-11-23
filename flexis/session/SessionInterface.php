<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session;

/**
 * Интерфейс, определяющий объект сессии Flexis.
 */
interface SessionInterface extends \IteratorAggregate {
    /**
     * Возвращает срок годности в секундах.
     *
     * @return  integer  Время окончания сессии в секундах.
     */
    public function getExpire(): int;

    /**
     * Возвращает имя сессии.
     *
     * @return  string  Имя сессии.
     */
    public function getName(): string;

    /**
     * Устанавливает имя сессии.
     *
     * @param   string  $name  Имя сессии.
     *
     * @return  $this
     */
    public function setName(string $name): self;

    /**
     * Возвращает идентификатор сессии.
     *
     * @return  string  Идентификатор сессии.
     */
    public function getId(): string;

    /**
     * Устанавливает идентификатор сессии.
     *
     * @param   string  $id  Идентификатор сессии.
     *
     * @return  $this
     */
    public function setId(string $id): self;

    /**
     * Проверяет, активна ли сессия.
     *
     * @return  boolean
     */
    public function isActive(): bool;

    /**
     * Проверяет, создана ли эта сессия заново.
     *
     * @return  boolean
     */
    public function isNew(): bool;

    /**
     * Проверяет, запущена ли сессия.
     *
     * @return  boolean
     */
    public function isStarted(): bool;

    /**
     * Возвращает токен сессии.
     *
     * Токены используются для защиты форм от спам-атак. После создания токена система проверит запрос на наличие его; иначе сессия будет признан недействительным.
     *
     * @param   boolean  $forceNew  Если true, принудительно создаётся новый токен.
     *
     * @return  string
     */
    public function getToken(bool $forceNew = false): string;

    /**
     * Проверяет, имеет ли сессия данный токен.
     *
     * @param   string   $token        Хешированный токен, подлежащий проверке.
     * @param   boolean  $forceExpire  Если true, истекает срок действия сессии.
     *
     * @return  boolean
     */
    public function hasToken(string $token, bool $forceExpire = true): bool;

    /**
     * Возвращает данные из хранилища сессии.
     *
     * @param   string  $name     Имя переменной.
     * @param   mixed   $default  Значение переменной по умолчанию, если оно не установлено.
     *
     * @return  mixed  Значение переменной.
     */
    public function get(string $name, mixed $default = null): mixed;

    /**
     * Устанавливает данные в хранилище сессии.
     *
     * @param   string  $name   Имя переменной.
     * @param   mixed   $value  Значение переменной.
     *
     * @return  mixed  Старое значение переменной.
     */
    public function set(string $name, mixed $value = null): mixed;

    /**
     * Проверяет, существуют ли данные в хранилище сессии.
     *
     * @param   string  $name  Имя переменной.
     *
     * @return  boolean  True если переменная существует.
     */
    public function has(string $name): bool;

    /**
     * Удалите переменную из хранилища сессии.
     *
     * @param   string  $name  Имя переменной.
     *
     * @return  mixed   Значение из сессии или NULL, если не установлено.
     */
    public function remove(string $name): mixed;

    /**
     * Очищает все переменные из хранилища сессии.
     *
     * @return  void
     */
    public function clear(): void;

    /**
     * Извлекает все переменные из хранилища сессии.
     *
     * @return  array
     */
    public function all(): array;

    /**
     * Начать сессию.
     *
     * @return  void
     */
    public function start(): void;

    /**
     * Освобождает все переменные сессии и уничтожает все данные, зарегистрированные в сессии.
     *
     * Этот метод сбрасывает переменную $_SESSION и уничтожает все данные,
     * связанные с текущей сессией, в его хранилище (файле или БД).
     * Это принудительно запускает новую сессию после вызова этого метода.
     *
     * @return  boolean
     *
     * @see     session_destroy()
     * @see     session_unset()
     */
    public function destroy(): bool;

    /**
     * Перезапускает истекшую или заблокированную сессию.
     *
     * @return  boolean
     *
     * @see     destroy
     */
    public function restart(): bool;

    /**
     * Создаёт новую сессию и скопируйте переменные из старого.
     *
     * @return  boolean
     */
    public function fork(): bool;

    /**
     * Записывает данные сессии и завершает её.
     *
     * Данные сессии обычно сохраняются после завершения работы вашего скрипта
     * без необходимости вызова {@link Session::close()}, но поскольку данные сессии
     * блокируются для предотвращения одновременной записи, в любой момент времени
     * в сессии может работать только один скрипт. При использовании наборов фреймов
     * вместе с сессией из-за этой блокировки фреймы будут загружаться один за другим.
     * Вы можете сократить время, необходимое для загрузки всех фреймов,
     * завершив сессию сразу после внесения всех изменений в переменные сессии.
     *
     * @return  void
     *
     * @see     session_write_close()
     */
    public function close(): void;

    /**
     * Выполняет сбор мусора данных сессии.
     *
     * @return  integer|boolean  Количество удаленных сессий в случае успеха или логическое значение
     *                           false в случае сбоя или если функция не поддерживается.
     *
     * @see     session_gc()
     */
    public function gc(): int|bool;

    /**
     * Прерывает текущую сессию.
     *
     * @return  boolean
     *
     * @see     session_abort()
     */
    public function abort(): bool;
}
