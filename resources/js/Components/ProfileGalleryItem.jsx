import {useSelector} from "react-redux";
import Icon from "@/Components/Icon.jsx";
import {Link, router, usePage} from "@inertiajs/react";
import {useState} from "react";

function DraftCard({data, labels, color}) {
    const {props} = usePage();
    const [failedPreview, setFailedPreview] = useState(null);
    const [deleting, setDeleting] = useState(false);
    const formatter = new Intl.DateTimeFormat(props.current_lang?.slug || props.locale || undefined, {
        dateStyle: 'short', timeStyle: 'short',
    });
    const renderDate = (date, label) => date && (
        <time dateTime={date} title={label}>{formatter.format(new Date(date))}</time>
    );

    const deleteDraft = () => {
        if (!window.confirm(labels.confirm_delete)) return;
        router.delete(data.deleteUrl, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
            onSuccess: (page) => {
                const drafts = page.props.data.sections.find((section) => section.slug === 'drafts')?.gallery_items;
                if (drafts?.data.length === 0 && drafts.prev_page_url) {
                    router.get(drafts.prev_page_url, {}, {replace: true, preserveScroll: true});
                }
            },
        });
    };

    return (
        <article className="galery-item-block-background profile-draft-card" style={{color}}>
            <div className="profile-draft-preview">
                {data.preview && failedPreview !== data.preview
                    ? <img src={data.preview} alt={data.title || labels.preview} onError={() => setFailedPreview(data.preview)} />
                    : <span>{labels.preview}</span>}
            </div>
            {data.deleteUrl && <div className="profile-draft-actions">
                <Link href={data.link} title={labels.edit} aria-label={labels.edit}>
                    <Icon name="edit" color={color} size={32} aria-hidden="true" />
                </Link>
                <button type="button" onClick={deleteDraft} disabled={deleting} title={labels.delete} aria-label={labels.delete}>
                    <Icon name="trash" color={color} size={28} aria-hidden="true" />
                </button>
            </div>}
            <footer className="profile-draft-dates">
                {data.updatedAt !== data.createdAt && renderDate(data.updatedAt, labels.updated)}
                {renderDate(data.createdAt, labels.created)}
            </footer>
        </article>
    );
}

const ProfileGalleryItem = ({data, section_slug, per_page, labels}) => {
    const {mode} = useSelector((state) => state.themeMode);
    let color = '';
    switch (mode) {
        case 'positive':
            color = '#031945';
            break;
        case 'negative':
            color = '#D9D9D9';
            break;
        case 'dark':
            color = '#AEC2ED';
            break;
        default:
            color = '#031945';
    }
    if (section_slug === 'drafts') return <DraftCard data={data} labels={labels} color={color} />;

    const renderFooter = () => {
        switch (section_slug) {
            case 'my_patterns':
                return (
                    <div
                        className={`flex justify-start items-start gap-[2em]`}>
                        <h5 className={`text-[1.5em]`} style={{color: color}}>{data.title}</h5>

                        <div className={'flex items-center gap-[0.5em]'}>
                            <Icon name={'comments'} size={24} color={color}/>
                            <Link className={`text-[1.3em]`} href={data.comments.link}
                                  style={{color: color}}>{data.comments.count}</Link>
                        </div>
                        <div className={'flex items-center gap-[0.5em]'}>
                            <Icon className={'cursor-pointer'} name={'likes'} size={24} color={color}/>
                            <p className={`text-[1.3em]`}
                               style={{color: color}}>{data.likes.count}</p>
                        </div>
                    </div>
                );
            default:
                return (
                    <div
                        className={`flex flex-col justify-start items-start gap-[2em]`}>
                        <h4 className={`text-[2em]`} style={{color: color}}>{data.title}</h4>
                        <div
                            className={'flex items-center justify-between gap-[1em]'}>
                            <div className={'flex items-center gap-[0.5em]'}>
                                <Icon name={'person_location'} size={24} color={color}/>
                                {data.author.url !== null ?
                                    <Link className={`text-[1.3em]`} href={data.author.url}
                                          style={{color: color}}>{data.author.name}</Link> :
                                    <h4 className={`text-[1.3em]`}
                                        style={{color: color}}>{data.author.name}</h4>
                                }
                            </div>
                            <div className={'flex items-center gap-[0.5em]'}>
                                <Icon name={'comments'} size={24} color={color}/>
                                <Link className={`text-[1.3em]`} href={data.comments.link}
                                      style={{color: color}}>{data.comments.count}</Link>
                            </div>
                            <div className={'flex items-center gap-[0.5em]'}>
                                {data.likes.liked ?
                                    <Icon className={'cursor-pointer'} name={'liked'} size={24} color={color}/> :
                                    <Icon className={'cursor-pointer'} name={'likes'} size={24} color={color}/>}
                                <p className={`text-[1.3em]`}
                                   style={{color: color}}>{data.likes.count}</p>
                            </div>
                        </div>
                    </div>
                );
        }
    }
    const draftLink = section_slug === 'drafts' && data.link;
    const Card = draftLink ? Link : 'div';

    return (
        <Card
            {...(draftLink ? {href: draftLink} : {})}
            className={`galery-item-block-background rounded-[15px] p-[1em] w-1/${per_page}`}>
            {draftLink || data.link === null ?
                <div
                    className={`flex justify-center items-start w-[90%] my-[1em]`}>
                    <img src={data.preview} alt={data.title} className={`w-[90%] h-[10em] ${draftLink ? 'object-contain bg-white' : ''}`}/>
                </div> :
                <Link href={data.link}
                    className={`flex justify-center items-start w-[90%] my-[1em]`}>
                    <img src={data.preview} alt={data.title} className={'w-[90%] h-[10em]'}/>
                </Link>
            }
            {renderFooter()}

        </Card>
    );
};

export default ProfileGalleryItem;
