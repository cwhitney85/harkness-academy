import type { RailLesson } from '@/types/lesson';

/**
 * What a quiz looks like once it has crossed the wire.
 *
 * These mirror `App\Assessment\QuizDocument::clientPayload()`, which strips
 * `correct_option_id` and `explanation` from every question (spec 5.2.3). The
 * omission is the point: a type that named either field would let a screen ask
 * the server for it, and 8.2.4's done-condition is that the pre-answer payload
 * contains neither.
 */
export type QuizOption = {
    id: string;
    text: string;
};

export type QuizQuestion = {
    id: string;
    prompt: string;
    options: QuizOption[];
    concept_tag: string | null;
};

/** Homework's reading list (spec 6.2). Empty whenever the model had nothing. */
export type SuggestedReading = {
    title: string;
    why: string;
};

/**
 * The student's answers, held by the screen and handed to a material to draw.
 * Question id → the option id they chose.
 */
export type QuizAnswers = Record<string, string>;

/**
 * How far the student has got, question by question (spec 6.2).
 *
 * The verdict survives a reload; the explanation does not. One disclosure
 * point is auditable, two are a race to keep in step — see `App\Assessment\
 * Homework`, which makes the same trade for the same reason.
 */
export type QuizProgress = {
    question_id: string;
    answered: boolean;
    correct: boolean;
    attempts: number;
};

/** Screen S8 — the homework (spec 8.9.1). */
export type HomeworkScreen = {
    lesson: { position: number; title: string; sections: string[] };
    course: { title: string };
    student: string;
    date: string;
    /** `pending` while the completion chain is still writing it (5.4.5). */
    state: 'pending' | 'ready';
    homework: {
        quiz_id: number;
        scope: string;
        questions: QuizQuestion[];
        suggested_reading: SuggestedReading[];
        progress: QuizProgress[];
    } | null;
    urls: {
        answers: string;
        lesson: string;
        /** Null when this was the last lesson — the exam is the move then. */
        next: string | null;
        exam: string;
    };
};

/** One whole sitting of the exam, marked (spec 6.3). No answer key: D20. */
export type SittingResult = {
    sitting: number;
    correct: number;
    total: number;
    score: number;
    concepts_missed: string[];
    passed: boolean;
};

/** Screens S9 and S10 — the final exam and its gate (spec 8.9.2, 8.9.3). */
export type FinalExamScreen = {
    /** Which of the three the server says to draw; never decided here. */
    state: 'closed' | 'open' | 'result';
    course: { title: string; lessons: RailLesson[] };
    student: string;
    date: string;
    pass_mark: number;
    questions: QuizQuestion[];
    /** A book already sat opens straight to the leaf, no ceremony (8.4.4). */
    resit: boolean;
    sitting: SittingResult | null;
    urls: {
        open: string;
        sittings: string;
        refresher: string;
        dashboard: string;
    };
};

/** Screen S11 — a refresher at any of the three grains (spec 8.9.4). */
export type RefresherScreen = {
    refresher: {
        quiz_id: number;
        scope: string;
        questions: QuizQuestion[];
        suggested_reading: SuggestedReading[];
        progress: QuizProgress[];
    };
    /** Null at the module grain, which hangs off no course (6.5). */
    course: string | null;
    student: string;
    date: string;
    urls: { answers: string; dashboard: string };
};
