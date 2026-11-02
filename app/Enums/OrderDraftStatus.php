<?php

namespace App\Enums;

enum OrderDraftStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
