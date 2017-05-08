<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

abstract class ApiControllerItem extends ApiControllerBase
{
	/*
	 * Unique key value.
	 */
	protected $id = 0;

	/**
	 * Execute the request.
	 */
	public function execute()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Get resource item id from input.
		$this->id = (int) $this->input->get('id');

		// Get resource item data.
		$data = $this->getData();

		// Get service object.
		$service = $this->getService();

		// Load the data into the HAL object.
		$service->load($data);

		parent::execute();
	}

	/**
	 * Get data for a single resource item.
	 *
	 * @return object Single resource item object.
	 */
	public function getData()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Get the database query object.
		$query = $this->getQuery($this->tableName);

		// Get a database query helper object.
		$apiQuery = $this->getApiQuery();

		// Get single record from database.
		$data = $apiQuery->getItem($query, (int) $this->id);

		return $data;
	}

	/**
	 * Post data from JSON resource item.
	 *
	 * @param   string	$data  The JSON+HAL resource.
	 *
	 * @return bool True if resource is created, false if some error occured
	 */
	public function postData($data, $tableClass = false, $tablePrefix = 'JTable', $tablePath = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Declare return
		$return = false;
	
		// Get the database query object.
		$query = $this->db->getQuery(true);
	
		// Get a database query helper object.
		$apiQuery = $this->getApiQuery();
	
		// Get the correct table class
		$tableClass = ($tableClass === false) ? $this->tableClass : $tableClass;
	
		// Get the correct table prefix
		$tablePrefix = ($tablePrefix != 'JTable') ? $tablePrefix : 'JTable';
	
		// Include the tags
		jimport('cms.helper.helper');
		jimport('cms.helper.tags');
		jimport('cms.helper.contenthistory');
		jimport('cms.component.helper');
		jimport('cms.application.helper');
		jimport('cms.component.record');
		
		// Include the legacy table classes
		JTable::addIncludePath(JPATH_LIBRARIES . '/legacy/table/');
	
		// Include the cms table classes
		JTable::addIncludePath(JPATH_LIBRARIES . '/cms/table/');
	
		// Include the custom table path if exists
		if (count($tablePath))
		{
			foreach ($tablePath as $path)
			{
				JTable::addIncludePath($path);
			}
		}
	
		JLog::add(new JLogEntry('table class: '.$tablePrefix.$tableClass, JLOG::DEBUG, 'api'));
	
		// Declare the JTable class
		$table = JTable::getInstance($tableClass, $tablePrefix, array('dbo' => $this->db));
	
		try
		{
			$data['created_by'] = $this->app->getIdentity()->get('id');

			$return = $apiQuery->postItem($query, $table, $data);
		}
		catch (Exception $e)
		{
			$this->app->setHeader('status', '400', true);
	
			// An exception has been caught, echo the message and exit.
			echo json_encode(array('message' => $e->getMessage(), 'code' => $e->getCode(), 'type' => get_class($e)));
			exit;
		}
	
		return $return;
	}

	/**
	 * Method to delete a row from the database table by primary key value.
	 *
	 * @param   mixed  $pk  An optional primary key value to delete.  If not set the instance property value is used.
	 *
	 * @return  boolean  True on success.
	 */
	public function delete($tableClass = false, $tablePrefix = 'JTable', $tablePath = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Declare return
		$return = false;
		
		// Get the correct table class
		$tableClass = ($tableClass === false) ? $this->tableClass : $tableClass;
		
		// Get the correct table prefix
		$tablePrefix = ($tablePrefix != 'JTable') ? $tablePrefix : 'JTable';
		
		// Include the tags
		jimport('cms.helper.helper');
		jimport('cms.helper.tags');
		jimport('cms.helper.contenthistory');
		jimport('cms.component.helper');
		jimport('cms.application.helper');
		jimport('cms.component.record');

		// Include the legacy table classes
		JTable::addIncludePath(JPATH_LIBRARIES . '/legacy/table/');
		
		// Include the cms table classes
		JTable::addIncludePath(JPATH_LIBRARIES . '/cms/table/');
		
		// Include the custom table path if exists
		if (count($tablePath))
		{
			foreach ($tablePath as $path)
			{
				JTable::addIncludePath($path);
			}
		}
		
		// Declare the JTable class
		$table = JTable::getInstance($tableClass, $tablePrefix, array('dbo' => $this->db));
		
		// Load data
		$this->id = (int) $this->input->get('id');
		if (!$table->load($this->id))
		{
			header('Status: 404 Not Found', true, 404);
			exit;
		}
		
		// Load asset
		$asset = JTable::getInstance('Asset', 'JTable', array('dbo' => $this->db));
		$asset->load($table->asset_id);
		
		// Get the user
		$user = $this->app->getIdentity();
		
		// Check access
		if (!$user->authorise('core.delete', $asset->name))
		{
			header('Status: 400 Bad Request', true, 400);
				
			$response = array(
				'error' => 'bad request',
				'error_description' => JText::_('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED')
			);
				
			echo json_encode($response);
			exit;
		}
		
		// Verify checkout
		if ($table->checked_out != 0 && $table->checked_out != $user->id)
		{
			header('Status: 400 Bad Request', true, 400);
		
			$response = array(
				'error' => 'bad request',
				'error_description' => JText::sprintf('JLIB_APPLICATION_ERROR_CHECKOUT_FAILED', JText::_('JLIB_APPLICATION_ERROR_CHECKOUT_USER_MISMATCH'))
			);
		
			echo json_encode($response);
			exit;
		}
		
		// Delete object
		try
		{
			$table->delete($this->id);
		}
		catch (Exception $e)
		{
			$this->app->setHeader('status', '404', true);

			// An exception has been caught, echo the message and exit.
			echo json_encode(array('message' => $e->getMessage(), 'code' => $e->getCode(), 'type' => get_class($e)));
			exit;
		}
		return $return;
	}
}