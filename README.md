# export-asana

Per [this forum post](https://forum.asana.com/t/exporting-projects-including-comments/53796/2), Asana does not offer a means to natively export a project.  This PHP CLI script  uses the Asana REST API to export to HTML, all the Tasks, Comments, and links to Attachments in a given Project in a Workspace.  It also includes any Subtasks in the hierarchy.  

This script has been tested with PHP 7.3 and PHP 8.0.

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

The script is used as follows:
```
php export.php --workspace=<workspacename> --project=<projectname> --output=<outputfilename> [--modified_since=YYYY-MM-DD] [--completed_since=YYYY-MM-DD]
```

Parameters:
* workspace - name of the Workspace, required
* project - name of the Project, required
* output - name of the output file, required
* modified_since - date in YYYY-MM-DD format, optional
* completed_since - date in YYYY-MM-DD format, optional

E.g., 
```
php export.php --workspace="My Workspace" --project="My Project" --modified_since=2022-01-15 --output=output.html
```

## Notes

- The script skips any Task that has been added to multiple Projects, but this can be easily changed if that restriction is not applicable to your use case.
- The output HTML uses [Bootstrap 5](https://getbootstrap.com/) for minimal styling.