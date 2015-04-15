<?php

App::uses('MyUpgradeShell', 'Upgrade.Console/Command');
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

class MyUpgradeShellTest extends CakeTestCase {

	public $Shell;

	public function setUp() {
		parent::setUp();

		$output = new TestConsoleOutput();
		$error = new TestConsoleOutput();
		$input = $this->getMock('ConsoleInput', [], [], '', false);

		$this->Shell = new MyUpgradeShell($output, $error, $input);
		$this->testPath = CakePlugin::path('Upgrade') . 'Test' . DS . 'test_files' . DS;
	}

}
