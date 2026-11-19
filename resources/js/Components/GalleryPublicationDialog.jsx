import axios from 'axios';
import {router} from '@inertiajs/react';
import {useRef, useState} from 'react';
import Modal from '@/Components/Modal.jsx';

export default function GalleryPublicationDialog({config, getSnapshot, onClose}) {
    const {labels, categories, url} = config;
    const publicationId = useRef(null);
    const [category, setCategory] = useState('');
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
            const {data} = await axios.post(url, {
                ...await getSnapshot(),
                publicationId: publicationId.current,
                title: form.get('title'),
                description: form.get('description'),
                category,
                ...(category === 'new' ? {categoryName: form.get('categoryName')} : {}),
            }, {headers: {Accept: 'application/json'}});
            onClose();
            router.visit(data.galleryUrl);
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
                    <label>{labels.title}<input name="title" required maxLength={255} autoFocus /></label>
                    <label>{labels.description}<textarea name="description" rows={3} maxLength={5000} /></label>
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
