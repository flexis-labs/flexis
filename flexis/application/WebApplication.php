<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application;

use Flexis\Application\Controller\ControllerResolverInterface;
use Flexis\Application\Web\WebClient;
use Flexis\Input\Input;
use Flexis\Registry\Registry;
use Flexis\Router\RouterInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Базовый класс веб-приложения для обработки HTTP-запросов.
 */
class WebApplication extends AbstractWebApplication implements SessionAwareWebApplicationInterface {
    use SessionAwareWebApplicationTrait;

    /**
     * Резолвер контроллера приложения.
     *
     * @var    ControllerResolverInterface
     */
    protected ControllerResolverInterface $controllerResolver;

    /**
     * Маршрутизатор приложения.
     *
     * @var    RouterInterface
     */
    protected RouterInterface $router;

    /**
     * Конструктор класса.
     *
     * @param  ControllerResolverInterface  $controllerResolver   Резолвер контроллера приложения.
     * @param  RouterInterface              $router               Маршрутизатор приложения.
     * @param  Input|null                   $input                Необязательный аргумент, обеспечивающий внедрение зависимостей для входного объекта приложения.
     *                                                            Если аргумент является входным объектом, этот объект станет входным объектом приложения,
     *                                                            иначе создаётся входной объект по умолчанию.
     * @param  Registry|null                $config               Необязательный аргумент, обеспечивающий внедрение зависимостей для объекта конфигурации приложения.
     *                                                            Если аргументом является объект реестра, этот объект станет объектом конфигурации приложения,
     *                                                            иначе создаётся объект конфигурации по умолчанию.
     * @param  WebClient|null               $client               Необязательный аргумент, обеспечивающий внедрение зависимостей для клиентского объекта приложения.
     *                                                            Если аргументом является объект Web\WebClient, этот объект станет клиентским объектом приложения,
     *                                                            иначе создаётся клиентский объект по умолчанию.
     * @param  ResponseInterface|null       $response             Необязательный аргумент, обеспечивающий внедрение зависимостей для объекта response приложения.
     *                                                            Если аргумент является объектом интерфейса Response, этот объект станет объектом response приложения,
     *                                                            иначе создаётся объект response по умолчанию.
     *
     */
    public function __construct(
        ControllerResolverInterface $controllerResolver,
        RouterInterface $router,
        ?Input $input = null,
        ?Registry $config = null,
        ?WebClient $client = null,
        ?ResponseInterface $response = null
    ) {
        $this->controllerResolver = $controllerResolver;
        $this->router             = $router;

        parent::__construct($input, $config, $client, $response);
    }

    /**
     * Метод для запуска подпрограмм приложения.
     *
     * @return  void
     */
    protected function doExecute(): void {
        $route = $this->router->parseRoute($this->get('uri.route'), $this->input->getMethod());

        foreach ($route->getRouteVariables() as $key => $value) {
            $this->input->def($key, $value);
        }

        \call_user_func($this->controllerResolver->resolve($route));
    }
}
