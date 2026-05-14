<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;

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
        } elseif ($blockedUntil !== '') {
            $timestamp = strtotime($blockedUntil);
            $blockedUntilSql = $timestamp === false ? 'NULL' : $db->quote((new Date('@' . $timestamp))->toSql());
        } else {
            $blockedUntilSql = 'NULL';
        }

        $reason = $reason === '' ? 'manual' : substr($reason, 0, 50);

        if ($id > 0) {
            $fields = [
                $db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress),
                $db->quoteName('scope') . ' = ' . $db->quote($scope),
                $db->quoteName('block_type') . ' = ' . $db->quote($blockType),
                $db->quoteName('reason') . ' = ' . $db->quote($reason),
                $db->quoteName('failure_count') . ' = ' . (string) $failureCount,
                $db->quoteName('blocked_until') . ' = ' . $blockedUntilSql,
                $db->quoteName('enabled') . ' = ' . (string) $enabled,
            ];
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__loginguard_blocked_ips'))
                ->set($fields)
                ->where($db->quoteName('id') . ' = ' . (string) $id);
            $message = Text::_('COM_LOGINGUARD_BLOCKEDIPS_ITEM_UPDATED');
        } else {
            $columns = ['ip_address', 'scope', 'block_type', 'reason', 'failure_count', 'blocked_until', 'created', 'created_by', 'enabled'];
            $values = [
                $db->quote($ipAddress),
                $db->quote($scope),
                $db->quote($blockType),
                $db->quote($reason),
                (string) $failureCount,
                $blockedUntilSql,
                $db->quote((new Date())->toSql()),
                (string) (int) $app->getIdentity()->id,
                (string) $enabled,
            ];
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__loginguard_blocked_ips'))
                ->columns($db->quoteName($columns))
                ->values(implode(',', $values));
            $message = Text::_('COM_LOGINGUARD_BLOCKEDIPS_ITEM_CREATED');
        }

        $db->setQuery($query)->execute();
        $this->setMessage($message);
        $this->setRedirect('index.php?option=com_loginguard&view=blockedips');
    }

    public function delete(): void
    {
        LoginGuardHelper::requirePermission('loginguard.manage_blocks');
        $this->checkToken();
        $ids = $this->getSelectedIds();

        if ($ids !== []) {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseDriver::class);
            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__loginguard_blocked_ips'))
                ->whereIn($db->quoteName('id'), $ids);
            $db->setQuery($query)->execute();
        }

        $this->setMessage(Text::plural('COM_LOGINGUARD_BLOCKEDIPS_N_ITEMS_DELETED', count($ids)));
        $this->setRedirect('index.php?option=com_loginguard&view=blockedips');
    }

    public function enable(): void
    {
        $this->setEnabledState(1, 'COM_LOGINGUARD_BLOCKEDIPS_N_ITEMS_ENABLED');
    }

    public function disable(): void
    {
        $this->setEnabledState(0, 'COM_LOGINGUARD_BLOCKEDIPS_N_ITEMS_DISABLED');
    }

    public function unblock(): void
    {
        $this->setEnabledState(0, 'COM_LOGINGUARD_BLOCKEDIPS_N_ITEMS_UNBLOCKED');
    }

    /** @return array<int, int> */
    private function getSelectedIds(): array
    {
        $ids = array_map('intval', (array) $this->input->get('cid', [], 'array'));

        return array_values(array_filter($ids));
    }

    private function setEnabledState(int $enabled, string $messageKey): void
    {
        LoginGuardHelper::requirePermission('loginguard.manage_blocks');
        $this->checkToken();
        $ids = $this->getSelectedIds();

        if ($ids !== []) {
            $db = Factory::getContainer()->get(\Joomla\Database\DatabaseDriver::class);
            $query = $db->getQuery(true)
                ->update($db->quoteName('#__loginguard_blocked_ips'))
                ->set($db->quoteName('enabled') . ' = ' . (string) $enabled)
                ->whereIn($db->quoteName('id'), $ids);
            $db->setQuery($query)->execute();
        }

        $this->setMessage(Text::plural($messageKey, count($ids)));
        $this->setRedirect('index.php?option=com_loginguard&view=blockedips');
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
