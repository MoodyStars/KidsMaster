#!/usr/bin/env bash
# tests/ci_worker_smoke.sh
# Small smoke test that enqueues a dummy thumbnail job and ensures it appears in encoding_jobs table.
# Requires DB env vars to be set for CLI context.

set -e

PHP=$(which php)
if [ -z "$PHP" ]; then
  echo "php CLI not found"
  exit 2
fi

# Basic PHP snippet to insert a test job and confirm DB insertion
$PHP -r '
require __DIR__ . "/../_includes/init.php";
try {
  $pdo = km_db();
  $payload = ["media_id" => 0, "file_url" => "/dev/null"];
  $stmt = $pdo->prepare("INSERT INTO encoding_jobs (job_type,payload,status,attempts,created_at,updated_at) VALUES (\"thumbnail\", :p, \"queued\", 0, :ts, :ts)");
  $now = date("Y-m-d H:i:s");
  $stmt->execute([":p"=>json_encode($payload), ":ts"=>$now]);
  $id = $pdo->lastInsertId();
  echo "Inserted job id $id\n";
  exit(0);
} catch (Exception $e) {
  echo "DB error: ".$e->getMessage()."\n";
  exit(3);
}
'