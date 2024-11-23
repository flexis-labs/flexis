<?php

/**
 * Часть пакета Flexis Filesystem Framework.
 */

namespace Flexis\Filesystem\Clients;

use Flexis\Filesystem\Exception\FilesystemException;
use FTP\Connection;

// phpcs:disable PSR1.Files.SideEffects
/*
 * Коды ошибок:
 * - 30 : Unable to connect to host
 * - 31 : Not connected
 * - 32 : Unable to send command to server
 * - 33 : Bad username
 * - 34 : Bad password
 * - 35 : Bad response
 * - 36 : Passive mode failed
 * - 37 : Data transfer error
 * - 38 : Local filesystem error
 */

if (!\defined('CRLF')) {
    \define('CRLF', "\r\n");
}

if (!\defined('FTP_AUTOASCII')) {
    \define('FTP_AUTOASCII', -1);
}

if (!\defined('FTP_BINARY')) {
    \define('FTP_BINARY', 1);
}

if (!\defined('FTP_ASCII')) {
    \define('FTP_ASCII', 0);
}

if (!\defined('FTP_NATIVE')) {
    \define('FTP_NATIVE', (\function_exists('ftp_connect')) ? 1 : 0);
}
// phpcs:enable PSR1.Files.SideEffects

/**
 * Класс FTP-клиента
 */
class FtpClient {
    /**
     * Ресурс сокета
     *
     * @var    resource
     */
    private $conn;

    /**
     * Ресурс подключения порта данных
     *
     * @var    resource
     */
    private $dataconn;

    /**
     * Информация о пассивном соединении
     *
     * @var    array
     */
    private array $pasv;

    /**
     * Ответное сообщение
     *
     * @var    string
     */
    private string $response;

    /**
     * Код ответа
     *
     * @var    integer
     */
    private int $responseCode;

    /**
     * Ответное сообщение
     *
     * @var    string
     */
    private string $responseMsg;

    /**
     * Ограничение времени ожидания
     *
     * @var    integer
     */
    private int $timeout = 15;

    /**
     * Тип перевода
     *
     * @var    integer
     */
    private int $type;

    /**
     * Массив для хранения расширений файлов формата ascii.
     *
     * @var    array
     */
    private array $autoAscii = [
        'asp',
        'bat',
        'c',
        'cpp',
        'csv',
        'h',
        'htm',
        'html',
        'shtml',
        'ini',
        'inc',
        'log',
        'php',
        'php3',
        'pl',
        'perl',
        'sh',
        'sql',
        'txt',
        'xhtml',
        'xml',
    ];

    /**
     * Массив для хранения собственных символов окончания строки
     *
     * @var    array
     */
    private array $lineEndings = ['UNIX' => "\n", 'WIN' => "\r\n"];

    /**
     * Контейнер экземпляров FtpClient.
     *
     * @var    FtpClient[]
     */
    protected static array $instances = [];

    /**
     * Конструктор объекта FtpClient
     *
     * @param   array  $options  Ассоциативный массив опций для установки
     *
     */
    public function __construct(array $options = []) {
        if (!isset($options['type'])) {
            $options['type'] = \FTP_BINARY;
        }

        $this->setOptions($options);

        if (FTP_NATIVE) {
            class_exists('Flexis\\Filesystem\\Buffer');
        }
    }

    /**
     * Деструктор объекта FtpClient
     *
     * Закрывает существующее соединение, если оно у нас есть
     *
     */
    public function __destruct() {
        if (\is_resource($this->conn)) {
            $this->quit();
        }
    }

    /**
     * Возвращает глобальный объект соединителя FTP, создавая его только в том случае, если он еще не существует.
     *
     * При желании в параметрах можно указать логин и пароль. Если вы это сделаете, вы не сможете снова войти в систему() с другими учетными данными, используя один и тот же объект.
     * Если вы не используете эту опцию, вы должны завершить() текущее соединение, когда закончите, чтобы освободить его для использования другими.
     *
     * @param   string       $host     Хост для подключения
     * @param   string       $port     Порт для подключения
     * @param   array        $options  Массив с любым из этих вариантов: type=>[FTP_AUTOASCII|FTP_ASCII|FTP_BINARY], timeout=>(int)
     * @param   string|null  $user     Логин, которое будет использоваться для подключения
     * @param   string|null  $pass     Пароль для подключения
     *
     * @return  FtpClient  Объект FTP-клиент.
     *
     */
    public static function getInstance(
        string $host = '127.0.0.1',
        string $port = '21',
        array $options = [],
        ?string $user = null,
        ?string $pass = null
    ): self {

        $signature = $user . ':' . $pass . '@' . $host . ':' . $port;

        if (!isset(self::$instances[$signature]) || !\is_object(self::$instances[$signature])) {
            self::$instances[$signature] = new static($options);
        } else {
            self::$instances[$signature]->setOptions($options);
        }

        if (!self::$instances[$signature]->isConnected()) {
            $return = self::$instances[$signature]->connect($host, $port);

            if ($return && $user !== null && $pass !== null) {
                self::$instances[$signature]->login($user, $pass);
            }
        }

        return self::$instances[$signature];
    }

    /**
     * Устанавливает параметры клиента
     *
     * @param   array  $options  Ассоциативный массив опций для установки
     *
     * @return  boolean  
     *
     */
    public function setOptions(array $options): bool {
        if (isset($options['type'])) {
            $this->type = $options['type'];
        }

        if (isset($options['timeout'])) {
            $this->timeout = $options['timeout'];
        }

        return true;
    }

    /**
     * Способ подключения к FTP-серверу
     *
     * @param   string   $host  Хост для подключения [по умолчанию: 127.0.0.1]
     * @param   integer  $port  Порт для подключения [по умолчанию: порт 21]
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function connect(string $host = '127.0.0.1', int $port = 21): bool {
        $errno = null;
        $err   = null;

        if (\is_resource($this->conn)) {
            return true;
        }

        if (FTP_NATIVE) {
            $this->conn = @ftp_connect($host, $port, $this->timeout);

            if ($this->conn === false) {
                throw new FilesystemException(sprintf('%1$s: Не удалось подключиться к хосту «%2$s» через порт «%3$s»', __METHOD__, $host, $port));
            }

            ftp_set_option($this->conn, \FTP_TIMEOUT_SEC, $this->timeout);

            return true;
        }

        $this->conn = @ fsockopen($host, $port, $errno, $err, $this->timeout);

        if (!$this->conn) {
            throw new FilesystemException(
                sprintf(
                    '%1$s: Не удалось подключиться к хосту «%2$s» через порт «%3$s». Номер ошибки сокета: %4$s и сообщение об ошибке: %5$s',
                    __METHOD__,
                    $host,
                    $port,
                    $errno,
                    $err
                )
            );
        }

        socket_set_timeout($this->conn, $this->timeout, 0);

        if (!$this->_verifyResponse(220)) {
            throw new FilesystemException(sprintf('%1$s: Плохой ответ. Ответ сервера: %2$s [ожидается: 220]', __METHOD__, $this->response));
        }

        return true;
    }

    /**
     * Метод определения того, подключен ли объект к FTP-серверу
     *
     * @return  boolean  True если подключен
     *
     */
    public function isConnected(): bool {
        return \is_resource($this->conn);
    }

    /**
     * Способ входа на сервер после подключения
     *
     * @param   string  $user  Логин для входа на сервер
     * @param   string  $pass  Пароль для входа на сервер
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function login(string $user = 'anonymous', string $pass = 'ftp@demohost.org') : bool {
        if (FTP_NATIVE) {
            if (@ftp_login($this->conn, $user, $pass) === false) {
                throw new FilesystemException(__METHOD__ . ': Невозможно войти');
            }

            return true;
        }

        if (!$this->_putCmd('USER ' . $user, [331, 503])) {
            throw new FilesystemException(
                sprintf('%1$s: Неверный логин. Ответ сервера: %2$s [ожидается: 331]. Логин отправлено: %3$s', __METHOD__, $this->response, $user)
            );
        }

        if ($this->responseCode == 503) {
            return true;
        }

        if (!$this->_putCmd('PASS ' . $pass, 230)) {
            throw new FilesystemException(sprintf('%1$s: Неверный пароль. Ответ сервера: %2$s [ожидается: 230].', __METHOD__, $this->response));
        }

        return true;
    }

    /**
     * Способ выхода и закрытия соединения
     *
     * @return  boolean  
     *
     */
    public function quit(): bool {
        if (FTP_NATIVE) {
            @ftp_close($this->conn);

            return true;
        }

        @fwrite($this->conn, "QUIT\r\n");
        @fclose($this->conn);

        return true;
    }

    /**
     * Метод получения текущего рабочего каталога на FTP-сервере
     *
     * @return  string   Текущий рабочий каталог
     *
     * @throws  FilesystemException
     */
    public function pwd(): string {
        if (FTP_NATIVE) {
            if (($ret = @ftp_pwd($this->conn)) === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            return $ret;
        }

        $match = [null];

        if (!$this->_putCmd('PWD', 257)) {
            throw new FilesystemException(sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 257]', __METHOD__, $this->response));
        }

        preg_match('/"[^"\r\n]*"/', $this->response, $match);

        return preg_replace('/"/', '', $match[0]);
    }

    /**
     * Метод для системной строки с FTP-сервера
     *
     * @return  string   Строка системного идентификатора
     *
     * @throws  FilesystemException
     */
    public function syst(): string {
        if (FTP_NATIVE) {
            if (($ret = @ftp_systype($this->conn)) === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }
        } else {
            if (!$this->_putCmd('SYST', 215)) {
                throw new FilesystemException(sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 215]', __METHOD__, $this->response));
            }

            $ret = $this->response;
        }

        if (strpos(strtoupper($ret), 'MAC') !== false) {
            $ret = 'MAC';
        } elseif (strpos(strtoupper($ret), 'WIN') !== false) {
            $ret = 'WIN';
        } else {
            $ret = 'UNIX';
        }

        return $ret;
    }

    /**
     * Способ изменения текущего рабочего каталога на FTP-сервере
     *
     * @param   string  $path  Путь для изменения на сервере
     *
     * @return  boolean 
     *
     * @throws  FilesystemException
     */
    public function chdir(string $path): bool {
        if (FTP_NATIVE) {
            if (@ftp_chdir($this->conn, $path) === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            return true;
        }

        if (!$this->_putCmd('CWD ' . $path, 250)) {
            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 250].  Путь отправки: %3$s', __METHOD__, $this->response, $path)
            );
        }

        return true;
    }

    /**
     * Способ повторной инициализации сервера, т.е. нужно войти еще раз
     *
     * ПРИМЕЧАНИЕ. Эта команда доступна не на всех серверах.
     *
     * @return  boolean 
     *
     * @throws  FilesystemException
     */
    public function reinit(): bool {
        if (FTP_NATIVE) {
            if (@ftp_site($this->conn, 'REIN') === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            return true;
        }

        if (!$this->_putCmd('REIN', 220)) {
            throw new FilesystemException(sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 220]', __METHOD__, $this->response));
        }

        return true;
    }

    /**
     * Способ переименования файла/папки на FTP-сервере
     *
     * @param   string  $from  Путь для изменения файла/папки
     * @param   string  $to    Путь для изменения файла/папки
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function rename(string $from, string $to): bool {
        if (FTP_NATIVE) {
            if (@ftp_rename($this->conn, $from, $to) === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            return true;
        }

        if (!$this->_putCmd('RNFR ' . $from, 350)) {
            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 350].  Из отправленного пути: %3$s', __METHOD__, $this->response, $from)
            );
        }

        if (!$this->_putCmd('RNTO ' . $to, 250)) {
            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 250].  Путь отправлен: %3$s', __METHOD__, $this->response, $to)
            );
        }

        return true;
    }

    /**
     * Способ изменения режима пути на FTP-сервере
     *
     * @param   string  $path  Путь к изменению режима включен
     * @param   mixed   $mode Восьмеричное значение для изменения режима, например. '0777', 0777 или 511 (строка или целое число)
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function chmod(string $path, mixed $mode): bool {
        if ($path == '') {
            $path = '.';
        }

        if (\is_int($mode)) {
            $mode = decoct($mode);
        }

        if (FTP_NATIVE) {
            if (@ftp_site($this->conn, 'CHMOD ' . $mode . ' ' . $path) === false) {
                if (!\defined('PHP_WINDOWS_VERSION_MAJOR')) {
                    throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
                }

                return false;
            }

            return true;
        }

        if (!$this->_putCmd('SITE CHMOD ' . $mode . ' ' . $path, [200, 250])) {
            if (!\defined('PHP_WINDOWS_VERSION_MAJOR')) {
                throw new FilesystemException(
                    sprintf(
                        '%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 250].  Путь отправки: %3$s.  Режим отправлен: %4$s',
                        __METHOD__,
                        $this->response,
                        $path,
                        $mode
                    )
                );
            }

            return false;
        }

        return true;
    }

    /**
     * Способ удаления пути [файла/папки] на FTP-сервере
     *
     * @param   string  $path  Путь для удаления
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function delete(string $path): bool {
        if (FTP_NATIVE) {
            if (@ftp_delete($this->conn, $path) === false) {
                if (@ftp_rmdir($this->conn, $path) === false) {
                    throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
                }
            }

            return true;
        }

        if (!$this->_putCmd('DELE ' . $path, 250)) {
            if (!$this->_putCmd('RMD ' . $path, 250)) {
                throw new FilesystemException(
                    sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 250].  Путь отправлен: %3$s', __METHOD__, $this->response, $path)
                );
            }
        }

        return true;
    }

    /**
     * Способ создания каталога на FTP-сервере
     *
     * @param   string  $path  Каталог для создания
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function mkdir(string $path): bool {
        if (FTP_NATIVE) {
            if (@ftp_mkdir($this->conn, $path) === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            return true;
        }

        if (!$this->_putCmd('MKD ' . $path, 257)) {
            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 257].  Путь отправлен: %3$s', __METHOD__, $this->response, $path)
            );
        }

        return true;
    }

    /**
     * Метод возобновления передачи данных с данного байта
     *
     * @param   integer  $point  Байт для возобновления передачи
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function restart(int $point): bool {
        if (FTP_NATIVE) {
            if (@ftp_site($this->conn, 'REST ' . $point) === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            return true;
        }

        if (!$this->_putCmd('REST ' . $point, 350)) {
            throw new FilesystemException(
                sprintf(
                    '%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 350].  Точка перезапуска отправлена: %3$s',
                    __METHOD__,
                    $this->response,
                    $point
                )
            );
        }

        return true;
    }

    /**
     * Способ создания пустого файла на FTP-сервере
     *
     * @param   string  $path  Путь к локальному файлу для хранения на FTP-сервере
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function create(string $path): bool {
        if (FTP_NATIVE) {
            if (@ftp_pasv($this->conn, true) === false) {
                throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
            }

            $buffer = fopen('buffer://tmp', 'r');

            if (@ftp_fput($this->conn, $path, $buffer, \FTP_ASCII) === false) {
                fclose($buffer);

                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            fclose($buffer);

            return true;
        }

        if (!$this->_passive()) {
            throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
        }

        if (!$this->_putCmd('STOR ' . $path, [150, 125])) {
            @ fclose($this->dataconn);

            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 150 или 125].  Путь отправлен: %3$s', __METHOD__, $this->response, $path)
            );
        }

        fclose($this->dataconn);

        if (!$this->_verifyResponse(226)) {
            throw new FilesystemException(
                sprintf('%1$s: Передача не удалась.  Ответ сервера: %2$s [ожидается: 226].  Путь отправлен: %3$s', __METHOD__, $this->response, $path)
            );
        }

        return true;
    }

    /**
     * Метод чтения файла из содержимого FTP-сервера в буфер
     *
     * @param   string  $remote  Путь к удаленному файлу для чтения на FTP-сервере
     * @param   string  $buffer  Буферная переменная для чтения содержимого файла.
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function read(string $remote, string &$buffer): bool {
        $mode = $this->_findMode($remote);

        if (FTP_NATIVE) {
            if (@ftp_pasv($this->conn, true) === false) {
                throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
            }

            $tmp = fopen('buffer://tmp', 'br+');

            if (@ftp_fget($this->conn, $tmp, $remote, $mode) === false) {
                fclose($tmp);

                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            rewind($tmp);
            $buffer = '';

            while (!feof($tmp)) {
                $buffer .= fread($tmp, 8192);
            }

            fclose($tmp);

            return true;
        }

        $this->_mode($mode);

        if (!$this->_passive()) {
            throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
        }

        if (!$this->_putCmd('RETR ' . $remote, [150, 125])) {
            @ fclose($this->dataconn);

            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 150 или 125].  Путь отправлен: %3$s', __METHOD__, $this->response, $remote)
            );
        }

        $buffer = '';

        while (!feof($this->dataconn)) {
            $buffer .= fread($this->dataconn, 4096);
        }

        fclose($this->dataconn);

        if ($mode == \FTP_ASCII) {
            $os = 'UNIX';

            if (\defined('PHP_WINDOWS_VERSION_MAJOR')) {
                $os = 'WIN';
            }

            $buffer = preg_replace('/' . CRLF . '/', $this->lineEndings[$os], $buffer);
        }

        if (!$this->_verifyResponse(226)) {
            throw new FilesystemException(
                sprintf(
                    '%1$s: Передача не удалась.  Ответ сервера: %2$s [ожидается: 226].  Точка перезапуска отправлена: %3$s',
                    __METHOD__,
                    $this->response,
                    $remote
                )
            );
        }

        return true;
    }

    /**
     * Метод получения файла с FTP-сервера и сохранения его в локальном файле
     *
     * @param   string  $local   Локальный путь для сохранения удаленного файла
     * @param   string  $remote  Путь к удаленному файлу для доступа на FTP-сервер
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function get(string $local, string $remote): bool {
        $mode = $this->_findMode($remote);

        if (FTP_NATIVE) {
            if (@ftp_pasv($this->conn, true) === false) {
                throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
            }

            if (@ftp_get($this->conn, $local, $remote, $mode) === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            return true;
        }

        $this->_mode($mode);

        $fp = fopen($local, 'wb');

        if (!$fp) {
            throw new FilesystemException(sprintf('%1$s: Невозможно открыть локальный файл для записи.  Локальный путь: %2$s', __METHOD__, $local));
        }

        if (!$this->_passive()) {
            throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
        }

        if (!$this->_putCmd('RETR ' . $remote, [150, 125])) {
            @ fclose($this->dataconn);

            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 150 или 125].  Путь отправлен: %3$s', __METHOD__, $this->response, $remote)
            );
        }

        while (!feof($this->dataconn)) {
            $buffer = fread($this->dataconn, 4096);
            fwrite($fp, $buffer, 4096);
        }

        fclose($this->dataconn);
        fclose($fp);

        if (!$this->_verifyResponse(226)) {
            throw new FilesystemException(
                sprintf('%1$s: Передача не удалась.  Ответ сервера: %2$s [ожидается: 226].  Путь отправлен: %3$s', __METHOD__, $this->response, $remote)
            );
        }

        return true;
    }

    /**
     * Способ сохранения файла на FTP-сервере
     *
     * @param   string       $local   Путь к локальному файлу для хранения на FTP-сервере.
     * @param   string|null  $remote  FTP-путь к файлу для создания
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function store(string $local, ?string $remote = null): bool {
        if ($remote == null) {
            $remote = basename($local);
        }

        $mode = $this->_findMode($remote);

        if (FTP_NATIVE) {
            if (@ftp_pasv($this->conn, true) === false) {
                throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
            }

            if (@ftp_put($this->conn, $remote, $local, $mode) === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            return true;
        }

        $this->_mode($mode);

        if (@ file_exists($local)) {
            $fp = fopen($local, 'rb');

            if (!$fp) {
                throw new FilesystemException(sprintf('%1$s: Невозможно открыть локальный файл для чтения. Локальный путь: %2$s', __METHOD__, $local));
            }
        } else {
            throw new FilesystemException(sprintf('%1$s: Невозможно найти локальный файл. Локальный путь: %2$s', __METHOD__, $local));
        }

        if (!$this->_passive()) {
            @ fclose($fp);

            throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
        }

        if (!$this->_putCmd('STOR ' . $remote, [150, 125])) {
            @ fclose($fp);
            @ fclose($this->dataconn);

            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 150 или 125].  Путь отправлен: %3$s', __METHOD__, $this->response, $remote)
            );
        }

        while (!feof($fp)) {
            $line = fread($fp, 4096);

            do {
                if (($result = @ fwrite($this->dataconn, $line)) === false) {
                    throw new FilesystemException(__METHOD__ . ': Невозможно записать в сокет порта данных');
                }

                $line = substr($line, $result);
            } while ($line != '');
        }

        fclose($fp);
        fclose($this->dataconn);

        if (!$this->_verifyResponse(226)) {
            throw new FilesystemException(
                sprintf('%1$s: Передача не удалась.  Ответ сервера: %2$s [ожидается: 226].  Путь отправлен: %3$s', __METHOD__, $this->response, $remote)
            );
        }

        return true;
    }

    /**
     * Метод записи строки на FTP-сервер
     *
     * @param   string  $remote  FTP-путь к файлу для записи
     * @param   string  $buffer  Содержимое для записи на FTP-сервер
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    public function write(string $remote, string $buffer): bool {
        $mode = $this->_findMode($remote);

        if (FTP_NATIVE) {
            if (@ftp_pasv($this->conn, true) === false) {
                throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
            }

            $tmp = fopen('buffer://tmp', 'br+');
            fwrite($tmp, $buffer);
            rewind($tmp);

            if (@ftp_fput($this->conn, $remote, $tmp, $mode) === false) {
                fclose($tmp);

                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            fclose($tmp);

            return true;
        }

        $this->_mode($mode);

        if (!$this->_passive()) {
            throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
        }

        if (!$this->_putCmd('STOR ' . $remote, [150, 125])) {
            @ fclose($this->dataconn);

            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 150 или 125].  Путь отправлен: %3$s', __METHOD__, $this->response, $remote)
            );
        }

        do {
            if (($result = @ fwrite($this->dataconn, $buffer)) === false) {
                throw new FilesystemException(__METHOD__ . ': Невозможно выполнить запись в сокет порта данных.');
            }

            $buffer = substr($buffer, $result);
        } while ($buffer != '');

        fclose($this->dataconn);

        if (!$this->_verifyResponse(226)) {
            throw new FilesystemException(
                sprintf('%1$s: Передача не удалась.  Ответ сервера: %2$s [ожидается: 226].  Путь отправлен: %3$s', __METHOD__, $this->response, $remote)
            );
        }

        return true;
    }

    /**
     * Метод для вывода списка имен файлов содержимого каталога на FTP-сервере.
     *
     * Примечание. Некоторые серверы также возвращают имена папок. Однако, чтобы быть уверенным в том, что папки будут перечислены на всех серверах, вместо этого вам следует использовать listDetails(), если вам также нужно иметь дело с папками.
     *
     * @param   string|null  $path  Путь к локальному файлу для хранения на FTP-сервере
     *
     * @return  string  Список каталогов
     *
     * @throws  FilesystemException
     */
    public function listNames(?string $path = null): string {
        $data = null;

        if (FTP_NATIVE) {
            if (@ftp_pasv($this->conn, true) === false) {
                throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
            }

            if (($list = @ftp_nlist($this->conn, $path)) === false) {
                if ($this->listDetails($path, 'files') === []) {
                    return [];
                }

                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }

            $list = preg_replace('#^' . preg_quote($path, '#') . '[/\\\\]?#', '', $list);

            if ($keys = array_merge(array_keys($list, '.'), array_keys($list, '..'))) {
                foreach ($keys as $key) {
                    unset($list[$key]);
                }
            }

            return $list;
        }

        if ($path != null) {
            $path = ' ' . $path;
        }

        if (!$this->_passive()) {
            throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
        }

        if (!$this->_putCmd('NLST' . $path, [150, 125])) {
            @ fclose($this->dataconn);

            if ($this->listDetails($path, 'files') === []) {
                return [];
            }

            throw new FilesystemException(
                sprintf('%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 150 или 125].  Путь отправлен: %3$s', __METHOD__, $this->response, $path)
            );
        }

        while (!feof($this->dataconn)) {
            $data .= fread($this->dataconn, 4096);
        }

        fclose($this->dataconn);

        if (!$this->_verifyResponse(226)) {
            throw new FilesystemException(
                sprintf('%1$s: Передача не удалась.  Ответ сервера: %2$s [ожидается: 226].  Путь отправлен: %3$s', __METHOD__, $this->response, $path)
            );
        }

        $data = preg_split('/[' . CRLF . ']+/', $data, -1, \PREG_SPLIT_NO_EMPTY);
        $data = preg_replace('#^' . preg_quote(substr($path, 1), '#') . '[/\\\\]?#', '', $data);

        if ($keys = array_merge(array_keys($data, '.'), array_keys($data, '..'))) {
            foreach ($keys as $key) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    /**
     * Метод для просмотра содержимого каталога на FTP-сервере
     *
     * @param   string|null  $path  Путь к локальному файлу, который будет храниться на FTP-сервере.
     * @param   string       $type  Тип возврата [raw|all|folders|files]
     *
     * @return  array  Если $type raw: строка Список каталогов, иначе массив строк с именами файлов.
     *
     * @throws  FilesystemException
     */
    public function listDetails(?string $path = null, string $type = 'all'): array {
        $dirList = [];
        $data    = null;
        $regs    = null;

        $recurse = false;

        if (FTP_NATIVE) {
            if (@ftp_pasv($this->conn, true) === false) {
                throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
            }

            if (($contents = @ftp_rawlist($this->conn, $path)) === false) {
                throw new FilesystemException(__METHOD__ . 'Плохой ответ.');
            }
        } else {
            if (!$this->_passive()) {
                throw new FilesystemException(__METHOD__ . ': Невозможно использовать пассивный режим.');
            }

            if ($path != null) {
                $path = ' ' . $path;
            }

            if (!$this->_putCmd(($recurse == true) ? 'LIST -R' : 'LIST' . $path, [150, 125])) {
                @ fclose($this->dataconn);

                throw new FilesystemException(
                    sprintf(
                        '%1$s: Плохой ответ.  Ответ сервера: %2$s [ожидается: 150 или 125].  Путь отправлен: %3$s',
                        __METHOD__,
                        $this->response,
                        $path
                    )
                );
            }

            while (!feof($this->dataconn)) {
                $data .= fread($this->dataconn, 4096);
            }

            fclose($this->dataconn);

            if (!$this->_verifyResponse(226)) {
                throw new FilesystemException(
                    sprintf('%1$s: Передача не удалась.  Ответ сервера: %2$s [ожидается: 226].  Путь отправлен: %3$s', __METHOD__, $this->response, $path)
                );
            }

            $contents = explode(CRLF, $data);
        }

        if ($type == 'raw') {
            return $data;
        }

        if (empty($contents[0])) {
            return $dirList;
        }

        if (strtolower(substr($contents[0], 0, 6)) == 'total ') {
            array_shift($contents);

            if (empty($contents[0])) {
                return $dirList;
            }
        }

        $regexps = [
            'UNIX' => '#([-dl][rwxstST-]+).* ([0-9]*) ([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*)'
                . ' ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{1,2}:[0-9]{2})|[0-9]{4}) (.+)#',
            'MAC' => '#([-dl][rwxstST-]+).* ?([0-9 ]*)?([a-zA-Z0-9]+).* ([a-zA-Z0-9]+).* ([0-9]*)'
                . ' ([a-zA-Z]+[0-9: ]*[0-9])[ ]+(([0-9]{2}:[0-9]{2})|[0-9]{4}) (.+)#',
            'WIN' => '#([0-9]{2})-([0-9]{2})-([0-9]{2}) +([0-9]{2}):([0-9]{2})(AM|PM) +([0-9]+|<DIR>) +(.+)#',
        ];

        $osType = null;

        foreach ($regexps as $k => $v) {
            if (@preg_match($v, $contents[0])) {
                $osType = $k;
                $regexp = $v;

                break;
            }
        }

        if (!$osType) {
            throw new FilesystemException(__METHOD__ . ':Неизвестный формат списка каталогов.');
        }

        if ($osType == 'UNIX' || $osType == 'MAC') {
            foreach ($contents as $file) {
                $tmpArray = null;

                if (@preg_match($regexp, $file, $regs)) {
                    $fType = (int) strpos('-dl', $regs[1][0]);

                    $tmpArray['type']   = $fType;
                    $tmpArray['rights'] = $regs[1];

                    // $tmpArray['number'] = $regs[2];
                    $tmpArray['user']  = $regs[3];
                    $tmpArray['group'] = $regs[4];
                    $tmpArray['size']  = $regs[5];
                    $tmpArray['date']  = @date('m-d', strtotime($regs[6]));
                    $tmpArray['time']  = $regs[7];
                    $tmpArray['name']  = $regs[9];
                }

                if ($type == 'files' && $tmpArray['type'] == 1) {
                    continue;
                }

                if ($type == 'folders' && $tmpArray['type'] == 0) {
                    continue;
                }

                if (\is_array($tmpArray) && $tmpArray['name'] != '.' && $tmpArray['name'] != '..') {
                    $dirList[] = $tmpArray;
                }
            }
        } else {
            foreach ($contents as $file) {
                $tmpArray = null;

                if (@preg_match($regexp, $file, $regs)) {
                    $fType     = (int) ($regs[7] == '<DIR>');
                    $timestamp = strtotime("$regs[3]-$regs[1]-$regs[2] $regs[4]:$regs[5]$regs[6]");

                    $tmpArray['type']   = $fType;
                    $tmpArray['rights'] = '';

                    // $tmpArray['number'] = 0;
                    $tmpArray['user']  = '';
                    $tmpArray['group'] = '';
                    $tmpArray['size']  = (int) $regs[7];
                    $tmpArray['date']  = date('m-d', $timestamp);
                    $tmpArray['time']  = date('H:i', $timestamp);
                    $tmpArray['name']  = $regs[8];
                }

                if ($type == 'files' && $tmpArray['type'] == 1) {
                    continue;
                }

                if ($type == 'folders' && $tmpArray['type'] == 0) {
                    continue;
                }

                if (\is_array($tmpArray) && $tmpArray['name'] != '.' && $tmpArray['name'] != '..') {
                    $dirList[] = $tmpArray;
                }
            }
        }

        return $dirList;
    }

    /**
     * Отправляет команду на FTP-сервер и проверьте ожидаемый код ответа.
     *
     * @param   string  $cmd               Команда для отправки на FTP-сервер
     * @param   mixed   $expectedResponse  Целочисленный код ответа или массив целочисленных кодов ответа
     *
     * @return  boolean  True если команда выполнена успешно
     *
     * @throws  FilesystemException
     */
    protected function _putCmd(string $cmd, mixed $expectedResponse): bool {
        if (!\is_resource($this->conn)) {
            throw new FilesystemException(__METHOD__ . ': Не подключен к порту управления.');
        }

        if (!fwrite($this->conn, $cmd . "\r\n")) {
            throw new FilesystemException(sprintf('%1$s: Невозможно отправить команду: %2$s', __METHOD__, $cmd));
        }

        return $this->_verifyResponse($expectedResponse);
    }

    /**
     * Проверяет код ответа от сервера и зарегистрируйте ответ, если установлен флаг.
     *
     * @param   mixed  $expected  Целочисленный код ответа или массив целочисленных кодов ответа
     *
     * @return  boolean  True если ожидается код ответа от сервера
     *
     * @throws  FilesystemException
     */
    protected function _verifyResponse(mixed $expected): bool {
        $parts          = null;
        $endTime        = time() + $this->timeout;
        $this->response = '';

        do {
            $this->response .= fgets($this->conn, 4096);
        } while (!preg_match('/^([0-9]{3})(-(.*' . CRLF . ')+\\1)? [^' . CRLF . ']+' . CRLF . '$/', $this->response, $parts) && time() < $endTime);

        if (!isset($parts[1])) {
            throw new FilesystemException(
                sprintf(
                    '%1$s: Тайм-аут или нераспознанный ответ при ожидании ответа от сервера. Ответ сервера: %2$s',
                    __METHOD__,
                    $this->response
                )
            );
        }

        $this->responseCode = $parts[1];
        $this->responseMsg  = $parts[0];

        if (\is_array($expected)) {
            if (\in_array($this->responseCode, $expected)) {
                $retval = true;
            } else {
                $retval = false;
            }
        } else {
            if ($this->responseCode == $expected) {
                $retval = true;
            } else {
                $retval = false;
            }
        }

        return $retval;
    }

    /**
     * Устанавливает сервер в пассивный режим и откройте соединение с портом данных.
     *
     * @return  boolean  
     *
     * @throws  FilesystemException
     */
    protected function _passive(): bool {
        $match = [];
        $parts = [];
        $errno = null;
        $err   = null;

        if (!\is_resource($this->conn)) {
            throw new FilesystemException(__METHOD__ . ': Не подключен к порту управления.');
        }

        @ fwrite($this->conn, "PASV\r\n");

        $endTime        = time() + $this->timeout;
        $this->response = '';

        do {
            $this->response .= fgets($this->conn, 4096);
        } while (!preg_match('/^([0-9]{3})(-(.*' . CRLF . ')+\\1)? [^' . CRLF . ']+' . CRLF . '$/', $this->response, $parts) && time() < $endTime);

        if (!isset($parts[1])) {
            throw new FilesystemException(
                sprintf(
                    '%1$s: Тайм-аут или нераспознанный ответ при ожидании ответа от сервера. Ответ сервера: %2$s',
                    __METHOD__,
                    $this->response
                )
            );
        }

        $this->responseCode = $parts[1];
        $this->responseMsg  = $parts[0];

        if ($this->responseCode != 227) {
            throw new FilesystemException(
                sprintf('%1$s: Не удалось получить IP и порт для передачи данных. Ответ сервера: %2$s', __METHOD__, $this->responseMsg)
            );
        }

        if (preg_match('~\((\d+),\s*(\d+),\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*(\d+))\)~', $this->responseMsg, $match) == 0) {
            throw new FilesystemException(
                sprintf('%1$s: IP и порт для передачи данных недействительны. Ответ сервера: %2$s', __METHOD__, $this->responseMsg)
            );
        }

        $this->pasv     = ['ip' => $match[1] . '.' . $match[2] . '.' . $match[3] . '.' . $match[4], 'port' => $match[5] * 256 + $match[6]];
        $this->dataconn = @fsockopen($this->pasv['ip'], $this->pasv['port'], $errno, $err, $this->timeout);

        if (!$this->dataconn) {
            throw new FilesystemException(
                sprintf(
                    '%1$s: Не удалось подключиться к хосту %2$s через порт %3$s. Номер ошибки сокета: %4$s и сообщение об ошибке: %5$s',
                    __METHOD__,
                    $this->pasv['ip'],
                    $this->pasv['port'],
                    $errno,
                    $err
                )
            );
        }

        socket_set_timeout($this->conn, $this->timeout, 0);

        return true;
    }

    /**
     * Способ узнать правильный режим передачи для конкретного файла
     *
     * @param   string  $fileName  Имя файла
     *
     * @return  integer Режим передачи для этого типа файла [FTP_ASCII|FTP_BINARY]
     *
     */
    protected function _findMode(string $fileName): int {
        if ($this->type == FTP_AUTOASCII) {
            $dot = strrpos($fileName, '.') + 1;
            $ext = substr($fileName, $dot);

            if (\in_array($ext, $this->autoAscii)) {
                $mode = \FTP_ASCII;
            } else {
                $mode = \FTP_BINARY;
            }
        } elseif ($this->type == \FTP_ASCII) {
            $mode = \FTP_ASCII;
        } else {
            $mode = \FTP_BINARY;
        }

        return $mode;
    }

    /**
     * Устанавливает режим передачи.
     *
     * @param   integer  $mode  Целочисленное представление режима передачи данных [1:Binary|0:Ascii]
     *                          Также можно использовать определенные константы [FTP_BINARY|FTP_ASCII]
     *
     * @return  boolean 
     *
     * @throws  FilesystemException
     */
    protected function _mode(int $mode): bool {
        if ($mode == \FTP_BINARY) {
            if (!$this->_putCmd('TYPE I', 200)) {
                throw new FilesystemException(
                    sprintf('%1$s: Плохой ответ. Ответ сервера: %2$s [ожидается: 200]. Режим отправки: Binary.', __METHOD__, $this->response)
                );
            }
        } else {
            if (!$this->_putCmd('TYPE A', 200)) {
                throw new FilesystemException(
                    sprintf('%1$s: Плохой ответ. Ответ сервера: %2$s [ожидается: 200]. Режим отправки: ASCII', __METHOD__, $this->response)
                );
            }
        }

        return true;
    }
}
