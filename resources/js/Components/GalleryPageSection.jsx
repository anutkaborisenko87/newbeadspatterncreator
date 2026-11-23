import SelectComponent from "@/Components/SelectComponent";
import SearchInput from "@/Components/SearchInput.jsx";
import {useSelector} from "react-redux";
import Pagination from "@/Components/Pagination.jsx";
import GalleryPatternCard from "@/Components/GalleryPatternCard.jsx";
import Icon from "@/Components/Icon.jsx";
import {updateQuery} from "@/hooks/updateQuery.js";
import {usePage} from "@inertiajs/react";

const GalleryPageSection = ({data}) => {
    const {mode} = useSelector((state) => state.themeMode);
    const iconColor = mode === '' || mode === 'positive' ? '#031945' : (mode === 'negative' ? '#AEC2ED' : '#8FA8DE')
    const {url, props: {ownGallery, authorGallery, likedGallery, galleryCardLabels}} = usePage();
    const gallery = data.gallery_items;
    const publicGallery = !ownGallery && !authorGallery;
    const cardLabels = ownGallery ?? authorGallery ?? {...galleryCardLabels, ...likedGallery};
    const title = ownGallery?.title ?? likedGallery?.title;
    const search = new URLSearchParams(url.split('?')[1]).get('patterns_search') ?? '';
    const handleCategoryChange = (e) => {
        const categoryValue = e.target.value;
        updateQuery('pattern_category_filter', categoryValue, 'page');
    }
    const handleSortByChange = (e) => {
        const sortValue = e.target.value;
        updateQuery('patterns_sort_by', sortValue, 'page');
    }
    const submitSearch = (event) => {
        event.preventDefault();
        updateQuery('patterns_search', new FormData(event.currentTarget).get('patterns_search').trim(), 'page');
    };
    return (
        <>
            {title && <h1 className="my-gallery-width my-gallery-title" style={{color: iconColor}}>
                <Icon name="person_location" color={iconColor} size={22} aria-hidden="true"/>
                {title}
            </h1>}
            <div
                className={`my-gallery-width my-gallery-filters ${publicGallery ? 'public-gallery-filters' : ''}`}>
                <div
                    className="my-gallery-selects">
                    <SelectComponent placeholder={data?.category_select?.category_select_placeholder}
                                     options={data?.category_select?.category_select_items}
                                     onChange={handleCategoryChange}
                                     className="select-galery-component"/>
                    <SelectComponent placeholder={data?.sort_select?.sort_select_placeholder}
                                     onChange={handleSortByChange}
                                     options={data?.sort_select?.sort_select_items}
                                     className="select-galery-component"/>
                </div>

                <SearchInput
                    key={search}
                    name="patterns_search"
                    defaultValue={search}
                    onSubmit={submitSearch}
                    onChange={(event) => {
                        if (search && !event.target.value) updateQuery('patterns_search', '', 'page');
                    }}
                    color={iconColor}
                    iconSize={24}
                    placeholderText={(publicGallery ? galleryCardLabels.search : authorGallery?.search) ?? data?.search_input?.search_input_placeholder}
                    classDivName="search-block-galery position-relative"
                    classInputName="search-input-galery"
                />
            </div>
            <div className="my-gallery-width my-gallery-list">
                {gallery?.data?.map((item) => (
                    <GalleryPatternCard key={item.id} item={item} labels={cardLabels} color={iconColor}
                                        authorGallery={Boolean(authorGallery)} publicGallery={publicGallery}/>
                ))}
                { !gallery?.data?.length && <p style={{color: iconColor}}>{cardLabels.empty}</p>}
            </div>
            {gallery?.last_page > 1 && (
                <div className={(authorGallery || publicGallery) ? 'author-gallery-pagination' : undefined}>
                    <Pagination {...gallery} />
                </div>
            )}
        </>

    );
};

export default GalleryPageSection;
