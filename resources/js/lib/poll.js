import { getJson } from './dom';

/** Reload the page once the status endpoint says the operation is no longer open. */
export function reloadWhenClosed(url, { every = 3000, retry = 5000, max = 120 } = {}) {
    let tries = 0;
    const poll = () =>
        getJson(url)
            .then((s) => {
                if (!s.open) {
                    location.reload();
                    return;
                }
                if (++tries < max) setTimeout(poll, every);
            })
            .catch(() => setTimeout(poll, retry));
    setTimeout(poll, every);
}
