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
		
		// Check Content-Type
		// @TODO: why not 415 Unsupported Media Type
		if ($targetData->_meta->contentType != $this->contentType)
		{
			header('Status: 400 Bad Request', true, 400);
			exit;
		}
		
		if ($resourceMap)
		{
			$targetData = $resourceMap->toInternal($targetData);
		}

		// Store the target data
		$this->postData($targetData);

		// Set the correct header if resource is created
		header('Status: 201 Created', true, 201);

		// Check Prefer: return=representation	
		if (isset($_SERVER['HTTP_PREFER']))
		{
			$prefers = explode(';', $_SERVER['HTTP_PREFER']);
			array_walk($prefers, function(&$v, &$k) {
				$v = trim($v, ' "');
			});
			foreach ($prefers as $prefer)
			{
				if ($prefer == 'return=rapresentation')
				{
					// Push results into the document.
					$this->app->getDocument()
						->setMimeEncoding($this->contentType)		// Comment this line out to debug
						->setBuffer($service->load($this->getData())->getHal())
						;
					return;
				}
			}
		}
		exit;
	}

}
