<?php
/**
 * Часть пакета Flexis Framework Utilities.
 */

namespace Flexis\Utilities;

/**
 * Утилитный класс для создания сложных регулярных выражений.
 */
abstract class RegEx {
	/**
	 * Сопоставяет регулярное выражение.
	 *
	 * @param string $subject  Строка для проверки.
	 *
	 * @return  array  Зафиксированные значения.
	 */
	public static function match(string $regex, string $subject): array {
		$match = array();

		preg_match($regex, $subject, $match);

		return array_filter(
			$match,
			static function ($value, $key) {
				return !is_numeric($key) && !empty($value);
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Назначает ключ выражению.
	 *
	 * @param string      $regex  Регулярное выражение для соответствия.
	 * @param string|null $as     Имя компонента, используемое в качестве индекса.
	 *
	 * @return  string  Модифицированное регулярное выражение.
	 */
	public static function capture(string $regex, string $as = null): string {
		return '(?P<' . $as . '>' . $regex . ')';
	}

	/**
	 * Добавляет в выражение квантификатор «ноль или один.
	 *
	 * @param string $regex  Регулярное выражение для соответствия.
	 *
	 * @return  string  Модифицированное регулярное выражение.
	 */
	public static function optional(string $regex): string {
		return '(?:' . $regex . ')?';
	}

	/**
	 * Добавляет в выражение квантификатор «один или несколько».
	 *
	 * @param string $regex  Регулярное выражение для соответствия.
	 *
	 * @return  string  Модифицированное регулярное выражение.
	 */
	public static function oneOrMore(string $regex): string {
		return '(?:' . $regex . ')+';
	}

	/**
	 * Добавляет к выражению квантификатор «ноль или более».
	 *
	 * @param string $regex  Регулярное выражение для соответствия.
	 *
	 * @return  string  Модифицированное регулярное выражение.
	 */
	public static function noneOrMore(string $regex): string {
		return '(?:' . $regex . ')*';
	}

	/**
	 * Определяет список альтернативных выражений.
	 *
	 * @param array|string $regexList  Список регулярных выражений на выбор.
	 *
	 * @return  string  Модифицированное регулярное выражение.
	 */
	public static function anyOf(array|string $regexList): string {
		if (is_string($regexList)) {
			$regexList = func_get_args();
		}

		return '(?:' . implode('|', $regexList) . ')';
	}
}
