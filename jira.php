<?php

/**
 * @usage
 * php jira.php "Days to load" "Name to get logs for"
 * php jira.php 1 nikunj
 */

require_once __DIR__ . '/settings.php';

$days = $argv[1] ?? 5;
$jira_name_to_check = $argv[2] ?? 'nikunj';

function get_curl() {
  global $jira_email, $jira_token;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_USERPWD, $jira_email . ':' . $jira_token);

  $headers = [];
  $headers[] = 'Accept: application/json';
  $headers[] = 'Content-Type: application/json';
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

  return $ch;
}

function get_issue($id) {
  $ch = get_curl();

  curl_setopt($ch, CURLOPT_URL, 'https://alshayagroup.atlassian.net/rest/api/3/issue/' . $id);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
  $result = curl_exec($ch);
  if (curl_errno($ch)) {
    print 'Error3:' . curl_error($ch);
    print PHP_EOL;
    die();
  }
  curl_close($ch);

  $result = json_decode($result, TRUE);
  return $result['key'];
}

$worklogs = [];

$api_url = 'https://alshayagroup.atlassian.net/rest/api/3/worklog/updated?expand=renderedFields&since=' . strtotime("-$days day") * 1000;

$total_time = [];

while (1) {
  print 'Getting page - ' . $api_url . PHP_EOL;
  $ch = get_curl();
  curl_setopt($ch, CURLOPT_URL, $api_url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');

  $main_result = curl_exec($ch);
  if (curl_errno($ch)) {
    print 'Error2:' . curl_error($ch);
    print PHP_EOL;
    die();
  }
  curl_close($ch);

  $main_result = json_decode($main_result, TRUE);

  $request = [
    'ids' => array_column($main_result['values'], 'worklogId'),
  ];

  $ch = get_curl();
  curl_setopt($ch, CURLOPT_URL, 'https://alshayagroup.atlassian.net/rest/api/3/worklog/list');
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request));

  $result = curl_exec($ch);

  if (curl_errno($ch)) {
    print 'Error2:' . curl_error($ch);
    print PHP_EOL;
    die();
  }
  curl_close($ch);

  $result = json_decode($result, TRUE);

  foreach ($result as $row) {
    if (stripos($row['author']['displayName'], $jira_name_to_check) === FALSE) {
      continue;
    }

    $issue = get_issue($row['issueId']);

    $comment = $row['comment']['content'][0]['content'][0]['text'] ?? '';

    $date = date('Y-m-d', strtotime($row['started']));
    $worklogs[$date][$issue][] = [
      'time' => $row['timeSpent'],
      'comment' => $comment,
    ];

    $total_time[$date] = $total_time[$date] ?? 0;
    $total_time[$date] += $row['timeSpentSeconds'];
  }

  if (!empty($main_result['nextPage'])) {
    $api_url = $main_result['nextPage'];
    continue;
  }

  break;
}

print PHP_EOL;

ksort($worklogs);
foreach ($worklogs as $date => $date_logs) {
  print 'Date: ' . $date . ' : ' . gmdate("H:i:s", $total_time[$date]) . PHP_EOL;
  foreach ($date_logs as $issue => $logs) {
    foreach ($logs as $log) {
      print $issue . ' : ' . $log['comment'] . ' - ' . $log['time'] . PHP_EOL;
    }
  }

  print PHP_EOL;
}
