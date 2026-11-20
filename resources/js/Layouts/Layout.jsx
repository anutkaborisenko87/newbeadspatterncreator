import Header from "@/Components/Header.jsx";
import Footer from "@/Components/Footer.jsx";
import Navbar from "@/Components/Navbar.jsx";
import {useDispatch, useSelector} from "react-redux";
import {useEffect} from "react";
import {setUser} from "../store/authUserSlice.js";
import Modal from "../Components/Modal.jsx";
import LoginForm from "../Components/LoginForm.jsx";
import ForgotPasswordModal from "../Components/ForgotPasswordModal.jsx";
import NewPasswordModal from "../Components/NewPasswordModal.jsx";
import RegisterForm from "../Components/RegisterForm.jsx";
import {usePage} from "@inertiajs/react";
import FlashAlert from "@/Components/FlashAllert.jsx";
import {openCloseModal} from "@/store/modalSlice.js";
import UpdateProfileForm from "@/Components/UpdateProfileForm.jsx";

const Layout = ({children}) => {
    const props = usePage().props;
    const dispatch = useDispatch();
    const { url } = usePage();
    const {mode} = useSelector((state) => state.themeMode);
    const {isOpen, modalMode} = useSelector((state) => state.modal);
    useEffect(() => {
        const user = props.user;
        dispatch(setUser(user))
    }, [children]);
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        const requestedModal = urlParams.get('modal');
        if (!props.user && requestedModal === 'reset-password') {
            dispatch(openCloseModal({open: true, mode: props.newPasswordModal ? requestedModal : 'forgot-password'}));
        }
        if (!props.user && (requestedModal === 'login' || requestedModal === 'forgot-password')) {
            dispatch(openCloseModal({open: true, mode: requestedModal}));
        }
    }, [url, dispatch, props.user, Boolean(props.newPasswordModal)]);

    return (
        <div className="site-layout">
            {modalMode === 'forgot-password' ? (isOpen && <ForgotPasswordModal />)
                : modalMode === 'reset-password'
                    ? (isOpen && props.newPasswordModal && <NewPasswordModal key={props.newPasswordModal.resetId} />)
                    : <Modal>
                {modalMode === 'login' && <LoginForm />}
                {modalMode === 'register' && <RegisterForm />}
                {modalMode === 'update_profile' && <UpdateProfileForm />}
            </Modal>}
            <Header/>
            <div className={`site-content ${mode}`}>
                <main>
                    <FlashAlert/>
                    <Navbar />
                    {children}
                </main>
                <Footer/>
            </div>
        </div>
    );
};

export default Layout;
