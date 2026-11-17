import Icon from "@/Components/Icon.jsx";
import {Link, usePage} from "@inertiajs/react";
import {useSelector} from "react-redux";
import LocaleSettingsProfile from "@/Components/LocaleSettingsProfile.jsx";
import {useWindowSize} from "react-use";

const Footer = () => {
    const {nav_menu, locale} = usePage().props;
    const {width} = useWindowSize();
    const {mode} = useSelector((state) => state.themeMode);
    return (
        <footer className="footer-full">
            <div className='footer mx-full-screen'>
                <div
                    className={'w-1/4 text-footer-text-light-positive dark:text-footer-text-dark-negative negative:text-footer-text-dark-negative'}>
                    <p>Copyright ©</p></div>
                <div className="flex items-center justify-center mx-auto w-1/2 gap-[0.5em]">
                    <Icon name="telegram" size="18" color={mode === '' || mode === 'positive' ? '#0E0448' : '#B9B1EE'}
                          background={mode === '' || mode === 'positive' ? '#B9B1EE' : '#0E0448'}/>
                    <Icon name="facebook" size="18" color={mode === '' || mode === 'positive' ? '#0E0448' : '#B9B1EE'}
                          background={mode === '' || mode === 'positive' ? '#B9B1EE' : '#0E0448'}/>
                    <Icon name="instagram" size="18" color={mode === '' || mode === 'positive' ? '#0E0448' : '#B9B1EE'}
                          background={mode === '' || mode === 'positive' ? '#B9B1EE' : '#0E0448'}/>
                </div>
                {
                    width > 1060 ?
                        <div className="flex items-center justify-items-start mx-auto w-1/2">

                            {
                                nav_menu.map((item, index) => {
                                    return (

                                        <Link
                                            key={item.id}
                                            href={`${locale !== '' ? `/${locale}${item.href}` : item.href}`}
                                            className={`padding-footer-links text-footer-text-light-positive dark:text-footer-text-dark-negative negative:text-footer-text-dark-negative no-underline active:underline ${ index < nav_menu.length - 1 && 'border-r-2' } ${mode === '' || mode === 'positive' ? '#0E0448' : '#B9B1EE'}`}>{item.title}</Link>
                                    )
                                })
                            }
                        </div>
                        : <></>
                }

                <LocaleSettingsProfile classPrefix="footer"/>
            </div>
        </footer>
    );
};

export default Footer;
