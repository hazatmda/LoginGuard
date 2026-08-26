-- Repair pre-release/legacy audit tables without discarding forensic rows.  The
-- prepared statement keeps this compatible with both a table missing the new
-- column and an already-repaired 0.2.27 table.
SET @loginguard_has_actor_username = (
  SELECT COUNT(*) FROM `information_schema`.`COLUMNS`
  WHERE `TABLE_SCHEMA` = DATABASE()
    AND `TABLE_NAME` = '#__loginguard_admin_audit'
    AND `COLUMN_NAME` = 'actor_username'
);
SET @loginguard_repair_sql = IF(
  @loginguard_has_actor_username = 0,
  'ALTER TABLE `#__loginguard_admin_audit` ADD COLUMN `actor_username` varchar(255) NOT NULL DEFAULT '''' AFTER `actor_user_id`',
  'SELECT 1'
);
PREPARE loginguard_repair_statement FROM @loginguard_repair_sql;
EXECUTE loginguard_repair_statement;
DEALLOCATE PREPARE loginguard_repair_statement;

-- Widening/conversion preserves legacy integer target values and permits the
-- NULL target used by export audit records.  The legacy target_ip column is
-- deliberately retained as forensic evidence.
ALTER TABLE `#__loginguard_admin_audit`
  MODIFY `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  MODIFY `actor_user_id` int unsigned NOT NULL DEFAULT 0,
  MODIFY `action` varchar(64) NOT NULL,
  MODIFY `target_type` varchar(64) NOT NULL,
  MODIFY `target_id` text NULL DEFAULT NULL;

-- Populate only empty values, and retain the empty-string default where the
-- referenced Joomla user no longer exists.
UPDATE `#__loginguard_admin_audit` AS `audit`
LEFT JOIN `#__users` AS `users` ON `users`.`id` = `audit`.`actor_user_id`
SET `audit`.`actor_username` = COALESCE(`users`.`username`, '')
WHERE `audit`.`actor_username` = '';

SET @loginguard_has_actor_index = (
  SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '#__loginguard_admin_audit'
    AND `INDEX_NAME` = 'idx_loginguard_admin_audit_actor'
);
SET @loginguard_repair_sql = IF(@loginguard_has_actor_index = 0,
  'ALTER TABLE `#__loginguard_admin_audit` ADD INDEX `idx_loginguard_admin_audit_actor` (`actor_user_id`)', 'SELECT 1');
PREPARE loginguard_repair_statement FROM @loginguard_repair_sql;
EXECUTE loginguard_repair_statement;
DEALLOCATE PREPARE loginguard_repair_statement;

SET @loginguard_has_action_index = (
  SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '#__loginguard_admin_audit'
    AND `INDEX_NAME` = 'idx_loginguard_admin_audit_action'
);
SET @loginguard_repair_sql = IF(@loginguard_has_action_index = 0,
  'ALTER TABLE `#__loginguard_admin_audit` ADD INDEX `idx_loginguard_admin_audit_action` (`action`)', 'SELECT 1');
PREPARE loginguard_repair_statement FROM @loginguard_repair_sql;
EXECUTE loginguard_repair_statement;
DEALLOCATE PREPARE loginguard_repair_statement;

SET @loginguard_has_created_index = (
  SELECT COUNT(*) FROM `information_schema`.`STATISTICS`
  WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = '#__loginguard_admin_audit'
    AND `INDEX_NAME` = 'idx_loginguard_admin_audit_created'
);
SET @loginguard_repair_sql = IF(@loginguard_has_created_index = 0,
  'ALTER TABLE `#__loginguard_admin_audit` ADD INDEX `idx_loginguard_admin_audit_created` (`created`)', 'SELECT 1');
PREPARE loginguard_repair_statement FROM @loginguard_repair_sql;
EXECUTE loginguard_repair_statement;
DEALLOCATE PREPARE loginguard_repair_statement;
