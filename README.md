# CakePHP2.x upgrade shell - improved version

The original one lacks a lot of things that could be done automatically.
Changes have been reported as ticket, but so far it has not yet made it into the official version.

## Installation

a) Copy the `/lib` folder of the current Cake master branch into a new folder next to your `/app` dir as `/lib` (containing the new Cake core).

b) Copy this plugin into your `/app/Plugin` folder as `/app/Plugin/Upgrade`.

c) Manually rename `/app/config` folder to `/app/Config` (we need the bootstrap.php here).

d) Put `CakePlugin::load('Upgrade')` or `CakePlugin::loadAll();` in your app/Config bootstrap to make sure this plugin is loaded.

e) Either put the cake shell from the downloaded 2.x repository in app/Console or use the lib/Cake one.

f) Ready to go! Run any of the available commands (see details below). The most important one is `locations` and should be run first.

Don't forget to remove the old 1.3 cake folder and manually clear the (persistent) cache before running the shell.

I never had to manually replace my core.php with the new 2.x core.php. But as there are many new features introduced, it might make sense to do that.
Just don't forget to merge your existing settings like salt and cache/session/cookie settings.

Remember: At this point you are already using the shell as 2.x shell. "cake1.x" or whatever you used before is now officially dead.

Also: Mind the casing! Uppercase/lowercase is important.

## Usage

As this is a plugin, use it with:

    cake Upgrade.Upgrade [command]

Running it without any command will get you a list of possible commands to chose from. Make sure to check on this first.
You might have to set the executable rights for your cake shell first in order to run any shell (on unix anyway).

Note: If you use windows the full command (from your app dir!) would be:

    ..\lib\Cake\Console\cake Upgrade.Upgrade [command]

On unix:

	../lib/Cake/Console/cake Upgrade.Upgrade [command]

and if you use the app/Console shell instead (I never do that, though):

    ./Console/cake Upgrade.Upgrade [command]

This version supports now on top of the original commands/tasks:
- webroot (important)
- database (important)
- routes (important)
- legacy
- name
- constructors
- report
- estrict
- views
- paginator (run only once!)
and many more

New functionally also supported now:
- svn (linux/windows)
- group commands
- except command (all except for a few, e.g. `except paginator` to skip paginator method)

Additionally you can use the CorrectShell to correct
- request
- amp
- vis
- reference
- i18n
- forms
- conventions
- conventions2
- html5
- php53

with `cake Upgrade.Correct [command]`

Tip: You can use `cake Upgrade.Correct all` to quickly apply all relevant correction commands.

Tip2: The probably most important feature for me is that my version cleanly separates
app and plugins. Without -p PluginName it leaves them alone.
If you want to upgrade plugins use

    cake Upgrade.Upgrade [command] -p PluginName

I also added support for -p * - to address all plugins at once.
This is only fully supported by the `group` command like so:

    cake Upgrade.Upgrade group [command1] [command2] ... -p *

You need to supply at least two commands (if you want to use only one, type it twice:

	  cake Upgrade.Upgrade group [command1] [command1] -p *

This is necessary because 1 argument stands for a config group

The not fully tested way of using the plugin wildcard would be

    cake Upgrade.Upgrade [command] -p *

which should just grab all files at once and process them

### UPDATE January 2012: Support for 2.1
Now supports
- Auth::allow(), Layout Stuff and more

### UPDATE September 2012: Support up to 2.3 (and some 3.0)
Now supports
- request->query() and Set/Hash replacement

### UPDATE December 2012:
Now creates missing App classes that are required since 2.1
- AppHelper, AppModel and AppController (AppShell is not yet required)


## Disclaimer

Use this script ONLY after backing up your app folder.

Also: This powerful plugin is not for N00bs. Do NOT use it if you don't know at least some Cake basics.

### My recommendation
Either use git or svn or some other version control to verify the changes made.
This way you are able to detect wrong replacements right away. So better use every upgrade command separatly and commit/push after each successful run.
If sth goes wrong with one command you can easily revert to the last step this way. The `regexp` commands sometimes can be too eager and have not been tested to their full extend. So please be careful.

So the ideal order might be:
- locations
- webroot
- routes
- database
- [the rest - the application might already be browsable again at this point]

### Stuff you REALLY have to do on your own (imagine that^^)
- make sure you add missing App::uses() statements to your class files
- fix some more ESTRICT errors
- apply missing configuration for new features

AND everything else that is not yet covered but meticulously documented in the migration guides:

- http://book.cakephp.org/2.0/en/appendices/2-0-migration-guide.html
- http://book.cakephp.org/2.0/en/appendices/2-1-migration-guide.html
- http://book.cakephp.org/2.0/en/appendices/2-2-migration-guide.html
- http://book.cakephp.org/2.0/en/appendices/2-3-migration-guide.html