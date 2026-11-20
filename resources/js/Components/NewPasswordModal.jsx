import {useForm, usePage} from '@inertiajs/react';
import {useDispatch} from 'react-redux';
import Modal from '@/Components/Modal.jsx';
import {openCloseModal} from '@/store/modalSlice.js';

export default function NewPasswordModal() {
    const {email, resetId, url, labels} = usePage().props.newPasswordModal;
    const dispatch = useDispatch();
    const {data, setData, post, processing, errors, reset} = useForm({resetId, password: '', password_confirmation: ''});

    function submit(event) {
        event.preventDefault();
        post(url, {preserveScroll: true, onFinish: () => reset('password', 'password_confirmation')});
    }

    return (
        <Modal open title={labels.reset_heading} closeLabel={labels.close} closeDisabled={processing}
               onClose={() => dispatch(openCloseModal({open: false}))}>
            <form className="password-reset-form" onSubmit={submit}>
                <label>{labels.email}<input type="email" value={email} readOnly /></label>
                <label>
                    {labels.password}
                    <input type="password" name="password" autoComplete="new-password" value={data.password}
                           onChange={(event) => setData('password', event.target.value)} required autoFocus />
                </label>
                <label>
                    {labels.confirmation}
                    <input type="password" name="password_confirmation" autoComplete="new-password" value={data.password_confirmation}
                           onChange={(event) => setData('password_confirmation', event.target.value)} required />
                </label>
                {(errors.email || errors.password) && <p role="alert">{errors.email || errors.password}</p>}
                <button type="submit" disabled={processing}>{labels.reset_button}</button>
            </form>
            {errors.email && <button className="password-reset-back" type="button"
                onClick={() => dispatch(openCloseModal({open: true, mode: 'forgot-password'}))}>{labels.heading}</button>}
        </Modal>
    );
}
