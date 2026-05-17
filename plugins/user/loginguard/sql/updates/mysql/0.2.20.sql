-- LoginGuard 0.2.20 preserves raw username telemetry and stores empty usernames as NULL.
UPDATE `#__loginguard_attempts`
   SET `username` = NULL
 WHERE `username` = '';

ALTER TABLE `#__loginguard_attempts`
  MODIFY `username` varchar(255) NULL DEFAULT NULL;
