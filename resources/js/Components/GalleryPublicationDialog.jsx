import axios from 'axios';
import {router} from '@inertiajs/react';
import {useRef, useState} from 'react';
import Modal from '@/Components/Modal.jsx';

export default function GalleryPublicationDialog({config, getSnapshot, onClose, onPublished}) {
    const {labels, categories, url, revision} = config;
    const publicationId = useRef(null);
    const [category, setCategory] = useState(revision?.category ?? '');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState([]);

    async function submit(event) {
        event.preventDefault();
        if (saving) return;
        const form = new FormData(event.currentTarget);
        setSaving(true);
        setErrors([]);
        try {
            publicationId.current ??= crypto.randomUUID();
            const payload = {
                ...await getSnapshot(),
                publicationId: publicationId.current,
                title: form.get('title'),
                description: form.get('description'),
                category,
                ...(revision ? {publicationMode: form.get('publicationMode')} : {}),
                ...(category === 'new' ? {categoryName: form.get('categoryName')} : {}),
                publishToGallery: form.get('publishToGallery') === 'on',
            };
            const photo = form.get('photo');
            let body = payload;
            if (photo?.name) {
                body = new FormData();
                body.append('photo', photo);
                for (const [key, value] of Object.entries(payload)) {
                    if (value != null) body.append(key, key === 'drawing' ? JSON.stringify(value) : value);
                }
            }
            const {data} = await axios.post(url, body, {headers: {Accept: 'application/json'}});
            onPublished(data.workspace);
            onClose();
            router.visit(data.editorUrl);
        } catch (error) {
            const validation = error.response?.data?.errors;
            setErrors(validation ? Object.values(validation).flat() : [labels.error]);
            setSaving(false);
        }
    }

    return (
        <Modal open title={labels.heading} onClose={onClose} closeDisabled={saving} closeLabel={labels.cancel}>
            <form className="gallery-publication-form" onSubmit={submit}>
                <fieldset disabled={saving}>
                    {revision && <label>{labels.revision_destination}
                        <select name="publicationMode" required defaultValue="">
                            <option value="" disabled>{labels.revision_destination}</option>
                            <option value="replace">{labels.replace}</option>
                            <option value="new">{labels.publish_new}</option>
                        </select>
                    </label>}
                    <label>{labels.title}<input name="title" defaultValue={revision?.title ?? ''} required maxLength={255} autoFocus /></label>
                    <label>{labels.description}<textarea name="description" defaultValue={revision?.description ?? ''} rows={3} maxLength={5000} /></label>
                    <label>{labels.photo}<input type="file" name="photo" accept="image/jpeg,image/png,image/webp" /></label>
                    <label>{labels.category}
                        <select required value={category} onChange={(event) => setCategory(event.target.value)}>
                            <option value="" disabled>{labels.choose_category}</option>
                            {categories.map(({id, title}) => <option key={id} value={id}>{title}</option>)}
                            <option value="new">{labels.new_category}</option>
                        </select>
                    </label>
                    {category === 'new' && <label>{labels.category_name}
                        <input name="categoryName" required maxLength={255} />
                        <small>{labels.translation_hint}</small>
                    </label>}
                    <label>
                        <input type="checkbox" name="publishToGallery" defaultChecked />
                        {labels.publish_to_gallery}
                    </label>
                </fieldset>
                {errors.length > 0 && <ul role="alert">{errors.map((message, index) => <li key={index}>{message}</li>)}</ul>}
                <div className="gallery-publication-actions">
                    <button type="button" onClick={onClose} disabled={saving}>{labels.cancel}</button>
                    <button type="submit" disabled={saving}>{saving ? labels.saving : labels.publish}</button>
                </div>
            </form>
        </Modal>
    );
}
