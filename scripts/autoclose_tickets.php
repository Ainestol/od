<?php
require_once __DIR__ . '/../config/db.php';

$pdo->prepare("
  UPDATE bug_reports br
  SET status = 'CLOSED'
  WHERE status = 'RESOLVED'
    AND EXISTS (
      SELECT 1
      FROM bug_report_messages m
      WHERE m.bug_report_id = br.id
        AND m.author_role = 'system'
        AND m.message LIKE '%potvrdil%'
        AND m.created_at < NOW() - INTERVAL 7 DAY
    )
")->execute();
