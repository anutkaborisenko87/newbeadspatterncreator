import {useState} from "react";
import {Link, router, usePage} from "@inertiajs/react";
import ReportDialog from "@/Components/ReportDialog.jsx";
import Icon from "@/Components/Icon.jsx";
import {CopyPattern, PatternDownloads} from "@/Components/PatternActions.jsx";
import PatternLikes from "@/Components/PatternLikes.jsx";

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
    const [deleting, setDeleting] = useState(false);
    const [reportOpen, setReportOpen] = useState(false);
    const [sent, setSent] = useState(false);
    const {reportLabels} = usePage().props.galleryCardLabels;
    const editUrl = item.editPrivateCopyUrl ?? item.editPublishedUrl;
    const editLabel = item.editPrivateCopyUrl ? labels.edit_private_copy : labels.edit;

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

    const copyAction = <CopyPattern url={item.copyUrl} label={labels.copy} color={color}/>;

    return (
        <article className={`my-gallery-card galery-item-block-background ${authorGallery ? 'author-gallery-card' : ''} ${publicGallery ? 'public-gallery-card' : ''}`} style={{color}}>
            <div className="my-gallery-details">
                <div className="gallery-card-summary">
                <div className="my-gallery-card-heading">
                    <h2>{item.title}</h2>
                    {item.reportUrl && (publicGallery || authorGallery) && !sent &&
                        <button className="gallery-title-action" type="button" onClick={() => setReportOpen(true)}
                                title={reportLabels.report} aria-label={reportLabels.report}>
                            <Icon name="flag" size={22} color="currentColor" aria-hidden="true"/>
                        </button>}
                    {item.unpublishUrl && <button className="gallery-title-action" type="button" disabled={deleting}
                        title={reportLabels.withdraw} aria-label={reportLabels.withdraw} onClick={() => {
                            if (window.confirm(reportLabels.confirm_withdraw)) router.delete(item.unpublishUrl, {
                                preserveScroll: true, onStart: () => setDeleting(true), onFinish: () => setDeleting(false),
                            });
                        }}>
                        <Icon name="hide_password" size={22} color="currentColor" aria-hidden="true"/>
                    </button>}
                    {authorGallery && copyAction}
                </div>
                {item.description && <p>{item.description}</p>}
                {item.visibilityLabel && <small>{item.visibilityLabel}</small>}
                {sent && <small role="status">{reportLabels.submitted}</small>}
                {reportOpen && <ReportDialog subject={item.title} url={item.reportUrl} labels={reportLabels}
                    onClose={() => setReportOpen(false)} onSent={() => {setReportOpen(false); setSent(true);}}/>}
                </div>
                <div className="my-gallery-card-footer">
                    {item.privateCopyLabel ? <small>{item.privateCopyLabel}</small> : <div className="my-gallery-stats">
                        {publicGallery && item.author.name && <span className="gallery-card-author">
                            <Icon name="person_location" color={color} size={22} aria-hidden="true"/>
                            <Link href={item.author.url}>{item.author.name}</Link>
                        </span>}
                        <span>
                            <Icon name="comments" color={color} size={22} aria-hidden="true"/>
                            {item.comments.link
                                ? <Link href={item.comments.link} aria-label={item.comments.label}>
                                    {authorGallery || publicGallery ? item.comments.label : item.comments.count}
                                </Link>
                                : item.comments.count}
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
            <PatternDownloads item={item} color={color}/>
            <GalleryImage src={item.preview} label={labels.preview} className="my-gallery-preview"/>
        </article>
    );
}
