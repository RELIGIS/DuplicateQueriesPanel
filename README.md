# Duplicate queries panel

Panel for Tracy Debug panel. It shows number of duplicate queries called in request. In tab it shows all the queries with locations from where were the quesries called.

* Author: Jan Petr
* Licence: MIT

Installation
------------

Install library via composer:

```
composer require religis/duplicate-queries-panel
```

Register bar in config.neon:

```
nette:
    debugger:
        bar: 
            - RELIGIS\DuplicateQueriesPanel
```