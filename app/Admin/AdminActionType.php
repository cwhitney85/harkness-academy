<?php

namespace App\Admin;

/**
 * Every kind of row `admin_actions` holds (spec 13.2). Each package that adds
 * an admin write adds its case here, and Admin → Log offers it as a filter.
 */
enum AdminActionType: string
{
    case ViewAsStarted = 'view_as.started';
    case ViewAsSteppedUp = 'view_as.stepped_up';
    case ViewAsExited = 'view_as.exited';
    case AiModelRepriced = 'ai_model.repriced';
    case PromptEdited = 'prompt.edited';
    case PromptRolledBack = 'prompt.rolled_back';
    case SubtopicPromoted = 'subtopic.promoted';
    case SubtopicRetitled = 'subtopic.retitled';
    case Removed = 'removal.created';
    case Restored = 'removal.restored';
    case RestoreRefused = 'removal.restore_refused';
    case PlanChanged = 'user.plan_changed';
    case AccountRestored = 'user.restored';
}
