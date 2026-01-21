<?php

namespace Q\Orm\Peculiar;

class Peculiar
{
    const TIMESTAMP_BITS = 41;
    const CUSTOM_BITS = 10;
    const SEQUENCE_BITS = 12;

    const MAX_TS = -1 ^ (-1 << self::TIMESTAMP_BITS);
    const MAX_CID = -1 ^ (-1 << self::CUSTOM_BITS);
    const MAX_SEQ = -1 ^ (-1 << self::SEQUENCE_BITS);

    private static $customEpoch = 1640991600000;
    private static $customId = 0;

    public function __construct(int $customId = null)
    {
        if ($customId !== null) {
            self::setCustomId($customId);
        }
    }

    private function timestampDiff()
    {
        return floor(microtime(true) * 1000) - self::$customEpoch;
    }

    /**
     * Check if APCu is available for use
     * @return bool
     */
    private static function isApcuAvailable(): bool
    {
        if (!function_exists('apcu_enabled') || !apcu_enabled()) {
            return false;
        }

        if (PHP_SAPI === 'cli') {
            return (bool) ini_get('apc.enable_cli');
        }

        return true;
    }

    public function generate()
    {
        // Use file-based fallback if APCu unavailable
        if (!self::isApcuAvailable()) {
            $ids = self::fileBasedNextIds(1, $this);
            return $ids[0];
        }

        // APCu fast path
        $currentTimestamp = $this->timestampDiff();
        $lastTimestamp = \apcu_fetch('lastTimestamp', $success);

        if (!$success || $lastTimestamp < $currentTimestamp) {
            \apcu_store('lastTimestamp', $currentTimestamp);
            \apcu_store('sequence', 0);
            $sequence = 0;
        } else {
            $sequence = \apcu_inc('sequence');
            if ($sequence > self::MAX_SEQ) {
                while ($currentTimestamp <= $lastTimestamp) {
                    $currentTimestamp = $this->timestampDiff();
                }
                \apcu_store('lastTimestamp', $currentTimestamp);
                \apcu_store('sequence', 0);
                $sequence = 0;
            }
        }

        $finalNumber = ($currentTimestamp << (self::CUSTOM_BITS + self::SEQUENCE_BITS))
            | (self::$customId << self::SEQUENCE_BITS)
            | $sequence;

        return (string) $finalNumber;
    }

    public static function setEpoch(int $timestamp)
    {
        if ($timestamp <= 0 || $timestamp > self::MAX_TS) {
            throw new \Error("Set epoch out of bounds. Must be between 0 and " . self::MAX_TS);
        }
        self::$customEpoch = $timestamp;
    }

    public static function getEpoch()
    {
        return self::$customEpoch;
    }

    public static function setCustomId(int $customId)
    {
        if ($customId < 0 || $customId > self::MAX_CID) {
            throw new \Error("Set custom Id out of bounds. Must be between 0 and " . self::MAX_CID);
        }
        self::$customId = $customId;
    }

    public static function nextId(int $customId = null)
    {
        $generator = new static($customId);
        return $generator->generate();
    }

    /**
     * File-based fallback for generating IDs when APCu is unavailable
     * Uses flock() for atomicity
     * @param int $count Number of IDs to generate
     * @param Peculiar $generator Instance with configured customId
     * @return array List of generated IDs
     */
    private static function fileBasedNextIds(int $count, Peculiar $generator): array
    {
        $lockFile = sys_get_temp_dir() . '/qorm_peculiar_' . md5(__DIR__) . '.lock';
        $configLockPath = \Q\Orm\SetUp::env('Q_PECULIAR_LOCK_PATH');
        if ($configLockPath) {
            $lockFile = $configLockPath;
        }

        $fp = fopen($lockFile, 'c+');
        if (!$fp) {
            throw new \Exception("Failed to open Peculiar lock file: $lockFile");
        }

        flock($fp, LOCK_EX);
        $ids = [];
        $remaining = $count;

        try {
            // Read current state
            $data = fread($fp, 8192);
            $state = ['timestamp' => 0, 'sequence' => 0];

            if ($data) {
                try {
                    $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $state = $decoded;
                    }
                } catch (\JsonException $e) {
                    // Corruption: Fallback to default state
                }
            }

            while ($remaining > 0) {
                $currentTimestamp = $generator->timestampDiff();
                $lastTimestamp = $state['timestamp'] ?? 0;

                // New millisecond or first run
                if ($lastTimestamp < $currentTimestamp) {
                    $state['timestamp'] = $currentTimestamp;
                    $state['sequence'] = 0;
                }

                $endSequence = $state['sequence'] + $remaining;
                $startSequence = $state['sequence'];

                // Check overflow
                if ($endSequence > self::MAX_SEQ) {
                    $validCount = max(0, self::MAX_SEQ - $startSequence + 1);

                    for ($i = 0; $i < $validCount; $i++) {
                        $seq = $startSequence + $i;
                        $ids[] = (string) (($currentTimestamp << (self::CUSTOM_BITS + self::SEQUENCE_BITS))
                            | (self::$customId << self::SEQUENCE_BITS)
                            | $seq);
                    }

                    $remaining -= $validCount;
                    $state['sequence'] = self::MAX_SEQ + 1; // Force rollover

                    // Wait for next millisecond
                    while ($generator->timestampDiff() <= $currentTimestamp) {
                        usleep(100);
                    }
                } else {
                    for ($i = 0; $i < $remaining; $i++) {
                        $seq = $startSequence + $i;
                        $ids[] = (string) (($currentTimestamp << (self::CUSTOM_BITS + self::SEQUENCE_BITS))
                            | (self::$customId << self::SEQUENCE_BITS)
                            | $seq);
                    }
                    $state['sequence'] = $endSequence;
                    $remaining = 0;
                }
            }

            // Write state back
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($state));
        } finally {
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        return $ids;
    }

    /**
     * Generate multiple IDs atomically for batch operations.
     * 
     * @param int $count Number of IDs to generate
     * @param int|null $customId Optional custom server ID
     * @return array List of generated IDs as strings
     */
    public static function nextIds(int $count, int $customId = null): array
    {
        if ($count <= 0) {
            return [];
        }

        $generator = new static($customId);

        // Use file-based fallback if APCu unavailable
        if (!self::isApcuAvailable()) {
            return self::fileBasedNextIds($count, $generator);
        }

        // APCu fast path
        $ids = [];
        $remaining = $count;

        while ($remaining > 0) {
            $currentTimestamp = $generator->timestampDiff();
            $lastTimestamp = \apcu_fetch('lastTimestamp', $success);

            // Handle missing timestamp key (init or race)
            if (!$success) {
                if (\apcu_add('lastTimestamp', $currentTimestamp)) {
                    // We initialized it. Reset sequence.
                    \apcu_store('sequence', 0);
                    $lastTimestamp = $currentTimestamp; // We are current
                } else {
                    // Someone else added it.
                    $lastTimestamp = \apcu_fetch('lastTimestamp');
                    if ($lastTimestamp === false) {
                        // Should not happen if add failed, but defensive
                        $lastTimestamp = $currentTimestamp;
                        \apcu_store('lastTimestamp', $currentTimestamp);
                    }
                }
            }

            // Check for new millisecond
            if ($lastTimestamp < $currentTimestamp) {
                if (\apcu_cas('lastTimestamp', $lastTimestamp, $currentTimestamp)) {
                    // We moved clock forward
                    \apcu_store('sequence', 0);
                } else {
                    // Lost race. Join the new bucket.
                    $fetched = \apcu_fetch('lastTimestamp');
                    if ($fetched !== false) {
                        $currentTimestamp = $fetched;
                    }
                }
            } elseif ($lastTimestamp >= $currentTimestamp) {
                // Ensure we use the stored timestamp if it's ahead or equal (clock consistency)
                $currentTimestamp = $lastTimestamp;
            }

            $endSequence = \apcu_inc('sequence', $remaining, $success);
            if ($success === false) {
                // Sequence key missing?
                \apcu_add('sequence', 0);
                $endSequence = \apcu_inc('sequence', $remaining);
                if ($endSequence === false) {
                    // Emergency fallback
                    $endSequence = $remaining;
                    \apcu_store('sequence', $remaining);
                }
            }
            $startSequence = $endSequence - $remaining;

            // Check if we overflowed MAX_SEQ
            if ($endSequence > self::MAX_SEQ) {
                $validCount = max(0, self::MAX_SEQ - $startSequence + 1);

                // We can use the valid ones
                for ($i = 0; $i < $validCount; $i++) {
                    $seq = $startSequence + $i;
                    $ids[] = (string) (($currentTimestamp << (self::CUSTOM_BITS + self::SEQUENCE_BITS))
                        | (self::$customId << self::SEQUENCE_BITS)
                        | $seq);
                }

                $remaining -= $validCount;

                // Wait for next millisecond
                while ($generator->timestampDiff() <= $currentTimestamp) {
                    // spin wait
                }
            } else {
                // All requested IDs fit in this millisecond
                for ($i = 0; $i < $remaining; $i++) {
                    $seq = $startSequence + $i;
                    $ids[] = (string) (($currentTimestamp << (self::CUSTOM_BITS + self::SEQUENCE_BITS))
                        | (self::$customId << self::SEQUENCE_BITS)
                        | $seq);
                }
                $remaining = 0;
            }
        }

        return $ids;
    }
}
