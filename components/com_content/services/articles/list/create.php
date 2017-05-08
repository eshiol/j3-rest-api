<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

class ComponentContentArticlesListCreate extends ApiControllerItem
{
	/**
	 * Constructor.
	 *
	 * @param   JInput            $input  The input object.
	 * @param   JApplicationBase  $app    The application object.
	 */
	public function __construct(JInput $input = null, JApplicationBase $app = null)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		parent::__construct($input, $app);

		// Use the default database.
		$this->setDatabase();

		// Set the controller options.
		$serviceOptions = array(
			'contentType' => 'application/vnd.joomla.item.v1; schema=articles.v1',
			'describedBy' => 'http://docs.joomla.org/Schemas/articles/v1',
			'primaryRel'  => 'joomla:articles',
			'resourceMap' => __DIR__ . '/../resource.json',
			'tableName'   => '#__content',
			'tableClass'  => 'Content',
		);

		$this->setOptions($serviceOptions);

		JFactory::getLanguage()->load('com_content', JPATH_ADMINISTRATOR, 'en-GB', true);
	}

	/**
	 * Execute the request.
	 */
	public function execute()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		parent::checkIdentity();

		// Get service object.
		$service = $this->getService();

		// Get the resource map
		$resourceMap = $service->getResourceMap();

		// Get resource item from input.
		$targetData = json_decode(file_get_contents("php://input"));

		if ($resourceMap)
		{
			$targetData = $resourceMap->toInternal($targetData);
		}
		JLog::add(new JLogEntry('targetData: '.print_r($targetData, true), JLOG::DEBUG, 'api'));

		// Store the target data
		$this->postData($targetData);

		// Set the correct header if resource is created
		header('Status: 201 Created', true, 201);

		exit;
	}

}
