<?php

/**
 * Try to find locations for libs during the upgrade process
 * will match old
 * - App::import('Lib', 'PluginName.ClassName') to App::uses('ClassName', 'PluginName.Lib/Package')
 * - App::import('Core', 'Xml') to App::uses('Xml', 'Package')
 */
class Lib {

	/**
	 * Convert file inclusions to 2.x style
	 * e.g. import("Lib", "Tools.SuperDuper") from App::import to uses("SuperDuper", "Tools.Lib")
	 *
	 * @return string Plugin.Misc (if in Misc Package), Plugin.Lib (if in lib root) or NULL on failure
	 */
	public function match($name, $type = 'Lib') {
		list($plugin, $tmp) = pluginSplit($name, true);
		list($pluginName, $name) = pluginSplit($name);

		if ($pluginName = Inflector::camelize(trim($pluginName))) {
			# make sure plugin is available to avoid errors later on
			try {
				CakePlugin::path($pluginName);
			} catch (exception $e) {
				trigger_error($e->getMessage() . ' - ' . $pluginName . ' does not exists (' . $name . ')');
				return null;
			}
		}
		# blacklist? lazyloading with app::uses causes fatal errors in some libs that extend/import vendor files
		$blacklist = array('IcqLib');
		if (in_array($name, $blacklist)) {
			return null;
		}

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
	 * Recursive match down the lib package paths
	 *
	 * @return string
	 */
	protected function _match($path, $name, $plugin, $loop = 0) {
		$Iterator = new RecursiveDirectoryIterator($path);
		foreach ($Iterator as $File) {
			if (substr(basename($File->getPathname()), 0, 1) === '.') {
				continue;
			}
			if (!$File->isFile()) {
				if ($res = $this->_match($File->getPathname(), $name, $plugin, $loop + 1)) {
					return $res;
				}
			}
			if (basename($File->getPathname(), '.php') === $name) {
				$finalPath = dirname($File->getPathname());
				$package = array();
				while ($loop) {
					$loop--;
					$package[] = basename($finalPath);
					$finalPath = dirname($finalPath);
				}
				$package = array_reverse($package);

				return $plugin . implode('/', $package);
			}
		}
		return null;
	}

}
