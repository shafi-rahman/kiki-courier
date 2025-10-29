<?php
namespace App\Support;

use SplPriorityQueue;

/**
 * SplPriorityQueue is a max-heap; we invert priorities to emulate a min-heap.
 */
final class MinHeap
{
    private SplPriorityQueue $q;
    private int $seq = 0;

    public function __construct()
    {
        $this->q = new SplPriorityQueue();
        $this->q->setExtractFlags(SplPriorityQueue::EXTR_DATA);
    }

    public function push(mixed $value, float $priority): void
    {
        // lower priority value should pop first -> invert.
        // Use a sequence counter as a stable tie-breaker so items pushed earlier
        // are popped earlier when priorities (floats) are equal.
        // We insert an array priority: [ -priority, -seq ] so SplPriorityQueue
        // compares by first element then by second element deterministically.
        $this->q->insert($value, [-$priority, -$this->seq++]);
    }

    public function pop(): mixed { return $this->q->extract(); }
    public function isEmpty(): bool { return $this->q->isEmpty(); }
}
