<?php

namespace App\Enums;

enum WorkflowHistoryAction: string
{
    case Started = 'started';
    case Moved = 'moved';
    case Paused = 'paused';
    case Resumed = 'resumed';
    case Assigned = 'assigned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Restored = 'restored';
}
