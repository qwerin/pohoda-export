<?php
/**
 * Author: Ivo Toman
 */

namespace Pohoda;


class Validators
{


	public static function assertDate($value)
	{
		if (!($value instanceof \DateTime) && !date_create($value)) {
			throw new \InvalidArgumentException("Value: $value is not a date");
		}
	}


	public static function isDate($value)
	{
		try {
			self::assertDate($value);
		} catch (\InvalidArgumentException $e) {
			return false;
		}
		return true;
	}


	public static function assertNumeric($value)
	{
		if (!is_numeric($value)) {
			throw new \InvalidArgumentException("Value: $value is not numeric");
		}
	}


	public static function isNumeric($value)
	{
		try {
			self::assertNumeric($value);
		} catch (\InvalidArgumentException $e) {
			return false;
		}
		return true;
	}


	public static function assertMaxLength($value, $maxLength)
	{
		if (strlen($value) > $maxLength) {
			throw new \InvalidArgumentException("Value: $value is length than $maxLength");
		}
	}


	public static function isMaxLength($value, $maxLength)
	{
		try {
			self::assertLength($value, $maxLength);
		} catch (\InvalidArgumentException $e) {
			return false;
		}
		return true;
	}


}