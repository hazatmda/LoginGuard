<?php

namespace Joomla\Database {
    interface DatabaseInterface
    {
    }
}

namespace {
    define('_JEXEC', 1);
    require __DIR__ . '/../administrator/components/com_loginguard/src/Service/AdminAuditService.php';

    use Joomla\Database\DatabaseInterface;
    use LoginGuard\Component\LoginGuard\Administrator\Service\AdminAuditService;

    final class AuditQueryStub
    {
        public string $values = '';

        public function insert(string $table): self { return $this; }
        public function columns(array $columns): self { return $this; }
        public function values(string $values): self { $this->values = $values; return $this; }
    }

    final class AuditDatabaseStub implements DatabaseInterface
    {
        public AuditQueryStub $query;

        public function quote(string $value): string { return "'" . str_replace("'", "''", $value) . "'"; }
        public function quoteName(string|array $name): string|array { return $name; }
        public function getQuery(bool $new = false): AuditQueryStub { return $this->query = new AuditQueryStub(); }
        public function setQuery(AuditQueryStub $query): self { $this->query = $query; return $this; }
        public function execute(): void {}
    }

    $db = new AuditDatabaseStub();
    $actor = (object) ['id' => 7, 'username' => str_repeat('界', 260)];
    $targets = implode(',', range(1, 400));
    (new AdminAuditService($db))->append($actor, 'blocked_ip.delete', 'blocked_ip', $targets, ['label' => str_repeat('é', 600)]);

    if (!str_contains($db->query->values, "'" . $targets . "'")) {
        throw new RuntimeException('Bulk target IDs were truncated');
    }

    if (!preg_match("/^7,'([^']+)'/u", $db->query->values, $match) || mb_strlen($match[1], 'UTF-8') !== 255) {
        throw new RuntimeException('Actor username was not truncated safely by UTF-8 character');
    }

    json_decode(str_repeat('"', 0)); // Ensure JSON extension is active for the service path.
    echo "Admin audit service regressions completed successfully\n";
}
