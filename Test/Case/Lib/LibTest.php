<?php

App::uses('Lib', 'Upgrade.Lib');

class LibTest extends CakeTestCase {


 	public function startTest() {
 		$this->Lib = new Lib();
 	}

	public function testPluginLibs() {
		$res = $this->Lib->match('Tools.SimilarityLib');
		pr($res);
		
		$res = $this->Lib->match('Tools.ZodiacLib');
		pr($res);
		
		$res = $this->Lib->match('Tools.Xml');
		pr($res);
		
	}
	
	public function testCoreLibs() {
		
		
		$res = $this->Lib->match('Multibyte');
		pr($res);
		
		$res = $this->Lib->match('Validation');
		pr($res);
		
		$res = $this->Lib->match('Router');
		pr($res);
	}
		
}