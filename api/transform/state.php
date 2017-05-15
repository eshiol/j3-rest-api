<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

class ApiTransformState extends ApiTransformBase
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
			case 0:
				$return = 'unpublished';
				break;
			case 1:
				$return = 'published';
				break;
			case 2:
				$return = 'archived';
				break;
			case -2:
				$return = 'trashed';
				break;
			default:
				$return = 'undefined';
				break;
		}

		return $return;
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
			case 'published':
				$return = 1;
				break;
			case 'archived':
				$return = 2;
				break;
			case 'trashed':
				$return = -2;
				break;
			case 'unpublished':
			default:
				$return = 0;
				break;
		}
	}

}
