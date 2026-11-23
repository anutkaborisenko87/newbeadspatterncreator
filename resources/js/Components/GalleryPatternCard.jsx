import {useState} from "react";
import {Link, router, usePage} from "@inertiajs/react";
import {useDispatch} from "react-redux";
import Icon from "@/Components/Icon.jsx";
import PatternDownloadDialog from "@/Components/PatternDownloadDialog.jsx";
import PatternLikes from "@/Components/PatternLikes.jsx";
import {openCloseModal} from "@/store/modalSlice.js";

function GalleryImage({src, label, className}) {
    const [failedSrc, setFailedSrc] = useState(null);

    return (
        <div className={className}>
            {src && src !== failedSrc
                ? <img src={src} alt={label} onError={() => setFailedSrc(src)}/>
                : <span>{label}</span>}
        </div>
    );
}

export default function GalleryPatternCard({item, labels, color, authorGallery, publicGallery}) {
    const {user} = usePage().props;
    const dispatch = useDispatch();
    const [deleting, setDeleting] = useState(false);
    const [copying, setCopying] = useState(false);
    const [downloadFormat, setDownloadFormat] = useState(null);
    const legacyDownloads = {png: item.pngLink, jpg: item.jpgLink};
    const editUrl = item.editPrivateCopyUrl ?? item.editPublishedUrl;
    const editLabel = item.editPrivateCopyUrl ? labels.edit_private_copy : labels.edit;

    const copyPattern = () => {
        if (!user) {
            dispatch(openCloseModal({open: true, mode: 'login'}));
            return;
        }

        router.post(item.copyUrl, {}, {
            preserveScroll: true,
            onStart: () => setCopying(true),
            onFinish: () => setCopying(false),
        });
    };

    const deletePattern = () => {
        if (!window.confirm(labels.confirm_delete)) return;

        router.delete(item.deleteUrl, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => setDeleting(false),
            onSuccess: (page) => {
                const gallery = page.props.data.sections.find(({slug}) => slug === 'gallery_page_section')?.gallery_items;
                if (gallery && gallery.current_page > gallery.last_page) {
                    router.get(gallery.last_page_url, {}, {replace: true, preserveScroll: true});
                }
            },
        });
    };

    const copyAction = item.copyUrl && <div className="my-gallery-actions">
        <button type="button" onClick={copyPattern} disabled={copying}
                title={labels.copy} aria-label={labels.copy}>
            <Icon name="copy" color={color} size={24} aria-hidden="true"/>
        </button>
    </div>;

    return (
        <article className={`my-gallery-card galery-item-block-background ${authorGallery ? 'author-gallery-card' : ''} ${publicGallery ? 'public-gallery-card' : ''}`} style={{color}}>
            <div className="my-gallery-details">
                <div className="gallery-card-summary">
                <div className="my-gallery-card-heading">
                    <h2>{item.title}</h2>
                    {authorGallery && copyAction}
                </div>
                {item.description && <p>{item.description}</p>}
                </div>
                <div className="my-gallery-card-footer">
                    {item.privateCopyLabel ? <small>{item.privateCopyLabel}</small> : <div className="my-gallery-stats">
                        {publicGallery && item.author.name && <span className="gallery-card-author">
                            <Icon name="person_location" color={color} size={22} aria-hidden="true"/>
                            <Link href={item.author.url}>{item.author.name}</Link>
                        </span>}
                        <span>
                            <Icon name="comments" color={color} size={22} aria-hidden="true"/>
                            {authorGallery || publicGallery ? item.comments.label : <>
                                <span className="sr-only">{labels.comments} </span>{item.comments.count}
                            </>}
                        </span>
                        <PatternLikes likes={item.likes} color={color}/>
                    </div>}
                    {publicGallery && copyAction}
                    {!authorGallery && !publicGallery && <div className="my-gallery-actions">
                        {item.deleteUrl && <button type="button" onClick={deletePattern} disabled={deleting}
                                                  title={labels.delete} aria-label={labels.delete}>
                            <Icon name="trash" color={color} size={22} aria-hidden="true"/>
                        </button>}
                        {editUrl && <Link href={editUrl} method={item.editPrivateCopyUrl ? 'get' : 'post'} as="button"
                                         title={editLabel} aria-label={editLabel} disabled={deleting}>
                            <Icon name="edit" color={color} size={24} aria-hidden="true"/>
                        </Link>}
                    </div>}
                </div>
            </div>
            <GalleryImage src={item.photo} label={labels.photo} className="my-gallery-photo"/>
            <div className="my-gallery-downloads">
                {['png', 'jpg'].map((format) => (item.download?.[format] || legacyDownloads[format]) && (
                    <button key={format} type="button" onClick={() => setDownloadFormat(format)}>
                        <Icon name="download" color={color} size={20} aria-hidden="true"/>
                        {format.toUpperCase()}
                    </button>
                ))}
            </div>
            <GalleryImage src={item.preview} label={labels.preview} className="my-gallery-preview"/>
            {downloadFormat && <PatternDownloadDialog format={downloadFormat} settings={item.download}
                                                       legacyUrl={legacyDownloads[downloadFormat]}
                                                       onClose={() => setDownloadFormat(null)}/>}
        </article>
    );
}
