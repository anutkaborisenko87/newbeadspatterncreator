import {useForm, usePage} from '@inertiajs/react';
import {useState} from 'react';
import {useDispatch} from 'react-redux';
import Modal from '@/Components/Modal.jsx';
import {openCloseModal} from '@/store/modalSlice.js';

export default function ForgotPasswordModal() {
    const {url, labels, sent} = usePage().props.passwordResetModal;
    const dispatch = useDispatch();
    const {data, setData, post, processing, errors} = useForm({email: ''});
    const [submitted, setSubmitted] = useState(false);

    function submit(event) {
        event.preventDefault();
        post(url, {preserveScroll: true, onSuccess: () => setSubmitted(true)});
    }

    return (
        <Modal open title={labels.heading} closeLabel={labels.close} closeDisabled={processing}
               onClose={() => dispatch(openCloseModal({open: false}))}>
            {submitted ? <p role="status">{sent}</p> : (
                <form className="password-reset-form" onSubmit={submit}>
                    <p>{labels.description}</p>
                    <label>
                        {labels.email}
                        <input name="email" type="email" autoComplete="email" value={data.email}
                               onChange={(event) => setData('email', event.target.value)} required autoFocus
                               aria-invalid={Boolean(errors.email)} aria-describedby={errors.email ? 'reset-email-error' : undefined} />
                    </label>
                    {errors.email && <p id="reset-email-error" role="alert">{errors.email}</p>}
                    <button type="submit" disabled={processing}>{labels.send}</button>
                </form>
            )}
            <button className="password-reset-back" type="button" disabled={processing}
                    onClick={() => dispatch(openCloseModal({open: true, mode: 'login'}))}>
                {labels.back}
            </button>
        </Modal>
    );
}
