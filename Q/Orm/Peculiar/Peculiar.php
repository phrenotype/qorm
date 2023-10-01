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

    public function generate()
    {
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

        return (string)$finalNumber;
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
}
