<?php

namespace App\Enums;

/**
 * draft → checked (availability resolved) → reserved (stock held, picking list ready) → shipped.
 */
enum InstallationStatus: string
{
    case Draft = 'draft';
    case Checked = 'checked';
    case Reserved = 'reserved';
    case Shipped = 'shipped';
    case Cancelled = 'cancelled';

    /**
     * Installations a catalogue change can still hurt.
     *
     * @return list<self>
     */
    public static function active(): array
    {
        return [self::Draft, self::Checked, self::Reserved];
    }
}
