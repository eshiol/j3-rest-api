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

		// Get HAL
		$hal = $service->getHal();

		// Check Accept
		if (isset($_SERVER['HTTP_ACCEPT']))
		{
			$accept = $_SERVER['HTTP_ACCEPT'];
		}
		else
		{
			$accept = $this->app->input->get('accept', '*/*');
		}

		if (!$service->isAccepted($accept))
		{
			header($_SERVER['SERVER_PROTOCOL'].' 415 Media type not supported');
			header('Content-Type: application/api-problem+json');

			$response = array(
				"describedby" => "http://docs.joomla.org/API_errors/v1/Media_type_not_recognised",
				"title" => "Media type must be ".$hal->_meta->contentType.'+hal+json',
				"requested" => $accept
			);

			echo json_encode($response);
			exit;
		}

		// Get ETag
		$etag = $hal->_meta->etag;
		JLog::add(new JLogEntry('etag: '.$etag, JLOG::DEBUG, 'api'));

		// Check If-Match
		if (isset($_SERVER['HTTP_IF_MATCH']))
		{
			$ifMatch = $_SERVER['HTTP_IF_MATCH'];
			if ($ifMatch != '*')
			{
				$ifMatch = explode(',', $ifMatch);
				array_walk($ifMatch, function(&$v, &$k) {
					$v = trim($v, ' "');
				});
				JLog::add(new JLogEntry('If-Match: '.print_r($ifMatch, true), JLOG::DEBUG, 'api'));
				if (!in_array($etag, $ifMatch))
				{
					header($_SERVER['SERVER_PROTOCOL'].' 412 Precondition Failed');
					exit;
				}
			}
		}

		// Get LastModified
		$lastModified = strtotime($hal->_meta->lastModified);
		JLog::add(new JLogEntry('lastModified: '.$hal->_meta->lastModified.' ('.$lastModified.')', JLOG::DEBUG, 'api'));

		// Check If-Unmodified-Since
		if (isset($_SERVER['HTTP_IF_UNMODIFIED_SINCE']))
		{
			if ($ifUnmodifiedSince = strtotime($_SERVER['HTTP_IF_UNMODIFIED_SINCE']))
			{
				JLog::add(new JLogEntry('If-Unmodified-Since: '.$_SERVER['HTTP_IF_UNMODIFIED_SINCE'].' ('.$ifUnmodifiedSince.')', JLOG::DEBUG, 'api'));
				if ($lastModified > $ifUnmodifiedSince)
				{
					header($_SERVER['SERVER_PROTOCOL'].' 412 Precondition Failed');
					exit;
				}
			}
		}

		// Check If-None-Match
		if (isset($_SERVER['HTTP_IF_NONE_MATCH']))
		{
			$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'];
			if ($ifNoneMatch == '*')
			{
				header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
				exit;
			}
			$ifNoneMatch = explode(',', $ifNoneMatch);
			array_walk($ifNoneMatch, function(&$v, &$k) {
				$v = trim($v, ' "');
			});
			JLog::add(new JLogEntry('If-None-Match: '.print_r($ifNoneMatch, true), JLOG::DEBUG, 'api'));
			if (in_array($etag, $ifNoneMatch))
			{
				header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
				exit;
			}
		}

		// Check If-Modified-Since
		if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']))
		{
			if ($ifModifiedSince = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))
			{
				JLog::add(new JLogEntry('If-Modified-Since: '.$_SERVER['HTTP_IF_MODIFIED_SINCE'].' ('.$ifModifiedSince.')', JLOG::DEBUG, 'api'));
				if ($lastModified <= time())
				{
					if ($lastModified <= $ifModifiedSince)
					{
						header($_SERVER['SERVER_PROTOCOL'].' 304 Not Modified');
						exit;
					}
				}
			}
		}

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
		JLog::add(new JLogEntry("id: {$this->id}", JLOG::DEBUG, 'api'));
		// Get the database query object.
		$query = $this->getQuery($this->tableName);

		// Get a database query helper object.
		$apiQuery = $this->getApiQuery();

		// Get single record from database.
		$data = $apiQuery->getItem($query, (int) $this->id);

		if (is_null($data))
		{
			header('Status: 404 Not Found', true, 404);
			exit;
		}

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
			JLog::add(new JLogEntry('id: '.$table->id, JLOG::DEBUG, 'api'));
			$this->id = $table->id;
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
			header('Content-Type: application/json');

			$response = array(
				'error' => 'bad request',
				'error_description' => JText::_('JLIB_APPLICATION_ERROR_DELETE_NOT_PERMITTED')
			);

			echo json_encode($response);
			exit;
		}

		// Check If-Match
		$ifMatch = isset($_SERVER['HTTP_IF_MATCH']) ? $_SERVER['HTTP_IF_MATCH'] : '*';
		if ($ifMatch != '*')
		{
			// Get resource item data.
			$data = $this->getData();

			// Get service object.
			$service = $this->getService();

			// Load the data into the HAL object.
			$service->load($data);

			// Get HAL
			$hal = $service->getHal();

			// Get ETag
			$etag = $hal->_meta->etag;

			$ifMatch = explode(',', $ifMatch);
			array_walk($ifMatch, function(&$v, &$k) {
				$v = trim($v, ' "');
			});
			JLog::add(new JLogEntry('If-Match: '.print_r($ifMatch, true), JLOG::DEBUG, 'api'));
			if (!in_array($etag, $ifMatch))
			{
				header('Status: 412 Precondition Failed', true, 412);
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
		}

		// Delete item
		try
		{
			$table->delete($this->id);
		}
		catch (Exception $e)
		{
			header('Status: 500', true, 500);

			// An exception has been caught, echo the message and exit.
			$response = array(
			);

			echo json_encode($response);
			exit;
		}
		return true;
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
