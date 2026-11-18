import {usePage} from "@inertiajs/react";
import Icon from "@/Components/Icon.jsx";
import {useSelector, useStore} from "react-redux";
import {CanvasGrid} from "@/Components/CanvasGrid.jsx";
import WidthHeightsControls from "@/Components/WidthHeightsControls.jsx";
import {redo, setDrawnCells, setTool, undo} from "@/store/gridSlice.js";
import {useDispatch} from "react-redux";
import UsedColors from "@/Components/UsedColors.jsx";
import ColorPicker from "@/Components/ColorPicker.jsx";
import BeadDictation, {BeadDictationControls} from "@/Components/BeadDictation.jsx";
import {useLayoutEffect, useRef, useState} from "react";
import {startEditorDraft} from '../utils/editorDraft.js';
import {captureDraftPreview} from '../utils/captureDraftPreview.js';

const EditorWorkspace = ({data, onNewDraft, archiving, archiveError, canvasVersion = 0, workspaceRef}) => {
    const dispatch = useDispatch();
    const {bead_preview_label, bead_preview_offsets, bead_dictation} = usePage().props;
    const [showBeadPreview, setShowBeadPreview] = useState(true);
    const [offsetDirection, setOffsetDirection] = useState('horizontal');
    const [showDictation, setShowDictation] = useState(true);
    const [bottomUp, setBottomUp] = useState(true);
    const [leftToRight, setLeftToRight] = useState(true);
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
                            onClick={() => setShowBeadPreview((visible) => !visible)}
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
                                onClick={() => setOffsetDirection((direction) => direction === 'horizontal' ? 'vertical' : 'horizontal')}
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
                    <div className="flex justify-end items-center gap-[1em]">
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
                        <Icon className="cursor-pointer" name="save" color={color} size={25}
                              title={data.save_draft_title}/>
                    </div>
                    {archiveError && <p role="alert">{archiveError}</p>}
                    <BeadDictationControls
                        labels={bead_dictation}
                        open={showDictation}
                        onToggle={() => setShowDictation((visible) => !visible)}
                        bottomUp={bottomUp}
                        setBottomUp={setBottomUp}
                        leftToRight={leftToRight}
                        setLeftToRight={setLeftToRight}
                    />
                </div>
            </div>
            <div className="editor-workspace" ref={workspaceRef}>
                <div className="max-w-full overflow-auto" inert={archiving ? '' : undefined}>
                    <div className="flex w-max items-start gap-[1em]" key={canvasVersion}>
                        <CanvasGrid clipboardRef={clipboardRef} />
                        {showBeadPreview && <CanvasGrid offsetDirection={offsetDirection} clipboardRef={clipboardRef} />}
                    </div>
                </div>
                {showDictation && (
                    <BeadDictation
                        labels={bead_dictation}
                        bottomUp={bottomUp}
                        leftToRight={leftToRight}
                    />
                )}
            </div>

        </div>
    );
};

function AuthenticatedEditor({data, owner, url, serverDraft, savedDraftId}) {
    const store = useStore();
    const {editor_draft_archive_error} = usePage().props;
    const initialDraft = useRef(serverDraft);
    const sessionRef = useRef(null);
    const workspaceRef = useRef(null);
    const [ready, setReady] = useState(false);
    const [archiving, setArchiving] = useState(false);
    const [archiveError, setArchiveError] = useState(null);
    const [canvasVersion, setCanvasVersion] = useState(0);

    useLayoutEffect(() => {
        const session = startEditorDraft(store, owner, url, initialDraft.current, savedDraftId,
            () => captureDraftPreview(workspaceRef.current));
        sessionRef.current = session;
        setReady(true);
        return session.stop;
    }, [store, owner, url, savedDraftId]);

    const archive = async () => {
        setArchiveError(null);
        setArchiving(true);
        try {
            if (await sessionRef.current.archive()) setCanvasVersion((value) => value + 1);
        } catch {
            setArchiveError(editor_draft_archive_error);
        } finally {
            setArchiving(false);
        }
    };

    return ready ? (
        <EditorWorkspace data={data} onNewDraft={savedDraftId == null ? archive : undefined} archiving={archiving}
                         archiveError={archiveError} canvasVersion={canvasVersion} workspaceRef={workspaceRef} />
    ) : null;
}

export default function EditorPageSection({data}) {
    const {user, editorDraft, editorDraftUrl, editorSavedDraftId} = usePage().props;

    return user && editorDraftUrl ? (
        <AuthenticatedEditor
            key={`${user.id}:${editorDraftUrl}`}
            data={data}
            owner={user.id}
            url={editorDraftUrl}
            serverDraft={editorDraft}
            savedDraftId={editorSavedDraftId}
        />
    ) : <EditorWorkspace data={data} />;
}
