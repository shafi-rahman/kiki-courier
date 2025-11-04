<?php

declare(strict_types=1);

namespace App\Support;

use SplPriorityQueue;

/**
 * Stable min-heap built on SplPriorityQueue.
 *
 * SplPriorityQueue is a max-heap; we invert priorities to emulate a min-heap.
 *
 * Tie-breaking order (lexicographic on the array priority):
 *   1) primary key  (lower first)          → e.g. availableAt
 *   2) secondary id (lower first)          → e.g. vehicle id (ensures deterministic selection)
 *   3) insertion order (earlier first)     → sequence counter
 *
 * This guarantees deterministic ordering when multiple items share the same primary time.
 */
final class MinHeap
{
    private SplPriorityQueue $q;

    /** monotonically increasing sequence for stable FIFO under ties */
    private int $seq = 0;

    public function __construct()
    {
        $this->q = new SplPriorityQueue();
        // We want to get back the VALUE only when extracting.
        $this->q->setExtractFlags(SplPriorityQueue::EXTR_DATA);
    }

    /**
     * Generic push with explicit primary & secondary keys.
     *
     * @param mixed $value         The value to push (e.g., Vehicle)
     * @param float $primaryKey    Lower is better (min-heap). Example: availableAt timestamp/hours
     * @param int   $secondaryId   Lower is better (deterministic tie-break). Example: vehicle id
     */
    public function pushWithKeys(mixed $value, float $primaryKey, int $secondaryId): void
    {
        // SplPriorityQueue is a max-heap; negate to get min-heap behavior.
        // Priority is compared lexicographically as an array.
        // Order here defines tie-breaks: primary → secondary → sequence.
        $this->q->insert($value, [
            -$primaryKey,     // 1) smaller primary first
            -$secondaryId,    // 2) smaller id first
            -$this->seq++,    // 3) earlier insertion first
        ]);
    }

    /**
     * Backwards-compatible generic push (single float priority + optional tieBreakerId).
     * If you were already calling push($veh, $veh->availableAt, $veh->id), this is identical.
     *
     * @param mixed    $value
     * @param float    $priority       Lower is better (min-heap)
     * @param int|null $tieBreakerId   Lower is better; when null, falls back to sequence only
     */
    public function push(mixed $value, float $priority, ?int $tieBreakerId = null): void
    {
        if ($tieBreakerId !== null) {
            $this->pushWithKeys($value, $priority, $tieBreakerId);
            return;
        }

        // No secondary id provided; use sequence as the only tie-break.
        $this->q->insert($value, [
            -$priority,
            -$this->seq++,
        ]);
    }

    /**
     * Pop the minimum-priority item.
     *
     * @return mixed
     */
    public function pop(): mixed
    {
        return $this->q->extract();
    }

    public function isEmpty(): bool
    {
        return $this->q->isEmpty();
    }

    public function count(): int
    {
        return $this->q->count();
    }
}
