<?php

defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\HTML\Registry;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Component\LoginGuard\Administrator\Extension\LoginGuardComponent;

return new class () implements ServiceProviderInterface {
    public function register(Container $container): void
    {
        $container->registerServiceProvider(new MVCFactory('Joomla\\Component\\LoginGuard'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('Joomla\\Component\\LoginGuard'));

        $container->set(
            ComponentInterface::class,
            static function (Container $container): MVCComponent {
                $component = new LoginGuardComponent(
                    $container->get(ComponentDispatcherFactoryInterface::class),
                    $container->get(MVCFactoryInterface::class)
                );

                $component->setRegistry($container->get(Registry::class));

                return $component;
            }
        );
    }
};
