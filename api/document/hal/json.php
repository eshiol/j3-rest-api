<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * ApiDocumentHal class, provides an easy interface to parse and display HAL+JSON output
 *
 * @package     Joomla.Services
 * @subpackage  Document
 * @see         http://stateless.co/hal_specification.html
 * @since       3.1
 */
class ApiDocumentHalJson extends JDocument
{
	/**
	 * Document name
	 *
	 * @var    string
	 * @since  3.1
	 */
	protected $_name = 'joomla';

	/**
	 * Render hrefs as absolute or relative?
	 */
	protected $absoluteHrefs = false;

	/**
	 * Class constructor
	 *
	 * @param   array  $options  Associative array of options
	 *
	 * @since  3.1
	 */
	public function __construct($options = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		parent::__construct($options);

		// Set default mime type.
		$this->_mime = 'application/json';

		// Set document type.
		$this->_type = 'hal+json';

		// Set absolute/relative hrefs.
		$this->absoluteHrefs = isset($options['absoluteHrefs']) ? $options['absoluteHrefs'] : false;
	}

	/**
	 * Render the document.
	 *
	 * @param   boolean  $cache   If true, cache the output
	 * @param   array    $params  Associative array of attributes
	 *
	 * @return  The rendered data
	 *
	 * @since  3.1
	 */
	public function render($cache = false, $params = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		JResponse::allowCache($cache);

		// Unfortunately, the exact syntax of the Content-Type header
		// is not defined, so we have to try to be a bit clever here.
		$contentType = $this->_mime;
		if (JFactory::getApplication()->input->get->getString('callback', false) !== false)
		{
			$contentType = 'application/javascript';
		}
		else
		{
			JResponse::setHeader('Content-disposition', 'attachment; filename="' . $this->getName() . '.json"', true);
			if (stripos($contentType, 'json') === false)
			{
				$contentType .= '+' . $this->_type;
			}
		}
		$this->_mime = $contentType;

		parent::render();

		// Get the HAL object from the buffer.
		$hal = $this->getBuffer();

		// If required, change relative links to absolute.
		if ($this->absoluteHrefs && is_object($hal) && isset($hal->_links))
		{
			// Adjust hrefs in the _links object.
			$this->relToAbs($hal->_links);

			// Adjust hrefs in the _embedded object (if there is one).
			if (isset($hal->_embedded))
			{
				foreach ($hal->_embedded as $rel => $resources)
				{
					foreach ($resources as $id => $resource)
					{
						if (isset($resource->_links))
						{
							$this->relToAbs($resource->_links);
						}
					}
				}
			}
		}

		//set last-modified header
		$lastModified = isset($hal->_meta->lastModified) ? strtotime($hal->_meta->lastModified) : time();
		header("Last-Modified: ".gmdate("D, d M Y H:i:s", $lastModified)." GMT");
		
		//set etag
		header('Etag: '.$hal->_meta->etag);

		//make sure caching is turned on
		header('Cache-Control: public');

		// Return it as a JSON string.
		return json_encode($hal);
	}

	/**
	 * Returns the document name
	 *
	 * @return  string
	 *
	 * @since  3.1
	 */
	public function getName()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		return $this->_name;
	}

	/**
	 * Method to convert relative to absolute links.
	 *
	 * @param  object $links  Links object (eg. _links).
	 */
	protected function relToAbs($links)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Adjust hrefs in the _links object.
		foreach ($links as $rel => $link)
		{
			if (substr($link->href, 0, 1) == '/')
			{
				$links->$rel->href = rtrim(JUri::base(), '/') . $link->href;
			}
		}
	}

	/**
	 * Sets the document name
	 *
	 * @param   string  $name  Document name
	 *
	 * @return  JDocumentJSON instance of $this to allow chaining
	 *
	 * @since   3.1
	 */
	public function setName($name = 'joomla')
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		$this->_name = $name;

		return $this;
	}
}
