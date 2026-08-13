<?php
/**
 * Ownership-check helpers for the Trade Log feature.
 * Every action on a client-supplied account_id/trade_id must go through
 * one of these first -- never trust a submitted ID belongs to the
 * current session user without checking.
 */

function trade_log_get_account(PDO $pdo, int $accountId, int $userId): ?array {
    $stmt = $pdo->prepare(
        'SELECT * FROM brokerage_accounts WHERE id = :id AND user_id = :user_id LIMIT 1'
    );
    $stmt->execute(['id' => $accountId, 'user_id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function trade_log_user_account_ids(PDO $pdo, int $userId): array {
    $stmt = $pdo->prepare(
        'SELECT id FROM brokerage_accounts WHERE user_id = :user_id ORDER BY brokerage_name, account_label'
    );
    $stmt->execute(['user_id' => $userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function trade_log_get_trade(PDO $pdo, int $tradeId, int $userId): ?array {
    $stmt = $pdo->prepare(
        'SELECT t.* FROM trades t
         JOIN brokerage_accounts ba ON ba.id = t.account_id
         WHERE t.id = :id AND ba.user_id = :user_id LIMIT 1'
    );
    $stmt->execute(['id' => $tradeId, 'user_id' => $userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
