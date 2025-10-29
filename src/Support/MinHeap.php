<?php
namespace App\Support;

use SplPriorityQueue;

/**
 * SplPriorityQueue is a max-heap; we invert priorities to emulate a min-heap.
 */
final class MinHeap
{
    private SplPriorityQueue $q;

    public function __construct()
    {
        $this->q = new SplPriorityQueue();
        $this->q->setExtractFlags(SplPriorityQueue::EXTR_DATA);
    }

    public function push(mixed $value, float $priority): void
    {
        // lower priority value should pop first -> invert
        $this->q->insert($value, -$priority);
    }

    public function pop(): mixed { return $this->q->extract(); }
    public function isEmpty(): bool { return $this->q->isEmpty(); }
}
