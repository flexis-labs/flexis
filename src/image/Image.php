<?php

/**
 * Часть пакета Flexis Image Framework.
 */

namespace Flexis\Image;

/**
 * Класс для управления изображением.
 */
class Image {
    /**
     * @const  integer
     */
    public const int SCALE_FILL = 1;

    /**
     * @const  integer
     */
    public const int SCALE_INSIDE = 2;

    /**
     * @const  integer
     */
    public const int SCALE_OUTSIDE = 3;

    /**
     * @const  integer
     */
    public const int CROP = 4;

    /**
     * @const  integer
     */
    public const int CROP_RESIZE = 5;

    /**
     * @const  integer
     */
    public const int SCALE_FIT = 6;

    /**
     * @const  string
     */
    public const string ORIENTATION_LANDSCAPE = 'landscape';

    /**
     * @const  string
     */
    public const string ORIENTATION_PORTRAIT = 'portrait';

    /**
     * @const  string
     */
    public const string ORIENTATION_SQUARE = 'square';

    /**
     * Дескриптор ресурса изображения.
     *
     * @var    resource
     */
    protected $handle;

    /**
     * Путь к исходному изображению.
     *
     * @var    string|null
     */
    protected ?string $path = null;

    /**
     * Поддерживаются ли разные форматы изображений.
     *
     * @var    array
     */
    protected static array $formats = [];

    /**
     * Флаг, если изображение должно использовать наилучшее доступное качество.
     * Отключите для повышения производительности.
     *
     * @var    boolean
     */
    protected bool $generateBestQuality = true;

    /**
     * Конструктор класса.
     *
     * @param   mixed  $source  Либо путь к файлу исходного изображения, либо обработчик ресурсов GD для изображения.
     *
     * @throws  \RuntimeException
     */
    public function __construct(mixed $source = null) {
        if (!\extension_loaded('gd')) {
            throw new \RuntimeException('Расширение GD для PHP недоступно.');

            // @codeCoverageIgnoreEnd
        }

        if (empty(static::$formats)) {
            $info                            = gd_info();
            static::$formats[IMAGETYPE_JPEG] = $info['JPEG Support'];
            static::$formats[IMAGETYPE_PNG]  = $info['PNG Support'];
            static::$formats[IMAGETYPE_GIF]  = $info['GIF Read Support'];
            static::$formats[IMAGETYPE_WEBP] = $info['WebP Support'];
            static::$formats[IMAGETYPE_AVIF] = $info['AVIF Support'];
        }

        if ((\is_object($source) && \get_class($source) == 'GdImage')) {
            $this->handle = $source;
        } elseif (!empty($source) && \is_string($source)) {
            $this->loadFile($source);
        }
    }

    /**
     * Возвращает дескриптор ресурса изображения.
     *
     * @return  \GdImage
     *
     * @throws  \LogicException если изображение не было загружено в экземпляр.
     */
    public function getHandle(): \GdImage {
        if (!$this->isLoaded()) {
            throw new \LogicException('Не было загружено допустимое изображение.');
        }

        return $this->handle;
    }

    /**
     * Метод для возврата объекта свойств изображения по указанному пути к файловой системе.
     *
     * Объект результата имеет значения ширины, высоты, типа, атрибутов, MIME-типа, битов и каналов изображения.
     *
     * @param   string  $path  Путь в файловой системе к изображению, для которого нужно получить свойства.
     *
     * @return  \stdClass
     *
     * @throws  \InvalidArgumentException
     * @throws  \RuntimeException
     */
    public static function getImageFileProperties(string $path): \stdClass {
        if (!is_file($path)) {
            throw new \InvalidArgumentException('Файл изображения не существует.');
        }

        $info = getimagesize($path);

        if (!$info) {
            throw new Exception\UnparsableImageException('Не удалось получить свойства изображения.');
        }

        return (object) [
            'width'       => $info[0],
            'height'      => $info[1],
            'type'        => $info[2],
            'attributes'  => $info[3],
            'bits'        => $info['bits'] ?? null,
            'channels'    => $info['channels'] ?? null,
            'mime'        => $info['mime'],
            'filesize'    => filesize($path),
            'orientation' => self::getOrientationString((int) $info[0], (int) $info[1]),
        ];
    }

    /**
     * Метод определения того, является ли ориентация изображения альбомной, книжной или квадратной.
     *
     * Ориентация будет возвращена в виде строки.
     *
     * @return  string|null   Строка ориентации или null.
     */
    public function getOrientation(): ?string {
        if ($this->isLoaded()) {
            return self::getOrientationString($this->getWidth(), $this->getHeight());
        }

        return null;
    }

    /**
     * Сравнивает целые числа ширины и высоты, чтобы определить ориентацию изображения.
     *
     * @param   integer  $width   Значение ширины, используемое для расчета.
     * @param   integer  $height  Значение высоты, используемое для расчета.
     *
     * @return  string   Строка ориентации
     */
    private static function getOrientationString(int $width, int $height): string {
        return match (true) {
            $width > $height => self::ORIENTATION_LANDSCAPE,
            $width < $height => self::ORIENTATION_PORTRAIT,
            default => self::ORIENTATION_SQUARE,
        };
    }

    /**
     * Метод для создания миниатюр из текущего изображения.
     * Это позволяет создавать путём изменения размера или обрезки исходного изображения.
     *
     * @param   mixed    $thumbSizes      Строка или массив строк. Пример: `$thumbSizes = array('150x75','250x150');`
     * @param   integer  $creationMethod  1-3 изменить размер `$scaleMethod` | 4 создать обрезку | 5 изменить размер, затем обрезать
     *
     * @return  array
     *
     * @throws  \LogicException
     * @throws  \InvalidArgumentException
     */
    public function generateThumbs(mixed $thumbSizes, int $creationMethod = self::SCALE_INSIDE): array {
        if (!$this->isLoaded()) {
            throw new \LogicException('Не было загружено допустимое изображение.');
        }

        if (!\is_array($thumbSizes)) {
            $thumbSizes = [$thumbSizes];
        }

        $generated = [];

        if (!empty($thumbSizes)) {
            foreach ($thumbSizes as $thumbSize) {
                $size = explode('x', strtolower($thumbSize));

                if (\count($size) != 2) {
                    throw new \InvalidArgumentException('Получен неверный размер миниатюры: ' . $thumbSize);
                }

                $thumbWidth  = $size[0];
                $thumbHeight = $size[1];

                $thumb = match ($creationMethod) {
                    self::CROP => $this->crop($thumbWidth, $thumbHeight),
                    self::CROP_RESIZE => $this->cropResize($thumbWidth, $thumbHeight),
                    default => $this->resize($thumbWidth, $thumbHeight, true, $creationMethod),
                };

                $generated[] = $thumb;
            }
        }

        return $generated;
    }

    /**
     * Метод создания миниатюр из текущего изображения и сохранения их на диск. Это позволяет создавать путем изменения размера или обрезки исходного изображения.
     *
     * @param   mixed         $thumbSizes       Строка или массив строк. Пример: `$thumbSizes = ['150x75','250x150'];`
     * @param   integer       $creationMethod   1-3 изменить размер `$scaleMethod` | 4 создать обрезку
     * @param   string|null   $thumbsFolder     Папка назначения превью. Null создает папку «thumb» в папке изображений
     * @param   boolean       $useOriginalName  Должны ли мы использовать исходное имя изображения? По умолчанию это false, `{filename}_{width}x{height}.{ext}`
     *
     * @return  array
     *
     * @throws  \LogicException
     * @throws  \InvalidArgumentException
     */
    public function createThumbnails(
        mixed $thumbSizes,
        int $creationMethod = self::SCALE_INSIDE,
        ?string $thumbsFolder = null,
        bool $useOriginalName = false
    ): array {
        if (!$this->isLoaded()) {
            throw new \LogicException('Не было загружено допустимое изображение.');
        }

        if (\is_null($thumbsFolder)) {
            $thumbsFolder = \dirname($this->getPath()) . '/thumbs';
        }

        if (!is_dir($thumbsFolder) && (!is_dir(\dirname($thumbsFolder)) || !@mkdir($thumbsFolder))) {
            throw new \InvalidArgumentException('Папка не существует и не может быть создана: ' . $thumbsFolder);
        }

        $thumbsCreated = [];

        if ($thumbs = $this->generateThumbs($thumbSizes, $creationMethod)) {
            $imgProperties = static::getImageFileProperties($this->getPath());

            $pathInfo      = pathinfo($this->getPath());
            $filename      = $pathInfo['filename'];
            $fileExtension = $pathInfo['extension'] ?? '';

            foreach ($thumbs as $thumb) {
                $thumbWidth  = $thumb->getWidth();
                $thumbHeight = $thumb->getHeight();

                if ($useOriginalName) {
                    $thumbFileName = $filename . '.' . $fileExtension;
                } else {
                    $thumbFileName = $filename . '_' . $thumbWidth . 'x' . $thumbHeight . '.' . $fileExtension;
                }

                $thumbFileName = $thumbsFolder . '/' . $thumbFileName;

                if ($thumb->toFile($thumbFileName, $imgProperties->type)) {
                    $thumb->path     = $thumbFileName;
                    $thumbsCreated[] = $thumb;
                }
            }
        }

        return $thumbsCreated;
    }

    /**
     * Метод обрезки текущего изображения.
     *
     * @param   mixed         $width      Ширина обрезаемой части изображения в пикселях или процентах.
     * @param   mixed         $height     Высота обрезаемой части изображения в пикселях или процентах.
     * @param   integer|null  $left       Количество пикселей слева, чтобы начать обрезку.
     * @param   integer|null  $top        Количество пикселей сверху, чтобы начать обрезку.
     * @param   boolean       $createNew  Если true, текущее изображение будет клонировано, обрезано и возвращено;
     *                                    иначе текущее изображение будет обрезано и возвращено.
     *
     * @return  Image
     *
     * @throws  \LogicException
     */
    public function crop(
        mixed $width,
        mixed $height,
        ?int $left = null,
        ?int $top = null,
        bool $createNew = true
    ): self {
        $width  = $this->sanitizeWidth($width, $height);
        $height = $this->sanitizeHeight($height, $width);

        if (\is_null($left)) {
            $left = round(($this->getWidth() - $width) / 2);
        }

        if (\is_null($top)) {
            $top = round(($this->getHeight() - $height) / 2);
        }

        $left   = $this->sanitizeOffset($left);
        $top    = $this->sanitizeOffset($top);
        $handle = imagecreatetruecolor($width, $height);

        imagealphablending($handle, false);
        imagesavealpha($handle, true);

        if ($this->isTransparent()) {
            $rgba  = imagecolorsforindex($this->getHandle(), imagecolortransparent($this->getHandle()));
            $color = imagecolorallocatealpha($handle, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']);

            imagecolortransparent($handle, $color);
            imagefill($handle, 0, 0, $color);
        }

        if (!$this->generateBestQuality) {
            imagecopyresized($handle, $this->getHandle(), 0, 0, $left, $top, $width, $height, $width, $height);
        } else {
            imagecopyresampled($handle, $this->getHandle(), 0, 0, $left, $top, $width, $height, $width, $height);
        }

        if ($createNew) {
            return new static($handle);
        }

        $this->destroy();

        $this->handle = $handle;

        return $this;
    }

    /**
     * Метод применения фильтра к изображению по типу.
     * Два примера: grayscale и sketchy.
     *
     * @param   string  $type     Имя применяемого фильтра изображения.
     * @param   array   $options  Массив опций для фильтра.
     *
     * @return  Image
     *
     * @see     \Flexis\Image\Filter ImageFilter
     * @throws  \LogicException
     */
    public function filter(string $type, array $options = []): self {
        if (!$this->isLoaded()) {
            throw new \LogicException('Не было загружено допустимое изображение.');
        }

        $filter = $this->getFilterInstance($type);

        $filter->execute($options);

        return $this;
    }

    /**
     * Метод получения высоты изображения в пикселях.
     *
     * @return  integer|boolean
     *
     * @throws  \LogicException
     */
    public function getHeight(): int|false {
        return imagesy($this->getHandle());
    }

    /**
     * Метод получения ширины изображения в пикселях.
     *
     * @return  integer|boolean
     *
     * @throws  \LogicException
     */
    public function getWidth(): int|false {
        return imagesx($this->getHandle());
    }

    /**
     * Метод возврата пути.
     *
     * @return  string
     *
     */
    public function getPath(): string {
        return $this->path;
    }

    /**
     * Метод, позволяющий определить, загружено ли изображение в объект.
     *
     * @return  boolean
     *
     */
    public function isLoaded(): bool {
        if (!(\is_object($this->handle) && \get_class($this->handle) == 'GdImage')) {
            return false;
        }

        return true;
    }

    /**
     * Метод определения того, имеет ли изображение прозрачность.
     *
     * @return  boolean
     *
     * @throws  \LogicException
     */
    public function isTransparent(): bool {
        return imagecolortransparent($this->getHandle()) >= 0;
    }

    /**
     * Метод для загрузки файла в объект Image в качестве ресурса.
     *
     * @param   string  $path  Путь к файловой системе для загрузки в виде изображения.
     *
     * @return  void
     *
     * @throws  \InvalidArgumentException
     * @throws  \RuntimeException
     */
    public function loadFile(string $path): void {
        $this->destroy();

        if (!is_file($path)) {
            throw new \InvalidArgumentException('Файл изображения не существует.');
        }

        $properties = static::getImageFileProperties($path);

        switch ($properties->mime) {
            case 'image/avif':
                if (empty(static::$formats[IMAGETYPE_AVIF])) {
                    throw new \RuntimeException('Попытка загрузить неподдерживаемый тип изображения AVIF.');
                }

                $handle = imagecreatefromavif($path);
                $type   = 'AVIF';

                break;

            case 'image/gif':
                if (empty(static::$formats[IMAGETYPE_GIF])) {
                    throw new \RuntimeException('Попытка загрузить неподдерживаемый тип изображения GIF.');
                }

                $handle = imagecreatefromgif($path);
                $type   = 'GIF';

                break;

            case 'image/jpeg':
                if (empty(static::$formats[IMAGETYPE_JPEG])) {
                    throw new \RuntimeException('Попытка загрузить неподдерживаемый тип изображения JPG.');
                }

                $handle = imagecreatefromjpeg($path);
                $type   = 'JPEG';

                break;

            case 'image/png':
                if (empty(static::$formats[IMAGETYPE_PNG])) {
                    throw new \RuntimeException('Попытка загрузить неподдерживаемый тип изображения PNG.');
                }

                $handle = imagecreatefrompng($path);
                $type   = 'PNG';

                break;

            case 'image/webp':
                if (empty(static::$formats[IMAGETYPE_WEBP])) {
                    throw new \RuntimeException('Попытка загрузить неподдерживаемый тип изображения WebP.');
                }

                $handle = imagecreatefromwebp($path);
                $type   = 'WebP';

                break;

            default:
                throw new \InvalidArgumentException('Попытка загрузить неподдерживаемый тип изображения ' . $properties->mime);
        }

        $this->handle = $handle;

        $this->path = $path;
    }

    /**
     * Метод изменения размера текущего изображения.
     *
     * @param   mixed    $width        Ширина изображения с измененным размером в пикселях или процентах.
     * @param   mixed    $height       Высота изображения с измененным размером в пикселях или процентах.
     * @param   boolean  $createNew    Если true, текущее изображение будет клонировано, изменено в размере и возвращено;
     *                                 в противном случае размер текущего изображения будет изменен и возвращен.
     * @param   integer  $scaleMethod  Какой метод использовать для масштабирования
     *
     * @return  Image
     *
     * @throws  \LogicException
     */
    public function resize(
        mixed $width,
        mixed $height,
        bool $createNew = true,
        int $scaleMethod = self::SCALE_INSIDE
    ): self {
        $width      = $this->sanitizeWidth($width, $height);
        $height     = $this->sanitizeHeight($height, $width);
        $dimensions = $this->prepareDimensions($width, $height, $scaleMethod);

        $offset    = new \stdClass();
        $offset->x = $offset->y = 0;

        if ($scaleMethod == self::SCALE_FIT) {
            $offset->x = round(($width - $dimensions->width) / 2);
            $offset->y = round(($height - $dimensions->height) / 2);

            $handle = imagecreatetruecolor($width, $height);

            if (!$this->isTransparent()) {
                $transparency = imagecolorallocatealpha($this->getHandle(), 0, 0, 0, 127);
                imagecolortransparent($this->getHandle(), $transparency);
            }
        } else {
            $handle = imagecreatetruecolor($dimensions->width, $dimensions->height);
        }

        imagealphablending($handle, false);
        imagesavealpha($handle, true);

        if ($this->isTransparent()) {
            $rgba  = imagecolorsforindex($this->getHandle(), imagecolortransparent($this->getHandle()));
            $color = imagecolorallocatealpha($handle, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']);

            imagecolortransparent($handle, $color);
            imagefill($handle, 0, 0, $color);
        }

        if (!$this->generateBestQuality) {
            imagecopyresized(
                $handle,
                $this->getHandle(),
                $offset->x,
                $offset->y,
                0,
                0,
                $dimensions->width,
                $dimensions->height,
                $this->getWidth(),
                $this->getHeight()
            );
        } else {
            imagecopyresampled(
                $handle,
                $this->getHandle(),
                $offset->x,
                $offset->y,
                0,
                0,
                $dimensions->width,
                $dimensions->height,
                $this->getWidth(),
                $this->getHeight()
            );
        }

        if ($createNew) {
            return new static($handle);
        }

        $this->destroy();

        $this->handle = $handle;

        return $this;
    }

    /**
     * Метод обрезки изображения после изменения его размера для сохранения пропорций без необходимости выполнять всю работу по настройке.
     *
     * @param   integer  $width      Желаемая ширина изображения в пикселях или процентах.
     * @param   integer  $height     Желаемая высота изображения в пикселях или процентах.
     * @param   boolean  $createNew  Если true, текущее изображение будет клонировано,
     *                               изменено в размере, обрезано и возвращено.
     *
     * @return  Image
     *
     */
    public function cropResize(int $width, int $height, bool $createNew = true): self {
        $width   = $this->sanitizeWidth($width, $height);
        $height  = $this->sanitizeHeight($height, $width);

        $resizewidth  = $width;
        $resizeheight = $height;

        if (($this->getWidth() / $width) < ($this->getHeight() / $height)) {
            $resizeheight = 0;
        } else {
            $resizewidth = 0;
        }

        return $this->resize($resizewidth, $resizeheight, $createNew)->crop($width, $height, null, null, false);
    }

    /**
     * Метод для поворота текущего изображения.
     *
     * @param   mixed    $angle       Угол поворота изображения
     * @param   integer  $background  Цвет фона, который будет использоваться при добавлении областей в результате вращения.
     * @param   boolean  $createNew   Если true, текущее изображение будет клонировано, повернуто и возвращено;
     *                                иначе текущее изображение будет повернуто и возвращено.
     *
     * @return  Image
     *
     * @throws  \LogicException
     */
    public function rotate(mixed $angle, int $background = -1, bool $createNew = true): self {
        $angle  = (float) $angle;
        $handle = imagecreatetruecolor($this->getWidth(), $this->getHeight());

        if ($background == -1) {
            imagealphablending($handle, false);
            imagesavealpha($handle, true);

            $background = imagecolorallocatealpha($handle, 0, 0, 0, 127);
        }

        imagecopy($handle, $this->getHandle(), 0, 0, 0, 0, $this->getWidth(), $this->getHeight());

        $handle = imagerotate($handle, $angle, $background);

        if ($createNew) {
            return new static($handle);
        }

        $this->destroy();

        $this->handle = $handle;

        return $this;
    }

    /**
     * Метод переворота текущего изображения.
     *
     * @param   integer  $mode       Режим переворота для переворачивания изображения {@link http://php.net/imageflip#refsect1-function.imageflip-parameters}
     * @param   boolean  $createNew  Если true, текущее изображение будет клонировано, перевернуто и возвращено;
     *                               иначе текущее изображение будет перевернуто и возвращено.
     *
     * @return  Image
     * @throws  \LogicException
     */
    public function flip(int $mode, bool $createNew = true): self {
        $handle = imagecreatetruecolor($this->getWidth(), $this->getHeight());

        imagecopy($handle, $this->getHandle(), 0, 0, 0, 0, $this->getWidth(), $this->getHeight());

        if (!imageflip($handle, $mode)) {
            throw new \LogicException('Невозможно перевернуть изображение.');
        }

        if ($createNew) {
            // @codeCoverageIgnoreStart
            return new static($handle);
            // @codeCoverageIgnoreEnd
        }

        $this->destroy();

        $this->handle = $handle;

        return $this;
    }

    /**
     * Водяной знак на изображении.
     *
     * @param   Image    $watermark     Объект изображения, содержащий изображение водяного знака.
     * @param   integer  $transparency  Прозрачность, используемая для графического водяного знака.
     * @param   integer  $bottomMargin  Поле внизу этого изображения.
     * @param   integer  $rightMargin   Поле с правой стороны этого изображения.
     *
     * @return  Image
     *
     * @link    https://secure.php.net/manual/ru/image.examples-watermark.php
     */
    public function watermark(
        Image $watermark,
        int $transparency = 50,
        int $bottomMargin = 0,
        int $rightMargin = 0
    ): self {
        imagecopymerge(
            $this->getHandle(),
            $watermark->getHandle(),
            $this->getWidth() - $watermark->getWidth() - $rightMargin,
            $this->getHeight() - $watermark->getHeight() - $bottomMargin,
            0,
            0,
            $watermark->getWidth(),
            $watermark->getHeight(),
            $transparency
        );

        return $this;
    }

    /**
     * Метод для записи текущего изображения в файл или вывода напрямую.
     *
     * @param   mixed    $path     Путь файловой системы для сохранения изображения.
     *                             Если значение равно нулю, поток необработанных изображений будет выводиться напрямую.
     * @param   integer  $type     Тип изображения, как сохранить файл.
     * @param   array    $options  Параметры типа изображения, которые будут использоваться при сохранении файла.
     *                             Для форматов PNG и JPEG используйте ключ `quality`, чтобы установить уровень сжатия (0..9 и 0..100).
     *
     * @return  boolean
     *
     * @link    http://www.php.net/manual/image.constants.php
     * @throws  \LogicException
     */
    public function toFile(mixed $path, int $type = IMAGETYPE_JPEG, array $options = []): bool {
        return match ($type) {
            IMAGETYPE_AVIF => imageavif($this->getHandle(), $path, (\array_key_exists('quality', $options)) ? $options['quality'] : 100),
            IMAGETYPE_GIF => imagegif($this->getHandle(), $path),
            IMAGETYPE_PNG => imagepng($this->getHandle(), $path, (\array_key_exists('quality', $options)) ? $options['quality'] : 0),
            IMAGETYPE_WEBP => imagewebp($this->getHandle(), $path, (\array_key_exists('quality', $options)) ? $options['quality'] : 100),
            default => imagejpeg($this->getHandle(), $path, (\array_key_exists('quality', $options)) ? $options['quality'] : 100),
        };
    }

    /**
     * Метод для получения экземпляра фильтра изображений указанного типа.
     *
     * @param   string  $type  Тип фильтра изображений, который требуется получить.
     *
     * @return  ImageFilter
     *
     * @throws  \RuntimeException
     */
    protected function getFilterInstance(string $type): ImageFilter {
        $type      = strtolower(preg_replace('#[^A-Z0-9_]#i', '', $type));
        $className = 'ImageFilter' . ucfirst($type);

        if (!class_exists($className)) {
            $className = __NAMESPACE__ . '\\Filter\\' . ucfirst($type);

            if (!class_exists($className)) {
                throw new \RuntimeException('Фильтр изображений недоступен: ' . ucfirst($type));
            }
        }

        $instance = new $className($this->getHandle());

        if (!($instance instanceof ImageFilter)) {
            throw new \RuntimeException('Фильтр изображений недействителен: ' . ucfirst($type));
        }

        return $instance;
    }

    /**
     * Метод получения новых размеров изображения с измененным размером.
     *
     * @param   integer  $width        Ширина измененного изображения в пикселях.
     * @param   integer  $height       Высота измененного изображения в пикселях.
     * @param   integer  $scaleMethod  Метод, используемый для масштабирования.
     *
     * @return  \stdClass
     *
     * @throws  \InvalidArgumentException  Если ширина, высота или оба значения равны нулю.
     */
    protected function prepareDimensions(int $width, int $height, int $scaleMethod): \stdClass {
        $dimensions = new \stdClass();

        switch ($scaleMethod) {
            case self::SCALE_FILL:
                $dimensions->width  = (int) round($width);
                $dimensions->height = (int) round($height);
                break;

            case self::SCALE_INSIDE:
            case self::SCALE_OUTSIDE:
            case self::SCALE_FIT:
                $rx = ($width > 0) ? ($this->getWidth() / $width) : 0;
                $ry = ($height > 0) ? ($this->getHeight() / $height) : 0;

                if ($scaleMethod != self::SCALE_OUTSIDE) {
                    $ratio = max($rx, $ry);
                } else {
                    $ratio = min($rx, $ry);
                }

                $dimensions->width  = (int) round($this->getWidth() / $ratio);
                $dimensions->height = (int) round($this->getHeight() / $ratio);
                break;

            default:
                throw new \InvalidArgumentException('Неверный метод масштабирования.');
        }

        return $dimensions;
    }

    /**
     * Метод очистки значения высоты.
     *
     * @param   mixed  $height  Входное значение высоты для очистки.
     * @param   mixed  $width   Значение входной ширины для справки.
     *
     * @return  integer
     *
     */
    protected function sanitizeHeight(mixed $height, mixed $width): int {
        $height = ($height === null) ? $width : $height;

        if (preg_match('/^[0-9]+(\.[0-9]+)?\%$/', $height)) {
            $height = (int) round($this->getHeight() * (float) str_replace('%', '', $height) / 100);
        } else {
            $height = (int) round((float) $height);
        }

        return $height;
    }

    /**
     * Метод для очистки значения смещения, например слева или сверху.
     *
     * @param   mixed  $offset  Значение смещения.
     *
     * @return  integer
     *
     */
    protected function sanitizeOffset(mixed $offset): int {
        return (int) round((float) $offset);
    }

    /**
     * Метод очистки значения ширины.
     *
     * @param   mixed  $width   Значение входной ширины для очистки.
     * @param   mixed  $height  Входное значение высоты для справки.
     *
     * @return  integer
     *
     */
    protected function sanitizeWidth(mixed $width, mixed $height): int {
        $width = ($width === null) ? $height : $width;

        if (preg_match('/^[0-9]+(\.[0-9]+)?\%$/', $width)) {
            $width = (int) round($this->getWidth() * (float) str_replace('%', '', $width) / 100);
        } else {
            $width = (int) round((float) $width);
        }

        return $width;
    }

    /**
     * Метод для уничтожения дескриптора изображения и освобождения памяти, связанной с дескриптором.
     *
     * @return  boolean  True в случае успеха, false в случае неудачи или если изображение не загружено
     *
     */
    public function destroy(): bool {
        if ($this->isLoaded()) {
            return imagedestroy($this->getHandle());
        }

        return false;
    }

    /**
     * Метод для вызова метода `destroy()` в последний раз, чтобы освободить всю память, когда объект не установлен.
     *
     * @see    Image::destroy()
     */
    public function __destruct() {
        $this->destroy();
    }

    /**
     * Метод установки опции метода создания миниатюр.
     *
     * @param   boolean  $quality  True для лучшего качества. False для лучшей скорости.
     *
     * @return  void
     */
    public function setThumbnailGenerate(bool $quality = true): void {
        $this->generateBestQuality = $quality;
    }
}
