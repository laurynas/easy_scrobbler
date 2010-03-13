<?php
/**
 * Easy Scrobbler
 * 
 * PHP Last.fm client using Audioscrobbler Web Services API
 * 
 * Example:
 * <code>
 * require_once 'easy_scrobbler.class.php';
 * 
 * $scrobbler = new EasyScrobbler('myusername');
 * 
 * // get tracks array
 * $list = $scrobbler->getWeeklyTrackChart();
 * 
 * // just to see what is in array - dump :) You could do something more useful.
 * var_dump($list);
 * </code>
 * 
 * Feel free to extend or modify.
 * 
 * More Audioscrobbler clients: http://audioscrobbler.net/wiki/
 * 
 * 
 * @author Laurynas Butkus <laurynas.butkus@gmail.com>
 * @copyright Copyright (c) 2006, Laurynas Butkus
 * @link http://lauris.night.lt/forge/easy_scrobbler/
 * @version 0.1 (2006-07-22)
 */

class EasyScrobbler
{
	/**
	 * @access private
	 */
	var $username;
	
	/**
	 * @access private
	 */
	var $cache_dir 		= '/tmp/';
	
	/**
	 * @access private
	 */
	var $cache_lifetime = 120; // seconds
	
	/**
	 * @access private
	 */
	var $_items_level 	= 3;
	
	/**
	 * @access private
	 */
	var $_items;
	
	/**
	 * @access private
	 */
	var $_item;
	
	/**
	 * @access private
	 */
	var $_parent_tags 	= array();
	
	/**
	 * @access private
	 */
	var $_cdata 		= array();
	
	/**
	 * @param string $username Last.fm username
	 * @return EasyScrobbler
	 */
	function EasyScrobbler($username)
	{
		$this->setUsername($username);
	}
	
	/**
	 * Set usertname
	 *
	 * @param string $username Last.fm username
	 */
	function setUsername($username)
	{
		$this->username = $username;
	}
	
	/**
	 * Cache lifetime (in seconds)
	 *
	 * @param int $seconds
	 */
	function setCacheLifetime($seconds)
	{
		$this->cache_lifetime = $seconds;
	}
	
	/**
	 * Cache directory
	 *
	 * @param string $dir
	 */
	function setCacheDir($dir)
	{
		$this->cache_dir = $dir;
	}
	
	/**
	 * Get user profile
	 *
	 * @return array
	 */
	function getProfile()
	{
		$this->setItemsLevel(2);		
		$items = $this->parseFeed('profile', 'user');		
		return current($items);
	}
	
	/**
	 * Get 50 most played artists from a music profile
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getTopArtists($max_entries = null)
	{
		return $this->getGenericLevel3('topartists', 'user', $max_entries);
	}
	
	/**
	 * Get 50 most played albums from a music profile
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getTopAlbums($max_entries = null)
	{
		return $this->getGenericLevel3('topalbums', 'user', $max_entries);
	}
	
	/**
	 * Get 50 most played tracks from a music profile
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getTopTracks($max_entries = null)
	{
		return $this->getGenericLevel3('toptracks', 'user', $max_entries);
	}
	
	/**
	 * Get most used tags by a music profile
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getTopTags($max_entries = null)
	{
		return $this->getGenericLevel3('tags', 'user', $max_entries);
	}
	
	/**
	 * Get friends added to profile
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getFriends($max_entries = null)
	{
		return $this->getGenericLevel3('friends', 'user', $max_entries);
	}
	
	/**
	 * Get people with similar taste to profile
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getNeighbours($max_entries = null)
	{
		return $this->getGenericLevel3('neighbours', 'user', $max_entries);
	}
	
	/**
	 * Get 10 recently played tracks for profile
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getRecentTracs($max_entries = null)
	{
		return $this->getGenericLevel3('recenttracks', 'user', $max_entries);
	}
	
	/**
	 * Get most recent weekly artist chart
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getWeeklyArtistChart($max_entries = null)
	{
		return $this->getGenericLevel3('weeklyartistchart', 'user', $max_entries);
	}

	/**
	 * Most recent weekly album chart
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getWeeklyAlbumChart($max_entries = null)
	{
		return $this->getGenericLevel3('weeklyalbumchart', 'user', $max_entries);
	}

	/**
	 * Most recent weekly track chart
	 *
	 * @param int $max_entries limit results
	 * @return array
	 */
	function getWeeklyTrackChart($max_entries = null)
	{
		return $this->getGenericLevel3('weeklytrackchart', 'user', $max_entries);
	}
	
	// --- PRIVATE METHODS BELOW -----------------------------------
	
	/**
	 * @access private
	 */
	function getGenericLevel3($action, $type, $max_entries = null)
	{
		$this->setItemsLevel(3);
		
		$items = $this->parseFeed($action, $type);
		
		if (!is_null($max_entries))
			$items = array_slice($items, 0, $max_entries);
			
		return $items;

	}
	
	/**
	 * @access private
	 */
	function setItemsLevel($level)
	{
		$this->_items_level = $level;
	}
	
	/**
	 * @access private
	 */
	function getItemsLevel()
	{
		return $this->_items_level;	
	}
	
	/**
	 * @access private
	 */
	function getFileContents($action, $type)
	{
		$file = $this->getCacheFileName($action, $type);
		
		if (!file_exists($file) || filemtime($file) < time() - $this->cache_lifetime)
		{		
			$xml = file_get_contents($this->getUrl($action, $type));
			
			$fp = fopen($file, "w+");
			fwrite($fp, $xml);
			fclose($fp);
		}
		
		return file_get_contents($file);
	}
	
	/**
	 * @access private
	 */
	function getCacheFileName($action, $type)
	{
		return $this->cache_dir . 'audioscrobbler-' . $type . '-' . $this->username . '-' . $action . '.xml';
	}
	
	/**
	 * @access private
	 */
	function getUrl($action, $type)
	{
		return "http://ws.audioscrobbler.com/1.0/{$type}/{$this->username}/{$action}.xml";
	}	
	
	/**
	 * @access private
	 */
	function parseFeed($action, $type)
	{
		return $this->parse($this->getFileContents($action, $type));
	}
	
	/**
	 * @access private
	 */
	function parse($xml)
	{
		$this->_items = array();
		
		$parser = xml_parser_create('UTF-8');
		
		xml_set_object($parser, $this);
		xml_set_element_handler($parser, "startElement", "endElement");
		xml_set_character_data_handler($parser, "characterData");
		
		if (!xml_parse($parser, $xml))
			trigger_error(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($parser)), xml_get_current_line_number($parser)), E_USER_ERROR);
			
		xml_parser_free($parser);
		
		return $this->_items;
	}
	
	/**
	 * @access private
	 */
	function startElement($parser, $name, $attrs)
    {
    	$name = strtoupper($name);
    	
		array_push($this->_parent_tags, $name);
		
		switch ($this->getParentLevel())
		{
			case $this->getItemsLevel() - 1:
				$this->_item = array();
				break;
				
			case $this->getItemsLevel():
			case $this->getItemsLevel() + 1:
				$this->_cdata[$this->getParentLevel()] = '';
				
				foreach ($attrs as $key=>$val)
					$this->_item[$name . '_' . $key] = $val;
				break;
		}
    }
	
	/**
	 * @access private
	 */
	function endElement($parser, $name)
    {
    	$name = strtoupper($name);
    	
		switch ($this->getParentLevel())
		{
			case $this->getItemsLevel() - 1:
				$this->_items[] = $this->_item;
				$this->_item = array();
				break;
				
			case $this->getItemsLevel():
				if (!is_array($this->_item[$name]))
					$this->_item[$name] = trim($this->_cdata[$this->getParentLevel()]);
				break;
				
			case $this->getItemsLevel() + 1:
				$this->_item[$this->_parent_tags[$this->getItemsLevel() - 1]][$name] = trim($this->_cdata[$this->getItemsLevel() + 1]);
				break;
		}
    	
    	array_pop($this->_parent_tags);
    }
    
	/**
	 * @access private
	 */
	function characterData($parser, $data)
	{
	    $this->_cdata[$this->getParentLevel()].= $data;
	}
	
	/**
	 * @access private
	 */
	function getParentLevel()
	{
		return count($this->_parent_tags);
	}
}