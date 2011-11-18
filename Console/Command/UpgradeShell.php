<?php
/**
 * Upgrade Shell
 *
 * PHP 5
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright 2005-2011, Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @package       Cake.Console.Command
 * @since         CakePHP(tm) v 2.0
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

App::uses('Folder', 'Utility');

/**
 * A shell class to help developers upgrade applications to CakePHP 2.0
 *
 * @package       Cake.Console.Command
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
	}
	
/**
 * @param string %type (svn, git, ...)
 * @return boolean $success
 */
	protected function _isType($type) {
		if (is_dir('.'.$type)) {
			return true;
		}
		//check if parent folders contain .type
		$path = APP;
		while ($path != ($newPath = dirname($path))) {
			$path = $newPath;
			if (is_dir($path . DS . '.'.$type)) {
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
		foreach($this->OptionParser->subcommands() as $command) {
			$name = $command->name();
			if ($name === 'all' || $name === 'group') {
				continue;
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
			$this->error(__d('cake_console', 'No group found. Please use args or Configure to define groups.'));
		}
		foreach ($commands as $command) {
			if (!in_array($command, $subCommandList)) {
				$this->err(__d('cake_console', 'Invalid command \'%s\' - skipping', $command));
				continue;
			}
		}
		foreach($subCommands as $command) {
			$name = $command->name();
			if ($name === 'all' || $name === 'group' || !in_array($name, $commands)) {
				continue;
			}
			$this->out(__d('cake_console', 'Running %s', $name));
			$this->$name();
		}
	}

/**
 * Update tests.
 *
 * - Update tests class names to FooTest rather than FooTestCase.
 *
 * @return void
 */
	public function tests() {
		$this->_paths = array(APP . 'Test' . DS, APP . 'tests' . DS);
		if (!empty($this->params['plugin'])) {
			$pluginpath = App::pluginPath($this->params['plugin']);
			$this->_paths = array($pluginpath . 'Test' . DS, $pluginpath . 'tests' . DS);
		}
		$patterns = array(
			array(
				'*TestCase extends CakeTestCase to *Test extends CakeTestCase',
				'/([a-zA-Z]*Test)Case extends CakeTestCase/',
				'\1 extends CakeTestCase'
			),
			array(
				'function startCase() to function startTest()',
				'/\bfunction startCase\(\)/i',
				'function startTest()'
			),
			array(
				'new (.*)Helper() to new (.*)Helper(new View(null))',
				'/\bnew (.*)Helper\(\)/i',
				'new \1Helper(new View(null))'
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
		$cwd = getcwd();

		if (!empty($this->params['plugin'])) {
			chdir(App::pluginPath($this->params['plugin']));
		}

		if (is_dir('plugins') && !empty($this->params['plugin'])) {
			$Folder = new Folder('plugins');
			list($plugins) = $Folder->read();
			foreach($plugins as $plugin) {
				chdir($cwd . DS . 'plugins' . DS . $plugin);
				$this->locations();
			}
			$this->_files = array();
			chdir($cwd);
		}
		$moves = array(
			'config' => 'Config',
			'Config' . DS . 'schema' => 'Config' . DS . 'Schema',
			'libs' => 'Lib',
			'tests' => 'Test',
			'views' => 'View',
			'models' => 'Model',
			'Model' . DS . 'behaviors' => 'Model' . DS . 'Behavior',
			'Model' . DS . 'datasources' => 'Model' . DS . 'Datasource',
			'Test' . DS . 'cases' => 'Test' . DS . 'Case',
			'Test' . DS . 'fixtures' => 'Test' . DS . 'Fixture',
			'vendors' . DS . 'shells' . DS . 'templates' => 'Console' . DS . 'Templates',
		);
		foreach($moves as $old => $new) {
			if (is_dir($old)) {
				$this->out(__d('cake_console', 'Moving %s to %s', $old, $new));
				if (!$this->params['dry-run']) {
					$this->_move($old, $new);
				}
			}
		}
		$this->_moveViewFiles();
		$sourceDirs = array(
			'.' => array('recursive' => false),
			'Console',
			'controllers',
			'Controller',
			'Lib' => array('checkFolder' => false),
			'models',
			'Model',
			'tests',
			'Test' => array('regex' => '@class (\S*Test) extends CakeTestCase@'),
			'views',
			'View',
			'vendors/shells',
		);

		$defaultOptions = array(
			'recursive' => true,
			'checkFolder' => true,
			'regex' => '@class (\S*) .*{@i'
		);
		foreach($sourceDirs as $dir => $options) {
			if (is_numeric($dir)) {
				$dir = $options;
				$options = array();
			}
			$options = array_merge($defaultOptions, $options);
			$this->_movePhpFiles($dir, $options);
			
			if(!$options['recursive']) {
				continue;
			}
			$Folder = new Folder($dir);
			$files = $Folder->findRecursive();
			if (count($files) === 0 && file_exists($folderPath = $Folder->pwd())) {
				$path = str_replace(APP, DS, $folderPath);
				$this->_delete($folderPath);
				$this->out(__d('cake_console', 'Removing empty folder %s', $path));
			}
		}
	}
	

/**
 * Update helpers.
 *
 * - Converts helpers usage to new format.
 *
 * @return void
 */
	public function helpers() {
		$this->_paths = array_diff(App::path('views'), App::core('views'));

		if (!empty($this->params['plugin'])) {
			$this->_paths = array(App::pluginPath($this->params['plugin']) . 'views' . DS);
		}

		$patterns = array();
		App::build(array(
			'View/Helper' => App::core('View/Helper'),
		), App::APPEND);
		$helpers = App::objects('helper');
		$plugins = App::objects('plugin');
		$pluginHelpers = array();
		foreach ($plugins as $plugin) {
			$pluginHelpers = array_merge(
				$pluginHelpers,
				App::objects('helper', App::pluginPath($plugin) . DS . 'views' . DS . 'helpers' . DS, false)
			);
		}
		$helpers = array_merge($pluginHelpers, $helpers);
		foreach ($helpers as $helper) {
			$helper = preg_replace('/Helper$/', '', $helper);
			$oldHelper = strtolower(substr($helper, 0, 1)).substr($helper, 1);
			$patterns[] = array(
				"\${$oldHelper} to \$this->{$helper}",
				"/\\\${$oldHelper}->/",
				"\\\$this->{$helper}->"
			);
		}

		$this->_filesRegexpUpdate($patterns);
	}

/**
 * Update i18n.
 *
 * - Removes extra true param.
 * - Add the echo to __*() calls that didn't need them before.
 *
 * @return void
 */
	public function i18n() {
		$this->_paths = array(
			APP
		);
		if (!empty($this->params['plugin'])) {
			$this->_paths = array(App::pluginPath($this->params['plugin']));
		}

		$patterns = array(
			array(
				'<?php __*(*) to <?php echo __*(*)',
				'/<\?php\s*(__[a-z]*\(.*?\))/',
				'<?php echo \1'
			),
			array(
				'<?php __*(*, true) to <?php echo __*()',
				'/<\?php\s*(__[a-z]*\(.*?)(,\s*true)(\))/',
				'<?php echo \1\3'
			),
			array('__*(*, true) to __*(*)', '/(__[a-z]*\(.*?)(,\s*true)(\))/', '\1\3')
		);

		$this->_filesRegexpUpdate($patterns);
	}

/**
 * Upgrade the removed basics functions.
 *
 * - a(*) -> array(*)
 * - e(*) -> echo *
 * - ife(*, *, *) -> !empty(*) ? * : *
 * - a(*) -> array(*)
 * - r(*, *, *) -> str_replace(*, *, *)
 * - up(*) -> strtoupper(*)
 * - low(*, *, *) -> strtolower(*)
 * - getMicrotime() -> microtime(true)
 *
 * @return void
 */
	public function basics() {
		$this->_paths = array(
			APP
		);
		if (!empty($this->params['plugin'])) {
			$this->_paths = array(App::pluginPath($this->params['plugin']));
		}
		$patterns = array(
			array(
				'a(*) -> array(*)',
				'/\ba\((.*)\)/',
				'array(\1)'
			),
			array(
				'e(*) -> echo *',
				'/\be\((.*)\)/',
				'echo \1'
			),
			array(
				'ife(*, *, *) -> !empty(*) ? * : *',
				'/ife\((.*), (.*), (.*)\)/',
				'!empty(\1) ? \2 : \3'
			),
			array(
				'r(*, *, *) -> str_replace(*, *, *)',
				'/\br\(/',
				'str_replace('
			),
			array(
				'up(*) -> strtoupper(*)',
				'/\bup\(/',
				'strtoupper('
			),
			array(
				'low(*) -> strtolower(*)',
				'/\blow\(/',
				'strtolower('
			),
			array(
				'getMicrotime() -> microtime(true)',
				'/getMicrotime\(\)/',
				'microtime(true)'
			),
			array(
				'$TIME_START -> $_SERVER[\'REQUEST_TIME\']',
				'/\$TIME_START/',
				'$_SERVER[\'REQUEST_TIME\']'
			),
			array(
				'var $ to public $',
				'/\bvar \$/i',
				'public $'
			),
			array(
				'private $ to protected $',
				'/\bprivate \$/i',
				'protected $'
			),
			array(
				'CakeRequest::clientIP() to CakeRequest::clientIP()',
				'/\bRequestHandlerComponent\:\:getClientIP\(\)/i',
				'CakeRequest::clientIP()'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

/**
 * Remove name (lib, controller, model, view, component, behavior, helper, fixture)
 *
 * @return void
 */
	public function name() {
		$libs = App::path('Lib');
		$views = App::path('views');
		$controllers = App::path('controllers');
		$components = App::path('components');
		$models = App::path('models');
		$helpers = App::path('helpers');
		$behaviors = App::path('behaviors');
		
		$this->_paths = array_merge($libs, $views, $controllers, $components, $models, $helpers, $behaviors);
		$this->_paths[] = TESTS . 'Fixture' . DS;
		
		if (!empty($this->params['plugin'])) {
			$pluginPath = App::pluginPath($this->params['plugin']);
			$this->_paths = array(
				$pluginPath . 'Lib' . DS,
				$pluginPath . 'Controller' . DS,
				$pluginPath . 'Controller' . DS . 'Component' .DS,
				$pluginPath . 'View' . DS,
				$pluginPath . 'View' . DS . 'Helper' . DS,
				$pluginPath . 'Model' . DS,
				$pluginPath . 'Model' . DS . 'Behavior' . DS,
				$pluginPath . 'Test' . DS . 'Fixture' . DS,
				$pluginPath . 'libs' . DS,
				$pluginPath . 'controllers' . DS,
				$pluginPath . 'controllers' . DS . 'components' .DS,
				$pluginPath . 'views' . DS,
				$pluginPath . 'views' . DS . 'helpers' .DS,
				$pluginPath . 'models' . DS,
				$pluginPath . 'models' . DS . 'behaviors' . DS,
				$pluginPath . 'tests' . DS . 'fixtures' . DS,
			);
		}
		
		$patterns = array(
			array(
				'remove var $name = ...;',
				'/\bvar\s*\$name\s*=\s*(.*);/',
				''
			),
			array(
				'remove public $name = ...;',
				'/\bpublic\s*\$name\s*=\s*(.*);/',
				''
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}
	

/**
 * Update the properties moved to CakeRequest.
 *
 * @return void
 */
	public function request() {
		$views = App::path('views');
		$controllers = App::path('controllers');
		$components = App::path('components');
	
		$this->_paths = array_merge($views, $controllers, $components);

		if (!empty($this->params['plugin'])) {
			$pluginPath = App::pluginPath($this->params['plugin']);
			$this->_paths = array(
				$pluginPath . 'Controller' . DS,
				$pluginPath . 'Controller' . DS . 'Component' .DS,
				$pluginPath . 'View' . DS,
				$pluginPath . 'controllers' . DS,
				$pluginPath . 'controllers' . DS . 'components' .DS,
				$pluginPath . 'views' . DS,
			);
		}
		$patterns = array(
			array(
				'$this->data -> $this->request->data',
				'/(\$this-\>data\b(?!\())/',
				'$this->request->data'
			),
			array(
				'$this->Controller->data -> $this->Controller->request->data',
				'/(\$this->Controller-\>data\b(?!\())/',
				'$this->Controller->request->data'
			),
			# TEMP ONLY!!!
			array(
				'->request->params[\'url\'][\'url\'] -> ->request->url',
				'/-\>request-\>params\[\'url\'\]\[\'url\'\]/',
				'->request->url'
			),
			array(
				'$this->params[\'url\'][\'url\'] -> $this->request->url',
				'/\$this-\>params\[\'url\'\]\[\'url\'\]/',
				'$this->request->url'
			),
			array(
				'$this->Controller->params[\'url\'][\'url\'] -> $this->Controller->request->url',
				'/\$this-\>Controller->params\[\'url\'\]\[\'url\'\]/',
				'$this->Controller->request->url'
			),
			# TEMP ONLY!!!
			array(
				'->request->params[\'url\'][*] -> ->query[*]',
				'/-\>request-\>params\[\'url\'\]\[\'(.*)\'\]/',
				'->request->query[\'\1\']',
			),
			array(
				'->params[\'url\'][*] -> ->request->query[*]',
				'/-\>params\[\'url\'\]\[\'(.*)\'\]/',
				'->request->query[\'\1\']',
			),
			array(
				'->request->request->params -> ->request->query',
				'/-\>request-\>request-\>params\b/',
				'->request->query',
			),
			array(
				'$this->params -> $this->request->params',
				'/(\$this->params\b(?!\())/',
				'$this->request->params'
			),
			array(
				'$this->Controller->params -> $this->Controller->request->params',
				'/(\$this->Controller-\>params\b(?!\())/',
				'$this->Controller->request->params'
			),
			array(
				'$this->webroot -> $this->request->webroot',
				'/(\$this->webroot\b(?!\())/',
				'$this->request->webroot'
			),
			array(
				'$this->base -> $this->request->base',
				'/(\$this->base\b(?!\())/',
				'$this->request->base'
			),
			array(
				'$this->here -> $this->request->here',
				'/(\$this->here\b(?!\())/',
				'$this->request->here'
			),
			array(
				'$this->action -> $this->request->action',
				'/(\$this->action\b(?!\())/',
				'$this->request->action'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}


/**
 * Add new cake routes
 * 
 * @return void
 */
	public function routes() {
		$file = APP.'Config'.DS.'routes.php';
		if (!file_exists($file)) {
			$this->out(__d('cake_console', 'no routes.php found in Config - abort adding missing routes'));
			return;
		}
		$content = file_get_contents($file);
		if (strpos($content, 'CakePlugin::routes()') === false) {
			$this->out(__d('cake_console', 'adding 2.0 plugin routes...'));
			$content .= PHP_EOL.PHP_EOL.'CakePlugin::routes();';
			$changes = true;
		}
		if (strpos($content, 'require CAKE . \'Config\' . DS . \'routes.php\'') === false) {
			$this->out(__d('cake_console', 'adding new 2.0 default routes...'));
			$content .= PHP_EOL.PHP_EOL.'/**
* Load the CakePHP default routes. Remove this if you do not want to use
* the built-in default routes.
*/
require CAKE . \'Config\' . DS . \'routes.php\';';
			$changes = true;
		}
		if (!empty($changes)) {
			file_put_contents($file, $content);
		}
	}


/**
 * Update Configure::read() calls with no params.
 *
 * @return void
 */
	public function configure() {
		$this->_paths = array(
			APP
		);
		if (!empty($this->params['plugin'])) {
			$this->_paths = array(App::pluginPath($this->params['plugin']));
		}
		$patterns = array(
			array(
				"Configure::read() -> Configure::read('debug')",
				'/Configure::read\(\)/',
				'Configure::read(\'debug\')'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

/**
 * constants
 *
 * @return void
 */
	public function constants() {
		$this->_paths = array(
			APP
		);
		if (!empty($this->params['plugin'])) {
			$this->_paths = array(App::pluginPath($this->params['plugin']));
		}
		$patterns = array(
			array(
				"LIBS -> CAKE",
				'/\bLIBS\b/',
				'CAKE'
			),
			array(
				"CONFIGS -> APP . 'Config' . DS",
				'/\bCONFIGS\b/',
				'APP . \'Config\' . DS'
			),
			array(
				"CONTROLLERS -> APP . 'Controller' . DS",
				'/\bCONTROLLERS\b/',
				'APP . \'Controller\' . DS'
			),
			array(
				"COMPONENTS -> APP . 'Controller' . DS . 'Component' . DS",
				'/\bCOMPONENTS\b/',
				'APP . \'Controller\' . DS . \'Component\''
			),
			array(
				"MODELS -> APP . 'Model' . DS",
				'/\bMODELS\b/',
				'APP . \'Model\' . DS'
			),
			array(
				"BEHAVIORS -> APP . 'Model' . DS . 'Behavior' . DS",
				'/\bBEHAVIORS\b/',
				'APP . \'Model\' . DS . \'Behavior\' . DS'
			),
			array(
				"VIEWS -> APP . 'View' . DS",
				'/\bVIEWS\b/',
				'APP . \'View\' . DS'
			),
			array(
				"HELPERS -> APP . 'View' . DS . 'Helper' . DS",
				'/\bHELPERS\b/',
				'APP . \'View\' . DS . \'Helper\' . DS'
			),
			array(
				"LAYOUTS -> APP . 'View' . DS . 'Layouts' . DS",
				'/\bLAYOUTS\b/',
				'APP . \'View\' . DS . \'Layouts\' . DS'
			),
			array(
				"ELEMENTS -> APP . 'View' . DS . 'Elements' . DS",
				'/\bELEMENTS\b/',
				'APP . \'View\' . DS . \'Elements\' . DS'
			),
			array(
				"CONSOLE_LIBS -> CAKE . 'Console' . DS",
				'/\bCONSOLE_LIBS\b/',
				'CAKE . \'Console\' . DS'
			),
			array(
				"CAKE_TESTS_LIB -> CAKE . 'TestSuite' . DS",
				'/\bCAKE_TESTS_LIB\b/',
				'CAKE . \'TestSuite\' . DS'
			),
			array(
				"CAKE_TESTS -> CAKE . 'Test' . DS",
				'/\bCAKE_TESTS\b/',
				'CAKE . \'Test\' . DS'
			)
		);
		$this->_filesRegexpUpdate($patterns);
	}
	
/**
 * Update controllers.
 *
 * - Update controller stuff
 *
 * @return void
 */
	public function controllers() {
		$this->_paths = App::Path('Controller');
		if (!empty($this->params['plugin'])) {
			$this->_paths = App::Path('Controller', $this->params['plugin']);
		}
		$patterns = array(
			array(
				'$this->viewPath = \'elements\' to $this->viewPath = \'Elements\'',
				'/\$this-\>viewPath\s*=\s*\'elements\'/i',
				'$this->viewPath = \'Elements\''
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}
	
/**
 * Update components.
 *
 * - Make components that extend Object to extend Component.
 *
 * @return void
 */
	public function components() {
		$this->_paths = App::Path('Controller/Component');
		if (!empty($this->params['plugin'])) {
			$this->_paths = App::Path('Controller/Component', $this->params['plugin']);
		}
		$patterns = array(
			array(
				'*Component extends Object to *Component extends Component',
				'/([a-zA-Z]*Component extends) Object/',
				'\1 Component'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

/**
 * Replace cakeError with built-in exceptions.
 * NOTE: this ignores calls where you've passed your own secondary parameters to cakeError().
 * @return void
 */
	public function exceptions() {
		$controllers = array_diff(App::path('controllers'), App::core('controllers'), array(APP));
		$components = array_diff(App::path('components'), App::core('components'));

		$this->_paths = array_merge($controllers, $components);

		if (!empty($this->params['plugin'])) {
			$pluginPath = App::pluginPath($this->params['plugin']);
			$this->_paths = array(
				$pluginPath . 'controllers' . DS,
				$pluginPath . 'controllers' . DS . 'components' .DS,
			);
		}
		$patterns = array(
			array(
				'$this->cakeError("error400") -> throw new BadRequestException()',
				'/(\$this->cakeError\(["\']error400["\']\));/',
				'throw new BadRequestException();'
			),
			array(
				'$this->cakeError("error404") -> throw new NotFoundException()',
				'/(\$this->cakeError\(["\']error404["\']\));/',
				'throw new NotFoundException();'
			),
			array(
				'$this->cakeError("error500") -> throw new InternalErrorException()',
				'/(\$this->cakeError\(["\']error500["\']\));/',
				'throw new InternalErrorException();'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}	

/**
 * Update views.
 *
 * - Update view stuff.
 *
 * @return void
 */
	public function views() {
		$this->_paths = array(
			APP . 'View' . DS,
			APP . 'views' . DS
		);
		if (!empty($this->params['plugin'])) {
			$path = App::pluginPath($this->params['plugin']);
			$this->_paths = array($path . 'View' . DS, $path . 'views' . DS);
		}
		$patterns = array(
			array(
				'<cake:nocache> to <!--nocache-->',
				'/\<cake\:nocache\>/',
				'<!--nocache-->'
			),
			array(
				'</cake:nocache> to <!--/nocache-->',
				'/\<\/cake\:nocache\>/',
				'<!--/nocache-->'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

/**
 * Update webroot.
 *
 * - Replaces index.php and test.php in webroot.
 * - Update .htaccess
 *
 * @return void
 */
	public function webroot() {
		if (!empty($this->params['plugin'])) {
			return;
		}
		
		$patterns = array(
			array(
				'index.php?url=$1 => index.php?/$1',
				'/index.php\?url=\$1/',
				'index.php?/\\$1'
			),
		);
		
		$to = APP . 'webroot' . DS;
		$from = CAKE . 'Console'. DS . 'Templates' . DS . 'skel' . DS . 'webroot' . DS;
		$file = $to.'.htaccess';
		if (file_exists($file)) {
			$this->_updateFile($file, $patterns);
			$this->out(__d('cake_console', '%s updated', '.htaccess'));
		}	
		
		$files = array('index.php', 'test.php');
		foreach ($files as $file) {
			copy($from . $file, $to . $file);
			$this->out(__d('cake_console', '%s updated', $file));
		}
	}

/**
 * Update legacy stuff.
 *
 * - Replaces App::import() with App::uses() - mainly Utility classes.
 *
 * @return void
 */
	public function legacy() {
		$this->_paths = array(
			APP
		);
		if (!empty($this->params['plugin'])) {
			$this->_paths = array(App::pluginPath($this->params['plugin']));
		}

		$patterns = array(
			array(
				'App::import(\'Core\', \'Folder\') to App::uses(\'Folder\', \'Utility\')',
				'/App\:\:import\(\'Core\', \'Folder\'\)/',
				'App::uses(\'Folder\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'File\') to App::uses(\'Folder\', \'File\')',
				'/App\:\:import\(\'Core\', \'File\'\)/',
				'App::uses(\'File\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'HttpSocket\') to App::uses(\'HttpSocket\', \'Network/Http\')',
				'/App\:\:import\(\'Core\', \'HttpSocket\'\)/',
				'App::uses(\'HttpSocket\', \'Network/Http\')'
			),
			array(
				'App::import(\'Core\', \'Inflector\') to App::uses(\'Inflector\', \'Utility\')',
				'/App\:\:import\(\'Core\', \'Inflector\'\)/',
				'App::uses(\'Inflector\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Validation\') to App::uses(\'Validation\', \'Utility\')',
				'/App\:\:import\(\'Core\', \'Validation\'\)/',
				'App::uses(\'Validation\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Security\') to App::uses(\'Security\', \'Utility\')',
				'/App\:\:import\(\'Core\', \'Security\'\)/',
				'App::uses(\'Security\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Xml\') to App::uses(\'Xml\', \'Utility\')',
				'/App\:\:import\(\'Core\', \'Xml\'\)/',
				'App::uses(\'Xml\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Router\') to App::uses(\'Router\', \'Routing\')',
				'/App\:\:import\(\'Core\', \'Router\'\)/',
				'App::uses(\'Router\', \'Routing\')'
			),
		);

		$this->_filesRegexpUpdate($patterns);		
	}
	

/**
 * Update database config file.
 *
 * - Update database.php (replace driver with datasource).
 *
 * @return void
 */
	public function database() {
		if (!empty($this->params['plugin'])) {
			return;
		}
		$file = APP.'Config'.DS.'database.php';
		if (!file_exists($file)) {
			return;
		}
		$content = file_get_contents($file);
		$content = explode("\n", $content);
		$changes = false;
		foreach ($content as $line => $row) {
			if (strpos($row, '\'driver\'') === false) {
			 	continue;
			}
			$content[$line] = preg_replace_callback('/\'driver\'\s*\=\>\s*\'(.*?)\'/', 'self::_prepDatasource', $row);
			$changes = true;
		}
		if ($changes) {
			$content = implode("\n", $content);
			file_put_contents($file, $content);
			$this->out(__d('cake_console', '%s updated', 'database.php'));
		} else {
			$this->out(__d('cake_console', '%s unchanged', 'database.php'));
		}
	}



/**
 * Update constructors.
 *
 * - Update Helper constructors.
 *
 * @return void
 */
	public function constructors() {
		$this->_paths = App::Path('View/Helper');
		if (!empty($this->params['plugin'])) {
			$this->_paths = App::Path('View/Helper', $this->params['plugin']);
		}
		$patterns = array(
			array(
				'__construct() to __construct(View $View, $settings = array())',
				'/function \_\_construct\(\)/',
				'function __construct(View $View, $settings = array())'
			),
			array(
				'__construct($View) to __construct(View $View, $settings = array())',
				'/function \_\_construct\(\$View\)/',
				'function __construct(View $View, $settings = array())'
			),
			array(
				'__construct($settings = array()) to __construct(View $View, $settings = array())',
				'/function \_\_construct\(\$settings = array\(\)\)/',
				'function __construct(View $View, $settings = array())'
			),
			array(
				'parent::__construct() to parent::__construct($View, $settings)',
				'/parent\:\:\_\_construct\(\)/',
				'parent::__construct($View, $settings)'
			),
			array(
				'parent::__construct($settings) to parent::__construct($View, $settings)',
				'/parent\:\:\_\_construct\(\$settings\)/',
				'parent::__construct($View, $settings)'
			),
		);
		$this->_filesRegexpUpdate($patterns);
		
		
		$this->_paths = App::Path('Controller/Component');
		if (!empty($this->params['plugin'])) {
			$this->_paths = App::Path('Controller/Component', $this->params['plugin']);
		}
		$patterns = array(
			array(
				'__construct() to __construct(ComponentCollection $Collection, $settings = array())',
				'/function \_\_construct\(\)/',
				'function __construct(ComponentCollection $Collection, $settings = array())'
			),
			array(
				'__construct($Collection) to __construct(ComponentCollection $Collection, $settings = array())',
				'/function \_\_construct\(\$Collection\)/',
				'function __construct(ComponentCollection $Collection, $settings = array())'
			),
			array(
				'__construct($settings = array()) to __construct(ComponentCollection $Collection, $settings = array())',
				'/function \_\_construct\(\$settings = array\(\)\)/',
				'function __construct(ComponentCollection $Collection, $settings = array())'
			),
			array(
				'parent::__construct() to parent::__construct($Collection, $settings)',
				'/parent\:\:\_\_construct\(\)/',
				'parent::__construct($Collection, $settings)'
			),
		);
		$this->_filesRegexpUpdate($patterns);	
	}

/**
 * Update paginator links
 * 
 * - Reverse order of title and field in pagination sort
 * 
 */
	public function paginator() {
		$this->_paths = array(
			APP . 'View' . DS,
			APP . 'views' . DS
		);
		if (!empty($this->params['plugin'])) {
			$path = App::pluginPath($this->params['plugin']);
			$this->_paths = array($path . 'View' . DS, $path . 'views' . DS);
		}
		$patterns = array(
			/*
			array(
				'Paginator->sort(\'title\', \'field\') -> Paginator->sort(\'field\', \'title\')',
				'/Paginator-\>sort\((.+),\s\\\'(\w+)\\\'\)/',
				'Paginator->sort(\'\2\', \1)'
			),
			*/
			array(
				'Paginator->sort(\'title\', \'field\') -> Paginator->sort(\'field\', \'title\')',
				'/Paginator\-\>sort\(\'(.*)\',\s*\'(.*)\'\)/',
				'Paginator->sort(\'\2\', \'\1\')'
			),
			array(
				'Paginator->sort(\'title\', \'field\') -> Paginator->sort(\'field\', \'title\')',
				'/Paginator\-\>sort\(__(.*),\s*\'(.*)\'\)/',
				'Paginator->sort(\'\2\', __\1)'
			),
			array(
				'Paginator->sort($..., \'field\') -> Paginator->sort(\'field\', $...)',
				'/Paginator\-\>sort\(\$(.*),\s*\'(.*)\'\)/',
				'Paginator->sort(\'\2\', $\1)'
			),
			array(
				'Paginator->sort(\'title\', \'field\', (.*)) -> Paginator->sort(\'field\', \'title\', (.*))',
				'/Paginator\-\>sort\(\'(.*)\',\s*\'(.*)\', array\((.*)\)\)/',
				'Paginator->sort(\'\2\', \'\1\', array(\3))'
			),
			array(
				'Paginator->sort($..., \'field\', (.*)) -> Paginator->sort(\'field\', $..., (.*))',
				'/Paginator\-\>sort\(__(.*),\s*\'(.*)\',\s*array\((.*)\)\)/',
				'Paginator->sort(\'\2\', __\1, array(\3))'
			),
			array(
				'Paginator->sort($..., \'field\', (.*)) -> Paginator->sort(\'field\', $..., (.*))',
				'/Paginator\-\>sort\(\$(.*),\s*\'(.*)\',\s*array\((.*)\)\)/',
				'Paginator->sort(\'\2\', $\1, array(\3))'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}
	
/**
 * Create a report
 * 
 * - Creates report.txt in TMP
 * 
 * currently reports following issues (for manual upgrade)
 * - cakeError(), aa(), uses(), PHP5, deprecated files
 * TODO:
 * - $this->element(..., array('cache', 'plugin', 'callbacks')) [should be third param]
 * - APP_PATH and CORE_PATH changed and might need manual adjustment
 * - report missing default routes `CakePlugin::routes()` and `require CAKE . 'Config' . DS . 'routes.php';`
 * - check if the core.php is up to date or what is missing/deprecated
 * @return void
 */
	public function report() {
		$file = TMP.'report.txt';
		
		$this->_paths = array(
			APP
		);
		if (!empty($this->params['plugin'])) {
			$this->_paths = array(App::pluginPath($this->params['plugin']));
		}
		
		$content = $this->_report();
		if ($content) {
			file_put_contents($file, $content);
			$this->out(__d('cake_console', 'Report %s has been generated in tmp folder', 'report.txt'));
		} else {
			$this->out(__d('cake_console', 'No issues found to report'));
		}
	}
	
/**
 * generate report
 * @return string $report
 */
	protected function _report() {
		$content = '';
		
		// check for deprecated code that needs manual fixing
		$patterns = array(
			array(
				'aa()',
				'/\baa\(/',
			),
			/*
			// currently grabs App::uses() too
			array(
				'uses() function',
				'/\b[^\:]uses\(/',
			),
			*/
			array(
				'cakeError()',
				'/\bcakeError\(/',
			),
			array(
				'PHP5 constant',
				'/\bPHP5\b/',
			),
		);
		$results = $this->_filesRegexpCheck($patterns);
		
		foreach ($results as $result) {
			$data = '';
			foreach ($result['matches'] as $pattern) {
				$data = 'Deprecated code: ' . $pattern['pattern'][0];
				$data .= PHP_EOL.print_r($pattern['matches'], true);	
			}
			$content .= $this->_newIssue($result['file'], $data);
		}
		
		// deprecated files
		$deprecatedFiles = array('Config'.DS.'inflections.php', 'config'.DS.'inflections.php');
		foreach ($this->_files as $file) {
			foreach ($deprecatedFiles as $deprecatedFile) {
				if (strpos($file, $deprecatedFile) !== false) {
					$data = 'Deprecated file \''.$deprecatedFile.'\' (can be removed)';
					$content .= $this->_newIssue($file, $data);
				}
			}
		}
		
		return $content;
	}
	
	protected function _newIssue($path, $data) {
		$path = str_replace(APP, DS, $path);
		return '*** '.$path.' ***'.PHP_EOL.print_r($data, true).PHP_EOL.PHP_EOL;
	}

/**
 * move file with 2 step process to avoid collisions on case insensitive systems
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
			exec('svn move --force ' . escapeshellarg($from) . ' ' . escapeshellarg($from . $tmp));
			exec('svn move --force ' . escapeshellarg($from . $tmp) . ' ' . escapeshellarg($to));
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
 * delete file according to repository type
 */
	protected function _delete($path, $folder = true) {
		//problems on windows due to case insensivity
		//problems in subversion after deletion
		return;
		
		if ($this->params['git']) {
			exec('git rm -rf ' . escapeshellarg($path));
		} elseif ($this->params['tgit']) {
			exec('tgit rm -rf ' . escapeshellarg($path));
		} elseif ($this->params['svn']) {
			exec('svn delete --force ' . escapeshellarg($path));
		} elseif ($folder) {
			$Folder = new Folder($path);
			$Folder->delete();
		} else {
			unlink($from, $to);
		}
	}
	
/**
 * create and add file according to repository type
 */
	protected function _create($path) {
		new Folder($path, true);
		if ($this->params['git']) {
			exec('git add -f ' . escapeshellarg($path));
		} elseif ($this->params['tgit']) {
			exec('tgit add -f ' . escapeshellarg($path));
		} elseif ($this->params['svn']) {
			exec('svn add --force ' . escapeshellarg($path));
		}
	}
	

/**
 * corrects name of database engine
 * mysqli => Mysql
 * 
 * @return string
 */
	protected function _prepDatasource($x) {
		$driver = $x[1];
		if ($driver == 'mysqli') {
			$driver = 'mysql';
		}
		$driver = ucfirst($driver);
		return '\'datasource\' => \'Database/'.$driver.'\'';
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
			if ($new == $old) {
				continue;
			}

			$this->out(__d('cake_console', 'Moving %s to %s', $old, $new));
			if (!$this->params['dry-run']) {
				$this->_move($old, $new);
			}
		}
	}

/**
 * Move application php files to where they now should be
 *
 * Find all php files in the folder (honoring recursive) and determine where cake expects the file to be
 * If the file is not exactly where cake expects it - move it.
 *
 * @param mixed $path
 * @param mixed $options array(recursive, checkFolder)
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
			foreach($this->_files as $i => $file) {
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
				$this->_create($dir);
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
	protected function _filesRegexpUpdate($patterns) {
		$this->_findFiles($this->params['ext']);
		foreach ($this->_files as $file) {
			$this->out(__d('cake_console', 'Updating %s...', $file), 1, Shell::VERBOSE);
			$this->_updateFile($file, $patterns);
		}
	}

/**
 * checks files based on regular expressions.
 *
 * @param array $patterns Array of search patterns.
 * @return array $matches
 */
	protected function _filesRegexpCheck($patterns) {
		$this->_findFiles($this->params['ext']);
		
		$matches = array();
		foreach ($this->_files as $file) {
			$this->out(__d('cake_console', 'Checking %s...', $file), 1, Shell::VERBOSE);
			if ($match = $this->_checkFile($file, $patterns)) {
				$matches[] = array('file'=>$file, 'matches'=>$match);
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
	protected function _updateFile($file, $patterns) {
		$contents = $fileContent = file_get_contents($file);

		foreach ($patterns as $pattern) {
			$this->out(__d('cake_console', ' * Updating %s', $pattern[0]), 1, Shell::VERBOSE);
			$contents = preg_replace($pattern[1], $pattern[2], $contents);
		}

		$this->out(__d('cake_console', 'Done updating %s', $file), 1);
		if (!$this->params['dry-run'] && $contents !== $fileContent) {
			file_put_contents($file, $contents);
		}
	}

/**
 * Checks a single file.
 *
 * @param string $file The file to check
 * @param array $patterns The matching patterns to run.
 * @return array $matches
 */
	protected function _checkFile($file, $patterns) {
		$contents = file_get_contents($file);
		
		$matches = array();
		foreach ($patterns as $pattern) {
			$this->out(__d('cake_console', ' * Checking %s', $pattern[0]), 1, Shell::VERBOSE);
			preg_match_all($pattern[1], $contents, $match);
			if ($match[0]) {
				$matches[] = array('pattern'=>$pattern, 'matches'=>$match);
			}
		}

		$this->out(__d('cake_console', 'Done checking %s', $file), 1);
		return $matches;
	}

/**
 * get the option parser
 * 
 * note: the order is important for the "all" task to run smoothly
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
				'dry-run'=> array(
					'short' => 'd',
					'help' => __d('cake_console', 'Dry run the update, no files will actually be modified.'),
					'boolean' => true
				)
			)
		);

		return parent::getOptionParser()
			->description(__d('cake_console', "A shell to help automate upgrading from CakePHP 1.3 to 2.0. \n" .
				"Be sure to have a backup of your application before running these commands."))
			->addSubcommand('all', array(
				'help' => __d('cake_console', 'Run all upgrade commands.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('group', array(
				'help' => __d('cake_console', 'Run all defined upgrade commands. Use Configure::write() or params to define.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('locations', array(
				'help' => __d('cake_console', 'Move files and folders to their new homes.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('tests', array(
				'help' => __d('cake_console', 'Update tests class names to FooTest rather than FooTestCase.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('i18n', array(
				'help' => __d('cake_console', 'Update the i18n translation method calls.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('helpers', array(
				'help' => __d('cake_console', 'Update calls to helpers.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('basics', array(
				'help' => __d('cake_console', 'Update removed basics functions to PHP native functions.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('request', array(
				'help' => __d('cake_console', 'Update removed request access, and replace with $this->request.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('routes', array(
				'help' => __d('cake_console', 'Add new 2.0 routes'),
				'parser' => $subcommandParser
			))
			->addSubcommand('configure', array(
				'help' => __d('cake_console', "Update Configure::read() to Configure::read('debug')"),
				'parser' => $subcommandParser
			))
			->addSubcommand('constants', array(
				'help' => __d('cake_console', "Replace Obsolete constants"),
				'parser' => $subcommandParser
			))
			->addSubcommand('controllers', array(
				'help' => __d('cake_console', 'Update controllers'),
				'parser' => $subcommandParser
			))
			->addSubcommand('components', array(
				'help' => __d('cake_console', 'Update components to extend Component class.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('exceptions', array(
				'help' => __d('cake_console', 'Replace use of cakeError with exceptions.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('views', array(
				'help' => __d('cake_console', 'Update components to extend Component class.'),
				'parser' => $subcommandParser
			))
			->addSubcommand('webroot', array(
				'help' => __d('cake_console', 'Update webroot'),
				'parser' => $subcommandParser
			))
			->addSubcommand('legacy', array(
				'help' => __d('cake_console', 'Update legacy files'),
				'parser' => $subcommandParser
			))
			->addSubcommand('constructors', array(
				'help' => __d('cake_console', 'Update constructors'),
				'parser' => $subcommandParser
			))
			->addSubcommand('database', array(
				'help' => __d('cake_console', 'Update database.php'),
				'parser' => $subcommandParser
			))
			->addSubcommand('paginator', array(
				'help' => __d('cake_console', 'Update paginator'),
				'parser' => $subcommandParser
			))
			->addSubcommand('name', array(
				'help' => __d('cake_console', 'Remove name var'),
				'parser' => $subcommandParser
			))
			->addSubcommand('report', array(
				'help' => __d('cake_console', 'Report issues that need to be addressed manually'),
				'parser' => $subcommandParser
			));
	}
}
