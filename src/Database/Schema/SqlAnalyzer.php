<?php

declare(strict_types=1);

namespace Conduit\Database\Schema;

/**
 * SQL Risk Analyzer
 *
 * SQL statement'ların riskini değerlendirir:
 * - CREATE: LOW (yeni tablo, veri kaybı yok)
 * - ALTER ADD: LOW (kolon ekleme, veri kaybı yok)
 * - ALTER DROP: HIGH (kolon silme, veri kaybı var)
 * - DROP: CRITICAL (tablo silme, tüm veri kaybolur)
 *
 * @package Conduit\Database\Schema
 */
class SqlAnalyzer
{
    public const RISK_LOW = 'LOW';
    public const RISK_MEDIUM = 'MEDIUM';
    public const RISK_HIGH = 'HIGH';
    public const RISK_CRITICAL = 'CRITICAL';

    /**
     * SQL statement'ın risk seviyesini değerlendir
     *
     * @param string $sql SQL statement
     * @return string Risk seviyesi
     */
    public static function assessRisk(string $sql): string
    {
        $sql = strtoupper(trim($sql));

        // DROP TABLE (CRITICAL)
        if (str_starts_with($sql, 'DROP TABLE')) {
            return self::RISK_CRITICAL;
        }

        // CREATE TABLE (LOW)
        if (str_starts_with($sql, 'CREATE TABLE')) {
            return self::RISK_LOW;
        }

        // ALTER TABLE
        if (str_starts_with($sql, 'ALTER TABLE')) {
            // DROP COLUMN (HIGH)
            if (str_contains($sql, 'DROP COLUMN')) {
                return self::RISK_HIGH;
            }

            // DROP INDEX (MEDIUM)
            if (str_contains($sql, 'DROP INDEX')) {
                return self::RISK_MEDIUM;
            }

            // MODIFY COLUMN (MEDIUM - veri kaybı riski)
            if (str_contains($sql, 'MODIFY COLUMN') || str_contains($sql, 'ALTER COLUMN')) {
                return self::RISK_MEDIUM;
            }

            // ADD COLUMN (LOW)
            if (str_contains($sql, 'ADD COLUMN') || str_contains($sql, 'ADD CONSTRAINT')) {
                return self::RISK_LOW;
            }

            // CREATE INDEX (LOW)
            if (str_contains($sql, 'ADD INDEX') || str_contains($sql, 'CREATE INDEX')) {
                return self::RISK_LOW;
            }
        }

        // CREATE INDEX (LOW)
        if (str_starts_with($sql, 'CREATE INDEX')) {
            return self::RISK_LOW;
        }

        // DROP INDEX (MEDIUM)
        if (str_starts_with($sql, 'DROP INDEX')) {
            return self::RISK_MEDIUM;
        }

        // TRUNCATE (HIGH)
        if (str_starts_with($sql, 'TRUNCATE')) {
            return self::RISK_HIGH;
        }

        // Default: MEDIUM
        return self::RISK_MEDIUM;
    }

    /**
     * SQL statement'tan etkilenen tabloyu çıkar
     *
     * @param string $sql SQL statement
     * @return string|null Tablo adı
     */
    public static function extractTableName(string $sql): ?string
    {
        $sql = trim($sql);

        // CREATE TABLE users (...)
        if (preg_match('/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?([a-zA-Z0-9_]+)/i', $sql, $matches)) {
            return $matches[1];
        }

        // DROP TABLE users
        if (preg_match('/DROP TABLE\s+(?:IF EXISTS\s+)?([a-zA-Z0-9_]+)/i', $sql, $matches)) {
            return $matches[1];
        }

        // ALTER TABLE users ...
        if (preg_match('/ALTER TABLE\s+([a-zA-Z0-9_]+)/i', $sql, $matches)) {
            return $matches[1];
        }

        // TRUNCATE users
        if (preg_match('/TRUNCATE\s+(?:TABLE\s+)?([a-zA-Z0-9_]+)/i', $sql, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Tahmini execution süresi (basit heuristic)
     *
     * @param string $sql SQL statement
     * @return float Tahmini süre (saniye)
     */
    public static function estimateDuration(string $sql): float
    {
        $sql = strtoupper(trim($sql));

        // CREATE TABLE: 0.5-2s
        if (str_starts_with($sql, 'CREATE TABLE')) {
            return 1.0;
        }

        // DROP TABLE: 0.1-0.5s
        if (str_starts_with($sql, 'DROP TABLE')) {
            return 0.3;
        }

        // ALTER TABLE: 1-5s (tablo boyutuna bağlı)
        if (str_starts_with($sql, 'ALTER TABLE')) {
            if (str_contains($sql, 'ADD COLUMN')) {
                return 2.0; // Kolon eklemek yavaş olabilir
            }
            if (str_contains($sql, 'DROP COLUMN')) {
                return 1.5;
            }
            return 1.0;
        }

        // CREATE INDEX: 5-30s (tablo boyutuna bağlı)
        if (str_starts_with($sql, 'CREATE INDEX') || str_contains($sql, 'ADD INDEX')) {
            return 10.0;
        }

        // Default
        return 0.5;
    }

    /**
     * Birden fazla SQL için toplu analiz
     *
     * @param array $statements SQL statements
     * @return array Analiz sonuçları
     */
    public static function analyzeBatch(array $statements): array
    {
        $totalRisk = self::RISK_LOW;
        $totalDuration = 0.0;
        $affectedTables = [];
        $details = [];

        foreach ($statements as $sql) {
            $risk = self::assessRisk($sql);
            $duration = self::estimateDuration($sql);
            $table = self::extractTableName($sql);

            $details[] = [
                'sql' => $sql,
                'risk' => $risk,
                'duration' => $duration,
                'table' => $table,
            ];

            // En yüksek risk seviyesini tut
            if (self::compareRisk($risk, $totalRisk) > 0) {
                $totalRisk = $risk;
            }

            $totalDuration += $duration;

            if ($table && !in_array($table, $affectedTables, true)) {
                $affectedTables[] = $table;
            }
        }

        return [
            'total_risk' => $totalRisk,
            'total_duration' => round($totalDuration, 2),
            'affected_tables' => $affectedTables,
            'statement_count' => count($statements),
            'details' => $details,
        ];
    }

    /**
     * İki risk seviyesini karşılaştır
     *
     * @return int -1 (r1 < r2), 0 (r1 = r2), 1 (r1 > r2)
     */
    private static function compareRisk(string $r1, string $r2): int
    {
        $levels = [
            self::RISK_LOW => 1,
            self::RISK_MEDIUM => 2,
            self::RISK_HIGH => 3,
            self::RISK_CRITICAL => 4,
        ];

        $l1 = $levels[$r1] ?? 2;
        $l2 = $levels[$r2] ?? 2;

        return $l1 <=> $l2;
    }

    /**
     * Risk seviyesine göre renk kodu (CLI output için)
     */
    public static function getRiskColor(string $risk): string
    {
        return match($risk) {
            self::RISK_LOW => "\033[32m", // Green
            self::RISK_MEDIUM => "\033[33m", // Yellow
            self::RISK_HIGH => "\033[91m", // Light Red
            self::RISK_CRITICAL => "\033[31m", // Red
            default => "\033[0m", // Reset
        };
    }

    /**
     * Risk seviyesine göre emoji
     */
    public static function getRiskEmoji(string $risk): string
    {
        return match($risk) {
            self::RISK_LOW => '✅',
            self::RISK_MEDIUM => '⚠️',
            self::RISK_HIGH => '🔴',
            self::RISK_CRITICAL => '💀',
            default => '❓',
        };
    }
}
