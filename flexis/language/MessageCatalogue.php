<?php

/**
 * Часть пакета Flexis Language Framework.
 */

namespace Flexis\Language;

/**
 * Каталог загруженных строк перевода для языка.
 */
class MessageCatalogue {
    /**
     * Вспомогательный вариант для этого каталога.
     *
     * @var    MessageCatalogue|null
     */
    private ?MessageCatalogue $fallbackCatalogue = null;

    /**
     * Язык сообщений в этом каталоге.
     *
     * @var    string
     */
    private string $language;

    /**
     * Сообщения, хранящиеся в этом каталоге.
     *
     * @var    array
     */
    private array $messages = [];

    /**
     * Конструктор каталога сообщений.
     *
     * @param   string  $language  Язык сообщений в этом каталоге.
     * @param   array   $messages  Сообщения для заполнения этого каталога.
     */
    public function __construct(string $language, array $messages = []) {
        $this->language = $language;

        $this->addMessages($messages);
    }

    /**
     * Добавляет сообщение в каталог, заменив ключ, если он уже существует.
     *
     * @param   string  $key      Ключ, идентифицирующий сообщение.
     * @param   string  $message  Сообщение для этого ключа.
     *
     * @return  void
     */
    public function addMessage(string $key, string $message): void {
        $this->addMessages([$key => $message]);
    }

    /**
     * Добавляет сообщения в каталог, заменяя уже существующие ключи.
     *
     * @param   array  $messages  Ассоциативный массив, содержащий сообщения, добавляемые в каталог.
     *
     * @return  void
     */
    public function addMessages(array $messages): void {
        $this->messages = array_replace($this->messages, array_change_key_case($messages, CASE_UPPER));
    }

    /**
     * Проверяет, есть ли в этом каталоге сообщение для данного ключа, игнорируя резервный вариант, если он определен.
     *
     * @param   string  $key  Ключ для проверки.
     *
     * @return  boolean
     */
    public function definesMessage(string $key): bool {
        return isset($this->messages[strtoupper($key)]);
    }

    /**
     * Возвращает резервный вариант для этого каталога, если он установлен.
     *
     * @return  MessageCatalogue|null
     */
    public function getFallbackCatalogue(): ?MessageCatalogue {
        return $this->fallbackCatalogue;
    }

    /**
     * Возвращает язык для этого каталога
     *
     * @return  string
     */
    public function getLanguage(): string {
        return $this->language;
    }

    /**
     * Возвращает сообщение для данного ключа.
     *
     * @param   string  $key  Ключ для получения сообщения.
     *
     * @return  string  Сообщение, если оно установлено, иначе ключ.
     */
    public function getMessage(string $key): string {
        if ($this->definesMessage($key)) {
            return $this->messages[strtoupper($key)];
        }

        if ($this->fallbackCatalogue) {
            return $this->fallbackCatalogue->getMessage($key);
        }

        return strtoupper($key);
    }

    /**
     * Возвращает сообщения, хранящиеся в этом каталоге.
     *
     * @return  array
     */
    public function getMessages(): array {
        return $this->messages;
    }

    /**
     * Проверить, есть ли в каталоге сообщение для данного ключа.
     *
     * @param   string  $key  Ключ для проверки.
     *
     * @return  boolean
     */
    public function hasMessage(string $key): bool {
        if ($this->definesMessage($key)) {
            return true;
        }

        if ($this->fallbackCatalogue) {
            return $this->fallbackCatalogue->hasMessage($key);
        }

        return false;
    }

    /**
     * Объединить другой каталог с этим.
     *
     * @param   MessageCatalogue  $messageCatalogue  Каталог для объединения.
     *
     * @return  void
     * @throws  \LogicException
     */
    public function mergeCatalogue(MessageCatalogue $messageCatalogue): void {
        if ($messageCatalogue->getLanguage() !== $this->getLanguage()) {
            throw new \LogicException('Невозможно объединить каталог, у которого нет одинакового кода языка.');
        }

        $this->addMessages($messageCatalogue->getMessages());
    }

    /**
     * Устанавливает резервный вариант для этого каталога.
     *
     * @param   MessageCatalogue  $messageCatalogue  Каталог, который будет использоваться в качестве запасного варианта.
     *
     * @return  void
     */
    public function setFallbackCatalogue(MessageCatalogue $messageCatalogue): void {
        $this->fallbackCatalogue = $messageCatalogue;
    }
}
