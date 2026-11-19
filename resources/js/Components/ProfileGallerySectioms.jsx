import ProfileSectionHeader from "@/Components/ProfileSectionHeader.jsx";
import ProfileGalleryItem from "@/Components/ProfileGalleryItem.jsx";
import Pagination from "@/Components/Pagination.jsx";

const ProfileGallerySectioms = ({data}) => {
    return (
        <div className={'w-5/6 mx-auto flex flex-col justify-center items-center gap-[2em] py-[2em]'}>
            <ProfileSectionHeader data={{ section_slug: data.slug, section_title: data.section_title, sort_select: data.sort_select, empty_data: data.gallery_items?.data.length === 0 }}/>
            <div className={data.slug === 'drafts' ? 'profile-draft-list' : 'flex gap-[1em] justify-start items-start w-full'}>
                {
                    data.gallery_items?.data.map((item) => {
                        return (<ProfileGalleryItem data={item} section_slug={data.slug} per_page={data.gallery_items.per_page}
                                                    labels={data.labels} key={item.id}/>)
                    })
                }
            </div>
            {data.gallery_items.links.length > 3 && <Pagination {...data.gallery_items} />}
        </div>
    );
};

export default ProfileGallerySectioms;
