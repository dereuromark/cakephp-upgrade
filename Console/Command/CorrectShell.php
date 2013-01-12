<?php

App::uses('Folder', 'Utility');
App::uses('UpgradeShell', 'Upgrade.Console/Command');

/**
 * Misc. corrections for my cakephp2.0 app folders (after upgrading from 1.3)
 *
 * They take care of deprecated code:
 * - request
 * - vis
 * - forms
 * - reference
 * - i18n
 * - amp
 *
 * not fully tested and therefore should not be used:
 * - php53
 * - objects
 *
 * app specific (probably not useful for anybody else)
 * - mail
 * - auth
 * - helper
 * - flash
 *
 * @cakephp 2
 * @php 5
 * @author Mark scherer
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 * 2011-11-18 ms
 */
class CorrectShell extends UpgradeShell {


	public function all() {
		$all = array('tests', 'request', 'amp', 'vis', 'reference', 'i18n', 'forms');
		foreach ($all as $name) {
			$this->out(__d('cake_console', 'Running %s', $name));
			$this->{$name}();
		}
		$this->out(__d('cake_console', 'Done!'));
	}


	public function startup() {
		$this->params['git'] = null;
		$this->params['tgit'] = null;
		$this->params['svn'] = null;
		parent::startup();

		$this->params['ext'] = 'php|ctp|thtml|inc|tpl|rst';
		$this->params['dry-run'] = false;
	}



	/**
	 * //TODO: test and verify
	 */
	public function conventions() {
		$this->params['ext'] = 'php|ctp|rst';
		$this->_getPaths();

		$patterns = array(
			/*
			array(
				'...=>... to ... => ...',
				array('/([^\s])=\>([^\s])/', '/([^\s])=\>/', '/=\>([^\s}])/'),
				array('$1 => $2', '$1 =>', '=> $1')
			),
			array(
				'...=... to ... = ...',
				array('/([^\s])=([^\s])/', '/([^\s])=/', '/=([^\s])/'),
				array('$1 = $2', '$1 =', '= $1')
			),
			*/
			/*
			# working but can cause problems if a normal character inside " or ' strings
			array(
				',... to , ...',
				array('/,([^\s])(?<!\\\\)(.*?)/'),
				array(', $1')
			),
			*/
			array(
				'if( to if (',
				array('/\bif\(/', '/\bforeach\(/', '/\bfor\(/', '/\bwhile\(/', '/(?<!\>)\bswitch\(/', '/\)\{/', '/\)\t\{/', '/\)\n\{/', '/\)\n\r\{/'),
				array('if (', 'foreach (', 'for (', 'while (', 'switch (', ') {', ') {', ') {', ') {')
			),
			array(
				'function xyz ( to function xyz(',
				array('/(function [a-zA-Z_\x7f\xff][a-zA-Z0-9_\x7f\xff]+) \(/'),
				array('$1(')
			),
			array(
				'function foo() {',
				'/\bfunction\s+(\w+)\s*\((.*)\)\s+\s+\s+{/',
				'function \1(\2) {',
			),
			array(
				') {',
				'/\)\s+\s+\s+{/',
				') {',
			),
			array(
				') else {',
				'/}\s*else\s*{/',
				'} else {',
			),
			array(
				') elseif (',
				'/}\s*elseif\s*\(/',
				'} elseif (',
			),
			array(
				'foreach ($x as $y => $z)',
				'/\bforeach\s*\((.*?)\s+as\s+(.*?)\s*=\>\s*(.*?)\)/',
				'foreach (\1 as \2 => \3)',
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * careful: not for JS in ctp files!
	 *
	 * //TODO: test and verify
	 */
	public function conventions2() {
		$this->params['ext'] = 'php|ctp|rst';
		$this->_getPaths();

		$patterns = array(
			array(
				'else if => elseif',
				array('/}\s*else\s+if\s*\(/'),
				array('} elseif (')
			),
			array(
				'else if => elseif',
				array('/\belse\s+if\s*\(/'),
				array('elseif (')
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * careful: could break sth
	 *
	 * //TODO: test and verify
	 */
	public function conventions3() {
		$this->params['ext'] = 'php|ctp|rst';
		$this->_getPaths();

		$patterns = array(
			array(
				',array( to , array(',
				array('/,array\(/'),
				array(', array(')
			),
			array(
				',$ to , $',
				array('/,\$/'),
				array(', $')
			),
			array(
				'multiple spaces to 1',
				array('/ {2,}/'),
				array(' ')
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 *
	 */
	public function umlauts() {
		$this->params['ext'] = 'php|ctp|rst';
		$this->_getPaths();

		$patterns = array(
			array(
				'&auml; to ä',
				array('/&auml;/'),
				array('ä')
			),
			array(
				'&Auml; to Ä',
				array('/&Auml;/'),
				array('Ä')
			),
			array(
				'&uuml; to ü',
				array('/&uuml;/'),
				array('ü')
			),
			array(
				'&Uuml; to Ü',
				array('/&Uuml;/'),
				array('Ü')
			),
			array(
				'&ouml; to ö',
				array('/&ouml;/'),
				array('ö')
			),
			array(
				'&Ouml; to Ö',
				array('/&Ouml;/'),
				array('Ö')
			),
			array(
				'&szlig; to ß',
				array('/&szlig;/'),
				array('ß')
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * In 2.0 i18n is easier!
	 *
	 * sprintf(__('Edit %s'), __('Job'))
	 * =>
	 * __('Edit %s', __('Job'))
	 *
	 * 2011-11-17 ms
	 */
	public function i18n() {
		$this->params['ext'] = 'php|ctp|rst';
		$this->_getPaths();

		$patterns = array(
			array(
				'sprintf(__(\'... %s\'), __(\'...\'))',
				'/\bsprintf\(__\(\'(.*?)\'\),\s*__\(\'(.*?)\'\)\)/',
				'__(\'\1\', __(\'\2\'))'
			),
			array(
				'sprintf(__(\'... %s\'),\s*(.*?))',
				'/\bsprintf\(__\(\'(.*?)\'\),\s*(.*?)\)/',
				'__(\'\1\', \2)'
			),
			array(
				'printf(__(\'... %s\'), __(\'...\'))',
				'/\bprintf\(__\(\'(.*?)\'\),\s*__\(\'(.*?)\'\)\)/',
				'echo __(\'\1\', __(\'\2\'))'
			),
			array(
				'printf(__(\'... %s\'),\s*(.*?))',
				'/\bprintf\(__\(\'(.*?)\'\),\s*(.*?)\)/',
				'echo __(\'\1\', \2)'
			),
			// i18n in forms etc
			array(
				'__(\'Edit Foo\') => Edit %s, Foo',
				'/__\(\'Edit (\w+)\'\)/',
				'__(\'Edit %s\', __(\'\1\'))'
			),
			array(
				'__(\'Add Foo\') => Add %s, Foo',
				'/__\(\'Add (\w+)\'\)/',
				'__(\'Add %s\', __(\'\1\'))'
			),
			array(
				'__(\'List Foo\') => List %s, Foo',
				'/__\(\'List (\w+)\'\)/',
				'__(\'List %s\', __(\'\1\'))'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	//TODO: move to MyUpgradeShell
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
	 * in 2.0 all $var should be replaced by $public
	 * also - a framework shouldnt have ANY private methods or attributes
	 * this makes so sense at all. this is covered in the current core
	 * user files should also follow this principle.
	 * Experimental/TODO:
	 * - trying to get all __function calls back to _function
	 */
	public function vis() {
		$this->params['ext'] = 'php|rst';
		$this->_getPaths();

		$patterns = array(
			array(
				'var $__ to private $__',
				'/\bvar \$__/i',
				'private $__'
			),
			array(
				'var $_ to protected $_',
				'/\bvar \$_/i',
				'protected $_'
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
				'private function __',
				'/\bprivate function\b/i',
				'protected function'
			),
			array(
				'function (.*)',
				'/	function (.*)\(/',
				'	public function \1('
			),
			array(
				'static function (.*)',
				'/	static function (.*)\(/',
				'	public static function \1('
			),
			/*
			array(
				'private function __',
				'/\bprivate function __(?!construct|destruct|sleep|wakeup|get|set|call|toString|invoke|set_state|clone|callStatic|isset|unset])\w+\b/i',
				'protected function _\1'
			),
			array(
				'respect $access doc info',
				'/\/**(.*)@access ([public|private|protected])(.*)\/*\s*\s+public \$/i',
				'/**\1'.PHP_EOL.TB.'@access'
			),
			*/
		);
		$skipFiles = array(
		);
		$skipFolders = array(
			'Vendor',
			'vendors',
			'Lib'.DS.'Vendor',
			'Lib'.DS.'vendors',
		);
		$this->_filesRegexpUpdate($patterns, $skipFiles, $skipFolders);
	}

	/**
	 * in 2.0 this is not needed anymore (thank god - forms post now to themselves per default^^)
	 */
	public function forms() {
		$this->params['ext'] = 'ctp';
		$this->_getPaths();

		$patterns = array(
			array(
				'array(\'url\'=>\'/\'.$this->params[\'url\'][\'url\'])',
				'/,\s*array\(\'url\'\s*=\>\s*\'\/\'\s*\.\s*\$this-\>params\[\'url\'\]\[\'url\'\]\)/',
				''
			),
			array(
				'array(\'url\'=>\'/\'.$this->params[\'url\'][\'url\'], array(\'type\'=>\'file\'))',
				'/,\s*array\(\'url\'\s*=\>\s*\'\/\'\s*\.\s*\$this-\>params\[\'url\'\]\[\'url\'\],\s*\'type\'\s*=>\s*\'file\'\)/',
				', array(\'type\' => \'file\')'
			),
			array(
				'\'url\'=>\'/\'.$this->params[\'url\'][\'url\']',
				'/\'url\'\s*=\>\s*\'\/\'\s*\.\s*\$this-\>params\[\'url\'\]\[\'url\'\]/',
				''
			),
		);

		$this->_filesRegexpUpdate($patterns);


		$this->params['ext'] = 'php';
		$this->_getPaths();

		$patterns = array(
			array(
				'->read(null, $id);',
				'/-\>read\(null,\s*\$id\);/',
				'->get($id);'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * deprecated stuff in php5.3
	 * or new features/fixed introduced in php5.3
	 * 2011-11-15 ms
	 */
	public function php53() {
		$this->params['ext'] = 'php';
		$this->_getPaths();

		# maybe:
		# - split => preg_split
		# - ereg(i) => preg_match
		# - ereg(i)_replace => preg_replace
		$patterns = array(
			array(
				'call_user_method(',
				'/\bcall_user_method\(/i',
				'call_user_func('
			),
			array(
				'call_user_method_array(',
				'/\bcall_user_method_array\(/i',
				'call_user_func_array('
			),
			# careful: sometimes self:: can actually be used on purpose!
			array(
				'self:: to new static::',
				'/\bself\:\:/',
				'static::',
			)
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * RequestHandler stuff is now mainly handled by Request Object
	 * 2011-11-15 ms
	 */
	public function request() {
		$this->params['ext'] = 'php';
		$this->_getPaths();

		$patterns = array(
			array(
				'RequestHandlerComponent::getClientIP() to CakeRequest::clientIP()',
				'/\bRequestHandlerComponent\:\:getClientIP\(\)/i',
				'CakeRequest::clientIP()'
			),
			array(
				'if (!empty($this->request->data)) to if($this->request->is(\'post\'))',
				'/\bif\s*\(!empty\(\$this-\>request-\>data\)\)/',
				//'if ($this->request->is(\'post\'))'
				'if ($this->Common->isPosted())'
			),
			//TODO: test
			array(
				'delete post requirement',
				'/\bfunction (.*?)delete\(\$id = null\)\s*{\s*\s*\s*\if \(empty/',
				'function \1delete($id = null) {
		if (!$this->Common->isPosted()) {
			throw new MethodNotAllowedException();
		}
		if (empty'
			),
			array(
				'revert $this->request->is(\'post\'))',
				'/\$this-\>request-\>is\(\'post\'\)/',
				'$this->Common->isPosted()'
			),
			array(
				'revert $this->request->is(\'put\'))',
				'/\$this-\>request-\>is\(\'put\'\)/',
				'$this->Common->isPosted()'
			),
			array(
				'$this->Common->isPosted() || $this->Common->isPosted()',
				'/\$this-\>Common-\>isPosted\(\) \|\| \$this-\>Common-\>isPosted\(\)/',
				'$this->Common->isPosted()'
			),
			array(
				'correct redirect',
				'/\$this-\>Common-\>flashMessage\(__\(\'record (edit|add) %s saved\',\s*h\(\$var\)\),\s*\'success\'\);
				\$this-\>Common-\>autoRedirect\(/',
				'$this->Common->flashMessage(__(\'record \1 %s saved\', h($var)), \'success\');
				$this->Common->postRedirect('
			),
			array(
				'update $this->Common->isPost()',
				'/\$this-\>Common-\>isPost\(\)/',
				'$this->Common->isPosted()'
			),
		);
		$this->_filesRegexpUpdate($patterns);


		$this->params['ext'] = 'ctp';
		$this->_getPaths();

		$patterns = array(
			array(
				'Html->link() to Form->postLink()',
				'/\$this-\>Html-\>link\(\$this-\>Common-\>icon\(\'delete\'/',
				'$this->Form->postLink($this->Common->icon(\'delete\''
			),
			array(
				'Html->link() to Form->postLink()',
				'/\$this-\>Html-\>link\(\$this-\>Format-\>icon\(\'delete\'/',
				'$this->Form->postLink($this->Format->icon(\'delete\''
			),
			array(
				'Html->link() to Form->postLink()',
				'/\$this-\>Html-\>link\(__\(\'Delete/',
				'$this->Form->postLink(__(\'Delete'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * AuthExt back to Auth (thx to aliasing!)
	 * 2011-11-17 ms
	 */
	public function auth() {
		$this->params['ext'] = 'php';
		$this->_getPaths();

		$patterns = array(
			array(
				'->AuthExt to $this->Auth',
				'/-\>AuthExt\b/',
				'->Auth'
			),
			array(
				'public $AuthExt to public $Auth',
				'/\bpublic \$AuthExt\b/',
				'public $Auth'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * from component to lib
	 * 2011-11-15 ms
	 */
	public function mail() {
		$this->params['ext'] = 'php';
		$this->_getPaths();

		$patterns = array(
			array(
				'App::import(\'Component\', \'Email\');',
				'/App\:\:import\(\'Component\',\s*\'Email\'\)/',
				'App::uses(\'EmailLib\', \'Tools.Lib\')'
			),
			array(
				'App::import(\'Component\', \'Tools.Mailer\');',
				'/App\:\:import\(\'Component\',\s*\'Tools\.Mailer\'\)/',
				'App::uses(\'EmailLib\', \'Tools.Lib\')'
			),
			array(
				'$this->Email = new EmailComponent();',
				'/\$this-\>Email\s*=\s*new EmailComponent\((.*?)\);/',
				'$this->Email = new EmailLib();'
			),
			array(
				'$this->Email = new MailerComponent($this);',
				'/\$this-\>Email\s*=\s*new MailerComponent\(\$this\);/',
				'$this->Email = new EmailLib();'
			),
			array(
				'$this->Email->from(...);',
				'/\$this-\>Email-\>from\(Configure\:\:read\(\'Config\.no_reply_email\'\),\s*Configure\:\:read\(\'Config\.no_reply_emailname\'\)\);/',
				''
			),
			array(
				'$this->Email->sendAs(...);',
				'/\$this-\>Email-\>sendAs\(\'(.*?)\'\);/',
				''
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	protected function _getPaths() {
		if (!empty($this->args)) {
			$this->_paths = $this->args[0];
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = App::pluginPath($this->params['plugin']);
		} else {
			$this->_paths = APP;
		}

		if (empty($this->_paths)) {
			$this->error('Please pass working dir as param (cake reference /absDir)');
		} else {
			$this->_paths = (array)$this->_paths;
		}
	}

	public function amp() {
		$this->params['ext'] = 'php|rst';
		$this->_getPaths();

		$patterns = array(
			array(
				'=& $this-> -> = $this->',
				'/=\s*\& \$this\-\>/',
				'= $this->'
			),
			array(
				'=& to =',
				'/=\s*\&\s/',
				'= '
			),

			array(
				'=& $ to = $',
				'/=\s*\&\s*\$[A-Z]/',
				'= $'
			),
		);
		$skipFiles = array(
		);
		$skipFolders = array(
		);
		$this->_filesRegexpUpdate($patterns, $skipFiles, $skipFolders);
	}

	/**
	 * move some methods from CommonHelper to FormatHelper
	 */
	public function helper() {
		$this->params['ext'] = 'ctp';
		$this->_getPaths();

		$methods = array(
			'thumbs', 'neighbors', 'addIcon', 'genderIcon', 'customIcon', 'countryIcon', 'importantIcon',
			'icon', 'cIcon', 'showStars', 'languageFlags', 'encodeEmail', 'encodeEmailUrl', 'encodeText',
			'yesNo', 'priorityIcon', 'ok',
		);
		$patterns = array();
		foreach ($methods as $method) {
			$patterns[] = array(
				$method.'()',
				'/-\>Common-\>'.$method.'\(/',
				'->Format->'.$method.'(',
			);
		}

		$methods = array(
			'currency',
		);
		$patterns = array();
		foreach ($methods as $method) {
			$patterns[] = array(
				$method.'()',
				'/-\>Common-\>'.$method.'\(/',
				'->Numeric->'.$method.'(',
			);
		}

		$methods = array(
			'url', 'link'
		);
		foreach ($methods as $method) {
			$patterns[] = array(
				$method.'()',
				'/-\>GoogleMapV3-\>'.$method.'\(/',
				'->GoogleMapV3->map'.ucfirst($method).'(',
			);
		}

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * html5
	 */
	public function html5() {
		$this->params['ext'] = 'ctp';
		$this->_getPaths();

		$patterns[] = array(
			'doctypes',
			'/\<!DOCTYPE\s*.*\>/i',
			'<!DOCTYPE html>',
		);
		$this->_filesRegexpUpdate($patterns);
	}

	public function reference() {
		$this->params['ext'] = 'php|rst';
		$this->_getPaths();

		$patterns = array(
			array(
				'(&$Model',
				'/\(\&\$Model\b/',
				'(Model $Model'
			),
			array(
				'(&$model',
				'/\(\&\$model\b/',
				'(Model $model'
			),
			array(
				'(&$Controller',
				'/\(\&\$Controller\b/',
				'(Controller $Controller'
			),
			array(
				'(&$controller',
				'/\(\&\$controller\b/',
				'(Controller $controller'
			),
			array(
				'(&$Component',
				'/\(\&\$Component\b/',
				'(Component $Component'
			),
			array(
				'(&$component',
				'/\(\&\$component\b/',
				'(Component $component'
			),
			array(
				'=& ClassRegistry::',
				'/=\s*&\s*ClassRegistry\:\:/',
				'= ClassRegistry::'
			),
			/*
			array(
				'=& $Controller -> = $Controller',
				'/=\& \$Controller/i',
				'= $Controller'
			),
			*/
			# careful: can destroy variable access inside methods
			array(
				'function *($Model',
				'/function (.*)\(\$Model\b/',
				'function \1(Model $Model'
			),
			array(
				'function *($model',
				'/function (.*)\(\$model\b/',
				'function \1(Model $model'
			),
			array(
				'function *($Controller',
				'/function (.*)\(\$Controller\b/',
				'function \1(Controller $Controller'
			),
			array(
				'function *($controller',
				'/function (.*)\(\$controller\b/',
				'function \1(Controller $controller'
			),
			array(
				'function *($Component',
				'/function (.*)\(\$Component\b/',
				'function \1(Component $Component'
			),
			array(
				'function *($component',
				'/function (.*)\(\$component\b/',
				'function \1(Component $component'
			),
			array(
				'ComponentCollection $collection',
				'/\ComponentCollection \$collection/',
				'ComponentCollection $collection'
			),
			array(
				'ComponentCollection $Collection',
				'/\ComponentCollection \$Collection/',
				'ComponentCollection $Collection'
			),
			/*
			array(
				'parent::__construct($collection to parent::__construct($Collection',
				'/parent\:\:\_\_construct\(\$collection/',
				'parent::__construct($Collection'
			),

			array(
				'this->_Collection = $Collection;',
				'/this-\>\_Collection = \$collection/i',
				'this->_Collection = $Collection'
			),
			array(
				'$model -> $Model',
				'/\$model\b/',
				'$Model'
			),
			array(
				'$controller -> $Controller',
				'/\$controller\b/',
				'$Controller'
			),
			array(
				'$component -> $Component',
				'/\$component\b/',
				'$Component'
			),
			array(
				'$this->model -> $this->Model',
				'/\$this\-\>model\b/',
				'$this->Model'
			),
			array(
				'$this->controller -> $this->Controller',
				'/\$this\-\>controller\b/',
				'$this->Controller'
			),
			array(
				'$this->component -> $this->Component',
				'/\$this\-\>component\b/',
				'$this->Component'
			),
			array(
				'$this->renderAs($controller,',
				'/\$this->renderAs\(\$controller,/',
				'$this->renderAs($Controller,',
			),
			array(
				'$controller->',
				'/\$controller-\>/',
				'$Controller->',
			),
			*/
			array(
				'function fullTableName(Model $Model',
				'/function fullTableName\(Model \$Model/i',
				'function fullTableName($model',
			),
			/*
			array(
				'$controller = new Controller',
				'/\$controller = new /',
				'$Controller = new ',
			),
			array(
				'return $controller',
				'/return \$controller/',
				'return $Controller',
			),
			array(
				'if (is_object($controller))',
				'/if \(is_object\(\$controller\)\)/',
				'if (is_object($Controller))',
			),
			*/

			/*
			array(
				'fetchAssociated($model',
				'/fetchAssociated\(\$model/',
				'fetchAssociated($Model',
			),
			array(
				'AssociationQuery($model',
				'/AssociationQuery\(\$model/',
				'AssociationQuery($Model',
			),
			array(
				'$model->',
				'/\$model-\>/',
				'$Model->',
			),

			array(
				'Model $model',
				'/Model \$model/',
				'Model $Model',
			),
			*/
			/*
			array(
				'Model $linkModel',
				'/Model \$linkModel/',
				'Model $linkModel',
			),
			array(
				'$linkModel',
				'/\$linkModel\b/',
				'$linkModel',
			),
			array(
				', &$linkModel',
				'/, \&\$linkModel/i',
				', Model $linkModel',
			),
			*/
			array(
				'function index(Model $Model',
				'/function index\(Model \$Model/i',
				'function index($model',
			),
			/*
			array(
				'$model = ClassRegistry',
				'/\$model = ClassRegistry/',
				'$Model = ClassRegistry',
			),
			array(
				'$model = new',
				'/\$model = new /',
				'$Model = new ',
			),
			array(
				'$table = $model->tablePrefix . $model->table',
				'/\$table = \$Model-\>tablePrefix . \$Model-\>table/',
				'$table = $model->tablePrefix . $model->table',
			),
			array(
				'$this->fields($model',
				'/\$this-\>fields\(\$model/',
				'$this->fields($Model',
			),
			array(
				' $this->fullTableName($model)',
				'/\$this-\>fullTableName\(\$model\)/',
				'$this->fullTableName($Model)',
			),
			array(
				'filterResults($resultSet, $model',
				'/filterResults\(\$resultSet, \$model/',
				'filterResults($resultSet, $Model',
			),
			array(
				'$db->queryAssociation($model',
				'/\$db-\>queryAssociation\(\$model/',
				'$db->queryAssociation($Model',
			),
			array(
				'		$model',
				'/		\$model\b/',
				'		$Model\b',
			),
			array(
				'$model',
				'/\),
				\$model/',
				'),
				$Model',
			),
			array(
				'x',
				'/get_class\(\$model\)/',
				'get_class($Model)',
			),
			array(
				'x',
				'/if \(is_object\(\$model\) && \$Model/',
				'if (is_object($Model) && $Model',
			),
			array(
				'x',
				'/is_object\(\$model\) \? \$Model/',
				'is_object($Model) ? $Model',
			),
			array(
				'x',
				'/array\(\$operator =\> array\(\$key =\> \$value\)\), true, \$model/',
				'array($operator => array($key => $value)), true, $Model',
			),
			array(
				'x',
				'/\$model = \$this-\>getObject/',
				'$Model = $this->getObject',
			),
			array(
				'x',
				'/if \(is_object\(\$model\) && \(is_a\(\$model, \$class/',
				'if (is_object($Model) && (is_a($Model, $class',
			),
			array(
				'x',
				'/\$duplicate = \$model;/',
				'$Duplicate = $Model;',
			),
			array(
				'x',
				'/return \$duplicate/',
				'return $duplicate',
			),
			array(
				'x',
				'/\$this-\>_addToWhitelist\(\$model/',
				'$this->_addToWhitelist($Model',
			),
			array(
				'x',
				'/\$this-\>fullTableName\(\$model/',
				'$this->fullTableName($Model',
			),
			array(
				'x',
				'/\$duplicate = false;/',
				'$Duplicate = false;',
			),
			array(
				'method_exists($model',
				'/method_exists\(\$model/',
				'method_exists($Model'
			),
			array(
				'$this->node($model',
				'/\$this-\>node\(\$model/',
				'$this->node($Model'
			),
			array(
				'afterSave($model',
				'/afterSave\(\$model/',
				'afterSave($Model'
			),
			array(
				'x',
				'/}
			unset\(\$model\);/',
				'}
			unset($Model);',
			),
			*/
			array(
				'_parseKey(Model $model',
				'/_parseKey\(Model \$model/',
				'_parseKey($model'
			),
			array(
				'array(Controller $Controller));',
				'/array\(Controller \$Controller\b/',
				'array(&$Controller'
			),
			array(
				'array(Controller $controller));',
				'/array\(Controller \$controller\b/',
				'array(&$controller'
			),
		);
		$skipFiles = array(
			'ComponentCollection.php',
			'BehaviorCollection.php',
			'FixtureTask.php',
			'FormHelper.php',
			'PaginatorHelper.php',
			'ControllerTestCase.php',
			'Router.php',
			'JsHelperTest.php',
			'JqueryEngineHelperTest.php'
		/*

			'Mysql.php',
			'BakeShell.php',
			'ConsoleShell.php',


			'ContainableBehaviorTest.php'
		*/
		);
		$skipFolders = array(
			'TODO__'
		);
		$this->_filesRegexpUpdate($patterns, $skipFiles, $skipFolders);


		$file = $this->_paths[0].DS.'View'.DS.'View.php';
		if (file_exists($file)) {
			$content = file_get_contents($file);
			$content = str_replace('__construct(Controller $controller)', '__construct(Controller $controller = null)', $content);
			file_put_contents($file, $content);
			/*
			array(
				'x',
				'/construct\(Controller \$Controller\)/i',
				'construct(Controller $Controller = null)',
			);
			$this->_updateFile($file, $patterns);
			*/
		}	else {
			//die('FILE NOT EXISTS');
		}
	}

	/**
	 * correct flash messages
	 * todo: move to MyUpgrade
	 */
	public function flash() {
		$this->params['ext'] = 'php';
		$this->_getPaths();
		$patterns = array(
			array(
				'$this->Session->setFlash(...)',
				'/-\>Session-\>setFlash\((.*)\)/',
				'->Common->flashMessage(\1)'
			),
			array(
				'$this->Session->setFlash(...)',
				'/-\>Common-\>flashMessage\(__\(\'Invalid (.*)\'\)\)/',
				'->Common->flashMessage(__(\'Invalid \1\'), \'error\')'
			),
			array(
				'$this->Session->setFlash(...)',
				'/-\>Common-\>flashMessage\(__\(\'(.*) has been saved\'\)\)/',
				'->Common->flashMessage(__(\'\1 has been saved\'), \'success\')'
			),
			array(
				'$this->Session->setFlash(...)',
				'/-\>Common-\>flashMessage\(__\(\'(.*) could not be saved(.*)\'\)\)/',
				'->Common->flashMessage(__(\'\1 could not be saved\2\'), \'error\')'
			),
			# tmp to qickly find unmatching ones
			array(
				'$this->Session->setFlash(...)',
				'/-\>Common-\>flashMessage\(__\(\'(.*)\'\)\)/',
				'->Common->flashMessage(__(\'\1\'), \'xxxxx\')'
			),
		);
		$skipFiles = array();
		$this->_filesRegexpUpdate($patterns, $skipFiles);
	}

	/**
	 * correct brackets: class X extends Y {
	 *
	 */
	public function classes() {
		$this->params['ext'] = 'php|rst';
		$this->_getPaths();
		$patterns = array(
			array(
				'class ... { (same row)',
				'/\bclass\s+(.*?)\s+\s*{/',
				'class \1 {'
			),
		);
		$skipFiles = array();
		$this->_filesRegexpUpdate($patterns, $skipFiles);
	}

	/**
	 * Update legacy stuff for 2.0.
	 *
	 * - Replaces App::import() with App::uses() - mainly Utility classes.
	 *
	 * @return void
	 */
	public function _objects() {
		$this->_getPaths();

		//die(print_r($this->_paths, true));
		$patterns = array(
			array(
				'$component -> $Component',
				'/\$component\b/',
				'$Component'
			),
			array(
				'$controller -> $Controller',
				'/\$controller\b/',
				'$Controller'
			),
			array(
				'$collection -> $Collection',
				'/\$collection\b/',
				'$Collection'
			),
		);
		$skipFiles = array(
			'ControllerTask.php', 'BakeShell.php', 'ControllerTask',
			'ControllerTaskTest.php', 'ViewTaskTest.php', 'AppTest.php',
			'ControllerTestCase.php', 'missing_action.ctp', 'private_action.ctp',
			'controller.ctp', 'Router.php',
			'ComponentCollection.php', # !!!
		);

		$this->_filesRegexpUpdate($patterns, $skipFiles);

		# manually adjust dispatcher
		$patterns = array(
			array(
				'= $controller =',
				'/= \$Controller =/',
				'= $controller ='
			),
			array(
				'if ($pluginPath . $controller)',
				'/if \(\$pluginPath \. \$Controller\)/',
				'if ($pluginPath . $controller)'
			),
			array(
				'$controller = Inflector',
				'/\$Controller = Inflector/',
				'$controller = Inflector'
			),
			array(
				'$class = $controller .',
				'/\$class = \$Controller \./',
				'$class = $controller .'
			),
		);
		$this->_paths[0] = $this->_paths[0].DS.'Routing';
		//die(print_r($this->_paths, true));
		$skipFiles = array('Router.php');
		$this->_filesRegexpUpdate($patterns, $skipFiles);
	}


	/**
	 * Updates files based on regular expressions.
	 *
	 * @param array $patterns Array of search and replacement patterns.
	 * @return void
	 */
	protected function _filesRegexpUpdate($patterns, $skipFiles = array(), $skipFolders = array()) {
		$this->_findFiles($this->params['ext'], $skipFolders);
		foreach ($this->_files as $file) {
			if (in_array(pathinfo($file, PATHINFO_BASENAME), $skipFiles)) {
				continue;
			}
			$this->out(__d('cake_console', 'Updating %s...', $file), 1, Shell::VERBOSE);
			$this->_updateFile($file, $patterns);
		}
	}


/**
 * Searches the paths and finds files based on extension.
 *
 * @param string $extensions
 * @return void
 */
	protected function _findFiles($extensions = '', $skipFolders = array()) {
		foreach ($this->_paths as $path) {
			if (substr($path, -1) !== DS) {
				$path .= DS;
			}
			if (!is_dir($path)) {
				continue;
			}
			if (!empty($skipFolders) && in_array(basename($path), $skipFolders)) {
				continue;
			}
			$this->_files = array();
			$Iterator = new RegexIterator(
				new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)),
				'/^.+\.(' . $extensions . ')$/i',
				RegexIterator::MATCH
			);
			foreach ($Iterator as $file) {
				# Iterator processes plugins even if not asked to
				$excludes = array();
				if (empty($this->params['plugin'])) {
					$excludes = array('Plugin', 'plugins');
				}
				$excludes = am($excludes, $skipFolders);
				//echo returns($excludes); die();

				$isIllegalPath = false;
				foreach ($excludes as $exclude) {
					if (strpos($file->getPathname(), $path . $exclude . DS) === 0) {
						$isIllegalPath = true;
						break;
					}
				}
				if ($isIllegalPath) {
					continue;
				}
				//$this->out( $file->getPathname() ); continue;

				if ($file->isFile()) {
					$this->_files[] = $file->getPathname();
				}
			}
		}
	}


	public function getOptionParser() {
		$subcommandParser = array(
			'options' => array(
				'plugin' => array(
					'short' => 'p',
					'help' => __d('cake_console', 'The plugin to update. Only the specified plugin will be updated.'),
					'default' => '',
				),
				'dry-run'=> array(
					'short' => 'd',
					'help' => __d('cake_console', 'Dry run the update, no files will actually be modified.'),
					'boolean' => true
				),
				'log'=> array(
					'short' => 'l',
					'help' => __d('cake_console', 'Log all ouput to file log.txt in TMP dir'),
					'boolean' => true
				)
			)
		);

		return parent::getOptionParser()
			->description(__d('cake_console', "A shell to help automate upgrading from CakePHP 1.3 to 2.0. \n" .
				"Be sure to have a backup of your application before running these commands."))
			->addSubcommand('all', array(
				'help' => __d('cake_console', 'Run all correctional commands'),
				'parser' => $subcommandParser
			))
			->addSubcommand('objects', array(
				'help' => __d('cake_console', 'Update objects'),
				'parser' => $subcommandParser
			))
			->addSubcommand('reference', array(
				'help' => __d('cake_console', 'Update reference'),
				'parser' => $subcommandParser
			))
			->addSubcommand('amp', array(
				'help' => __d('cake_console', '=& fix'),
				'parser' => $subcommandParser
			))
			->addSubcommand('request', array(
				'help' => __d('cake_console', 'clientIp correction'),
				'parser' => $subcommandParser
			))
			->addSubcommand('i18n', array(
				'help' => __d('cake_console', 'i18n simplifications'),
				'parser' => $subcommandParser
			))
			->addSubcommand('vis', array(
				'help' => __d('cake_console', 'visibility (public, protected)'),
				'parser' => $subcommandParser
			))
			->addSubcommand('forms', array(
				'help' => __d('cake_console', 'post to itself by default'),
				'parser' => $subcommandParser
			))
			->addSubcommand('conventions', array(
				'help' => __d('cake_console', 'usual php5/cakephp2 conventions for coding'),
				'parser' => $subcommandParser
			))
			->addSubcommand('conventions2', array(
				'help' => __d('cake_console', 'usual php5/cakephp2 conventions for coding'),
				'parser' => $subcommandParser
			))
			->addSubcommand('conventions3', array(
				'help' => __d('cake_console', 'usual php5/cakephp2 conventions for coding'),
				'parser' => $subcommandParser
			))
			# custom app stuff (not for anyone else)
			->addSubcommand('helper', array(
				'help' => __d('cake_console', 'helper fix'),
				'parser' => $subcommandParser
			))
			->addSubcommand('auth', array(
				'help' => __d('cake_console', 'auth fix'),
				'parser' => $subcommandParser
			))
			->addSubcommand('classes', array(
				'help' => __d('cake_console', 'classes'),
				'parser' => $subcommandParser
			))
			->addSubcommand('mail', array(
				'help' => __d('cake_console', 'mail fix'),
				'parser' => $subcommandParser
			))
			->addSubcommand('flash', array(
				'help' => __d('cake_console', 'flash messages'),
				'parser' => $subcommandParser
			))
			->addSubcommand('umlauts', array(
				'help' => __d('cake_console', 'umlauts fixes in utf8'),
				'parser' => $subcommandParser
			))
			->addSubcommand('html5', array(
				'help' => __d('cake_console', 'html5 updates'),
				'parser' => $subcommandParser
			));
	}


}
