import React from 'react';
import {Head, usePage} from "@inertiajs/react";
import HomePageSection from "@/Components/HomePageSection.jsx";
import GalleryPageSection from "@/Components/GalleryPageSection.jsx";
import AboutUsPageSection from "@/Components/AboutUsPageSection.jsx";
import EditorPageSection from "@/Components/EditorPageSection.jsx";
import AuthorTitleSection from "@/Components/AuthorTitleSection.jsx";
import ProfileBreadcrumbs from "@/Components/ProfileBreadcrumbs.jsx";
import ProfileDataSection from "@/Components/ProfileDataSection.jsx";
import ProfileGallerySectioms from "@/Components/ProfileGallerySectioms.jsx";
import MyReports from "@/Components/MyReports.jsx";

const Page = () => {
    const props = usePage().props;
    const data = props.data ?? {};
    const sections = data.sections ?? [];
    return (
        <div>
            <Head title={data.title ?? ''}></Head>
            {sections.map((item) => {
                console.log('SECTION ITEM', item);
                return (
                    <section key={item.slug}>
                        {item.slug === 'my_patterns' && <ProfileGallerySectioms data={item}/>}
                        {item.slug === 'drafts' && <ProfileGallerySectioms data={item}/>}
                        {item.slug === 'liked' && <ProfileGallerySectioms data={item}/>}
                        {item.slug === 'profile_title_section' && <ProfileBreadcrumbs data={item}/>}
                        {item.slug === 'profile_user_section' && <ProfileDataSection data={item}/>}
                        {item.slug === 'my_reports' && <MyReports {...item}/>}
                        {item.slug === 'home_page_section' && <HomePageSection data={item}/>}
                        {item.slug === 'gallery_author_section' && <AuthorTitleSection data={item}/>}
                        {item.slug === 'gallery_page_section' && <GalleryPageSection data={item}/>}
                        {item.slug === 'aboutus_page_section' && <AboutUsPageSection data={item}/>}
                        {item.slug === 'editor_page_section' && <EditorPageSection data={item}/>}
                    </section>

            )
            })}
        </div>
    );
};

export default Page;
