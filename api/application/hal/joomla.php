<?php
/**
 * @package     Joomla.Services
 *
 * @copyright   Copyright (C) 2005 - 2013 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

/**
 * Class to represent a Joomla HAL object.
 *
 * This is a HAL object with some Joomla-specific additional properties.
 */
class ApiApplicationHalJoomla extends ApiApplicationHal
{
	/*
	 * Metadata object.
	 */
	protected $meta = null;

	/*
	 * Page number.
	 */
	protected $page = 1;

	/*
	 * Number of items per page.
	 */
	protected $perPage = 10;

	/*
	 * Page base offset.
	 */
	protected $offset = 0;

	/*
	 * Resource map.
	 */
	protected $resourceMap = null;

	/*
	 * Include map object for embedded resources.
	 */
	protected $includeMap = null;

	/**
	 * Constructor.
	 *
	 * @param  array  $options  Array of configuration options.
	 */
	public function __construct($options = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// Create a metadata object.
		$this->meta = new stdClass;
		$this->meta->apiVersion = '1.0';
		$this->set('_meta', $this->meta);

		// Add standard Joomla namespace as curie.
		$joomlaCurie = new ApiApplicationHalLink('curies', 'http://docs.joomla.org/Link_relations/{rel}');
		$joomlaCurie->name = 'joomla';
		$joomlaCurie->templated = true;
		$this->addLink($joomlaCurie);

		// Add basic hypermedia links.
		$this->addLink(new ApiApplicationHalLink('base', rtrim(JUri::base(), '/')));
		if (isset($options['self']))
		{
			$this->addLink(new ApiApplicationHalLink('self', $options['self']));
		}

		// Set the content type.
		if (isset($options['contentType']))
		{
			$this->setMetadata('contentType', $options['contentType']);
		}

		// Set link to (human-readable) schema documentation.
		if (isset($options['describedBy']))
		{
			$this->setMetadata('describedBy', $options['describedBy']);
		}

		// Set the fields
		if (isset($options['fields']) && ($options['fields'] != ''))
		{
			$this->setMetadata('fields', explode(',', $options['fields']));
		}
	
		// Load the resource field map (if there is one).
		$resourceMapFile = isset($options['resourceMap']) ? $options['resourceMap'] : '';
		if ($resourceMapFile != '' && file_exists($resourceMapFile))
		{
			$basePath = dirname($options['resourceMap']);
			$this->resourceMap = new ApiApplicationResourcemap(array('basePath' => $basePath));
			$this->resourceMap->fromJson(file_get_contents($resourceMapFile));
		}

		// Load the embedded field map (if there is one).
		$embeddedMapFile = isset($options['embeddedMap']) ? $options['embeddedMap'] : '';
		if ($embeddedMapFile != '' && file_exists($embeddedMapFile))
		{
			// Load the embedded fields list.
			$this->includeMap = new ApiApplicationIncludemap();
			$this->includeMap->fromJson(file_get_contents($embeddedMapFile));
		}
	
		// Only showing requested fields.
		if (isset($options['fields']) && ($options['fields'] != ''))
		{
			$fields = explode(',', $options['fields']);
			if ($this->resourceMap)
			{
				foreach ($this->resourceMap->toArray() as $k => $v)
				{
					$f = (($i = strpos($k, '/')) === false ? $k : substr($k, $i + 1));
					if (!in_array($f, $fields))
					{
						$this->resourceMap->delete($k);
					}
				}
			}
			if ($this->includeMap)
			{
				foreach ($this->includeMap->toArray() as $k => $v)
				{
					$f = (($i = strpos($k, '/')) === false ? $k : substr($k, $i + 1));
					if (!in_array($f, $fields))
					{
						$this->includeMap->delete($k);
					}
				}
			}
		}
	}

	/**
	 * Import data into HAL object.
	 *
	 * @param  string $name  Name (rel) of data to be imported.
	 * @param  array  $data  Array of data items.
	 *
	 * @return object This object may be chained.
	 */
	public function embed($name, $data)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// If there is no map then use the standard embed method.
		if (!($this->includeMap instanceof ApiApplicationIncludemap))
		{
			return parent::embed($name, $data);
		}

		// Get list of fields to be included.
		$include = $this->includeMap->toArray();

		// Transform the source data array.
		$resources = array();
		foreach ($data as $key => $datum)
		{
			$resources[$key] = $this->resourceMap->toExternal($datum, $include);
		}

		// Embed data into HAL object.
		parent::embed($name, $resources);

		// Add pagination URI template (per RFC6570).
		$pagesLink = new ApiApplicationHalLink('pages', '/' . $name . '{?fields,offset,page,perPage,sort}');
		$pagesLink->templated = true;
		$this->addLink($pagesLink);

		return $this;
	}

	/**
	 * Method to return an object suitable for serialisation.
	 *
	 * @return stdClass A Joomla HAL object suitable for serialisation.
	 */
	public function getHal()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		
		$properties = $this->properties;
		foreach ($properties as $k => $v)
		{
			if (substr($k, 0, 1) == "_")
			{
				unset($properties[$k]);
			}
		}
		$this->meta->etag = md5(json_encode($properties));
		
		if (isset($properties['publish']->modified))
		{
			$this->meta->lastModified = $properties['publish']->modified;
		}
		elseif (isset($properties['publish']->created))
		{
			$this->meta->lastModified = isset($properties['publish']->created);
		}
		else
		{
			$this->meta->lastModified = gmdate("Y-M-d H:i:s");
		}		
		$this->set('_meta', $this->meta);

		$hal = parent::getHal();

		return $hal;
	}

	/**
	 * Method to return a metadata field.
	 *
	 * @param  string  $field   Field name.
	 * @param  string  $default Optional default value.
	 *
	 * @return mixed Value of field.
	 */
	public function getMetadata($field, $default = '')
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		if (!isset($this->meta->$field))
		{
			return $default;
		}

		return $this->meta->$field;
	}

	/**
	 * Method to return the resource map object.
	 *
	 * @return ApiApplicationResourcemap Resource map object.
	 */
	public function getResourceMap()
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		return $this->resourceMap;
	}

	/**
	 * Method to load an object into this HAL object.
	 *
	 * @param  object  $object  Object whose properties are to be loaded.
	 *
	 * @return object This method may be chained.
	 */
	public function load($object)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		// If there is no map then use the standard load method.
		if (empty($this->resourceMap))
		{
			return parent::load($object);
		}

		parent::load($this->resourceMap->toExternal($object));

		return $this;
	}

	/**
	 * Method to add or modify a metadata field.
	 *
	 * @param  string  $field  Field name.
	 * @param  mixed   $value  Value to be assigned to the field.
	 *
	 * @return object  This method may be chained.
	 */
	public function setMetadata($field, $value)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		$this->meta->$field = $value;

		return $this;
	}

	/**
	 * Set pagination variables.
	 *
	 * @param  array  $page  Array of pagination variables.
	 *
	 * @return object  This object may be chained.
	 */
	public function setPagination($page = array())
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		if (isset($page['page']))
		{
			$this->meta->page = $page['page'];
		}

		if (isset($page['perPage']))
		{
			$this->meta->perPage = $page['perPage'];
		}

		if (isset($page['offset']))
		{
			$this->meta->offset = $page['offset'];
		}

		if (isset($page['totalItems']))
		{
			$this->meta->totalItems = $page['totalItems'];
		}

		if (isset($page['totalPages']))
		{
			$this->meta->totalPages = $page['totalPages'];
		}

		return $this;
	}

	/**
	 * expand Content-Type
	 *
	 * @param  string  $content_type  
	 * @param  boolean $pattern
	 *
	 * @return array  content types
	 */
	public function expandContentType($content_type, $pattern = false)
	{
		JLog::add(new JLogEntry(__METHOD__."('{$content_type}', {$pattern})", JLOG::DEBUG, 'api'));
		JLog::add(new JLogEntry($content_type, JLOG::DEBUG, 'api'));
		if (isset($pattern)) JLog::add(new JLogEntry($pattern, JLOG::DEBUG, 'api'));
				
		$content_types = explode(',', $content_type);
		array_walk($content_types, function(&$v, &$k) {
			$v = trim($v, ' "');
		});
	
		$temp = [];
		foreach ($content_types as $mime_type)
		{
			if (strpos($mime_type, '+') !== false)
			{ // Contains +
				$arr = explode('/', $mime_type);
				$type = $arr[0];
				$medias = explode('+', $arr[1]);
				foreach ($medias as $media)
				{
					array_push($temp, $type."/".$media);
				}
			}
			else
			{
				array_push($temp, $mime_type);
			}
		}
		$temp = array_unique($temp);

		if ($pattern)
		{
			array_walk($temp, function(&$v1, $k1) {
				$v1 = explode(';', $v1);
				array_walk($v1, function(&$v2, $k2) {
					$v2 = explode('.', $v2);
					array_walk($v2, function(&$v3, $k3) {
						$v3 = trim($v3, ' ');
					});
					$v3 = end($v2); 
					while ($v4 = prev($v2))
					{
						$v3 = $v4.'(.'.$v3.')?';
					}
					$v2 = $v3;
				});
				$v2 = end($v1);
				while ($v3 = prev($v1))
				{
					$v2 = $v3.'(; '.$v2.')?';
				}
				$v1 = '/'.str_replace('/', '\/', $v2).'/';
			});
		}
		JLog::add(new JLogEntry(print_r($temp, true), JLOG::DEBUG, 'api'));
		return $temp;
	}
	
	public function isAccepted($accept)
	{
		JLog::add(new JLogEntry(__METHOD__, JLOG::DEBUG, 'api'));
		
		$accept = $this->expandContentType($accept);
		$patterns = $this->expandContentType($this->meta->contentType.'+hal+json', true);
		
		if (in_array("*/*", $accept)) return true;
		foreach($accept as $content_type)
		{
			foreach($patterns as $pattern)
			{
				if (preg_match($pattern, $content_type, $matches))
				{
					if ($content_type == $matches[0]) return true;
				}
			}
		}
		return false;
	}
}
