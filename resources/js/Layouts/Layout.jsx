import Header from "@/Components/Header.jsx";
import Footer from "@/Components/Footer.jsx";
import Navbar from "@/Components/Navbar.jsx";
import {useDispatch, useSelector} from "react-redux";
import {useEffect} from "react";
import {setUser} from "../store/authUserSlice.js";
import Modal from "../Components/Modal.jsx";
import LoginForm from "../Components/LoginForm.jsx";
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
    const {modalMode} = useSelector((state) => state.modal);
    useEffect(() => {
        const user = props.user;
        dispatch(setUser(user))
    }, [children]);
    useEffect(() => {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('modal') === 'login') {
            dispatch(openCloseModal({ open: true, mode: 'login' }));
        }
    }, [url, dispatch]);

    return (
        <div className="site-layout">
            <Modal>
                {modalMode === 'login' && <LoginForm />}
                {modalMode === 'register' && <RegisterForm />}
                {modalMode === 'update_profile' && <UpdateProfileForm />}
            </Modal>
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
