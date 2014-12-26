<?php
define('DS', DIRECTORY_SEPARATOR);
if (!defined('WINDOWS')) {
	if (DS == '\\' || substr(PHP_OS, 0, 3) === 'WIN') {
		define('WINDOWS', true);
	} else {
		define('WINDOWS', false);
	}
}

define('ROOT', dirname(__DIR__));
define('TMP', ROOT . DS . 'tmp' . DS);
define('LOGS', TMP . 'logs' . DS);
define('CACHE', TMP . 'cache' . DS);
define('APP', sys_get_temp_dir());
define('APP_DIR', 'src');
define('CAKE_CORE_INCLUDE_PATH', ROOT . '/vendor/cakephp/cakephp');
define('CORE_PATH', CAKE_CORE_INCLUDE_PATH . DS);
define('CAKE', CORE_PATH . APP_DIR . DS);

define('WWW_ROOT', ROOT . DS . 'webroot' . DS);
define('CONFIG', dirname(__FILE__) . DS . 'config' . DS);

require ROOT . '/vendor/cakephp/cakephp/src/basics.php';
require ROOT . '/vendor/autoload.php';

require CAKE . 'Core/ClassLoader.php';

$loader = new Cake\Core\ClassLoader;
$loader->register();

$loader->addNamespace('TestApp', ROOT . DS . 'tests' . DS . 'TestApp' . DS);

Cake\Core\Configure::write('App', [
		'namespace' => 'App',
		'encoding' => 'UTF-8']);
Cake\Core\Configure::write('debug', true);

mb_internal_encoding('UTF-8');

$Tmp = new \Cake\Filesystem\Folder(TMP);
$Tmp->create(TMP . 'cache/models', 0770);
$Tmp->create(TMP . 'cache/persistent', 0770);
$Tmp->create(TMP . 'cache/views', 0770);

$cache = [
	'default' => [
		'engine' => 'File'
	],
	'_cake_core_' => [
		'className' => 'File',
		'prefix' => 'crud_myapp_cake_core_',
		'path' => CACHE . 'persistent/',
		'serialize' => true,
		'duration' => '+10 seconds'
	],
	'_cake_model_' => [
		'className' => 'File',
		'prefix' => 'crud_my_app_cake_model_',
		'path' => CACHE . 'models/',
		'serialize' => 'File',
		'duration' => '+10 seconds'
	]
];

Cake\Cache\Cache::config($cache);

Cake\Core\Plugin::load('Upgrade', ['path' => ROOT . DS]);
Cake\Core\Plugin::load('Tools', ['path' => ROOT . DS . 'plugins' . DS . 'Tools' . DS]);

// Ensure default test connection is defined
if (!getenv('db_class')) {
	putenv('db_class=Cake\Database\Driver\Sqlite');
	putenv('db_dsn=sqlite::memory:');
}

if (WINDOWS) {
	Cake\Datasource\ConnectionManager::config('test', [
		'className' => 'Cake\Database\Connection',
		'driver' => 'Cake\Database\Driver\Mysql',
		'database' => 'cake_test',
		'username' => 'root',
		'password' => '',
		'timezone' => 'UTC',
		'quoteIdentifiers' => true,
		'cacheMetadata' => true,
	]);
	return;
}

Cake\Datasource\ConnectionManager::config('test', [
	'className' => 'Cake\Database\Connection',
	'driver' => getenv('db_class'),
	'dsn' => getenv('db_dsn'),
	'database' => getenv('db_database'),
	'username' => getenv('db_username'),
	'password' => getenv('db_password'),
	'timezone' => 'UTC',
	'quoteIdentifiers' => true,
	'cacheMetadata' => true,
]);
