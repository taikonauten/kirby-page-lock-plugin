# kirby-page-lock-plugin

![Version](https://img.shields.io/badge/version-0.1.0-green.svg) ![License](https://img.shields.io/badge/license-MIT-green.svg) ![Kirby Version](https://img.shields.io/badge/Kirby-2.3.2%2B-red.svg)

[Kirby](https://getkirby.com/) plugin tracking what pages users are editing at the moment. It warns other users who try to edit the same page at the same time to prevent potential data loss.

## Installation

1. [Clone](https://github.com/taikonauten/kirby-page-lock-plugin.git) or [download](https://github.com/taikonauten/kirby-page-lock-plugin/archive/master.zip) this repository.
2. Unzip the archive if needed and rename the folder to `page-lock`.

**Make sure that the plugin folder structure looks like this:**

```
site/plugins/page-lock/
```

## Options

There is no configuration needed, this plugin works out of the box.

Following options can be set in your config files:

```php
// file location where to store tracking information
c::set(
  'plugin.pagelock.editinglogpath',
  $kirby->roots()->cache() . DS . 'page-lock.json');

// cooldown after which a page is released (in seconds)
// activity is reported every 10 seconds
c::set('plugin.pagelock.cooldown', 20);
```



--

'made with ♡ by Taikonauten'
