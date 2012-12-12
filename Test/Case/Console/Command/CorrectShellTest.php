<?php

App::uses('CorrectShell', 'Upgrade.Console/Command');

class CorrectShellTest extends CakeTestCase {

	public $Correct;

 	public function setUp() {
 		parent::setUp();

 		$this->Correct = new TestCorrectShell();
 		$this->Correct->testPath = CakePlugin::path('Upgrade'). 'Test' . DS . 'test_files' . DS;
 	}

	public function testHTML5() {
		$this->Correct->file = 'html5';
		$this->Correct->html5();
		$result = $this->Correct->result;
		//debug($result);
		$this->assertTextEquals($result['expected'], $result['is']);
	}

	public function _testVis() {
		$this->Correct->file = 'html5';
		$this->Correct->html5();
		$result = $this->Correct->result;
		//debug($result);
		$this->assertTextEquals($result['expected'], $result['is']);
	}


}



class TestCorrectShell extends CorrectShell {

	protected function _filesRegexpUpdate($patterns, $skipFiles = array(), $skipFolders = array()) {
		$this->_updateFile($this->file, $patterns);
	}

	protected function _updateFile($file, $patterns) {
		$isFile = $this->testPath . $file . '.txt';
		$expectedFile = $this->testPath . $file . '_expected.txt';

		$contents = file_get_contents($isFile);
		foreach ($patterns as $pattern) {
			$contents = preg_replace($pattern[1], $pattern[2], $contents);
		}

		$this->out(__d('cake_console', 'Done updating %s', $file), 1);
		$testContents = null;
		if (file_exists($expectedFile)) {
			$testContents = file_get_contents($expectedFile);
		}
		$this->result = array('is' => $contents, 'expected' => $testContents);
	}

}
