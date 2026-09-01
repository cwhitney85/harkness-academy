/**
 * The two endpoints that answer a lesson screen in JSON rather than in a page:
 * a checkpoint answer (spec 5.2.4) and a selection explanation (5.5).
 *
 * Inertia's own visits go through axios and can only follow a redirect, which
 * is not what either of these returns — grading and explaining leave the
 * student on the screen they are already on. So they are `fetch` calls, and
 * this is the one place that remembers what Laravel's session guard wants on a
 * POST. `readTurnStream` reads the same header for the same reason.
 */

/**
 * POST `body` as JSON and read the response back.
 *
 * Pre:  `url` is a same-origin route the student is authenticated for.
 * Post: resolves with the decoded body on a 2xx; rejects with the server's own
 *       `message` otherwise, because both endpoints refuse in words written
 *       for the student (an over-long highlight, an unanswerable question) and
 *       a screen that replaced them with wording of its own would be saying
 *       something the server did not.
 */
export async function postJson<T>(url: string, body: unknown): Promise<T> {
    const response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...csrfHeader(),
        },
        body: JSON.stringify(body),
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        throw new Error(
            (payload as { message?: string } | null)?.message ??
                `The request was refused with ${response.status}.`,
        );
    }

    return payload as T;
}

/**
 * The token Laravel's session guard wants on a POST.
 *
 * Inertia's own requests go through axios, which reads this cookie for us;
 * a raw `fetch` has to do it itself or every request is a 419.
 */
export function csrfHeader(): Record<string, string> {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie
        ? {
              'X-XSRF-TOKEN': decodeURIComponent(
                  cookie.slice('XSRF-TOKEN='.length),
              ),
          }
        : {};
}
