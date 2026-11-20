import { getJson } from './dom';
import { later, reloadPage } from './page';

/** Show the page again once the status endpoint says the operation is no longer open. */
export function reloadWhenClosed(url, { every = 3000, retry = 5000, max = 120 } = {}) {
    let tries = 0;
    // later(): the polling stops when the user opens another page.
    const poll = () =>
        getJson(url)
            .then((s) => {
                if (!s.open) {
                    reloadPage();
                    return;
                }
                if (++tries < max) later(poll, every);
            })
            .catch(() => later(poll, retry));
    later(poll, every);
}
