<?php

namespace App\Courses\Interviews;

/**
 * Which of a course's two interviews a transcript belongs to (spec 4.3, 4.4).
 *
 * Deliberately empty of behaviour: what each interview asks, summarizes and
 * writes lives in KnowledgeInterview and GoalsInterview. This is only the name
 * the transcript rows are filed under.
 */
enum InterviewKind: string
{
    case Knowledge = 'knowledge';
    case Goals = 'goals';
}
