import {useState} from 'react';
import axios from 'axios';
import Modal from '@/Components/Modal.jsx';

export default function ReportCommentDialog({entry, url, labels, onClose, onSent}) {
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    async function submit(event) {
        event.preventDefault();
        if (busy) return;
        const body = new FormData(event.currentTarget);
        if (!body.get('screenshots[]')?.name) body.delete('screenshots[]');
        setBusy(true);
        setError('');
        try {
            await axios.post(url, body, {headers: {Accept: 'application/json'}});
            onSent();
        } catch (failure) {
            setError(Object.values(failure.response?.data?.errors ?? {}).flat().join(' ') || labels.failed);
            setBusy(false);
        }
    }
    return <Modal open title={labels.report + ' · ' + entry.login} onClose={onClose} closeDisabled={busy} closeLabel={labels.cancel}>
        <form className="gallery-publication-form" onSubmit={submit}>
            <fieldset disabled={busy}>
                <label>{labels.topic}<select name="topic" required defaultValue="">
                    <option disabled value="">{labels.choose}</option>
                    {Object.entries(labels.topics).map(([key, title]) => <option key={key} value={key}>{title}</option>)}
                </select></label>
                <label>{labels.message}<textarea required name="message" rows={4} maxLength={4000}/></label>
                <label>{labels.upload}<input type="file" multiple name="screenshots[]" accept="image/png,image/jpeg,image/webp"/></label>
            </fieldset>
            {error && <p role="alert">{error}</p>}
            <div className="gallery-publication-actions"><button disabled={busy} type="submit">{labels.send}</button></div>
        </form>
    </Modal>;
}
