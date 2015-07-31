<?php
App::uses('Folder', 'Utility');
App::uses('UpgradeShell', 'Upgrade.Console/Command');

/**
 * Testing extendability and covering some own upgrade use cases
 *
 * You better ONLY use "packages".
 * The rest is mainly app specific and does probably not help anybody else
 * Use the normal (extended) Upgrade.UpgradeShell instead
 *
 * @license MIT
 * @author Mark Scherer
 */
class MyUpgradeShell extends UpgradeShell {

	/**
	 * Override for except
	 */
	public function all() {
		foreach ($this->OptionParser->subcommands() as $command) {
			$name = $command->name();
			if ($name === 'all' || $name === 'group' || $name === 'except' || $name === 'shim') {
				continue;
			}
			if (!empty($this->params['interactive'])) {
				$runThisCommand = $this->in('Continue with `' . $name . '`?', array('y', 'n', 'q'), 'y');
				if ($runThisCommand === 'q') {
					return $this->error('Aborted');
				}
				if ($runThisCommand !== 'y') {
					$this->out('Skipping ' . $name);
					continue;
				}
			}
			$this->out(__d('cake_console', 'Running %s', $name));
			$this->$name();
		}
	}

	/**
	 * Skip locations as well as any other group/all command
	 */
	public function except() {
		$skip = $this->args;
		$commands = $this->OptionParser->subcommands();

		foreach ($commands as $command) {
			$name = $command->name();
			if (in_array($name, array('all', 'except', 'group', 'locations', 'shim')) || in_array($name, $skip)) {
				continue;
			}
			if (!empty($this->params['interactive'])) {
				$runThisCommand = $this->in('Continue with `' . $name . '`?', array('y', 'n', 'q'), 'y');
				if ($runThisCommand === 'q') {
					return $this->error('Aborted');
				}
				if ($runThisCommand !== 'y') {
					$this->out('Skipping ' . $name);
					continue;
				}
			}
			$this->out(__d('cake_console', 'Running %s', $name));
			$this->$name();
		}
	}

	/**
	 * Correct App::import() to App::uses() and respect new package structure
	 * auto-finds the package name in the Lib folder
	 *
	 * Putting libs in /Lib directly is kind of deprecated. They should belong to a package so to speak.
	 * /Lib/ZodiacLib.php
	 * becomes then (using Misc Package)
	 * /Lib/Misc/ZodiacLib.php
	 *
	 * And instead of using
	 * App::import('Lib', 'PluginName.ZodiacLib');
	 * it is now
	 * App::uses('ZodiacLib', 'Tools.Misc');
	 *
	 * So
	 * a) move your libs to the new packages (or even subpackages)
	 * b) execute this command to correct all App::import and App::uses occurrances in your code
	 */
	public function packages() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']));
		} else {
			$this->_paths = array(APP);
		}

		$patterns = array(
			# Lib
			array(
				'App::import(\'Lib\', \'Plugin.SomeLib\')',
				'|App\:\:import\(\'(Lib)\'\,\s*\'(.*?)\'\)|'
			),
			array(
				'App::uses(\'SomeLib\', \'Plugin.Package\')',
				'|App\:\:uses\(\'(.*?)\'\,\s*\'(.*?\.(.*?))\'\)|'
			),
			# Model
			array(
				'App::import(\'Model\', \'Plugin.SomeModel\')',
				'|App\:\:import\(\'(Model)\'\,\s*\'(.*?)\'\)|'
			),
			//TODO: component, helper, behavior, ...
		);

		$this->_filesRegexpUpdate($patterns, 'libPackage');

		$patterns = array(

		);

		$this->_filesRegexpUpdate($patterns, 'libPackage');
	}

	/**
	 * Fix class names
	 */
	public function classes() {
		# Shell correction
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = (array)$this->_appPath('Console/Command', $this->params['plugin']);
		} else {
			$this->_paths = (array)$this->_appPath('Console/Command');
		}

		$patterns = array(
			array(
				'Shells should extend AppShell instead of core Shell',
				'/\bclass (.*?)Shell extends Shell\s*\{/',
				'class \1Shell extends AppShell {'
			),
			array(
				'Tasks should extend AppShell instead of core Shell',
				'/\bclass (.*?)Task extends Shell\s*\{/',
				'class \1Task extends AppShell {'
			),
		);
		$this->_filesRegexpUpdate($patterns);

		# database.php
		if (empty($this->params['custom']) && empty($this->params['plugin'])) {
			if (file_exists($file = APP . 'Config' . DS . 'database.php')) {
				$content = file_get_contents($file);
				$content = str_replace('extends BASE_CONFIG {', 'extends BaseConfig {', $content);
				file_put_contents($file, $content);
			}
		}

		# lowercase class fix
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = (array)CakePlugin::path($this->params['plugin']);
		} else {
			$this->_paths = array(APP);
		}

		$patterns = array(
			array(
				'class foo to class Foo',
				'/\bclass (\w++)(.*)\{/',
			),
		);

		$this->_filesRegexpUpdate($patterns, 'className');
	}

	protected function _className($matches) {
		//die(returns($matches));
		return 'class ' . ucfirst($matches[1]) . $matches[2] . '{';
	}

	/**
	 * Correct search plugin stuff
	 *
	 * @return void
	 */
	public function search() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = $this->_appPath('Controller', $this->params['plugin']);
		} else {
			$this->_paths = $this->_appPath('Controller');
		}

		$patterns = array(
			array(
				'$this->ModelName->parseCriteria($this->Prg->parsedParams()) correction',
				'/-\>parseCriteria\(\$this-\>passedArgs\)/',
				'->parseCriteria($this->Prg->parsedParams())'
			),
			array(
				'preset var removal',
				'/\s*\tpublic \$presetVars = true;/',
				''
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Extend the basic controller stuff for redirects
	 *
	 * @return void
	 */
	public function controllers2() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = $this->_appPath('Controller', $this->params['plugin']);
		} else {
			$this->_paths = $this->_appPath('Controller');
		}

		$patterns = array(
			array(
				'$this->Common->(\w+)edirect( ... return $this-Common->(\w+)edirect(',
				'/\t\$this-\>Common-\>(\w+)edirect\(/',
				"\t" . 'return $this->Common->\1edirect('
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Tools to Data
	 *
	 * @return void
	 */
	public function tools_data() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']));
		} else {
			$this->_paths = array(APP);
		}

		$names = array(
			'Address', 'City', 'Continent', 'Country', 'CountryProvince', 'County', 'Currency', 'District', 'Language',
			'Location', 'MimeType', 'MimeTypeImage', 'PostalCode', 'Smiley', 'State',
		);

		$patterns = array();
		foreach ($names as $name) {
			$patterns[] = array(
				'App::uses(\'' . $name . '\', \'Tools.Model\')',
				'/\bApp\:\:uses\(\'' . $name . '\', \'Tools.Model\'/',
				'App::uses(\'' . $name . '\', \'Data.Model\''
			);

			$patterns[] = array(
				'Tools.' . $name . ' => ' . 'Data.' . $name,
				'/\bTools\.' . $name . '\b/',
				'Data.' . $name
			);
			$fixtureName = Inflector::underscore($name);
			$patterns[] = array(
				'tools.' . $fixtureName . ' => ' . 'data.' . $fixtureName,
				'/\btools\.' . $fixtureName . '\b/',
				'data.' . $fixtureName
			);

			if (($plural = Inflector::pluralize($name)) === $name) {
				continue;
			}

			$patterns[] = array(
				'Tools.' . $plural . ' => ' . 'Data.' . $plural,
				'/\bTools\.' . $plural . '\b/',
				'Data.' . $plural
			);
			$fixturePlural = Inflector::underscore($plural);
			$patterns[] = array(
				'tools.' . $fixturePlural . ' => ' . 'data.' . strtolower($fixturePlural),
				'/\btools\.' . $fixturePlural . '\b/',
				'data.' . $fixturePlural
			);
		}

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Function to Auth class
	 * @see http://www.dereuromark.de/2012/04/07/auth-inline-authorization-the-easy-way/
	 */
	public function auth() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']));
		} else {
			$this->_paths = array(APP);
		}

		$patterns = array(
			array(
				'hasRole()',
				'/(?<!\:)\bhasRole\((.*)\)/',
				'Auth::hasRole(\1)'
			),
			array(
				'hasRoles()',
				'/(?<!\:)\bhasRoles\((.*)\)/',
				'Auth::hasRoles(\1)'
			),
			array(
				'Auth::hasRole() || Auth::hasRole() || Auth::hasRole() || Auth::hasRole()',
				'/Auth\:\:hasRole\((.*?)\)\s*\|\|\s*Auth\:\:hasRole\((.*?)\)\s*\|\|\s*Auth\:\:hasRole\((.*?)\)\s*\|\|\s*Auth\:\:hasRole\((.*?)\)/',
				'Auth::hasRoles(array(\1, \2, \3, \4))'
			),
			array(
				'Auth::hasRole() || Auth::hasRole() || Auth::hasRole()',
				'/Auth\:\:hasRole\((.*?)\)\s*\|\|\s*Auth\:\:hasRole\((.*?)\)\s*\|\|\s*Auth\:\:hasRole\((.*?)\)/',
				'Auth::hasRoles(array(\1, \2, \3))'
			),
			array(
				'Auth::hasRole() || Auth::hasRole()',
				'/Auth\:\:hasRole\((.*?)\)\s*\|\|\s*Auth\:\:hasRole\((.*?)\)/',
				'Auth::hasRoles(array(\1, \2))'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Move files and folders to their new homes
	 *
	 * Moves folders containing files which cannot necessarily be auto-detected (libs and templates)
	 * and then looks for all php files except vendors, and moves them to where Cake 2.0 expects
	 * to find them.
	 *
	 * @return void
	 */
	public function locations() {
		parent::locations();

		if (!empty($this->params['custom'])) {
			return;
		}

		$cwd = getcwd();
		if (!empty($this->params['plugin'])) {
			chdir(CakePlugin::path($this->params['plugin']));
		}

		if (is_dir('plugins') && !empty($this->params['plugin'])) {
			$Folder = new Folder('plugins');
			list($plugins) = $Folder->read();
			foreach ($plugins as $plugin) {
				chdir($cwd . DS . 'plugins' . DS . $plugin);
				$this->locations();
			}
			$this->_files = array();
			chdir($cwd);
		}
		$moves = array(
			//'config' => 'Config',
			//'Config' . DS . 'schema' => 'Config' . DS . 'Schema',
			'libs' => 'Lib',
			'tests' => 'Test',
			'views' => 'View',
			'models' => 'Model',
			'Model' . DS . 'behaviors' => 'Model' . DS . 'Behavior',
			'Model' . DS . 'datasources' => 'Model' . DS . 'Datasource',
			'Test' . DS . 'cases' => 'Test' . DS . 'Case',
			'Test' . DS . 'fixtures' => 'Test' . DS . 'Fixture',
			//'vendors' . DS . 'shells' . DS . 'templates' => 'Console' . DS . 'Templates',
		);
		foreach ($moves as $old => $new) {
			if (is_dir($old)) {
				$this->out(__d('cake_console', 'Moving %s to %s', $old, $new));
				if (!$this->params['dry-run']) {
					$this->_move($old, $new);
				}
			}
		}
		$this->_moveViewFiles();
		$sourceDirs = array(
			//'.' => array('recursive' => false),
			//'Console',
			'controllers',
			'Controller',
			'Lib' => array('checkFolder' => false),
			'models',
			'Model',
			'tests',
			'Test' => array('regex' => '@class (\S*Test) extends MyCakeTestCase@'),
			'views',
			'View',
			//'vendors/shells',
		);

		$defaultOptions = array(
			'recursive' => true,
			'checkFolder' => true,
			'regex' => '@class (\S*) .*{@i'
		);
		foreach ($sourceDirs as $dir => $options) {
			if (is_numeric($dir)) {
				$dir = $options;
				$options = array();
			}
			$options = array_merge($defaultOptions, $options);
			$this->_movePhpFiles($dir, $options);

			if (!$options['recursive']) {
				continue;
			}
			$Folder = new Folder($dir);
			$files = $Folder->findRecursive();
			if (count($files) === 0 && file_exists($folderPath = $Folder->pwd())) {
				$path = str_replace(APP, DS, $folderPath);
				$this->_delete($folderPath);
				$this->out(__d('cake_console', 'Removing empty folder %s', $path), 1, Shell::VERBOSE);
			}
		}
	}

	/**
	 * MyUpgradeShell::view()
	 *
	 * @return void
	 */
	public function view() {
		$this->params['ext'] = 'ctp';
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = (array)$this->_appPath('View', $this->params['plugin']);
		} else {
			$this->_paths = (array)$this->_appPath('View');
		}

		$patterns = array(
			array(
				'p pagination to div pagination-container',
				'/\<p class="pagination"\>(\s*)(.*)(\s*)\<\/p\>/',
				'<div class="pagination-container">\1\2\3</div>'
			),
			array(
				'div pagination to div pagination-container',
				'/\<div class="pagination"\>(\s*)(.*)(\s*)\<\/div\>/',
				'<div class="pagination-container">\1\2\3</div>'
			),
			array(
				'\'class\'=>\'halfSize\' removal',
				'/\\\'class\\\'\s*\=\>\s*\\\'halfSize\\\',\s*/',
				''
			),
			array(
				'\'class\'=>\'halfSize\' removal',
				'/\s*,\s*\\\'class\\\'\s*\=\>\s*\\\'halfSize\\\'/',
				''
			),
			array(
				'\'class\'=>\'halfSize\' removal',
				'/\\\'class\\\'\s*\=\>\s*\\\'halfSize\\\'/',
				''
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * MyUpgradeShell::view()
	 *
	 * @return void
	 */
	public function datetime() {
		$this->params['ext'] = 'ctp';
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = (array)$this->_appPath('View', $this->params['plugin']);
		} else {
			$this->_paths = (array)$this->_appPath('View');
		}

		$patterns = array(
			array(
				'\'Datetime->niceDate() => localDate()',
				'/\bDatetime-\>niceDate\(([^,)]+)\)/',
				'Datetime->localDate(\1)'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * MyUpgradeShell::helpers()
	 *
	 * @return void
	 */
	public function helpers() {
		$this->params['ext'] = 'ctp';
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = (array)$this->_appPath('View', $this->params['plugin']);
		} else {
			$this->_paths = (array)$this->_appPath('View');
		}

		$patterns = array(
			array(
				'$this->Format->countryIcon( ... $this->Data->countryIcon(',
				'/\$this-\>Format-\>countryIcon\(/',
				'$this->Data->countryIcon('
			),
			array(
				'$this->Format->countryAndProvince( ... $this->Data->countryAndProvince(',
				'/\$this-\>Format-\>countryAndProvince\(/',
				'$this->Data->countryAndProvince('
			),
			array(
				'$this->Format->languageFlag( ... $this->Data->languageFlag(',
				'/\$this-\>Format-\>languageFlag\(/',
				'$this->Data->languageFlag('
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Added MyCakeTestCase
	 *
	 * @return void
	 */
	public function tests() {
		parent::tests();

		$patterns = array(
			array(
				'*TestCase extends CakeTestCase to *Test extends CakeTestCase',
				'/([a-zA-Z]*Test)Case extends CakeTestCase/',
				'\1 extends CakeTestCase'
			),
			array(
				'*TestCase extends MyCakeTestCase to *Test extends MyCakeTestCase',
				'/([a-zA-Z]*Test)Case extends MyCakeTestCase/',
				'\1 extends MyCakeTestCase'
			),
			array(
				'*Case extends CakeTestCase to *Test extends CakeTestCase',
				'/([a-zA-Z]*)Case extends CakeTestCase/',
				'\1Test extends CakeTestCase'
			),
			array(
				'App::import(\'Vendor\', \'MyCakeTestCase\'); => App::uses()',
				'/App::import\(\'Vendor\',\s*\'MyCakeTestCase\'\);/',
				'App::uses(\'MyCakeTestCase\', \'Tools.Lib\');'
			),
			array(
				'App::import(\'Lib\', \'Tools.MyCakeTestCase\'); => App::uses()',
				'/App::import\(\'Lib\',\s*\'Tools\.MyCakeTestCase\'\);/',
				'App::uses(\'MyCakeTestCase\', \'Tools.Lib\');'
			),
			array(
				'App::import(\'Controller\', \'(.*)Controller\'); => App::uses()',
				'/App::import\(\'Controller\',\s*\'(.*)\.(.*)Controller\'\);/',
				'App::uses(\'\2\', \'\1.Controller\');'
			),
			array(
				'App::import(\'Controller\', \'(.*)Controller\'); => App::uses()',
				'/App::import\(\'Controller\',\s*\'(.*)\.(.*)\'\);/',
				'App::uses(\'\2Controller\', \'\1.Controller\');'
			),
			array(
				'App::import(\'Controller\', \'(.*)Controller\'); => App::uses()',
				'/App::import\(\'Controller\',\s*\'(.+)Controller\'\);/',
				'App::uses(\'\1Controller\', \'Controller\');'
			),
			# buggy! Controller itself is also found
			array(
				'App::import(\'Controller\', \'Controller\'); => App::uses()',
				'/App::import\(\'Controller\',\s*\'(.+)\'\);/',
				'App::uses(\'\1Controller\', \'Controller\');'
			),
			# components
			array(
				'App::import(\'Component\', \'(.*).Component\'); => App::uses()',
				'/App::import\(\'Component\',\s*\'(.*)\.(.*)\'\);/',
				'App::uses(\'\2Component\', \'\1.Controller/Component\');'
			),
			array(
				'App::import(\'Component\', \'(.*)Component\'); => App::uses()',
				'/App::import\(\'Component\',\s*\'(.*)\'\);/',
				'App::uses(\'\1Component\', \'Controller/Component\');'
			),
			# helper
			array(
				'App::import(\'Helper\', \'(.*).Helper\'); => App::uses()',
				'/App::import\(\'Helper\',\s*\'(.*)\.(.*)\'\);/',
				'App::uses(\'\2Helper\', \'\1.View/Helper\');'
			),
			array(
				'App::import(\'Helper\', \'(.*)Helper\'); => App::uses()',
				'/App::import\(\'Helper\',\s*\'(.*)\'\);/',
				'App::uses(\'\1Helper\', \'View/Helper\');'
			),
			array(
				'replace $this->Controller->Component->init();',
				'/\$this-\>Controller-\>Component-\>init\((.*)\);/',
				'$this->Controller->constructClasses();
		$this->Controller->startupProcess();'
			),
			/*
			array(
				'class *Test extends CakeTestCase to *Test extends MyCakeTestCase {',
				'/class ([a-zA-Z]*Test) extends CakeTestCase \{/',
				'App::import(\'Lib\', \'Tools.MyCakeTestCase\');'.PHP_EOL.PHP_EOL.'class \1 extends MyCakeTestCase {'
			),
			*/
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Adds parent:: calls to test actions if possible.
	 *
	 * @return void
	 */
	public function tests2() {
		parent::tests();

		$patterns = array(
			'setUp',
			'tearDown'
		);

		foreach ($this->_files as $file) {
			$this->out(__d('cake_console', 'Updating %s...', $file), 1, Shell::VERBOSE);
			//$this->_updateFile($file, $patterns);

			$contents = file_get_contents($file);
			$remember = $contents;

			foreach ($patterns as $pattern) {
				$snippet = 'public function ' . $pattern . '()';
				$snippet2 = 'parent::' . $pattern . '()';

				$search = '/\bpublic function ' . $pattern . '\(\)\s*{/';
				$replace = $snippet . ' {' . PHP_EOL . TB . TB . $snippet2 . ';' . PHP_EOL;

				$this->out(__d('cake_console', ' * Updating %s', $pattern[0]), 1, Shell::VERBOSE);
				if (strpos($contents, $snippet) !== false) {
					if (strpos($contents, $snippet2) === false) {
						$contents = preg_replace($search, $replace, $contents);
					}
				}
			}

			$this->out(__d('cake_console', 'Done updating %s', $file), 1);
			if ($remember !== $contents && !$this->params['dry-run']) {
				file_put_contents($file, $contents);
				//die('E');
			}
		}
	}

	/**
	 * Adjust old templates to new ones
	 */
	public function template() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = $this->_appPath('View', $this->params['plugin']);
		} else {
			$this->_paths = $this->_appPath('View');
		}

		$patterns = array(
			array(
				'Datetime wrapper for created and modified',
				'/echo\s+\$(\w+)\[\'(\w+)\'\]\[\'(created|modified)\'\]/',
				'echo $this->Datetime->niceDate($\1[\'\2\'][\'\3\'])'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Correct session to 2.x standard (static access inside models)
	 */
	public function session() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = $this->_appPath('Model', $this->params['plugin']);
		} else {
			$this->_paths = $this->_appPath('Model');
		}

		$patterns = array(
			array(
				'make session static in models',
				'/\$this-\>Session-\>read\(/',
				'CakeSession::read('
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Custom app stuff
	 */
	public function custom() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']));
		} else {
			$this->_paths = array(APP);
		}

		$patterns = array(
			array(
				'->addHelper(',
				'/-\>addHelper\(/',
				'->loadHelper('
			),
			array(
				'->addComponent(',
				'/-\>addComponent\(/',
				'->loadComponent('
			),
			array(
				'$paginate = array(\'order\'=>array())',
				'/\$paginate\s*=\s*array\(\'order\'\s*=\>\s*array\(\)\)/',
				'$paginate = array()'
			),
			array(
				'Localization.decimal_point',
				'/Localization.decimal_point/',
				'Localization.decimals'
			),
			array(
				'Localization.thousands_point',
				'/Localization.thousands_point/',
				'Localization.thousands'
			),
			array(
				'$this->redirect($this->referer(...), true));',
				'/\$this-\>redirect\(\$this-\>referer\((.*?),\s*true\)\)/i',
				'$this->Common->autoRedirect(\1)'
			),
			array(
				'Datetime::',
				'/\bDatetimeLib\:\:/',
				'TimeLib::'
			),
			array(
				'App::uses(\'DatetimeLib\'',
				'/\bApp\:\:uses\(\'DatetimeLib\'/',
				'App::uses(\'TimeLib\''
			),
		);
		$this->_filesRegexpUpdate($patterns);

		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']) . 'Model' . DS);
		} else {
			$this->_paths = array(APP . 'Model' . DS);
		}

		$patterns = array(
			array(
				'MyLazyModel',
				'/\bMyLazyModel\b/',
				'MyModel'
			),
			array(
				'App::uses(\'MyModel\', \'Tools.Lib\'',
				'/\bApp\:\:uses\(\'MyModel\',\s*\'Tools.Lib\'/',
				'App::uses(\'MyModel\', \'Tools.Model\''
			),
			array(
				'->get() to ->record()',
				'/-\>get\((.+),\s*(.*),\s*(.*)\)/',
				'->record(\1, [\'fields\' => \1, \'contain\' => \2])'
			),
			array(
				'->get() to ->record()',
				'/-\>get\((.+),\s*(.*)\)/',
				'->record(\1, [\'fields\' => \1])'
			),
			array(
				'->get() to ->record()',
				'/-\>get\((.+)\)/',
				'->record(\1)'
			),
		);
		$this->_filesRegexpUpdate($patterns);

		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']) . 'View' . DS);
		} else {
			$this->_paths = array(APP . 'View' . DS);
		}

		$patterns = array(
			array(
				'$this->element(\'pagination\', array(\'plugin\'=>\'tools\'));',
				'/\$this->element\(\'pagination\',\s*array\(\'plugin\'\s*=\>\s*\'tools\'\)\);/',
				'$this->element(\'pagination\', array(), array(\'plugin\'=>\'tools\'));'
			),
			array(
				'App::uses(\'MyHelper\', \'Tools.Lib\'',
				'/\bApp\:\:uses\(\'MyHelper\',\s*\'Tools.Lib\'/',
				'App::uses(\'MyHelper\', \'Tools.View/Helper\''
			),
		);
		$this->_filesRegexpUpdate($patterns);

		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']) . 'Test' . DS);
		} else {
			$this->_paths = array(APP . 'Test' . DS);
		}

		$patterns = array(
			array(
				'App::uses(\'MyCakeTestCase\', \'Tools.Lib\'',
				'/\bApp\:\:uses\(\'MyCakeTestCase\',\s*\'Tools.Lib\'/',
				'App::uses(\'MyCakeTestCase\', \'Tools.TestSuite\''
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Experimental
	 *
	 * @return void
	 * @deprecated Dont use i18n in shells!
	 */
	public function domains() {
		$this->params['ext'] = 'php';
		# only for shell files
		$patterns = array(
			array(
				'add console domain to __()',
				'/__\(\'(.*?)\'/',
				'__d(\'console\', \'\1\''
			),
			array(
				'add console domain to __()',
				'/__c\(\'(.*?)\'/',
				'__dc(\'console\', \'\1\''
			),
			array(
				'add console domain to __()',
				'/__n\(\'(.*?)\'/',
				'__dn(\'console\', \'\1\''
			),

		);
		$this->_filesRegexpUpdate($patterns);

		$this->params['ext'] = 'php|ctp';
		# only for non-shell files
		$patterns = array(
			array(
				'remove cake domain from __()',
				'/__d\(\'(.*?)\',\s*/',
				'__('
			),
			array(
				'remove cake domain from __()',
				'/__dc\(\'(.*?)\',\s*/',
				'__c('
			),
			array(
				'remove cake domain from __()',
				'/__dn\(\'(.*?)\',\s*/',
				'__n('
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Correct flash messages
	 *
	 * @return void
	 */
	public function flash() {
		$this->params['ext'] = 'php';

		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']));
		} else {
			$this->_paths = array(APP);
		}

		$patterns = array(
			array(
				'$this->Session->setFlash(...)',
				'/-\>Session-\>setFlash\((.*)\)/',
				'->Flash->message(\1)'
			),
			array(
				'$this->Flash->message(...)',
				'/-\>Common-\>flashMessage\(__\(\'Invalid (.*)\'\)\)/',
				'->Flash->error(__(\'Invalid \1\'))'
			),
			array(
				'$this->Flash->message(...)',
				'/-\>Common-\>flashMessage\(__\(\'(.*) has been saved\'\)\)/',
				'->Flash->success(__(\'\1 has been saved\'))'
			),
			array(
				'$this->Flash->message(...)',
				'/-\>Common-\>flashMessage\(__\(\'(.*) could not be saved(.*)\'\)\)/',
				'->Flash->error(__(\'\1 could not be saved\2\'))'
			),
			# old ones to new sugar
			array(
				'$this->Flash->message(..., type) ... $this->Flash->type(...)',
				'/-\>Flash-\>message\((.+),\s*\'(error|warning|success|info)\'\)/',
				'->Flash->\2(\1)'
			),
			# tmp to qickly find unmatching ones
			array(
				'$this->Flash->message(...)',
				'/-\>Common-\>flashMessage\(__\(\'(.*)\'\)\)/',
				'->Flash->xxxxx(__(\'\1\'))'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * MyUpgradeShell::doc_blocks()
	 *
	 * - remove xxxx-xx-xx xx in favor of just the return value and version control
	 *
	 * @return void
	 */
	public function doc_blocks() {
		$this->params['ext'] = 'php';
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']));
		} else {
			$this->_paths = array(APP);
		}

		$patterns = array(
			# tmp to qickly find unmatching ones
			array(
				'* xxxx-xx-xx xx ... removal',
				'/\*\s*[0-9]{4}-[0-9]{2}-[0-9]{2}\s+[a-z]+\s*\*\//i',
				'*/'
			),
			// Created/Updated: 18.10.2010, 18:41:36
			array(
				'* Created/Updated removal',
				'/\* (Created|Updated)\: [0-9\.\,\-\: ]+/i',
				'*'
			),
			array(
				'@static removal',
				'/\* \@static\s*\*/i',
				'*'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Do run this ONLY if you actually use the https://github.com/dereuromark/cakephp-shim Shim plugin
	 *
	 * @return void
	 */
	public function shim() {
		$this->params['ext'] = 'php|ctp';
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']) . 'View' . DS);
		} else {
			$this->_paths = array(APP . 'View' . DS);
		}

		$patterns = array(
			array(
				'$this->Url->build() to $this->Url->build()',
				'/->Html->url\(/',
				'->Url->build('
			),
		);
		$this->_filesRegexpUpdate($patterns);
		$this->out('Done.');
	}

	/**
	 * Do run this ONLY if you actually start upgrading to 3.x
	 *
	 * @return void
	 */
	public function cake3() {
		// Replace composer stuff
		$path = APP;
		$file = $path . 'composer.json';
		if (!file_exists($file)) {
			return $this->errror('Cannot find composer.json');
		}

		$content = file_get_contents($file);

		// Basically the same as "composer require cakephp/cakephp:3.0.*
		$content = preg_replace('#"cakephp/cakephp"\s*:\s*"2\..*"#', '"cakephp/cakephp": "3.0.*"', $content);

		$content = preg_replace('#"markstory/asset_compress"\s*\:\s*"dev-master"#', '"markstory/asset_compress": "3.0.*-dev"', $content);
		$content = preg_replace('#"cakedc/search"\s*\:\s*"dev-master"#', '"cakedc/search": "3.0.*-dev"', $content);

		file_put_contents($file, $content);

		$this->out('Done. Also add "autoload" and "autoload-dev". Then run "composer update".');
	}

	/**
	 * out() + log if desired
	 */
	public function out($message = null, $newlines = 1, $level = Shell::NORMAL) {
		if (!empty($this->params['log'])) {
			$file = TMP . 'log.txt';
			if (!empty($this->appendLogFile)) {
				$flag = FILE_APPEND;
			} else {
				$flag = null;
				$this->appendLogFile = true;
			}
			file_put_contents($file, trim($message) . str_repeat(PHP_EOL, $newlines), $flag);
		}
		return parent::out($message, $newlines, $level);
	}

	/**
	 * App::import('Lib', 'PluginName.SomeLib') => App::uses('SomeLib', 'PluginName.NewPackageName')
	 * App::uses('SomeLib', 'PluginName.OldPackageName') => App::uses('SomeLib', 'PluginName.NewPackageName')
	 */
	protected function _libPackage($matches) {
		//pr($matches);
		if (!isset($this->Lib)) {
			App::uses('Lib', 'Upgrade.Lib');
			$this->Lib = new Lib();
		}

		if (count($matches) < 4) {
			# App::import
			$type = $matches[1];
			list($plugin, $name) = pluginSplit($matches[2]);
			$package = $this->Lib->match($matches[2], $type);
			if (!$package) {
				return $matches[0];
			}
		} else {
			//echo(returns($matches));
			# App::uses
			$package = $matches[2];
			$packageName = $matches[3];
			$name = $matches[1];
			list($plugin, $type) = pluginSplit($package, true);
			$package = $this->Lib->match($plugin . $name, 'Lib');
			if (!$package) {
				$package = $matches[2];
			}
		}
		return 'App::uses(\'' . $name . '\', \'' . $package . '\')';
	}

	/**
	 * Get the option parser
	 *
	 * @return ConsoleOptionParser
	 */
	public function getOptionParser() {
		$subcommandParser = array(
			'options' => array(
				'plugin' => array(
					'short' => 'p',
					'help' => __d('cake_console', 'The plugin to update. Only the specified plugin will be updated.'),
					'default' => ''
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
				'svn' => array(
					'short' => 's',
					'help' => __d('cake_console', 'Use svn command for moving files around.'),
					'boolean' => true
				),
				'dry-run' => array(
					'short' => 'd',
					'help' => __d('cake_console', 'Dry run the update, no files will actually be modified.'),
					'boolean' => true
				),
				'log' => array(
					'short' => 'l',
					'help' => __d('cake_console', 'Log all ouput to file log.txt in TMP dir'),
					'boolean' => true
				),
				'interactive' => array(
					'short' => 'i',
					'help' => 'Run it interactively and ask before each each command',
					'boolean' => true
				),
			)
		);

		return parent::getOptionParser()
			->addSubcommand('search', array(
				'help' => __d('cake_console', 'search plugin corrections'),
				'parser' => $subcommandParser
			))
			->addSubcommand('except', array(
				'help' => __d('cake_console', 'all except locations and defined commands'),
				'parser' => $subcommandParser
			))
			->addSubcommand('custom', array(
				'help' => __d('cake_console', 'custom'),
				'parser' => $subcommandParser
			))
			->addSubcommand('controllers2', array(
				'help' => __d('cake_console', 'more controller stuff'),
				'parser' => $subcommandParser
			))
			->addSubcommand('auth', array(
				'help' => __d('cake_console', 'auth'),
				'parser' => $subcommandParser
			))
			->addSubcommand('packages', array(
				'help' => __d('cake_console', 'lib packages'),
				'parser' => $subcommandParser
			))
			->addSubcommand('session', array(
				'help' => __d('cake_console', 'make sesssion static'),
				'parser' => $subcommandParser
			))
			->addSubcommand('template', array(
				'help' => __d('cake_console', 'adjust baked templates'),
				'parser' => $subcommandParser
			))
			->addSubcommand('view', array(
				'help' => __d('cake_console', 'adjust view stuff'),
				'parser' => $subcommandParser
			))
			->addSubcommand('helpers', array(
				'help' => __d('cake_console', 'adjust helpers stuff'),
				'parser' => $subcommandParser
			))
			->addSubcommand('tests2', array(
				'help' => __d('cake_console', 'fix test methods'),
				'parser' => $subcommandParser
			))
			->addSubcommand('flash', array(
				'help' => __d('cake_console', 'flash messages'),
				'parser' => $subcommandParser
			))
			->addSubcommand('domains', array(
				'help' => __d('cake_console', '__() domains'),
				'parser' => $subcommandParser
			))
			->addSubcommand('classes', array(
				'help' => __d('cake_console', 'class names'),
				'parser' => $subcommandParser
			))
			->addSubcommand('doc_blocks', array(
				'help' => __d('cake_console', 'update/correct doc blocks'),
				'parser' => $subcommandParser
			))
			->addSubcommand('tools_data', array(
				'help' => __d('cake_console', 'Tools plugin to Data plugin stuff'),
				'parser' => $subcommandParser
			))
			->addSubcommand('datetime', array(
				'help' => __d('cake_console', 'niceDate() to localDate()'),
				'parser' => $subcommandParser
			))
			->addSubcommand('shim', array(
				'help' => 'Upgrade to Shim plugin now.',
				'parser' => $subcommandParser
			))
			->addSubcommand('cake3', array(
				'help' => 'Upgrade to Cake3 now - last command of the 2.x shell',
				'parser' => $subcommandParser
			));
	}
}
