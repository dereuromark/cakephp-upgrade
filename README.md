# CakePHP 3.x upgrade shell

Helps automating the upgrade process mainly between CakePHP 3.x versions - and maybe also coming from CakePHP 2.x.

It completes the existing [CakePHP Upgrade Tool](https://github.com/cakephp/upgrade) application with a few more things on top.

It can also apply a lot of additional sugar and enforce coding standards etc. Those commands are not vital, but nice to have.


## Installation

### Composer (recommended)
Add this to your composer.json file with `composer require cakephp-upgrade:dev-cake3 --dev`.
It will automatically download and install it right away.

Alternativly, you can add it manually:
```
"require-dev": {
	"dereuromark/cakephp-upgrade": "dev-cake3"
}
```
And run:
```
composer install
```


## Usage

### Upgrade shell

As this is a plugin, use it with:

	cake Upgrade.Upgrade [command]

Running it without any command will get you a list of possible commands to chose from. Make sure to check on this first.
You might have to set the executable rights for your cake shell first in order to run any shell (on unix anyway).

Note: If you use Unix the full command (relative from your app dir!) would be:

	bin/cake Upgrade.Upgrade [command]

On Windows:

	.\bin\cake Upgrade.Upgrade [command]

Tip: You can alias the shell in your bootstrap cli:

```php
use Cake\Console\ShellDispatcher;

// Custom shell aliases
ShellDispatcher::alias('u', 'Upgrade.Upgrade'); // Or any other alias
```

#### This version supports now on top of the original commands/tasks:

coming up...


## Disclaimer

Use this script ONLY after backing up your app folder.

Also: This powerful plugin is not for N00bs. Do NOT use it if you don't know at least some Cake basics.

### Troubleshooting
- Make sure you have `debug` mode enabled and/or cleared the cache.
- Use a fresh 3.x app and play with the plugin to get a grasp on how the new major version works, and how the plugin operates, if it is your first trial with 3.x.
Then - with some more insight - it will be easier to migrate apps.

### My recommendation
Either use git or svn or some other version control to verify the changes made.
This way you are able to detect wrong replacements right away. So better use every upgrade command separatly and commit/push after each successful run.
If sth goes wrong with one command you can easily revert to the last step this way. The `regexp` commands sometimes can be too eager and have not been tested to their full extend. So please be careful.


Also don't forget to take a look at in the migration guides:

- book.cakephp.org/3.0/en/appendices/3-0-migration-guide.html
