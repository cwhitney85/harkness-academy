import { cleanup, render } from '@testing-library/react';
import { afterEach, expect, test } from 'vitest';

import LessonSection from '@/components/lesson-section';
import type { LessonTurn } from '@/types';

/**
 * Spec 8.8.7 — emphasis pairs, across the explainer's mark and mid-stream.
 *
 * Both failures were the same failure: the paragraph was cut into strings and
 * each piece asked for its own pair of asterisks. Splitting around the mark
 * left `*a ` and ` b*`, neither of which pairs; a paragraph still arriving
 * held only the opener. Either way the student read an asterisk.
 */

afterEach(cleanup);

function turn(content: string): LessonTurn {
    return {
        message_id: 1,
        turn_id: 'turn-1',
        kind: 'section',
        speaker: 'tutor',
        section_index: 1,
        content,
    };
}

function section(content: string, mark: string | null = null) {
    return render(
        <LessonSection
            title="Ser and estar"
            concepts={['ser-estar']}
            turns={[turn(content)]}
            live={null}
            marked={mark === null ? null : { messageId: 1, text: mark }}
        />,
    ).container;
}

test('an emphasis span containing the marked phrase stays one span', () => {
    const container = section(
        'Say *the soup is good* and you have said something else.',
        'soup is',
    );

    expect(container.querySelectorAll('em')).toHaveLength(1);
    expect(container.querySelectorAll('mark')).toHaveLength(1);
    expect(container.querySelector('em')?.textContent).toBe('the soup is good');
    expect(container.querySelector('mark')?.textContent).toBe('soup is');
    expect(container.textContent).not.toContain('*');
});

test('a mark outside the emphasis leaves both intact', () => {
    const container = section('Say *buena* about the recipe.', 'the recipe');

    expect(container.querySelectorAll('em')).toHaveLength(1);
    expect(container.querySelectorAll('mark')).toHaveLength(1);
    expect(container.querySelector('em')?.textContent).toBe('buena');
    expect(container.querySelector('mark')?.textContent).toBe('the recipe');
});

test('the double asterisks the model also writes are the same emphasis', () => {
    const container = section(
        'A payoff is an **absolute payoff** — what you walk away with.',
    );

    expect(container.textContent).not.toContain('*');
    expect(container.querySelectorAll('em')).toHaveLength(1);
    expect(container.querySelector('em')?.textContent).toBe('absolute payoff');
});

test('a paragraph still arriving prints no opener', () => {
    const { container } = render(
        <LessonSection
            title="Ser and estar"
            concepts={['ser-estar']}
            turns={[]}
            live="The distinction rests on *perman"
            marked={null}
        />,
    );

    expect(container.textContent).not.toContain('*');
    expect(container.querySelector('em')?.textContent).toBe('perman');
});

test('the drop cap is the opening letter, never an asterisk', () => {
    const container = section('*Ser* is the one that lasts.');

    expect(container.textContent).not.toContain('*');
    expect(container.querySelector('span.float-left')?.textContent).toBe('S');
    expect(container.querySelector('em')?.textContent).toBe('er');
});
