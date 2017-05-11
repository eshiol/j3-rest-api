<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

jimport('cms.helper.helper');
jimport('cms.helper.tags');
jimport('cms.helper.contenthistory');
jimport('cms.component.helper');
jimport('cms.application.helper');
jimport('cms.component.record');

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

		if (is_null($data))
		{
			header('Status: 404 Not Found', true, 404);
			exit;
		}
		
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

		// Get the user
		$user = $this->app->getIdentity();
		
		if ($user->guest == 1)
		{
			if ($data->access != 1)
			{
				header('Status: 401 Unauthorized', true, 401);
					
				$response = array(
						'error' => 'unauthorized',
						'error_description' => JText::_('JERROR_ALERTNOAUTHOR')
				);
		
				echo json_encode($response);
				exit;
			}
		}
		else
		{
			if (!in_array($data->access, $user->getAuthorisedViewLevels()))
			{
				header('Status: 403 Forbidden', true, 403);
		
				$response = array(
						'error' => 'forbidden',
						'error_description' => JText::_('JERROR_ALERTNOAUTHOR')
				);
					
				echo json_encode($response);
				exit;
			}
		}

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
	 * @return  boolean  True on success.
	 */
	public function delete($tableClass = false, $tablePrefix = 'JTable', $tablePath = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		
		// Get resource item id from input.
		$this->id = (int) $this->input->get('id');
		
		// Get the correct table class
		$tableClass = ($tableClass === false) ? $this->tableClass : $tableClass;
		
		// Get the correct table prefix
		$tablePrefix = ($tablePrefix != 'JTable') ? $tablePrefix : 'JTable';
		
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

		// Delete item
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

	/**
	 * Method to check a row out if the necessary properties/fields exist.
	 *
	 * To prevent race conditions while editing rows in a database, a row can be checked out if the fields 'checked_out' and 'checked_out_time'
	 * are available. While a row is checked out, any attempt to store the row by a user other than the one who checked the row out should be
	 * held until the row is checked in again.
	 *
	 * @return  boolean  True on success.
	 *
	 * @throws  UnexpectedValueException
	 */
	public function checkOut($tableClass = false, $tablePrefix = 'JTable', $tablePath = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		
		// Get resource item id from input.
		$this->id = (int) $this->input->get('id');
		
		// Get the correct table class
		$tableClass = ($tableClass === false) ? $this->tableClass : $tableClass;
		
		// Get the correct table prefix
		$tablePrefix = ($tablePrefix != 'JTable') ? $tablePrefix : 'JTable';
		
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
		if (!$user->authorise('core.edit', $asset->name))
		{
			header('Status: 401 Unauthorised status', true, 401);

			$response = array(
				'error' => 'bad request',
				'error_description' => JText::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED')
			);

			echo json_encode($response);
			exit;
		}

		// Verify checkout
		if ($table->checked_out != 0 && $table->checked_out != $user->id)
		{
			header('Status: 401 Unauthorised status', true, 401);

			$response = array(
				'error' => 'bad request',
				'error_description' => JText::sprintf('JLIB_APPLICATION_ERROR_CHECKOUT_FAILED', JText::_('JLIB_APPLICATION_ERROR_CHECKOUT_USER_MISMATCH'))
			);

			echo json_encode($response);
			exit;
		}

		// checkout item
		try
		{
			$table->checkOut($user->id, $this->id);
		}
		catch (Exception $e)
		{
			$this->app->setHeader('status', '404', true);

			// An exception has been caught, echo the message and exit.
			echo json_encode(array('message' => $e->getMessage(), 'code' => $e->getCode(), 'type' => get_class($e)));
			exit;
		}

		// Get resource item data.
		$data = $this->getData();

		// Get service object.
		$service = $this->getService();

		// Load the data into the HAL object.
		$service->load($data);

		parent::execute();
	}	

	/**
	 * Method to check a row in if the necessary properties/fields exist.
	 *
	 * Checking a row in will allow other users the ability to edit the row.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   11.1
	 * @throws  UnexpectedValueException
	 */
	public function checkIn($tableClass = false, $tablePrefix = 'JTable', $tablePath = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));

		// Get resource item id from input.
		$this->id = (int) $this->input->get('id');
		
		// Get the correct table class
		$tableClass = ($tableClass === false) ? $this->tableClass : $tableClass;
	
		// Get the correct table prefix
		$tablePrefix = ($tablePrefix != 'JTable') ? $tablePrefix : 'JTable';
	
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
		if (!$user->authorise('core.edit', $asset->name))
		{
			header('Status: 401 Unauthorised status', true, 401);
	
			$response = array(
				'error' => 'bad request',
				'error_description' => JText::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED')
			);
	
			echo json_encode($response);
			exit;
		}
	
		// Verify checkout
		if ($table->checked_out != 0 && $table->checked_out != $user->id)
		{
			header('Status: 401 Unauthorised status', true, 401);
	
			$response = array(
				'error' => 'bad request',
				'error_description' => JText::sprintf('JLIB_APPLICATION_ERROR_CHECKOUT_FAILED', JText::_('JLIB_APPLICATION_ERROR_CHECKOUT_USER_MISMATCH'))
			);
	
			echo json_encode($response);
			exit;
		}
	
		// checkin item
		try
		{
			$table->checkIn($this->id);
		}
		catch (Exception $e)
		{
			$this->app->setHeader('status', '404', true);
	
			// An exception has been caught, echo the message and exit.
			echo json_encode(array('message' => $e->getMessage(), 'code' => $e->getCode(), 'type' => get_class($e)));
			exit;
		}
		
		// Get resource item data.
		$data = $this->getData();
		
		// Get service object.
		$service = $this->getService();
		
		// Load the data into the HAL object.
		$service->load($data);
		
		parent::execute();
	}

	/**
	 * Method to update a row from the database table by primary key value.
	 *
	 * @return  boolean  True on success.
	 */
	public function update($tableClass = false, $tablePrefix = 'JTable', $tablePath = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));

		// Get resource item id from input.
		$this->id = (int) $this->input->get('id');
		
		// Get the database query object.
		$query = $this->db->getQuery(true);
		
		// Get a database query helper object.
		$apiQuery = $this->getApiQuery();
		
		// Get the correct table class
		$tableClass = ($tableClass === false) ? $this->tableClass : $tableClass;
		
		// Get the correct table prefix
		$tablePrefix = ($tablePrefix != 'JTable') ? $tablePrefix : 'JTable';
		
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
		if (!$user->authorise('core.edit', $asset->name))
		{
			header('Status: 401 Unauthorised status', true, 401);
		
			$response = array(
					'error' => 'bad request',
					'error_description' => JText::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED')
			);
		
			echo json_encode($response);
			exit;
		}
		
		// Verify checkout
		if ($table->checked_out != 0 && $table->checked_out != $user->id)
		{
			header('Status: 401 Unauthorised status', true, 401);
		
			$response = array(
					'error' => 'bad request',
					'error_description' => JText::sprintf('JLIB_APPLICATION_ERROR_CHECKOUT_FAILED', JText::_('JLIB_APPLICATION_ERROR_CHECKOUT_USER_MISMATCH'))
			);
		
			echo json_encode($response);
			exit;
		}
		
		// update item
		try
		{
			// Get service object.
			$service = $this->getService();
			
			// Get the resource map
			$resourceMap = $service->getResourceMap();
			
			// Get resource item from input.
			$targetData = json_decode(file_get_contents("php://input"));
			$targetData->id = $this->id;
			$targetData->modified_by = $this->app->getIdentity()->get('id');

			if ($resourceMap)
			{
				$targetData = $resourceMap->toInternal($targetData);
			}

			JLog::add(new JLogEntry('targetData: '.print_r($targetData, true), JLOG::DEBUG, 'api'));

			$return = $apiQuery->postItem($query, $table, $targetData);
		}
		catch (Exception $e)
		{
			$this->app->setHeader('status', '404', true);
		
			// An exception has been caught, echo the message and exit.
			echo json_encode(array('message' => $e->getMessage(), 'code' => $e->getCode(), 'type' => get_class($e)));
			exit;
		}

		// Get resource item data.
		$data = $this->getData();
		
		// Get service object.
		$service = $this->getService();
		
		// Load the data into the HAL object.
		$service->load($data);
		
		parent::execute();
		
		return $return;
	}
}
