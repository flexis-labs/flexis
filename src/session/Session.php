<?php

/**
 * Часть пакета Flexis Session Framework.
 */

namespace Flexis\Session;

use Flexis\Event\DispatcherAwareInterface;
use Flexis\Event\DispatcherAwareTrait;
use Flexis\Event\DispatcherInterface;
use Random\RandomException;

/**
 * Класс для управления HTTP-сессиями.
 *
 * Предоставляет доступ к значениям состояния сессии, а также к настройкам уровня сессии и методам управления сроком службы.
 * Основанный на стандартном механизме обработки сессии PHP, он предоставляет более продвинутые функции, такие как тайм-ауты истечения срока действия.
 */
class Session implements SessionInterface, DispatcherAwareInterface {
    use DispatcherAwareTrait;

    /**
     * Состояние внутреннего сессии.
     *
     * @var    string
     */
    protected string $state = SessionState::INACTIVE;

    /**
     * Максимальный срок неиспользованной сессии в секундах.
     *
     * @var    integer
     */
    protected int $expire = 900;

    /**
     * Объект хранилища сессии.
     *
     * @var    StorageInterface
     */
    protected StorageInterface $store;

    /**
     * Валидаторы сессии хранения контейнеров.
     *
     * @var    ValidatorInterface[]
     */
    protected array $sessionValidators = [];

    /**
     * Конструктор.
     *
     * @param   ?StorageInterface     $store       Реализация StorageInterface.
     * @param   ?DispatcherInterface  $dispatcher  DispatcherInterface для используемого сессии.
     * @param   array                 $options     Необязательные параметры. Поддерживаемые ключи включают в себя:
     *                                             - name: Имя сессии
     *                                             - id: Идентификатор сессии
     *                                             - expire: Время жизни сессии в секундах
     */
    public function __construct(
        ?StorageInterface $store = null,
        ?DispatcherInterface $dispatcher = null,
        array $options = []
    ) {

        $this->store = $store ?: new Storage\NativeStorage(new Handler\FilesystemHandler());

        if ($dispatcher) {
            $this->setDispatcher($dispatcher);
        }

        $this->setOptions($options);

        $this->setState(SessionState::INACTIVE);
    }

    /**
     * Добавляет валидатор в сессию.
     *
     * @param   ValidatorInterface  $validator  Валидатор сессии.
     *
     * @return  void
     */
    public function addValidator(ValidatorInterface $validator): void {
        $this->sessionValidators[] = $validator;
    }

    /**
     * Возвращает срок годности в секундах.
     *
     * @return  integer  Время окончания сессии в секундах.
     */
    public function getExpire(): int {
        return $this->expire;
    }

    /**
     * Возвращает текущее состояние сессии.
     *
     * @return  string  Состояние сессии.
     */
    public function getState(): string {
        return $this->state;
    }

    /**
     * Возвращает токен сессии.
     *
     * Токены используются для защиты форм от спам-атак.
     * После создания токена система проверит запрос на наличие его;
     * иначе сессия будет признана недействительной.
     *
     * @param boolean $forceNew Если true, принудительно создаётся новый токен.
     *
     * @return  string
     * @throws RandomException
     */
    public function getToken(bool $forceNew = false): string {
        if (!$this->has('session.token') || $forceNew) {
            $this->set('session.token', $this->createToken());
        }

        return $this->get('session.token');
    }

    /**
     * Проверяет, имеет ли сессия данный токен.
     *
     * @param   string   $token        Хешированный токен, подлежащий проверке.
     * @param   boolean  $forceExpire  Если true, истекает срок действия сессии.
     *
     * @return  boolean
     */
    public function hasToken(string $token, bool $forceExpire = true): bool {
        $result = $this->get('session.token') === $token;

        if (!$result && $forceExpire) {
            $this->setState(SessionState::EXPIRED);
        }

        return $result;
    }

    /**
     * Возвращает внешний итератор.
     *
     * @return  \ArrayIterator  Верните ArrayIterator $_SESSION.
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): \ArrayIterator {
        return new \ArrayIterator($this->all());
    }

    /**
     * Возвращает имя сессии.
     *
     * @return  string  Имя сессии.
     */
    public function getName(): string {
        return $this->store->getName();
    }

    /**
     * Устанавливает имя сессии.
     *
     * @param   string  $name  Имя сессии.
     *
     * @return  $this
     */
    public function setName(string $name): self {
        $this->store->setName($name);

        return $this;
    }

    /**
     * Возвращает идентификатор сессии.
     *
     * @return  string  Идентификатор сессии.
     */
    public function getId(): string {
        return $this->store->getId();
    }

    /**
     * Устанавливает идентификатор сессии.
     *
     * @param   string  $id  Идентификатор сессии.
     *
     * @return  $this
     */
    public function setId(string $id): self {
        $this->store->setId($id);

        return $this;
    }

    /**
     * Проверяет, активна ли сессия.
     *
     * @return  boolean
     */
    public function isActive(): bool {
        if ($this->getState() === SessionState::ACTIVE) {
            return $this->store->isActive();
        }

        return false;
    }

    /**
     * Проверяет, создана ли эта сессия в данный момент.
     *
     * @return  boolean
     */
    public function isNew(): bool {
        $counter = $this->get('session.counter');

        return $counter === 1;
    }

    /**
     * Проверяет, запущена ли сессия.
     *
     * @return  boolean
     */
    public function isStarted(): bool {
        return $this->store->isStarted();
    }

    /**
     * Возвращает данные из хранилища сессии.
     *
     * @param   string  $name     Имя переменной.
     * @param   mixed   $default  Значение переменной по умолчанию, если оно не установлено.
     *
     * @return  mixed  Значение переменной
     */
    public function get(string $name, mixed $default = null): mixed {
        if (!$this->isActive()) {
            $this->start();
        }

        return $this->store->get($name, $default);
    }

    /**
     * Устанавливает данные в хранилище сессии.
     *
     * @param   string  $name   Имя переменной.
     * @param   mixed   $value Значение переменной.
     *
     * @return  mixed  Старое значение переменной.
     */
    public function set(string $name, mixed $value = null): mixed {
        if (!$this->isActive()) {
            $this->start();
        }

        return $this->store->set($name, $value);
    }

    /**
     * Проверяет, существуют ли данные в хранилище сессии.
     *
     * @param   string  $name  Имя переменной.
     *
     * @return  boolean  True если переменная существует.
     */
    public function has(string $name): bool {
        if (!$this->isActive()) {
            $this->start();
        }

        return $this->store->has($name);
    }

    /**
     * Удаляет переменную из хранилища сессии.
     *
     * @param   string  $name  Имя переменной.
     *
     * @return  mixed   Значение из сессии или NULL, если не установлено.
     */
    public function remove(string $name): mixed {
        if (!$this->isActive()) {
            $this->start();
        }

        return $this->store->remove($name);
    }

    /**
     * Очищает все переменные из хранилища сессии.
     *
     * @return  void
     */
    public function clear(): void {
        if (!$this->isActive()) {
            $this->start();
        }

        $this->store->clear();
    }

    /**
     * Извлекает все переменные из хранилища сессии.
     *
     * @return  array
     */
    public function all(): array {
        if (!$this->isActive()) {
            $this->start();
        }

        return $this->store->all();
    }

    /**
     * Начать сессию.
     *
     * @return  void
     */
    public function start(): void {
        if ($this->isStarted()) {
            return;
        }

        $this->store->start();

        $this->setState(SessionState::ACTIVE);
        $this->setCounter();
        $this->setTimers();

        if (!$this->validate()) {
            if ($this->getState() === SessionState::EXPIRED) {
                $this->restart();
            } else {
                $this->destroy();
            }
        }

        $this->dispatcher?->dispatch(new SessionEvent(SessionEvents::START, $this));
    }

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
    public function destroy(): bool {
        if ($this->getState() === SessionState::DESTROYED) {
            return true;
        }

        $this->clear();
        $this->fork(true);

        $this->setState(SessionState::DESTROYED);

        return true;
    }

    /**
     * Перезапускает истекающую или заблокированную сессию.
     *
     * @return  boolean
     *
     * @see     destroy
     */
    public function restart(): bool {
        $data = $this->all();

        $this->destroy();

        if ($this->getState() !== SessionState::DESTROYED) {
            return false;
        }

        $this->store->start();

        $this->setState(SessionState::ACTIVE);
        $this->setCounter();
        $this->setTimers();

        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }

        if (!$this->validate(true)) {
            $this->destroy();
        }

        $this->dispatcher?->dispatch(new SessionEvent(SessionEvents::RESTART, $this));

        return true;
    }

    /**
     * Создаёт новую сессию и скопирует переменные из старой.
     *
     * @param   boolean  $destroy  Удалить старую сессию или оставить его для сборки мусора.
     *
     * @return  boolean
     */
    public function fork(bool $destroy = false): bool {
        $result = $this->store->regenerate($destroy);

        if ($result) {
            $this->setTimers();
        }

        return $result;
    }

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
    public function close(): void {
        $this->store->close();
        $this->setState(SessionState::CLOSED);
    }

    /**
     * Выполняет сбор мусора данных сессии.
     *
     * @return  integer|boolean  Количество удаленных сессий в случае успеха или логическое значение false в случае сбоя или если функция не поддерживается.
     *
     * @see     session_gc()
     */
    public function gc(): int|bool {
        if (!$this->isActive()) {
            $this->start();
        }

        return $this->store->gc();
    }

    /**
     * Прерывает текущую сессию.
     *
     * @return  boolean
     *
     * @see     session_abort()
     */
    public function abort(): bool {
        if (!$this->isActive()) {
            return true;
        }

        return $this->store->abort();
    }

    /**
     * Создаёт строку токена.
     *
     * @return  string
     *
     * @throws RandomException
     */
    protected function createToken(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * Установить счетчик использования сессии.
     *
     * @return  boolean
     */
    protected function setCounter(): bool {
        $counter = $this->get('session.counter', 0);
        $counter++;

        $this->set('session.counter', $counter);

        return true;
    }

    /**
     * Устанавливает срок действия сессии.
     *
     * @param   integer  $expire  Максимальный срок неиспользованной сессии в секундах.
     *
     * @return  $this
     */
    protected function setExpire(int $expire): self {
        $this->expire = $expire;

        return $this;
    }

    /**
     * Устанавливает состояние сессии.
     *
     * @param   string  $state  Внутреннее состояние.
     *
     * @return  $this
     */
    protected function setState(string $state): self {
        $this->state = $state;

        return $this;
    }

    /**
     * Устанавливает таймеры сессии.
     *
     * @return  boolean
     */
    protected function setTimers(): bool {
        if (!$this->has('session.timer.start')) {
            $start = time();

            $this->set('session.timer.start', $start);
            $this->set('session.timer.last', $start);
            $this->set('session.timer.now', $start);
        }

        $this->set('session.timer.last', $this->get('session.timer.now'));
        $this->set('session.timer.now', time());

        return true;
    }

    /**
     * Устанавливает дополнительные параметры сессии.
     *
     * @param   array  $options  Список параметров.
     *
     * @return  boolean
     */
    protected function setOptions(array $options): bool {
        if (isset($options['name'])) {
            $this->setName($options['name']);
        }

        if (isset($options['id'])) {
            $this->setId($options['id']);
        }

        if (isset($options['expire'])) {
            $this->setExpire($options['expire']);
        }

        if (!headers_sent()) {
            ini_set('session.gc_maxlifetime', $this->getExpire());
        }

        return true;
    }

    /**
     * Создаёт несколько проверок в целях безопасности.
     *
     * Если одна проверка не удалась, данные сессии необходимо очистить.
     *
     * @param   boolean  $restart  Повторно активировать сессию.
     *
     * @return  boolean
     *
     * @see     http://shiflett.org/articles/the-truth-about-sessions
     */
    protected function validate(bool $restart = false): bool {
        if ($restart) {
            $this->setState(SessionState::ACTIVE);
        }

        if ($this->expire) {
            $curTime = $this->get('session.timer.now', 0);
            $maxTime = $this->get('session.timer.last', 0) + $this->expire;

            if ($maxTime < $curTime) {
                $this->setState(SessionState::EXPIRED);

                return false;
            }
        }

        try {
            foreach ($this->sessionValidators as $validator) {
                $validator->validate($restart);
            }
        } catch (Exception\InvalidSessionException $e) {
            $this->setState(SessionState::ERROR);

            return false;
        }

        return true;
    }
}
