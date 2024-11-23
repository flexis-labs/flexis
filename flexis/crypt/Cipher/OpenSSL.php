<?php

/**
 * Часть пакета Flexis Crypt Framework.
 */

namespace Flexis\Crypt\Cipher;

use Flexis\Crypt\CipherInterface;
use Flexis\Crypt\Exception\DecryptionException;
use Flexis\Crypt\Exception\EncryptionException;
use Flexis\Crypt\Exception\InvalidKeyException;
use Flexis\Crypt\Exception\InvalidKeyTypeException;
use Flexis\Crypt\Key;

/**
 * Шифр Flexis для шифрования, дешифрования и генерации ключей через расширение openssl.
 */
class OpenSSL implements CipherInterface {
    /**
     * Вектор инициализации для метода генератора ключей.
     *
     * @var    string
     */
    private string $iv;

    /**
     * Метод, используемый для шифрования.
     *
     * @var    string
     */
    private string $method;

    /**
     * Создайте экземпляр шифра.
     *
     * @param   string  $iv      Используемый вектор инициализации
     * @param   string  $method  The encryption method to use
     */
    public function __construct(string $iv, string $method) {
        $this->iv     = $iv;
        $this->method = $method;
    }

    /**
     * Метод расшифровки строки данных.
     *
     * @param   string  $data  Зашифрованная строка для расшифровки.
     * @param   Key     $key   Ключевой объект, используемый для расшифровки.
     *
     * @return  string  Расшифрованная строка данных.
     *
     * @throws  DecryptionException если данные не могут быть расшифрованы
     * @throws  InvalidKeyTypeException если ключ недействителен для шифра
     */
    public function decrypt(string $data, Key $key): string {
        if ($key->getType() !== 'openssl') {
            throw new InvalidKeyTypeException('openssl', $key->getType());
        }

        $cleartext = openssl_decrypt($data, $this->method, $key->getPrivate(), true, $this->iv);

        if ($cleartext === false) {
            throw new DecryptionException('Не удалось расшифровать данные');
        }

        return $cleartext;
    }

    /**
     * Метод шифрования строки данных.
     *
     * @param   string  $data  Строка данных для шифрования.
     * @param   Key     $key   Ключевой объект, используемый для шифрования.
     *
     * @return  string  Зашифрованная строка данных.
     * 
     * @throws  EncryptionException если данные не могут быть зашифрованы
     * @throws  InvalidKeyTypeException если ключ недействителен для шифра
     */
    public function encrypt(string $data, Key $key): string {
        if ($key->getType() !== 'openssl') {
            throw new InvalidKeyTypeException('openssl', $key->getType());
        }

        $encrypted = openssl_encrypt($data, $this->method, $key->getPrivate(), true, $this->iv);

        if ($encrypted === false) {
            throw new EncryptionException('Невозможно зашифровать данные');
        }

        return $encrypted;
    }

    /**
     * Метод для создания нового объекта ключа шифрования.
     *
     * @param   array  $options  Варианты генерации ключей.
     *
     * @return  Key
     *
     * @throws  InvalidKeyException если ключ не может быть сгенерирован
     */
    public function generateKey(array $options = []): Key {
        $passphrase = $options['passphrase'] ?? false;

        if ($passphrase === false) {
            throw new InvalidKeyException('Отсутствует файл с парольной фразой');
        }

        return new Key('openssl', $passphrase, 'unused');
    }

    /**
     * Проверяет, поддерживается ли шифр в этой среде.
     *
     * @return  boolean
     */
    public static function isSupported(): bool {
        return \extension_loaded('openssl');
    }
}
