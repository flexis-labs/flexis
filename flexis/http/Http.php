<?php

/**
 * Часть пакета Flexis Http Framework.
 */

namespace Flexis\Http;

use ArrayAccess;
use Flexis\Uri\Uri;
use Flexis\Uri\UriInterface;
use InvalidArgumentException;
use Laminas\Diactoros\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Класс HTTP-клиента.
 */
class Http implements ClientInterface {
    /**
     * Параметры HTTP-клиента.
     *
     * @var    array|ArrayAccess
     */
    protected array|ArrayAccess $options;

    /**
     * Транспортный объект HTTP, используемый при отправке HTTP-запросов.
     *
     * @var    TransportInterface
     */
    protected TransportInterface $transport;

    /**
     * Конструктор.
     *
     * @param   array|ArrayAccess        $options    Массив опций клиента. 
     *                                               Если реестр содержит какие-либо элементы headers.*, 
     *                                               они будут добавлены к заголовкам запроса.
     * @param   TransportInterface|null  $transport  Транспортный объект HTTP.
     *
     * @throws  InvalidArgumentException
     */
    public function __construct(array|ArrayAccess $options = [], ?TransportInterface $transport = null) {
        if (!\is_array($options) && !($options instanceof ArrayAccess)) {
            throw new InvalidArgumentException(
                'Параметр options должен быть массивом или реализовывать интерфейс ArrayAccess.'
            );
        }

        $this->options = $options;

        if (!$transport) {
            $transport = (new HttpFactory())->getAvailableDriver($this->options);

            if (!($transport instanceof TransportInterface)) {
                throw new InvalidArgumentException(sprintf('Действительный объект %s не был установлен.', TransportInterface::class));
            }
        }

        $this->transport = $transport;
    }

    /**
     * Возвращает опцию от HTTP-клиента.
     *
     * @param   string  $key      Имя опции, которую нужно получить.
     * @param   mixed   $default  Значение по умолчанию, если опция не установлена.
     *
     * @return  mixed  Значение опции.
     *
     */
    public function getOption(string $key, mixed $default = null): mixed {
        return $this->options[$key] ?? $default;
    }

    /**
     * Устанавливает параметр для HTTP-клиента.
     *
     * @param   string  $key    Имя устанавливаемой опции.
     * @param   mixed   $value  Значение параметра, которое необходимо установить.
     *
     * @return  $this
     *
     */
    public function setOption(string $key, mixed $value): self {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Метод отправки запроса OPTIONS на сервер.
     *
     * @param   string|UriInterface  $url      URI запрашиваемого ресурса.
     * @param   array                $headers  Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null         $timeout  Чтение тайм-аута в секундах.
     *
     * @return  Response
     *
     */
    public function options(string|UriInterface $url, array $headers = [], ?int $timeout = null): Response {
        return $this->makeTransportRequest('OPTIONS', $url, null, $headers, $timeout);
    }

    /**
     * Метод отправки запроса HEAD на сервер.
     *
     * @param   string|UriInterface  $url      URI запрашиваемого ресурса.
     * @param   array                $headers  Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null         $timeout  Чтение тайм-аута в секундах.
     *
     * @return  Response
     *
     */
    public function head(string|UriInterface $url, array $headers = [], ?int $timeout = null): Response {
        return $this->makeTransportRequest('HEAD', $url, null, $headers, $timeout);
    }

    /**
     * Метод отправки запроса GET на сервер.
     *
     * @param   string|UriInterface  $url      URI запрашиваемого ресурса.
     * @param   array                $headers  Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null         $timeout  Чтение тайм-аута в секундах.
     *
     * @return  Response
     *
     */
    public function get(string|UriInterface $url, array $headers = [], ?int $timeout = null): Response {
        return $this->makeTransportRequest('GET', $url, null, $headers, $timeout);
    }

    /**
     * Метод отправки запроса POST на сервер.
     *
     * @param   string|UriInterface  $url      URI запрашиваемого ресурса.
     * @param   mixed                $data     Либо ассоциативный массив, либо строка, которая будет отправлена с запросом.
     * @param   array                $headers  Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null         $timeout  Чтение тайм-аута в секундах.
     *
     * @return  Response
     *
     */
    public function post(string|UriInterface $url, $data, array $headers = [], ?int $timeout = null): Response {
        return $this->makeTransportRequest('POST', $url, $data, $headers, $timeout);
    }

    /**
     * Метод отправки запроса PUT на сервер.
     *
     * @param   string|UriInterface  $url      URI запрашиваемого ресурса.
     * @param   mixed                $data     Либо ассоциативный массив, либо строка, которая будет отправлена с запросом.
     * @param   array                $headers  Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null         $timeout  Чтение тайм-аута в секундах.
     *
     * @return  Response
     *
     */
    public function put(string|UriInterface $url, $data, array $headers = [], ?int $timeout = null): Response {
        return $this->makeTransportRequest('PUT', $url, $data, $headers, $timeout);
    }

    /**
     * Метод для отправки запроса DELETE на сервер.
     *
     * @param   string|UriInterface  $url      URI запрашиваемого ресурса.
     * @param   array                $headers  Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null         $timeout  Чтение тайм-аута в секундах.
     * @param   mixed                $data     Либо ассоциативный массив, либо строка, которая будет отправлена с запросом.
     *
     * @return  Response
     *
     */
    public function delete(string|UriInterface $url, array $headers = [], ?int $timeout = null, mixed $data = null): Response {
        return $this->makeTransportRequest('DELETE', $url, $data, $headers, $timeout);
    }

    /**
     * Метод для отправки запроса TRACE на сервер.
     *
     * @param   string|UriInterface  $url      URI запрашиваемого ресурса.
     * @param   array                $headers  Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null         $timeout  Чтение тайм-аута в секундах.
     *
     * @return  Response
     *
     */
    public function trace(string|UriInterface $url, array $headers = [], ?int $timeout = null): Response {
        return $this->makeTransportRequest('TRACE', $url, null, $headers, $timeout);
    }

    /**
     * Метод отправки запроса PATCH на сервер.
     *
     * @param   string|UriInterface  $url      URI запрашиваемого ресурса.
     * @param   mixed                $data     Либо ассоциативный массив, либо строка, которая будет отправлена с запросом.
     * @param   array                $headers  Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null         $timeout  Чтение тайм-аута в секундах.
     *
     * @return  Response
     *
     */
    public function patch(string|UriInterface $url, mixed $data, array $headers = [], ?int $timeout = null): Response {
        return $this->makeTransportRequest('PATCH', $url, $data, $headers, $timeout);
    }

    /**
     * Отправляет запрос PSR-7 и возвращает ответ PSR-7.
     *
     * @param   RequestInterface  $request  Объект запроса PSR-7.
     *
     * @return  Response
     */
    public function sendRequest(RequestInterface $request): Response {
        $data = $request->getBody()->getContents();

        return $this->makeTransportRequest(
            $request->getMethod(),
            new Uri((string) $request->getUri()),
            empty($data) ? null : $data,
            $request->getHeaders()
        );
    }

    /**
     * Отправляет запрос на сервер и возвращает объект Response с ответом.
     *
     * @param   string               $method   HTTP-метод отправки запроса.
     * @param   string|UriInterface  $url      The URI to the resource to request.
     * @param   mixed                $data     Либо ассоциативный массив, либо строка, которая будет отправлена с запросом.
     * @param   array                $headers  Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null         $timeout  Чтение тайм-аута в секундах.
     *
     * @return  Response
     *
     * @throws  InvalidArgumentException
     */
    protected function makeTransportRequest(
        string $method,
        string|UriInterface $url,
        mixed $data = null,
        array $headers = [],
        ?int $timeout = null
    ): Response {

        if (isset($this->options['headers'])) {
            $temp = (array) $this->options['headers'];

            foreach ($temp as $key => $val) {
                if (!isset($headers[$key])) {
                    $headers[$key] = $val;
                }
            }
        }

        if ($timeout === null && isset($this->options['timeout'])) {
            $timeout = $this->options['timeout'];
        }

        $userAgent = isset($this->options['userAgent']) ? $this->options['userAgent'] : null;

        if (\is_string($url)) {
            $url = new Uri($url);
        } elseif (!($url instanceof UriInterface)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Необходимо указать строку или объект %s, был предоставлен «%s».',
                    UriInterface::class,
                    \gettype($url)
                )
            );
        }

        return $this->transport->request($method, $url, $data, $headers, $timeout, $userAgent);
    }
}
