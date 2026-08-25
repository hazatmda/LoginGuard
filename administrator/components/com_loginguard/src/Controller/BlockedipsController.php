<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;
use LoginGuard\Component\LoginGuard\Administrator\Service\OperationalAudit;
use Throwable;

final class BlockedipsController extends AdminController
{
    public function getModel($name = 'Blockedips', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function save(): void
    {
        LoginGuardHelper::requirePermission('loginguard.manage_blocks');
        $this->checkToken();

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseDriver::class);
        $app = Factory::getApplication();
        $actorId = (int) $app->getIdentity()->id;
        $id = $this->input->getInt('id', 0);
        $ipAddress = trim((string) $this->input->getString('ip_address', ''));
        $scope = $this->normaliseScope($this->input->getCmd('scope', 'all'));
        $blockType = $this->normaliseBlockType($this->input->getCmd('block_type', 'temporary'));
        $reason = trim((string) $this->input->getString('reason', 'manual'));
        $failureCount = max(0, $this->input->getInt('failure_count', 0));
        $blockedUntil = trim((string) $this->input->getString('blocked_until', ''));
        $enabled = $this->input->getInt('enabled', 1) ? 1 : 0;

        if ($ipAddress === '' || !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $app->enqueueMessage(Text::_('COM_LOGINGUARD_BLOCKEDIPS_INVALID_IP'), 'error');
            $this->setRedirect('index.php?option=com_loginguard&view=blockedips' . ($id > 0 ? '&edit_id=' . $id : ''));
            return;
        }

        if ($blockType === 'permanent') {
            $blockedUntilSql = 'NULL';
        } else {
            $blockedUntilDate = null;

            if ($blockedUntil !== '') {
                try {
                    $blockedUntilDate = new \DateTimeImmutable($blockedUntil, LoginGuardHelper::getConfiguredTimezone());
                } catch (\Exception $exception) {
                    $blockedUntilDate = null;
                }
            }

            if ($blockedUntilDate === null) {
                $cooldownMinutes = max(1, (int) ComponentHelper::getParams('com_loginguard')->get('cooldown_duration_minutes', 30));
                $blockedUntilDate = new \DateTimeImmutable('@' . (time() + ($cooldownMinutes * 60)));
            }

            $blockedUntilSql = $db->quote($blockedUntilDate->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'));
        }

        $reason = $reason === '' ? 'manual' : substr($reason, 0, 50);
        $now = gmdate('Y-m-d H:i:s');
        $activeKeySql = $enabled === 1 ? $db->quote(hash('sha256', $ipAddress . '|' . $scope)) : 'NULL';

        try {
            if ($id > 0) {
                $fields = [
                    $db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress),
                    $db->quoteName('scope') . ' = ' . $db->quote($scope),
                    $db->quoteName('block_type') . ' = ' . $db->quote($blockType),
                    $db->quoteName('reason') . ' = ' . $db->quote($reason),
                    $db->quoteName('active_key') . ' = ' . $activeKeySql,
                    $db->quoteName('failure_count') . ' = ' . (string) $failureCount,
                    $db->quoteName('blocked_until') . ' = ' . $blockedUntilSql,
                    $db->quoteName('updated') . ' = ' . $db->quote($now),
                    $db->quoteName('updated_by') . ' = ' . (string) $actorId,
                    $db->quoteName('disabled_at') . ' = ' . ($enabled === 1 ? 'NULL' : $db->quote($now)),
                    $db->quoteName('disabled_by') . ' = ' . ($enabled === 1 ? '0' : (string) $actorId),
                    $db->quoteName('enabled') . ' = ' . (string) $enabled,
                ];
                $query = $db->getQuery(true)
                    ->update($db->quoteName('#__loginguard_blocked_ips'))
                    ->set($fields)
                    ->where($db->quoteName('id') . ' = ' . (string) $id);
                $message = Text::_('COM_LOGINGUARD_BLOCKEDIPS_ITEM_UPDATED');
                $action = 'BLOCK_UPDATED';
            } else {
                $columns = [
                    'ip_address', 'scope', 'block_type', 'reason', 'source', 'active_key', 'failure_count',
                    'blocked_until', 'created', 'created_by', 'updated', 'updated_by', 'disabled_at', 'disabled_by', 'enabled',
                ];
                $values = [
                    $db->quote($ipAddress),
                    $db->quote($scope),
                    $db->quote($blockType),
                    $db->quote($reason),
                    $db->quote('manual'),
                    $activeKeySql,
                    (string) $failureCount,
                    $blockedUntilSql,
                    $db->quote($now),
                    (string) $actorId,
                    'NULL',
                    '0',
                    $enabled === 1 ? 'NULL' : $db->quote($now),
                    $enabled === 1 ? '0' : (string) $actorId,
                    (string) $enabled,
                ];
                $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__loginguard_blocked_ips'))
                    ->columns($db->quoteName($columns))
                    ->values(implode(',', $values));
                $message = Text::_('COM_LOGINGUARD_BLOCKEDIPS_ITEM_CREATED');
                $action = 'BLOCK_CREATED';
            }

            $db->setQuery($query)->execute();
            $targetId = $id > 0 ? $id : (int) $db->insertid();
            OperationalAudit::recordAdminAction($db, $action, 'blocked_ip', $targetId, $ipAddress, $actorId, [
                'scope' => $scope,
                'block_type' => $blockType,
                'reason' => $reason,
                'enabled' => $enabled,
            ]);
            OperationalAudit::recordHealth($db, 'database', 'healthy', 'Blocked-IP write completed successfully.');
            $this->setMessage($message);
        } catch (Throwable $exception) {
            OperationalAudit::logFailure('blocked_ip', $exception->getMessage());
            OperationalAudit::recordHealth($db, 'database', 'degraded', $exception->getMessage());
            $app->enqueueMessage('LoginGuard could not save this block. Check for an existing active block with the same IP/scope and review Joomla logs.', 'error');
        }

        $this->setRedirect('index.php?option=com_loginguard&view=blockedips');
    }

    /**
     * Normal delete is intentionally a soft-disable so security history remains
     * available until retention cleanup purges old disabled records.
     */
    public function delete(): void
    {
        $this->setEnabledState(0, 'COM_LOGINGUARD_BLOCKEDIPS_N_ITEMS_DELETED', 'BLOCK_DELETE_SOFT');
    }

    public function enable(): void
    {
        $this->setEnabledState(1, 'COM_LOGINGUARD_BLOCKEDIPS_N_ITEMS_ENABLED', 'BLOCK_ENABLED');
    }

    public function disable(): void
    {
        $this->setEnabledState(0, 'COM_LOGINGUARD_BLOCKEDIPS_N_ITEMS_DISABLED', 'BLOCK_DISABLED');
    }

    public function unblock(): void
    {
        $this->setEnabledState(0, 'COM_LOGINGUARD_BLOCKEDIPS_N_ITEMS_UNBLOCKED', 'BLOCK_UNBLOCKED');
    }

    /** @return array<int, int> */
    private function getSelectedIds(): array
    {
        $ids = array_map('intval', (array) $this->input->get('cid', [], 'array'));

        return array_values(array_filter($ids));
    }

    private function setEnabledState(int $enabled, string $messageKey, string $action): void
    {
        LoginGuardHelper::requirePermission('loginguard.manage_blocks');
        $this->checkToken();
        $ids = $this->getSelectedIds();
        $app = Factory::getApplication();
        $actorId = (int) $app->getIdentity()->id;

        if ($ids === []) {
            $this->setMessage(Text::plural($messageKey, 0));
            $this->setRedirect('index.php?option=com_loginguard&view=blockedips');
            return;
        }

        $db = Factory::getContainer()->get(\Joomla\Database\DatabaseDriver::class);
        $rows = $this->getBlockRows($db, $ids);
        $now = gmdate('Y-m-d H:i:s');

        try {
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__loginguard_blocked_ips'))
                ->set($db->quoteName('enabled') . ' = ' . (string) $enabled)
                ->set($db->quoteName('updated') . ' = ' . $db->quote($now))
                ->set($db->quoteName('updated_by') . ' = ' . (string) $actorId)
                ->set($db->quoteName('active_key') . ' = ' . ($enabled === 1
                    ? 'SHA2(CONCAT(' . $db->quoteName('ip_address') . ',' . $db->quote('|') . ',' . $db->quoteName('scope') . '),256)'
                    : 'NULL'))
                ->set($db->quoteName('disabled_at') . ' = ' . ($enabled === 1 ? 'NULL' : $db->quote($now)))
                ->set($db->quoteName('disabled_by') . ' = ' . ($enabled === 1 ? '0' : (string) $actorId))
                ->whereIn($db->quoteName('id'), $ids);
            $db->setQuery($query)->execute();

            foreach ($rows as $row) {
                OperationalAudit::recordAdminAction($db, $action, 'blocked_ip', (int) $row->id, (string) $row->ip_address, $actorId, [
                    'enabled' => $enabled,
                    'scope' => (string) $row->scope,
                ]);
            }
            OperationalAudit::recordHealth($db, 'database', 'healthy', 'Blocked-IP state update completed successfully.');
            $this->setMessage(Text::plural($messageKey, count($ids)));
        } catch (Throwable $exception) {
            OperationalAudit::logFailure('blocked_ip', $exception->getMessage());
            OperationalAudit::recordHealth($db, 'database', 'degraded', $exception->getMessage());
            $app->enqueueMessage('LoginGuard could not update the selected block state. An active duplicate may already exist for the same IP/scope.', 'error');
        }

        $this->setRedirect('index.php?option=com_loginguard&view=blockedips');
    }

    /** @return array<int, object> */
    private function getBlockRows($db, array $ids): array
    {
        $query = $db->getQuery(true)
            ->select([$db->quoteName('id'), $db->quoteName('ip_address'), $db->quoteName('scope')])
            ->from($db->quoteName('#__loginguard_blocked_ips'))
            ->whereIn($db->quoteName('id'), $ids);
        $db->setQuery($query);

        return $db->loadObjectList() ?: [];
    }

    private function normaliseScope(string $scope): string
    {
        return in_array($scope, ['all', 'frontend', 'backend'], true) ? $scope : 'all';
    }

    private function normaliseBlockType(string $blockType): string
    {
        return $blockType === 'permanent' ? 'permanent' : 'temporary';
    }
}
