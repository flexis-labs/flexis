<?php

/**
 * Part of the Flexis Framework DI Package
 */

namespace Flexis\DIP;

use Flexis\DIP\Exception\DependencyResolutionException;
use Flexis\DIP\Exception\KeyNotFoundException;
use Flexis\DIP\Exception\ProtectedKeyException;
use Psr\Container\ContainerInterface;

/**
 * Класс контейнера.
 */
class Container implements ContainerInterface {
    /**
     * Содержит псевдонимы ключей.
     *
     * Формат:
     * 'alias' => 'key'
     *
     * @var    array
     */
    protected array $aliases = [];

    /**
     * Содержит ресурсы.
     *
     * @var    ContainerResource[]
     */
    protected array $resources = [];

    /**
     * Родительский элемент для иерархических контейнеров.
     *
     * На самом деле, это может быть любой контейнер, совместимый с PSR-11,
     * который оформляется соответственным образом.
     *
     * @var    Container|ContainerInterface|null
     */
    protected $parent;

    /**
     * Содержит сопоставление служебных тегов.
     *
     * @var    array
     */
    protected array $tags = [];

    /**
     * Конструктор для контейнера DI
     *
     * @param   ContainerInterface|null  $parent  Родительский элемент для иерархических контейнеров.
     *
     */
    public function __construct(?ContainerInterface $parent = null) {
        $this->parent = $parent;
    }

    /**
     * Извлечение ресурса.
     *
     * @param   string  $resourceName  Название ресурса, который нужно получить.
     *
     * @return  mixed  Запрашиваемый ресурс.
     *
     * @throws  KeyNotFoundException
     */
    public function get($resourceName):mixed {
        $key = $this->resolveAlias($resourceName);

        if (!isset($this->resources[$key])) {
            if ($this->parent instanceof ContainerInterface && $this->parent->has($key)) {
                return $this->parent->get($key);
            }

            throw new KeyNotFoundException(sprintf("Resource '%s' has not been registered with the container.", $resourceName));
        }

        return $this->resources[$key]->getInstance();
    }

    /**
     * Проверка, существует ли указанный ресурс.
     *
     * @param   string  $resourceName  Название ресурса для проверки.
     *
     * @return  boolean  True, если ключ определен, иначе false.
     */
    public function has(string $resourceName):bool {
        $key = $this->resolveAlias($resourceName);

        if (!isset($this->resources[$key])) {
            if ($this->parent instanceof ContainerInterface) {
                return $this->parent->has($key);
            }

            return false;
        }

        return true;
    }

    /**
     * Создаёт псевдоним для данного ключа для удобства доступа.
     *
     * @param   string  $alias  Псевдоним
     * @param   string  $key    Ключ к псевдониму
     *
     * @return  $this
     *
     */
    public function alias(string $alias, string $key):Container {
        $this->aliases[$alias] = $key;

        return $this;
    }

    /**
     * Разрешить имя ресурса.
     *
     * Если имя ресурса является псевдонимом, то возвращается соответствующий ключ.
     * Если имя ресурса не является псевдонимом, имя ресурса возвращается без изменений.
     *
     * @param   string  $resourceName  Ключ, который нужно искать.
     *
     * @return  string
     *
     */
    protected function resolveAlias(string $resourceName):string {
        return $this->aliases[$resourceName] ?? $resourceName;
    }

    /**
     * Проверка, является ли ресурс общим.
     *
     * @param   string  $resourceName  Имя ресурса для проверки.
     *
     * @return  boolean
     */
    public function isShared(string $resourceName):bool {
        return $this->hasFlag($resourceName, 'isShared', true);
    }

    /**
     * Проверка, защищён ли ресурс.
     *
     * @param   string  $resourceName  Имя ресурса для проверки.
     *
     * @return  boolean
     */
    public function isProtected(string $resourceName):bool {
        return $this->hasFlag($resourceName, 'isProtected', true);
    }

    /**
     * Проверка, хранится ли ресурс локально.
     *
     * @param   string  $resourceName  Имя ресурса для проверки.
     *
     * @return  boolean
     *
     */
    private function isLocal(string $resourceName):bool {
        $key = $this->resolveAlias($resourceName);

        return !empty($this->resources[$key]);
    }

    /**
     * Проверка, установлен ли флаг (т.е. один из "shared" или "protected")
     *
     * @param   string   $resourceName  Имя ресурса для проверки.
     * @param   string   $method        Метод делегирования полномочий.
     * @param   boolean  $default       Возвращаемое значение по умолчанию.
     *
     * @return  boolean
     * @throws  KeyNotFoundException
     */
    private function hasFlag(string $resourceName, string $method, bool $default = true):bool {
        $key = $this->resolveAlias($resourceName);

        if (isset($this->resources[$key])) {
            return \call_user_func([$this->resources[$key], $method]);
        }

        if ($this->parent instanceof self) {
            return \call_user_func([$this->parent, $method], $key);
        }

        if ($this->parent instanceof ContainerInterface && $this->parent->has($key)) {
            // Мы не знаем, поддерживает ли родительский элемент концепцию "общего доступа" или "защищенного доступа",
            // поэтому мы принимаем значение по умолчанию.
            return $default;
        }

        throw new KeyNotFoundException(sprintf("Resource '%s' has not been registered with the container.", $resourceName));
    }

    /**
     * Присваивает сервисам тег.
     *
     * @param   string  $tag   Имя тега.
     * @param   array   $keys  Сервисные ключи для тега.
     *
     * @return  $this
     */
    public function tag(string $tag, array $keys):Container {
        foreach ($keys as $key) {
            $resolvedKey = $this->resolveAlias($key);

            if (!isset($this->tags[$tag])) {
                $this->tags[$tag] = [];
            }

            $this->tags[$tag][] = $resolvedKey;
        }

        // Удаляем дубликаты
        $this->tags[$tag] = array_unique($this->tags[$tag]);

        return $this;
    }

    /**
     * Получить все сервисы, зарегистрированные по данному тегу.
     *
     * @param   string  $tag  Имя тега.
     *
     * @return  array  Разрешённые сервисы для данного тега.
     */
    public function getTagged(string $tag):array {
        $services = [];

        if (isset($this->tags[$tag])) {
            foreach ($this->tags[$tag] as $service) {
                $services[] = $this->get($service);
            }
        }

        return $services;
    }

    /**
     * Создание объекта требуемого класса.
     *
     * Создаёт экземпляр класса, указанного в параметре $resourceName, со всеми введенными зависимостями.
     * Если зависимости не могут быть полностью разрешены, генерируется исключение DependencyResolutionException.
     *
     * @param   string   $resourceName  Имя класса для создания.
     * @param   boolean  $shared        True, чтобы создать общий ресурс.
     *
     * @return  object|false  Экземпляр класса, указанный с помощью $resourceName, со всеми введенными зависимостями.
     *                        Возвращает объект, если класс существует, иначе false.
     *
     * @throws  DependencyResolutionException если объект не может быть построен (из-за отсутствия информации).
     */
    public function buildObject(string $resourceName, bool $shared = false):false|object {
        static $buildStack = [];

        $key = $this->resolveAlias($resourceName);

        if (\in_array($key, $buildStack, true)) {
            $buildStack = [];

            throw new DependencyResolutionException(sprintf('Cannot resolve circular dependency for "%s"', $key));
        }

        $buildStack[] = $key;

        if ($this->has($key)) {
            $resource = $this->get($key);
            array_pop($buildStack);

            return $resource;
        }

        try {
            $reflection = new \ReflectionClass($key);
        } catch (\ReflectionException $e) {
            array_pop($buildStack);

            return false;
        }

        if (!$reflection->isInstantiable()) {
            $buildStack = [];

            if ($reflection->isInterface()) {
                throw new DependencyResolutionException(
                    sprintf('There is no service for "%s" defined, cannot autowire a class service for an interface.', $key)
                );
            }

            if ($reflection->isAbstract()) {
                throw new DependencyResolutionException(
                    sprintf('There is no service for "%s" defined, cannot autowire an abstract class.', $key)
                );
            }

            throw new DependencyResolutionException(sprintf('"%s" cannot be instantiated.', $key));
        }

        $constructor = $reflection->getConstructor();

        // Если параметров нет, просто вернём новый объект.
        if ($constructor === null) {
            // Конструктора нет, просто возвращаем новый объект.
            $callback = function () use ($key) {
                return new $key();
            };
        } else {
            $newInstanceArgs = $this->getMethodArgs($constructor);

            $callback = function () use ($reflection, $newInstanceArgs) {
                return $reflection->newInstanceArgs($newInstanceArgs);
            };
        }

        $this->set($key, $callback, $shared);

        $resource = $this->get($key);
        array_pop($buildStack);

        return $resource;
    }

    /**
     * Удобный метод для создания общего объекта.
     *
     * @param   string  $resourceName  Имя класса для создания.
     *
     * @return  object|false  Экземпляр класса, указанный с помощью $resourceName, со всеми введенными зависимостями.
     *                        Возвращает объект, если класс существует, иначе false.
     *
     */
    public function buildSharedObject(string $resourceName):false|object {
        return $this->buildObject($resourceName, true);
    }

    /**
     * Создаёт дочерний контейнер с новой областью свойств,
     * которая имеет возможность доступа к родительской области при разрешении.
     *
     * @return  Container  Новый контейнер с текущим в качестве родительского.
     *
     */
    public function createChild():Container {
        return new static($this);
    }

    /**
     * Расширяет определенное замыкание сервиса, добавив новую вызываемую функцию.
     *
     * Это работает очень похоже на шаблон декоратора.
     * Обратите внимание, что это работает только с замыканием сервиса,
     * которые были определены в текущем контейнере, а не с родительскими контейнерами.
     *
     * @param   string    $resourceName  Уникальный идентификатор для замыкания или свойства.
     * @param   callable  $callable      Вызываемый объект для обертывания исходного замыкания.
     *
     * @return  void
     *
     * @throws  KeyNotFoundException
     */
    public function extend(string $resourceName, callable $callable):void {
        $key      = $this->resolveAlias($resourceName);
        $resource = $this->getResource($key, true);

        $closure = function ($c) use ($callable, $resource) {
            return $callable($resource->getInstance(), $c);
        };

        $this->set($key, $closure, $resource->isShared());
    }

    /**
     * Создание массива аргументов метода.
     *
     * @param   \ReflectionMethod  $method  Метод, для которого будет создан массив аргументов.
     *
     * @return  array  Массив аргументов для передачи методу.
     *
     * @throws  DependencyResolutionException
     */
    private function getMethodArgs(\ReflectionMethod $method):array {
        $methodArgs = [];

        foreach ($method->getParameters() as $param) {
            // Проверка наличия зависимости с указанием типа.
            if ($param->hasType()) {
                $dependency = $param->getType();

                // Не поддерживает типы объединений в PHP 8.
                if ($dependency instanceof \ReflectionUnionType) {
                    // Если этот параметр имеет значение null, то не выводим ошибку.
                    if ($param->allowsNull()) {
                        $methodArgs[] = null;

                        continue;
                    }

                    throw new DependencyResolutionException(
                        sprintf(
                            'Could not resolve the parameter "$%s" of "%s::%s()": Union typehints are not supported.',
                            $param->name,
                            $method->class,
                            $method->name
                        )
                    );
                }

                // Проверка наличия класса,
                // если у него его нет, то это скалярный тип, который мы не можем обработать,
                // если обязательный аргумент.
                if ($dependency->isBuiltin()) {
                    // Если параметр является необязательным, то перейдём к обработке необязательного параметра позже в этом методе
                    if (!$param->isOptional()) {
                        $message = 'Could not resolve the parameter "$%s" of "%s::%s()":';
                        $message .= ' Scalar parameters cannot be autowired and the parameter does not have a default value.';

                        throw new DependencyResolutionException(
                            sprintf(
                                $message,
                                $param->name,
                                $method->class,
                                $method->name
                            )
                        );
                    }
                } else {
                    $dependencyClassName = $dependency->getName();

                    // Проверим, существует ли класс или интерфейс
                    if (!interface_exists($dependencyClassName) && !class_exists($dependencyClassName)) {
                        // Если этот параметр имеет значение null, то не выводим ошибку
                        if ($param->allowsNull()) {
                            $methodArgs[] = null;

                            continue;
                        }

                        throw new DependencyResolutionException(
                            sprintf(
                                'Could not resolve the parameter "$%s" of "%s::%s()": The "%s" class does not exist.',
                                $param->name,
                                $method->class,
                                $method->name,
                                $dependencyClassName
                            )
                        );
                    }

                    // Если имя класса зависимостей зарегистрировано в этом контейнере или родительском элементе, используем его.
                    if ($this->getResource($dependencyClassName) !== null) {
                        $depObject = $this->get($dependencyClassName);
                    } else {
                        try {
                            $depObject = $this->buildObject($dependencyClassName);
                        } catch (DependencyResolutionException $exception) {
                            // Если этот параметр имеет значение null, то не выводим ошибку
                            if ($param->allowsNull()) {
                                $methodArgs[] = null;

                                continue;
                            }

                            $message = 'Could not resolve the parameter "$%s" of "%s::%s()":';
                            $message .= ' No service for "%s" exists and the dependency could not be autowired.';

                            throw new DependencyResolutionException(
                                sprintf(
                                    $message,
                                    $param->name,
                                    $method->class,
                                    $method->name,
                                    $dependencyClassName
                                ),
                                0,
                                $exception
                            );
                        }
                    }

                    if ($depObject instanceof $dependencyClassName) {
                        $methodArgs[] = $depObject;

                        continue;
                    }
                }
            }

            // Если есть параметр по умолчанию и его можно прочитать, используем его
            if ($param->isOptional() && $param->isDefaultValueAvailable()) {
                try {
                    $methodArgs[] = $param->getDefaultValue();

                    continue;
                } catch (\ReflectionException $exception) {
                    throw new DependencyResolutionException(
                        sprintf(
                            'Could not resolve the parameter "$%s" of "%s::%s()": Unable to read the default parameter value.',
                            $param->name,
                            $method->class,
                            $method->name
                        ),
                        0,
                        $exception
                    );
                }
            }

            // Если это не типизированный переменный аргумент, пропустим его
            if (!$param->hasType() && $param->isVariadic()) {
                continue;
            }

            // На данный момент аргумент не может быть разрешён,
            // наиболее вероятной причиной является не типизированный обязательный аргумент
            throw new DependencyResolutionException(
                sprintf(
                    'Could not resolve the parameter "$%s" of "%s::%s()": The argument is untyped and has no default value.',
                    $param->name,
                    $method->class,
                    $method->name
                )
            );
        }

        return $methodArgs;
    }

    /**
     * Устанавливает ресурс в контейнер. Если значение равно null, ресурс будет удален.
     *
     * @param   string   $key        Имя устанавливаемого ключа к ресурсам.
     * @param   mixed    $value      Вызываемая функция для запуска или строка для извлечения при запросе указанного $key.
     * @param   boolean  $shared     True для создания и хранения общего экземпляра.
     * @param   boolean  $protected  True необходимо для защиты этого элемента от перезаписи. Полезно для сервисов.
     *
     * @return  $this
     *
     * @throws  ProtectedKeyException  Выбрасывается, если предоставленный ключ уже установлен и защищен.
     */
    public function set(string $key, mixed $value, bool $shared = false, bool $protected = false):Container {
        $key = $this->resolveAlias($key);

        $hasKey = $this->has($key);

        if ($hasKey && $this->isLocal($key) && $this->isProtected($key)) {
            throw new ProtectedKeyException(sprintf("Key %s is protected and can't be overwritten.", $key));
        }

        if ($value === null && $hasKey) {
            unset($this->resources[$key]);

            return $this;
        }

        $mode = $shared ? ContainerResource::SHARE : ContainerResource::NO_SHARE;
        $mode |= $protected ? ContainerResource::PROTECT : ContainerResource::NO_PROTECT;

        $this->resources[$key] = new ContainerResource($this, $value, $mode);

        return $this;
    }

    /**
     * Быстрый способ создания защищенных ключей.
     *
     * @param   string   $key     Имя устанавливаемого ключа хранилища данных.
     * @param   mixed    $value   Вызываемая функция для запуска или строка для извлечения при запросе указанного $key.
     * @param   boolean  $shared  True для создания и хранения общего экземпляра.
     *
     * @return  $this
     *
     */
    public function protect(string $key, mixed $value, bool $shared = false):Container {
        return $this->set($key, $value, $shared, true);
    }

    /**
     * Быстрый способ создания общих ключей.
     *
     * @param   string   $key        Имя устанавливаемого ключа хранилища данных.
     * @param   mixed    $value      Вызываемая функция для запуска или строка для извлечения при запросе указанного $key.
     * @param   boolean  $protected  True необходимо для защиты этого элемента от перезаписи. Полезно для сервисов.
     *
     * @return  $this
     *
     */
    public function share(string $key, mixed $value, bool $protected = false):Container {
        return $this->set($key, $value, true, $protected);
    }

    /**
     * Получает необработанные данные, присвоенные ключу.
     *
     * @param   string   $key   Ключ, по которому можно получить сохраненный элемент.
     * @param   boolean  $bail  Генерирует исключение, если ключ не найден.
     *
     * @return  ContainerResource|null  Ресурс, если он присутствует, или значение null, если ему дано указание.
     * @throws  KeyNotFoundException
     */
    public function getResource(string $key, bool $bail = false): ?ContainerResource {
        if (isset($this->resources[$key])) {
            return $this->resources[$key];
        }

        if ($this->parent instanceof self) {
            return $this->parent->getResource($key);
        }

        if ($this->parent instanceof ContainerInterface && $this->parent->has($key)) {
            return new ContainerResource($this, $this->parent->get($key), ContainerResource::SHARE | ContainerResource::PROTECT);
        }

        if ($bail) {
            throw new KeyNotFoundException(sprintf('Key %s has not been registered with the container.', $key));
        }

        return null;
    }

    /**
     * Метод, позволяющий заставить контейнер возвращать новый экземпляр результатов обратного вызова для запрошенного $key.
     *
     * @param   string  $key  Имя ключа к ресурсу, который нужно получить.
     *
     * @return  mixed   Результаты выполнения обратного вызова для указанного ключа.
     *
     */
    public function getNewInstance(string $key):mixed {
        $key = $this->resolveAlias($key);

        $this->getResource($key, true)->reset();

        return $this->get($key);
    }

    /**
     * Зарегистрирует сервис в контейнере.
     *
     * @param   ServiceProviderInterface  $provider  Сервис для регистрации.
     *
     * @return  $this
     *
     */
    public function registerServiceProvider(ServiceProviderInterface $provider):Container {
        $provider->register($this);

        return $this;
    }

    /**
     * Извлекает ключи для сервисов, назначенных этому контейнеру.
     *
     * @return  array
     */
    public function getKeys():array {
        return array_unique(array_merge(array_keys($this->aliases), array_keys($this->resources)));
    }
}
