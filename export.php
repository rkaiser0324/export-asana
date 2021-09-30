<?php
/**
 * Use the Asana API to export all tasks and comments for a given project.
 *
 * export.php <workspacename> <projectname> <outputcsvfilename>
 *
 * E.g.,
 * export.php "My Workspace" "My Project" output.csv
 *
 */

require dirname(__FILE__) . '/vendor/autoload.php';

require dirname(__FILE__) . '/config.php';

use Asana\Client;

// Define ASANA_ACCESS_TOKEN as a Personal Access Token found in Asana Account Settings

if (empty($argv[1])) {
    throw new exception("Please specify a workspace name");
}
$workspace_name = $argv[1];

if (empty($argv[2])) {
    throw new exception("Please specify a project name");
}
$project_name = $argv[2];

if (empty($argv[3])) {
    throw new exception("Please specify an output filename");
}
$filename = $argv[3];

if (!$fp = fopen($filename, 'w')) {
    throw new exception("Cannot open $filename for writing");
}

echo "Getting tasks for project $project_name in workspace $workspace_name...\n";

// create a $client->with a Personal Access Token
$client = Asana\Client::accessToken(ASANA_ACCESS_TOKEN);

$me = $client->users->me();

$project_id = null;
$tasks = [];

foreach ($me->workspaces as $w) {
    if ($w->name == $workspace_name) {
        $projects = $client->projects->findByWorkspace($w->gid, null, array('iterator_type' => false, 'page_size' => null))->data;

        foreach ($projects as $p) {
            if ($p->name == $project_name) {
                $project_id = $p->gid;
                foreach ($client->tasks->findAll(array('project' => $project_id), array('page_size' => 100)) as $t) {
                    $comments = [];
                    foreach ($client->stories->getStoriesForTask($t->gid, array(), array('opt_pretty' => 'true')) as $story) {
                        if ($story->type == 'comment') {
                            //var_dump($story);
                            $comments[$story->created_at] = $story->text;
                        }
                    }
                    $tasks[$t->gid] = [
                        'name' => $t->name,
                        'comments' => $comments
                    ];
                    printf("\t%s\n", $t->name);
                }
            }
        }
        break;
    }
}

fputcsv($fp, ["Task ID", "Name", "URL", "Comment Date", "Comment"]);

foreach ($tasks as $task_id => $task) {
    $task_url = sprintf('https://app.asana.com/0/%s/%s', $project_id, $task_id);

    fputcsv($fp, [$task_id, $task['name'], $task_url]);

    foreach ($task['comments'] as $comment_timestamp => $comment_text) {
        fputcsv($fp, ['', '', '', $comment_timestamp, $comment_text]);
    }
}

fclose($fp);

echo "Output written to $filename.\n";
