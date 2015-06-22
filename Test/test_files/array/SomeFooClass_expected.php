<?php

$foo = [];

class SomeFooClass {

	public $x = [];

	public $y = [
		'foo' => 'bar'
	];

	public function x($y = [], array $z = ['abc']) {
		$foo = [
			'bar',
			'baz' => 'abc'
		];

		$this->foo((array)$x, ['b', 'c', 'd']);
	}

}
