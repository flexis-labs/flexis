<?php

/**
 * Часть пакета Flexis Http Framework.
 */

namespace Flexis\Http\Transport;

use Composer\CaBundle\CaBundle;
use Flexis\Http\AbstractTransport;
use Flexis\Http\Exception\InvalidResponseCodeException;
use Flexis\Uri\Uri;
use Flexis\Uri\UriInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream as StreamResponse;
use RuntimeException;

/**
 * Транспортный класс HTTP для использования потоков PHP.
 */
class Stream extends AbstractTransport {
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
    ):Response {

        $options = ['method' => strtoupper($method)];

        if (isset($data)) {
            if (is_scalar($data)) {
                $options['content'] = $data;
            } else {
                $options['content'] = http_build_query($data);
            }

            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
            }

            $headers['Content-Length'] = \strlen($options['content']);
        }

        if (isset($timeout)) {
            $options['timeout'] = $timeout;
        }

        if (isset($userAgent)) {
            $options['user_agent'] = $userAgent;
        }

        $options['ignore_errors'] = 1;

        $options['follow_location'] = (int) $this->getOption('follow_location', 1);
        $options['protocol_version'] = $this->getOption('protocolVersion', '1.0');

        if ($this->getOption('proxy.enabled', false)) {
            $options['request_fulluri'] = true;

            if ($this->getOption('proxy.host') && $this->getOption('proxy.port')) {
                $options['proxy'] = $this->getOption('proxy.host') . ':' . (int) $this->getOption('proxy.port');
            }

            if ($this->getOption('proxy.user') && $this->getOption('proxy.password')) {
                $headers['Proxy-Authorization'] = 'Basic ' . base64_encode($this->getOption('proxy.user') . ':' . $this->getOption('proxy.password'));
            }
        }

        if (!empty($headers)) {
            $headerString = '';

            foreach ($headers as $key => $value) {
                if (\is_array($value)) {
                    foreach ($value as $header) {
                        $headerString .= "$key: $header\r\n";
                    }
                } else {
                    $headerString .= "$key: $value\r\n";
                }
            }

            $options['header'] = trim($headerString, "\r\n");
        }

        if ($uri instanceof Uri && $this->getOption('userauth') && $this->getOption('passwordauth')) {
            $uri->setUser($this->getOption('userauth'));
            $uri->setPass($this->getOption('passwordauth'));
        }

        foreach ($this->getOption('transport.stream', []) as $key => $value) {
            $options[$key] = $value;
        }

        $contextOptions = stream_context_get_options(stream_context_get_default());
        $contextOptions['http'] = isset($contextOptions['http']) ? array_merge($contextOptions['http'], $options) : $options;

        $streamOptions = [
            'http' => $options,
            'ssl'  => [
                'verify_peer'      => true,
                'verify_depth'     => 5,
                'verify_peer_name' => true,
            ],
        ];

        $certpath = $this->getOption('stream.certpath', CaBundle::getSystemCaRootBundlePath());

        if (is_dir($certpath)) {
            $streamOptions['ssl']['capath'] = $certpath;
        } else {
            $streamOptions['ssl']['cafile'] = $certpath;
        }

        $context = stream_context_create($streamOptions);

        error_clear_last();

        $stream = @fopen((string) $uri, 'r', false, $context);

        if (!$stream) {
            $error = error_get_last();

            if ($error === null || $error['message'] === '') {
                $error = [
                    'message' => sprintf('Не удалось подключиться к ресурсу %s', $uri),
                ];
            }

            throw new RuntimeException($error['message']);
        }

        $metadata = stream_get_meta_data($stream);
        $content  = stream_get_contents($stream);

        fclose($stream);

        $headers = [];

        if (isset($metadata['wrapper_data']['headers'])) {
            $headers = $metadata['wrapper_data']['headers'];
        } elseif (isset($metadata['wrapper_data'])) {
            $headers = $metadata['wrapper_data'];
        }

        return $this->getResponse($headers, $content);
    }

    /**
     * Метод для получения объекта ответа из ответа сервера.
     *
     * @param   array   $headers  Заголовки ответов в виде массива.
     * @param   string  $body     Тело ответа в виде строки.
     *
     * @return  Response
     *
     * @throws  InvalidResponseCodeException
     */
    protected function getResponse(array $headers, string $body): Response {
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
     * Метод проверки доступности транспортного потока HTTP для использования.
     *
     * @return  boolean  True если доступно, иначе false.
     *
     */
    public static function isSupported(): bool {
        return \function_exists('fopen') && \is_callable('fopen') && ini_get('allow_url_fopen');
    }
}
