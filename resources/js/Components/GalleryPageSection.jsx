import SelectComponent from "@/Components/SelectComponent";
import SearchInput from "@/Components/SearchInput.jsx";
import {useSelector} from "react-redux";
import {useWindowSize} from "react-use";
import Pagination from "@/Components/Pagination.jsx";
import GaleryItem from "@/Components/GaleryItem.jsx";
import {updateQuery} from "@/hooks/updateQuery.js";
import {usePage} from "@inertiajs/react";

const GalleryPageSection = ({data}) => {
    const {mode} = useSelector((state) => state.themeMode);
    const iconColor = mode === '' || mode === 'positive' ? '#031945' : (mode === 'negative' ? '#AEC2ED' : '#8FA8DE')
    const {width} = useWindowSize();
    const {url} = usePage();
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
            <div
                className={`flex justify-center items-center my-[0.5em] w-5/6 ${width > 900 ? 'gap-[1em]' : 'flex-col-reverse gap-[0.5em]'}  mx-auto`}>
                <div
                    className={`flex justify-center items-center ${width > 900 ? 'w-2/3 gap-[1em]' : 'w-full gap-[0.5em]'}  mx-auto`}>
                    <SelectComponent placeholder={data?.category_select?.category_select_placeholder}
                                     options={data?.category_select?.category_select_items}
                                     onChange={handleCategoryChange}
                                     className={`select-galery-component w-1/2  ${width > 900 ? 'text-[2em] h-[2em]' : 'text-[1.5em] h-[1.7em]'}`}/>
                    <SelectComponent placeholder={data?.sort_select?.sort_select_placeholder}
                                     onChange={handleSortByChange}
                                     options={data?.sort_select?.sort_select_items}
                                     className={`select-galery-component w-1/2 ${width > 900 ? 'text-[2em] h-[2em]' : 'text-[1.5em] h-[1.7em]'}`}/>
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
                    iconSize={width > 900 ? 30 : 20}
                    placeholderText={data?.search_input?.search_input_placeholder}
                    classDivName={`search-block-galery position-relative  ${width > 900 ? 'w-2/3' : 'w-full'}`}
                    classInputName={`search-input-galery ${width > 900 ? 'text-[2em] h-[2em]' : 'text-[1.7em] h-[1.7em]'}`}
                />
            </div>
            {data.gallery_items?.data?.map((item) => (
                <GaleryItem key={item.id} item={item}/>
            ))}
            {data.gallery_items?.last_page > 1 && <Pagination {...data.gallery_items} />}
        </>

    );
};

export default GalleryPageSection;
