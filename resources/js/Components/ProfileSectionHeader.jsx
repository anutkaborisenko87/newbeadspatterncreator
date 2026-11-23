import SelectComponent from "@/Components/SelectComponent.jsx";
import {useWindowSize} from "react-use";
import SearchInput from "@/Components/SearchInput.jsx";
import {Link, usePage} from "@inertiajs/react";
import {updateQuery} from "@/hooks/updateQuery.js";

const handleSortByChange = (value, slug) => {
    updateQuery(`${slug}_sort_by`, value, slug === 'liked' ? 'likes_page' : `${slug}_page`);
}
const ProfileSectionHeader = ({data}) => {
    const props = usePage().props;
    const {width} = useWindowSize();
    const galleryUrl = data.url ?? (data.section_slug === 'my_patterns' ? props.routes?.myGallery : null);
    return (
        <div className="mr-auto flex flex-wrap justify-start items-center">
            <h2 className={'profile-main-section'}>
                {galleryUrl
                    ? <Link href={galleryUrl} className="text-inherit">{data.section_title}</Link>
                    : data.section_title}
            </h2>
            {
                data.empty_data ? <SearchInput
                        placeholderText={props.empty_data_input_placeholder.title}
                        type={'text'}
                        iconName={''}
                        classDivName={`position-relative`}
                        classInputName={`select-galery-component ${width > 900 ? 'text-[1.5em] h-[2em]' : 'text-[1em] h-[1.5em]'}`}
                        disabled={true}
                    /> :
                    <SelectComponent placeholder={data?.sort_select?.sort_select_placeholder}
                                     options={data?.sort_select?.sort_select_items}
                                     onChange={(event) => {handleSortByChange(event.target.value, data.section_slug)}}
                                     className={`select-galery-component w-1/2 ${width > 900 ? 'text-[1.5em] h-[2em]' : 'text-[1em] h-[1.5em]'}`}/>
            }
        </div>
    );
};

export default ProfileSectionHeader;
