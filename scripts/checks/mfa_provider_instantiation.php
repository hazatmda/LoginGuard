<?php

declare(strict_types=1);

namespace Joomla\Event {
    interface DispatcherInterface {}
    interface SubscriberInterface {}
    final class TestDispatcher implements DispatcherInterface {}
}

namespace Joomla\CMS\Extension {
    interface PluginInterface {}
}

namespace Joomla\CMS\Plugin {
    use Joomla\Event\DispatcherInterface;

    class CMSPlugin implements \Joomla\CMS\Extension\PluginInterface
    {
        public function __construct(DispatcherInterface $dispatcher, array $config = []) {}
        public function setApplication(object $application): void {}
    }

    final class PluginHelper
    {
        public static function getPlugin(string $folder, string $element): object
        {
            return (object) ['folder' => $folder, 'element' => $element];
        }
    }
}

namespace Joomla\Database {
    interface DatabaseInterface {}
    final class TestDatabase implements DatabaseInterface {}

    trait DatabaseAwareTrait
    {
        public function setDatabase(DatabaseInterface $database): void {}
    }
}

namespace Joomla\CMS {
    final class Factory
    {
        public static function getApplication(): object
        {
            return new \stdClass();
        }
    }
}

namespace Joomla\DI {
    interface ServiceProviderInterface
    {
        public function register(Container $container): void;
    }

    final class Container
    {
        private array $entries = [];

        public function set(string $id, mixed $value): void
        {
            $this->entries[$id] = $value;
        }

        public function get(string $id): mixed
        {
            $value = $this->entries[$id];
            return is_callable($value) ? $value($this) : $value;
        }
    }
}

namespace {
    define('_JEXEC', 1);

    require dirname(__DIR__, 2) . '/plugins/system/loginguardmfa/src/Extension/LoginGuardMfa.php';

    $container = new \Joomla\DI\Container();
    $container->set(\Joomla\Event\DispatcherInterface::class, new \Joomla\Event\TestDispatcher());
    $container->set(\Joomla\Database\DatabaseInterface::class, new \Joomla\Database\TestDatabase());

    $provider = require dirname(__DIR__, 2) . '/plugins/system/loginguardmfa/services/provider.php';
    $provider->register($container);
    $plugin = $container->get(\Joomla\CMS\Extension\PluginInterface::class);

    if (!$plugin instanceof \Joomla\Plugin\System\LoginGuardMfa\Extension\LoginGuardMfa) {
        throw new \RuntimeException('MFA system plugin provider did not instantiate LoginGuardMfa');
    }

    $expectedSubscriptions = [
        'onComUsersCaptiveShowCaptive' => 'onCaptiveShown',
        'onComUsersCaptiveValidateFailed' => 'onMfaFailed',
        'onComUsersCaptiveValidateTryLimitReached' => 'onMfaTryLimitReached',
        'onComUsersCaptiveValidateInvalidMethod' => 'onMfaInvalidMethod',
        'onComUsersCaptiveValidateSuccess' => 'onMfaSuccess',
    ];
    if ($plugin::getSubscribedEvents() !== $expectedSubscriptions) {
        throw new \RuntimeException('Isolation Candidate B must restore all captive MFA event subscriptions');
    }

    echo "MFA system plugin provider instantiated with normal captive event subscriptions (Isolation Candidate B)\n";
}
