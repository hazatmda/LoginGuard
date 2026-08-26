<?php

declare(strict_types=1);

namespace Joomla\CMS\Plugin { class CMSPlugin {} }
namespace Joomla\Event {
    class Event {
        public function __construct(private array $arguments = []) {}
        public function getArguments(): array { return $this->arguments; }
    }
}
namespace Joomla\CMS\Authentication { class AuthenticationResponse {} }

namespace {
    define('_JEXEC', 1);
    require dirname(__DIR__) . '/plugins/user/loginguard/src/Extension/LoginGuard.php';

    use Joomla\Event\Event;
    use Joomla\Plugin\User\LoginGuard\Extension\LoginGuard;

    final class LoginFailureEventFixture extends Event
    {
        public function __construct(private array $response, array $options)
        {
            parent::__construct(['subject' => $response, 'options' => $options]);
        }
        public function getAuthenticationResponse(): array { return $this->response; }
    }

    $plugin = (new ReflectionClass(LoginGuard::class))->newInstanceWithoutConstructor();
    $extract = new ReflectionMethod(LoginGuard::class, 'normaliseLoginFailurePayload');
    $classify = new ReflectionMethod(LoginGuard::class, 'detectFailureReason');

    foreach ([
        ['alice', 'User could not be found', 'USERNAME_NOT_FOUND'],
        ['bob', 'Password does not match', 'PASSWORD_INCORRECT'],
    ] as [$username, $message, $type]) {
        $response = ['username' => $username, 'error_message' => $message, 'type' => $type];
        $options = ['username' => 'wrong-user', 'error_message' => 'wrong error', 'type' => 'INVALID_CREDENTIALS'];

        foreach ([$response, new LoginFailureEventFixture($response, $options), new Event(['subject' => $response, 'options' => $options])] as $payload) {
            $actual = $extract->invoke($plugin, $payload);
            if ($actual !== $response || $classify->invoke($plugin, $actual) !== $type) {
                throw new RuntimeException('Legacy failure normalization lost Joomla response identity or reason');
            }
        }
    }

    echo "Legacy and Event array failed-login responses retain identity and classification.\n";
}
