<?php

/**
 * @package     LoginGuard.Administrator
 * @subpackage  com_loginguard
 */

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use LoginGuard\Component\LoginGuard\Administrator\Extension\LoginGuardComponent;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the component services required by Joomla's component dispatcher.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     */
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('\\LoginGuard\\Component\\LoginGuard'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\LoginGuard\\Component\\LoginGuard'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container): LoginGuardComponent {
                $component = new LoginGuardComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
