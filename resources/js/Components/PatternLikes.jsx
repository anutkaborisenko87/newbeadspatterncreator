import {useEffect, useState} from "react";
import {Link, router, usePage} from "@inertiajs/react";
import axios from "axios";
import Icon from "@/Components/Icon.jsx";
import Modal from "@/Components/Modal.jsx";

function LikeList({url, labels, onClose}) {
    const [users, setUsers] = useState(null);
    const [failed, setFailed] = useState(false);

    useEffect(() => {
        const controller = new AbortController();
        axios.get(url, {signal: controller.signal, headers: {Accept: 'application/json'}})
            .then(({data}) => setUsers(data.users))
            .catch(() => { if (!controller.signal.aborted) setFailed(true); });
        return () => controller.abort();
    }, [url]);

    return <Modal open title={labels.list} closeLabel={labels.close} onClose={onClose}>
        {failed ? <p role="alert">{labels.error}</p>
            : !users ? <p role="status">{labels.loading}</p>
            : users.length === 0 ? <p>{labels.empty}</p>
            : <ul className="pattern-like-users">{users.map(user =>
                <li key={user.url}><Link href={user.url} onClick={onClose}>{user.login}</Link></li>
            )}</ul>}
    </Modal>;
}

export default function PatternLikes({likes, color, size = 22}) {
    const {patternLikeLabels: labels} = usePage().props;
    const [showUsers, setShowUsers] = useState(false);
    const [saving, setSaving] = useState(false);
    const content = <>
        <Icon name={likes.liked ? 'liked' : 'likes'} color={color} size={size} aria-hidden="true"/>
        <span>{likes.count}</span>
    </>;

    if (!likes.listUrl && !likes.updateUrl) {
        return <span className="pattern-likes"><span className="sr-only">{labels.count} </span>{content}</span>;
    }

    function activate() {
        if (likes.listUrl) {
            setShowUsers(true);
            return;
        }
        setSaving(true);
        router.put(likes.updateUrl, {liked: !likes.liked}, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
            onSuccess: page => {
                const liked = page.props.data?.sections?.find(section => section.slug === 'liked')?.gallery_items;
                if (liked && liked.current_page > liked.last_page) {
                    router.get(liked.last_page_url, {}, {replace: true, preserveScroll: true});
                }
            },
        });
    }

    const label = likes.listUrl ? labels.list : (likes.liked ? labels.remove : labels.add);
    return <>
        <button type="button" className="pattern-likes" disabled={saving}
                aria-label={`${label} (${likes.count})`} title={label}
                aria-pressed={likes.updateUrl ? likes.liked : undefined}
                aria-haspopup={likes.listUrl ? 'dialog' : undefined} onClick={activate}>{content}</button>
        {showUsers && <LikeList url={likes.listUrl} labels={labels} onClose={() => setShowUsers(false)}/>}
    </>;
}
