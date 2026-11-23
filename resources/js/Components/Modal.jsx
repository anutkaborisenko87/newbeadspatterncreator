import {openCloseModal} from "../store/modalSlice.js";
import {useDispatch, useSelector} from "react-redux";
import Icon from "@/Components/Icon.jsx";
import {useCallback, useEffect, useRef} from "react";
import {createPortal} from "react-dom";

const Modal = ({children, open, onClose, closeDisabled = false, title, closeLabel}) => {
    const dispatch = useDispatch();
    const modalRef = useRef(null);
    const {mode} = useSelector((state) => state.themeMode);
    let bgMode = '';
    switch (mode) {
        case 'positive':
            bgMode = '#547BCD99';
            break;
        case 'negative':
            bgMode = '#0B328699';
            break;
        case 'dark':
            bgMode = '#0B328699';
            break;
        default:
            bgMode = '#d9d9d999';
    }
    const storeOpen = useSelector(state => state.modal.isOpen);
    const controlled = open !== undefined;
    const isOpen = controlled ? open : storeOpen;
    const close = useCallback(() => {
        if (closeDisabled) return;
        if (controlled) onClose();
        else dispatch(openCloseModal({open: false}));
    }, [closeDisabled, controlled, dispatch, onClose]);

    useEffect(() => {
        if (!isOpen) return;
        function handleOutsideClick(event) {
            if (modalRef.current && !modalRef.current.contains(event.target)) {
                close();
            }
        }

        document.addEventListener('mousedown', handleOutsideClick);
        const handleKeys = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                close();
            }
            if (event.key === 'Tab') {
                const fields = [...modalRef.current.querySelectorAll('button, input, textarea, select, a[href]')]
                    .filter((field) => !field.matches(':disabled'));
                const edge = event.shiftKey ? fields.at(0) : fields.at(-1);
                if (document.activeElement === edge || !modalRef.current.contains(document.activeElement)) {
                    event.preventDefault();
                    (event.shiftKey ? fields.at(-1) : fields.at(0))?.focus();
                }
            }
        };
        if (controlled) document.addEventListener('keydown', handleKeys);
        return () => {
            document.removeEventListener('mousedown', handleOutsideClick);
            if (controlled) document.removeEventListener('keydown', handleKeys);
        };
    }, [close, controlled, isOpen]);

    if (!isOpen) return null;

    const content = (
        <div className={`${controlled ? 'shared-modal-overlay' : 'absolute h-full w-full'} flex items-center justify-center z-50`}
             style={{background: bgMode}}>
            <div className={title ? 'shared-modal-panel' : 'relative mx-auto p-5 w-6/12 max-h-90 shadow-lg rounded-md bg-white'}
                 ref={modalRef} role={title ? 'dialog' : undefined} aria-modal={title ? true : undefined}
                 aria-label={title}>
                <button
                    type="button"
                    className={title ? 'shared-modal-close' : 'absolute bg-transparent right-[2%] top-[3.5%] m-3 border-none cursor-pointer'}
                    aria-label={closeLabel}
                    disabled={closeDisabled}
                    onClick={close}
                >
                    <Icon color="#FFFFFF"
                          name="close_modal" size={40}/>

                </button>
                {title && <header className="shared-modal-heading"><h2>{title}</h2></header>}
                <div className={title ? 'shared-modal-body' : 'mt-3 h-[90vh] overflow-y-auto flex flex-col'}>
                    {children}
                </div>
            </div>
        </div>
    );
    return controlled ? createPortal(content, document.body) : content;
};

export default Modal;
