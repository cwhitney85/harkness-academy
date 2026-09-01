<?php

namespace Database\Seeders\Fixtures;

use App\Courses\SkillLevel;
use App\Lessons\Speaker;

/**
 * The course the design canvas was drawn against (spec 8.2.3, 8.2.4, 8.2.5).
 *
 * Three of the six fixture states live here, because they are three states of
 * one course: a lesson finished and its homework still untouched, the next
 * lesson stopped halfway through, and the checkpoint that arrived in the
 * middle of it and has not been answered.
 *
 * The syllabus, the section titles, the estar prose and the soup checkpoint
 * are the artboards' own -- `ALesson.dc.html`, `CheckpointOptions.dc.html` and
 * `FoolscapSyllabus.dc.html` in `docs/design/canvas/`. That is deliberate: the
 * layouts were judged against this text, so the screens should be built
 * against it too.
 *
 * `subtopic_mismatch` is set for the same reason -- the syllabus artboard
 * carries the note, so 4.5.7's banner has something to render (8.7.5).
 *
 * **The subtopic is named for its language, and the artboards are not.** They
 * read "Spanish / Conversational Basics / Intro", which no course can be: the
 * module is a catalog row and Spanish is a *subtopic* of Language (spec 4.1).
 * PLAN.md's acceptance test was corrected to "Language / Conversational
 * Spanish / Intro" (Colin, 2026-08-31) and the fixture follows it. The canvas
 * copy still says the old thing and wants correcting at its next re-seed.
 */
final class ConversationalSpanish
{
    public static function fixture(): CourseFixture
    {
        return new CourseFixture(
            moduleSlug: 'language',
            slug: 'conversational-spanish',
            title: 'Conversational Spanish',
            level: SkillLevel::Introductory,
            workingKnowledge: self::WORKING_KNOWLEDGE,
            criteria: self::CRITERIA,
            syllabus: self::syllabus(),
            lessons: [self::lessonOne(), self::lessonTwo()],
        );
    }

    private const WORKING_KNOWLEDGE = <<<'MD'
        # Conversational Spanish

        ## Solid

        - Reads restaurant menus and street signs without help, and has done for years of holidays. Recognition is well ahead of production.
        - Numbers to a hundred, on paper. Said aloud and at speed they fall apart, which is a different skill and is treated as one below.
        - Knows that Spanish verbs change ending by person, and can conjugate *ser* in the present unprompted.

        ## Shaky

        - *Ser* and *estar*. Gets the textbook rule right when asked to state it — permanent versus temporary — and then applies it wrongly to «la sopa está buena» and to anything about location. Their own words: "I have bounced off this twice."
        - *Tú* and *usted*: knows both exist, defaults to *tú* with everybody, has not registered that the choice is read from the situation rather than from age.
        - Gendered adjective endings. Correct on nouns they have memorised, guessed elsewhere.

        ## Gaps

        - Past and future tenses. Out of scope for this course by design; the student has not asked for them.
        - Anything spoken at native speed. They have never had a conversation in Spanish with a person, only with an app.
        - Question intonation — they read questions comfortably and have never had to ask one aloud.
        MD;

    private const CRITERIA = <<<'MD'
        # Course criteria — Conversational Spanish

        ## Goals

        - Hold up their end of a short exchange with a stranger: greet, say how they are, ask for something, understand the answer.
        - Stop stalling on *ser* and *estar* mid-sentence. They named this twice, unprompted.
        - Order and pay in a café without switching to English when the waiter says something unexpected.

        ## Interests

        - Salamanca specifically — they have a fortnight booked there in November and every example should be a thing that could happen in that fortnight.
        - Cafés, markets and small shops rather than business settings.
        - Food vocabulary is a way in: they cook, and they care what the words on a menu actually mean.

        ## Success

        - "Getting through a whole coffee order without the waiter switching to English on me." Their sentence, and the finish line they will judge this course by.
        - The trip is in November, so the course has a real deadline and should not sprawl.

        ## Evaluation style

        - Correct errors immediately and say why, in one line. They asked not to be let off.
        - They respond well to being shown a minimal pair — two sentences that differ by one word — rather than to a rule stated abstractly.
        - Do not soften a wrong answer into a half-right one. They noticed an app doing that and did not trust it afterwards.
        MD;

    /**
     * The syllabus of `FoolscapSyllabus.dc.html`, as `json/syllabus.v1`.
     *
     * @return array<string, mixed>
     */
    private static function syllabus(): array
    {
        return [
            'course_title' => 'Conversational Spanish: Getting By in a Fortnight',
            'overview' => "Five lessons that take you from a standing start to holding up your end of a short exchange — greeting someone, saying how and where you are, asking for what you need, handling numbers and prices, and getting directions when you are lost. Everything stays in the present tense; past and future are the next course's job.\n\nWe will spend more time than usual on ser and estar, because you said in the interview that you have bounced off them twice before.",
            'lessons' => [
                [
                    'position' => 1,
                    'title' => "Greeting Someone You've Just Met",
                    'objective' => 'You can open and close a first conversation without stalling.',
                    'sections' => [
                        ['title' => 'Tú, usted, and reading the room', 'concepts' => ['formal-vs-informal-address']],
                        ['title' => 'The formulas that do the work', 'concepts' => ['greeting-formulas']],
                        ['title' => 'Saying who you are', 'concepts' => ['ser-for-identity']],
                    ],
                    'homework_focus' => 'Choosing between tú and usted, and the greeting that fits the hour.',
                    'estimated_minutes' => 32,
                ],
                [
                    'position' => 2,
                    'title' => 'Saying Where You Are and How You Are',
                    'objective' => 'You can choose between ser and estar without stopping to think.',
                    'sections' => [
                        ['title' => 'Ser, briefly revisited', 'concepts' => ['ser-for-identity', 'ser-vs-estar']],
                        ['title' => 'Estar: how things are right now', 'concepts' => ['estar-for-state', 'ser-vs-estar']],
                        ['title' => "Location, and the exception that isn't", 'concepts' => ['estar-for-location']],
                        ['title' => 'Putting them side by side', 'concepts' => ['ser-vs-estar']],
                    ],
                    'homework_focus' => 'Ser and estar only — no other verb should appear.',
                    'estimated_minutes' => 45,
                ],
                [
                    'position' => 3,
                    'title' => 'Asking for What You Need',
                    'objective' => 'You can make a request that lands as polite rather than blunt.',
                    'sections' => [
                        ['title' => 'Querer, necesitar, and quisiera', 'concepts' => ['querer-vs-necesitar', 'polite-requests']],
                        ['title' => 'The little words in front of the noun', 'concepts' => ['definite-articles']],
                    ],
                    'homework_focus' => 'Turning a blunt request into one a waiter would enjoy hearing.',
                    'estimated_minutes' => 38,
                ],
                [
                    'position' => 4,
                    'title' => 'Numbers, Prices and Time',
                    'objective' => 'You can hear a price said aloud and repeat it back correctly.',
                    'sections' => [
                        ['title' => 'Numbers at speaking speed', 'concepts' => ['cardinal-numbers']],
                        ['title' => 'Telling the time, and being told it', 'concepts' => ['telling-time']],
                        ['title' => 'Euros, céntimos and what a price sounds like', 'concepts' => ['currency-phrases']],
                    ],
                    'homework_focus' => 'Prices said aloud, written back as digits.',
                    'estimated_minutes' => 36,
                ],
                [
                    'position' => 5,
                    'title' => 'Getting Directions',
                    'objective' => 'You can ask the way, and follow the answer you get back.',
                    'sections' => [
                        ['title' => 'Asking, and asking again', 'concepts' => ['clarifying-questions']],
                        ['title' => 'Left, right, and past the pharmacy', 'concepts' => ['imperative-directions', 'prepositions-of-place']],
                    ],
                    'homework_focus' => 'Following a spoken direction all the way to the end.',
                    'estimated_minutes' => 41,
                ],
            ],
            'final_exam_concepts' => [
                'ser-vs-estar',
                'formal-vs-informal-address',
                'polite-requests',
                'cardinal-numbers',
                'prepositions-of-place',
            ],
            'subtopic_mismatch' => 'Your goals mention reading Borges in the original. That sits outside Conversational Spanish — it is closer to Literary Reading. This syllabus covers the conversational ground you asked for; the reading goal is not addressed here.',
        ];
    }

    /**
     * Finished, summarized, and its homework never opened (spec 8.2.5).
     */
    private static function lessonOne(): LessonFixture
    {
        return new LessonFixture(
            position: 1,
            taught: [
                new SectionFixture(
                    "Spanish makes you choose, in the first word out of your mouth, how close you are standing to the person you are talking to. «¿Cómo estás?» and «¿Cómo está?» are the same question. The first is what you ask a friend, a fellow student, or anyone roughly your own age in a bar. The second is what you ask a shopkeeper you have never met, a colleague's parent, or anyone you would call sir or madam in English without feeling strange about it.\n\nThe good news is that the cost of guessing is small, and lopsided. Use tú where usted was expected and you sound informal — sometimes charmingly so. Use usted where tú was expected and you sound careful, or briefly funny, and the other person will almost always say «puedes tutearme»: you can use tú with me. Nobody is offended by the careful direction. So when you are unsure, start with usted and let them promote you.",
                ),
                new SectionFixture(
                    "Almost every first conversation in Spanish runs on about eight fixed phrases, and once you have them the rest is filling in nouns. «Buenos días» until roughly lunch. «Buenas tardes» from lunch until dark. «Buenas noches» after that, and as a goodbye too. «Mucho gusto» on being introduced. «Encantado» if you are a man saying it, «encantada» if you are a woman — the ending agrees with you, not with them, which is the part English speakers reliably get backwards.\n\nNotice that «buenos días» is plural, and so is «buenas tardes». There is no shortening it to «buen día» in most of Spain, though you will hear exactly that in parts of Argentina. Learn the plural form and you will be right everywhere you are likely to be.",
                ),
                new SectionFixture(
                    "Ser is the verb for what something is, and for a first meeting that is very nearly everything you need. «Soy Colin.» «Soy de Inglaterra.» «Soy profesor.» Three sentences, one verb, and you have introduced yourself.\n\nWatch the article. English says «I am a teacher»; Spanish drops the article and says «soy profesor». Putting «un» in front of it is not wrong so much as loud — «soy un profesor» sounds like you are insisting on the category, the way «I am a teacher, I'll have you know» does in English. Leave it out.",
                ),
            ],
            completed: true,
            summary: [
                'summary' => [
                    'prose' => 'We spent most of the hour on the tú/usted choice, which the student kept trying to settle with a rule — age, or job title — rather than by reading the situation. The turn came when they worked out for themselves that a waiter their own age still gets usted in a smart restaurant and tú in a student bar. Greetings went quickly; they already had them passively and only needed the encantado/encantada agreement corrected, which they took on the first try. Ser for identity was the weakest part of the hour: they produced «soy un profesor» twice after being told once.',
                    'concepts' => [
                        ['slug' => 'formal-vs-informal-address', 'mastery' => 'solid', 'evidence' => 'Chose correctly on all four of the situations put to them, including the two designed to defeat an age-based rule.'],
                        ['slug' => 'greeting-formulas', 'mastery' => 'solid', 'evidence' => 'Produced buenas tardes and encantado unprompted and at the right hour; corrected their own gender agreement once.'],
                        ['slug' => 'ser-for-identity', 'mastery' => 'shaky', 'evidence' => 'The verb was right every time, the dropped article was not — «soy un profesor» twice after correction.'],
                    ],
                    'struggles' => [
                        'Wants a rule for tú/usted and is uncomfortable that the answer is "read the room".',
                        'Keeps the English indefinite article in front of a profession.',
                    ],
                    'questions_asked' => [
                        'Is it rude to use usted with someone my own age?',
                        'Do people actually say buenos días after midday, or is that just textbooks?',
                    ],
                ],
            ],
            homework: self::lessonOneHomework(),
        );
    }

    /**
     * Stopped in the middle of section 2, with the checkpoint unanswered.
     */
    private static function lessonTwo(): LessonFixture
    {
        return new LessonFixture(
            position: 2,
            taught: [
                new SectionFixture(
                    "You already met ser last lesson, doing the work of identity: «soy Colin», «soy profesor», «soy de Inglaterra». Keep that list in your head as a shape rather than a rule — ser answers the question *what is this thing*.\n\nIt also handles the facts that sit underneath a thing and do not move with the weather: what something is made of («la mesa es de madera»), where somebody is from as opposed to where they are standing («es de Salamanca»), and what time it is («son las tres»). That last one surprises everybody, and it is worth simply accepting: the time of day is treated as an identity, not a condition.",
                ),
                new SectionFixture(
                    "You walk into a café in Salamanca at nine in the morning. The waiter looks up and says «¿Cómo está?» — and then, an hour later, as you are leaving: «El café está frío.» Same verb, twice, and neither time is he telling you what something *is*.\n\nEstar is the verb Spanish uses for conditions and locations — the things that could be otherwise. The coffee is cold now; it was hot twenty minutes ago. You are tired today; yesterday you were not. Ser, which you met in the last lesson, does the opposite work: it names what a thing is, not how it happens to be at the moment you are looking at it.\n\nThe pairing that trips up almost every English speaker is estar bueno versus ser bueno. «La sopa es buena» says the soup is a good soup — well made, a good recipe, the kind of thing a critic writes. «La sopa está buena» says the soup tastes good right now, in this bowl, in front of you. Both are compliments; only one of them is about the bowl on the table.\n\nThe textbook rule you have probably been given is permanent versus temporary, and it will get you about three quarters of the way. Where it fails is exactly where you have been failing: «está muerto» is as permanent as anything gets, and it is estar. «Es joven» is as temporary as anything gets, and it is ser. The better question to ask yourself is not how long it will last but which of two things you are saying — what this is, or how it is doing.",
                    exchange: [
                        [Speaker::Student, 'So is «está muerto» just an exception I have to memorise, or is there a reason for it?'],
                        [Speaker::Tutor, 'There is a reason, and it is the one that makes the rest of estar click. Estar marks a *change of state* — the result of something having happened. Being dead is the result of having died, so Spanish files it under how things now stand rather than under what something is. The same logic runs through «está casado» (married), «está cansado» (tired) and «está roto» (broken): each of them is where something ended up. Permanence never came into it; the textbook rule was a rough guide to a different idea.'],
                    ],
                ),
            ],
            checkpoints: [
                new CheckpointFixture(
                    sectionIndex: 1,
                    questions: [
                        [
                            'id' => 'l2s1-q1',
                            'prompt' => 'Which of these is ser doing its ordinary work?',
                            'options' => [
                                ['id' => 'a', 'text' => '«La mesa es de madera.»'],
                                ['id' => 'b', 'text' => '«La mesa está sucia.»'],
                                ['id' => 'c', 'text' => '«La mesa está en la cocina.»'],
                            ],
                            'correct_option_id' => 'a',
                            'explanation' => 'What the table is made of is what it is, so ser. Dirty is a condition and in the kitchen is a location — both estar.',
                            'concept_tag' => 'ser-for-identity',
                        ],
                        [
                            'id' => 'l2s1-q2',
                            'prompt' => 'It is three o\'clock. How does Spanish say so?',
                            'options' => [
                                ['id' => 'a', 'text' => '«Están las tres.»'],
                                ['id' => 'b', 'text' => '«Son las tres.»'],
                                ['id' => 'c', 'text' => '«Es las tres.»'],
                            ],
                            'correct_option_id' => 'b',
                            'explanation' => 'The time takes ser, and it agrees with the plural «las tres» — so son, not es. One o\'clock is the exception that proves it: «es la una».',
                            'concept_tag' => 'ser-vs-estar',
                        ],
                    ],
                    answers: ['l2s1-q1' => 'a', 'l2s1-q2' => 'b'],
                ),
                // Arrived, unanswered -- the state 8.2.4 exists for.
                new CheckpointFixture(
                    sectionIndex: 2,
                    questions: [
                        [
                            'id' => 'l2s2-q1',
                            'prompt' => 'A friend hands you a bowl of soup, you taste it, and it is delicious. Which do you say?',
                            'options' => [
                                ['id' => 'a', 'text' => '«La sopa es buena.»'],
                                ['id' => 'b', 'text' => '«La sopa está buena.»'],
                                ['id' => 'c', 'text' => '«La sopa es bien.»'],
                                ['id' => 'd', 'text' => '«La sopa está bien.»'],
                            ],
                            'correct_option_id' => 'b',
                            'explanation' => 'You are saying how it tastes right now, in this bowl — estar. «Es buena» would be a verdict on the recipe, and «bien» is an adverb, so neither of the last two describes the soup at all.',
                            'concept_tag' => 'estar-for-state',
                        ],
                    ],
                ),
            ],
        );
    }

    /**
     * Generated by the completion chain and never opened (spec 8.2.5).
     *
     * @return array<string, mixed>
     */
    private static function lessonOneHomework(): array
    {
        return [
            'quiz' => [
                'scope' => 'Greeting someone you have just met — tú and usted, and the greeting that fits the hour',
                'questions' => [
                    [
                        'id' => 'l1-hw-q1',
                        'prompt' => 'You walk into a small shop in Salamanca at four in the afternoon. The owner is about seventy. What do you say?',
                        'options' => [
                            ['id' => 'a', 'text' => '«Buenos días. ¿Cómo estás?»'],
                            ['id' => 'b', 'text' => '«Buenas tardes. ¿Cómo está?»'],
                            ['id' => 'c', 'text' => '«Buenas noches. ¿Cómo está?»'],
                            ['id' => 'd', 'text' => '«Buenas tardes. ¿Cómo estás?»'],
                        ],
                        'correct_option_id' => 'b',
                        'explanation' => 'Four in the afternoon is tardes, and a shopkeeper you have never met is usted — so «está», not «estás».',
                        'concept_tag' => 'formal-vs-informal-address',
                    ],
                    [
                        'id' => 'l1-hw-q2',
                        'prompt' => 'A woman is introduced to you at a party. Which does she say?',
                        'options' => [
                            ['id' => 'a', 'text' => '«Encantado.»'],
                            ['id' => 'b', 'text' => '«Encantada.»'],
                            ['id' => 'c', 'text' => 'Either — it agrees with the person she is meeting.'],
                        ],
                        'correct_option_id' => 'b',
                        'explanation' => 'The ending agrees with the speaker, not with the person being met. She is delighted, so «encantada».',
                        'concept_tag' => 'greeting-formulas',
                    ],
                    [
                        'id' => 'l1-hw-q3',
                        'prompt' => 'How do you say "I am a teacher"?',
                        'options' => [
                            ['id' => 'a', 'text' => '«Soy un profesor.»'],
                            ['id' => 'b', 'text' => '«Soy profesor.»'],
                            ['id' => 'c', 'text' => '«Estoy profesor.»'],
                        ],
                        'correct_option_id' => 'b',
                        'explanation' => 'Spanish drops the article in front of a profession. «Soy un profesor» sounds like you are insisting on the category.',
                        'concept_tag' => 'ser-for-identity',
                    ],
                    [
                        'id' => 'l1-hw-q4',
                        'prompt' => 'Your new colleague, who is your age, says «puedes tutearme». What have they just done?',
                        'options' => [
                            ['id' => 'a', 'text' => 'Asked you to keep using usted.'],
                            ['id' => 'b', 'text' => 'Invited you to switch to tú.'],
                            ['id' => 'c', 'text' => 'Told you their name.'],
                            ['id' => 'd', 'text' => 'Asked you to speak more slowly.'],
                        ],
                        'correct_option_id' => 'b',
                        'explanation' => 'Tutear is the verb for using tú with someone. This is the standard way the more senior person drops the formality.',
                        'concept_tag' => 'formal-vs-informal-address',
                    ],
                ],
                'suggested_reading' => [
                    [
                        'title' => 'RAE — «Tuteo y ustedeo» (Diccionario panhispánico de dudas)',
                        'why' => 'You wanted a rule and the honest answer is that usage varies by country; this is the short authority saying so, with the Spanish/Latin American split laid out.',
                    ],
                ],
            ],
        ];
    }
}
