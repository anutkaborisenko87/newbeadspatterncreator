import {useSelector} from "react-redux";
import Icon from "@/Components/Icon.jsx";

const AuthorTitleSection = ({data}) => {
    const {mode} = useSelector((state) => state.themeMode);
    let color = '';
    let background = '';
    switch (mode) {
        case 'positive':
            color = '#031945';
            background = `#AEC2ED`;
            break;
        case 'negative':
            color = '#D9D9D9';
            background = `#3663C4`;
            break;
        case 'dark':
            color = '#AEC2ED';
            background = `#1F51BD`;
            break;
        default:
            color = '#031945';
            background = `#D9D9D9`;
            break
    }
    return (
        <div className="author-gallery-title my-gallery-width">
            <h1 style={{color, background}}>
                <Icon name="profile_logo" color={color} size={48} aria-hidden="true"/>
                <span>{data.title.trim()} {data.author_name}</span>
            </h1>
        </div>
    );
};

export default AuthorTitleSection;
