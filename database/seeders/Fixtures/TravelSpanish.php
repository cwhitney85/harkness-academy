<?php

namespace Database\Seeders\Fixtures;

use App\Courses\SkillLevel;

/**
 * A course approved and not yet begun (spec 8.2.2).
 *
 * The state a student is in for about thirty seconds and the one the syllabus
 * screen has to be right for: a whole plan on screen, five lessons deep, and
 * nothing behind it yet. It carries no lesson content at all, which is the
 * point — every lesson row exists, all of them at their opening status, and
 * the screen has only the approved JSON to render from (4.5.5.1).
 *
 * A second subtopic rather than a second attempt at the first: a student may
 * hold only one open course per subtopic (D7a/D7b), and these two are open at
 * once.
 */
final class TravelSpanish
{
    public static function fixture(): CourseFixture
    {
        return new CourseFixture(
            moduleSlug: 'language',
            slug: 'travel-spanish',
            title: 'Travel Spanish',
            level: SkillLevel::Introductory,
            workingKnowledge: self::WORKING_KNOWLEDGE,
            criteria: self::CRITERIA,
            syllabus: self::syllabus(),
        );
    }

    private const WORKING_KNOWLEDGE = <<<'MD'
        # Travel Spanish

        ## Solid

        - Airport and station signage. Years of holidays have made *salidas*, *llegadas*, *andén* and *facturación* automatic.
        - Present-tense *ser* and *estar* in writing, carried over from Conversational Spanish and visible in the interview.

        ## Shaky

        - Numbers heard aloud. Can read a platform number; loses a price said at speed. This is the single thing they raised twice.
        - Questions. They can answer one and freeze on asking one, because they have never had to.

        ## Gaps

        - Anything to do with illness or the pharmacy. Never come up, and squarely in scope for this course.
        - Complaining. They said they would rather eat the loss than try, which is exactly the gap lesson four addresses.
        - Regional accents outside central Spain. Out of scope here; worth a note for a later course.
        MD;

    private const CRITERIA = <<<'MD'
        # Course criteria — Travel Spanish

        ## Goals

        - Get from the plane to the hotel room without English.
        - Report a problem — a late train, a wrong room, a lost bag — in one sentence that is understood the first time.
        - Walk into a pharmacy and describe a symptom.

        ## Interests

        - The November fortnight in Salamanca again, but this time the parts either side of it: Madrid airport, the bus, the hotel.
        - Trains. They would rather learn the vocabulary of a ticket office than of an airline.

        ## Success

        - "Not having to hold my phone up at a counter." Their words.

        ## Evaluation style

        - Short corrections, immediately, as in the previous course.
        - They asked for more listening and less reading this time, having noticed the gap themselves.
        MD;

    /**
     * @return array<string, mixed>
     */
    private static function syllabus(): array
    {
        return [
            'course_title' => 'Travel Spanish: From the Plane to the Pharmacy',
            'overview' => "Five lessons following the shape of an actual trip: getting through the airport, taking a room, crossing a city you do not know, saying that something has gone wrong, and asking for help when you are unwell. Every lesson ends where the next one begins, so the whole course is one journey rather than five vocabulary lists.\n\nYou asked for more listening this time. Each lesson leans on hearing a sentence and acting on it rather than reading one and translating it.",
            'lessons' => [
                [
                    'position' => 1,
                    'title' => 'Landing and Getting Through',
                    'objective' => 'You can answer the questions asked at a border without freezing.',
                    'sections' => [
                        ['title' => 'The three questions you will be asked', 'concepts' => ['arrival-questions']],
                        ['title' => 'How long, and what for', 'concepts' => ['duration-phrases', 'purpose-phrases']],
                    ],
                    'homework_focus' => 'The four sentences that get you past a border officer.',
                    'estimated_minutes' => 25,
                ],
                [
                    'position' => 2,
                    'title' => 'Checking In',
                    'objective' => 'You can take a room, ask what it includes, and say when you are leaving.',
                    'sections' => [
                        ['title' => 'Taking the room', 'concepts' => ['hotel-check-in']],
                        ['title' => 'What is included, and until when', 'concepts' => ['included-and-until']],
                    ],
                    'homework_focus' => 'Asking what a price actually covers.',
                    'estimated_minutes' => 28,
                ],
                [
                    'position' => 3,
                    'title' => 'Getting Around a Strange City',
                    'objective' => 'You can buy the right ticket and follow the answer you are given.',
                    'sections' => [
                        ['title' => 'Buying the ticket', 'concepts' => ['buying-tickets']],
                        ['title' => 'Reading back what you are told', 'concepts' => ['prepositions-of-place', 'transport-verbs']],
                    ],
                    'homework_focus' => 'Following a spoken direction all the way to the end.',
                    'estimated_minutes' => 34,
                ],
                [
                    'position' => 4,
                    'title' => 'When Something Goes Wrong',
                    'objective' => 'You can say what has happened and what you would like done about it.',
                    'sections' => [
                        ['title' => 'The lost, the late and the broken', 'concepts' => ['problem-vocabulary']],
                        ['title' => 'Complaining without being rude', 'concepts' => ['polite-complaints']],
                    ],
                    'homework_focus' => 'Reporting a problem in one clear sentence.',
                    'estimated_minutes' => 30,
                ],
                [
                    'position' => 5,
                    'title' => 'The Pharmacy and the Doctor',
                    'objective' => 'You can describe a symptom precisely enough to be helped.',
                    'sections' => [
                        ['title' => 'Saying what hurts', 'concepts' => ['body-and-symptoms']],
                        ['title' => 'Understanding the instruction', 'concepts' => ['dosage-instructions']],
                    ],
                    'homework_focus' => 'Turning a vague ache into a sentence a pharmacist can act on.',
                    'estimated_minutes' => 26,
                ],
            ],
            'final_exam_concepts' => [
                'arrival-questions',
                'hotel-check-in',
                'buying-tickets',
                'polite-complaints',
                'body-and-symptoms',
            ],
            'subtopic_mismatch' => null,
        ];
    }
}
