# export-asana

This is a PHP CLI script which uses the Asana REST API to export, to a CSV, all the Tasks and Comments in a given Project in a Workspace.

## Setup

1. `composer install`
1.  Create `./config.php` as
    ```php
    <?php
    // Set this to a Personal Access Token found in Asana Account Settings
    define('ASANA_ACCESS_TOKEN', 'xxx');
    ```

## Usage

```
php export.php <workspacename> <projectname> <outputcsvfilename>
```

E.g., 
```
php export.php "My Workspace" "My Project" output.csv
```