<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Service;

defined('_JEXEC') or die;

use DateTimeImmutable;
use DateTimeZone;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use RuntimeException;
use Throwable;

final class TestEmailService
{
    public const SUBJECT = '[LOGIN GUARD] TEST EMAIL';
    public const INTRO = 'This is a LoginGuard test email. No real security event occurred.';

    /** @return list<string> */
    public static function normaliseRecipients(string $configuredRecipients): array
    {
        $items = preg_split('/[\s,;]+/', $configuredRecipients, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $valid = [];
        foreach ($items as $item) {
            $email = trim($item);
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[strtolower($email)] = $email;
            }
        }
        return array_values($valid);
    }

    /** @param list<string> $recipients */
    public function send(array $recipients): void
    {
        if ($recipients === []) {
            throw new RuntimeException('No valid configured recipients.');
        }

        $rows = $this->dummyRows();
        $mailer = Factory::getMailer();
        $mailer->addRecipient($recipients);
        $mailer->setSubject(self::SUBJECT);
        $mailer->setBody($this->htmlBody($rows));
        $mailer->AltBody = self::INTRO . "\n\n" . implode("\n", array_map(
            static fn (array $row): string => $row[0] . ': ' . $row[1],
            $rows
        )) . "\n\nGenerated automatically by Login Guard MDA.";
        $mailer->isHtml(true);
        if ($mailer->Send() === false) {
            throw new RuntimeException('Joomla mailer did not accept the message.');
        }
    }

    /** @return list<array{0:string,1:string}> */
    private function dummyRows(): array
    {
        $timezoneName = (string) Factory::getConfig()->get('offset', 'UTC');
        try {
            $timezone = new DateTimeZone($timezoneName !== '' ? $timezoneName : 'UTC');
        } catch (Throwable $exception) {
            $timezone = new DateTimeZone('UTC');
        }
        $date = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->setTimezone($timezone)->format(Text::_('DATE_FORMAT_LC5'));

        return [
            ['Full Name', 'LoginGuard Test Administrator'], ['Username', 'loginguard_test'],
            ['Email', 'loginguard-test@example.invalid'], ['IP Address', '203.0.113.10'],
            ['Where', 'Backend'], ['Browser', 'Google Chrome (Test Data)'],
            ['Operating System', 'Windows 11 (Test Data)'], ['Status', 'TEST EMAIL'],
            ['Failure Reason', 'None - Test Email'], ['Country', 'Malaysia'], ['Country Code', 'MY'],
            ['Region', 'Kuala Lumpur'], ['City', 'Kuala Lumpur'], ['ISP', 'Example ISP (Test Data)'],
            ['ASN', 'AS64500 (Test Data)'], ['User Agent', 'Mozilla/5.0 (LoginGuard Test Email)'],
            ['Date/Time', $date],
        ];
    }

    /** @param list<array{0:string,1:string}> $rows */
    private function htmlBody(array $rows): string
    {
        $table = '';
        foreach ($rows as [$label, $value]) {
            $table .= '<tr><th style="padding:10px 12px;text-align:left;color:#475569;border-bottom:1px solid #e2e8f0;width:34%;">'
                . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</th><td style="padding:10px 12px;color:#0f172a;border-bottom:1px solid #e2e8f0;">'
                . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $site = (string) Factory::getConfig()->get('sitename', '');
        return '<!doctype html><html><body style="margin:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a">'
            . '<div style="max-width:680px;margin:0 auto;padding:24px 16px"><div style="background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden">'
            . '<div style="height:8px;background:#2563eb"></div><div style="padding:24px"><p style="font-size:12px;text-transform:uppercase;color:#64748b;font-weight:700">LoginGuard Security Notification</p>'
            . '<h1 style="font-size:20px">' . self::SUBJECT . '</h1><div style="display:inline-block;padding:6px 10px;border-radius:999px;background:#eff6ff;color:#1d4ed8;font-weight:700">TEST EMAIL</div>'
            . '<p style="font-weight:700;color:#1d4ed8">' . self::INTRO . '</p><table style="width:100%;border-collapse:collapse;border:1px solid #e2e8f0">'
            . $table . '</table><p style="font-size:12px;color:#64748b;text-align:center">Generated automatically by Login Guard MDA.'
            . ($site !== '' ? '<br>' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8') : '') . '</p></div></div></div></body></html>';
    }
}
