<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

class ComponentContentArticlesUpdate extends ApiControllerItem
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
			'resourceMap' => __DIR__ . '/resource.json',
			'self' 		  => '/joomla:articles/' . (int) $this->input->get('id'),
			'tableName'   => '#__content',
			'tableClass'  => 'Content',
		);

		$this->setOptions($serviceOptions);
	}

	/**
	 * Execute the request.
	 */
	public function execute()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		parent::update();
	}
}
