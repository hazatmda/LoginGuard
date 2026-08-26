<?php

declare(strict_types=1);

namespace Joomla\CMS\Plugin {
    class CMSPlugin {}
}

namespace Joomla\CMS\Authentication {
    class Authentication { public const STATUS_DENIED = 0; }
    class AuthenticationResponse {}
}

namespace Joomla\Database {
    class DatabaseDriver {}
}

namespace Joomla\Event {
    class Event {}
}

namespace {
    define('_JEXEC', 1);
    require dirname(__DIR__) . '/plugins/user/loginguard/src/Extension/LoginGuard.php';

    $class = Joomla\Plugin\User\LoginGuard\Extension\LoginGuard::class;
    $plugin = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $user = ['username' => 'valid-user'];
    $options = ['action' => 'core.login.admin'];
    $results = [$plugin->onUserLogin($user, $options)];

    if ($results !== [true] || in_array(false, $results, true)) {
        fwrite(STDERR, 'LoginGuard must contribute exactly true to Joomla user-login result aggregation.' . PHP_EOL);
        exit(1);
    }
    if ($user !== ['username' => 'valid-user'] || $options !== ['action' => 'core.login.admin']) {
        fwrite(STDERR, 'The neutral login callback modified Joomla login inputs.' . PHP_EOL);
        exit(1);
    }

    echo 'User login lifecycle aggregation validation completed successfully' . PHP_EOL;
}
