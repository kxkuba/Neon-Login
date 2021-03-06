<?php
/*
 * CPHP is more free software. It is licensed under the WTFPL, which
 * allows you to do pretty much anything with it, without having to
 * ask permission. Commercial use is allowed, and no attribution is
 * required. We do politely request that you share your modifications
 * to benefit other developers, but you are under no enforced
 * obligation to do so :)
 * 
 * Please read the accompanying LICENSE document for the full WTFPL
 * licensing text.
 */

if($_CPHP !== true) { die(); }

if(!empty($cphp_config->memcache->enabled))
{
	$cphp_memcache = new Memcache;
	$cphp_memcache_established = $cphp_memcache->connect($cphp_config->memcache->hostname, $cphp_config->memcache->port);

	if($cphp_memcache_established !== false)
	{
		$cphp_memcache_connected = true;
	}
	else
	{
		$cphp_memcache_connected = false;
	}
}

function mc_get($key)
{
	global $cphp_config, $cphp_memcache_connected, $cphp_memcache;
	
	if(empty($cphp_config->memcache->enabled) || $cphp_memcache_connected === false)
	{
		return false;
	}
	else
	{
		$get_result = $cphp_memcache->get($key);
		
		if($get_result !== false)
		{
			return $get_result;
		}
		else
		{
			return false;
		}
	}
}

function mc_set($key, $value, $expiry)
{
	global $cphp_config, $cphp_memcache_connected, $cphp_memcache;
	
	if(empty($cphp_config->memcache->enabled) || $cphp_memcache_connected === false)
	{
		return false;
	}
	else
	{
		if(!empty($cphp_config->memcache->compressed) === true)
		{
			$flag = MEMCACHE_COMPRESSED;
		}
		else
		{
			$flag = false;
		}
		
		$set_result = $cphp_memcache->set($key, $value, $flag, $expiry);
		return $set_result;
	}
}

function mc_delete($key)
{
	global $cphp_config, $cphp_memcache_connected, $cphp_memcache;
	
	if(empty($cphp_config->memcache->enabled) || $cphp_memcache_connected === false)
	{
		return false;
	}
	else
	{
		$delete_result = $cphp_memcache->delete($key);
		return $delete_result;
	}
}

function mysql_query_cached($query, $expiry = 60, $key = "", $exec = false)
{
	global $cphp_config, $database;
	
	if($key == "")
	{
		$key = md5($query) . md5($query . "x");
	}
	
	if($res = mc_get($key))
	{
		$return_object->source = "memcache";
		$return_object->data = $res;
		return $return_object;
	}
	else
	{
		if(empty($cphp_config->database->pdo))
		{
			if($res = mysql_query($query))
			{
				$found = false;
				
				while($row = mysql_fetch_assoc($res))
				{
					$return_object->data[] = $row;
					$found = true;
				}
				
				if($found === true)
				{
					$return_object->source = "database";
					mc_set($key, $return_object->data, $expiry);
					return $return_object;
				}
				else
				{
					return false;
				}
			}
			else
			{
				return null;
			}
		}
		else
		{
			/* Transparently use PDO to run the query. */
			if($exec === false && $statement = $database->query($query))
			{
				if($data = $statement->fetchAll(PDO::FETCH_ASSOC))
				{
					if(count($data) > 0)
					{
						if($expiry != 0)
						{
							mc_set($key, $result, $expiry);
						}
						
						$return_object = new stdClass;
						$return_object->source = "database";
						$return_object->data = $data;
						
						return $return_object;
					}
					else
					{
						return false;
					}
				}
				else
				{
					return null;
				}
			}
			elseif($exec === true)
			{
				$statement = $database->exec($query);
				
				if(is_null($statement))
				{
					return null;
				}
				/*elseif($statement == 0)
				{
					return false;
				}*/
				else
				{
					$return_object = new stdClass();
					$return_object->source = "database";
					$return_object->data = $statement;
					return $return_object;
				}
			}
			else
			{
				return null;
			}
		}
	}
}

function file_get_contents_cached($path, $expiry = 3600)
{
	if($res = mc_get(md5($path) . md5($path . "x")))
	{
		$return_object->source = "memcache";
		$return_object->data = $res;
		return $return_object;
	}
	else
	{
		if($result = file_get_contents($path))
		{
			$return_object->source = "disk";
			$return_object->data = $result;
			mc_set(md5($path) . md5($path . "x"), $return_object->data, $expiry);
			return $return_object;
		}
		else
		{
			return false;
		}
	}
}
