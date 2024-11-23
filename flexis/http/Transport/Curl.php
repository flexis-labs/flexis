<?php

/**
 * Часть пакета Flexis Http Framework.
 */

namespace Flexis\Http\Transport;

use Composer\CaBundle\CaBundle;
use CurlHandle;
use Flexis\Http\AbstractTransport;
use Flexis\Http\Exception\InvalidResponseCodeException;
use Flexis\Uri\UriInterface;
use Laminas\Diactoros\Response;
use Laminas\Diactoros\Stream as StreamResponse;

/**
 * Транспортный класс HTTP для использования cURL.
 */
class Curl extends AbstractTransport {
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
     * @throws  \RuntimeException
     */
    public function request(
        string $method,
        UriInterface $uri,
        mixed $data = null,
        array $headers = [],
        ?int $timeout = null,
        ?string $userAgent = null
    ): Response {

        $ch = curl_init();

        $this->setCAOptionAndValue($ch);

        $options = [];

        switch (strtoupper($method)) {
            case 'GET':
                $options[\CURLOPT_HTTPGET] = true;

                break;

            case 'POST':
                $options[\CURLOPT_POST] = true;

                break;

            default:
                $options[\CURLOPT_CUSTOMREQUEST] = strtoupper($method);

                break;
        }

        $options[\CURLOPT_NOBODY] = ($method === 'HEAD');
        $options[CURLOPT_CAINFO] = $this->getOption('curl.certpath', CaBundle::getSystemCaRootBundlePath());

        if (isset($data)) {
            if (is_scalar($data) || (isset($headers['Content-Type']) && str_starts_with($headers['Content-Type'], 'multipart/form-data'))) {
                $options[\CURLOPT_POSTFIELDS] = $data;
            } else {
                $options[\CURLOPT_POSTFIELDS] = http_build_query($data);
            }

            if (!isset($headers['Content-Type'])) {
                $headers['Content-Type'] = 'application/x-www-form-urlencoded; charset=utf-8';
            }

            if (is_scalar($options[\CURLOPT_POSTFIELDS])) {
                $headers['Content-Length'] = \strlen($options[\CURLOPT_POSTFIELDS]);
            }
        }

        $headerArray = [];

        if (!empty($headers)) {
            foreach ($headers as $key => $value) {
                if (\is_array($value)) {
                    foreach ($value as $header) {
                        $headerArray[] = "$key: $header";
                    }
                } else {
                    $headerArray[] = "$key: $value";
                }
            }

            $options[\CURLOPT_HTTPHEADER] = $headerArray;
        }

        if (isset($headers['Accept-Encoding'])) {
            $options[\CURLOPT_ENCODING] = $headers['Accept-Encoding'];
        }

        if (isset($timeout)) {
            $options[\CURLOPT_TIMEOUT]        = $timeout;
            $options[\CURLOPT_CONNECTTIMEOUT] = $timeout;
        }

        if (isset($userAgent)) {
            $options[\CURLOPT_USERAGENT] = $userAgent;
        }

        $options[\CURLOPT_URL] = (string) $uri;
        $options[\CURLOPT_HEADER] = true;
        $options[\CURLOPT_RETURNTRANSFER] = true;
        $options[\CURLOPT_HTTPHEADER][] = 'Expect:';

        if ($this->redirectsAllowed()) {
            $options[\CURLOPT_FOLLOWLOCATION] = (bool) $this->getOption('follow_location', true);
        }

        if ($this->getOption('userauth') && $this->getOption('passwordauth')) {
            $options[\CURLOPT_USERPWD]  = $this->getOption('userauth') . ':' . $this->getOption('passwordauth');
            $options[\CURLOPT_HTTPAUTH] = CURLAUTH_BASIC;
        }

        if ($protocolVersion = $this->getOption('protocolVersion')) {
            $options[\CURLOPT_HTTP_VERSION] = $this->mapProtocolVersion($protocolVersion);
        }

        foreach ($this->getOption('transport.curl', []) as $key => $value) {
            $options[$key] = $value;
        }

        curl_setopt_array($ch, $options);

        $content = curl_exec($ch);

        if (!\is_string($content)) {
            $message = curl_error($ch);

            if (empty($message)) {
                $message = 'HTTP-ответ не получен.';
            }

            throw new \RuntimeException($message);
        }

        $info = curl_getinfo($ch);

        curl_close($ch);

        return $this->getResponse($content, $info);
    }

    /**
     * Настраивает ресурсы cURL с соответствующими корневыми сертификатами.
     *
     * @param   CurlHandle  $ch  Ресурс cURL, для которого вы хотите настроить сертификаты.
     *
     * @return  void
     */
    protected function setCAOptionAndValue(CurlHandle $ch): void {
        if ($certpath = $this->getOption('curl.certpath')) {
            curl_setopt($ch, \CURLOPT_CAINFO, $certpath);

            return;
        }

        $caPathOrFile = CaBundle::getSystemCaRootBundlePath();

        if (is_dir($caPathOrFile) || (is_link($caPathOrFile) && is_dir(readlink($caPathOrFile)))) {
            curl_setopt($ch, \CURLOPT_CAPATH, $caPathOrFile);

            return;
        }

        curl_setopt($ch, \CURLOPT_CAINFO, $caPathOrFile);
    }

    /**
     * Метод для получения объекта ответа из ответа сервера.
     *
     * @param   string  $content  Полный ответ сервера, включая заголовки в виде строки, если ответ не содержит ошибок.
     * @param   array   $info     Информация запроса cURL.
     *
     * @return  Response
     *
     * @throws  InvalidResponseCodeException
     */
    protected function getResponse(string $content, array $info): Response {
        if (isset($info['header_size'])) {
            $headerString = trim(substr($content, 0, $info['header_size']));
            $headerArray  = explode("\r\n\r\n", $headerString);
            $headers      = explode("\r\n", array_pop($headerArray));
            $body         = substr($content, $info['header_size']);
        } else {
            $redirects = $info['redirect_count'] ?? 0;
            $response  = explode("\r\n\r\n", $content, 2 + $redirects);
            $body      = array_pop($response);
            $headers   = explode("\r\n", array_pop($response));
        }

        preg_match('/[0-9]{3}/', array_shift($headers), $matches);

        $code = \count($matches) ? $matches[0] : null;

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
     * Метод проверки доступности HTTP-транспорта cURL для использования.
     *
     * @return  boolean  True если доступно, иначе false.
     *
     */
    public static function isSupported(): bool {
        return \function_exists('curl_version') && curl_version();
    }

    /**
     * Возвращает константу cURL для версии протокола HTTP.
     *
     * @param   string  $version  Используемая версия протокола HTTP.
     *
     * @return  integer
     */
    private function mapProtocolVersion(string $version): int {
        switch ($version) {
            case '1.0':
                return \CURL_HTTP_VERSION_1_0;

            case '1.1':
                return \CURL_HTTP_VERSION_1_1;

            case '2.0':
            case '2':
                if (\defined('CURL_HTTP_VERSION_2')) {
                    return \CURL_HTTP_VERSION_2;
                }
        }

        return \CURL_HTTP_VERSION_NONE;
    }

    /**
     * Проверяет, разрешены ли перенаправления.
     *
     * @return  boolean
     */
    private function redirectsAllowed(): bool {
        return true;
    }
}
