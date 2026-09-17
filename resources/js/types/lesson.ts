import type { QuizQuestion } from '@/types/assessment';

/**
 * Screen S6, as the server hands it over (spec 8.8.1).
 *
 * `App\Lessons\LessonState` verbatim, plus what `LessonController` decorates
 * it with: a URL against each section and each checkpoint, the course's other
 * lessons for the rail, and the moves a finished lesson offers.
 *
 * There is deliberately no `correct_option_id` or `explanation` on a
 * checkpoint question — those arrive only in the answer to `POST …/answers`
 * (spec 5.2.3), and a type that named them here would invite a screen to look
 * for them before the student has earned them.
 */

export type LessonTurn = {
    message_id: number;
    turn_id: string;
    kind: 'section' | 'qa';
    speaker: 'tutor' | 'student';
    section_index: number | null;
    content: string;
};

export type LessonSectionRow = {
    index: number;
    title: string;
    concepts: string[];
    taught: boolean;
    /** Its checkpoint is out on the queue and could still arrive (spec 11.6). */
    checkpoint_writing: boolean;
    stream: string;
    /** Asks again for a checkpoint that did not arrive (spec 11.3). */
    checkpoint_request: string;
};

export type LessonCheckpointRow = {
    checkpoint_id: number;
    section_index: number;
    payload: {
        checkpoint: { section_index: number; questions: QuizQuestion[] };
    };
};

/** What answering discloses, and only then (spec 5.2.4). */
export type GradedAnswer = {
    correct: boolean;
    correct_option_id: string;
    explanation: string;
};

export type RailLesson = {
    position: number;
    title: string;
    completed: boolean;
    /** Null while the lesson is locked — the rail draws openable from this. */
    url: string | null;
};

export type LessonScreen = {
    lesson: {
        id: number;
        position: number;
        title: string;
        objective: string;
        status: 'locked' | 'available' | 'in_progress' | 'completed';
        section_index: number | null;
        sections_total: number;
        completed_at: string | null;
    };
    resume_at: number;
    sections: LessonSectionRow[];
    transcript: LessonTurn[];
    checkpoints: LessonCheckpointRow[];
    /** Read from the classes that enforce them, never restated on the screen. */
    limits: { question_characters: number; highlight_characters: number };
    course: { title: string; lessons: RailLesson[] };
    urls: {
        question: string;
        explanation: string;
        complete: string;
        refresher: string;
        homework: string;
        /** The final exam, or for a demo the offer to keep the course (12.4.1). */
        exam: string;
    };
    demo: boolean;
};
