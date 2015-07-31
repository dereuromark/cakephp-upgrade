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
 * - tests
 *
 * app specific (probably not useful for anybody else)
 * - mail
 * - auth
 * - helper
 * - flash
 *
 * @cakephp 2
 * @author Mark scherer
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class CorrectShell extends UpgradeShell {

	public $unstable = array('php53', 'conventions_experimental', 'variables', 'header', 'specialchars');

	/**
	 * CorrectShell::all()
	 *
	 * @return void
	 */
	public function all() {
		$except = get_class_methods('UpgradeShell');

		$all = get_class_methods($this);
		$all = array_diff($all, $except);
		foreach ($all as $key => $name) {
			if (strpos($name, '_') === 0 || in_array($name, am($this->unstable, array('stable', 'unstable')))) {
				unset($all[$key]);
			}
		}

		foreach ($all as $name) {
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

			$this->out(sprintf('Running %s', $name));
			$this->{$name}();
		}
		$this->out('Done!');
	}

	/**
	 * CorrectShell::stable()
	 *
	 * @return void
	 */
	public function stable() {
		$all = array('tests', 'request', 'amp', 'vis', 'reference', 'i18n', 'forms');
		foreach ($all as $name) {
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

			$this->out(sprintf('Running %s', $name));
			$this->{$name}();
		}
		$this->out('Done!');
	}

	/**
	 * CorrectShell::unstable()
	 *
	 * @return void
	 */
	public function unstable() {
		$all = $this->unstable;
		foreach ($all as $name) {
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

			$this->out(sprintf('Running %s', $name));
			$this->{$name}();
		}
		$this->out('Done!');
	}

	/**
	 * CorrectShell::startup()
	 *
	 * @return void
	 */
	public function startup() {
		$this->params['git'] = null;
		$this->params['tgit'] = null;
		$this->params['svn'] = null;
		parent::startup();

		//$this->params['ext'] = 'php|ctp';
		$this->params['dry-run'] = false;
	}

	/**
	 * CorrectShell::_setExt()
	 *
	 * @param string $ext Extensions separated by |, e.g. 'php|ctp'
	 * @return void
	 */
	protected function _setExt($ext = null) {
		if ($ext === null) {
			$ext = 'php|ctp';
		}
		$this->params['ext'] = $ext;
	}

	public function tests() {
		$this->params['ext'] = 'php';
		$this->_getPaths();

		$patterns = array(
			array(
				// For assertEquals, assertNotEquals, assertSame, assertNotSame, ...
				// Unfortunatetly, assertTags() is the only one with the opposite order
				'assert*($is, $expected) to assert*($expected, $is)',
				array('/\bassert((?!tags)\w+)\(\$(\w+),\s*\$expected\)/i'),
				array('assert\1($expected, $\2)')
			),
			array(
				'assertSame(..., true) => assertTrue(...)',
				array('/\bassertSame\((.*?),\s*true\)/i'),
				array('assertTrue(\1)')
			),
			array(
				'assertSame(..., false) => assertFalse(...)',
				array('/\bassertSame\((.*?),\s*false\)/i'),
				array('assertFalse(\1)')
			),
			array(
				'assertSame(..., null) => assertNull(...)',
				array('/\bassertSame\((.*?),\s*null\)/i'),
				array('assertNull(\1)')
			),
			/*
			array(
				'$this->assertNotSame(..., true) => assertFalse(...)',
				array('/\bassertNotSame\((.*?),\s*true\)/i'),
				array('assertFalse(\1)')
			),
			*/
			array(
				'setup() to setUp()',
				'/public\s+function\s+setUp\(\)/i',
				'public function setUp()'
			),
			array(
				'teardown() to tearDown()',
				'/public\s+function\s+tearDown\(\)/i',
				'public function tearDown()'
			),
			array(
				'parent::setup() to parent::setUp()',
				'/parent\:\:setUp\(\)/i',
				'parent::setUp()'
			),
			array(
				'parent::teardown() to parent::tearDown()',
				'/parent\:\:tearDown\(\)/i',
				'parent::tearDown()'
			),
		);
		$this->_filesRegexpUpdate($patterns);

		$patterns = array(
			array(
				'setUp() with parent call',
				'/public\s+function\s+setUp\(\)\s*{/i',
				'setUp'
			),
			array(
				'tearDown() with parent call',
				'/public\s+function\s+tearDown\(\)\s*{/i',
				'tearDown'
			),
		);
		$this->_filesRegexpUpdate($patterns, array(), array(), null, '_updateFileTests');
	}

	/**
	 * CorrectShell::_updateFileTests()
	 *
	 * @param string $file
	 * @param array $patterns
	 * @param string $callback
	 * @return void
	 */
	protected function _updateFileTests($file, $patterns, $callback = null) {
		$contents = $fileContent = file_get_contents($file);

		foreach ($patterns as $pattern) {
			$this->out(sprintf(' * Updating %s', $pattern[0]), 1, Shell::VERBOSE);
			//echo debug($contents);
			preg_match($pattern[1], $contents, $matches);
			if (!$matches) {
				continue;
			}

			if (strpos($contents, 'parent::' . $pattern[2] . '();') !== false) {
				continue;
			}

			$replacement = 'public function ' . $pattern[2] . '() {' . PHP_EOL . "\t\t" . 'parent::' . $pattern[2] . '();';
			$contents = preg_replace($pattern[1], $replacement, $contents);
		}

		$this->out(sprintf('Done updating %s', $file), 1, Shell::VERBOSE);
		if (!$this->params['dry-run'] && $contents !== $fileContent) {
			file_put_contents($file, $contents);
		}
	}

	/**
	 * //TODO: test and verify
	 * @return void
	 */
	public function conventions() {
		$this->params['ext'] = 'php|ctp';
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
				'/\)\s*\s+\s+{/',
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
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function conventions2() {
		$this->params['ext'] = 'php|ctp';
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
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function conventions3() {
		$this->params['ext'] = 'php|ctp';
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
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * careful: could break sth
	 *
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function conventions4() {
		$this->params['ext'] = 'php';
		$this->_getPaths();

		$patterns = array(
			array(
				' != to !== for strings',
				array('/\s+\!\=\s+"/'),
				array(' !== "')
			),
			array(
				' != to !== for strings',
				array('/\s+\!\=\s+\'/'),
				array(' !== \'')
			),
			array(
				' == to === for strings',
				array('/\s+\=\=\s+"/'),
				array(' === "')
			),
			array(
				' == to === for strings',
				array('/\s+\=\=\s+\'/'),
				array(' === \'')
			),
			array(
				'double quote strings to single quote strings for comparison',
				array('/\=\s+"(\w*)"/'),
				array('= \'\1\'')
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * CorrectShell::conventions_experimental()
	 *
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function conventions_experimental() {
		$this->params['ext'] = 'php|ctp';
		$this->_getPaths();

		$patterns = array(
			/*
			array(
				'multiple spaces to 1',
				array('/ {2,}/'),
				array(' ')
			),
			*/
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Careful: in some places this is actually desired
	 *
	 * @return void
	 */
	public function umlauts() {
		$this->params['ext'] = 'php|ctp';
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
	 * Careful: in some places this is actually desired
	 *
	 * @return void
	 */
	public function specialchars() {
		$this->params['ext'] = 'php|ctp';
		$this->_getPaths();

		$patterns = array(
			array(
				'‘ to \'',
				array('/‘/'),
				array('\'')
			),
			array(
				'’ to \'',
				array('/’/'),
				array('\'')
			),
			array(
				'“ to "',
				array('/“/'),
				array('"')
			),
			array(
				'” to "',
				array('/”/'),
				array('"')
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
	 * @return void
	 */
	public function i18n() {
		$this->params['ext'] = 'php|ctp';
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
			[
				'__d(\'cake_console\', ...) Removal',
				'/__d\(\'cake_console\',\s\'(.+?)\'\)/',
				'\'\1\''
			]
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Some speed improvements
	 * - strict null checks should be used instead of is_null()
	 * - strlen() comparisons should be strict
	 *
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function performance() {
		$this->params['ext'] = 'php|ctp';
		$this->_getPaths();

		$patterns = array(
			array(
				'\b!is_null() to !==',
				'/(\!is_null\()(.+?)(\))/i',
				'\2 !== null'
			),
			array(
				'\bis_null() to ===',
				'/\b(is_null\()(.+?)(\))/i',
				'\2 === null'
			),
			// Careful, can grab too much
			array(
				'strlen() == to strlen() ===',
				'/strlen\((.*?)\)\s+\=\=\s+/',
				'strlen(\1) === '
			),
			array(
				'strlen() != to strlen() !==',
				'/strlen\((.*?)\)\s+\!\=\s+/',
				'strlen(\1) !== '
			),
			array(
				'isset(...) && !empty(...) to just !empty(...)',
				'/\bisset\(.+\)\s*\&\&\s*\!empty\((.+)\)/',
				'!empty(\1)'
			),
			array(
				'!isset(...) || empty(...) to just empty(...)',
				'/\!isset\(.+\)\s*\|\|\s*empty\((.+)\)/',
				'empty(\1)'
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * In 2.0 all $var should be replaced by $public
	 * also - a framework shouldnt have ANY private methods or attributes
	 * this makes so sense at all. this is covered in the current core
	 * user files should also follow this principle.
	 * Experimental/TODO:
	 * - trying to get all __function calls back to _function
	 *
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function vis() {
		$this->params['ext'] = 'php';
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
			*/
		);
		$skipFiles = array(
		);
		$skipFolders = array(
			'Vendor',
			'vendors',
			'Lib' . DS . 'Vendor',
			'Lib' . DS . 'vendors',
		);
		$this->_filesRegexpUpdate($patterns, $skipFiles, $skipFolders);
	}

	/**
	 * CorrectShell::whitespace()
	 *
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function whitespace() {
		$this->params['ext'] = 'php';
		$this->_getPaths();

		$patterns = array(
			array(
				'3 or more newlines to 2',
				"/\n\n+/",
				"\n\n"
				//"\r\r", // "\n\n", "\r\n\r\n")
			),
			array(
				'3 or more newlines to 2',
				"/\r\r+/",
				"\r\r"
			),
			array(
				'3 or more newlines to 2',
				"/\r\n[\r\n]+/",
				"\r\n\r\n"
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * CorrectShell::doc_blocks()
	 *
	 * @return void
	 */
	public function doc_blocks() {
		$this->params['ext'] = 'php|ctp|css|js|bat|ini';
		$this->_getPaths();

		// Removal of @package etc
		$patterns = array(
			array(
				'@filesource ... removal',
				'/\s+\@filesource\s*\*/',
				''
			),
			array(
				'@package ... removal',
				'/\s+\@package\s+\s*([a-z.-_ ]+)\s*\*/i',
				''
			),
			array(
				'@subpackage ... removal',
				'/\s+\@subpackage\s+\s*([a-z.-_ ]+)\s*\*/i',
				''
			),
			array(
				'@access ... removal',
				'/\s+\@access\s+\s*\w*\s*\*/i',
				''
			),
			array(
				'PHP version ... removal',
				'/ \* PHP version.*?\s*\s* \*\s*\s* \*/i',
				' *'
			),
		);

		$this->_filesRegexpUpdate($patterns);

		// Unification of @param
		$this->params['ext'] = 'php|ctp';
		$patterns = array(
			array(
				'@param string $string. Some string ... @param string $string Some string.',
				'/\@param (\w+) \$(\w+)\. (.+)\b/',
				'@param \1 $\2 \3'
			),
		);

		$this->_filesRegexpUpdate($patterns, array(), array(), 'docBlock');

		// Unification of doc block beginnings
		$this->params['ext'] = 'php|ctp';
		$patterns = array(
			array(
				'/** * some thing ... /* * Some thing',
				'/\/\*\*(\s+\s*) \* (\w+) /sm',
			),
		);

		//$this->_filesRegexpUpdate($patterns, array(), array(), 'docBlockBeginning');

		$patterns = array(
			array(
				'@param/return/var boolean',
				'/\@(\w+) ([\w\\|\\\\]*?)boolean\b/i',
				'@\1 \2bool'
			),
			array(
				'@param/return/var integer',
				'/\@(\w+) ([\w|\\\\]*?)integer\b/i',
				'@\1 \2int'
			),
			array(
				'@return \w+ $...',
				'/\@return (\w+) \$(\w+)\b/i',
				'@return \1 \2'
			),
			array(
				'multiple empty doc block rows to single ones',
				'/\ \*(\s+) \*(\s+ \*)+/sm',
				' *\1 *'
			),
			array(
				'* @var type Something .. * @var type',
				'/\ \* @var ([\w\\|\\\\]*?) (.*)/i',
				' * @var \1'
			),
		);

		$this->_filesRegexpUpdate($patterns);

		$patterns = array(
			array(
				'@param/return types Sentence.',
				'/\* \@(param|return) ([\w\\|\\\\]+?) (.*)/i',
			),
		);

		//$this->_filesRegexpUpdate($patterns, array(), array(), 'docBlockSentence');
	}

	/**
	 * Callback for doc_blocks regexp update
	 *
	 * @param array $matches
	 * @return string
	 */
	protected function _docBlockVar($matches) {
		$desc = ucfirst($matches[2]);
		return '* @var'. ' ' . $matches[1];
	}

	/**
	 * Callback for doc_blocks regexp update
	 *
	 * @param array $matches
	 * @return string
	 */
	protected function _docBlockSentence($matches) {
		$desc = ucfirst($matches[3]);
		return '* @' . $matches[1] . ' ' . $matches[2] . ' ' . $desc;
	}

	/**
	 * Callback for doc_blocks regexp update
	 *
	 * @param array $matches
	 * @return string
	 */
	protected function _docBlock($matches) {
		$desc = ucfirst($matches[3]);
		return '@param ' . $matches[1] . ' $' . $matches[2] . ' ' . $desc;
	}

	/**
	 * Callback for doc_blocks regexp update
	 *
	 * @param array $matches
	 * @return string
	 */
	protected function _docBlockBeginning($matches) {
		$whitespace = $matches[1];
		$content = ucfirst($matches[2]);

		return '/**' . $whitespace . ' * ' . $content . ' ';
	}

	/**
	 * In 2.0 this is not needed anymore (thank god - forms post now to themselves per default^^)
	 *
	 * @return void
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
	 * Deprecated stuff in php5.3
	 * or new features/fixed introduced in php5.3
	 *
	 * Careful: Some self:: stuff is needed and should not be updated
	 *
	 * @return void
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
			# FIXME: "static::" is not allowed in compile-time constants in: look back for = as in "function x($y = self::FOO)"
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
	 *
	 * @return void
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
		$this->request->allowMethod(\'post\');
		if (empty'
			),
			array(
				'simplify require post',
				'/if\s*\(!\$this-\>Common-\>isPosted\(\)\)\s*{\s*\s*throw new MethodNotAllowedException\(\);\s*\s*}/',
				'$this->request->allowMethod(\'post\');'),
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
				'update $this->Common->isPost()',
				'/\$this-\>Common-\>isPost\(\)/',
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
				'correct flash message',
				'/-\>Common-\>flashMessage\(/',
				'->Flash->message('
			),
			array(
				'correct transient flash message',
				'/-\>Common-\>transientFlashMessage\(/',
				'->Flash->transientMessage('
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
			array(
				'Common->flash() to Flash->flash()',
				'/\$this-\>Common-\>flash\(/',
				'$this->Flash->flash('
			),
		);
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * AuthExt back to Auth (thx to aliasing!)
	 *
	 * @return void
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
	 * From component to lib
	 *
	 * @return void
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
			$this->_paths = CakePlugin::path($this->params['plugin']);
		} else {
			$this->_paths = APP;
		}

		if (empty($this->_paths)) {
			return $this->error('Please pass working dir as param (cake reference /absDir)');
		}
		$this->_paths = (array)$this->_paths;
	}

	/**
	 * CorrectShell::amp()
	 *
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function amp() {
		$this->params['ext'] = 'php';
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
	 * Move some methods from CommonHelper to FormatHelper
	 *
	 * @return void
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
				$method . '()',
				'/-\>Common-\>' . $method . '\(/',
				'->Format->' . $method . '(',
			);
		}

		$methods = array(
			'currency',
		);
		$patterns = array();
		foreach ($methods as $method) {
			$patterns[] = array(
				$method . '()',
				'/-\>Common-\>' . $method . '\(/',
				'->Numeric->' . $method . '(',
			);
		}

		$methods = array(
			'url', 'link'
		);
		foreach ($methods as $method) {
			$patterns[] = array(
				$method . '()',
				'/-\>GoogleMapV3-\>' . $method . '\(/',
				'->GoogleMapV3->map' . ucfirst($method) . '(',
			);
		}

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * html5
	 *
	 * @return void
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

	/**
	 * CorrectShell::reference()
	 *
	 * @return void
	 */
	public function reference() {
		$this->params['ext'] = 'php';
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

		$file = $this->_paths[0] . DS . 'View' . DS . 'View.php';
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
		} else {
			//die('FILE NOT EXISTS');
		}
	}

	/**
	 * Correct brackets: class X extends Y {
	 *
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function classes() {
		$this->params['ext'] = 'php';
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
	 * CorrectShell::functions()
	 *
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function functions() {
		$this->_getPaths();

		$patterns = array(
			array(
				'is_integer() to is_int()',
				'/\bis\_integer\(/',
				'is_int('
			),
			array(
				'is_writeable() to is_writable()',
				'/\bis\_writeable\(/',
				'is_writable('
			),
			array(
				'is_a($foo, \'CakeFoo\') to instance of',
				'/\bis\_a\(\$(\w+)\,\s*\\\'(\w+)\\\'\)/',
				'$\1 instanceof \2'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * CorrectShell::variables()
	 *
	 * @deprecated Use my cakephp-codesniffer plugin
	 * @return void
	 */
	public function variables() {
		$this->_getPaths();

		$patterns = array(
			array(
				'$some_thing to $someThing',
				'/\$[a-z]+\_[a-z_]+\b/',
			),
		);

		$this->_filesRegexpUpdate($patterns, array(), array(), 'variables');
	}

	/**
	 * Pagination sort links for priority, rating, created, modified, published, ...
	 * should be ordered DESC as default.
	 *
	 * @return void
	 */
	public function pagination() {
		$this->params['ext'] = 'ctp';
		$this->_getPaths();

		$patterns = array(
			array(
				'$this->Paginator->sort(..) => direction DESC',
				'/\bPaginator-\>sort\(\'(created|modified|published|publish_date|rating|priority)\'\)/',
				'Paginator->sort(\'\1\', null, array(\'direction\' => \'desc\'))'
			),
		);

		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Changes the Level of a headline
	 * e.g. H2 -> H1
	 *
	 * @return void
	 */
	public function header() {
		$this->params['ext'] = 'ctp';
		$this->_getPaths();

		$patterns = array();
		for ($i = 1; $i <= 7; $i++) {
			$patterns[] = array(
				'Change H' . ($i + 1) . ' to H' . $i . ' open',
				'/<h' . ($i + 1) . '\b/',
				'<h' . $i . '',
			);
			$patterns[] = array(
				'Change H' . ($i + 1) . ' to H' . $i . ' open',
				'/<\/h' . ($i + 1) . '>/',
				'</h' . $i . '>',
			);
		}
		$this->_filesRegexpUpdate($patterns);
	}

	/**
	 * Callback for variables regexp update
	 *
	 * @param array $matches
	 * @return string
	 */
	protected function _variables($matches) {
		$variable = Inflector::camelize($matches[0]);
		return $variable;
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
		$this->_paths[0] = $this->_paths[0] . DS . 'Routing';
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
	protected function _filesRegexpUpdate($patterns, $skipFiles = array(), $skipFolders = array(), $callback = null, $method = null) {
		$this->_findFiles($this->params['ext'], $skipFolders);
		foreach ($this->_files as $file) {
			if (in_array(pathinfo($file, PATHINFO_BASENAME), $skipFiles)) {
				continue;
			}
			$this->out(sprintf('Updating %s...', $file), 1, Shell::VERBOSE);
			if ($method) {
				$this->{$method}($file, $patterns, $callback);
				continue;
			}
			$this->_updateFile($file, $patterns, $callback);
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
				if (empty($this->args[0])) {
					$excludes = array('Vendor', 'vendors');
				}
				if (empty($this->params['plugin'])) {
					$excludes = am($excludes, array('Plugin', 'plugins'));
				}
				$excludes = am($excludes, $skipFolders);

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

				if ($file->isFile()) {
					$this->_files[] = $file->getPathname();
				}
			}
		}
	}

	/**
	 * CorrectShell::getOptionParser()
	 *
	 * @return ConsoleOptionParser
	 */
	public function getOptionParser() {
		$subcommandParser = array(
			'options' => array(
				'plugin' => array(
					'short' => 'p',
					'help' => 'The plugin to update. Only the specified plugin will be updated.',
					'default' => '',
				),
				'dry-run' => array(
					'short' => 'd',
					'help' => 'Dry run the update, no files will actually be modified.',
					'boolean' => true
				),
				'log' => array(
					'short' => 'l',
					'help' => 'Log all ouput to file log.txt in TMP dir',
					'boolean' => true
				),
				'ext' => array(
					'short' => 'e',
					'help' => 'The extension(s) to search. A pipe delimited list, or a preg_match compatible subpattern',
					'default' => 'php|ctp'
				),
				'interactive' => array(
					'short' => 'i',
					'help' => 'Run it interactively and ask before each each command',
					'boolean' => true
				),
			)
		);

		return parent::getOptionParser()
			->description("A shell to help automate upgrading from CakePHP 1.3 to 2.0. \n" .
				"Be sure to have a backup of your application before running these commands.")
			->addSubcommand('all', array(
				'help' => 'Run all correctional commands',
				'parser' => $subcommandParser
			))
			/*
			->addSubcommand('objects', array(
				'help' => 'Update objects'),
				'parser' => $subcommandParser
			))
			*/
			->addSubcommand('stable', array(
				'help' => 'Run all stable Correct commands.',
				'parser' => $subcommandParser
			))
			->addSubcommand('unstable', array(
				'help' => 'Run all unstable Correct commands.',
				'parser' => $subcommandParser
			))
			->addSubcommand('reference', array(
				'help' => 'Update reference',
				'parser' => $subcommandParser
			))
			->addSubcommand('amp', array(
				'help' => '=& fix',
				'parser' => $subcommandParser
			))
			->addSubcommand('request', array(
				'help' => 'clientIp corrections',
				'parser' => $subcommandParser
			))
			->addSubcommand('variables', array(
				'help' => 'variables corrections',
				'parser' => $subcommandParser
			))
			->addSubcommand('functions', array(
				'help' => 'function name corrections',
				'parser' => $subcommandParser
			))
			->addSubcommand('i18n', array(
				'help' => 'i18n simplifications',
				'parser' => $subcommandParser
			))
			->addSubcommand('vis', array(
				'help' => 'visibility (public, protected)',
				'parser' => $subcommandParser
			))
			->addSubcommand('forms', array(
				'help' => 'post to itself by default',
				'parser' => $subcommandParser
			))
			->addSubcommand('conventions', array(
				'help' => 'usual php5/cakephp2 conventions for coding',
				'parser' => $subcommandParser
			))
			->addSubcommand('conventions2', array(
				'help' => 'usual php5/cakephp2 conventions for coding',
				'parser' => $subcommandParser
			))
			->addSubcommand('conventions3', array(
				'help' => 'usual php5/cakephp2 conventions for coding',
				'parser' => $subcommandParser
			))
			->addSubcommand('conventions4', array(
				'help' => 'usual php5/cakephp2 conventions for coding',
				'parser' => $subcommandParser
			))
			->addSubcommand('conventions_experimental', array(
				'help' => 'experimental conventions (careful!)',
				'parser' => $subcommandParser
			))
			# custom app stuff (not for anyone else)
			->addSubcommand('helper', array(
				'help' => 'helper fix',
				'parser' => $subcommandParser
			))
			->addSubcommand('auth', array(
				'help' => 'auth fix',
				'parser' => $subcommandParser
			))
			->addSubcommand('classes', array(
				'help' => 'classes',
				'parser' => $subcommandParser
			))
			->addSubcommand('mail', array(
				'help' => 'mail fix',
				'parser' => $subcommandParser
			))
			->addSubcommand('umlauts', array(
				'help' => 'umlauts fixes in utf8',
				'parser' => $subcommandParser
			))
			->addSubcommand('doc_blocks', array(
				'help' => 'doc block updates',
				'parser' => $subcommandParser
			))
			->addSubcommand('tests', array(
				'help' => 'test case updates',
				'parser' => $subcommandParser
			))
			->addSubcommand('html5', array(
				'help' => 'html5 updates',
				'parser' => $subcommandParser
			))
			->addSubcommand('header', array(
				'help' => 'header change',
				'parser' => $subcommandParser
			))
			->addSubcommand('performance', array(
				'help' => 'performance updates',
				'parser' => $subcommandParser
			))
			->addSubcommand('specialchars', array(
				'help' => 'Resolve specialchars issues',
				'parser' => $subcommandParser
			))
			->addSubcommand('pagination', array(
				'help' => 'Correct pagination default order',
				'parser' => $subcommandParser
			))
			->addSubcommand('whitespace', array(
				'help' => 'Resolve whitespace issues',
				'parser' => $subcommandParser
			));
	}

}
