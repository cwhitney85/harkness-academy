<?php

namespace Database\Seeders\Fixtures;

use App\Courses\SkillLevel;

/**
 * A course walked all the way to its final exam, and stopped there (8.2.6).
 *
 * Five lessons finished, every homework handed in, and an exam generated and
 * never opened — the closed blue book of `ExamClosed.dc.html`. It is the state
 * 8.9.2's cover screen has to be right for, and the only fixture state that
 * has nothing left in front of it but the gate.
 *
 * Each lesson is two sections rather than the hero course's three or four.
 * That is a fixture economy, not a claim about lesson length: what this course
 * exists to prove is that a course can be *finished*, and a third section per
 * lesson would add pages of prose that no screen in package 8 renders.
 */
final class OrderingAndEatingOut
{
    public static function fixture(): CourseFixture
    {
        return new CourseFixture(
            moduleSlug: 'language',
            slug: 'ordering-and-eating-out',
            title: 'Ordering and Eating Out',
            level: SkillLevel::Introductory,
            workingKnowledge: self::WORKING_KNOWLEDGE,
            criteria: self::CRITERIA,
            syllabus: self::syllabus(),
            lessons: self::lessons(),
            exam: self::exam(),
        );
    }

    private const WORKING_KNOWLEDGE = <<<'MD'
        # Spanish — Ordering and Eating Out

        ## Solid

        - The whole shape of a Spanish meal: *primero*, *segundo*, *postre*, and what a *menú del día* commits you to. Finished the course and demonstrated it under exam conditions.
        - *Quisiera* as the default opening of any request, in place of *quiero*. Used it unprompted from lesson two onwards.
        - Naming an allergy and asking whether a dish contains something. This was a gap at the start of the course and is not one now.

        ## Shaky

        - Numbers in the bill. Reading a total is fine; a waiter saying «treinta y siete con cincuenta» aloud still needs a repeat.
        - *Por* and *para* outside the fixed phrases the course drilled.

        ## Gaps

        - Regional dishes outside Castile. Deliberately out of scope — the course stayed with what a Salamanca menu would offer.
        - Wine vocabulary beyond *tinto*, *blanco* and *de la casa*.
        MD;

    private const CRITERIA = <<<'MD'
        # Course criteria — Ordering and Eating Out

        ## Goals

        - Order a whole meal, including the follow-up questions, without the waiter switching to English.
        - Say what they cannot eat and be believed.
        - Ask for the bill and understand what is on it.

        ## Interests

        - They cook, and they want to know what the words on a menu actually mean rather than a translation that flattens them.
        - Small neighbourhood restaurants, not tourist ones.

        ## Success

        - "A whole coffee order without being switched to English." The same finish line as the previous course, moved on to a full meal.

        ## Evaluation style

        - Immediate correction, one line of reason. Established in the first course and reaffirmed here.
        - Minimal pairs work better than rules for this student.
        MD;

    /**
     * @return array<string, mixed>
     */
    private static function syllabus(): array
    {
        return [
            'course_title' => 'Ordering and Eating Out: A Whole Meal in Spanish',
            'overview' => "Five lessons in the order a meal actually happens: reading the menu, ordering from it, answering what the waiter asks back, saying what you cannot eat, and paying. Nothing here is vocabulary for its own sake — every list is one you will need within about ten minutes of sitting down.\n\nWe stay with what a small Castilian restaurant would put in front of you, because that is the trip you have booked.",
            'lessons' => [
                [
                    'position' => 1,
                    'title' => 'Reading a Menu Without Panicking',
                    'objective' => 'You can tell what kind of thing each part of a menu is offering before you know any of the nouns.',
                    'sections' => [
                        ['title' => 'How a Spanish menu is laid out', 'concepts' => ['menu-structure']],
                        ['title' => 'The cooking words that change everything', 'concepts' => ['cooking-methods']],
                    ],
                    'homework_focus' => 'Placing an unfamiliar dish in the right course from its heading alone.',
                    'estimated_minutes' => 24,
                ],
                [
                    'position' => 2,
                    'title' => 'Ordering',
                    'objective' => 'You can order for yourself and for the table without sounding blunt.',
                    'sections' => [
                        ['title' => 'Quisiera, and why it beats quiero', 'concepts' => ['polite-ordering']],
                        ['title' => 'Quantities, sharing and the second round', 'concepts' => ['quantities']],
                    ],
                    'homework_focus' => 'Turning a blunt order into one a waiter would enjoy hearing.',
                    'estimated_minutes' => 28,
                ],
                [
                    'position' => 3,
                    'title' => 'Questions the Waiter Will Ask You',
                    'objective' => 'You can answer the four questions that come back at you, and decline one of them politely.',
                    'sections' => [
                        ['title' => 'Para beber, para picar', 'concepts' => ['waiter-questions']],
                        ['title' => 'Saying no without offence', 'concepts' => ['declining-politely']],
                    ],
                    'homework_focus' => 'Hearing a question you did not plan for and answering it anyway.',
                    'estimated_minutes' => 26,
                ],
                [
                    'position' => 4,
                    'title' => 'Dietary Needs and Allergies',
                    'objective' => 'You can state a restriction clearly enough that the kitchen acts on it.',
                    'sections' => [
                        ['title' => 'Naming what you cannot eat', 'concepts' => ['dietary-restrictions']],
                        ['title' => 'Checking an ingredient', 'concepts' => ['ingredient-questions']],
                    ],
                    'homework_focus' => 'The difference between a preference and an allergy, said out loud.',
                    'estimated_minutes' => 30,
                ],
                [
                    'position' => 5,
                    'title' => 'The Bill',
                    'objective' => 'You can ask for the bill, read it, and settle it the way the room expects.',
                    'sections' => [
                        ['title' => 'Asking for it, and what it includes', 'concepts' => ['asking-for-the-bill']],
                        ['title' => 'Tipping, and the arithmetic of it', 'concepts' => ['tipping-customs']],
                    ],
                    'homework_focus' => 'Reading a total off a bill and saying it back.',
                    'estimated_minutes' => 22,
                ],
            ],
            'final_exam_concepts' => [
                'menu-structure',
                'polite-ordering',
                'waiter-questions',
                'dietary-restrictions',
                'asking-for-the-bill',
            ],
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
                    new SectionFixture("A Spanish menu is a shape before it is a list of words, and the shape is the same nearly everywhere: *para picar* or *entrantes* to start, *primeros*, *segundos*, then *postres*. A *primero* is not a starter in the English sense — it is a full plate, usually vegetables, rice, pulses or soup, and ordering one plus a *segundo* is an ordinary lunch rather than a feast.\n\nThe *menú del día* sits alongside all of this and is the thing worth knowing. One price buys a *primero*, a *segundo*, bread, a drink and either a dessert or a coffee. It is offered at lunch on weekdays, it is often the best food in the building, and it is not a tourist menu — the people around you eating it work nearby."),
                    new SectionFixture("Half of what a menu tells you is in the cooking word rather than the noun, and there are perhaps eight of them worth knowing. *A la plancha* is on the flat griddle: quick, dry, usually the lightest thing on the page. *Frito* is fried. *Al horno* is baked. *Guisado* and *estofado* are both stewed slowly with liquid. *Asado* is roasted. *A la brasa* is over coals.\n\nOnce you have those, an unknown noun stops mattering as much as you fear. *Merluza a la plancha* is some fish, griddled; you can order it without knowing that merluza is hake, and you will be right about what arrives."),
                ],
                completed: true,
                summary: Documents::summary(
                    'The student came in already able to read individual words and unable to read the page. Most of the hour went on the primero/segundo shape, which they had been reading as starter and main and were therefore ordering badly. The cooking words landed immediately — they cook, and recognised every technique under a new name.',
                    [
                        ['menu-structure', 'solid', 'Correctly placed six unfamiliar dishes from headings alone, including two designed to look like starters.'],
                        ['cooking-methods', 'solid', 'Named all eight techniques unprompted and explained the difference between guisado and asado without help.'],
                    ],
                    ['Kept translating primero as "starter" for the first twenty minutes.'],
                    ['Is the menú del día always cheaper, or just fixed?'],
                ),
                homework: Documents::quiz('Reading a Spanish menu', [
                    ['id' => 'oe1-q1', 'prompt' => 'A dish appears under «Primeros». What is it likely to be?', 'options' => [['id' => 'a', 'text' => 'A small nibble to share'], ['id' => 'b', 'text' => 'A full plate, often vegetables, rice or pulses'], ['id' => 'c', 'text' => 'The dessert']], 'correct_option_id' => 'b', 'explanation' => 'A primero is a full course in its own right, not an English-style starter.', 'concept_tag' => 'menu-structure'],
                    ['id' => 'oe1-q2', 'prompt' => '«Merluza a la plancha» tells you the fish has been…', 'options' => [['id' => 'a', 'text' => 'stewed slowly in liquid'], ['id' => 'b', 'text' => 'cooked quickly on a flat griddle'], ['id' => 'c', 'text' => 'roasted in the oven']], 'correct_option_id' => 'b', 'explanation' => 'A la plancha is the flat griddle — quick and dry, usually the lightest option on the page.', 'concept_tag' => 'cooking-methods'],
                    ['id' => 'oe1-q3', 'prompt' => 'What does a «menú del día» normally include?', 'options' => [['id' => 'a', 'text' => 'One plate and a drink'], ['id' => 'b', 'text' => 'A primero, a segundo, bread, a drink and a dessert or coffee'], ['id' => 'c', 'text' => 'Whatever the kitchen has left over']], 'correct_option_id' => 'b', 'explanation' => 'It is a full weekday lunch at one price, and usually the best value in the building.', 'concept_tag' => 'menu-structure'],
                ]),
                homeworkAnswers: ['oe1-q1' => 'b', 'oe1-q2' => 'b', 'oe1-q3' => 'b'],
            ),
            new LessonFixture(
                position: 2,
                taught: [
                    new SectionFixture("«Quiero un café» is grammatically perfect and lands like a demand. Spanish has a softer form sitting right next to it — «quisiera» — which means the same thing and costs you one syllable: «Quisiera un café, por favor.» Use it every time and you will never have to think about politeness again.\n\nThere is an even lighter option that natives use constantly: «me pone un café», literally *put me a coffee*, or «para mí, la merluza» — *for me, the hake*. Neither is rude. What is genuinely blunt is the bare noun with nothing around it, and even that is survivable with a *por favor* on the end."),
                    new SectionFixture("Ordering for a table is where the quantities matter. «Una ración» is a full plate to share, «una media ración» is half of one, and «una tapa» is the small serving that may arrive free with a drink. In Salamanca that last one is genuinely free and genuinely unpredictable — you do not choose it.\n\nFor the second round, «otra» is the word: «otra caña, por favor» — another small beer. Note it agrees with what you are ordering, so it is «otro café» and «otra cerveza». Saying «más café» asks for more coffee in the cup you already have, which is not what you meant."),
                ],
                completed: true,
                summary: Documents::summary(
                    'Quisiera went in within five minutes and stayed. The interesting half of the lesson was quantities: the student did not know that a ración was a plate to share and had been ordering three of them for one person. They asked good questions about the free tapa and were visibly pleased to learn it is not chosen.',
                    [
                        ['polite-ordering', 'solid', 'Used quisiera unprompted for the rest of the hour, including in two exchanges that were about something else.'],
                        ['quantities', 'shaky', 'Ración and media ración are secure; otro/otra agreement went wrong twice.'],
                    ],
                    ['Adjective agreement on otro/otra under time pressure.'],
                    ['Is it rude to ask for the free tapa if it does not arrive?'],
                ),
                homework: Documents::quiz('Ordering politely, and for a table', [
                    ['id' => 'oe2-q1', 'prompt' => 'Which lands best with a waiter you have never met?', 'options' => [['id' => 'a', 'text' => '«Quiero un café.»'], ['id' => 'b', 'text' => '«Quisiera un café, por favor.»'], ['id' => 'c', 'text' => '«Café.»']], 'correct_option_id' => 'b', 'explanation' => 'Quisiera is the softened form of quiero and costs you one syllable.', 'concept_tag' => 'polite-ordering'],
                    ['id' => 'oe2-q2', 'prompt' => 'You want a second beer. What do you say?', 'options' => [['id' => 'a', 'text' => '«Más caña, por favor.»'], ['id' => 'b', 'text' => '«Otra caña, por favor.»'], ['id' => 'c', 'text' => '«Otro caña, por favor.»']], 'correct_option_id' => 'b', 'explanation' => 'Otra for a fresh one, agreeing with the feminine caña. «Más» asks for a top-up of the glass you have.', 'concept_tag' => 'quantities'],
                    ['id' => 'oe2-q3', 'prompt' => '«Una ración de jamón» is…', 'options' => [['id' => 'a', 'text' => 'a small free nibble with a drink'], ['id' => 'b', 'text' => 'a full plate meant to be shared'], ['id' => 'c', 'text' => 'a single slice']], 'correct_option_id' => 'b', 'explanation' => 'A ración is a plate for the table. Order one each and you will have ordered far too much.', 'concept_tag' => 'quantities'],
                ]),
                homeworkAnswers: ['oe2-q1' => 'b', 'oe2-q2' => 'c', 'oe2-q3' => 'b'],
            ),
            new LessonFixture(
                position: 3,
                taught: [
                    new SectionFixture("The waiter's questions are a short and predictable list, and knowing it is most of what stops the switch into English. «¿Para beber?» — to drink? «¿Algo para picar?» — anything to nibble on? «¿Todo bien?» — everything all right? «¿Han terminado?» — have you finished?\n\nEach of them has a one-word answer that is enough. «Agua, por favor.» «No, gracias.» «Sí, muy bien.» «Sí, gracias.» You do not need a sentence, and reaching for one is what produces the pause that makes a waiter helpfully change language."),
                    new SectionFixture("Declining is where politeness in Spanish differs most from English politeness, because English hedges and Spanish does not. «No, gracias» is complete, warm and final; adding apologies to it sounds odd rather than polite.\n\nWhen you want to decline something and leave the door open, the phrase is «ahora no, gracias» — not now, thanks — or «quizás después», maybe later. And if you simply have not decided, «un momento, por favor» buys you as long as you need. None of these are rude, and all three are shorter than the English equivalents you are tempted to translate."),
                ],
                completed: true,
                summary: Documents::summary(
                    'This was the lesson the whole course was for. The student had been able to order and unable to survive the reply, and the fix was a list of four questions rather than any grammar. They found the declining section uncomfortable — they wanted to apologise — and by the end were saying «ahora no, gracias» without the apology attached.',
                    [
                        ['waiter-questions', 'solid', 'Answered all four questions correctly at speed, including two put to them out of order.'],
                        ['declining-politely', 'solid', 'Dropped the English hedging by the third attempt and said so themselves.'],
                    ],
                    ['Instinct to over-apologise when declining.'],
                    ['Does «¿todo bien?» expect a real answer or is it like "how are you"?'],
                ),
                homework: Documents::quiz('Answering the waiter', [
                    ['id' => 'oe3-q1', 'prompt' => 'The waiter says «¿Algo para picar?». What is being offered?', 'options' => [['id' => 'a', 'text' => 'The bill'], ['id' => 'b', 'text' => 'Something to nibble on before the meal'], ['id' => 'c', 'text' => 'A dessert menu']], 'correct_option_id' => 'b', 'explanation' => 'Picar is to nibble; this is the offer of something small to start.', 'concept_tag' => 'waiter-questions'],
                    ['id' => 'oe3-q2', 'prompt' => 'You do not want a dessert but might later. Which is best?', 'options' => [['id' => 'a', 'text' => '«Lo siento mucho, no, perdón.»'], ['id' => 'b', 'text' => '«Ahora no, gracias.»'], ['id' => 'c', 'text' => '«No quiero.»']], 'correct_option_id' => 'b', 'explanation' => 'Short and warm, and it leaves the door open. The apologies in the first sound strange rather than polite.', 'concept_tag' => 'declining-politely'],
                    ['id' => 'oe3-q3', 'prompt' => '«¿Han terminado?» is asking whether…', 'options' => [['id' => 'a', 'text' => 'you have finished eating'], ['id' => 'b', 'text' => 'you would like anything else'], ['id' => 'c', 'text' => 'you are paying by card']], 'correct_option_id' => 'a', 'explanation' => 'Terminar is to finish; the plural form is addressed to the table.', 'concept_tag' => 'waiter-questions'],
                ]),
                homeworkAnswers: ['oe3-q1' => 'b', 'oe3-q2' => 'b', 'oe3-q3' => 'a'],
            ),
            new LessonFixture(
                position: 4,
                taught: [
                    new SectionFixture("There is a real difference in Spanish between a preference and an allergy, and using the wrong one is how a restriction gets quietly ignored. «No como carne» — I do not eat meat — is a preference, and a kitchen may treat the stock in a soup as not counting. «Soy alérgico a los frutos secos» — I am allergic to nuts — is a medical statement and is treated as one.\n\nIf it matters, say «soy alérgico» or «soy alérgica», and add «es grave» if it is. Spanish kitchens are used to the phrase and legally obliged to answer you. The word for coeliac is *celíaco*, and it is worth knowing that Spain labels gluten-free food *sin gluten* everywhere, including on menus."),
                    new SectionFixture("Checking a single ingredient is two words: «¿Lleva…?» — does it carry? «¿Lleva nata?» «¿Lleva ajo?» «¿Lleva harina?» It is the exact question a waiter expects, and it is far more useful than trying to describe a whole dietary system.\n\nThe answer will often be «lleva un poco» — it has a little — which is a real answer and not an evasion. If a little is not acceptable, «no puedo comer nada de…» — I cannot eat any — is the sentence that closes it. Note *nada de*: without it you have said you cannot eat, full stop."),
                ],
                completed: true,
                summary: Documents::summary(
                    'The lesson turned on the distinction between «no como» and «soy alérgico», which the student had not known existed and immediately recognised as the reason a previous holiday had gone badly. «¿Lleva…?» was easy and they drilled it without being asked. Nada de came out wrong the first three times.',
                    [
                        ['dietary-restrictions', 'solid', 'Distinguished preference from allergy correctly in all five scenarios, including the two ambiguous ones.'],
                        ['ingredient-questions', 'shaky', '«¿Lleva…?» is automatic; «nada de» was dropped three times in a row before it stuck.'],
                    ],
                    ['Dropping «de» after nada, which changes the meaning entirely.'],
                    ['Is «sin gluten» reliable on a menu or should I ask anyway?'],
                ),
                homework: Documents::quiz('Saying what you cannot eat', [
                    ['id' => 'oe4-q1', 'prompt' => 'You have a serious nut allergy. Which sentence will a kitchen act on?', 'options' => [['id' => 'a', 'text' => '«No como frutos secos.»'], ['id' => 'b', 'text' => '«Soy alérgico a los frutos secos.»'], ['id' => 'c', 'text' => '«No me gustan los frutos secos.»']], 'correct_option_id' => 'b', 'explanation' => 'The first two are read as preferences. Soy alérgico is a medical statement and is treated as one.', 'concept_tag' => 'dietary-restrictions'],
                    ['id' => 'oe4-q2', 'prompt' => 'How do you ask whether a sauce contains garlic?', 'options' => [['id' => 'a', 'text' => '«¿Lleva ajo?»'], ['id' => 'b', 'text' => '«¿Tiene hambre?»'], ['id' => 'c', 'text' => '«¿Es ajo?»']], 'correct_option_id' => 'a', 'explanation' => 'Llevar is the verb for what a dish carries. The second asks whether the waiter is hungry.', 'concept_tag' => 'ingredient-questions'],
                    ['id' => 'oe4-q3', 'prompt' => 'A little is not acceptable. What do you say?', 'options' => [['id' => 'a', 'text' => '«No puedo comer.»'], ['id' => 'b', 'text' => '«No puedo comer nada de nata.»'], ['id' => 'c', 'text' => '«No como nata mucho.»']], 'correct_option_id' => 'b', 'explanation' => 'Nada de is what makes it none at all. Without it you have announced that you cannot eat.', 'concept_tag' => 'ingredient-questions'],
                ]),
                homeworkAnswers: ['oe4-q1' => 'b', 'oe4-q2' => 'a', 'oe4-q3' => 'b'],
            ),
            new LessonFixture(
                position: 5,
                taught: [
                    new SectionFixture("«La cuenta, por favor» is the whole phrase, and it will not arrive until you ask for it. This is the single biggest difference from an English restaurant: bringing the bill unasked would be hurrying you out, so nobody does it, and a table waiting politely can wait a very long time.\n\nOn the bill itself, *IVA incluido* means the tax is already in the total, which it almost always is. *Servicio* appears rarely and, when it does, means the service charge is included. A *cubierto* is a cover charge for bread and table setting, and it is legitimate."),
                    new SectionFixture("Tipping in Spain is small and optional in a way it is not in Britain or America. Rounding up is normal, leaving the coins from the change is normal, and ten per cent is generous rather than expected. In a bar, nothing at all is entirely ordinary.\n\nTo pay by card, «¿Puedo pagar con tarjeta?» To split, «¿Podemos pagar por separado?» — though be prepared for a no in a small place, where one person paying and settling up afterwards is the norm. «Quédese con el cambio» is *keep the change*, and it is the phrase that ends the meal."),
                ],
                completed: true,
                summary: Documents::summary(
                    'A short, easy lesson to finish on, and the student knew most of it already from the reading they had done. The useful part was the bill vocabulary — IVA, cubierto, servicio — which they had seen and never had explained. They were relieved to hear that tipping is small.',
                    [
                        ['asking-for-the-bill', 'solid', 'Asked for the bill, queried a cubierto line and asked to pay by card, all in one unbroken exchange.'],
                        ['tipping-customs', 'solid', 'Stated the convention accurately and distinguished a bar from a restaurant unprompted.'],
                    ],
                    [],
                    ['If the bill never comes, is it rude to go to the bar and pay there?'],
                ),
                homework: Documents::quiz('The bill', [
                    ['id' => 'oe5-q1', 'prompt' => 'You have finished and want to leave. What happens if you wait?', 'options' => [['id' => 'a', 'text' => 'The bill arrives within a few minutes'], ['id' => 'b', 'text' => 'Nothing — you have to ask for it'], ['id' => 'c', 'text' => 'The waiter clears the table and then brings it']], 'correct_option_id' => 'b', 'explanation' => 'Bringing the bill unasked would be hurrying you out, so nobody does it.', 'concept_tag' => 'asking-for-the-bill'],
                    ['id' => 'oe5-q2', 'prompt' => '«IVA incluido» on a bill means…', 'options' => [['id' => 'a', 'text' => 'the tip is included'], ['id' => 'b', 'text' => 'the tax is already in the total'], ['id' => 'c', 'text' => 'bread is charged separately']], 'correct_option_id' => 'b', 'explanation' => 'IVA is the sales tax, and it is almost always already in the printed total.', 'concept_tag' => 'asking-for-the-bill'],
                    ['id' => 'oe5-q3', 'prompt' => 'How much would you normally tip after a restaurant meal in Spain?', 'options' => [['id' => 'a', 'text' => 'Twenty per cent'], ['id' => 'b', 'text' => 'Round up, or leave the coins'], ['id' => 'c', 'text' => 'Exactly fifteen per cent, always']], 'correct_option_id' => 'b', 'explanation' => 'Small and optional. Ten per cent reads as generous rather than expected.', 'concept_tag' => 'tipping-customs'],
                ]),
                homeworkAnswers: ['oe5-q1' => 'b', 'oe5-q2' => 'b', 'oe5-q3' => 'b'],
            ),
        ];
    }

    /**
     * A `json/quiz.v1` exam, ten questions, two per declared exam concept.
     *
     * Ten sits inside QuizLength::exam()'s 8..12, so 0.7 asks for seven —
     * which is what makes the pass mark mean one thing (6.3, D20).
     *
     * @return array<string, mixed>
     */
    private static function exam(): array
    {
        return [
            'quiz' => [
                'scope' => 'Everything from Ordering and Eating Out — the menu, the order, the questions back, what you cannot eat, and the bill',
                'questions' => [
                    ['id' => 'oe-x1', 'prompt' => 'Under which heading would you look for a plate of lentils?', 'options' => [['id' => 'a', 'text' => 'Primeros'], ['id' => 'b', 'text' => 'Segundos'], ['id' => 'c', 'text' => 'Postres'], ['id' => 'd', 'text' => 'Para picar']], 'correct_option_id' => 'a', 'explanation' => 'Pulses, rice, vegetables and soups sit under primeros, which is a full course rather than a starter.', 'concept_tag' => 'menu-structure'],
                    ['id' => 'oe-x2', 'prompt' => 'A weekday «menú del día» normally buys you…', 'options' => [['id' => 'a', 'text' => 'one plate and a coffee'], ['id' => 'b', 'text' => 'a primero, a segundo, bread, a drink, and a dessert or coffee'], ['id' => 'c', 'text' => 'whatever is left at the end of service']], 'correct_option_id' => 'b', 'explanation' => 'It is a full lunch at a fixed price, and usually the best food in the building.', 'concept_tag' => 'menu-structure'],
                    ['id' => 'oe-x3', 'prompt' => 'Which opening is the safest with a waiter you have never met?', 'options' => [['id' => 'a', 'text' => '«Quiero la merluza.»'], ['id' => 'b', 'text' => '«Quisiera la merluza, por favor.»'], ['id' => 'c', 'text' => '«Dame la merluza.»']], 'correct_option_id' => 'b', 'explanation' => 'Quisiera is the softened form and costs nothing. Dame is an order given to a friend.', 'concept_tag' => 'polite-ordering'],
                    ['id' => 'oe-x4', 'prompt' => 'You are ordering on behalf of someone else at the table. Which works?', 'options' => [['id' => 'a', 'text' => '«Para él, la sopa.»'], ['id' => 'b', 'text' => '«Él quiere sopa ahora.»'], ['id' => 'c', 'text' => '«Sopa para yo.»']], 'correct_option_id' => 'a', 'explanation' => '«Para él, la sopa» is the natural construction and needs no verb at all.', 'concept_tag' => 'polite-ordering'],
                    ['id' => 'oe-x5', 'prompt' => 'The waiter asks «¿Para beber?». What is wanted?', 'options' => [['id' => 'a', 'text' => 'Your drinks order'], ['id' => 'b', 'text' => 'Whether you have finished'], ['id' => 'c', 'text' => 'Whether you want dessert'], ['id' => 'd', 'text' => 'Your table number']], 'correct_option_id' => 'a', 'explanation' => 'Beber is to drink; this is the first question of nearly every meal.', 'concept_tag' => 'waiter-questions'],
                    ['id' => 'oe-x6', 'prompt' => 'Halfway through the meal the waiter says «¿Todo bien?». The expected reply is…', 'options' => [['id' => 'a', 'text' => 'a full account of the meal so far'], ['id' => 'b', 'text' => '«Sí, muy bien, gracias.»'], ['id' => 'c', 'text' => '«La cuenta, por favor.»']], 'correct_option_id' => 'b', 'explanation' => 'It is a check, not an invitation to review. Two words and a thank you is a complete answer.', 'concept_tag' => 'waiter-questions'],
                    ['id' => 'oe-x7', 'prompt' => 'Which sentence will a kitchen treat as a medical restriction?', 'options' => [['id' => 'a', 'text' => '«No como marisco.»'], ['id' => 'b', 'text' => '«Soy alérgica al marisco.»'], ['id' => 'c', 'text' => '«El marisco no me gusta.»']], 'correct_option_id' => 'b', 'explanation' => 'Only the allergy is stated as one. The other two are preferences and may be handled loosely.', 'concept_tag' => 'dietary-restrictions'],
                    ['id' => 'oe-x8', 'prompt' => 'You cannot have any cream at all. Which says so?', 'options' => [['id' => 'a', 'text' => '«No puedo comer nata mucho.»'], ['id' => 'b', 'text' => '«No puedo comer nada de nata.»'], ['id' => 'c', 'text' => '«No puedo comer.»']], 'correct_option_id' => 'b', 'explanation' => '«Nada de» is what makes it none at all; the last option says you cannot eat, full stop.', 'concept_tag' => 'dietary-restrictions'],
                    ['id' => 'oe-x9', 'prompt' => 'How do you get the bill?', 'options' => [['id' => 'a', 'text' => 'Wait — it comes when the table is cleared'], ['id' => 'b', 'text' => 'Ask: «La cuenta, por favor.»'], ['id' => 'c', 'text' => 'Go to the kitchen']], 'correct_option_id' => 'b', 'explanation' => 'It never arrives unasked, because bringing it would be hurrying you out.', 'concept_tag' => 'asking-for-the-bill'],
                    ['id' => 'oe-x10', 'prompt' => 'A line on the bill reads «cubierto». What is it?', 'options' => [['id' => 'a', 'text' => 'A service charge'], ['id' => 'b', 'text' => 'A cover charge for bread and the table setting'], ['id' => 'c', 'text' => 'The tax'], ['id' => 'd', 'text' => 'A mistake']], 'correct_option_id' => 'b', 'explanation' => 'A legitimate cover charge. Servicio would be the service charge and IVA the tax.', 'concept_tag' => 'asking-for-the-bill'],
                ],
                'suggested_reading' => [],
            ],
        ];
    }
}
