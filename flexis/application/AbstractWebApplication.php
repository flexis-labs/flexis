<?php

/**
 * Часть пакета Flexis Application Framework.
 */

namespace Flexis\Application;

use DateTime;
use Flexis\Application\Event\ApplicationErrorEvent;
use Flexis\Application\Exception\UnableToWriteBody;
use Flexis\Application\Web\WebClient;
use Flexis\Input\Input;
use Flexis\Registry\Registry;
use Flexis\Uri\Uri;
use JetBrains\PhpStorm\NoReturn;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream;
use Psr\Http\Message\ResponseInterface;

/**
 * Базовый класс для веб-приложения Flexis.
 *
 * @property-read  Input $input  Входной объект приложения.
 */
abstract class AbstractWebApplication extends AbstractApplication implements WebApplicationInterface {
    /**
     * Объект ввода приложения.
     *
     * @var    Input
     */
    protected Input $input;

    /**
     * Строка кодировки символов.
     *
     * @var    string
     */
    public string $charSet = 'utf-8';

    /**
     * Тип ответа.
     *
     * @var    string
     */
    public string $mimeType = 'text/html';

    /**
     * Версия протокола HTTP.
     *
     * @var    string
     */
    public string $httpVersion = '1.1';

    /**
     * Дата изменения тела для заголовков ответов.
     *
     * @var    DateTime|null
     */
    public ?DateTime $modifiedDate = null;

    /**
     * Клиентский объект приложения.
     *
     * @var    Web\WebClient
     */
    public WebClient $client;

    /**
     * Объект ответа приложения.
     *
     * @var    ResponseInterface
     */
    protected ResponseInterface $response;

    /**
     * Кэширование включено?
     *
     * @var    boolean
     */
    private bool $cacheable = false;

    /**
     * Сопоставление целочисленных кодов ответов HTTP с полным статусом HTTP для заголовков.
     *
     * @var    array
     * @link   https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     */
    private array $responseMap = [
        100 => 'HTTP/{version} 100 Continue',
        101 => 'HTTP/{version} 101 Switching Protocols',
        102 => 'HTTP/{version} 102 Processing',
        200 => 'HTTP/{version} 200 OK',
        201 => 'HTTP/{version} 201 Created',
        202 => 'HTTP/{version} 202 Accepted',
        203 => 'HTTP/{version} 203 Non-Authoritative Information',
        204 => 'HTTP/{version} 204 No Content',
        205 => 'HTTP/{version} 205 Reset Content',
        206 => 'HTTP/{version} 206 Partial Content',
        207 => 'HTTP/{version} 207 Multi-Status',
        208 => 'HTTP/{version} 208 Already Reported',
        226 => 'HTTP/{version} 226 IM Used',
        300 => 'HTTP/{version} 300 Multiple Choices',
        301 => 'HTTP/{version} 301 Moved Permanently',
        302 => 'HTTP/{version} 302 Found',
        303 => 'HTTP/{version} 303 See other',
        304 => 'HTTP/{version} 304 Not Modified',
        305 => 'HTTP/{version} 305 Use Proxy',
        306 => 'HTTP/{version} 306 (Unused)',
        307 => 'HTTP/{version} 307 Temporary Redirect',
        308 => 'HTTP/{version} 308 Permanent Redirect',
        400 => 'HTTP/{version} 400 Bad Request',
        401 => 'HTTP/{version} 401 Unauthorized',
        402 => 'HTTP/{version} 402 Payment Required',
        403 => 'HTTP/{version} 403 Forbidden',
        404 => 'HTTP/{version} 404 Not Found',
        405 => 'HTTP/{version} 405 Method Not Allowed',
        406 => 'HTTP/{version} 406 Not Acceptable',
        407 => 'HTTP/{version} 407 Proxy Authentication Required',
        408 => 'HTTP/{version} 408 Request Timeout',
        409 => 'HTTP/{version} 409 Conflict',
        410 => 'HTTP/{version} 410 Gone',
        411 => 'HTTP/{version} 411 Length Required',
        412 => 'HTTP/{version} 412 Precondition Failed',
        413 => 'HTTP/{version} 413 Payload Too Large',
        414 => 'HTTP/{version} 414 URI Too Long',
        415 => 'HTTP/{version} 415 Unsupported Media Type',
        416 => 'HTTP/{version} 416 Range Not Satisfiable',
        417 => 'HTTP/{version} 417 Expectation Failed',
        418 => 'HTTP/{version} 418 I\'m a teapot',
        421 => 'HTTP/{version} 421 Misdirected Request',
        422 => 'HTTP/{version} 422 Unprocessable Entity',
        423 => 'HTTP/{version} 423 Locked',
        424 => 'HTTP/{version} 424 Failed Dependency',
        426 => 'HTTP/{version} 426 Upgrade Required',
        428 => 'HTTP/{version} 428 Precondition Required',
        429 => 'HTTP/{version} 429 Too Many Requests',
        431 => 'HTTP/{version} 431 Request Header Fields Too Large',
        451 => 'HTTP/{version} 451 Unavailable For Legal Reasons',
        500 => 'HTTP/{version} 500 Internal Server Error',
        501 => 'HTTP/{version} 501 Not Implemented',
        502 => 'HTTP/{version} 502 Bad Gateway',
        503 => 'HTTP/{version} 503 Service Unavailable',
        504 => 'HTTP/{version} 504 Gateway Timeout',
        505 => 'HTTP/{version} 505 HTTP Version Not Supported',
        506 => 'HTTP/{version} 506 Variant Also Negotiates',
        507 => 'HTTP/{version} 507 Insufficient Storage',
        508 => 'HTTP/{version} 508 Loop Detected',
        510 => 'HTTP/{version} 510 Not Extended',
        511 => 'HTTP/{version} 511 Network Authentication Required',
    ];

    /**
     * Конструктор класса.
     *
     * @param  Input|null              $input     Необязательный аргумент, обеспечивающий внедрение зависимостей для входного объекта приложения.
     *                                            Если аргумент является входным объектом, этот объект станет входным объектом приложения,
     *                                            иначе создаётся входной объект по умолчанию.
     * @param  Registry|null           $config    Необязательный аргумент, обеспечивающий внедрение зависимостей для объекта конфигурации приложения.
     *                                            Если аргументом является объект реестра, этот объект станет объектом конфигурации приложения,
     *                                            иначе создаётся объект конфигурации по умолчанию.
     * @param  WebClient|null          $client    Необязательный аргумент, обеспечивающий внедрение зависимостей для клиентского объекта приложения.
     *                                            Если аргументом является объект Web\WebClient, этот объект станет клиентским объектом приложения,
     *                                            иначе создаётся клиентский объект по умолчанию.
     * @param  ResponseInterface|null  $response  Необязательный аргумент, обеспечивающий внедрение зависимостей для объекта response приложения.
     *                                            Если аргумент является объектом интерфейса Response, этот объект станет объектом response приложения,
     *                                            иначе создаётся объект response по умолчанию.
     */
    public function __construct(
        ?Input $input = null,
        ?Registry $config = null,
        ?WebClient $client = null,
        ?ResponseInterface $response = null
    ) {
        $this->input  = $input ?: new Input();
        $this->client = $client ?: WebClient::getInstance();

        if (!$response) {
            $response = new Response();
        }

        $this->setResponse($response);

        parent::__construct($config);

        $this->loadSystemUris();
    }

    /**
     * Выполняет приложение.
     *
     * @return  void
     */
    public function execute(): void {
        try {
            $this->dispatchEvent(ApplicationEvents::BEFORE_EXECUTE);
            $this->doExecute();
            $this->dispatchEvent(ApplicationEvents::AFTER_EXECUTE);

            if (
                $this->get('gzip')
                && !\ini_get('zlib.output_compression')
                && (\ini_get('output_handler') != 'ob_gzhandler')
            ) {
                $this->compress();
            }
        } catch (\Throwable $throwable) {
            $this->dispatchEvent(ApplicationEvents::ERROR, new ApplicationErrorEvent($throwable, $this));
        }

        $this->dispatchEvent(ApplicationEvents::BEFORE_RESPOND);
        $this->respond();
        $this->dispatchEvent(ApplicationEvents::AFTER_RESPOND);
    }

    /**
     * Проверяет принимаемую кодировку браузера и, если возможно, сжимает данные перед отправкой клиенту.
     *
     * @return  void
     */
    protected function compress(): void {
        $supported = [
            'x-gzip'  => 'gz',
            'gzip'    => 'gz',
            'deflate' => 'deflate',
        ];

        $encodings = \array_intersect($this->client->encodings, \array_keys($supported));

        if (empty($encodings)) {
            return;
        }

        if ($this->checkHeadersSent() || !$this->checkConnectionAlive()) {
            return;
        }

        foreach ($encodings as $encoding) {
            if (($supported[$encoding] == 'gz') || ($supported[$encoding] == 'deflate')) {
                if (!\extension_loaded('zlib') || \ini_get('zlib.output_compression')) {
                    continue;
                }

                $data   = $this->getBody();
                $gzdata = \gzencode($data, 4, ($supported[$encoding] == 'gz') ? FORCE_GZIP : FORCE_DEFLATE);

                if ($gzdata === false) {
                    continue;
                }

                $this->setHeader('Content-Encoding', $encoding);
                $this->setHeader('Vary', 'Accept-Encoding');
                $this->setBody($gzdata);

                break;
            }
        }
    }

    /**
     * Метод для отправки ответа приложения клиенту.
     * Все заголовки будут отправлены до основных выходных данных приложения.
     *
     * @return  void
     */
    protected function respond(): void {
        if (!$this->getResponse()->hasHeader('Content-Type')) {
            $this->setHeader('Content-Type', $this->mimeType . '; charset=' . $this->charSet);
        }

        if (!$this->allowCache()) {
            $this->setHeader('Expires', 'Wed, 17 Aug 2005 00:00:00 GMT', true);
            $this->setHeader('Last-Modified', \gmdate('D, d M Y H:i:s') . ' GMT', true);
            $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, post-check=0, pre-check=0', false);
            $this->setHeader('Pragma', 'no-cache');
        } else {
            if (!$this->getResponse()->hasHeader('Expires')) {
                $this->setHeader('Expires', \gmdate('D, d M Y H:i:s', \time() + 900) . ' GMT');
            }

            if (!$this->getResponse()->hasHeader('Last-Modified') && $this->modifiedDate instanceof DateTime) {
                $this->modifiedDate->setTimezone(new \DateTimeZone('UTC'));
                $this->setHeader('Last-Modified', $this->modifiedDate->format('D, d M Y H:i:s') . ' GMT');
            }
        }

        if (!$this->getResponse()->hasHeader('Status')) {
            $this->setHeader('Status', (string) $this->getResponse()->getStatusCode());
        }

        $this->sendHeaders();

        echo $this->getBody();
    }

    /**
     * Метод для получения объекта ввода приложения.
     *
     * @return  Input
     */
    public function getInput(): Input {
        return $this->input;
    }

    /**
     * Перенаправление на другой URL.
     *
     * Если заголовки не были отправлены, перенаправление будет выполнено с помощью кода "301 Moved Permanently"
     * или "303 See Other" код в заголовке, указывающий на новое местоположение.
     * Если заголовки уже были отправлены, это будет выполнено с помощью инструкции JavaScript.
     *
     * @param  string   $url     URL-адрес для перенаправления. Может быть только URL-адрес http/https.
     * @param  integer  $status  Код состояния HTTP, который необходимо предоставить. По умолчанию предполагается 303.
     *
     * @return  void
     *
     * @throws  \InvalidArgumentException
     */
    #[NoReturn]
    public function redirect(string $url, int $status = 303): void {
        if (\preg_match('#^index\.php#', $url)) {
            $url = $this->get('uri.base.full') . $url;
        }

        $url = \preg_split("/[\r\n]/", $url);
        $url = $url[0];

        if (!\preg_match('#^[a-z]+://#i', $url)) {
            $uri = new Uri($this->get('uri.request'));
            $prefix = $uri->toString(['scheme', 'user', 'pass', 'host', 'port']);

            if ($url[0] == '/') {
                $url = $prefix . $url;
            } else {
                $parts = \explode('/', $uri->toString(['path']));
                \array_pop($parts);
                $path = \implode('/', $parts) . '/';
                $url  = $prefix . $path . $url;
            }
        }

        if ($this->checkHeadersSent()) {
            echo '<script>document.location.href=' . \json_encode($url) . ";</script>\n";
        } elseif (($this->client->engine == WebClient::TRIDENT) && !static::isAscii($url)) {
            $html = '<html><head>';
            $html .= '<meta http-equiv="content-type" content="text/html; charset=' . $this->charSet . '" />';
            $html .= '<script>document.location.href=' . \json_encode($url) . ';</script>';
            $html .= '</head><body></body></html>';

            echo $html;
        } else {
            $this->setHeader('Status', (string) $status, true);
            $this->setHeader('Location', $url, true);
        }

        $this->dispatchEvent(ApplicationEvents::BEFORE_RESPOND);
        $this->respond();
        $this->dispatchEvent(ApplicationEvents::AFTER_RESPOND);
        $this->close();
    }

    /**
     * Установить/получить кэшируемое состояние для ответа.
     *
     * Если установлен $alloy, устанавливает кэшируемое состояние ответа. Всегда возвращает текущее состояние.
     *
     * @param  boolean|null  $allow  True, чтобы разрешить кеширование браузера.
     *
     * @return  boolean
     */
    public function allowCache(?bool $allow = null): bool {
        if ($allow !== null) {
            $this->cacheable = $allow;
        }

        return $this->cacheable;
    }

    /**
     * Метод установки заголовка ответа.
     *
     * Если флаг замены установлен, то все заголовки с данным именем будут заменены новыми.
     * Заголовки хранятся во внутреннем массиве и отправляются при отправке сайта в браузер.
     *
     * @param  string   $name     Имя заголовка, который нужно установить.
     * @param  string   $value    Значение заголовка, который необходимо установить.
     * @param  boolean  $replace  True для замены любых заголовков с тем же именем.
     *
     * @return  $this
     */
    public function setHeader(string $name, string $value, bool $replace = false): self {
        $response = $this->getResponse();

        if ($replace && $response->hasHeader($name)) {
            $response = $response->withoutHeader($name);
        }

        $this->setResponse($response->withAddedHeader($name, $value));

        return $this;
    }

    /**
     * Метод для получения массива заголовков ответа, который будет отправлен при отправке ответа клиенту.
     *
     * @return  array
     */
    public function getHeaders(): array {
        $return = [];

        foreach ($this->getResponse()->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $return[] = ['name' => $name, 'value' => $value];
            }
        }

        return $return;
    }

    /**
     * Метод очистки всех заданных заголовков ответа.
     *
     * @return  $this
     */
    public function clearHeaders(): self {
        $response = $this->getResponse();

        foreach ($response->getHeaders() as $name => $values) {
            $response = $response->withoutHeader($name);
        }

        $this->setResponse($response);

        return $this;
    }

    /**
     * Отправляет заголовки ответов.
     *
     * @return  $this
     */
    public function sendHeaders(): self {
        if (!$this->checkHeadersSent()) {
            foreach ($this->getHeaders() as $header) {
                if (\strtolower($header['name']) == 'status') {
                    $status = $this->getHttpStatusValue($header['value']);

                    $this->header($status, true, (int) $header['value']);
                } else {
                    $this->header($header['name'] . ': ' . $header['value']);
                }
            }
        }

        return $this;
    }

    /**
     * Устанавливает содержимое тела.  Если содержимое тела уже определено, это заменит его.
     *
     * @param  string  $content  Содержимое, которое необходимо установить в качестве тела ответа.
     *
     * @return  $this
     */
    public function setBody(string $content): self {
        $stream = new Stream('php://memory', 'rw');
        $stream->write((string) $content);
        $this->setResponse($this->getResponse()->withBody($stream));

        return $this;
    }

    /**
     * Добавление содержимого к основному содержимому
     *
     * @param  string  $content  Содержимое, добавляемое к телу ответа.
     *
     * @return  $this
     */
    public function prependBody(string $content): self {
        $currentBody = $this->getResponse()->getBody();

        if (!$currentBody->isReadable()) {
            throw new UnableToWriteBody();
        }

        $stream = new Stream('php://memory', 'rw');
        $stream->write($content . (string) $currentBody);
        $this->setResponse($this->getResponse()->withBody($stream));

        return $this;
    }

    /**
     * Добавляет содержимое к содержимому тела.
     *
     * @param  string  $content  Содержимое, добавляемое в тело ответа.
     *
     * @return  $this
     */
    public function appendBody(string $content): self {
        $currentStream = $this->getResponse()->getBody();

        if ($currentStream->isWritable()) {
            $currentStream->write($content);
            $this->setResponse($this->getResponse()->withBody($currentStream));
        } elseif ($currentStream->isReadable()) {
            $stream = new Stream('php://memory', 'rw');
            $stream->write((string) $currentStream . $content);
            $this->setResponse($this->getResponse()->withBody($stream));
        } else {
            throw new UnableToWriteBody();
        }

        return $this;
    }

    /**
     * Возвращает содержимое тела.
     *
     * @return  string  Тело ответа в виде строки.
     */
    public function getBody(): string {
        return (string) $this->getResponse()->getBody();
    }

    /**
     * Возвращает объект ответа PSR-7.
     *
     * @return  ResponseInterface
     */
    public function getResponse(): ResponseInterface {
        return $this->response;
    }

    /**
     * Проверяет, может ли данное значение быть успешно сопоставлено с действительным значением статуса http.
     *
     * @param  string|int  $value  Данный статус как int или строка
     *
     * @return  string
     */
    protected function getHttpStatusValue(string|int $value): string {
        $code = (int) $value;

        if (\array_key_exists($code, $this->responseMap)) {
            $value = $this->responseMap[$code];
        } else {
            $value = 'HTTP/{version} ' . $code;
        }

        return \str_replace('{version}', $this->httpVersion, $value);
    }

    /**
     * Проверяет, является ли значение действительным кодом состояния HTTP.
     *
     * @param  integer  $code  Потенциальный код состояния.
     *
     * @return  boolean
     */
    public function isValidHttpStatus(int $code): bool {
        return \array_key_exists($code, $this->responseMap);
    }

    /**
     * Метод проверки текущего состояния клиентского соединения, чтобы убедиться, что оно активно.
     * Мы обертываем это, чтобы изолировать функцию \connection_status() от нашей базы кода в целях тестирования.
     *
     * @return  boolean  True, если соединение действительное и нормальное.
     *
     * @codeCoverageIgnore
     * @see     \connection_status()
     */
    protected function checkConnectionAlive(): bool {
        return \connection_status() === CONNECTION_NORMAL;
    }

    /**
     * Метод проверки того, были ли уже отправлены заголовки.
     *
     * @return  boolean  True, если заголовки уже отправлены.
     *
     * @codeCoverageIgnore
     * @see     \headers_sent()
     */
    protected function checkHeadersSent(): bool {
        return \headers_sent();
    }

    /**
     * Метод обнаружения запрошенного URI из переменных среды сервера.
     *
     * @return  string  Запрошенный URI
     */
    protected function detectRequestUri(): string {
        $scheme     = $this->isSslConnection() ? 'https://' : 'http://';
        $phpSelf    = $this->input->server->getString('PHP_SELF', '');
        $requestUri = $this->input->server->getString('REQUEST_URI', '');

        $uri = $scheme . $this->input->server->getString('HTTP_HOST');

        if (!empty($phpSelf) && !empty($requestUri)) {
            $uri .= $requestUri;
        } else {
            $uri .= $this->input->server->getString('SCRIPT_NAME');
            $queryHost = $this->input->server->getString('QUERY_STRING', '');

            if (!empty($queryHost)) {
                $uri .= '?' . $queryHost;
            }
        }

        $uri = str_replace(["'", '"', '<', '>'], ['%27', '%22', '%3C', '%3E'], $uri);

        return \trim($uri);
    }

    /**
     * Метод отправки заголовка клиенту.
     *
     * @param  string        $string    Строка заголовка.
     * @param  boolean       $replace   Необязательный параметр replace указывает, должен ли заголовок заменить
     *                                  предыдущий аналогичный заголовок или добавить второй заголовок того же типа.
     * @param  integer|null  $code      Принудительно присваивает коду ответа HTTP указанное значение.
     *                                  Обратите внимание, что этот параметр действует только в том случае, если строка не пуста.
     *
     * @return  void
     *
     * @codeCoverageIgnore
     * @see     \header()
     */
    protected function header(string $string, bool $replace = true, ?int $code = null): void {
        if ($code === null) {
            $code = 0;
        }

        \header(\str_replace(\chr(0), '', $string), $replace, $code);
    }

    /**
     * Устанавливает объект ответа PSR-7.
     *
     * @param  ResponseInterface  $response  Объект ответа.
     *
     * @return  void
     */
    public function setResponse(ResponseInterface $response): void {
        $this->response = $response;
    }

    /**
     * Проверяет, является ли состояние состоянием перенаправления.
     *
     * @param  integer  $state  Код состояния HTTP.
     *
     * @return  boolean
     */
    protected function isRedirectState(int $state): bool {
        return $state > 299 && $state < 400 && \array_key_exists($state, $this->responseMap);
    }

    /**
     * Определяет, используем ли мы безопасное (SSL) соединение.
     *
     * @return  boolean  True, если используется SSL, иначе — false.
     */
    public function isSslConnection(): bool {
        $serverSSLVar = $this->input->server->getString('HTTPS', '');

        if (!empty($serverSSLVar) && \strtolower($serverSSLVar) !== 'off') {
            return true;
        }

        $serverForwarderProtoVar = $this->input->server->getString('HTTP_X_FORWARDED_PROTO', '');

        return !empty($serverForwarderProtoVar) && \strtolower($serverForwarderProtoVar) === 'https';
    }

    /**
     * Метод для загрузки системных строк URI для приложения.
     *
     * @param  string|null  $requestUri  Необязательный URI запроса, который можно использовать вместо обнаружения его из переменных среды сервера.
     *
     * @return  void
     */
    protected function loadSystemUris(?string $requestUri = null): void {
        if (!empty($requestUri)) {
            $this->set('uri.request', $requestUri);
        } else {
            $this->set('uri.request', $this->detectRequestUri());
        }

        $siteUri = \trim($this->get('site_uri', ''));

        if ($siteUri !== '') {
            $uri  = new Uri($siteUri);
            $path = $uri->toString(['path']);
        } else {
            $uri = new Uri($this->get('uri.request'));

            $requestUri = $this->input->server->getString('REQUEST_URI', '');

            if (str_contains(PHP_SAPI, 'cgi') && !\ini_get('cgi.fix_pathinfo') && !empty($requestUri)) {
                $path = \dirname($this->input->server->getString('PHP_SELF', ''));
            } else {
                $path = \dirname($this->input->server->getString('SCRIPT_NAME', ''));
            }
        }

        $host = $uri->toString(['scheme', 'user', 'pass', 'host', 'port']);

        if (str_contains($path, 'index.php')) {
            $path = \substr_replace($path, '', \strpos($path, 'index.php'), 9);
        }

        $path = \rtrim($path, '/\\');

        $this->set('uri.base.full', $host . $path . '/');
        $this->set('uri.base.host', $host);
        $this->set('uri.base.path', $path . '/');

        if (\stripos($this->get('uri.request'), $this->get('uri.base.full')) === 0) {
            $this->set(
                'uri.route',
                \substr_replace($this->get('uri.request'), '', 0, \strlen($this->get('uri.base.full')))
            );
        }

        $mediaURI = \trim($this->get('media_uri', ''));

        if ($mediaURI !== '') {
            if (str_contains($mediaURI, '://')) {
                $this->set('uri.media.full', $mediaURI);
            } else {
                $mediaURI = \trim($mediaURI, '/\\');
                $mediaURI = !empty($mediaURI) ? '/' . $mediaURI . '/' : '/';
                $this->set('uri.media.full', $this->get('uri.base.host') . $mediaURI);
            }
            $this->set('uri.media.path', $mediaURI);
        } else {
            $this->set('uri.media.full', $this->get('uri.base.full') . 'media/');
            $this->set('uri.media.path', $this->get('uri.base.path') . 'media/');
        }
    }

    /**
     * Проверяет, содержит ли строка только 7-битные байты ASCII.
     *
     * Вы можете использовать это для условной проверки, требует ли строка обработки как UTF-8 или нет,
     * что потенциально дает преимущества в производительности за счет использования собственного эквивалента PHP,
     * если это просто ASCII.
     *
     * @param  string  $str  Строка для проверки.
     *
     * @return  boolean  True, если строка полностью ASCII.
     */
    public static function isAscii(string $str): bool {
        return \preg_match('/[^\x00-\x7F]/', $str) !== 1;
    }
}
