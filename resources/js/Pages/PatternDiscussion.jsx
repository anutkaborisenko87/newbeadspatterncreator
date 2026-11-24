import {useEffect, useMemo, useRef, useState} from 'react';
import {Head, Link, usePage} from '@inertiajs/react';
import {useDispatch} from 'react-redux';
import axios from 'axios';
import Pusher from 'pusher-js';
import Icon from '@/Components/Icon.jsx';
import PatternLikes from '@/Components/PatternLikes.jsx';
import {CopyPattern, PatternDownloads} from '@/Components/PatternActions.jsx';
import {openCloseModal} from '@/store/modalSlice.js';

function mergeComments(previous, incoming) {
    const entries = new Map(previous.map(entry => [entry.id, entry]));
    for (const entry of incoming) entries.set(entry.id, entry);
    return [...entries.values()].sort((a, b) => a.id - b.id);
}

function updateEntry(comments, updated) {
    return comments.map(entry => entry.id === updated.id ? updated : entry);
}

function deleteEntry(comments, id) {
    // The feed and mergeComments keep parents before their replies (ascending ID).
    const deleted = new Set([id]);
    return comments.filter(entry => {
        if (deleted.has(entry.parent_id)) deleted.add(entry.id);
        return !deleted.has(entry.id);
    });
}

function threadOrder(comments, expanded) {
    const children = new Map();
    const roots = [];
    for (const entry of comments) {
        children.set(entry.id, []);
    }
    // The stack pops the newest sibling first, even when older records were imported later.
    const chronological = [...comments].sort((a, b) =>
        (a.created_at && b.created_at ? a.created_at.localeCompare(b.created_at) : 0) || a.id - b.id);
    for (const entry of chronological) {
        (children.get(entry.parent_id) ?? roots).push(entry);
    }
    const ordered = [];
    const stack = roots.map(entry => ({entry, parent: null, depth: 0}));
    while (stack.length) {
        const item = stack.pop();
        const {entry, depth} = item;
        const replies = children.get(entry.id);
        ordered.push({...item, replyCount: replies.length});
        if (expanded.has(entry.id)) {
            for (const child of replies) stack.push({entry: child, parent: entry, depth: depth + 1});
        }
    }
    return ordered;
}

function CommentActions({entry, canEdit, url, labels, onEdit, onDelete}) {
    const [editing, setEditing] = useState(false);
    const [text, setText] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    async function save(remove) {
        if (busy || (!remove && !canEdit) || (remove && !window.confirm(labels.confirm_delete))) return;
        setBusy(true);
        setError('');
        try {
            if (remove) {
                await axios.delete(url);
                onDelete(entry.id);
            } else {
                const {data} = await axios.patch(url, {comment: text.trim()});
                onEdit(data.comment);
                setEditing(false);
            }
        } catch (failure) {
            setError(failure.response?.data?.errors?.comment?.[0] ?? labels.change_failed);
        } finally {
            setBusy(false);
        }
    }
    return <div className="discussion-owner-actions">
        {editing ? <form onSubmit={event => {event.preventDefault(); save(false);}}>
            <textarea autoFocus aria-label={labels.edit} value={text} maxLength={4000}
                      onChange={event => setText(event.target.value)} disabled={busy} readOnly={!canEdit}/>
            {!canEdit && <p role="status">{labels.edit_locked}</p>}
            <button disabled={busy || !canEdit || !text.trim()}>{labels.save}</button>
            <button type="button" disabled={busy} onClick={() => {setEditing(false); setError('');}}>{labels.cancel_edit}</button>
        </form> : <>
            {canEdit && <button type="button" disabled={busy} title={labels.edit} aria-label={labels.edit}
                    onClick={() => {setText(entry.text); setEditing(true); setError('');}}>
                <Icon name="edit" color="currentColor" size={17}/>
            </button>}
            <button type="button" disabled={busy} title={labels.remove} aria-label={labels.remove} onClick={() => save(true)}>
                <Icon name="trash" color="currentColor" size={17}/>
            </button>
        </>}
        {error && <p role="alert">{error}</p>}
    </div>;
}

function Discussion({pattern, feedUrl, initialComments, diagramUrl, labels, socketKey, channel}) {
    const {user, galleryCardLabels} = usePage().props;
    const dispatch = useDispatch();
    const [comments, setComments] = useState(initialComments);
    const [draft, setDraft] = useState('');
    const [replyId, setReplyId] = useState(null);
    const reply = comments.find(entry => entry.id === replyId);
    const [expanded, setExpanded] = useState(() => new Set());
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const [syncError, setSyncError] = useState(false);
    const [diagramFailed, setDiagramFailed] = useState(false);
    const field = useRef(null);
    const ordered = useMemo(() => threadOrder(comments, expanded), [comments, expanded]);
    const image = diagramFailed ? pattern.preview : diagramUrl ?? pattern.preview;

    function toggleReplies(id) {
        setExpanded(previous => {
            const next = new Set(previous);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    useEffect(() => {
        const match = /^#comment-(\d+)$/.exec(location.hash);
        if (!match) return;
        const entries = new Map(initialComments.map(entry => [entry.id, entry]));
        let target = entries.get(Number(match[1]));
        const ancestors = new Set();
        while (target?.parent_id && !ancestors.has(target.parent_id)) {
            ancestors.add(target.parent_id);
            target = entries.get(target.parent_id);
        }
        setExpanded(ancestors);
        const frame = requestAnimationFrame(() => document.getElementById(match[0].slice(1))?.scrollIntoView({block: 'nearest'}));
        return () => cancelAnimationFrame(frame);
    }, [initialComments]);

    useEffect(() => {
        const controller = new AbortController();
        if (!socketKey) {
            setSyncError(true);
            return;
        }
        const port = Number(location.port) || (location.protocol === 'https:' ? 443 : 80);
        const socket = new Pusher(socketKey, {
            cluster: 'mt1', wsHost: location.hostname, wsPort: port, wssPort: port,
            forceTLS: location.protocol === 'https:', enabledTransports: ['ws', 'wss'], enableStats: false,
        });
        const subscription = socket.subscribe(channel);
        let retryTimer;
        let synchronization = 0;
        let pendingChanges = null;
        async function catchUp(attempt = 0) {
            const current = ++synchronization;
            pendingChanges = [];
            clearTimeout(retryTimer);
            try {
                const {data} = await axios.get(feedUrl, {signal: controller.signal});
                if (controller.signal.aborted || current !== synchronization) return;
                setComments(pendingChanges.reduce((list, change) => change(list), data.comments));
                pendingChanges = null;
                setSyncError(false);
            } catch {
                if (controller.signal.aborted || current !== synchronization) return;
                setSyncError(true);
                retryTimer = setTimeout(() => catchUp(attempt + 1), Math.min(60000, 5000 * 2 ** attempt));
            }
        }
        function receive(change) {
            pendingChanges?.push(change);
            setComments(change);
        }
        subscription.bind('comment.created', ({comment}) => receive(list => mergeComments(list, [comment])));
        subscription.bind('comment.updated', ({comment}) => receive(list => updateEntry(list, comment)));
        subscription.bind('comment.deleted', ({id}) => receive(list => deleteEntry(list, id)));
        // Subscribe before reconciling so comments sent during the handshake are included.
        subscription.bind('pusher:subscription_succeeded', () => catchUp());
        const connectionChanged = ({previous, current}) => {
            if (previous === 'connected' || current === 'unavailable' || current === 'failed') {
                ++synchronization;
                clearTimeout(retryTimer);
                setSyncError(true);
            }
        };
        socket.connection.bind('state_change', connectionChanged);
        return () => {
            controller.abort();
            clearTimeout(retryTimer);
            subscription.unbind_all();
            socket.connection.unbind('state_change', connectionChanged);
            socket.disconnect();
        };
    }, [feedUrl, socketKey, channel]);

    async function submit(event) {
        event.preventDefault();
        if (!draft.trim() || sending) return;
        setSending(true);
        setError('');
        try {
            const {data} = await axios.post(pattern.comments.link, {comment: draft.trim(), parent_id: reply?.id});
            setComments(previous => mergeComments(previous, [data.comment]));
            setDraft('');
            if (reply) setExpanded(previous => new Set([...previous, reply.id]));
            setReplyId(null);
        } catch (failure) {
            setError(failure.response?.data?.errors?.parent_id?.[0] ??
                failure.response?.data?.errors?.comment?.[0] ?? labels.failed);
        } finally {
            setSending(false);
        }
    }

    return <div className="discussion-page">
        <Head title={pattern.title ?? labels.title}/>
        <section className="discussion-panel galery-item-block-background">
            <div className="discussion-pattern">
                <header><h1>{pattern.title}</h1>{pattern.description && <p>{pattern.description}</p>}</header>
                <div className="discussion-canvas">
                    {image ? <a href={image} target="_blank" rel="noreferrer">
                        <img src={image} alt={pattern.title || galleryCardLabels.preview}
                             onError={() => setDiagramFailed(true)}/>
                    </a> : <span>{galleryCardLabels.preview}</span>}
                </div>
                <div className="discussion-actions">
                    <PatternLikes likes={pattern.likes} color="currentColor"/>
                    <PatternDownloads item={pattern} color="currentColor"/>
                    <CopyPattern url={pattern.copyUrl} label={labels.copy} color="currentColor"/>
                    <Link href={pattern.author.url}><Icon name="person_location" color="currentColor" size={22}/>{pattern.author.name}</Link>
                </div>
            </div>
            <section className="discussion-thread" aria-label={labels.title}>
                <h2><Icon name="comments" color="currentColor" size={22}/>{labels.title} ({comments.length})</h2>
                {syncError && <p role="status">{labels.reconnecting}</p>}
                {comments.length === 0 && <p>{labels.empty}</p>}
                <ol>{ordered.map(({entry, parent, depth, replyCount}) => <li key={entry.id} id={`comment-${entry.id}`}
                    className={parent ? 'discussion-reply' : undefined} style={{'--reply-depth': Math.min(depth, 4)}}>
                    <div className="discussion-message">
                        <Icon name="profile_logo" color="currentColor" size={22}/>
                        <div className="discussion-message-body">
                            {parent && <a className="discussion-parent" href={`#comment-${parent.id}`}>
                                <span>{labels.reply_to} <strong>{parent.login}</strong></span>
                                <span className="discussion-excerpt">{parent.text}</span>
                            </a>}
                            <p><strong>{entry.login}</strong> {entry.text}</p>
                            {user && Number(entry.author_id) === Number(user.id) && <CommentActions
                                entry={entry} canEdit={replyCount === 0 && !entry.has_hidden_replies}
                                labels={labels} url={`${pattern.comments.link}/${entry.id}`}
                                onEdit={updated => setComments(previous => updateEntry(previous, updated))}
                                onDelete={id => setComments(previous => deleteEntry(previous, id))}/>}
                            {replyCount > 0 && <button type="button" className="discussion-toggle"
                                aria-expanded={expanded.has(entry.id)} onClick={() => toggleReplies(entry.id)}>
                                {expanded.has(entry.id) ? labels.hide_replies : labels.show_replies} ({replyCount})
                            </button>}
                        </div>
                    </div>
                    {user && <button type="button" title={labels.reply} aria-label={`${labels.reply} · ${entry.login}`}
                                     onClick={() => {setReplyId(entry.id); field.current?.focus();}}>
                        <Icon name="undo_arrow" color="currentColor" size={19}/>
                    </button>}
                </li>)}</ol>
            </section>
        </section>
        {user ? <form className="discussion-compose galery-item-block-background" onSubmit={submit}>
            <div className="discussion-compose-header">
                <label htmlFor="discussion-text"><Icon name="profile_logo" color="currentColor" size={24}/>{user.login}</label>
                {reply && <div className="discussion-quote">
                    <span>{labels.reply}: <strong>{reply.login}</strong> · {reply.text}</span>
                    <button type="button" onClick={() => setReplyId(null)} aria-label={labels.cancel}>×</button>
                </div>}
                <button className="discussion-send" type="submit" disabled={!draft.trim() || sending}
                        title={labels.send} aria-label={labels.send}>➤</button>
            </div>
            <textarea id="discussion-text" ref={field} maxLength={4000} required disabled={sending}
                      placeholder={labels.placeholder} value={draft} onChange={event => setDraft(event.target.value)}/>
            {error && <p role="alert">{error}</p>}
        </form> : <div className="discussion-compose galery-item-block-background">
            <button type="button" className="discussion-login" onClick={() => dispatch(openCloseModal({open: true, mode: 'login'}))}>
                {labels.sign_in}
            </button>
        </div>}
    </div>;
}

export default function PatternDiscussion(props) {
    return <Discussion key={props.pattern.id} {...props}/>;
}
