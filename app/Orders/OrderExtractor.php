<?php

namespace App\Orders;

/**
 * Reads the lines of an order written by a person (an email body, the text of
 * a PDF). Its output is a proposal: OrderDraftService stores it as a pending
 * draft that a person must approve.
 */
interface OrderExtractor
{
    public function name(): string;

    /** @return list<ExtractedLine> */
    public function extract(string $text): array;
}
