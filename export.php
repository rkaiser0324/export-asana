<?php
/**
 * Use the Asana API to export all tasks and comments for a given project.
 *
 * export.php <workspacename> <projectname> <outputfilename>
 *
 * E.g.,
 * export.php "My Workspace" "My Project" output.html
 *
 */

use Asana\Client;

try {
    require dirname(__FILE__) . '/vendor/autoload.php';

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

    require dirname(__FILE__) . '/config.php';

    if (!defined('ASANA_ACCESS_TOKEN')) {
        throw new exception("Please define ASANA_ACCESS_TOKEN in ./config.php");
    }

    // create a $client->with a Personal Access Token
    $client = Asana\Client::accessToken(ASANA_ACCESS_TOKEN, array('headers' => array('asana-disable' => 'new_user_task_lists')));

    echo "Getting tasks for project \"$project_name\" in workspace \"$workspace_name\"...\n";

    $me = $client->users->me();

    $project_id = null;
    $tasks = [];
    $found_workspace = false;
    $found_project = false;
    foreach ($me->workspaces as $w) {
        printf("Found workspace \"%s\"...\n", $w->name);
        if ($w->name == $workspace_name) {
            $found_workspace = true;
            $projects = $client->projects->findByWorkspace($w->gid, null, array('iterator_type' => false, 'page_size' => null))->data;

            foreach ($projects as $p) {
                printf("Found project \"%s\"...\n", $p->name);
                if ($p->name == $project_name) {
                    $found_project = true;
                    $project_id = $p->gid;

                    $i = 0;
                    foreach ($client->tasks->findAll(array('project' => $project_id), array('page_size' => 100)) as $t) {
                        $result = get_task($client, $t->gid);

                        if ($result['status'] == 'OK') {
                            $tasks[] = $result['task'];
                        }

                        printf("  Task %s - %s - %s\n", ++$i, $result['status'], $result['task']['name']);
                    }
                }
            }
            break;
        }
    }
    if (!$found_workspace) {
        throw new exception("Could not find workspace \"$workspace_name\"");
    }

    if (!$found_project) {
        throw new exception("Could not find project \"$project_name\" in workspace \"$workspace_name\"");
    }

    // Sort tasks by created_at
    usort($tasks, function ($a, $b) {
        return ($a['created_at'] < $b['created_at']) ? -1 : 1;
    });

    fprintf($fp, '<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">

    <title>Asana Tasks - %s</title>
    <style type="text/css">
    dt, dl {
        font-size: 0.875em;
    }
    </style>
  </head>
    <body>
        <div class="container">
        <h1>Asana Tasks - %s (%s)</h1>
        ', $project_name, $project_name, count($tasks));

    foreach ($tasks as $task) {
        print_task($fp, $project_id, $task);
    }

    fprintf($fp, "
        </div><!-- end container -->
    </body>
</html>");
    fclose($fp);

    echo "\nOutput written to $filename.\n";
} catch (exception $ex) {
    printf("\033[01;31m \n*** ERROR: %s\n\n\033[0m", $ex->getMessage());
}
function format_timestamp($str)
{
    return strftime('%m/%d/%y %r %Z', strtotime($str));
}

function get_task($client, $gid)
{
    $status = "OK";
    $comments = [];
    $subtasks = [];

    $task_data = $client->tasks->getTask($gid);
  
    // Skip any task that is in multiple projects
    if (count($task_data->projects) > 1) {
        $status = "SKIP";
    } else {
        foreach ($client->stories->getStoriesForTask($gid, array(), array('opt_pretty' => 'true')) as $story) {
            if ($story->type == 'comment') {
                $comments[] = [
                    'created_at' => $story->created_at,
                    'text' => $story->text
                ];
            }
        }

        foreach ($client->attachments->getAttachmentsForTask($gid, array(), array('opt_pretty' => 'true')) as $attachment) {
            $attachment_data = $client->attachments->findById($attachment->gid);
            $comments[] = [
                'created_at' => $attachment_data->created_at,
                'text' => $attachment_data->name,
                'url' => $attachment_data->view_url,
            ];
        }

        usort($comments, function ($a, $b) {
            return ($a['created_at'] < $b['created_at']) ? -1 : 1;
        });

        foreach ($client->tasks->getSubtasksForTask($gid, array(), array('opt_pretty' => 'true')) as $subtask) {
            $result = get_task($client, $subtask->gid);
            if ($result['status'] == 'OK') {
                $subtasks[] = $result['task'];
            }
        }

        usort($subtasks, function ($a, $b) {
            return ($a['created_at'] < $b['created_at']) ? -1 : 1;
        });
    }
    
    return [
            'status' => $status,
            'task' => [
                'gid' => $task_data->gid,
                'assignee' => $task_data->assignee->name ?? '',
                'created_at' => $task_data->created_at,
                'completed_at' => $task_data->completed ? format_timestamp($task_data->completed_at) : '',
                'name' => $task_data->name,
                'notes' => $task_data->notes,
                'comments' => $comments,
                'subtasks' => $subtasks
            ]
        ];
}

function print_task($fp, $project_id, $task)
{
    $task_url = sprintf('https://app.asana.com/0/%s/%s', $project_id, $task['gid']);

    fprintf($fp, '
    <div class="row">
        <div class="col-12">
            <p><strong>%s</strong></p>
            <p class="small">%s</p>
            <dl class="row">
                <dt class="col-sm-2">URL</dt>
                <dd class="col-sm-10"><a href="%s" target="_new">%s</a></dd>
                <dt class="col-sm-2">Created At</dt>
                <dd class="col-sm-10">%s</dd>
                <dt class="col-sm-2">Completed At</dt>
                <dd class="col-sm-10">%s</dd>
            </dl>
        </div>
        <div class="col-1"></div>
        <div class="col-11">
', htmlspecialchars($task['name']), nl2br(htmlspecialchars($task['notes'])), $task_url, $task_url, format_timestamp($task['created_at']), $task['completed_at']);

    foreach ($task['comments'] as $comment) {
        $text = nl2br(htmlspecialchars($comment['text']));
        if (!empty($comment['url'])) {
            $text = sprintf('<a href="%s" target="_new">%s</a>', $comment['url'], $comment['text']);
        }
        fprintf($fp, '
            <dl class="row">
                <dt class="col-2">%s</dt>
                <dd class="col-10">%s</dd>
            </dl>
        ', format_timestamp($comment['created_at']), $text);
    }

    foreach ($task['subtasks'] as $subtask) {
        print_task($fp, $project_id, $subtask);
    }

    fprintf($fp, "
        </div>
    </div><!-- end task -->
    ");
}
