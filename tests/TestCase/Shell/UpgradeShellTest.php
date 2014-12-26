<?php
namespace Upgrade\Test\TestCase\Shell;

use Upgrade\Shell\UpgradeShell;
use Cake\Console\ConsoleIo;
use Tools\TestSuite\ConsoleOutput;
use Cake\Console\Shell;
use Cake\Core\Plugin;
use Tools\TestSuite\TestCase;

/**
 */
class UpgradeShellTest extends TestCase {

/**
 * setUp method
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();

		$this->out = new ConsoleOutput();
		$this->err = new ConsoleOutput();
		$io = new ConsoleIo($this->out, $this->err);

		$this->Shell = $this->getMock(
			'Upgrade\Shell\UpgradeShell',
			['in', '_stop'],
			[$io]
		);

		if (!is_dir(TMP . 'upgrade')) {
			mkdir(TMP . 'upgrade', 0770, true);
		}
	}

	/**
	 * tearDown
	 *
	 * @return void
	 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Shell);
	}

	/**
	 * Test upgrade command
	 *
	 * @return void
	 */
	public function testUpgradeX() {
		return;

		$this->Shell->expects($this->any())->method('in')
			->will($this->returnValue('y'));


	}

}
