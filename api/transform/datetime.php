<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

class ApiTransformDatetime extends ApiTransformBase
{
	/**
	 * Method to transform an internal representation to an external one.
	 *
	 * @param  string   $definition  Field definition.
	 * @param  mixed    $data        Source data.
	 *
	 * @return string Transformed value.
	 */
	public static function toExternal($definition, $data)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		if ($definition != '0000-00-00 00:00:00')
		{
			return date(DATE_ISO8601, strtotime($definition));
		}
		else
		{
			return null;
		}
		return (string) $definition;
	}

	/**
	 * Method to transform an internal representation to an external one.
	 *
	 * @param  string   $definition  Field definition.
	 * @param  mixed    $data        Source data.
	 *
	 * @return string Transformed value.
	 */
	public static function toInternal($definition, $data)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));	
		return date('Y-m-d H:i:s', strtotime($definition));
	}
	
}