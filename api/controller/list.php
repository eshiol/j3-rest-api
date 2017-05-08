<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

abstract class ApiControllerList extends ApiControllerBase
{
	/*
	 * Category id.
	 */
	protected $catid = 0;

	/**
	 * Execute the request.
	 */
	public function execute()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Are we linking from categories?
		$this->catid = (int) $this->input->get('catid');

		// Get page of data.
		$data = $this->getData();

		// Get service object.
		$service = $this->getService();

		// Set pagination.
		$service->setPagination($this->getApiQuery()->getPagination());

		// Import the data into the HAL object.
		$service->embed($this->primaryRel, $data);

		parent::execute();
	}

	/**
	 * Get a page of data.
	 *
	 * @return array  Array of data records.
	 */
	public function getData()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Get the database query object.
		$query = $this->getQuery($this->tableName);

		// Get a database query helper object.
		$apiQuery = $this->getApiQuery();

		// Get pagination variables.
		$pagination = $this->getPagination();

		// Get the page of data.
		$data = $apiQuery->setPagination($pagination)->getList($query);

		return $data;
	}

	/**
	 * Get pagination variables.
	 *
	 * May be overridden in child classes.
	 *
	 * @return array Array of pagination variables.
	 */
	public function getPagination()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Set pagination variables from input.
		$pagination = array(
			'offset'  => (int) $this->input->get('offset', 0),
			'page'    => (int) $this->input->get('page', 1),
			'perPage' => (int) $this->input->get('perPage', 15),
		);

		return $pagination;
	}

	/**
	 * Get database query.
	 *
	 * May be overridden in child classes.
	 *
	 * @param  string  $table  Primary table name.
	 *
	 * @return JDatabaseDriver object.
	 */
	public function getQuery($table)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Get the base query.
		$query = parent::getQuery($table);

		if ($this->catid)
		{
			$query->where($this->db->qn('catid') . ' = ' . (int) $this->catid);
		}

		// Get the user
		$user = $this->app->getIdentity();
	
		// Filter by access level.
		if ($user->guest != 1)
		{
			$groups = implode(',', $user->getAuthorisedViewLevels());
			$query->where('access IN (' . $groups . ')');
		}
		else if ($user->guest == 1)
		{
			$query->where('access = 1');
		}
	
		JLog::add(new JLogEntry($query, JLOG::DEBUG, 'api'));
		return $query;
	}
}
