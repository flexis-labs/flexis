<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt;

use Defuse\Crypto\Exception\BadFormatException;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Flexis\Crypt\Cipher\Crypto;
use Flexis\Crypt\Exception\DecryptionException;
use Flexis\Crypt\Exception\EncryptionException;
use Flexis\Crypt\Exception\InvalidKeyException;
use Flexis\Crypt\Exception\InvalidKeyTypeException;
use Flexis\Crypt\Exception\UnsupportedCipherException;
use Random\RandomException;

/**
 * Crypt — это класс Flexis Framework для базового шифрования/дешифрования данных.
 */
class Crypt {
    /**
     * Объект шифрования.
     *
     * @var    CipherInterface
     */
    private CipherInterface $cipher;

    /**
     * Ключ шифрования[/пара)].
     *
     * @var    Key
     */
    private Key $key;

    /**
     * Конструктор объектов принимает дополнительный ключ, который будет использоваться для шифрования/дешифрования.
     * Если ключ не указан, используется секретное слово из объекта конфигурации.
     *
     * @param   CipherInterface|null  $cipher   Объект шифрования.
     * @param   Key|null              $key      Ключ шифрования[/пара)].
     *
     * @throws EnvironmentIsBrokenException
     */
    public function __construct(?CipherInterface $cipher = null, ?Key $key = null) {
        $this->cipher = $cipher ?: new Crypto();
        $this->key    = $key ?: $this->generateKey();
    }

    /**
     * Метод расшифровки строки данных.
     *
     * @param   string  $data  Зашифрованная строка для расшифровки.
     *
     * @return  string  Расшифрованная строка данных.
     *
     * @throws  DecryptionException если данные не могут быть расшифрованы
     * @throws  InvalidKeyTypeException если ключ недействителен для шифра
     * @throws  UnsupportedCipherException если шифр не поддерживается в текущей среде
     */
    public function decrypt(string $data): string {
        return $this->cipher->decrypt($data, $this->key);
    }

    /**
     * Метод шифрования строки данных.
     *
     * @param   string  $data  Строка данных для шифрования.
     *
     * @return  string  Зашифрованная строка данных.
     *
     * @throws  EncryptionException если данные не могут быть зашифрованы
     * @throws  InvalidKeyTypeException если ключ недействителен для шифра
     * @throws  UnsupportedCipherException если шифр не поддерживается в текущей среде
     * @throws  BadFormatException
     */
    public function encrypt(string $data): string {
        return $this->cipher->encrypt($data, $this->key);
    }

    /**
     * Метод для создания нового объекта ключа[/пара].
     *
     * @param   array  $options Варианты генерации ключей.
     *
     * @return  Key
     *
     * @throws  InvalidKeyException если ключ не может быть сгенерирован
     * @throws  UnsupportedCipherException если шифр не поддерживается в текущей среде
     * @throws  EnvironmentIsBrokenException
     */
    public function generateKey(array $options = []): Key {
        return $this->cipher->generateKey($options);
    }

    /**
     * Метод для установки объекта ключа[/пара].
     *
     * @param   Key  $key  Ключевой объект для установки.
     *
     * @return  $this  Экземпляр $this для разрешения цепочки.
     *
     */
    public function setKey(Key $key): self {
        $this->key = $key;

        return $this;
    }

    /**
     * Возвращает случайные байты.
     *
     * @param integer $length Длина случайных данных для генерации.
     *
     * @return  string  Случайные двоичные данные.
     *
     * @throws RandomException
     */
    public static function genRandomBytes(int $length = 16): string {
        return random_bytes($length);
    }
}
