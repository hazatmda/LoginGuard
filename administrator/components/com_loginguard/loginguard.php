<?php

/**
 * @package     LoginGuard.Administrator
 * @subpackage  com_loginguard
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app = Factory::getApplication();
$input = $app->getInput();
$component = $app->bootComponent('com_loginguard');
$controller = $component->getMVCFactory()->createController(
    'Display',
    'Administrator',
    [],
    $app,
    $input
);

$controller->execute($input->getCmd('task', 'display'));
$controller->redirect();
