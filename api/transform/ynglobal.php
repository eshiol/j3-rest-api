<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

class ApiTransformYNGlobal extends ApiTransformBase
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
		switch ($definition)
		{
			case '':
				return 'global';
				break;
			case 0:
				return 'no';
				break;
			case 1:
				return 'yes';
				break;
			default:
				return 'undefined';
				break;
		}
	}

	/**
	 * Method to transform an external representation to an internal one.
	 *
	 * @param  string   $definition  Field definition.
	 * @param  mixed    $data        Source data.
	 *
	 * @return int Transformed value.
	 */
	public static function toInternal($definition, $data)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		switch ($definition)
		{
			case 'no':
				return 0;
				break;
			case 'yes':
				return 1;
				break;
			case 'global':
			default:
				return '';
				break;
		}
	}

}
