<?php
/**
 * Upgrade Shell
 *
 * CakePHP(tm) : Rapid Development Framework (http://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     Copyright (c) Cake Software Foundation, Inc. (http://cakefoundation.org)
 * @link          http://cakephp.org CakePHP(tm) Project
 * @since         CakePHP(tm) v 2.0
 * @license       http://www.opensource.org/licenses/mit-license.php MIT License
 */

App::uses('AppShell', 'Console/Command');
App::uses('Folder', 'Utility');
App::uses('CakePlugin', 'Core');

/**
 * A shell class to help developers upgrade applications to CakePHP 2.0 and above
 *
 * Necessary expecations for the shell to work flawlessly:
 * - brackets must always be correct (`Class extends OtherClass {` in one line!)
 * - all php files most NOT have a closing `?>` tag
 * - 1 tab indentation (instead of spaces) as described in coding guidelines
 *
 */
class UpgradeShell extends AppShell {

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
				$paths[] = CakePlugin::path($plugin);
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
				$this->_paths = array(CakePlugin::path($this->params['plugin']));
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
			$plugins = CakePlugin::loaded();
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
	 * Some really old stuff
	 * @link http://book.cakephp.org/2.0/en/appendices/migrating-from-cakephp-1-2-to-1-3.html
	 *
	 * @return void
	 */
	public function cake13() {
		$this->_buildPaths('View' . DS);

		$patterns = array(
			array(
				'$this->Form->text(\'Model/field\')',
				'/\-\>Form-\>(\w+)\(\'(\w+)\/(\w+)\'/',
				'->Form->\1(\'\2.\3\''
			),
		);

		$this->_filesRegexpUpdate($patterns);

		$this->_buildPaths('Model' . DS);

		$patterns = array(
			array(
				'VALID contants',
				'/\b\VALID_NOT_EMPTY\b/',
				'\'notEmpty\''
			),
			array(
				'VALID contants',
				'/\b\VALID_EMAIL\b/',
				'\'email\''
			),
			array(
				'VALID contants',
				'/\b\VALID_NUMBER\b/',
				'\'number\''
			),
			array(
				'VALID contants',
				'/\b\VALID_YEAR\b/',
				'\'year\''
			),
		);

		$this->_filesRegexpUpdate($patterns);

		$patterns = array(
			array(
				'del( to delete(',
				'/\b\del\(/',
				'delete('
			),
		);

		$this->_filesRegexpUpdate($patterns);

		$this->_buildPaths();

		$patterns = array(
			array(
				'vendor()',
				'/\bvendor\((.*)\)/',
				'App::import(\'Vendor\', \1);'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * CakePHP 2.0 upgrades
	 * @link http://book.cakephp.org/2.0/en/appendices/2-0-migration-guide.html
	 *
	 * @return void
	 */
	public function cake20() {
		$this->_buildPaths('Controller' . DS);
		$patterns = array(
			array(
				'AclComponent::grant( ... allow(',
				'/-\>Acl-\>grant\(/',
				'->Acl->allow('
			),
			array(
				'AclComponent::revoke( ... deny(',
				'/-\>Acl-\>revoke\(/',
				'->Acl->deny('
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * cake2.1 upgrades
	 * @link http://book.cakephp.org/2.0/en/appendices/2-1-migration-guide.html
	 *
	 * @return void
	 */
	public function cake21() {
		# create missing files (AppController / AppModel / AppHelper)
		# http://book.cakephp.org/2.0/en/appendices/2-1-migration-guide.html#appcontroller-apphelper-appmodel-and-appshell
		$appFiles = array(
			'AppHelper' => array(
				'namespace' => 'View/Helper',
			),
			'AppController' => array(
				'namespace' => 'Controller',
			),
			'AppModel' => array(
				'namespace' => 'Model',
			),
			'AppShell' => array(
				'namespace' => 'Console/Command',
			)
		);
		$appFiles['AppHelper']['content'] = <<<EOL
<?php
App::uses('Helper', 'View');
class AppHelper extends Helper {
}

EOL;

		$appFiles['AppModel']['content'] = <<<EOL
<?php
App::uses('Model', 'Model');
class AppModel extends Model {
}

EOL;

		$appFiles['AppController']['content'] = <<<EOL
<?php
App::uses('Controller', 'Controller');
class AppController extends Controller {
}

EOL;

		$appFiles['AppShell']['content'] = <<<EOL
<?php
App::uses('Shell', 'Console');
class AppShell extends Shell {
}

EOL;

		# right now the AppShell is not required yet
		unset($appFiles['AppShell']);

		foreach ($appFiles as $filename => $appFile) {
			$path = APP . $appFile['namespace'] . DS;
			if (!file_exists($path . $filename . '.php')) {
				if (!is_dir($path)) {
					mkdir($path, 0770, true);
				}
				if (!$this->params['dry-run']) {
					file_put_contents($path . $filename . '.php', $appFile['content']);
				}
				$this->out(__d('cake_console', 'Creating %s in %s', $filename, $appFile['namespace']), 1, Shell::VERBOSE);
			}
		}

		$this->_buildPaths('View' . DS . 'Layouts' . DS);

		$patterns = array(
			#	http://book.cakephp.org/2.0/en/views.html#layouts
			array(
				'$content_for_layout replacement',
				'/\$content_for_layout/',
				'$this->fetch(\'content\')'
			),
			# experimental:
			/*
			array(
				'$scripts_for_layout replacement',
				'/\$scripts_for_layout/',
				'echo $this->fetch(\'css\'); echo $this->fetch(\'script\'); echo $this->fetch(\'meta\');'
			),
			# new element plugin syntax:
			array(
				'replacing $this->element(..., array(), array(\'plugin\'=>...))',
				'/\$this-\>element\(\'(.*?)\',\s*array\(.*\),\s*array\((.*)\'plugin\'\s*=>\s*\'(.*?)\'(.*)\)\)/',
				'$this->element(\'\2.\1\')'
			),
			*/
		);
		$this->_filesRegexpUpdate($patterns);

		# auth component allow('*')
		$this->_buildPaths('Controller' . DS);
		$patterns = array(
			#	http://book.cakephp.org/2.0/en/views.html#layouts
			array(
				'remove * wildcard',
				'/-\>Auth-\>allow\(\'\*\'\)/',
				'->Auth->allow()'
			),
			array(
				'remove * wildcard',
				'/-\>Auth-\>allow\(array\(\'\*\'\)\)/',
				'->Auth->allow()'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * cake2.2 and cake2.3 replacements
	 * @link http://book.cakephp.org/2.0/en/appendices/2-2-migration-guide.html
	 * @link http://book.cakephp.org/2.0/en/appendices/2-3-migration-guide.html
	 *
	 * @return void
	 */
	public function cake23() {
		$this->_buildPaths();
		$patterns = array(
			array(
				'->request[\'url\'][...] to ->request(...)',
				'/-\>request[\'url\'][\'(.*?)\']/',
				'->request->query(\'\1\')'
			),
			array(
				'->request[\'url\'][...] to ->request(...)',
				'/-\>request\[\'url\'\]\[(.*?)\]/',
				'->request->query(\1)'
			),
			array(
				'->request->query[...] to ->request(...)',
				'/-\>request-\>query\[(.*?)\]/',
				'->request->query(\1)'
			),
			array(
				'->Behaviors->attach to ->Behaviors->load',
				'/-\>Behaviors-\>attach\(/',
				'->Behaviors->load('
			),
			array(
				'->Behaviors->detach to ->Behaviors->unload',
				'/-\>Behaviors-\>detach\(/',
				'->Behaviors->unload('
			),
			array(
				'->Behaviors->attached to ->Behaviors->loaded',
				'/-\>Behaviors-\>attached\(/',
				'->Behaviors->loaded('
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * cake2.4 replacements
	 * @link https://github.com/cakephp/docs/blob/2.4/en/appendices/2-4-migration-guide.rst
	 * @link http://book.cakephp.org/2.0/en/appendices/2-4-migration-guide.html
	 * (as soon as 2.4 becomes beta)
	 *
	 * @return void
	 */
	public function cake24() {
		$this->_buildPaths();

		$patterns = array(
			array(
				'$this->request->is(\'post\') || $this->request->is(\'put\')',
				'/\$this-\>request-\>is\(\'post\'\) \|\| \$this-\>request-\>is\(\'put\'\)/',
				'$this->request->is(array(\'post\', \'put\'))'
			),
			array(
				'$this->request->is(\'put\') || $this->request->is(\'post\')',
				'/\$this-\>request-\>is\(\'put\'\) \|\| \$this-\>request-\>is\(\'post\'\)/',
				'$this->request->is(array(\'post\', \'put\'))'
			),
			array(
				'$this->request->is(\'post\') && $this->request->is(\'ajax\')',
				'/\$this-\>request-\>is\(\'post\'\) \&\& \$this-\>request-\>is\(\'ajax\'\)/',
				'$this->request->isAll(\'post\', \'ajax\')'
			),
			array(
				'$this->request->is(\'ajax\') && $this->request->is(\'post\')',
				'/\$this-\>request-\>is\(\'ajax\'\) \&\& \$this-\>request-\>is\(\'post\'\)/',
				'$this->request->isAll(\'post\', \'ajax\')'
			),
			# fix wrong ones
			array(
				'$this->request->is(\'post\', \'put\')',
				'/\$this-\>request-\>is\(\'post\', \'put\'\)/',
				'$this->request->is(array(\'post\', \'put\'))'
			),
			array(
				'$this->request->isAll(\'post\', \'ajax\')',
				'/\$this-\>request-\>isAll\(\'post\', \'ajax\'\)/',
				'$this->request->isAll(array(\'post\', \'ajax\'))'
			),
			# constants to Configure
			array(
				'App.fullBaseURL ... App.fullBaseUrl',
				'/\bApp\.fullBaseURL\b/',
				'App.fullBaseUrl'
			),
			array(
				'IMAGES_URL',
				'/\bIMAGES_URL\b/',
				'Configure::read(\'App.imageBaseUrl\')'
			),
			array(
				'JS_URL',
				'/\bJS_URL\b/',
				'Configure::read(\'App.jsBaseUrl\')'
			),
			array(
				'CSS_URL',
				'/\bCSS_URL\b/',
				'Configure::read(\'App.cssBaseUrl\')'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Do not use for CakePHP <= 2.4 !
	 *
	 * Currently does:
	 * - rename view variables
	 * - validation rules name fixes for notEmpty etc.
	 *
	 * @return void
	 */
	public function cake25() {
		$this->_buildPaths();

		$patterns = array(
			array(
				'App::pluginPath() ... CakePlugin::path()',
				'/\bApp\:\:pluginPath\(/',
				'CakePlugin::path('
			),
			array(
				'App::objects(\'plugin\') ... CakePlugin::loaded()',
				'/\bApp\:\:objects\(\'plugin\'\)/i',
				'CakePlugin::loaded()'
			),
		);

		$this->_filesRegexpUpdate($patterns);

		$this->_buildPaths('View' . DS);

		$patterns = array(
			array(
				'$title_for_layout to fetch(\'title\')',
				'/\$title\_for\_layout\b/',
				'$this->fetch(\'title\')'
			),
		);

		$this->_filesRegexpUpdate($patterns);

		$this->_buildPaths('Controller' . DS, 'Controller' . DS . 'Component' . DS);
		$patterns = array(
			array(
				'$this->request->onlyAllow() -> $this->request->allowMethod()',
				'/\$this->request->onlyAllow\(/',
				'$this->request->allowMethod('
			)
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Do not use for CakePHP <= 2.5 !
	 *
	 * - ssn() to personId()
	 * - between validation rule to lenghtBetween (careful: can collide with similar app methods)
	 *
	 * @return void
	 */
	public function cake26() {
		$this->_buildPaths('Model' . DS);

		$patterns = array(
			array(
				'Replace ->between() with ->lengthBetween()',
				'#-\>between\(#',
				'->lengthBetween(',
			),
			array(
				'Replace ::between() with ::lengthBetween()',
				'#\:\:\between\(#',
				'::lengthBetween(',
			),
			array(
				'Replace \'rule\' between lengthBetween',
				'#\'rule\'\s*\=\>\s*\'between\'#',
				'\'rule\' => \'lengthBetween\'',
			),

		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Optional upgrades to prepare for 3.0
	 * will remove/correct deprecated stuff
	 *
	 * @return void
	 */
	public function cake30() {
		// NOT IN USE - same methods changed how they work!
		return;

		$this->_buildPaths('Test' . DS, 'tests' . DS);
		$patterns = array(
			array(
				'App::uses(\'Set\', \'Utility\')',
				'/\bApp\:\:uses\(\'Set\',\s*\'Utility\'\)/',
				'App::uses(\'Hash\', \'Utility\')'
			),
			array(
				'Set to Hash',
				'/\bSet\:\:/',
				'Hash::'
			)
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Make sure validation rules have the proper casing.
	 *
	 * @return void
	 */
	public function validation() {
		$this->_buildPaths('Model' . DS);

		$patterns = array(
			array(
				'notempty to notEmpty',
				'/\bnotempty\b/',
				'notEmpty'
			),
			array(
				'isunique to isUnique',
				'/\bisunique\b/',
				'isUnique'
			),
			array(
				'alphanumeric to alphaNumeric',
				'/\balphanumeric\b/',
				'alphaNumeric'
			),
			array(
				'equalto to equalTo',
				'/\bequalto\b/',
				'equalTo'
			),
			array(
				'minlength to minLength',
				'/\bminlength\b/',
				'minLength'
			),
			array(
				'maxlength to maxLength',
				'/\bmaxlength\b/',
				'maxLength'
			),
			array(
				'naturalnumber to naturalNumber',
				'/\bnaturalnumber\b/',
				'naturalNumber'
			),
			array(
				'inlist to inList',
				'/\binlist\b/',
				'inList'
			),
			array(
				'userdefined to userDefined',
				'/\buserdefined\b/',
				'userDefined'
			),
			array(
				'mimetype to mimeType',
				'/\bmimetype\b/',
				'mimeType'
			),
			array(
				'filesize to fileSize',
				'/\bfilesize\b/',
				'fileSize'
			),
			array(
				'uploaderror to uploadError',
				'/\buploaderror\b/',
				'uploadError'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Try to auto-correct E_STRICT issues.
	 * Mainly for Cake2.4.
	 *
	 */
	public function estrict() {
		$this->_buildPaths();
		$patterns = array(
			array(
				'public function startTest()',
				'/public function startTest\(\)/',
				'public function setUp()'
			),
			array(
				'public function endTest()',
				'/public function endTest\(\)/',
				'public function tearDown()'
			),
		);
		$this->_filesRegexpUpdate($patterns);

		$this->_buildPaths('Model' . DS . 'Behavior' . DS);
		$patterns = array(
			# beforeValidate
			array(
				'public function beforeValidate()',
				'/public function beforeValidate\(\)/',
				'public function beforeValidate(Model $Model, $options = array())'
			),
			array(
				'parent::beforeValidate()',
				'/\bparent::beforeValidate\(\)/',
				'parent::beforeValidate($Model, $options)'
			),
			array(
				'public function beforeValidate(Model $Model)',
				'/public function beforeValidate\((\w+)\s+\$(\w+)\)/',
				'public function beforeValidate(\1 $\2, $options = array())'
			),
			array(
				'parent::beforeValidate($Model)',
				'/\bparent::beforeValidate\(\$(\w+)\)/',
				'parent::beforeValidate($\1, $options)'
			),
			# beforeSave
			array(
				'public function beforeSave()',
				'/public function beforeSave\(\)/',
				'public function beforeSave(Model $Model, $options = array())'
			),
			array(
				'parent::beforeSave()',
				'/\bparent::beforeSave\(\)/',
				'parent::beforeSave($Model, $options)'
			),
			array(
				'public function beforeSave(Model $Model)',
				'/public function beforeSave\((\w+)\s+\$(\w+)\)/',
				'public function beforeSave(\1 $\2, $options = array())'
			),
			array(
				'parent::beforeSave($Model)',
				'/\bparent::beforeSave\(\$(\w+)\)/',
				'parent::beforeSave($\1, $options)'
			),
			# afterSave
			array(
				'public function afterSave()',
				'/public function afterSave\(\)/',
				'public function afterSave(Model $Model, $created, $options = array())'
			),
			array(
				'public function afterSave(Model $Model)',
				'/public function afterSave\((\w+)\s+\$(\w+)\)/',
				'public function afterSave(\1 $\2, $created, $options = array())'
			),
			array(
				'public function afterSave(Model $Model, $created)',
				'/public function afterSave\((\w+)\s+\$(\w+),\s*\$created\)/',
				'public function afterSave(\1 $\2, $created, $options = array())'
			),
			array(
				'parent::afterSave()',
				'/\bparent::afterSave\(\)/',
				'parent::afterSave($Model, $created, $options)'
			),
			array(
				'parent::afterSave($Model)',
				'/\bparent::afterSave\(\$(\w+)\)/',
				'parent::afterSave($\1, $created, $options)'
			),
			array(
				'parent::afterSave($Model, $created)',
				'/\bparent::afterSave\(\$(\w+)\,\s*\$created\)/',
				'parent::afterSave($\1, $created, $options)'
			),
			# beforeFind
			array(
				'public function beforeFind()',
				'/public function beforeFind\(\)/',
				'public function beforeFind(Model $Model, $query)'
			),
			array(
				'parent::beforeFind()',
				'/\bparent::beforeFind\(\)/',
				'parent::beforeFind($Model, $query)'
			),
			array(
				'public function beforeFind(Model $Model)',
				'/public function beforeFind\((\w+)\s+\$(\w+)\)/',
				'public function beforeFind(\1 $\2, $query)'
			),
			array(
				'parent::beforeFind($Model)',
				'/\bparent::beforeFind\(\$(\w+)\)/',
				'parent::beforeFind(\1, $query)'
			),
			# afterFind
			array(
				'public function afterFind()',
				'/public function afterFind\(\)/',
				'public function afterFind(Model $Model, $results, $primary = false)'
			),
			array(
				'public function afterFind(Model $Model)',
				'/public function afterFind\((\w+)\s+\$(\w+)\)/',
				'public function afterFind(\1 $\2, $results, $primary = false)'
			),
			array(
				'public function afterFind(Model $Model, $results)',
				'/public function afterFind\((\w+)\s+\$(\w+),\s*\$results\)/',
				'public function afterFind(\1 $\2, $results, $primary = false)'
			),
			array(
				'public function afterFind(Model $Model, $results, $primary)',
				'/public function afterFind\((\w+)\s+\$(\w+),\s*\$results,\s*\$primary\)/',
				'public function afterFind(\1 $\2, $results, $primary = false)'
			),
			array(
				'parent::afterFind()',
				'/\bparent::afterFind\(\)/',
				'parent::afterFind($Model, $results, $primary)'
			),
			array(
				'parent::afterFind($Model)',
				'/\bparent::afterFind\(\$(\w+)\)/',
				'parent::afterFind($\1, $results, $primary)'
			),
			array(
				'parent::afterFind($Model, $results)',
				'/\bparent::afterFind\(\$(\w+),\s*\$results\)/',
				'parent::afterFind($\1, $results, $primary)'
			),
			# beforeDelete
			array(
				'public function beforeDelete()',
				'/public function beforeDelete\(\)/',
				'public function beforeDelete($cascade = true)'
			),
			array(
				'public function beforeDelete(Model $Model)',
				'/public function beforeDelete\((\w+)\s+\$(\w+)\)/',
				'public function beforeDelete(\1 $\2, $cascade = true)'
			),
			array(
				'parent::beforeDelete()',
				'/\bparent::beforeDelete\(\)/',
				'parent::beforeDelete($Model, $cascade)'
			),
			array(
				'parent::beforeDelete($Model)',
				'/\bparent::beforeDelete\(\$(\w+)\)/',
				'parent::beforeDelete($\1, $cascade)'
			),
			# afterDelete
		);
		$this->_filesRegexpUpdate($patterns);

		$this->_buildPaths('Model' . DS);
		$patterns = array(
			# beforeValidate
			array(
				'public function beforeValidate()',
				'/public function beforeValidate\(\)/',
				'public function beforeValidate($options = array())'
			),
			array(
				'parent::beforeValidate()',
				'/\bparent::beforeValidate\(\)/',
				'parent::beforeValidate($options)'
			),
			# beforeSave
			array(
				'public function beforeSave()',
				'/public function beforeSave\(\)/',
				'public function beforeSave($options = array())'
			),
			array(
				'parent::beforeSave()',
				'/\bparent::beforeSave\(\)/',
				'parent::beforeSave($options)'
			),
			# afterSave
			array(
				'public function afterSave()',
				'/public function afterSave\(\)/',
				'public function afterSave($created, $options = array())'
			),
			array(
				'parent::afterSave()',
				'/\bparent::afterSave\(\)/',
				'parent::afterSave($created, $options)'
			),
			array(
				'public function afterSave($created)',
				'/public function afterSave\(\$created\)/',
				'public function afterSave($created, $options = array())'
			),
			array(
				'parent::afterSave($created)',
				'/\bparent::afterSave\(\$created\)/',
				'parent::afterSave($created, $options)'
			),
			# beforeFind
			array(
				'public function beforeFind()',
				'/public function beforeFind\(\)/',
				'public function beforeFind($query)'
			),
			array(
				'parent::beforeFind()',
				'/\bparent::beforeFind\(\)/',
				'parent::beforeFind($query)'
			),
			# afterFind
			array(
				'public function afterFind()',
				'/public function afterFind\(\)/',
				'public function afterFind($results, $primary = false)'
			),
			array(
				'public function afterFind($results)',
				'/public function afterFind\(\$results\)/',
				'public function afterFind($results, $primary = false)'
			),
			array(
				'public function afterFind($results, $primary)',
				'/public function afterFind\(\$results,\s*\$primary\)/',
				'public function afterFind($results, $primary = false)'
			),
			array(
				'parent::afterFind()',
				'/\bparent::afterFind\(\)/',
				'parent::afterFind($results, $primary)'
			),
			array(
				'parent::afterFind($results)',
				'/\bparent::afterFind\(\$results\)/',
				'parent::afterFind($results, $primary)'
			),
			# beforeDelete
			array(
				'public function beforeDelete()',
				'/public function beforeDelete\(\)/',
				'public function beforeDelete($cascade = true)'
			),
			array(
				'parent::beforeDelete()',
				'/\bparent::beforeDelete\(\)/',
				'parent::beforeDelete($cascade)'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Update tests.
	 *
	 * - Update tests class names to FooTest rather than FooTestCase.
	 *
	 * @return void
	 */
	public function tests() {
		$this->_buildPaths(array('Test' . DS, 'tests' . DS));

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
			array(
				'$this->assertEqual to $this->assertEquals',
				'/\$this-\>assertEqual\(/i',
				'$this->assertEquals('
			),
			array(
				'$this->assertNotEqual to $this->assertNotEquals',
				'/\$this-\>assertNotEqual\(/i',
				'$this->assertNotEquals('
			),
			array(
				'$this->assertIdentical to $this->assertSame',
				'/\$this-\>assertIdentical\(/i',
				'$this->assertSame('
			),
			array(
				'$this->assertNotIdentical to $this->assertNotSame',
				'/\$this-\>assertNotIdentical\(/i',
				'$this->assertNotSame('
			),
			array(
				'$this->assertPattern to $this->assertRegExp',
				'/\$this-\>assertPattern\(/i',
				'$this->assertRegExp('
			),
			array(
				'$this->assertNoPattern to $this->assertNotRegExp',
				'/\$this-\>assertNoPattern\(/i',
				'$this->assertNotRegExp('
			),
			array(
				'$this->assertInstanceOf correct order',
				'/\$this-\>assertInstanceOf\(\$(.*?),\s*\'(.*?)\'\)/i',
				'$this->assertInstanceOf(\'\2\', $\1)'
			),
			array(
				'$this->assertIsA to $this->assertInstanceOf',
				'/\$this-\>assertIsA\(\$(.*?),\s*\'(.*?)\'\)/i',
				'$this->assertInstanceOf(\'\2\', $\1)'
			),
			array(
				'$this->assertTrue(is_a()) to $this->assertInstanceOf()',
				'/\$this-\>assertTrue\(is_a\(\$(.*?),\s*\'(.*?)\'\)\)/i',
				'$this->assertInstanceOf(\'\2\', $\1)'
			),
			array(
				'assertReference() to assertSame()',
				'/\$this-\>assertReference\(\$(.*?),\s*\'(.*?)\'\)/i',
				'$this->assertSame($\1, $\2)'
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
				$this->out(__d('cake_console', 'Upgrading locations for plugin %s', $plugin));
				$this->locations();
			}
			$this->_files = array();
			chdir($cwd);
			$this->out(__d('cake_console', 'Upgrading locations for app directory'));
		}
		$moves = array(
			'locale' => 'Locale',
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
		foreach ($moves as $old => $new) {
			if (is_dir($old)) {
				$this->out(__d('cake_console', 'Moving %s to %s', $old, $new));
				if (!$this->params['dry-run']) {
					$this->_move($old, $new);
				}
			}
		}

		$this->_moveViewFiles();
		$this->_moveAppClasses();

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
			'Test/fixtures',
			'Test/Fixture',
			'views',
			'View',
			'vendors/shells',
		);

		$defaultOptions = array(
			'recursive' => true,
			'checkFolder' => true,
			'regex' => '@class (\S*) .*(\s|\v)*{@i'
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
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = array(CakePlugin::path($this->params['plugin']) . 'views' . DS);
		} else {
			$this->_paths = array_diff(App::path('views'), App::core('views'));
		}

		$patterns = array();
		App::build(array(
			'View/Helper' => App::core('View/Helper'),
		), App::APPEND);
		$helpers = App::objects('helper');
		$plugins = CakePlugin::loaded();
		$pluginHelpers = array();
		foreach ($plugins as $plugin) {
			CakePlugin::load($plugin);
			$pluginHelpers = array_merge(
				$pluginHelpers,
				App::objects('helper', CakePlugin::path($plugin) . DS . 'views' . DS . 'helpers' . DS, false)
			);
		}
		$helpers = array_merge($pluginHelpers, $helpers);
		foreach ($helpers as $helper) {
			$helper = preg_replace('/Helper$/', '', $helper);
			$oldHelper = $helper;
			$oldHelper{0} = strtolower($oldHelper{0});
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
		$this->_buildPaths();

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
			array(
				'__*(*, true) to __*(*)',
				'/(__[a-z]*\(.*?)(,\s*true)(\))/',
				'\1\3'),
			/*
			//2.4
			array(
				'Upgrade default validation error message',
				'/\bThis field cannot be left blank\b/',
				'This field is invalid'
			)
			*/
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
		$this->_buildPaths();

		$patterns = array(
			array(
				'a(*) -> array(*)',
				'/\ba\(([^)]*)\)/',
				'array(\1)'
			),
			array(
				'e(*) -> echo *',
				'/\be\(([^)]*)\)/',
				'echo \1'
			),
			array(
				'ife(*, *, *) -> !empty(*) ? * : *',
				'/ife\(([^)]*), ([^)]*), ([^)]*)\)/',
				'!empty(\1) ? \2 : \3'
			),
			array(
				'r(*, *, *) -> str_replace(*, *, *)',
				'/\br\(/',
				'str_replace('
			),
			// fix to look back and not convert `function up() {...}` or `$this->up()` etc
			array(
				'up(*) -> strtoupper(*)',
				'/(?<!function )(?<!\>)\bup\(/',
				'strtoupper('
			),
			array(
				'low(*) -> strtolower(*)',
				'/(?<!function )(?<!\>)\blow\(/',
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
				'RequestHandlerComponent::clientIP() to CakeRequest::clientIP()',
				'/\bRequestHandlerComponent\:\:getClientIP\(\)/i',
				'CakeRequest::clientIP()'
			),
			// new in cake2.x
			array(
				'am() to array_merge',
				'/\bam\(/i',
				'array_merge('
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Remove name (lib, controller, model, view, component, behavior, helper, fixture)
	 *
	 * @return void
	 */
	public function name_attribute() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$pluginPath = CakePlugin::path($this->params['plugin']);
			$this->_paths = array(
				$pluginPath . 'Lib' . DS,
				$pluginPath . 'Controller' . DS,
				$pluginPath . 'Controller' . DS . 'Component' . DS,
				$pluginPath . 'View' . DS,
				$pluginPath . 'View' . DS . 'Helper' . DS,
				$pluginPath . 'Model' . DS,
				$pluginPath . 'Model' . DS . 'Behavior' . DS,
				$pluginPath . 'Test' . DS . 'Fixture' . DS,
				$pluginPath . 'Config' . DS . 'Schema' . DS,
				$pluginPath . 'libs' . DS,
				$pluginPath . 'controllers' . DS,
				$pluginPath . 'controllers' . DS . 'components' . DS,
				$pluginPath . 'views' . DS,
				$pluginPath . 'views' . DS . 'helpers' . DS,
				$pluginPath . 'models' . DS,
				$pluginPath . 'models' . DS . 'behaviors' . DS,
				$pluginPath . 'tests' . DS . 'fixtures' . DS,
				$pluginPath . 'config' . DS . 'schema' . DS,
			);
		} else {
			$libs = App::path('Lib');
			$views = App::path('View');
			$controllers = App::path('Controller');
			$components = App::path('Controller/Component');
			$models = App::path('Model');
			$helpers = App::path('View/Helper');
			$behaviors = App::path('Model/Behavior');
			$this->_paths = array_merge($libs, $views, $controllers, $components, $models, $helpers, $behaviors);
			$this->_paths[] = TESTS . 'Fixture' . DS;
			$this->_paths[] = APP . 'Config' . DS . 'Schema' . DS;
		}

		$patterns = array(
			array(
				'remove var $name = ...;',
				'/\s\bvar\s*\$name\s*=\s*(.*);\s/',
				''
			),
			array(
				'remove public $name = ...;',
				'/\s\bpublic\s*\$name\s*=\s*(.*);\s/',
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
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$pluginPath = CakePlugin::path($this->params['plugin']);
			$this->_paths = array(
				$pluginPath . 'Controller' . DS,
				$pluginPath . 'Controller' . DS . 'Component' . DS,
				$pluginPath . 'View' . DS,
				$pluginPath . 'controllers' . DS,
				$pluginPath . 'controllers' . DS . 'components' . DS,
				$pluginPath . 'views' . DS,
			);
		} else {
			$views = array_diff(App::path('views'), App::core('views'));
			$controllers = array_diff(App::path('controllers'), App::core('controllers'), array(APP));
			$components = array_diff(App::path('components'), App::core('components'));
			$this->_paths = array_merge($views, $controllers, $components);
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
				'/-\>request-\>params\[\'url\'\]\[\'(.*?)\'\]/',
				'->request->query[\'\1\']',
			),
			array(
				'->params[\'url\'][*] -> ->request->query[*]',
				'/-\>params\[\'url\'\]\[\'(.*?)\'\]/',
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
				'$this->params -> $this->request->params',
				'/-\>request-\>params\[\'form\'\]/',
				'->request->data'
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
			array(
				'\'type\'=>\'textfield\' to textarea',
				'/\'type\'\s*=\>\s*\'textfield\'/',
				'\'type\' => \'textarea\''
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
		if (!empty($this->params['plugin'])) {
			return;
		}
		if (!empty($this->params['custom'])) {
			return;
		}

		$file = APP . 'Config' . DS . 'routes.php';
		if (!file_exists($file)) {
			$this->out(__d('cake_console', 'no routes.php found in Config - abort adding missing routes'));
			return;
		}
		$content = file_get_contents($file);
		if (strpos($content, 'CakePlugin::routes()') === false) {
			$this->out(__d('cake_console', 'adding 2.0 plugin routes...'));
			$content .= PHP_EOL . PHP_EOL . 'CakePlugin::routes();';
			$changes = true;
		}
		if (strpos($content, 'require CAKE . \'Config\' . DS . \'routes.php\'') === false) {
			$this->out(__d('cake_console', 'adding new 2.0 default routes...'));
			$content .= PHP_EOL . PHP_EOL . '/**
* Load the CakePHP default routes. Remove this if you do not want to use
* the built-in default routes.
*/
require CAKE . \'Config\' . DS . \'routes.php\';';
			$changes = true;
		}
		if (!empty($changes)) {
			if (!$this->params['dry-run']) {
				file_put_contents($file, $content);
			}
		}
	}

	/**
	 * Update Configure::read() calls with no params.
	 *
	 * @return void
	 */
	public function configure() {
		$this->_buildPaths();

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
		$this->_buildPaths();

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
		$this->_buildPaths('Controller');

		$patterns = array(
			array(
				'$this->viewPath = \'elements\' to $this->viewPath = \'Elements\'',
				'/\$this-\>viewPath\s*=\s*\'elements\'/i',
				'$this->viewPath = \'Elements\''
			),
			array(
				'$this->view is now $this->viewClass',
				'/\$this-\>view\s*=\s*\'(.*?)\'/i',
				'$this->viewClass = \'\1\''
			),
			array(
				'$this->RequestHandler->isPost()',
				'/\$this-\>RequestHandler-\>isPost\(\)/',
				'$this->request->is(\'post\')'
			),
			array(
				'$this->RequestHandler->isPut()',
				'/\$this-\>RequestHandler-\>isPut\(\)/',
				'$this->request->is(\'put\')'
			),
			array(
				'$this->RequestHandler->isDelete()',
				'/\$this-\>RequestHandler-\>isDelete\(\)/',
				'$this->request->is(\'delete\')'
			),
			array(
				'$this->RequestHandler->isGet()',
				'/\$this-\>RequestHandler-\>isGet\(\)/',
				'$this->request->is(\'get\')'
			),
			array(
				'$this->RequestHandler->isAjax()',
				'/\$this-\>RequestHandler-\>isAjax\(\)/',
				'$this->request->is(\'ajax\')'
			),
			array(
				'$this->RequestHandler->getReferer()',
				'/\$this-\>RequestHandler-\>getReferer\(\)/',
				'$this->request->referer()'
			),
			array(
				'$this->RequestHandler->getClientIP()',
				'/\$this-\>RequestHandler-\>getClientIP\(\)/',
				'$this->request->clientIp()'
			),
			array(
				'$this->redirect(); exit; ... return $this->redirect(',
				'/\t\$this-\>redirect\((.*?)\);\s*\s*\s*(exit|die)(\(.*?\))?;/',
				"\t" . 'return $this->redirect(\1);'
			),
			array(
				'return $this->redirect(); exit; ... return $this->redirect(',
				'/\treturn\s+\$this-\>redirect\((.*?)\);\s*\s*\s*(exit|die)(\(.*?\))?;/',
				"\t" . 'return $this->redirect(\1);'
			),
			array(
				'$this->redirect( ... return $this->redirect(',
				'/\t\$this-\>redirect\(/',
				"\t" . 'return $this->redirect('
			),
			array(
				'$this->flash( ... return $this->flash(',
				'/\t\$this-\>flash\(/',
				"\t" . 'return $this->flash('
			),
			// Correct bad practices
			array(
				'CakeSession:: ... $this->Session->',
				'/\bCakeSession\:\:/',
				'$this->Session->'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Update console stuff.
	 *
	 * - Shells and tasks.
	 *
	 * @return void
	 */
	public function console() {
		$this->_buildPaths('Console/Command');

		$patterns = array(
			array(
				'$this->error( ... return $this->error(',
				'/\t\$this-\>error\(/',
				"\t" . 'return $this->error('
			),
		);

		$this->_filesRegexpUpdate($patterns);

		$to = APP . 'Console' . DS;
		$from = CAKE . 'Console' . DS . 'Templates' . DS . 'skel' . DS . 'Console' . DS;
		$files = array('cake', 'cake.bat', 'cake.php');
		foreach ($files as $file) {
			if (file_exists($to . $file)) {
				if (!$this->params['dry-run']) {
					copy($from . $file, $to . $file);
				}
				$this->out(__d('cake_console', 'Console file %s updated', $file));
			}
		}
		$content = file_get_contents($to . 'cake');
		if (strpos($content, "\n\r") !== false) {
			$this->error($to . 'cake contains WIN newlines which will break on some UNIX OS. Please convert to UNIX newlines.');
		}
	}

	/**
	 * Update components.
	 *
	 * - Make components that extend Object to extend Component.
	 *
	 * @return void
	 */
	public function components() {
		$this->_buildPaths('Controller/Component');

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
	 * Update method calls
	 *
	 * - mainly in controllers/models
	 *
	 * @return void
	 */
	public function methods() {
		$this->_buildPaths();

		$patterns = array(
			array(
				'generatetreelist to generateTreeList',
				'/\bgeneratetreelist\(/i',
				'generateTreeList('
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
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$pluginPath = CakePlugin::path($this->params['plugin']);
			$this->_paths = array(
				$pluginPath . 'controllers' . DS,
				$pluginPath . 'controllers' . DS . 'components' . DS,
			);
		} else {
			$controllers = array_diff(App::path('controllers'), App::core('controllers'), array(APP));
			$components = array_diff(App::path('components'), App::core('components'));
			$this->_paths = array_merge($controllers, $components);
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
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$path = CakePlugin::path($this->params['plugin']);
			$this->_paths = array($path . 'View' . DS, $path . 'views' . DS);
		} else {
			$this->_paths = array(
				APP . 'View' . DS,
				APP . 'views' . DS
			);
		}

		$patterns = array(
			array(
				'$...-> to $this->...->',
				'/\$\b(?!this)([a-z][a-zA-Z0-9_]+)\b-\>/',
			),
		);
		$this->_filesRegexpUpdate($patterns, 'helperName');

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
			array(
				'renderElement() to element()',
				'/-\>renderElement\(/',
				'->element('
			),
			array(
				'renderElement() to element()',
				'/-\>renderElement\(/',
				'->element('
			),
			array(
				'$this->Javascript->link() to $this->Html->script()',
				'/\$this-\>Javascript-\>link\(/',
				'$this->Html->script('
			),
			array(
				'$this->Javascript() to $this->Js()',
				'/\$this-\>Javascript-\>/',
				'$this->Js->'
			),
			// Correct bad practices
			array(
				'CakeSession:: ... $this->Session->',
				'/\bCakeSession\:\:/',
				'$this->Session->'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * UpgradeShell::stylesheets()
	 *
	 * @return void
	 */
	public function stylesheets() {
		if (!empty($this->_customPaths)) {
			$this->_paths = $this->_customPaths;
		} elseif (!empty($this->params['plugin'])) {
			$path = CakePlugin::path($this->params['plugin']);
			$this->_paths = array($path . 'View' . DS, $path . 'views' . DS);
		} else {
			$this->_paths = array(
				APP . 'View' . DS,
				APP . 'views' . DS
			);
		}

		$patterns = array(
			array(
				'$this->Html->css($path, $rel, $options) to $this->Html->css($path, $options)',
				'/\$this-\>Html-\>css\((.*?),\s*(.*?),\s*(.*?)\)/',
				'$this->Html->css(\1, \3)'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	protected function _helperName($matches) {
		$name = $matches[1];
		$name = Inflector::camelize(Inflector::underscore($name));
		return '$this->' . ucfirst($name) . '->';
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
		if (!empty($this->params['custom'])) {
			return;
		}

		$patterns = array(
			array(
				'index.php?url=$1 => index.php?/$1',
				'/index.php\?url=\$1/',
				'index.php'
			),
			array(
				'index.php?/$1',
				'/index.php\?\/\$1/',
				'index.php'
			),
		);

		$to = APP . 'webroot' . DS;
		$from = CAKE . 'Console' . DS . 'Templates' . DS . 'skel' . DS . 'webroot' . DS;
		$file = $to . '.htaccess';
		if (file_exists($file)) {
			$this->_updateFile($file, $patterns);
			$this->out(__d('cake_console', '%s updated', '.htaccess'));
		}

		$files = array('index.php', 'test.php');
		foreach ($files as $file) {
			if (!$this->params['dry-run']) {
				copy($from . $file, $to . $file);
			}
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
		$this->_buildPaths();

		$patterns = array(
			array(
				'App::import(\'Core\', \'Folder\') to App::uses(\'Folder\', \'Utility\')',
				'/App\:\:import\(\'Core\',\s*\'Folder\'\)/',
				'App::uses(\'Folder\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'File\') to App::uses(\'File\', \'Utility\')',
				'/App\:\:import\(\'Core\',\s*\'File\'\)/',
				'App::uses(\'File\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Sanitize\') to App::uses(\'Sanitize\', \'Utility\')',
				'/App\:\:import\(\'Core\',\s*\'Sanitize\'\)/',
				'App::uses(\'Sanitize\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Inflector\') to App::uses(\'Inflector\', \'Utility\')',
				'/App\:\:import\(\'Core\',\s*\'Inflector\'\)/',
				'App::uses(\'Inflector\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Validation\') to App::uses(\'Validation\', \'Utility\')',
				'/App\:\:import\(\'Core\',\s*\'Validation\'\)/',
				'App::uses(\'Validation\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Security\') to App::uses(\'Security\', \'Utility\')',
				'/App\:\:import\(\'Core\',\s*\'Security\'\)/',
				'App::uses(\'Security\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Xml\') to App::uses(\'Xml\', \'Utility\')',
				'/App\:\:import\(\'Core\',\s*\'Xml\'\)/',
				'App::uses(\'Xml\', \'Utility\')'
			),
			array(
				'App::import(\'Core\', \'Router\') to App::uses(\'Router\', \'Routing\')',
				'/App\:\:import\(\'Core\',\s*\'Router\'\)/',
				'App::uses(\'Router\', \'Routing\')'
			),
			array(
				'App::import(\'Core\', \'HttpSocket\') to App::uses(\'HttpSocket\', \'Network/Http\')',
				'/App\:\:import\(\'Core\',\s*\'HttpSocket\'\)/',
				'App::uses(\'HttpSocket\', \'Network/Http\')'
			),
			array(
				'App::import(\'Core\', \'Object\') to App::uses(\'Object\', \'Core\')',
				'/App\:\:import\(\'Core\',\s*\'Object\'\)/',
				'App::uses(\'Object\', \'Core\')'
			),
			array(
				'App::import(\'Core\', \'Controller\') to App::uses(\'Controller\', \'Controller\')',
				'/App\:\:import\(\'Core\',\s*\'Controller\'\)/',
				'App::uses(\'Controller\', \'Controller\')'
			),
		);

		$legacyClasses = array(
			'Folder' => 'Utility',
			'File' => 'Utility',
			'Sanitize' => 'Utility',
			'Inflector' => 'Utility',
			'Validation' => 'Utility',
			'Security' => 'Utility',
			'Xml' => 'Utility',
			'Router' => 'Routing',
			'HttpSocket' => 'Network/Http',
			'Object' => 'Core',
			'Controller' => 'Controller',
		);
		foreach ($legacyClasses as $legacyClass => $package) {
			$patterns[] = array(
				'App::import(\'' . $legacyClass . '\') to App::uses(\'' . $legacyClass . '\', \'' . $package . '\')',
				'/App\:\:import\(\'' . $legacyClass . '\'\)/',
				'App::uses(\'' . $legacyClass . '\', \'' . $package . '\')'
			);
		}

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
		if (!empty($this->params['custom'])) {
			return;
		}

		$file = APP . 'Config' . DS . 'database.php';
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
			$content[$line] = trim(preg_replace_callback('/\'driver\'\s*\=\>\s*\'(.*?)\'/', 'self::_prepDatasource', $row));
			$changes = true;
		}
		if ($changes) {
			$content = implode(PHP_EOL, $content);
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
		$this->_buildPaths('View/Helper');

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

		$this->_buildPaths('Controller/Component');

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
	 * Careful: run only once (the second time some could get switched back)
	 *
	 * - Reverse order of title and field in pagination sort
	 *
	 * @return void
	 */
	public function paginator() {
		$this->_buildPaths(array('View' . DS, 'views' . DS));

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
				'/Paginator\-\>sort\(\'(.*?)\',\s*\'(.*?)\'\)/',
				'Paginator->sort(\'\2\', \'\1\')'
			),
			array(
				'Paginator->sort(\'title\', \'field\') -> Paginator->sort(\'field\', \'title\')',
				'/Paginator\-\>sort\(__(.*),\s*\'(.*?)\'\)/',
				'Paginator->sort(\'\2\', __\1)'
			),
			array(
				'Paginator->sort($..., \'field\') -> Paginator->sort(\'field\', $...)',
				'/Paginator\-\>sort\(\$(.*),\s*\'(.*)\'\)/',
				'Paginator->sort(\'\2\', $\1)'
			),
			array(
				'Paginator->sort(\'title\', \'field\', (.*)) -> Paginator->sort(\'field\', \'title\', (.*))',
				'/Paginator\-\>sort\(\'(.*?)\',\s*\'(.*?)\', array\((.*)\)\)/',
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
	 *
	 * @return void
	 */
	public function report() {
		$file = TMP . 'report.txt';
		$this->_buildPaths();

		$content = $this->_report();
		if ($content) {
			file_put_contents($file, $content);
			$this->out(__d('cake_console', 'Report %s has been generated in tmp folder', 'report.txt'));
		} else {
			$this->out(__d('cake_console', 'No issues found to report'));
		}
	}

	/**
	 * Generate report
	 *
	 * @return string Report
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
				$data .= PHP_EOL . print_r($pattern['matches'], true);
			}
			$content .= $this->_newIssue($result['file'], $data);
		}

		// deprecated files
		$deprecatedFiles = array('Config' . DS . 'inflections.php', 'config' . DS . 'inflections.php');
		foreach ($this->_files as $file) {
			foreach ($deprecatedFiles as $deprecatedFile) {
				if (strpos($file, $deprecatedFile) !== false) {
					$data = 'Deprecated file \'' . $deprecatedFile . '\' (can be removed)';
					$content .= $this->_newIssue($file, $data);
				}
			}
		}

		return $content;
	}

	protected function _newIssue($path, $data) {
		$path = str_replace(APP, DS, $path);
		return '*** ' . $path . ' ***' . PHP_EOL . print_r($data, true) . PHP_EOL . PHP_EOL;
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
			if ($callback) {
				$contents = preg_replace_callback($pattern[1], array($this, '_' . $callback), $contents);
			} else {
				$contents = preg_replace($pattern[1], $pattern[2], $contents);
			}
		}

		$this->out(__d('cake_console', 'Done updating %s', $file), 1, Shell::VERBOSE);
		if (!$this->params['dry-run'] && $contents !== $fileContent) {
			file_put_contents($file, $contents);
		}
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
				'interactive' => array(
					'short' => 'i',
					'help' => __d('cake_console', 'Interactive commands.'),
					'boolean' => true
				),
			)
		);

		$parser->description(
			__d('cake_console', "A shell to help automate upgrading from CakePHP 1.x to 2.x. \n" .
			"Be sure to have a backup of your application before running these commands."
		))->addSubcommand('all', array(
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
		->addSubcommand('console', array(
			'help' => __d('cake_console', 'Update console (shells and tasks)'),
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
			'help' => __d('cake_console', 'Update views and replace nocache tag'),
			'parser' => $subcommandParser
		))
		->addSubcommand('stylesheets', array(
			'help' => __d('cake_console', 'Update CSS style tag'),
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
		->addSubcommand('name_attribute', array(
			'help' => __d('cake_console', 'Remove name attribute var (PHP4 leftover)'),
			'parser' => $subcommandParser
		))
		->addSubcommand('methods', array(
			'help' => __d('cake_console', 'Correct method calls'),
			'parser' => $subcommandParser
		))
		->addSubcommand('cake13', array(
			'help' => __d('cake_console', 'Upgrade stuff older than cake13 (already deprecated in v13)'),
			'parser' => $subcommandParser
		))
		->addSubcommand('cake20', array(
			'help' => __d('cake_console', 'Upgrade to CakePHP 2.0'),
			'parser' => $subcommandParser
		))
		->addSubcommand('cake21', array(
			'help' => __d('cake_console', 'Upgrade to CakePHP 2.1'),
			'parser' => $subcommandParser
		))
		->addSubcommand('cake23', array(
			'help' => __d('cake_console', 'Upgrade to CakePHP 2.3'),
			'parser' => $subcommandParser
		))
		->addSubcommand('cake24', array(
			'help' => __d('cake_console', 'Upgrade to CakePHP 2.4'),
			'parser' => $subcommandParser
		))
		->addSubcommand('cake25', array(
			'help' => __d('cake_console', 'Upgrade to CakePHP 2.5'),
			'parser' => $subcommandParser
		))
		->addSubcommand('cake26', array(
			'help' => __d('cake_console', 'Upgrade to CakePHP 2.6'),
			'parser' => $subcommandParser
		))
		->addSubcommand('cake30', array(
			'help' => __d('cake_console', 'Upgrade to CakePHP 3.0 (experimental!)'),
			'parser' => $subcommandParser
		))
		->addSubcommand('validation', array(
			'help' => __d('cake_console', 'Upgrade validation name casings.'),
			'parser' => $subcommandParser
		))
		->addSubcommand('estrict', array(
			'help' => __d('cake_console', 'Upgrade to E_STRICT standards'),
			'parser' => $subcommandParser
		))
		->addSubcommand('report', array(
			'help' => __d('cake_console', 'Report issues that need to be addressed manually'),
			'parser' => $subcommandParser
		));

		return $parser;
	}

}
