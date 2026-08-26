<?php

declare(strict_types=1);

namespace Joomla\CMS\Plugin {
    class CMSPlugin
    {
    }
}

namespace Joomla\Event {
    interface SubscriberInterface
    {
        public static function getSubscribedEvents(): array;
    }

    class Event
    {
        public function __construct(private array $arguments = [])
        {
        }

        public function getArguments(): array
        {
            return $this->arguments;
        }
    }
}

namespace Joomla\CMS\Authentication {
    class AuthenticationResponse
    {
        public string $username = '';
        public string $error_message = '';
        public string $type = '';
    }
}

namespace {
    define('_JEXEC', 1);
    require dirname(__DIR__) . '/plugins/user/loginguard/src/Extension/LoginGuard.php';

    use Joomla\CMS\Authentication\AuthenticationResponse;
    use Joomla\Event\Event;
    use Joomla\Plugin\User\LoginGuard\Extension\LoginGuard;

    final class LoginFailureEventFixture extends Event
    {
        public function __construct(
            private AuthenticationResponse $response,
            array $options
        ) {
            parent::__construct(['subject' => $response, 'options' => $options]);
        }

        public function getAuthenticationResponse(): AuthenticationResponse
        {
            return $this->response;
        }
    }

    $plugin = (new ReflectionClass(LoginGuard::class))->newInstanceWithoutConstructor();
    $extract = new ReflectionMethod(LoginGuard::class, 'getAuthenticationResponseFromEvent');
    $classify = new ReflectionMethod(LoginGuard::class, 'detectFailureReason');

    $cases = [
        ['alice', 'User could not be found', 'USERNAME_NOT_FOUND'],
        ['bob', 'Password does not match', 'PASSWORD_INCORRECT'],
    ];

    foreach ($cases as [$username, $message, $type]) {
        $response = new AuthenticationResponse();
        $response->username = $username;
        $response->error_message = $message;
        $response->type = $type;
        $options = [
            'username' => 'displaced-user',
            'error_message' => 'generic options error',
            'type' => 'INVALID_CREDENTIALS',
        ];

        foreach ([new LoginFailureEventFixture($response, $options), new Event(['subject' => $response, 'options' => $options])] as $event) {
            $payload = $extract->invoke($plugin, $event);

            if ($payload !== $response || $payload->username !== $username || $payload->error_message !== $message) {
                throw new RuntimeException('Login options displaced the failed authentication response');
            }
            if ($classify->invoke($plugin, $payload) !== $type) {
                throw new RuntimeException("Failed authentication response was not classified as {$type}");
            }
        }
    }

    $source = file_get_contents(dirname(__DIR__) . '/plugins/user/loginguard/src/Extension/LoginGuard.php');
    $start = strpos($source, 'public function onUserLoginFailure');
    $end = strpos($source, 'public function onUserAfterLogout', $start);
    $handler = substr($source, $start, $end - $start);
    if (!str_contains($handler, 'getAuthenticationResponseFromEvent($event)') || str_contains($handler, 'normaliseEventPayload')) {
        throw new RuntimeException('Failure handler must read the typed authentication response directly');
    }

    echo "Typed failed-login responses retain identity, error, and classification.\n";
}
