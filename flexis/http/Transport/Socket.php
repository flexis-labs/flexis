<?php

/**
 * Часть пакета Flexis Http Framework.
 */

namespace Flexis\Http\Transport;

use Flexis\Http\AbstractTransport;
use Flexis\Http\Exception\InvalidResponseCodeException;
use Flexis\Uri\Uri;
use Flexis\Uri\UriInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream as StreamResponse;
use RuntimeException;
use UnexpectedValueException;

/**
 * Транспортный класс HTTP для прямого использования сокетов.
 */
class Socket extends AbstractTransport {
    /**
     * Socket.
     *
     * @var    array
     */
    protected array $connections;

    /**
     * Отправляет запрос на сервер и возвращает объект Response с ответом.
     *
     * @param   string        $method     HTTP-метод отправки запроса.
     * @param   UriInterface  $uri        URI запрашиваемого ресурса.
     * @param   mixed         $data       Либо ассоциативный массив, либо строка, которая будет отправлена с запросом.
     * @param   array         $headers    Массив заголовков запроса для отправки вместе с запросом.
     * @param   integer|null  $timeout    Чтение тайм-аута в секундах.
     * @param   string|null   $userAgent  Необязательная строка пользовательского агента, отправляемая вместе с запросом.
     *
     * @return  Response
     *
     * @throws  RuntimeException
     */
    public function request(
        string $method,
        UriInterface $uri,
        mixed $data = null,
        array $headers = [],
        ?int $timeout = null,
        ?string $userAgent = null
    ): Response {
        $connection = $this->connect($uri, $timeout);

        if (!\is_resource($connection)) {
            throw new RuntimeException('Не подключен к серверу.');
        }

        $meta = stream_get_meta_data($connection);

        if ($meta['timed_out']) {
            throw new RuntimeException('Время соединения с сервером истекло.');
        }

        $path = $uri->toString(['path', 'query']);

        if (!empty($data)) {
            if (!is_scalar($data)) {
                $data = http_build_query($data);
            }

            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
            }

            $headers['Content-Length'] = \strlen($data);
        }

        $protocolVersion = $this->getOption('protocolVersion', '1.0');

        $request   = [];
        $request[] = strtoupper($method) . ' ' . ((empty($path)) ? '/' : $path) . ' HTTP/' . $protocolVersion;

        if (!isset($headers['Host'])) {
            $request[] = 'Host: ' . $uri->getHost();
        }

        if (isset($userAgent)) {
            $headers['User-Agent'] = $userAgent;
        }

        if ($uri->getUser()) {
            $authString               = $uri->getUser() . ':' . $uri->getPass();
            $headers['Authorization'] = 'Basic ' . base64_encode($authString);
        }

        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                if (\is_array($value)) {
                    foreach ($value as $header) {
                        $request[] = "$key: $header";
                    }
                } else {
                    $request[] = "$key: $value";
                }
            }
        }

        if ($this->getOption('userauth') && $this->getOption('passwordauth')) {
            $request[] = 'Authorization: Basic ' . base64_encode($this->getOption('userauth') . ':' . $this->getOption('passwordauth'));
        }

        foreach ($this->getOption('transport.socket', []) as $value) {
            $request[] = $value;
        }

        if (!empty($data)) {
            $request[] = null;
            $request[] = $data;
        }

        fwrite($connection, implode("\r\n", $request) . "\r\n\r\n");

        $content = '';

        while (!feof($connection)) {
            $content .= fgets($connection, 4096);
        }

        $content = $this->getResponse($content);

        if ($content->getStatusCode() >= 301 && $content->getStatusCode() < 400 && $content->hasHeader('Location')) {
            return $this->request($method, new Uri($content->getHeaderLine('Location')), $data, $headers, $timeout, $userAgent);
        }

        return $content;
    }

    /**
     * Метод для получения объекта ответа из ответа сервера.
     *
     * @param   string  $content  Полный ответ сервера, включая заголовки.
     *
     * @return  Response
     *
     * @throws  UnexpectedValueException
     * @throws  InvalidResponseCodeException
     */
    protected function getResponse(string $content): Response {
        if (empty($content)) {
            throw new UnexpectedValueException('Никакого контента в ответ.');
        }

        $response = explode("\r\n\r\n", $content, 2);
        $headers  = explode("\r\n", $response[0]);
        $body     = empty($response[1]) ? '' : $response[1];

        preg_match('/[0-9]{3}/', array_shift($headers), $matches);
        $code = $matches[0];

        if (!is_numeric($code)) {
            throw new InvalidResponseCodeException('Код ответа HTTP не найден.');
        }

        $statusCode      = (int) $code;
        $verifiedHeaders = $this->processHeaders($headers);

        $streamInterface = new StreamResponse('php://memory', 'rw');
        $streamInterface->write($body);

        return new Response($streamInterface, $statusCode, $verifiedHeaders);
    }

    /**
     * Метод подключения к серверу и получения ресурса.
     *
     * @param   UriInterface  $uri      URI для подключения.
     * @param   integer|null  $timeout  Чтение тайм-аута в секундах.
     *
     * @return  resource  Socket connection resource.
     *
     * @throws  RuntimeException
     */
    protected function connect(UriInterface $uri, ?int $timeout = null): Socket {
        $errno = null;
        $err   = null;

        $host = ($uri->isSsl()) ? 'ssl://' . $uri->getHost() : $uri->getHost();

        if (!$uri->getPort()) {
            $port = ($uri->getScheme() == 'https') ? 443 : 80;
        } else {
            $port = $uri->getPort();
        }

        $key = md5($host . $port);

        if (!empty($this->connections[$key]) && \is_resource($this->connections[$key])) {
            $meta = stream_get_meta_data($this->connections[$key]);

            if ($meta['eof']) {
                if (!fclose($this->connections[$key])) {
                    throw new RuntimeException('Невозможно закрыть соединение.');
                }
            } elseif (!$meta['timed_out']) {
                return $this->connections[$key];
            }
        }

        if (!is_numeric($timeout)) {
            $timeout = ini_get('default_socket_timeout');
        }

        error_clear_last();

        $connection = @fsockopen($host, $port, $errno, $err, $timeout);

        if (!$connection) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => sprintf('Не удалось подключиться к ресурсу %s: %s (%d)', $uri, $err, $errno),
                ];
            }

            throw new RuntimeException($error['message']);
        }

        $this->connections[$key] = $connection;

        stream_set_timeout($this->connections[$key], (int) $timeout);

        return $this->connections[$key];
    }

    /**
     * Метод проверки доступности транспортного сокета HTTP для использования.
     *
     * @return  boolean   True если доступно, иначе false.
     *
     */
    public static function isSupported(): bool {
        return \function_exists('fsockopen') && \is_callable('fsockopen');
    }
}
