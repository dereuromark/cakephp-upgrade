<?php

App::uses('ArraysShell', 'Upgrade.Console/Command');
App::uses('CakePlugin', 'Core');
App::uses('ConsoleOutput', 'Console');
App::uses('ConsoleInput', 'Console');

class TestConsoleOutput extends ConsoleOutput {

	public $output = [];

	protected function _write($message) {
		$this->output[] = $message;
	}

	public function output() {
		return implode(PHP_EOL, $this->output);
	}

}

class ArraysShellTest extends CakeTestCase {

	public $Correct;

	public function setUp() {
		parent::setUp();

		$output = new TestConsoleOutput();
		$error = new TestConsoleOutput();
		$input = $this->getMock('ConsoleInput', [], [], '', false);

		$this->Arrays = new TestArraysShell($output, $error, $input);
		$this->Arrays->testPath = CakePlugin::path('Upgrade') . 'Test' . DS . 'test_files' . DS . 'array' . DS;
	}

	public function testRunDryRun() {
		$this->Arrays->params['dry-run'] = true;
		$this->Arrays->params['verbose'] = true;
		$this->Arrays->args = [$this->Arrays->testPath];
		$this->Arrays->run();
		$result = $this->Arrays->stdout->output;

		$this->assertTextContains('Updating ', $result[0]);
		$this->assertTextContains('Done updating ', $result[1]);
	}

	public function testRun() {
		$tmpPath = TMP . 'array' . DS;
		if (!is_dir($tmpPath)) {
			mkdir($tmpPath, 0770, true);
		}

		copy($this->Arrays->testPath . 'SomeFooClass.php', $tmpPath . 'SomeFooClass.php');
		$this->Arrays->params['dry-run'] = false;
		$this->Arrays->params['verbose'] = true;
		$this->Arrays->args = [$tmpPath];
		$this->Arrays->run();
		$result = $this->Arrays->stdout->output;

		$this->assertTextContains('Updating ', $result[0]);
		$this->assertTextContains('Done updating ', $result[1]);

		$result = file_get_contents(TMP . 'array' . DS . 'SomeFooClass.php');
		$expected =  file_get_contents($this->Arrays->testPath . 'SomeFooClass_expected.php');
		$this->assertTextEquals($expected, $result);

		unlink($tmpPath . 'SomeFooClass.php');
	}

}

class TestArraysShell extends ArraysShell {

}
