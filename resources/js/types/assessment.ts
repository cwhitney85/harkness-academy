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
