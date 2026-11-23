import {usePage} from "@inertiajs/react";
import {useDispatch} from "react-redux";
import Icon from "@/Components/Icon.jsx";
import {openCloseModal} from "@/store/modalSlice.js";

const ProfileDataSection = ({data}) => {
    const {update_profile_popup_title} = usePage().props;
    const dispatch = useDispatch();
    return (
        <div className="profile-main-section profile-details w-5/6 mx-auto">
            <Icon name="profile_logo" className="profile-avatar" color="currentColor" size={80} aria-hidden="true"/>
            <h4 className="profile-name">{data.name_title} {data.user_name}</h4>
            <p className="profile-email">{data.email_title} {data.user_email}</p>
            <h4 className="profile-login">{data.login_title} {data.user_login}</h4>
            <button type="button" className="profile-edit" aria-label={update_profile_popup_title.title}
                    onClick={() => dispatch(openCloseModal({open: true, mode: 'update_profile'}))}>
                <Icon name="edit" color="currentColor" size={36} aria-hidden="true"/>
            </button>
        </div>
    );
};

export default ProfileDataSection;
