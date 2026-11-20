import SelectComponent from "@/Components/SelectComponent";
import SearchInput from "@/Components/SearchInput.jsx";
import {useSelector} from "react-redux";
import {useWindowSize} from "react-use";
import Pagination from "@/Components/Pagination.jsx";
import GaleryItem from "@/Components/GaleryItem.jsx";
import GalleryPatternCard from "@/Components/GalleryPatternCard.jsx";
import Icon from "@/Components/Icon.jsx";
import {updateQuery} from "@/hooks/updateQuery.js";
import {usePage} from "@inertiajs/react";

const GalleryPageSection = ({data}) => {
    const {mode} = useSelector((state) => state.themeMode);
    const iconColor = mode === '' || mode === 'positive' ? '#031945' : (mode === 'negative' ? '#AEC2ED' : '#8FA8DE')
    const {width} = useWindowSize();
    const {url, props: {ownGallery, authorGallery}} = usePage();
    const gallery = data.gallery_items;
    const wide = width > 900;
    const cardLabels = ownGallery ?? authorGallery;
    const selectClass = cardLabels ? 'select-galery-component'
        : `select-galery-component w-1/2 ${wide ? 'text-[2em] h-[2em]' : 'text-[1.5em] h-[1.7em]'}`;
    const searchClass = cardLabels ? 'search-input-galery'
        : `search-input-galery ${wide ? 'text-[2em] h-[2em]' : 'text-[1.7em] h-[1.7em]'}`;
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
            {ownGallery && <h1 className="my-gallery-width my-gallery-title" style={{color: iconColor}}>
                <Icon name="person_location" color={iconColor} size={22} aria-hidden="true"/>
                {ownGallery.title}
            </h1>}
            <div
                className={cardLabels ? 'my-gallery-width my-gallery-filters' : `flex justify-center items-center my-[0.5em] w-5/6 ${wide ? 'gap-[1em]' : 'flex-col-reverse gap-[0.5em]'}  mx-auto`}>
                <div
                    className={cardLabels ? 'my-gallery-selects' : `flex justify-center items-center ${wide ? 'w-2/3 gap-[1em]' : 'w-full gap-[0.5em]'}  mx-auto`}>
                    <SelectComponent placeholder={data?.category_select?.category_select_placeholder}
                                     options={data?.category_select?.category_select_items}
                                     onChange={handleCategoryChange}
                                     className={selectClass}/>
                    <SelectComponent placeholder={data?.sort_select?.sort_select_placeholder}
                                     onChange={handleSortByChange}
                                     options={data?.sort_select?.sort_select_items}
                                     className={selectClass}/>
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
                    iconSize={cardLabels ? 24 : (wide ? 30 : 20)}
                    placeholderText={authorGallery?.search ?? data?.search_input?.search_input_placeholder}
                    classDivName={`search-block-galery position-relative ${cardLabels ? '' : (wide ? 'w-2/3' : 'w-full')}`}
                    classInputName={searchClass}
                />
            </div>
            <div className={cardLabels ? 'my-gallery-width my-gallery-list' : 'gallery-pattern-list'}>
                {gallery?.data?.map((item) => cardLabels
                    ? <GalleryPatternCard key={item.id} item={item} labels={cardLabels} color={iconColor}
                                          authorGallery={Boolean(authorGallery)}/>
                    : <GaleryItem key={item.id} item={item}/>)}
                {cardLabels && !gallery?.data?.length && <p style={{color: iconColor}}>{cardLabels.empty}</p>}
            </div>
            {gallery?.last_page > 1 && (
                <div className={authorGallery ? 'author-gallery-pagination' : undefined}>
                    <Pagination {...gallery} />
                </div>
            )}
        </>

    );
};

export default GalleryPageSection;
