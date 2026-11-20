import {router, usePage} from "@inertiajs/react";
import Icon from "@/Components/Icon.jsx";
import {useSelector, useStore} from "react-redux";
import {CanvasGrid} from "@/Components/CanvasGrid.jsx";
import WidthHeightsControls from "@/Components/WidthHeightsControls.jsx";
import {redo, setDrawnCells, setEditorView, setTool, undo} from "@/store/gridSlice.js";
import {useDispatch} from "react-redux";
import UsedColors from "@/Components/UsedColors.jsx";
import ColorPicker from "@/Components/ColorPicker.jsx";
import BeadDictation, {BeadDictationControls, useBeadDictation} from "@/Components/BeadDictation.jsx";
import GalleryPublicationDialog from "@/Components/GalleryPublicationDialog.jsx";
import {openCloseModal} from "@/store/modalSlice.js";
import {useLayoutEffect, useRef, useState} from "react";
import {getDrawing, startEditorDraft} from '../utils/editorDraft.js';
import {captureDraftPreview} from '../utils/captureDraftPreview.js';

const EditorWorkspace = ({data, onNewDraft, onPublish, onDiscard, onSaveCopy, archiving, archiveError, canvasVersion = 0, workspaceRef}) => {
    const dispatch = useDispatch();
    const {bead_preview_label, bead_preview_offsets, bead_dictation, galleryPublication, editorPrivateCopy} = usePage().props;
    const {showBeadPreview, offsetDirection, showDictation, bottomUp, leftToRight} = useSelector((state) => state.grid.view);
    const dictation = useBeadDictation(bead_dictation, showDictation, bottomUp, leftToRight);
    const clipboardRef = useRef(null);
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
    const toggleTool = (tool) => {
        dispatch(setTool(tool));
    };
    return (
        <div className={'editor-main-div'}>
            <div className="editor-header">
                <div className="editor-header-tools" inert={archiving ? '' : undefined}>
                    <WidthHeightsControls color={color} data={data} />
                    <div className="flex flex-wrap items-center gap-[1em]">
                        <Icon className={'cursor-pointer'} name={'pencil'} color={color} size={25} title={data.pencil_title}
                              onClick={() => toggleTool("pen")}/>
                        <Icon className={'cursor-pointer'} name={'paint_fill'} color={color} size={25} title={data.fulfill_title}
                              onClick={() => toggleTool("fill")}/>
                        <Icon className={'cursor-pointer'} name={'eraser'} color={color} size={25} title={data.eraser_title}
                              onClick={() => toggleTool("eraser")}/>
                        <Icon className={'cursor-pointer'} name={'undo'} color={color} size={25} title={data.undo_title}
                              onClick={() => dispatch(undo())}/>
                        <Icon className={'cursor-pointer'} name={'redo'} color={color} size={25} title={data.redo_title}
                              onClick={() => dispatch(redo())}/>
                        <Icon className={'cursor-pointer'} name={'trash'} color={color} size={25} title={data.clear_title}
                              onClick={() => dispatch(setDrawnCells({}))}/>
                        <ColorPicker color={color} title={data.colors_title}/>
                        <UsedColors/>
                        <Icon className={'cursor-pointer'} name={'grid'} color={color} size={25}
                              title={data.copyfield_title}
                              onClick={() => toggleTool("selection")}
                        />
                        <button
                            type="button"
                            className="inline-flex cursor-pointer items-center justify-center border-0 bg-transparent p-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                            aria-label={bead_preview_label}
                            aria-pressed={showBeadPreview}
                            title={bead_preview_label}
                            onClick={() => dispatch(setEditorView({showBeadPreview: !showBeadPreview}))}
                        >
                            <Icon
                                name={showBeadPreview ? 'see_password' : 'hide_password'}
                                color={color}
                                size={25}
                                aria-hidden="true"
                            />
                        </button>
                        {showBeadPreview && (
                            <button
                                type="button"
                                className="inline-flex cursor-pointer items-center justify-center border-0 bg-transparent p-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2"
                                aria-label={`${bead_preview_offsets.label}: ${bead_preview_offsets.vertical}`}
                                aria-pressed={offsetDirection === 'vertical'}
                                title={`${bead_preview_offsets.label}: ${bead_preview_offsets[offsetDirection]}`}
                                onClick={() => dispatch(setEditorView({offsetDirection: offsetDirection === 'horizontal' ? 'vertical' : 'horizontal'}))}
                            >
                                <Icon
                                    name="staggered_grid"
                                    color={color}
                                    size={25}
                                    style={{ transform: offsetDirection === 'vertical' ? 'rotate(90deg)' : undefined }}
                                    aria-hidden="true"
                                />
                            </button>
                        )}
                    </div>
                </div>
                <div className="editor-header-aside">
                    {editorPrivateCopy ? (
                        <div className="flex flex-wrap justify-end items-center gap-[1em]" style={{color}}>
                            <span>{editorPrivateCopy.title}</span>
                            <button type="button" disabled={archiving} onClick={onSaveCopy}
                                    className="inline-flex cursor-pointer items-center gap-[0.35em] border-0 bg-transparent p-0 text-inherit disabled:opacity-50">
                                <Icon name="save" color={color} size={25} aria-hidden="true" />
                                {editorPrivateCopy.save}
                            </button>
                        </div>
                    ) : <div className="flex justify-end items-center gap-[1em]">
                        {onDiscard && <button type="button" disabled={archiving} onClick={onDiscard}
                            className="border-0 bg-transparent cursor-pointer p-0"
                            title={galleryPublication.labels.discard} aria-label={galleryPublication.labels.discard}>
                            <Icon name="trash" color={color} size={25} aria-hidden="true" />
                        </button>}
                        <button
                            type="button"
                            className="inline-flex cursor-pointer items-center border-0 bg-transparent p-0 disabled:cursor-default disabled:opacity-50"
                            aria-label={data.new_draft_title}
                            title={data.new_draft_title}
                            disabled={!onNewDraft || archiving}
                            onClick={onNewDraft}
                        >
                            <Icon name="plus" color={color} size={25} aria-hidden="true" />
                        </button>
                        <button type="button"
                                className="inline-flex cursor-pointer items-center border-0 bg-transparent p-0 disabled:cursor-default disabled:opacity-50"
                                title={galleryPublication.labels.heading} aria-label={galleryPublication.labels.heading}
                                disabled={archiving}
                                onClick={onPublish ?? (() => dispatch(openCloseModal({open: true, mode: 'login'})))}>
                            <Icon name="save" color={color} size={25} aria-hidden="true" />
                        </button>
                    </div>}
                    {archiveError && <p role="alert">{archiveError}</p>}
                    <BeadDictationControls
                        labels={bead_dictation}
                        open={showDictation}
                        onToggle={() => dispatch(setEditorView({showDictation: !showDictation}))}
                        bottomUp={bottomUp}
                        setBottomUp={(value) => dispatch(setEditorView({bottomUp: value}))}
                        leftToRight={leftToRight}
                        setLeftToRight={(value) => dispatch(setEditorView({leftToRight: value}))}
                        dictation={dictation}
                    />
                </div>
            </div>
            <div className="editor-workspace" ref={workspaceRef}>
                <div className="editor-canvases" inert={archiving ? '' : undefined}>
                    <div className="flex w-max items-start gap-[1em]" key={canvasVersion}>
                        <CanvasGrid clipboardRef={clipboardRef} />
                        {showBeadPreview && <CanvasGrid offsetDirection={offsetDirection} clipboardRef={clipboardRef} />}
                    </div>
                </div>
                {showDictation && (
                    <BeadDictation
                        labels={bead_dictation}
                        bottomUp={bottomUp}
                        dictation={dictation}
                    />
                )}
            </div>

        </div>
    );
};

function AuthenticatedEditor({data, owner, url, serverDraft, savedDraftId, workingUrl}) {
    const store = useStore();
    const {editor_draft_archive_error, galleryPublication, editorPrivateCopy} = usePage().props;
    const initialDraft = useRef(serverDraft);
    const sessionRef = useRef(null);
    const workspaceRef = useRef(null);
    const [ready, setReady] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const [archiveError, setArchiveError] = useState(null);
    const [canvasVersion, setCanvasVersion] = useState(0);
    const [publishing, setPublishing] = useState(false);

    useLayoutEffect(() => {
        const session = startEditorDraft(store, owner, url, initialDraft.current, savedDraftId,
            () => captureDraftPreview(workspaceRef.current), workingUrl);
        sessionRef.current = session;
        setReady(true);
        return session.stop;
    }, [store, owner, url, savedDraftId, workingUrl]);

    const archive = async () => {
        setArchiveError(null);
        setArchiving(true);
        try {
            const result = await sessionRef.current.archive({saveCopy: Boolean(editorPrivateCopy)});
            const returnUrl = editorPrivateCopy?.returnUrl ?? result;
            if (result && typeof returnUrl === 'string') {
                router.visit(returnUrl, {onFinish: () => setArchiving(false)});
                return;
            }
            if (result) setCanvasVersion((value) => value + 1);
        } catch {
            setArchiveError(editorPrivateCopy?.save_error ?? editor_draft_archive_error);
        }
        setArchiving(false);
    };

    const publicationSnapshot = async () => {
        await sessionRef.current.backupWorkingDraft();
        const drawing = getDrawing(store.getState().grid);
        const version = Math.max((initialDraft.current?.version ?? 0) + 1, Date.now() * 1000 + 999);
        const preview = drawing.gridWidth && drawing.gridHeight
            ? await captureDraftPreview(workspaceRef.current).catch(() => null) : null;
        return {owner, drawing, version, ...preview};
    };

    const discard = galleryPublication?.revision ? () => {
        if (window.confirm(galleryPublication.labels.confirm_discard)) {
            router.delete(galleryPublication.revision.discardUrl);
        }
    } : undefined;

    return ready ? (
        <>
            <EditorWorkspace data={data} onNewDraft={archive} onPublish={() => setPublishing(true)} onDiscard={discard}
                             onSaveCopy={archive} archiving={archiving}
                             archiveError={archiveError} canvasVersion={canvasVersion} workspaceRef={workspaceRef} />
            {!editorPrivateCopy && publishing && <GalleryPublicationDialog config={galleryPublication} getSnapshot={publicationSnapshot}
                                                     onPublished={(workspace) => sessionRef.current.finishPublication(workspace)}
                                                     onClose={() => setPublishing(false)} />}
        </>
    ) : null;
}

export default function EditorPageSection({data}) {
    const {user, editorDraft, editorDraftUrl, editorSavedDraftId, editorWorkingDraftUrl} = usePage().props;

    return user && editorDraftUrl ? (
        <AuthenticatedEditor
            key={`${user.id}:${editorDraftUrl}`}
            data={data}
            owner={user.id}
            url={editorDraftUrl}
            serverDraft={editorDraft}
            savedDraftId={editorSavedDraftId}
            workingUrl={editorWorkingDraftUrl}
        />
    ) : <EditorWorkspace data={data} />;
}
