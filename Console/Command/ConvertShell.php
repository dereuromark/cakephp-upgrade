<?php

App::uses('Folder', 'Utility');
App::uses('UpgradeShell', 'Upgrade.Console/Command');

/**
 * Shell to convert stuff to PHP5.4.
 *
 * Currently handles:
 * - Array syntax from long array() to short []
 *
 * @cakephp 2
 * @php 5
 * @author Mark scherer
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
class ConvertShell extends UpgradeShell {

	/**
	 * ConvertShell::startup()
	 *
	 * @return void
	 */
	public function startup() {
		$this->params['git'] = null;
		$this->params['tgit'] = null;
		$this->params['svn'] = null;
		parent::startup();
	}

	/**
	 * ConvertShell::arrays()
	 *
	 * @return void
	 */
	public function arrays() {
		$this->params['ext'] = 'php|ctp';
		$this->_getPaths();

		$this->_filesUpdate();
	}

	/**
	 * Updates all found files.
	 *
	 * @param array $skipFiles Array of files to skip.
	 * @param array $skipFolders Array of folders to skip.
	 * @return void
	 */
	protected function _filesUpdate($skipFiles = array(), $skipFolders = array()) {
		$this->_findFiles($this->params['ext'], $skipFolders);
		foreach ($this->_files as $file) {
			if (in_array(pathinfo($file, PATHINFO_BASENAME), $skipFiles)) {
				continue;
			}
			$this->out(__d('cake_console', 'Updating %s...', $file), 1, Shell::VERBOSE);
			$this->_updateFile($file);
		}
	}

	/**
	 * Actually modifies the file.
	 * Transform array() into [].
	 *
	 * Use `dry-run` to simulate the replacement.
	 *
	 * @param string $file
	 * @return void
	 */
	protected function _updateFile($file) {
		$contents = file_get_contents($file);
		$modified = $contents;

		$tokens = token_get_all($modified);

		$replacements = array();
		$offset = 0;
		for ($i = 0; $i < count($tokens); ++$i) {
			// Keep track of the current byte offset in the source code
			$offset += strlen(is_array($tokens[$i]) ? $tokens[$i][1] : $tokens[$i]);

			// T_ARRAY could either mean the "array(...)" syntax we're looking for
			// or a type hinting statement ("function(array $foo) { ... }")
			if (!is_array($tokens[$i]) || $tokens[$i][0] !== T_ARRAY) {
				continue;
			}

			// Look for a subsequent opening bracket ("(") to be sure we're actually
			// looking at an "array(...)" statement
			$isArraySyntax = false;
			$subOffset = $offset;
			for ($j = $i + 1; $j < count($tokens); ++$j) {
				$subOffset += strlen(is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j]);

				if (is_string($tokens[$j]) && $tokens[$j] == '(') {
					$isArraySyntax = true;
					break;
				} elseif (!is_array($tokens[$j]) || $tokens[$j][0] !== T_WHITESPACE) {
					$isArraySyntax = false;
					break;
				}
			}

			if (!$isArraySyntax) {
				continue;
			}

			// Replace "array" and the opening bracket (including preceeding whitespace) with "["
			$replacements[] = array(
				'start' => $offset - strlen($tokens[$i][1]),
				'end' => $subOffset,
				'string' => '[',
			);

			// Look for matching closing bracket (")")
			$subOffset = $offset;
			$openBracketsCount = 0;
			for ($j = $i + 1; $j < count($tokens); ++$j) {
				$subOffset += strlen(is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j]);

				if (is_string($tokens[$j]) && $tokens[$j] === '(') {
					++$openBracketsCount;
				} elseif (is_string($tokens[$j]) && $tokens[$j] === ')') {
					--$openBracketsCount;

					if ($openBracketsCount === 0) {
						// Replace ")" with "]"
						$replacements[] = array(
							'start' => $subOffset - 1,
							'end' => $subOffset,
							'string' => ']',
						);
						break;
					}
				}
			}
		}

		// Apply the replacements to the source code
		$offsetChange = 0;
		foreach ($replacements as $replacement) {
			$modified = substr_replace($modified, $replacement['string'], $replacement['start'] + $offsetChange, $replacement['end'] - $replacement['start']);
			$offsetChange += strlen($replacement['string']) - ($replacement['end'] - $replacement['start']);
		}

		$this->out(__d('cake_console', 'Done updating %s', $file), 1, Shell::VERBOSE);
		if (!$this->params['dry-run'] && $modified !== $contents) {
			file_put_contents($file, $modified);
			$this->out(__d('cake_console', 'Replace modified file %s', $file), 1, Shell::VERBOSE);
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

	/**
	 * ConvertShell::_getPaths()
	 *
	 * @return void
	 */
	protected function _getPaths() {
		if (!empty($this->args)) {
			$this->_paths = $this->args[0];
		} elseif (!empty($this->params['plugin'])) {
			$this->_paths = App::pluginPath($this->params['plugin']);
		} else {
			$this->_paths = APP;
		}

		if (empty($this->_paths)) {
			return $this->error('Please pass working dir as param (cake reference /absDir)');
		}
		$this->_paths = (array)$this->_paths;
	}

	/**
	 * ConvertShell::getOptionParser()
	 *
	 * @return ConsoleOptionParser
	 */
	public function getOptionParser() {
		$subcommandParser = array(
			'options' => array(
				'plugin' => array(
					'short' => 'p',
					'help' => __d('cake_console', 'The plugin to update. Only the specified plugin will be updated.'),
					'default' => '',
				),
				'dry-run' => array(
					'short' => 'd',
					'help' => __d('cake_console', 'Dry run the update, no files will actually be modified.'),
					'boolean' => true
				),
				'ext' => array(
					'short' => 'e',
					'help' => __d('cake_console', 'The extension(s) to search. A pipe delimited list, or a preg_match compatible subpattern'),
					'default' => 'php'
				),
			)
		);

		$name = ($this->plugin ? $this->plugin . '.' : '') . $this->name;
		$parser = new ConsoleOptionParser($name);
		return $parser
			->description(__d('cake_console', "A shell to help automate upgrading array syntax to PHP5.4"))
			->addSubcommand('arrays', array(
				'help' => __d('cake_console', 'Main method to update verbose array syntax to short one.'),
				'parser' => $subcommandParser
			));
	}

}
