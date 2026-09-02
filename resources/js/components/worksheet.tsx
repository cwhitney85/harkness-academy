import { useState } from 'react';
import type { ReactNode } from 'react';

import QuizSheet from '@/components/quiz-sheet';
import { postJson } from '@/lib/api';
import type {
    GradedAnswer,
    QuizAnswers,
    QuizProgress,
    QuizQuestion,
    SuggestedReading,
} from '@/types';

/**
 * A photocopied sheet the student is working through — spec 8.9.1, 8.9.4.
 *
 * `QuizSheet` is the material; this is what happens on it. Homework and all
 * three refresher grains are the same transaction — choose an option, the
 * answer is recorded, the marker's note appears under the question — so it is
 * written once, and the two screens differ only in what they print at the head
 * of the sheet and what they offer underneath it.
 *
 * **Choosing an option is the hand-in.** The artboard draws a "Hand it in"
 * button, but `POST …/answers` takes one question at a time and discloses that
 * question's key in its reply (6.2, 6.4) — the rail's own words on the same
 * artboard are "Answers are kept as you go", and that is the half the engine
 * can actually do. A button that batched them would either record nothing
 * until the last question or be a no-op; neither is what the sheet says.
 *
 * Pre:  `questions` carries no answer key — `QuizQuestion` has no field for
 *       one, which is 8.9.1's pre-answer DOM condition held by the type.
 * Post: a question the student answers here shows its verdict and its
 *       explanation. A question answered before this page was loaded shows the
 *       verdict only: the explanation is disclosed at one point, and reloading
 *       is not a second one (`App\Assessment\Homework` makes the same trade).
 */
export default function Worksheet({
    course,
    scope,
    student,
    date,
    directions,
    questions,
    reading,
    progress,
    answersUrl,
    children,
}: {
    course: string;
    scope: string;
    student: string;
    date: string;
    directions?: string;
    questions: QuizQuestion[];
    reading: SuggestedReading[];
    progress: QuizProgress[];
    answersUrl: string;
    /** The onward moves this particular sheet offers, under the count. */
    children?: ReactNode;
}) {
    const [answers, setAnswers] = useState<QuizAnswers>({});
    const [marks, setMarks] = useState<Record<string, GradedAnswer>>({});
    const [failure, setFailure] = useState<string | null>(null);

    const answered = new Set([
        ...progress.filter((row) => row.answered).map((row) => row.question_id),
        ...Object.keys(marks),
    ]);
    const finished = answered.size === questions.length;

    function answer(questionId: string, optionId: string) {
        setAnswers((chosen) => ({ ...chosen, [questionId]: optionId }));
        setFailure(null);

        postJson<GradedAnswer>(answersUrl, {
            question_id: questionId,
            option_id: optionId,
        })
            .then((graded) =>
                setMarks((marked) => ({ ...marked, [questionId]: graded })),
            )
            .catch((error: Error) => setFailure(error.message));
    }

    return (
        <QuizSheet
            course={course}
            scope={scope}
            student={student}
            date={date}
            directions={directions}
            questions={questions}
            answers={answers}
            onAnswer={answer}
            notes={notes(questions, marks, progress)}
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center gap-x-5 gap-y-3">
                    <span className="label">
                        {answered.size} of {questions.length} answered
                    </span>
                    <span className="grow" />
                    {children}
                </div>

                {failure && (
                    <p className="label text-wine" role="alert">
                        {failure}
                    </p>
                )}

                {/* The reading is the sheet's back page: it names what to go
                    and read, so it would be an answer key of sorts if it were
                    on the desk while the questions were still open (6.2). */}
                {reading.length > 0 &&
                    (finished ? (
                        <div className="border-t border-hairline pt-4">
                            <p className="label mb-2.5 text-wine">
                                Suggested reading
                            </p>
                            <ul className="flex flex-col gap-2">
                                {reading.map((item) => (
                                    <li key={item.title}>
                                        <span className="font-medium">
                                            {item.title}
                                        </span>
                                        <span className="text-muted-ink">
                                            {' '}
                                            — {item.why}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : (
                        <p className="label">
                            Suggested reading appears once you have answered
                            them all
                        </p>
                    ))}
            </div>
        </QuizSheet>
    );
}

/**
 * What the marker wrote under each question.
 *
 * Three states, and the difference between the last two is the whole of
 * 5.2.3's one-disclosure-point rule: unanswered says nothing, answered in
 * front of us says why, and answered before this page loaded says only how it
 * went.
 */
function notes(
    questions: QuizQuestion[],
    marks: Record<string, GradedAnswer>,
    progress: QuizProgress[],
): Record<string, ReactNode> {
    const notes: Record<string, ReactNode> = {};

    for (const question of questions) {
        const mark = marks[question.id];

        if (mark) {
            const right = question.options.find(
                (option) => option.id === mark.correct_option_id,
            );

            notes[question.id] = (
                <>
                    <span className="text-pen">
                        {mark.correct
                            ? 'Right.'
                            : `Not quite — ${right?.text ?? ''} is the answer.`}
                    </span>{' '}
                    {mark.explanation}
                </>
            );

            continue;
        }

        const row = progress.find((entry) => entry.question_id === question.id);

        if (row?.answered) {
            notes[question.id] = (
                <span className="text-pen">
                    Answered — {row.correct ? 'right' : 'wrong'} first time.
                </span>
            );
        }
    }

    return notes;
}
