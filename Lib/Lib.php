<?php

/**
 * try to find locations for libs during the upgrade process
 * will match old App::import('Lib', 'PluginName.ClassName') to App::uses('ClassName', 'PluginName')
 * 2011-11-09 ms
 */
class Lib {
	
	/**
	 * e.g. Lib, Tools.SuperDuper (from App::import)
	 * @return Tools.Misc (if in Misc Package), Tools.Lib (if in lib root) or NULL on failure
	 */
	public function match($name, $type = 'Lib') {
		list($plugin, $x) = pluginSplit($name, true);
		list($pluginName, $name) = pluginSplit($name);
		
		$libs = App::objects($plugin.$type, null, false);
		if (in_array($name, $libs)) {
			return $plugin.$type;
		}
		$paths = App::path($type, $pluginName, false);
		foreach ($paths as $path) {
			if (!file_exists($path)) {
				continue;
			}
			$Iterator = new RecursiveDirectoryIterator($path);
			foreach ($Iterator as $File) {
				if ($File->isFile()) {
					continue;
				}
				if ($res = $this->_match($path, $name, $plugin)) {
					return $res;
				}
			}
		}
		if (!empty($plugin)) {
			return null;
		}
		
		# check core
		$type = 'Core';
		$path = CAKE;
		if ($res = $this->_match($path, $name, $plugin)) {
			return $res;
		}
		
		return null;
	}
	
	/**
	 * recursive match down the lib package paths
	 */
	protected function _match($path, $name, $plugin, $loop = 0) {
		$Iterator = new RecursiveDirectoryIterator($path);
		foreach ($Iterator as $File) {
			if (substr(basename($File->getPathname()), 0, 1) == '.') {
				continue;
			}
			if (!$File->isFile()) {
				if ($res = $this->_match($File->getPathname(), $name, $plugin, $loop+1)) {
					return $res;
				}
			}
			if (basename($File->getPathname(), '.php') == $name) {
				$finalPath = dirname($File->getPathname());
				$package = array();
				while ($loop) {
					$loop--;
					$package[] = basename($finalPath);
					$finalPath = dirname($finalPath);
				}
				$package = array_reverse($package);
				
				return $plugin.implode('/', $package);
			}
		}
		return null;
	}
	
}