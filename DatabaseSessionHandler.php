<?php
declare(strict_types=1);

require_once __DIR__ . "/DatabaseConnection.php";

final class DatabaseSessionHandler implements SessionHandlerInterface
{
    private bool $transactionStarted = false;
    private ?string $lockedSessionId = null;

    public function open(string $path, string $name): bool
    {
        app_database_pdo();
        return true;
    }

    public function close(): bool
    {
        $this->commitTransaction();
        return true;
    }

    public function read(string $id): string|false
    {
        $this->lockSession($id);
        $stmt = app_database_pdo()->prepare(
            "SELECT session_data, last_activity
             FROM PhpSessions
             WHERE session_id = :session_id
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute([":session_id" => $id]);
        $session = $stmt->fetch();
        if ($session === false) {
            return "";
        }

        $maxLifetime = max(1, (int) ini_get("session.gc_maxlifetime"));
        if ((int) $session["last_activity"] < time() - $maxLifetime) {
            $this->deleteSession($id);
            return "";
        }

        return (string) $session["session_data"];
    }

    public function write(string $id, string $data): bool
    {
        $this->lockSession($id);
        $stmt = app_database_pdo()->prepare(
            "INSERT INTO PhpSessions (session_id, session_data, last_activity)
             VALUES (:session_id, :session_data, :last_activity)
             ON DUPLICATE KEY UPDATE
               session_data = VALUES(session_data),
               last_activity = VALUES(last_activity)"
        );
        return $stmt->execute([
            ":session_id" => $id,
            ":session_data" => $data,
            ":last_activity" => time(),
        ]);
    }

    public function destroy(string $id): bool
    {
        $this->lockSession($id);
        return $this->deleteSession($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = app_database_pdo()->prepare(
            "DELETE FROM PhpSessions WHERE last_activity < :expires_before"
        );
        $stmt->execute([":expires_before" => time() - $max_lifetime]);
        $deleted = $stmt->rowCount();

        $lockCleanup = app_database_pdo()->prepare(
            "DELETE FROM PhpSessionLocks
             WHERE last_activity < :expires_before
               AND NOT EXISTS (
                 SELECT 1 FROM PhpSessions WHERE PhpSessions.session_id = PhpSessionLocks.session_id
               )"
        );
        $lockCleanup->execute([":expires_before" => time() - $max_lifetime]);
        return $deleted;
    }

    private function lockSession(string $id): void
    {
        if ($this->lockedSessionId === $id) {
            return;
        }
        if ($this->lockedSessionId !== null) {
            // session_regenerate_id() switches IDs during the same request.
            $this->commitTransaction();
        }

        $pdo = app_database_pdo();
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
            $this->transactionStarted = true;
        }

        $createLock = $pdo->prepare(
            "INSERT INTO PhpSessionLocks (session_id, last_activity)
             VALUES (:session_id, :last_activity)
             ON DUPLICATE KEY UPDATE last_activity = VALUES(last_activity)"
        );
        $createLock->execute([
            ":session_id" => $id,
            ":last_activity" => time(),
        ]);

        $lock = $pdo->prepare(
            "SELECT session_id
             FROM PhpSessionLocks
             WHERE session_id = :session_id
             FOR UPDATE"
        );
        $lock->execute([":session_id" => $id]);
        $lock->fetchColumn();
        $this->lockedSessionId = $id;
    }

    private function commitTransaction(): void
    {
        $pdo = app_database_pdo();
        if ($this->transactionStarted && $pdo->inTransaction()) {
            $pdo->commit();
        }
        $this->transactionStarted = false;
        $this->lockedSessionId = null;
    }

    private function deleteSession(string $id): bool
    {
        $stmt = app_database_pdo()->prepare(
            "DELETE FROM PhpSessions WHERE session_id = :session_id"
        );
        return $stmt->execute([":session_id" => $id]);
    }
}
