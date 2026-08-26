<?php

namespace LoginGuard\Component\LoginGuard\Administrator\Service;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Throwable;

/** Shared configured Success/Failed Alert pipeline for password and MFA outcomes. */
final class AuditAlertService
{
    /** @param array<string, mixed> $record */
    public function send(array $record, DatabaseInterface $db): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $params = ComponentHelper::getParams('com_loginguard');
        if (!(int) $params->get('audit_alerts_enabled', 0)) {
            return;
        }

        $status = (string) ($record['status'] ?? '');
        $isSuccess = $status === 'SUCCESS_LOGIN';
        if ($isSuccess && !(int) $params->get('audit_alert_success', 0)) {
            return;
        }
        if (!$isSuccess && !(int) $params->get('audit_alert_failed', 1)) {
            return;
        }
        if (!$isSuccess && $this->isFailedAlertThrottled($record, $db)) {
            return;
        }

        $recipients = $this->normaliseAlertRecipients((string) $params->get('audit_alert_recipients', ''));
        if ($recipients === []) {
            return;
        }

        $subject = (string) $params->get($isSuccess ? 'audit_alert_success_subject' : 'audit_alert_failed_subject', $isSuccess ? '[LOGIN GUARD] SUCCESSFUL BACKEND LOGIN' : '[LOGIN GUARD] FAILED LOGIN ATTEMPT');
        $body = (string) $params->get($isSuccess ? 'audit_alert_success_body' : 'audit_alert_failed_body', $this->getDefaultAlertBodyTemplate());
        $this->sendMail($recipients, $subject, $body, $this->buildAlertTemplateVariables($record), $status, $db);
    }

    /** @param list<string> $recipients @param array<string, string> $variables */
    private function sendMail(array $recipients, string $subjectTemplate, string $bodyTemplate, array $variables, string $status, DatabaseInterface $db): void
    {
        $subject = strtoupper($this->replaceAlertTemplateVariables($subjectTemplate, $variables));
        $plainBody = $this->withAlertFooter($this->replaceAlertTemplateVariables($bodyTemplate, $variables));
        $htmlBody = $this->buildAlertHtmlBody($subject, $bodyTemplate, $variables, $status);

        try {
            $mailer = Factory::getMailer();
            $mailer->addRecipient($recipients);
            $mailer->setSubject($subject);
            $mailer->setBody($htmlBody);
            $mailer->AltBody = $plainBody;
            $mailer->isHtml(true);
            $mailer->Send();
            OperationalAudit::recordHealth($db, 'mail', 'healthy', 'Last configured LoginGuard alert was sent successfully.');
        } catch (Throwable $exception) {
            OperationalAudit::logFailure('mail', $exception->getMessage());
        }
    }

    /** @param array<string, mixed> $record */
    private function isFailedAlertThrottled(array $record, DatabaseInterface $db): bool
    {
        $params = ComponentHelper::getParams('com_loginguard');
        if (!$params->get('failed_alert_throttling_enabled', 0)) {
            return false;
        }

        $ipAddress = (string) ($record['ip_address'] ?? '');
        if ($ipAddress === '' || $ipAddress === 'unknown') {
            return false;
        }

        $threshold = max(1, (int) $params->get('failed_alert_threshold', 10));
        $windowMinutes = max(1, (int) $params->get('failed_alert_throttle_window_minutes', 15));
        $since = gmdate('Y-m-d H:i:s', time() - ($windowMinutes * 60));

        try {
            $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__loginguard_attempts'))
                ->where($db->quoteName('status') . ' IN (' . implode(',', [$db->quote('FAILED_LOGIN'), $db->quote('MFA_FAILED'), $db->quote('MFA_TRY_LIMIT')]) . ')')
                ->where($db->quoteName('ip_address') . ' = ' . $db->quote($ipAddress))
                ->where($db->quoteName('created') . ' >= ' . $db->quote($since));
            $db->setQuery($query);
            return (int) $db->loadResult() > $threshold;
        } catch (Throwable $exception) {
            OperationalAudit::logFailure('alert_throttle', $exception->getMessage());
            return false;
        }
    }

    /** @return list<string> */
    private function normaliseAlertRecipients(string $configuredRecipients): array
    {
        $recipients = preg_split('/[\s,;]+/', $configuredRecipients, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $validRecipients = [];

        foreach ($recipients as $recipient) {
            $recipient = trim($recipient);
            if ($recipient !== '' && filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $validRecipients[] = $recipient;
            }
        }

        return array_values(array_unique($validRecipients));
    }

    /** @param array<string, mixed> $record @return array<string, string> */
    private function buildAlertTemplateVariables(array $record): array
    {
        $config = Factory::getConfig();
        $status = (string) ($record['status'] ?? '');
        $failureReason = in_array($status, ['FAILED_LOGIN', 'BLOCKED_LOGIN', 'MFA_FAILED', 'MFA_TRY_LIMIT'], true)
            ? (string) ($record['reason'] ?? '') : '';

        return [
            'username' => $this->formatNullableUsername($record['username'] ?? null),
            'ip' => (string) ($record['ip_address'] ?? 'unknown'),
            'status' => $this->formatAlertStatus($status),
            'failure_reason' => $this->formatAlertFailureReason($failureReason),
            'where' => $this->formatAlertWhere((string) ($record['where_at'] ?? 'frontend')),
            'browser' => (string) ($record['browser'] ?? 'unknown'),
            'os' => (string) ($record['operating_system'] ?? 'unknown'),
            'datetime' => $this->formatConfiguredDateTime((string) ($record['created'] ?? gmdate('Y-m-d H:i:s'))),
            'site_name' => (string) $config->get('sitename', ''),
            'name' => (string) ($record['name'] ?? ''),
            'full_name' => (string) ($record['name'] ?? ''),
            'email' => (string) ($record['email'] ?? ''),
            'user_agent' => (string) ($record['user_agent'] ?? ''),
            'mfa_method' => (string) ($record['mfa_method'] ?? ''),
            'mfa_status' => $this->formatAlertStatus((string) ($record['mfa_status'] ?? '')),
            'mfa_reason' => $this->formatAlertFailureReason((string) ($record['mfa_reason'] ?? '')),
        ];
    }

    private function getConfiguredTimezone(): \DateTimeZone
    {
        $timezone = (string) Factory::getConfig()->get('offset', 'UTC');
        try {
            return new \DateTimeZone($timezone !== '' ? $timezone : 'UTC');
        } catch (\Exception $exception) {
            return new \DateTimeZone('UTC');
        }
    }

    private function formatConfiguredDateTime(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            $date = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
        } catch (\Exception $exception) {
            return '';
        }

        return $date->setTimezone($this->getConfiguredTimezone())->format(Text::_('DATE_FORMAT_LC5'));
    }

    /** @param array<string, string> $variables */
    private function replaceAlertTemplateVariables(string $template, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $name => $value) {
            $replacements['{' . $name . '}'] = $value;
        }
        return strtr($template, $replacements);
    }

    private function getDefaultAlertBodyTemplate(): string
    {
        return "LoginGuard recorded a {status} event on {site_name}.\n\nFull Name: {full_name}\nUsername: {username}\nEmail: {email}\nIP Address: {ip}\nWhere: {where}\nBrowser: {browser}\nOperating System: {os}\nFailure Reason: {failure_reason}\nUser Agent: {user_agent}\nDate/Time: {datetime}\n\nGenerated automatically by Login Guard MDA.";
    }

    private function getDefaultBlockedIpAlertBodyTemplate(): string
    {
        return "LoginGuard recorded a {status} event on {site_name}.\n\nFull Name: {full_name}\nUsername: {username}\nEmail: {email}\nIP Address: {ip}\nWhere: {where}\nBrowser: {browser}\nOperating System: {os}\nFailure Reason: {failure_reason}\nBlock Type: {block_type}\nBlock Reason: {block_reason}\nBlocked Until: {block_until}\nFailure Count: {failure_count}\nUser Agent: {user_agent}\nDate/Time: {datetime}\n\nGenerated automatically by Login Guard MDA.";
    }

    private function withAlertFooter(string $body): string
    {
        $footer = 'Generated automatically by Login Guard MDA.';
        if (str_contains($body, $footer)) {
            return $body;
        }
        return rtrim($body) . "\n\n" . $footer;
    }

    /** @param array<string, string> $variables */
    private function buildAlertHtmlBody(string $subject, string $bodyTemplate, array $variables, string $status): string
    {
        $accentColor = match ($status) {
            'SUCCESS_LOGIN', 'MFA_SUCCESS' => '#1f8f45',
            'BLOCKED_LOGIN', 'MFA_TRY_LIMIT' => '#b45309',
            default => '#c62828',
        };
        $severityBackground = match ($status) {
            'SUCCESS_LOGIN', 'MFA_SUCCESS' => '#ecfdf3',
            'BLOCKED_LOGIN', 'MFA_TRY_LIMIT' => '#fffbeb',
            default => '#fef2f2',
        };
        $severityText = match ($status) {
            'SUCCESS_LOGIN', 'MFA_SUCCESS' => '#166534',
            'BLOCKED_LOGIN', 'MFA_TRY_LIMIT' => '#92400e',
            default => '#991b1b',
        };
        $htmlRows = '';

        foreach ($this->buildStructuredAlertRows($bodyTemplate, $variables, $status) as $row) {
            $htmlRows .= '<tr>'
                . '<th class="lg-label" style="padding:10px 12px;text-align:left;color:#475569;border-bottom:1px solid #e2e8f0;width:34%;font-weight:700;font-size:13px;line-height:1.4;vertical-align:top;">' . htmlspecialchars($row['label'], ENT_QUOTES, 'UTF-8') . '</th>'
                . '<td class="lg-value" style="padding:10px 12px;color:#0f172a;border-bottom:1px solid #e2e8f0;font-size:14px;line-height:1.45;word-break:break-word;vertical-align:top;">' . nl2br(htmlspecialchars($row['value'], ENT_QUOTES, 'UTF-8')) . '</td>'
                . '</tr>';
        }

        $intro = $this->extractAlertIntro($bodyTemplate, $variables);
        $footer = $this->extractAlertFooter($bodyTemplate, $variables);
        $statusLabel = $variables['status'] ?? $this->formatAlertStatus($status);
        $siteName = $variables['site_name'] ?? '';

        return '<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>@media only screen and (max-width:600px){.lg-wrap{padding:12px!important}.lg-card-body{padding:18px!important}.lg-label,.lg-value{display:block!important;width:auto!important}.lg-label{padding-bottom:4px!important;border-bottom:0!important}.lg-value{padding-top:0!important}.lg-title{font-size:18px!important}}</style>'
            . '</head><body style="margin:0;padding:0;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;-webkit-text-size-adjust:100%;">'
            . '<div class="lg-wrap" style="max-width:680px;margin:0 auto;padding:24px 16px;">'
            . '<div style="background:#ffffff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,0.08);">'
            . '<div style="height:8px;background:' . $accentColor . ';"></div>'
            . '<div class="lg-card-body" style="padding:24px;">'
            . '<p style="margin:0 0 10px;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#64748b;font-weight:700;">LoginGuard Security Notification</p>'
            . '<h1 class="lg-title" style="margin:0 0 14px;font-size:20px;line-height:1.3;color:#0f172a;">' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . '</h1>'
            . '<div style="display:inline-block;margin:0 0 18px;padding:6px 10px;border-radius:999px;background:' . $severityBackground . ';color:' . $severityText . ';font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;">' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</div>'
            . ($intro !== '' ? '<p style="margin:0 0 18px;font-size:14px;line-height:1.55;color:#334155;">' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>' : '')
            . '<table role="presentation" cellspacing="0" cellpadding="0" style="width:100%;border-collapse:collapse;margin:0 0 20px;border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;">' . $htmlRows . '</table>'
            . '<p style="margin:0;font-size:12px;color:#64748b;text-align:center;line-height:1.5;">' . htmlspecialchars($footer, ENT_QUOTES, 'UTF-8') . ($siteName !== '' ? '<br>' . htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8') : '') . '</p>'
            . '</div></div></div></body></html>';
    }

    /** @param array<string, string> $variables @return list<array{label: string, value: string}> */
    private function buildStructuredAlertRows(string $bodyTemplate, array $variables, string $status): array
    {
        $labels = $this->getAlertVariableLabels();
        $variableNames = $this->extractAlertTemplateVariableNames($bodyTemplate);

        if ($variableNames === []) {
            $variableNames = ['full_name', 'username', 'email', 'ip', 'status', 'failure_reason', 'where', 'browser', 'os', 'user_agent', 'datetime'];
        }
        if (in_array($status, ['SUCCESS_LOGIN', 'MFA_SUCCESS'], true)) {
            $variableNames = array_values(array_filter($variableNames, static fn ($name) => $name !== 'failure_reason'));
        }

        $rows = [];
        $seen = [];
        foreach ($variableNames as $name) {
            if (isset($seen[$name]) || !array_key_exists($name, $labels)) {
                continue;
            }
            $value = trim((string) ($variables[$name] ?? ''));
            if ($value === '') {
                continue;
            }
            $rows[] = ['label' => $this->extractAlertVariableLabel($bodyTemplate, $name, $labels[$name]), 'value' => $value];
            $seen[$name] = true;
        }
        return $rows;
    }

    /** @return array<string, string> */
    private function getAlertVariableLabels(): array
    {
        return [
            'full_name' => 'Full Name', 'name' => 'Full Name', 'username' => 'Username', 'email' => 'Email',
            'ip' => 'IP Address', 'status' => 'Status', 'failure_reason' => 'Failure Reason', 'where' => 'Where',
            'browser' => 'Browser', 'os' => 'Operating System', 'user_agent' => 'User Agent',
            'datetime' => 'Date/Time', 'block_type' => 'Block Type', 'block_reason' => 'Block Reason',
            'block_until' => 'Blocked Until', 'failure_count' => 'Failure Count', 'mfa_method' => 'MFA Method',
            'mfa_status' => 'MFA Status', 'mfa_reason' => 'MFA Reason',
        ];
    }

    /** @return list<string> */
    private function extractAlertTemplateVariableNames(string $template): array
    {
        preg_match_all('/\{([a-z0-9_]+)\}/i', $template, $matches);
        $names = [];
        foreach ($matches[1] ?? [] as $name) {
            $name = strtolower((string) $name);
            if (!in_array($name, $names, true)) {
                $names[] = $name;
            }
        }
        return $names;
    }

    private function extractAlertVariableLabel(string $template, string $name, string $default): string
    {
        if (preg_match('/^\s*([^\r\n:{}]{2,60})\s*:\s*\{' . preg_quote($name, '/') . '\}/mi', $template, $match)) {
            return trim($match[1]);
        }
        return $default;
    }

    /** @param array<string, string> $variables */
    private function extractAlertIntro(string $template, array $variables): string
    {
        $paragraphs = preg_split('/\R{2,}/', trim($template), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($paragraphs as $paragraph) {
            if (preg_match('/^\s*[^\r\n:{}]+\s*:\s*\{[a-z0-9_]+\}/mi', $paragraph)) {
                continue;
            }
            $intro = trim($this->replaceAlertTemplateVariables($paragraph, $variables));
            $intro = preg_replace('/\s+/', ' ', $intro) ?? $intro;
            if ($intro !== '' && !str_contains($intro, 'Generated automatically by Login Guard MDA.')) {
                return $intro;
            }
        }
        return 'LoginGuard recorded this security event with structured telemetry for review.';
    }

    /** @param array<string, string> $variables */
    private function extractAlertFooter(string $template, array $variables): string
    {
        $footer = 'Generated automatically by Login Guard MDA.';
        $paragraphs = preg_split('/\R{2,}/', trim($template), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        for ($index = count($paragraphs) - 1; $index >= 0; $index--) {
            $paragraph = trim($paragraphs[$index]);
            if ($index === 0 || $paragraph === '' || preg_match('/^\s*[^\r\n:{}]+\s*:\s*\{[a-z0-9_]+\}/mi', $paragraph)) {
                continue;
            }
            $customFooter = trim($this->replaceAlertTemplateVariables($paragraph, $variables));
            if ($customFooter !== '') {
                return $customFooter;
            }
        }
        return $footer;
    }

    private function formatAlertStatus(string $status): string
    {
        return match ($status) {
            'SUCCESS_LOGIN' => 'SUCCESS LOGIN',
            'FAILED_LOGIN' => 'FAILED LOGIN',
            'BLOCKED_LOGIN' => 'BLOCKED LOGIN',
            'MFA_PENDING' => 'MFA PENDING',
            'MFA_FAILED' => 'MFA FAILED',
            'MFA_SUCCESS' => 'MFA SUCCESS',
            'MFA_TRY_LIMIT' => 'MFA TRY LIMIT',
            default => str_replace('_', ' ', strtoupper($status)),
        };
    }

    private function formatAlertFailureReason(string $reason): string
    {
        return match ($reason) {
            'PASSWORD_INCORRECT' => 'Incorrect Password',
            'USERNAME_NOT_FOUND' => 'User Not Found',
            'ACCOUNT_DISABLED' => 'Account Disabled',
            'ACCOUNT_BLOCKED' => 'Account Blocked',
            'IP_BLOCKED' => 'IP Address Blocked',
            'INVALID_CREDENTIALS' => 'Invalid Credentials',
            'MFA_REQUIRED' => 'MFA Required',
            'MFA_INVALID_CODE' => 'Invalid MFA Code',
            'MFA_INVALID_METHOD' => 'Invalid MFA Method',
            'MFA_TRY_LIMIT_REACHED' => 'MFA Try Limit Reached',
            'MFA_COMPLETED' => 'MFA Completed',
            'MFA_THRESHOLD_EXCEEDED' => 'MFA Failure Threshold Exceeded',
            '' => '',
            default => ucwords(strtolower(str_replace('_', ' ', $reason))),
        };
    }

    private function formatAlertWhere(string $where): string
    {
        return match (strtolower($where)) {
            'backend', 'administrator' => 'Backend',
            'frontend', 'site' => 'Frontend',
            'api' => 'API',
            'cli' => 'CLI',
            default => ucwords(strtolower(str_replace('_', ' ', $where))),
        };
    }
}
