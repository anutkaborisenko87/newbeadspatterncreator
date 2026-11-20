import {useState} from "react";
import {usePage} from "@inertiajs/react";
import Modal from "@/Components/Modal.jsx";

function downloadFile(url, filename = '') {
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();
}

export default function PatternDownloadDialog({format, settings, legacyUrl, onClose}) {
    const {patternDownloadLabels: labels, bead_dictation: dictation} = usePage().props;
    const [includeDictation, setIncludeDictation] = useState(false);
    const [preparing, setPreparing] = useState(false);
    const [error, setError] = useState(null);

    async function submit(event) {
        event.preventDefault();
        if (preparing) return;
        if (!settings) {
            downloadFile(legacyUrl);
            onClose();
            return;
        }

        const form = new FormData(event.currentTarget);
        const url = new URL(settings[format], window.location.href);
        url.searchParams.set('dictation', includeDictation ? '1' : '0');
        if (includeDictation) {
            url.searchParams.set('rows', form.get('rows'));
            url.searchParams.set('columns', form.get('columns'));
        }
        setPreparing(true);
        setError(null);

        try {
            const response = await fetch(url, {headers: {Accept: 'application/json'}});
            if (!response.ok || !response.headers.get('Content-Type')?.startsWith('image/')) {
                const details = await response.json().catch(() => null);
                throw new Error(details?.errors?.pattern?.[0] ?? labels.error);
            }
            const filename = response.headers.get('Content-Disposition')?.match(/filename="?([^";]+)"?/)?.[1]
                ?? `pattern.${format}`;
            const objectUrl = URL.createObjectURL(await response.blob());
            downloadFile(objectUrl, filename);
            window.setTimeout(() => URL.revokeObjectURL(objectUrl), 1000);
            onClose();
        } catch (error) {
            setError(error.message === labels.too_large ? error.message : labels.error);
            setPreparing(false);
        }
    }

    return (
        <Modal open title={`${labels.title} ${format.toUpperCase()}`} onClose={onClose}
               closeDisabled={preparing} closeLabel={labels.cancel}>
            <form className="gallery-publication-form pattern-download-form" onSubmit={submit}>
                <p>{settings ? labels.layout : labels.image_only}</p>
                {settings && <fieldset disabled={preparing}>
                    <label className="pattern-download-checkbox">
                        <input type="checkbox" checked={includeDictation} autoFocus
                               onChange={(event) => setIncludeDictation(event.target.checked)}/>
                        {labels.include_dictation}
                    </label>
                    <div className="pattern-download-directions" hidden={!includeDictation}>
                        <label>{dictation.row_order}
                            <select name="rows" defaultValue={settings.rows}>
                                <option value="bottom_up">{dictation.bottom_up}</option>
                                <option value="top_down">{dictation.top_down}</option>
                            </select>
                        </label>
                        <label>{dictation.column_order}
                            <select name="columns" defaultValue={settings.columns}>
                                <option value="left_to_right">{dictation.left_to_right}</option>
                                <option value="right_to_left">{dictation.right_to_left}</option>
                            </select>
                        </label>
                    </div>
                </fieldset>}
                {error && <p role="alert">{error}</p>}
                <div className="gallery-publication-actions">
                    <button type="button" onClick={onClose} disabled={preparing}>{labels.cancel}</button>
                    <button type="submit" disabled={preparing}>
                        {preparing ? labels.preparing : `${labels.download} ${format.toUpperCase()}`}
                    </button>
                </div>
            </form>
        </Modal>
    );
}
