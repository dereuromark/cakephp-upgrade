<?php
namespace Upgrade\Shell;

use Cake\Console\Shell;
use Cake\Core\Plugin;
use Cake\Filesystem\Folder;

/**
 * A shell class to help developers upgrade applications to CakePHP 3.x latest.
 *
 * Necessary expectations for the shell to work flawlessly:
 * - Follow PSR-2-R (recommended) or PSR-2.
 *
 */
class UpgradeShell extends Shell {

	/**
	 * Files
	 *
	 * @var array
	 */
	protected $_files = array();

	/**
	 * Paths
	 *
	 * @var array
	 */
	protected $_paths = array();

	/**
	 * Custom Paths
	 *
	 * @var array
	 */
	protected $_customPaths = array();

	/**
	 * Map
	 *
	 * @var array
	 */
	protected $_map = array(
		'Controller' => 'Controller',
		'Component' => 'Controller/Component',
		'Model' => 'Model',
		'Behavior' => 'Model/Behavior',
		'Datasource' => 'Model/Datasource',
		'Dbo' => 'Model/Datasource/Database',
		'View' => 'View',
		'Helper' => 'View/Helper',
		'Shell' => 'Console/Command',
		'Task' => 'Console/Command/Task',
		'Case' => 'Test/Case',
		'Fixture' => 'Test/Fixture',
		'Error' => 'Lib/Error',
	);

	/**
	 * Shell startup, prints info message about dry run.
	 *
	 * @return void
	 */
	public function startup() {
		parent::startup();
		if ($this->params['dry-run']) {
			$this->out(__d('cake_console', '<warning>Dry-run mode enabled!</warning>'), 1, Shell::QUIET);
		}
		if (($this->params['git'] || $this->params['tgit']) && !$this->_isType('git')) {
			$this->out(__d('cake_console', '<warning>No git repository detected!</warning>'), 1, Shell::QUIET);
		}
		if ($this->params['svn'] && !$this->_isType('svn')) {
			$this->out(__d('cake_console', '<warning>No svn repository detected!</warning>'), 1, Shell::QUIET);
		}
		//TODO: .hg

		# check for commands - if not available exit immediately
		if ($this->params['svn']) {
			$this->params['svn'] = 'svn';
			if (!empty($this->args[0])) {
				$this->params['svn'] = rtrim($this->args[0], DS) . DS . 'svn';
			}
			$res = exec('"' . $this->params['svn'] . '" help', $array, $r);
			if ($r) {
				return $this->error($res, 'The command `svn` is unknown (on Windows install SlikSVN)');
			}
		}
		if ($this->params['tgit']) {
			$res = exec('tgit help', $array, $r);
			if ($r) {
				return $this->error($res, 'The command `tgit` is unknown (on Windows install TortoiseGit)');
			}
		}

		// custom path overrides everything
		if (!empty($this->params['custom'])) {
			$this->_customPaths = array($this->params['custom']);
			//$this->_paths = array($this->params['custom']);
			$this->params['plugin'] = '';
		} elseif ($this->params['plugin'] === '*') {
			$plugins = App::objects('plugins');
			$plugins = array_unique($plugins);
			$paths = array();
			foreach ($plugins as $plugin) {
				$paths[] = Plugin::path($plugin);
			}
			$this->_customPaths = $paths;
			//$this->_paths = $this->_customPaths;
			$this->params['plugin'] = '';
		}
	}

	/**
	 * UpgradeShell::_buildPaths()
	 *
	 * @param string|array $path Path relative to plugin or APP and with trailing DS.
	 * @return void
	 */
	protected function _buildPaths($path = null) {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
			return;
		}
		$path = (array)$path;

		if (empty($path)) {
			if (!empty($this->params['plugin'])) {
				$this->_paths = array(Plugin::path($this->params['plugin']));
			} else {
				$this->_paths = array(APP);
			}
			return;
		}

		$paths = array();
		foreach ($path as $p) {
			$p = str_replace(DS, '/', $p);
			$p = trim($p, '/');
			$paths = array_merge($paths, App::path($p, $this->params['plugin']));
		}
		$this->_paths = $paths;
	}

	/**
	 * @param string %type (svn, git, ...)
	 * @return boolean Success
	 */
	protected function _isType($type) {
		if (is_dir('.' . $type)) {
			return true;
		}
		//check if parent folders contain .type
		$path = APP;
		while ($path !== ($newPath = dirname($path))) {
			$path = $newPath;
			if (is_dir($path . DS . '.' . $type)) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Run all upgrade steps one at a time
	 *
	 * @return void
	 */
	public function all() {
		foreach ($this->OptionParser->subcommands() as $command) {
			$name = $command->name();
			if ($name === 'all' || $name === 'group') {
				continue;
			}
			$version = (float)Configure::version();

			if ($version < 2.5 && $name === 'cake25' || $version < 3.0 && $name === 'cake30') {
				$this->out('Skipping command ' . $name);
				continue;
			}

			if (!empty($this->params['interactive'])) {
				$continue = $this->in('Continue with `' . $name . '`?', array('y', 'n', 'q'), 'y');
				if ($continue === 'q') {
					return $this->error('Aborted');
				}
				if ($continue === 'n') {
					$this->out('Skipping command ' . $name);
					continue;
				}
			}

			$this->out(__d('cake_console', 'Running %s', $name));
			$this->$name();
		}
	}

	/**
	 * Run all defined upgrade steps one at a time
	 *
	 * Use params to define (at least two - otherwise you can run it standalone):
	 * cake upgrade group controllers components ...
	 *
	 * Or use Configure:
	 * Configure::write('UpgradeGroup.cc', array('controllers', 'components', ...));
	 * and
	 * cake upgrade group cc
	 * NOTE: group names cannot be one of the commands to run!
	 *
	 * the group method is the only one capable of understanding -p * (all plugins at once)
	 *
	 * @return void
	 */
	public function group() {
		$subCommands = $this->OptionParser->subcommands();
		$subCommandList = array_keys($subCommands);
		$commands = $this->args;
		if (count($commands) === 1) {
			$commands = (array)Configure::read('UpgradeGroup' . $commands[0]);
		}
		if (empty($commands)) {
			return $this->error(__d('cake_console', 'No group found. Please use args or Configure to define groups.'));
		}
		$commands = array_unique($commands);
		foreach ($commands as $command) {
			if (!in_array($command, $subCommandList)) {
				$this->err(__d('cake_console', 'Invalid command \'%s\' - skipping', $command));
				continue;
			}
		}
		$this->args = $this->_paths = array();
		foreach ($subCommands as $command) {
			$name = $command->name();
			if ($name === 'all' || $name === 'group' || !in_array($name, $commands)) {
				continue;
			}
			$this->out(__d('cake_console', 'Running %s', $name));
			if (empty($this->params['plugin']) || $this->params['plugin'] !== '*') {
				$this->$name();
				continue;
			}
			# run all plugins
			$plugins = Plugin::loaded();
			foreach ($plugins as $plugin) {
				if (in_array($plugin, array('Upgrade'))) {
					continue;
				}
				$this->args = array();
				$this->out(__d('cake_console', '- in plugin %s', $plugin), 1, Shell::VERBOSE);
				$this->params['plugin'] = $plugin;
				$this->$name();
			}

		}
	}

	/**
	 * Automatically set the path according to custom paths or plugin given
	 * defaults to $path
	 *
	 * @return void
	 */
	protected function _setPath($path, $pluginPath) {
		if (!empty($this->_customPaths)) {
			$this->_paths = (array)$this->_customPaths;
			return;
		}
		if (!empty($this->params['plugin'])) {
			$this->_paths = (array)$pluginPath;
			return;
		}
		$this->_paths = (array)$path;
	}

	/**
	 * Move file with 2 step process to avoid collisions on case insensitive systems
	 *
	 * @return void
	 */
	protected function _move($from, $to, $folder = true) {
		$tmp = '_tmp';
		if ($this->params['git']) {
			exec('git mv -f ' . escapeshellarg($from) . ' ' . escapeshellarg($from . $tmp));
			exec('git mv -f ' . escapeshellarg($from . $tmp) . ' ' . escapeshellarg($to));
		} elseif ($this->params['tgit']) {
			exec('tgit mv -f ' . escapeshellarg($from) . ' ' . escapeshellarg($from . $tmp));
			exec('tgit mv -f ' . escapeshellarg($from . $tmp) . ' ' . escapeshellarg($to));
		} elseif ($this->params['svn']) {
			exec('"' . $this->params['svn'] . '" move --force ' . escapeshellarg($from) . ' ' . escapeshellarg($from . $tmp));
			exec('"' . $this->params['svn'] . '" move --force ' . escapeshellarg($from . $tmp) . ' ' . escapeshellarg($to));
		} elseif ($folder) {
			$Folder = new Folder($from);
			$Folder->move($to . $tmp);
			$Folder = new Folder($to . $tmp);
			$Folder->move($to);
		} else {
			rename($from, $to);
		}
	}

	/**
	 * Delete file according to repository type
	 *
	 * @return void
	 */
	protected function _delete($path, $folder = true) {
		//problems on windows due to case insensivity (Config/config etc)
		//problems in subversion after deletion
		if (strpos($path, DS . 'Config' . DS) !== false) {
			return;
		}

		if ($this->params['git']) {
			//exec('git rm -rf ' . escapeshellarg($path));
		} elseif ($this->params['tgit']) {
			//exec('tgit rm -rf ' . escapeshellarg($path));
		} elseif ($this->params['svn']) {
			//exec('svn delete --force ' . escapeshellarg($path));
		} elseif ($folder) {
			$Folder = new Folder($path);
			$Folder->delete();
		} else {
			unlink($from, $to);
		}
	}

	/**
	 * Create and add file according to repository type
	 *
	 * @return void
	 */
	protected function _create($path) {
		while (!is_dir($subpath = dirname($path))) {
			$this->_create($subpath);
		}

		new Folder($path, true);
		if ($this->params['git']) {
			exec('git add -f ' . escapeshellarg($path));
		} elseif ($this->params['tgit']) {
			exec('tgit add -f ' . escapeshellarg($path));
		} elseif ($this->params['svn']) {
			exec('"' . $this->params['svn'] . '" add --force ' . escapeshellarg($path));
		}
	}

	/**
	 * Corrects name of database engine
	 * mysqli => Mysql
	 *
	 * @return string
	 */
	protected function _prepDatasource($x) {
		$driver = $x[1];
		if ($driver === 'mysqli') {
			$driver = 'mysql';
		}
		$driver = ucfirst($driver);
		return '\'datasource\' => \'Database/' . $driver . '\'';
	}

	/**
	 * Move application views files to where they now should be
	 *
	 * Find all view files in the folder and determine where cake expects the file to be
	 *
	 * @return void
	 */
	protected function _moveViewFiles() {
		if (!is_dir('View')) {
			return;
		}

		$dirs = scandir('View');
		foreach ($dirs as $old) {
			if (!is_dir('View' . DS . $old) || $old === '.' || $old === '..') {
				continue;
			}

			$new = 'View' . DS . Inflector::camelize($old);
			$old = 'View' . DS . $old;
			if ($new === $old) {
				continue;
			}

			$this->out(__d('cake_console', 'Moving %s to %s', $old, $new));
			if (!$this->params['dry-run']) {
				$this->_move($old, $new);
			}
		}
	}

	/**
	 * Move the AppController, and AppModel classes.
	 *
	 * @return void
	 */
	protected function _moveAppClasses() {
		$files = array(
			APP . 'app_controller.php' => APP . 'Controller' . DS . 'AppController.php',
			APP . 'controllers' . DS . 'app_controller.php' => APP . 'Controller' . DS . 'AppController.php',
			APP . 'app_model.php' => APP . 'Model' . DS . 'AppModel.php',
			APP . 'models' . DS . 'app_model.php' => APP . 'Model' . DS . 'AppModel.php',
		);
		foreach ($files as $old => $new) {
			if (file_exists($old)) {
				$this->out(__d('cake_console', 'Moving %s to %s', $old, $new));

				if ($this->params['dry-run']) {
					continue;
				}
				$this->_move($old, $new);
			}
		}
	}

	/**
	 * Move application php files to where they now should be
	 *
	 * Find all php files in the folder (honoring recursive) and determine where CakePHP expects the file to be
	 * If the file is not exactly where CakePHP expects it - move it.
	 *
	 * @param string $path
	 * @param array $options array(recursive, checkFolder)
	 * @return void
	 */
	protected function _movePhpFiles($path, $options) {
		if (!is_dir($path)) {
			return;
		}

		$paths = $this->_paths;

		$this->_paths = array($path);
		$this->_files = array();
		if ($options['recursive']) {
			$this->_findFiles('php');
		} else {
			$this->_files = scandir($path);
			foreach ($this->_files as $i => $file) {
				if (strlen($file) < 5 || substr($file, -4) !== '.php') {
					unset($this->_files[$i]);
				}
			}
		}

		$cwd = getcwd();
		foreach ($this->_files as &$file) {
			$file = $cwd . DS . $file;

			if (strpos(dirname($file), '__') !== false) {
				continue;
			}

			$contents = file_get_contents($file);
			preg_match($options['regex'], $contents, $match);
			if (!$match) {
				continue;
			}

			$class = $match[1];

			if (substr($class, 0, 3) === 'Dbo') {
				$type = 'Dbo';
			} else {
				preg_match('@([A-Z][^A-Z]*)$@', $class, $match);
				if ($match) {
					$type = $match[1];
				} else {
					$type = 'unknown';
				}
			}

			preg_match('@^.*[\\\/]plugins[\\\/](.*?)[\\\/]@', $file, $match);
			$base = $cwd . DS;
			$plugin = false;
			if ($match) {
				$base = $match[0];
				$plugin = $match[1];
			}

			if ($options['checkFolder'] && !empty($this->_map[$type])) {
				$folder = str_replace('/', DS, $this->_map[$type]);
				$new = $base . $folder . DS . $class . '.php';
			} else {
				$new = dirname($file) . DS . $class . '.php';
			}

			if ($file === $new || strpos(dirname($file), dirname($new)) === 0 && basename($file) === basename($new)) {
				continue;
			}

			$dir = dirname($new);
			if (!is_dir($dir)) {
				if (!$this->params['dry-run']) {
					$this->_create($dir);
				}
			}

			$this->out(__d('cake_console', 'Moving %s to %s', $file, $new), 1, Shell::VERBOSE);
			if (!$this->params['dry-run']) {
				$this->_move($file, $new, false);
			}
		}

		$this->_paths = $paths;
	}

	/**
	 * Updates files based on regular expressions.
	 *
	 * @param array $patterns Array of search and replacement patterns.
	 * @return void
	 */
	protected function _filesRegexpUpdate($patterns, $callback = null) {
		$this->_findFiles($this->params['ext']);
		foreach ($this->_files as $file) {
			$this->out(__d('cake_console', 'Updating %s...', $file), 1, Shell::VERBOSE);
			$this->_updateFile($file, $patterns, $callback);
		}
	}

	/**
	 * Checks files based on regular expressions.
	 *
	 * @param array $patterns Array of search patterns.
	 * @return array Matches
	 */
	protected function _filesRegexpCheck($patterns) {
		$this->_findFiles($this->params['ext']);

		$matches = array();
		foreach ($this->_files as $file) {
			$this->out(__d('cake_console', 'Checking %s...', $file), 1, Shell::VERBOSE);
			if ($match = $this->_checkFile($file, $patterns)) {
				$matches[] = array('file' => $file, 'matches' => $match);
			}
		}
		return $matches;
	}

	/**
	 * Searches the paths and finds files based on extension.
	 *
	 * @param string $extensions
	 * @return void
	 */
	protected function _findFiles($extensions = '') {
		$this->_files = array();
		foreach ($this->_paths as $path) {
			if (!is_dir($path)) {
				continue;
			}
			$Iterator = new RegexIterator(
				new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)),
				'/^.+\.(' . $extensions . ')$/i',
				RegexIterator::MATCH
			);
			foreach ($Iterator as $file) {
				//Iterator processes plugins even if not asked to
				if (empty($this->params['plugin'])) {
					$excludes = array('Plugin', 'plugins');
					if (empty($this->params['custom'])) {
						$excludes[] = 'Vendor';
						$excludes[] = 'vendors';
					}

					$isIllegalPluginPath = false;
					foreach ($excludes as $exclude) {
						if (strpos($file, $path . $exclude . DS) === 0) {
							$isIllegalPluginPath = true;
							break;
						}
					}
					if ($isIllegalPluginPath) {
						continue;
					}
				}

				if ($file->isFile()) {
					$this->_files[] = $file->getPathname();
				}
			}
		}
	}

	/**
	 * Update a single file.
	 *
	 * @param string $file The file to update
	 * @param array $patterns The replacement patterns to run.
	 * @return void
	 */
	protected function _updateFile($file, $patterns, $callback = null) {
		$contents = $fileContent = file_get_contents($file);

		foreach ($patterns as $pattern) {
			$this->out(__d('cake_console', ' * Updating %s', $pattern[0]), 1, Shell::VERBOSE);
			if ($callback && is_callable($callback)) {
				$contents = $callback($contents, $pattern);
			} elseif ($callback) {
				$contents = preg_replace_callback($pattern[1], array($this, '_' . $callback), $contents);
			} else {
				$contents = preg_replace($pattern[1], $pattern[2], $contents);
			}
		}

		if (!$this->params['dry-run'] && $contents !== $fileContent) {
			file_put_contents($file, $contents);
		}
		$this->out(__d('cake_console', 'Done updating %s', $file), 1, Shell::VERBOSE);
	}

	/**
	 * Checks a single file.
	 *
	 * @param string $file The file to check
	 * @param array $patterns The matching patterns to run.
	 * @return array Matches
	 */
	protected function _checkFile($file, $patterns) {
		$contents = file_get_contents($file);

		$matches = array();
		foreach ($patterns as $pattern) {
			$this->out(__d('cake_console', ' * Checking %s', $pattern[0]), 1, Shell::VERBOSE);
			preg_match_all($pattern[1], $contents, $match);
			if ($match[0]) {
				$matches[] = array('pattern' => $pattern, 'matches' => $match);
			}
		}

		$this->out(__d('cake_console', 'Done checking %s', $file), 1, Shell::VERBOSE);
		return $matches;
	}

	/**
	 * Get the option parser
	 *
	 * note: the order is important for the "all" task to run smoothly
	 *
	 * @return ConsoleOptionParser
	 */
	public function getOptionParser() {
		$parser = parent::getOptionParser();

		$subcommandParser = array(
			'options' => array(
				'plugin' => array(
					'short' => 'p',
					'help' => __d('cake_console', 'The plugin to update. Only the specified plugin will be updated.'),
					'default' => '',
				),
				'custom' => array(
					'short' => 'c',
					'help' => __d('cake_console', 'Custom path to update recursivly.'),
					'default' => ''
				),
				'ext' => array(
					'short' => 'e',
					'help' => __d('cake_console', 'The extension(s) to search. A pipe delimited list, or a preg_match compatible subpattern'),
					'default' => 'php|ctp|thtml|inc|tpl'
				),
				'git' => array(
					'short' => 'g',
					'help' => __d('cake_console', 'Use git command for moving files around.'),
					'boolean' => true
				),
				'tgit' => array(
					'short' => 't',
					'help' => __d('cake_console', 'Use tortoise git command for moving files around.'),
					'boolean' => true
				),
				'dry-run' => array(
					'short' => 'd',
					'help' => __d('cake_console', 'Dry run the update, no files will actually be modified.'),
					'boolean' => true
				),
				'interactive' => array(
					'short' => 'i',
					'help' => __d('cake_console', 'Interactive commands.'),
					'boolean' => true
				),
			)
		);

		$parser->description(
			__d('cake_console', "A shell to help automate upgrading CakePHP apps to 3.x latest. \n" .
			"Be sure to have a backup of your application before running these commands."
		))->addSubcommand('all', array(
			'help' => __d('cake_console', 'Run all upgrade commands.'),
			'parser' => $subcommandParser
		))
		->addSubcommand('group', array(
			'help' => __d('cake_console', 'Run all defined upgrade commands. Use Configure::write() or params to define.'),
			'parser' => $subcommandParser
		));

		return $parser;
	}

}
