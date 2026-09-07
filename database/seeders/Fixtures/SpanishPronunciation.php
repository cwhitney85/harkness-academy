<?php

namespace Database\Seeders\Fixtures;

use App\Courses\SkillLevel;

/**
 * A subtopic the student has finished (spec 8.2.6, 8.2.7).
 *
 * Two things hang off this course. It is the only fixture course that has been
 * *passed*, so it is where the module rollup gets its "completed: 101" line and
 * where 8.9.4's subtopic and module refreshers find something to draw on. And
 * its exam was failed once and re-sat, which is the other half of 8.2.6: a
 * course that only ever passed would leave 8.9.3's fail gate with nothing to
 * render, and a re-sit is the same exam by design (D20).
 *
 * Three lessons rather than five, and two sections each. Nothing requires a
 * completed course to be long, and the prose here exists to make the summaries
 * and the working-knowledge document true rather than to be read on a screen.
 */
final class SpanishPronunciation
{
    public static function fixture(): CourseFixture
    {
        return new CourseFixture(
            moduleSlug: 'language',
            slug: 'spanish-pronunciation',
            title: 'Spanish Pronunciation',
            level: SkillLevel::Introductory,
            workingKnowledge: self::WORKING_KNOWLEDGE,
            criteria: self::CRITERIA,
            syllabus: self::syllabus(),
            lessons: self::lessons(),
            exam: self::exam(),
            sittings: [self::failedSitting(), self::passedSitting()],
        );
    }

    private const WORKING_KNOWLEDGE = <<<'MD'
        # Spanish Pronunciation

        ## Solid

        - The five pure vowels, produced consistently and without the English glide. Held up under exam conditions on the second sitting.
        - The default stress rules: vowel, *n* or *s* takes the penultimate syllable, anything else takes the last.
        - Reading a written accent as information rather than decoration.

        ## Shaky

        - The rolled *rr*. Produced reliably at the start of a word, unreliably between vowels, and it is the thing they will need to keep practising after this course.
        - Telling *b* and *v* apart in listening. They now know the two letters are one sound, and still hear an English distinction that is not there.

        ## Gaps

        - The Castilian *c*/*z* — covered only in passing, since the student's trip is to Salamanca where it is universal. Worth a lesson of its own later.
        - Intonation across a whole sentence. Out of scope for a first pronunciation course.
        MD;

    private const CRITERIA = <<<'MD'
        # Course criteria — Spanish Pronunciation

        ## Goals

        - Be understood the first time, rather than the second.
        - Stop the English vowel glide, which they had been told about and could not hear in themselves.

        ## Interests

        - Salamanca again. The examples should sound like central Spain, not Latin America.
        - They are musical and respond to being told what to do with their mouth rather than to phonetic notation.

        ## Success

        - "Saying my own name and my order without the waiter leaning in."

        ## Evaluation style

        - Blunt correction, immediately. They asked to be interrupted mid-word.
        - Minimal pairs again — the same thing that worked for ser and estar works here.
        MD;

    /**
     * @return array<string, mixed>
     */
    private static function syllabus(): array
    {
        return [
            'course_title' => 'Spanish Pronunciation: Being Understood the First Time',
            'overview' => "Three short lessons on the three things that actually stop an English speaker being understood in Spanish: the vowels, two consonants, and where the stress falls. Nothing about accents you might have; everything about sounds that change what word you have said.\n\nYou asked to be interrupted mid-word. That is how this will go.",
            'lessons' => [
                [
                    'position' => 1,
                    'title' => 'The Five Vowels',
                    'objective' => 'You can produce all five Spanish vowels without the English glide.',
                    'sections' => [
                        ['title' => 'Five sounds, and only five', 'concepts' => ['pure-vowels']],
                        ['title' => 'Where English leaks in', 'concepts' => ['vowel-reduction']],
                    ],
                    'homework_focus' => 'Hearing your own diphthong where a Spanish vowel should be.',
                    'estimated_minutes' => 20,
                ],
                [
                    'position' => 2,
                    'title' => 'The Consonants That Trip You',
                    'objective' => 'You can produce a rolled rr and stop hearing a b/v distinction that is not there.',
                    'sections' => [
                        ['title' => 'R and rr', 'concepts' => ['rolled-r']],
                        ['title' => 'B, V, and the sound between them', 'concepts' => ['b-v-merger']],
                    ],
                    'homework_focus' => 'Minimal pairs where the roll is the whole difference.',
                    'estimated_minutes' => 26,
                ],
                [
                    'position' => 3,
                    'title' => 'Stress and the Written Accent',
                    'objective' => 'You can put the stress on the right syllable of a word you have never seen.',
                    'sections' => [
                        ['title' => 'Where the stress falls by default', 'concepts' => ['default-stress']],
                        ['title' => 'What the accent mark is telling you', 'concepts' => ['written-accent']],
                    ],
                    'homework_focus' => 'Stressing unfamiliar words correctly on first sight.',
                    'estimated_minutes' => 24,
                ],
            ],
            'final_exam_concepts' => ['pure-vowels', 'rolled-r', 'b-v-merger', 'default-stress', 'written-accent'],
            'subtopic_mismatch' => null,
        ];
    }

    /**
     * @return list<LessonFixture>
     */
    private static function lessons(): array
    {
        return [
            new LessonFixture(
                position: 1,
                taught: [
                    new SectionFixture("Spanish has five vowel sounds and English has about twenty, which means every Spanish vowel you produce is a choice not to do something your mouth wants to do. A is the *a* of *father*, short. E is close to the *e* of *bet* but tighter. I is *ee*. O is a rounded *o* with nothing after it. U is *oo*.\n\nThe word to hold on to is *pure*. Each of these is one sound held steady from beginning to end. English almost never does that — say «no» aloud in English and you will hear your mouth travel from an *o* to a *w* on the way out. Spanish «no» does not travel. It starts where it ends."),
                    new SectionFixture("The leak is easiest to hear in unstressed syllables, because English weakens them to a schwa and Spanish does not. In English, *banana* has three different vowel sounds despite three identical letters. In Spanish, *banana* has three identical *a* sounds, all fully pronounced, all the same length.\n\nThis is why «Sa-la-man-ca» said by an English speaker often comes out as «Suh-luh-MAN-cuh» and is not understood. Every one of those a's is the same open *a*. Say the word slowly with all four equal and you will hear how different it is from what you have been saying."),
                ],
                completed: true,
                summary: Documents::summary(
                    'The student could hear the difference immediately and could not produce it for the first ten minutes, which is the usual order. The breakthrough was banana — four identical a sounds in Spanish against three different ones in English — after which they self-corrected on Salamanca without being asked.',
                    [
                        ['pure-vowels', 'solid', 'Produced all five without a glide on the third pass, and heard their own glide before I pointed at it.'],
                        ['vowel-reduction', 'solid', 'Corrected their own Salamanca unprompted, which is the whole point of the section.'],
                    ],
                    ['The o glide is the most stubborn; it survives into fast speech.'],
                    ['Do Spanish speakers hear my glide as an accent or as a different word?'],
                ),
                homework: Documents::quiz('The five vowels', [
                    ['id' => 'sp1-q1', 'prompt' => 'How many vowel sounds does Spanish have?', 'options' => [['id' => 'a', 'text' => 'Five'], ['id' => 'b', 'text' => 'Twelve'], ['id' => 'c', 'text' => 'Twenty']], 'correct_option_id' => 'a', 'explanation' => 'Five, one per written vowel, each held steady from start to finish.', 'concept_tag' => 'pure-vowels'],
                    ['id' => 'sp1-q2', 'prompt' => 'Spanish «no» differs from English «no» because…', 'options' => [['id' => 'a', 'text' => 'it is longer'], ['id' => 'b', 'text' => 'the vowel does not travel towards a w sound'], ['id' => 'c', 'text' => 'the n is silent']], 'correct_option_id' => 'b', 'explanation' => 'English glides out of the o; Spanish holds it. That glide is the single most audible English accent marker.', 'concept_tag' => 'pure-vowels'],
                    ['id' => 'sp1-q3', 'prompt' => 'In «Salamanca», how many of the four a sounds are identical?', 'options' => [['id' => 'a', 'text' => 'Two'], ['id' => 'b', 'text' => 'Three'], ['id' => 'c', 'text' => 'All four']], 'correct_option_id' => 'c', 'explanation' => 'Spanish does not weaken unstressed vowels, so all four are the same open a.', 'concept_tag' => 'vowel-reduction'],
                ]),
                homeworkAnswers: ['sp1-q1' => 'a', 'sp1-q2' => 'b', 'sp1-q3' => 'c'],
            ),
            new LessonFixture(
                position: 2,
                taught: [
                    new SectionFixture("There are two r sounds and the difference between them changes words. The single r between vowels is a tap — the tongue touches the ridge behind your teeth once, very lightly, and it is close to the *dd* in the American pronunciation of *ladder*. The double rr is a trill: the same contact repeated, driven by air rather than by the tongue.\n\nThe pair worth drilling is *pero* (but) against *perro* (dog). One tap, one trill, and the meanings are unrelated. If you cannot trill yet, a slightly over-long tap is understood; what is not understood is an English r, which is made with the tongue nowhere near the right place."),
                    new SectionFixture("B and v are the same sound in Spanish. Not similar — the same. *Vaca* and *baca* are pronounced identically and Spanish schoolchildren are taught to spell them apart by rote, exactly as English children learn *their* and *there*.\n\nWhat varies is not the letter but the position. At the start of an utterance or after m or n, it is a full English-style b. Between vowels it softens into something with the lips almost but not quite touching, which is why «la vaca» sounds nothing like the b you were expecting. Stop listening for a distinction; there is not one to hear."),
                ],
                completed: true,
                summary: Documents::summary(
                    'The rr was the hard part and remains unfinished — the student produces it word-initially and loses it between vowels, so perro comes out as pero about half the time. The b/v section went the other way: they were audibly relieved, and the fact that there is no distinction to hear removed a worry they had been carrying since school.',
                    [
                        ['rolled-r', 'shaky', 'Word-initial rr is reliable; intervocalic rr collapsed to a tap in roughly half of the pero/perro drills.'],
                        ['b-v-merger', 'solid', 'Stated the rule correctly and stopped trying to hear the difference within the hour.'],
                    ],
                    ['Intervocalic rr — the tongue stops vibrating as soon as a vowel precedes it.'],
                    ['If b and v sound the same, how do Spanish speakers spell?'],
                ),
                homework: Documents::quiz('R, rr, b and v', [
                    ['id' => 'sp2-q1', 'prompt' => 'What is the difference between «pero» and «perro»?', 'options' => [['id' => 'a', 'text' => 'One is stressed differently'], ['id' => 'b', 'text' => 'A single tapped r against a trilled rr — and the meanings are unrelated'], ['id' => 'c', 'text' => 'Nothing; they are spelling variants']], 'correct_option_id' => 'b', 'explanation' => 'But against dog. The roll is the whole difference.', 'concept_tag' => 'rolled-r'],
                    ['id' => 'sp2-q2', 'prompt' => 'How do «vaca» and «baca» sound?', 'options' => [['id' => 'a', 'text' => 'Identical'], ['id' => 'b', 'text' => 'The v is softer'], ['id' => 'c', 'text' => 'The v is like an English v']], 'correct_option_id' => 'a', 'explanation' => 'B and v are one sound in Spanish. What changes it is position in the word, not which letter is written.', 'concept_tag' => 'b-v-merger'],
                    ['id' => 'sp2-q3', 'prompt' => 'You cannot trill yet. What is the better fallback?', 'options' => [['id' => 'a', 'text' => 'An English r'], ['id' => 'b', 'text' => 'A slightly over-long tap'], ['id' => 'c', 'text' => 'Skipping the sound']], 'correct_option_id' => 'b', 'explanation' => 'A long tap is understood. An English r is made in the wrong part of the mouth entirely and is not.', 'concept_tag' => 'rolled-r'],
                ]),
                homeworkAnswers: ['sp2-q1' => 'b', 'sp2-q2' => 'a', 'sp2-q3' => 'b'],
            ),
            new LessonFixture(
                position: 3,
                taught: [
                    new SectionFixture("Spanish tells you where the stress falls, every time, with two rules and one mark. Rule one: a word ending in a vowel, n or s is stressed on the second-to-last syllable — *casa*, *hablan*, *lunes*. Rule two: a word ending in any other consonant is stressed on the last — *hotel*, *ciudad*, *comer*.\n\nThat covers the great majority of the language, which means you can stress a word you have never seen before and be right. Try it with *ferrocarril*: ends in l, so the stress is on the final syllable."),
                    new SectionFixture("The written accent is what happens when a word breaks those two rules. It is not decoration and it is not about vowel quality — it does one job, which is to say *the stress is here instead*.\n\n*Teléfono* ends in a vowel, so rule one would put the stress on the *fo*; the mark moves it back to *lé*. *Está* ends in a vowel too, and the mark drags the stress forward to the end — which is why «esta» and «está» are two different words, one meaning *this* and the other *is*. When you see the mark, believe it, and when a word has none, apply the two rules."),
                ],
                completed: true,
                summary: Documents::summary(
                    'The shortest and most immediately useful lesson of the three. Once the two rules were stated the student was correct on unfamiliar words straight away, including ferrocarril and murciélago. The accent mark section reframed something they had been treating as ornamental for years.',
                    [
                        ['default-stress', 'solid', 'Stressed nine of ten unseen words correctly, and the tenth was a word with an accent mark they had not yet been shown.'],
                        ['written-accent', 'solid', 'Explained esta against está unprompted and applied it to two further pairs.'],
                    ],
                    [],
                    ['Why do some one-syllable words have accents if there is nothing to move?'],
                ),
                homework: Documents::quiz('Stress and the written accent', [
                    ['id' => 'sp3-q1', 'prompt' => 'Where is the stress in «hotel»?', 'options' => [['id' => 'a', 'text' => 'On ho'], ['id' => 'b', 'text' => 'On tel'], ['id' => 'c', 'text' => 'Even across both']], 'correct_option_id' => 'b', 'explanation' => 'It ends in l — not a vowel, n or s — so the stress goes on the last syllable.', 'concept_tag' => 'default-stress'],
                    ['id' => 'sp3-q2', 'prompt' => 'What is the written accent in «teléfono» doing?', 'options' => [['id' => 'a', 'text' => 'Lengthening the e'], ['id' => 'b', 'text' => 'Moving the stress away from where the rules would put it'], ['id' => 'c', 'text' => 'Marking a different vowel sound']], 'correct_option_id' => 'b', 'explanation' => 'The mark has one job: the stress is here instead. It says nothing about vowel quality.', 'concept_tag' => 'written-accent'],
                    ['id' => 'sp3-q3', 'prompt' => 'Where is the stress in «lunes»?', 'options' => [['id' => 'a', 'text' => 'On lu'], ['id' => 'b', 'text' => 'On nes'], ['id' => 'c', 'text' => 'It varies by region']], 'correct_option_id' => 'a', 'explanation' => 'It ends in s, so rule one applies and the stress is on the second-to-last syllable.', 'concept_tag' => 'default-stress'],
                ]),
                homeworkAnswers: ['sp3-q1' => 'b', 'sp3-q2' => 'b', 'sp3-q3' => 'a'],
            ),
        ];
    }

    /**
     * Six questions -- two per lesson (6.3.2), so 0.7 asks for five.
     *
     * @return array<string, mixed>
     */
    private static function exam(): array
    {
        return [
            'quiz' => [
                'scope' => 'Everything from Spanish Pronunciation — the vowels, the two consonants, and where the stress falls',
                'questions' => [
                    ['id' => 'sp-x1', 'prompt' => 'How many vowel sounds does Spanish have, and how many does English have?', 'options' => [['id' => 'a', 'text' => 'Five and about twenty'], ['id' => 'b', 'text' => 'Five and five'], ['id' => 'c', 'text' => 'Twelve and about twenty']], 'correct_option_id' => 'a', 'explanation' => 'Five against roughly twenty, which is why every Spanish vowel is a choice not to do what your mouth wants.', 'concept_tag' => 'pure-vowels'],
                    ['id' => 'sp-x2', 'prompt' => 'What is wrong with an English speaker saying «Suh-luh-MAN-cuh»?', 'options' => [['id' => 'a', 'text' => 'The stress is on the wrong syllable'], ['id' => 'b', 'text' => 'The unstressed a sounds have been weakened, and Spanish does not weaken them'], ['id' => 'c', 'text' => 'Nothing — that is how it is said']], 'correct_option_id' => 'b', 'explanation' => 'All four a sounds are the same open a. Reducing them to schwa is the single most audible English habit.', 'concept_tag' => 'pure-vowels'],
                    ['id' => 'sp-x3', 'prompt' => '«Pero» and «perro» differ in that…', 'options' => [['id' => 'a', 'text' => 'one has a tapped r and one a trilled rr, and they mean but and dog'], ['id' => 'b', 'text' => 'one is a regional variant of the other'], ['id' => 'c', 'text' => 'the stress moves']], 'correct_option_id' => 'a', 'explanation' => 'One tap against a trill, and two unrelated meanings.', 'concept_tag' => 'rolled-r'],
                    ['id' => 'sp-x4', 'prompt' => 'The single r between two vowels is closest to…', 'options' => [['id' => 'a', 'text' => 'an English r'], ['id' => 'b', 'text' => 'the dd in the American pronunciation of ladder'], ['id' => 'c', 'text' => 'a rolled trill']], 'correct_option_id' => 'b', 'explanation' => 'A single light tap of the tongue against the ridge behind the teeth.', 'concept_tag' => 'rolled-r'],
                    ['id' => 'sp-x5', 'prompt' => 'How do b and v differ in Spanish?', 'options' => [['id' => 'a', 'text' => 'They do not — they are the same sound'], ['id' => 'b', 'text' => 'V is softer everywhere'], ['id' => 'c', 'text' => 'V is like an English v between vowels']], 'correct_option_id' => 'a', 'explanation' => 'One sound, two spellings. What changes the sound is position in the word, not which letter is written.', 'concept_tag' => 'b-v-merger'],
                    ['id' => 'sp-x6', 'prompt' => 'Where does the stress fall in «ciudad»?', 'options' => [['id' => 'a', 'text' => 'On ciu'], ['id' => 'b', 'text' => 'On dad'], ['id' => 'c', 'text' => 'It needs an accent mark to say']], 'correct_option_id' => 'b', 'explanation' => 'It ends in d, so rule two applies: the last syllable.', 'concept_tag' => 'default-stress'],
                ],
                'suggested_reading' => [],
            ],
        ];
    }

    /**
     * Two of six: below the 0.7 mark, so the course goes back to `active`.
     *
     * @return array<string, string>
     */
    private static function failedSitting(): array
    {
        return [
            'sp-x1' => 'a',
            'sp-x2' => 'a',
            'sp-x3' => 'a',
            'sp-x4' => 'a',
            'sp-x5' => 'c',
            'sp-x6' => 'a',
        ];
    }

    /**
     * Five of six on the re-sit -- the same exam, never disclosed (D20).
     *
     * @return array<string, string>
     */
    private static function passedSitting(): array
    {
        return [
            'sp-x1' => 'a',
            'sp-x2' => 'b',
            'sp-x3' => 'a',
            'sp-x4' => 'b',
            'sp-x5' => 'a',
            'sp-x6' => 'a',
        ];
    }
}
