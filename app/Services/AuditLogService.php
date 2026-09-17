<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    private const SENSITIVE_KEY_PARTS = [
        'password',
        'token',
        'secret',
        'authorization',
        'cookie',
        'session_id',
        'api_key',
        'encryption_key',
        'connection_string',
        'card_number',
        'bank_account',
        'cvv',
        'cvc',
    ];

    /** @param array<string, mixed> $newValues */
    public static function created(string $entityType, string $entityId, array $newValues): void
    {
        self::persist('create', $entityType, $entityId, [], $newValues);
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function updated(
        string $entityType,
        string $entityId,
        array $oldValues,
        array $newValues,
    ): void {
        [$changedOldValues, $changedNewValues] = self::changedValues($oldValues, $newValues);

        if ($changedOldValues === [] && $changedNewValues === []) {
            return;
        }

        self::persist('update', $entityType, $entityId, $changedOldValues, $changedNewValues);
    }

    /** @param array<string, mixed> $oldValues */
    public static function deleted(string $entityType, string $entityId, array $oldValues): void
    {
        self::persist('delete', $entityType, $entityId, $oldValues);
    }

    public static function viewed(string $entityType, string $entityId): void
    {
        self::persist('view', $entityType, $entityId, [], ['viewed_id' => $entityId]);
    }

    public static function log(
        string $action,
        string $entityType,
        string $entityId,
        array $old = [],
        array $new = [],
    ): void {
        self::persist($action, $entityType, $entityId, $old, $new);
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    private static function persist(
        string $action,
        string $entityType,
        string $entityId,
        array $old = [],
        array $new = [],
    ): void {
        $request = app()->bound('request') ? request() : null;
        AuditLog::query()->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => self::sanitize($old) ?: null,
            'new_values' => self::sanitize($new) ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'logged_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    public static function sanitize(array $values): array
    {
        $sanitized = [];

        // Settings and imported records can represent a secret as {key: ..., value: ...}.
        $sensitiveValue = false;
        foreach (['key', 'name', 'setting_key'] as $nameField) {
            if (isset($values[$nameField]) && is_string($values[$nameField]) && self::isSensitiveKey($values[$nameField])) {
                $sensitiveValue = true;
            }
        }

        foreach ($values as $key => $value) {
            if (self::isSensitiveKey((string) $key) || ($sensitiveValue && in_array($key, ['value', 'old_value', 'new_value'], true))) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            $sanitized[$key] = is_array($value) ? self::sanitize($value) : $value;
        }

        return $sanitized;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $normalizedKey = strtolower((string) preg_replace('/[^a-zA-Z0-9]/', '', $key));

        foreach (self::SENSITIVE_KEY_PARTS as $sensitiveKeyPart) {
            if (str_contains($normalizedKey, str_replace('_', '', $sensitiveKeyPart))) {
                return true;
            }
        }

        return $normalizedKey === 'pan';
    }

    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @return array{array<string, mixed>, array<string, mixed>}
     */
    private static function changedValues(array $oldValues, array $newValues): array
    {
        $changedOldValues = [];
        $changedNewValues = [];

        foreach (array_unique([...array_keys($oldValues), ...array_keys($newValues)]) as $key) {
            $oldValue = $oldValues[$key] ?? null;
            $newValue = $newValues[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $changedOldValues[$key] = $oldValue;
            $changedNewValues[$key] = $newValue;
        }

        return [$changedOldValues, $changedNewValues];
    }
}
