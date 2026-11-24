import {useState} from "react";
import {router, usePage} from "@inertiajs/react";
import {useDispatch} from "react-redux";
import {openCloseModal} from "@/store/modalSlice.js";
import Icon from "@/Components/Icon.jsx";
import PatternDownloadDialog from "@/Components/PatternDownloadDialog.jsx";

export function CopyPattern({url, label, color}) {
    const {user} = usePage().props;
    const dispatch = useDispatch();
    const [copying, setCopying] = useState(false);
    if (!url) return null;
    function copy() {
        if (!user) {
            dispatch(openCloseModal({open: true, mode: 'login'}));
            return;
        }
        router.post(url, {}, {preserveScroll: true, onStart: () => setCopying(true), onFinish: () => setCopying(false)});
    }
    return <div className="my-gallery-actions">
        <button type="button" onClick={copy} disabled={copying} title={label} aria-label={label}>
            <Icon name="copy" color={color} size={24} aria-hidden="true"/>
        </button>
    </div>;
}

export function PatternDownloads({item, color}) {
    const [format, setFormat] = useState(null);
    const legacy = {png: item.pngLink, jpg: item.jpgLink};
    return <div className="my-gallery-downloads">
        {['png', 'jpg'].map(type => (item.download?.[type] || legacy[type]) && (
            <button key={type} type="button" onClick={() => setFormat(type)}>
                <Icon name="download" color={color} size={20} aria-hidden="true"/>{type.toUpperCase()}
            </button>
        ))}
        {format && <PatternDownloadDialog format={format} settings={item.download} legacyUrl={legacy[format]}
                                          onClose={() => setFormat(null)}/>}
    </div>;
}
