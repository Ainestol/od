<?php

/**
 * Jednoduchý fixed-window rate-limit.
 * @throws Exception('RATE_LIMITED')
 */
function rate_limit(PDO $pdoWeb, string $key, int $limit, int $windowSec): void
{
    try {
        if (!$pdoWeb->inTransaction()) {
            $pdoWeb->beginTransaction();
        }

        $stmt = $pdoWeb->prepare("
            SELECT id, window_start, window_sec, count
            FROM api_rate_limits
            WHERE bucket_key = ?
            FOR UPDATE
        ");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $now = time();

        if (!$row) {
            $pdoWeb->prepare("
                INSERT INTO api_rate_limits (bucket_key, window_start, window_sec, count)
                VALUES (?, NOW(), ?, 1)
            ")->execute([$key, $windowSec]);

            $pdoWeb->commit();
            return;
        }

        $windowStart = strtotime($row['window_start']);
        $elapsed = $now - $windowStart;

        if ($elapsed >= (int)$row['window_sec']) {
            $pdoWeb->prepare("
                UPDATE api_rate_limits
                SET window_start = NOW(), window_sec = ?, count = 1
                WHERE id = ?
            ")->execute([$windowSec, $row['id']]);

            $pdoWeb->commit();
            return;
        }

        if ((int)$row['count'] >= $limit) {
            $pdoWeb->rollBack();
            throw new Exception('RATE_LIMITED');
        }

        $pdoWeb->prepare("
            UPDATE api_rate_limits
            SET count = count + 1
            WHERE id = ?
        ")->execute([$row['id']]);

        $pdoWeb->commit();

    } catch (Throwable $e) {
        if ($pdoWeb->inTransaction()) {
            $pdoWeb->rollBack();
        }
        throw $e;
    }
}

/**
 * Bezpečné získání IP
 */
function client_ip(): string
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
