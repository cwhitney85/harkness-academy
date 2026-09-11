/**
 * The five events Harkness Academy streams (spec 5.2.5).
 *
 * One vocabulary for every streamed turn in the product — a lesson section, a
 * Q&A turn, the side-tab explainer and an interview question all arrive as
 * these, which is why `TurnKind` has four members and this file has no
 * lesson-shaped fields outside `turn.start`.
 *
 * Mirrors `App\Lessons\Streaming\StreamVocabulary`. A sixth name is a spec
 * change (5.2.5) before it is a type change.
 */

import type { QuizQuestion } from '@/types/assessment';

export type TurnKind = 'section' | 'qa' | 'interview' | 'explainer';

export type TurnEvent =
    /**
     * The section fields travel together or not at all: a `qa`, `explainer` or
     * `interview` turn has no section to name, and the server omits them
     * rather than sending nulls for every client to check.
     */
    | {
          name: 'turn.start';
          data: {
              turn_id: string;
              kind: TurnKind;
              section_index?: number;
              section_title?: string;
              sections_total?: number;
          };
      }
    | { name: 'turn.delta'; data: { turn_id: string; text: string } }
    /** The promise that the turn survived: the row existed before this was sent. */
    | { name: 'turn.end'; data: { turn_id: string; message_id: number } }
    /**
     * The section's checkpoint, stripped of its answer key (5.2.3), and the id
     * that addresses `POST …/checkpoints/{checkpoint}/answers`. Without the id
     * a checkpoint learned from the stream could be shown and not answered.
     */
    | {
          name: 'checkpoint.ready';
          data: {
              checkpoint_id: number;
              checkpoint: { section_index: number; questions: QuizQuestion[] };
          };
      }
    /** `retryable` is the whole value: whether reopening is worth doing. */
    | { name: 'stream.error'; data: { code: string; retryable: boolean } };
