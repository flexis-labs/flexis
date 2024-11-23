<?php

/**
 * Часть пакета Flexis Framework Utilities.
 */

namespace Flexis\Utilities;

/**
 * IpHelper — служебный класс для обработки IP-адресов.
 */
final class IpHelper {
    /**
     * IP-адрес текущего посетителя.
     *
     * @var    string|null
     */
    private static ?string $ip = null;

    /**
     * Разрешить переопределение IP-адресов через HTTP-заголовки X-Forwarded-For или Client-Ip?
     *
     * @var    boolean
     */
    private static bool $allowIpOverrides = false;

    /**
     * Приватный конструктор для предотвращения создания экземпляра этого класса.
     */
    private function __construct() {}

    /**
     * Получение IP-адреса текущего посетителя.
     *
     * @return  string|null
     */
    public static function getIp(): ?string {
        if (self::$ip === null) {
            $ip = self::detectAndCleanIP();

            if (!empty($ip) && ($ip != '0.0.0.0') && \function_exists('inet_pton') && \function_exists('inet_ntop')) {
                $myIP = @inet_pton($ip);

                if ($myIP !== false) {
                    $ip = inet_ntop($myIP);
                }
            }

            self::setIp($ip);
        }

        return self::$ip;
    }

    /**
     * Устанавливает IP-адрес текущего посетителя.
     *
     * @param string $ip  IP-адрес посетителя.
     *
     * @return  void
     */
    public static function setIp(string $ip): void {
        self::$ip = $ip;
    }

    /**
     * Это IP-адрес IPv6?
     *
     * @param   string   $ip  IP-адрес для проверки.
     *
     * @return  boolean
     */
    public static function isIPv6($ip): bool {
        return str_contains($ip, ':');
    }

    /**
     * Проверяет, содержится ли IP-адрес в списке IP-адресов или выражений IP.
     *
     * @param string       $ip       Адрес IPv4/IPv6 для проверки.
     * @param array|string $ipTable  Выражение IP (или список выражений IP, разделенных запятыми, или массив), для проверки.
     *
     * @return  boolean
     */
    public static function IPinList(string $ip, array|string $ipTable = ''): bool {
        // Нет смысла продолжать работу с пустым списком IP-адресов.
        if (empty($ipTable)) {
            return false;
        }

        // Если список IP-адресов не является массивом, преобразуем его в массив.
        if (!\is_array($ipTable)) {
            if (str_contains($ipTable, ',')) {
                $ipTable = explode(',', $ipTable);
                $ipTable = array_map('trim', $ipTable);
            } else {
                $ipTable = trim($ipTable);
                $ipTable = [$ipTable];
            }
        }

        // Если IP-адрес не найден, вернём false
        if ($ip === '0.0.0.0') {
            return false;
        }

        // Если IP не указан, вернём false
        if (empty($ip)) {
            return false;
        }

        // Проверка работоспособности
        if (!\function_exists('inet_pton')) {
            return false;
        }

        // Получим представление IP-адреса in_adds
        $myIP = @inet_pton($ip);

        // Если IP в неузнаваемом формате
        if ($myIP === false) {
            return false;
        }

        $ipv6 = self::isIPv6($ip);

        foreach ($ipTable as $ipExpression) {
            $ipExpression = trim($ipExpression);

            // Инклюзивный диапазон IP-адресов, т.е. 123.123.123.123-124.125.126.127
            if (str_contains($ipExpression, '-')) {
                list($from, $to) = explode('-', $ipExpression, 2);

                if ($ipv6 && (!self::isIPv6($from) || !self::isIPv6($to))) {
                    // Не применять фильтрацию IPv4 к адресу IPv6.
                    continue;
                }

                if (!$ipv6 && (self::isIPv6($from) || self::isIPv6($to))) {
                    // Не применять фильтрацию IPv6 к адресу IPv4.
                    continue;
                }

                $from = @inet_pton(trim($from));
                $to   = @inet_pton(trim($to));

                // Проверка работоспособности
                if (($from === false) || ($to === false)) {
                    continue;
                }

                // Поменяем местами, если они расположены в неправильном порядке.
                if ($from > $to) {
                    list($from, $to) = [$to, $from];
                }

                if (($myIP >= $from) && ($myIP <= $to)) {
                    return true;
                }
            } elseif (str_contains($ipExpression, '/')) {
                // Предоставлена сетевая маска или CIDR.
                $binaryip = self::inetToBits($myIP);

                list($net, $maskbits) = explode('/', $ipExpression, 2);

                if ($ipv6 && !self::isIPv6($net)) {
                    // Не применять фильтрацию IPv4 к адресу IPv6.
                    continue;
                }

                if (!$ipv6 && self::isIPv6($net)) {
                    // Не применять фильтрацию IPv6 к адресу IPv4.
                    continue;
                }

                if ($ipv6 && strstr($maskbits, ':')) {
                    // Выполним проверку CIDR IPv6.
                    if (self::checkIPv6CIDR($myIP, $ipExpression)) {
                        return true;
                    }

                    // Если мы не совпали, переходим к следующему выражению
                    continue;
                }

                if (!$ipv6 && strstr($maskbits, '.')) {
                    // Преобразование сетевой маски IPv4 в CIDR
                    $long     = ip2long($maskbits);
                    $base     = ip2long('255.255.255.255');
                    $maskbits = 32 - log(($long ^ $base) + 1, 2);
                }

                // Преобразование IP-адреса сети в представление in_addr
                $net = @inet_pton($net);

                // Проверка работоспособности
                if ($net === false) {
                    continue;
                }

                // Получим двоичное представление сети
                $expectedNumberOfBits = $ipv6 ? 128 : 24;
                $binarynet            = str_pad(self::inetToBits($net), $expectedNumberOfBits, '0', STR_PAD_RIGHT);

                // Проверим соответствующие биты IP и сети
                $ipNetBits = substr($binaryip, 0, $maskbits);
                $netBits   = substr($binarynet, 0, $maskbits);

                if ($ipNetBits === $netBits) {
                    return true;
                }
            } else {
                // IPv6: Поддерживаются только отдельные IP-адреса.
                if ($ipv6) {
                    $ipExpression = trim($ipExpression);

                    if (!self::isIPv6($ipExpression)) {
                        continue;
                    }

                    $ipCheck = @inet_pton($ipExpression);

                    if ($ipCheck === false) {
                        continue;
                    }

                    if ($ipCheck == $myIP) {
                        return true;
                    }
                } else {
                    // Стандартный IPv4-адрес, т.е. 123.123.123.123, или частичный IP-адрес, т.е. 123.[123.][123.][123]
                    $dots = 0;

                    if (str_ends_with($ipExpression, '.')) {
                        // Частичный IP-адрес. Преобразование в CIDR и повторное сопоставление
                        foreach (count_chars($ipExpression, 1) as $i => $val) {
                            if ($i == 46) {
                                $dots = $val;
                            }
                        }

                        switch ($dots) {
                            case 1:
                                $netmask = '255.0.0.0';
                                $ipExpression .= '0.0.0';

                                break;

                            case 2:
                                $netmask = '255.255.0.0';
                                $ipExpression .= '0.0';

                                break;

                            case 3:
                                $netmask = '255.255.255.0';
                                $ipExpression .= '0';

                                break;

                            default:
                                $dots = 0;
                        }

                        if ($dots) {
                            $binaryip = self::inetToBits($myIP);

                            // Преобразование сетевой маски в CIDR
                            $long     = ip2long($netmask);
                            $base     = ip2long('255.255.255.255');
                            $maskbits = 32 - log(($long ^ $base) + 1, 2);

                            $net = @inet_pton($ipExpression);

                            // Проверка работоспособности
                            if ($net === false) {
                                continue;
                            }

                            // Получим двоичное представление сети
                            $expectedNumberOfBits = $ipv6 ? 128 : 24;
                            $binarynet            = str_pad(self::inetToBits($net), $expectedNumberOfBits, '0', STR_PAD_RIGHT);

                            // Проверим соответствующие биты IP и сети
                            $ipNetBits = substr($binaryip, 0, $maskbits);
                            $netBits   = substr($binarynet, 0, $maskbits);

                            if ($ipNetBits === $netBits) {
                                return true;
                            }
                        }
                    }

                    if (!$dots) {
                        $ip = @inet_pton(trim($ipExpression));

                        if ($ip == $myIP) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Работает с REMOTE_ADDR, не содержащим IP-адрес пользователя.
     *
     * @return  void
     */
    public static function workaroundIPIssues(): void {
        $ip = self::getIp();

        if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] === $ip) {
            return;
        }

        if (isset($_SERVER['REMOTE_ADDR'])) {
            $_SERVER['FLEXIS_REMOTE_ADDR'] = $_SERVER['REMOTE_ADDR'];
        } elseif (\function_exists('getenv')) {
            if (getenv('REMOTE_ADDR')) {
                $_SERVER['FLEXIS_REMOTE_ADDR'] = getenv('REMOTE_ADDR');
            }
        }

        $_SERVER['REMOTE_ADDR'] = $ip;
    }

    /**
     * Разрешить переопределение IP-адреса удаленного клиента HTTP-заголовком X-Forwarded-For или Client-Ip?
     *
     * @param boolean $newState  True, чтобы разрешить переопределение.
     *
     * @return  void
     */
    public static function setAllowIpOverrides(bool $newState): void {
        self::$allowIpOverrides = $newState;
    }

    /**
     * Возвращает IP-адрес посетителя.
     *
     * Автоматически обрабатывает обратные прокси-серверы, сообщающие IP-адреса промежуточных устройств, таких как балансировщики нагрузки.
     *
     * Примеры:
     * - https://www.akeebabackup.com/support/admin-tools/13743-double-ip-adresses-in-security-exception-log-warnings.html
     * - https://stackoverflow.com/questions/2422395/why-is-request-envremote-addr-returning-two-ips
     *
     * Используемое решение предполагает, что последний IP-адрес является внешним.
     *
     * @return  string
     */
    protected static function detectAndCleanIP(): string {
        $ip = self::detectIP();

        if (str_contains($ip, ',') || str_contains($ip, ' ')) {
            $ip  = str_replace(' ', ',', $ip);
            $ip  = str_replace(',,', ',', $ip);
            $ips = explode(',', $ip);
            $ip  = '';

            while (empty($ip) && !empty($ips)) {
                $ip = array_shift($ips);
                $ip = trim($ip);
            }
        } else {
            $ip = trim($ip);
        }

        return $ip;
    }

    /**
     * Возвращает IP-адрес посетителя.
     *
     * @return  string
     */
    protected static function detectIP(): string {
        // Обычно устанавливается суперглобальный $_SERVER.
        if (isset($_SERVER)) {
            // Есть ли у нас HTTP-заголовок с пересылкой X (например, NginX)?
            if (self::$allowIpOverrides && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                return $_SERVER['HTTP_X_FORWARDED_FOR'];
            }

            // Есть ли у нас заголовок client-ip (например, непрозрачный прокси)?
            if (self::$allowIpOverrides && isset($_SERVER['HTTP_CLIENT_IP'])) {
                return $_SERVER['HTTP_CLIENT_IP'];
            }

            // Обычный сервер без прокси или сервер за прозрачным прокси
            if (isset($_SERVER['REMOTE_ADDR'])) {
                return $_SERVER['REMOTE_ADDR'];
            }
        }

        /*
         * Эта часть выполняется на PHP, работающем как CGI, или на SAPI,
         * которые не устанавливают суперглобальный параметр $_SERVER.
         * Если getenv() отключен, вы облажались!
         */
        if (!\function_exists('getenv')) {
            return '';
        }

        // Есть ли у нас HTTP-заголовок x-forwarded-for?
        if (self::$allowIpOverrides && getenv('HTTP_X_FORWARDED_FOR')) {
            return getenv('HTTP_X_FORWARDED_FOR');
        }

        // Есть ли у нас заголовок client-ip?
        if (self::$allowIpOverrides && getenv('HTTP_CLIENT_IP')) {
            return getenv('HTTP_CLIENT_IP');
        }

        // Обычный сервер без прокси или сервер за прозрачным прокси
        if (getenv('REMOTE_ADDR')) {
            return getenv('REMOTE_ADDR');
        }

        // По-видимому, универсальный случай сломанных серверов.
        return '';
    }

    /**
     * Преобразует вывод inet_pton в битовую строку.
     *
     * @param string $inet  Представление in_addr адреса IPv4 или IPv6.
     *
     * @return  string
     */
    protected static function inetToBits(string $inet): string {
        if (\strlen($inet) == 4) {
            $unpacked = unpack('A4', $inet);
        } else {
            $unpacked = unpack('A16', $inet);
        }

        $unpacked = str_split($unpacked[1]);
        $binaryip = '';

        foreach ($unpacked as $char) {
            $binaryip .= str_pad(decbin(\ord($char)), 8, '0', STR_PAD_LEFT);
        }

        return $binaryip;
    }

    /**
     * Проверяет, является ли IPv6-адрес $ip частью блока IPv6 CIDR $cidrnet.
     *
     * @param string $ip       IPv6-адрес для проверки, например, 21DA:00D3:0000:2F3B:02AC:00FF:FE28:9C5A
     * @param string $cidrnet  Блок IPv6 CIDR, например, 21DA:00D3:0000:2F3B::/64
     *
     * @return  boolean
     */
    protected static function checkIPv6CIDR(string $ip, string $cidrnet): bool {
        $ip       = inet_pton($ip);
        $binaryip = self::inetToBits($ip);

        list($net, $maskbits) = explode('/', $cidrnet);
        $net                  = inet_pton($net);
        $binarynet            = self::inetToBits($net);

        $ipNetBits = substr($binaryip, 0, $maskbits);
        $netBits   = substr($binarynet, 0, $maskbits);

        return $ipNetBits === $netBits;
    }
}
