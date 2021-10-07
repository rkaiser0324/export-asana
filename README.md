# export-asana

This is a PHP CLI script, inspired by [this forum post](https://forum.asana.com/t/exporting-projects-including-comments/53796/2), which uses the Asana REST API to export to HTML, all the Tasks and Comments in a given Project in a Workspace.



## Setup

1. `composer install`
1. Get a Personal Access Token per [these instructions](https://developers.asana.com/docs/authentication-quick-start).
1.  Create `./config.php` as
    ```php
    <?php
    // Set this to your Personal Access Token
    define('ASANA_ACCESS_TOKEN', 'xxx');
    ```

## Usage

```
php export.php <workspacename> <projectname> <outputfilename>
```

E.g., 
```
php export.php "My Workspace" "My Project" output.html
```