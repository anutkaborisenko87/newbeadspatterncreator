import ProfileSectionHeader from "@/Components/ProfileSectionHeader.jsx";
import ProfileGalleryItem from "@/Components/ProfileGalleryItem.jsx";
import Pagination from "@/Components/Pagination.jsx";

const ProfileGallerySectioms = ({data}) => {
    const gallery = data.gallery_items;
    return (
        <div className={'w-5/6 mx-auto flex flex-col justify-center items-center gap-[2em] py-[2em]'}>
            <ProfileSectionHeader data={{ section_slug: data.slug, section_title: data.section_title, sort_select: data.sort_select, empty_data: data.gallery_items?.data.length === 0 }}/>
            <div className="profile-gallery-grid">
                {
                    gallery?.data.map((item) => {
                        return (<ProfileGalleryItem data={item} section_slug={data.slug}
                                                    labels={data.labels} key={item.id}/>)
                    })
                }
            </div>
            {gallery?.last_page > 1 && <Pagination {...gallery} preserveScroll />}
        </div>
    );
};

export default ProfileGallerySectioms;
