<?php

$foo = array();

class SomeFooClass {

	public $x = array();

	public $y = array(
		'foo' => 'bar'
	);

	public function x($y = array(), array $z = array('abc')) {
		$foo = array(
			'bar',
			'baz' => 'abc'
		);

		$this->foo((array)$x, array('b', 'c', 'd'));
	}

}
