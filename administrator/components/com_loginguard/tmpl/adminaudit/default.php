<?php

defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use LoginGuard\Component\LoginGuard\Administrator\Helper\LoginGuardHelper;
?>
<div class="container-fluid">
    <div class="alert alert-info"><?php echo Text::_('COM_LOGINGUARD_ADMIN_AUDIT_READ_ONLY'); ?></div>
    <div class="table-responsive">
        <table class="table table-striped" id="loginguardAdminAuditList">
            <thead><tr>
                <th><?php echo Text::_('JGLOBAL_FIELD_ID_LABEL'); ?></th>
                <th><?php echo Text::_('COM_LOGINGUARD_AUDIT_ACTOR'); ?></th>
                <th><?php echo Text::_('COM_LOGINGUARD_AUDIT_ACTION'); ?></th>
                <th><?php echo Text::_('COM_LOGINGUARD_AUDIT_TARGET'); ?></th>
                <th><?php echo Text::_('COM_LOGINGUARD_AUDIT_DETAILS'); ?></th>
                <th><?php echo Text::_('COM_LOGINGUARD_HEADING_DATETIME'); ?></th>
            </tr></thead>
            <tbody>
            <?php foreach ($this->items as $item) : ?>
                <tr>
                    <td><?php echo (int) $item->id; ?></td>
                    <td><?php echo $this->escape((string) $item->actor_username); ?> (#<?php echo (int) $item->actor_user_id; ?>)</td>
                    <td><?php echo $this->escape((string) $item->action); ?></td>
                    <td><?php echo $this->escape((string) $item->target_type); ?>: <?php echo $this->escape((string) $item->target_id); ?></td>
                    <td><code><?php echo $this->escape((string) $item->details); ?></code></td>
                    <td><?php echo LoginGuardHelper::formatConfiguredDateTime((string) $item->created); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php echo $this->pagination->getListFooter(); ?>
</div>
