<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

/**
 * Include map object.
 *
 * @package     Joomla.Services
 * @since       3.2
 */
class ApiApplicationIncludemap
{
	/*
	 * Include map.
	 */
	protected $map = array();

	/**
	 * Load include map from JSON.
	 *
	 * @param  string JSON-encoded resource map.
	 *
	 * @return object This method may be chained.
	 */
	public function fromJson($json)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		$map = json_decode($json, true);

		// The "embedded" array will contain a list of field names to be retained.
		if (isset($map['embedded']))
		{
			$this->map = $map['embedded'];
		}

		return $this;
	}

	/**
	 * Method to return a simple array of included fields.
	 *
	 * @return array Array of included field names.
	 */
	public function toArray()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		return $this->map;
	}

	/**
	 * Method to determine if a particular map is to be included.
	 *
	 * @param  string  $fieldName Field name.
	 *
	 * @return boolean
	 */
	public function isIncluded($fieldName)
	{
		JLog::add(new JLogEntry(__METHOD__.'('.$fieldName.')', JLOG::DEBUG, 'api'));
		return isset($this->map[$fieldName]);
	}

	/**
	 * Method to destroy a particular field
	 *
	 * @param  string  $fieldName Field name.
	 *
	 * @return void
	 */
	public function delete($fieldName)
	{
		JLog::add(new JLogEntry(__METHOD__.'('.$fieldName.')', JLOG::DEBUG, 'api'));
		$this->map = array_diff($this->map, [$fieldName]);
	}
}
