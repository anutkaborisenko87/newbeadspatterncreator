import {loadEditorDraft} from '../store/gridSlice.js';

function readCachedDraft(key) {
    try {
        const snapshot = JSON.parse(window.localStorage.getItem(key));
        if (Number.isSafeInteger(snapshot?.version) && snapshot.drawing) return snapshot;
    } catch {
        // Browser storage can be unavailable or contain data from an interrupted write.
    }
    return null;
}

function cacheDraft(key, snapshot) {
    try {
        window.localStorage.setItem(key, JSON.stringify(snapshot));
    } catch {
        // Server autosave still works when the browser cannot store a backup.
    }
}

async function saveDraft(url, owner, snapshot, keepalive, method = 'PUT') {
    const body = JSON.stringify({owner, ...snapshot});
    const xsrf = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)?.[1];
    const csrfHeader = xsrf
        ? {'X-XSRF-TOKEN': decodeURIComponent(xsrf)}
        : {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? ''};

    return fetch(url, {
        method,
        headers: {'Content-Type': 'application/json', Accept: 'application/json', ...csrfHeader},
        body,
        keepalive: keepalive && new Blob([body]).size < 60_000,
    });
}

export function getDrawing(grid) {
    const {gridWidth, gridHeight, margin, cellSize, view} = grid;
    const drawnCells = Object.fromEntries(Object.entries(grid.drawnCells).filter(([key]) => {
        const [x, y] = key.split(',').map(Number);
        return x >= margin && x < margin + gridWidth && y >= margin && y < margin + gridHeight
            && (x - margin) % cellSize === 0 && (y - margin) % cellSize === 0;
    }));
    return {gridWidth, gridHeight, drawnCells, view};
}

function afterVersion(version) {
    return Math.max(version + 1, Date.now() * 1000 + Math.floor(Math.random() * 1000));
}

export function startEditorDraft(store, owner, url, serverDraft, savedDraftId, capturePreview, workingUrl) {
    const workingKey = `editor-draft:${owner}`;
    const key = savedDraftId == null ? workingKey : `${workingKey}:saved:${savedDraftId}`;
    const localDraft = readCachedDraft(key);
    let pending = (localDraft?.version ?? 0) > (serverDraft?.version ?? 0) ? localDraft : null;
    let pendingSince = Date.now();
    const initialDraft = pending ?? serverDraft;
    if (!pending && savedDraftId != null && capturePreview && serverDraft?.version !== serverDraft?.previewVersion) {
        pending = serverDraft;
    }
    let version = initialDraft?.version ?? 0;
    let latestVersion = version;
    let timer;
    let sending = false;
    let stopped = false;
    let archiving = false;
    let restartOperation;
    let inFlight = null;

    store.dispatch(loadEditorDraft(initialDraft?.drawing));
    let previous = store.getState().grid;

    const schedule = (delay) => {
        window.clearTimeout(timer);
        if (!stopped) timer = window.setTimeout(flush, delay);
    };

    async function flush() {
        window.clearTimeout(timer);
        if (!pending || sending || stopped || archiving) return;

        const snapshot = pending;
        sending = true;
        let retry = false;
        try {
            const preview = savedDraftId != null && capturePreview ? await capturePreview().catch(() => null) : null;
            if (stopped || archiving) return;
            inFlight = snapshot;
            const response = await saveDraft(url, owner,
                preview && pending === snapshot ? {...snapshot, ...preview} : snapshot, false);
            if (response.ok) {
                const result = await response.json();
                latestVersion = Math.max(latestVersion, result.version);
                if (pending === snapshot) pending = null;
            } else {
                retry = response.status >= 500 || response.status === 429;
            }
        } catch {
            retry = true;
        } finally {
            sending = false;
            inFlight = null;
            if (pending && pending !== snapshot) schedule(0);
            else if (retry) schedule(5000);
        }
    }

    const unsubscribe = store.subscribe(() => {
        if (archiving) return;
        const grid = store.getState().grid;
        if (grid.drawnCells === previous.drawnCells && grid.gridWidth === previous.gridWidth
            && grid.gridHeight === previous.gridHeight && grid.view === previous.view) return;
        previous = grid;
        restartOperation = undefined;
        version = latestVersion = afterVersion(latestVersion);
        if (!pending || pending === inFlight) pendingSince = Date.now();
        pending = {version, drawing: getDrawing(grid)};
        cacheDraft(key, pending);
        schedule(Math.max(0, Math.min(750, pendingSince + 3000 - Date.now())));
    });

    const saveBeforeLeaving = () => {
        if (pending && pending !== inFlight) {
            void saveDraft(url, owner, pending, true).catch(() => {});
        }
    };
    const saveWhenHidden = () => {
        if (document.visibilityState === 'hidden') saveBeforeLeaving();
    };
    window.addEventListener('pagehide', saveBeforeLeaving);
    window.addEventListener('online', flush);
    document.addEventListener('visibilitychange', saveWhenHidden);
    if (pending) schedule(750);

    async function archive({saveCopy = false} = {}) {
        if (archiving || stopped) return false;
        archiving = true;
        window.clearTimeout(timer);
        if (!version) version = latestVersion = afterVersion(latestVersion);
        const snapshot = {version, drawing: getDrawing(store.getState().grid)};

        try {
            const preview = capturePreview ? await capturePreview().catch(() => null) : null;
            if (capturePreview && !preview && !saveCopy) throw new Error('Draft preview capture failed');
            const payload = {...snapshot, ...preview};
            if (savedDraftId != null && !saveCopy) {
                await backupWorkingDraft();
                restartOperation ??= afterVersion(latestVersion);
                payload.operation = restartOperation;
            }
            const response = await saveDraft(url, owner, payload, false, saveCopy ? 'PUT' : 'POST');
            if (!response.ok) throw new Error('Draft archive failed');
            const nextDraft = await response.json();
            if (saveCopy && nextDraft.version !== snapshot.version) throw new Error('Newer copy already saved');
            if (stopped) return false;
            pending = null;
            if (savedDraftId != null) {
                cacheDraft(key, snapshot);
                return saveCopy ? true : nextDraft.editorUrl;
            }
            version = nextDraft.version;
            latestVersion = Math.max(latestVersion, version);
            cacheDraft(key, nextDraft);
            store.dispatch(loadEditorDraft(nextDraft.drawing));
            previous = store.getState().grid;
            return true;
        } finally {
            archiving = false;
            if (pending) schedule(0);
        }
    }

    function stop() {
        stopped = true;
        unsubscribe();
        window.clearTimeout(timer);
        window.removeEventListener('pagehide', saveBeforeLeaving);
        window.removeEventListener('online', flush);
        document.removeEventListener('visibilitychange', saveWhenHidden);
        saveBeforeLeaving();
        store.dispatch(loadEditorDraft());
    }

    async function backupWorkingDraft() {
        const backup = savedDraftId != null ? readCachedDraft(workingKey) : null;
        if (backup && !(await saveDraft(workingUrl, owner, backup, false)).ok) {
            throw new Error('Working draft backup failed');
        }
    }

    function finishPublication(workspace) {
        archiving = true;
        pending = null;
        window.clearTimeout(timer);
        if (workspace) cacheDraft(workingKey, workspace);
        store.dispatch(loadEditorDraft(workspace?.drawing));
    }

    return {archive, stop, backupWorkingDraft, finishPublication};
}
