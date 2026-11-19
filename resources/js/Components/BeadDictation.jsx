import {useCallback, useEffect, useMemo, useRef, useState} from 'react';
import {useSelector} from 'react-redux';
import {beadDictationPhrases, describeBeads, orderBeadRows} from '../utils/beadReading.js';

const Swatch = ({color}) => (
    <span aria-hidden="true" className="dictation-swatch" style={{backgroundColor: color}} />
);

export function BeadDictationControls({
    labels, open, onToggle, bottomUp, setBottomUp, leftToRight, setLeftToRight, dictation,
}) {
    const {rows, speechStatus, toggleSpeech, togglePause} = dictation;
    const active = speechStatus === 'playing' || speechStatus === 'paused';
    const paused = speechStatus === 'paused';
    const directionButton = (arrow, label, pressed, change) => (
        <button type="button" title={label} aria-label={label} aria-pressed={pressed} onClick={change}>
            {arrow}
        </button>
    );

    const speechButton = (icon, label, action, disabled = false) => (
        <button type="button" className="dictation-speech-button" title={label} aria-label={label}
                onClick={action} disabled={disabled}>
            <span className={`dictation-speech-icon dictation-speech-icon-${icon}`} aria-hidden="true" />
        </button>
    );

    return (
        <div className="dictation-panel">
            <button type="button" className="dictation-heading" aria-expanded={open} onClick={onToggle}>
                {labels.title}<span aria-hidden="true">{open ? '▾' : '▸'}</span>
            </button>
            {open && (
                <div className="dictation-controls">
                    <div className="dictation-directions" role="group" aria-label={labels.row_order}>
                        {directionButton('↑', labels.bottom_up, bottomUp, () => setBottomUp(true))}
                        {directionButton('↓', labels.top_down, !bottomUp, () => setBottomUp(false))}
                    </div>
                    <div className="dictation-directions" role="group" aria-label={labels.column_order}>
                        {directionButton('→', labels.left_to_right, leftToRight, () => setLeftToRight(true))}
                        {directionButton('←', labels.right_to_left, !leftToRight, () => setLeftToRight(false))}
                    </div>
                    {speechButton(active ? 'stop' : 'play', active ? labels.speech_stop : labels.speech_start,
                        toggleSpeech, rows.length === 0)}
                    {active && speechButton(paused ? 'play' : 'pause', paused ? labels.speech_resume : labels.speech_pause,
                        togglePause)}
                    {speechStatus === 'unavailable' && <p className="dictation-speech-status" role="status">
                        {labels.speech_unavailable}
                    </p>}
                </div>
            )}
        </div>
    );
}

export function useBeadDictation(labels, open, bottomUp, leftToRight) {
    const {drawnCells, gridWidth, gridHeight, cellSize, margin, tool, penColor, fillColor} = useSelector((state) => state.grid);
    const stats = useMemo(() => describeBeads(drawnCells, gridWidth, gridHeight, cellSize, margin),
        [drawnCells, gridWidth, gridHeight, cellSize, margin]);
    const rows = useMemo(() => orderBeadRows(stats.rows, bottomUp, leftToRight), [stats, bottomUp, leftToRight]);
    const colorNumbers = useMemo(() => new Map([...stats.colorCounts.keys()].map((color, index) => [color, index + 1])),
        [stats]);
    const selectedColor = (tool === 'fill' ? fillColor : penColor).toLowerCase();
    const [speechStatus, setSpeechStatus] = useState('idle');
    const activeSpeech = useRef(null);

    const cancelSpeech = useCallback(() => {
        const session = activeSpeech.current;
        activeSpeech.current = null;
        session?.stop();
    }, []);

    useEffect(() => {
        window.speechSynthesis?.getVoices();
    }, []);

    useEffect(() => {
        setSpeechStatus('idle');
        return cancelSpeech;
    }, [open, rows, labels, cancelSpeech]);

    const toggleSpeech = () => {
        if (activeSpeech.current) {
            cancelSpeech();
            setSpeechStatus('idle');
            return;
        }

        const synth = window.speechSynthesis;
        const language = labels.speech_language.toLowerCase().split('-')[0];
        let voice = language !== 'uk' && window.SpeechSynthesisUtterance
            ? synth?.getVoices().find((voice) => voice.lang.toLowerCase().split(/[-_]/)[0] === language)
            : null;
        const phrases = beadDictationPhrases(rows, colorNumbers, labels);
        const audio = new Audio();
        let utterance;
        let paused = false;
        const session = {
            stop() {
                if (utterance) {
                    utterance.onend = null;
                    utterance.onerror = null;
                    synth.cancel();
                    if (paused) synth.resume();
                }
                audio.onended = null;
                audio.onerror = null;
                audio.pause();
                if (audio.hasAttribute('src')) {
                    audio.removeAttribute('src');
                    audio.load();
                }
            },
            pause() {
                paused = true;
                if (utterance) synth.pause();
                else audio.pause();
            },
            resume() {
                paused = false;
                if (utterance) synth.resume();
                else playAudio();
            },
        };
        const finish = (status) => {
            if (activeSpeech.current !== session) return;
            cancelSpeech();
            setSpeechStatus(status);
        };
        const playAudio = () => {
            audio.play().catch((error) => {
                if (error.name !== 'AbortError') finish('unavailable');
            });
        };
        const playClip = ({clip}) => {
            const version = language === 'uk' ? '?voice=recorded-v2' : '';
            audio.src = `/bead-speech/${language}/${clip}${version}`;
            audio.onended = speakNext;
            audio.onerror = () => finish('unavailable');
            if (!paused) playAudio();
        };
        const speakNext = () => {
            if (activeSpeech.current !== session) return;
            const {value: phrase, done} = phrases.next();
            if (done) {
                finish('idle');
                return;
            }
            if (!voice) {
                playClip(phrase);
                return;
            }

            utterance = new window.SpeechSynthesisUtterance(phrase.text);
            utterance.voice = voice;
            utterance.lang = voice.lang;
            utterance.onend = speakNext;
            utterance.onerror = () => {
                if (activeSpeech.current !== session) return;
                if (paused) synth.resume();
                voice = null;
                utterance = null;
                playClip(phrase);
            };
            synth.speak(utterance);
        };

        activeSpeech.current = session;
        setSpeechStatus('playing');
        speakNext();
    };

    const togglePause = () => {
        const session = activeSpeech.current;
        if (!session) return;
        if (speechStatus === 'paused') {
            session.resume();
            setSpeechStatus('playing');
        } else {
            session.pause();
            setSpeechStatus('paused');
        }
    };

    return {stats, rows, colorNumbers, selectedColor, speechStatus, toggleSpeech, togglePause};
}

export default function BeadDictation({labels, bottomUp, dictation}) {
    const {stats, rows, colorNumbers, selectedColor} = dictation;
    return (
        <aside className="dictation-panel dictation-body" aria-label={labels.title}>
            <dl className="dictation-stats">
                <div>
                    <dt>{labels.total}</dt>
                    <dd>{stats.total}</dd>
                </div>
                <div>
                    <dt>{labels.selected}</dt>
                    <dd><Swatch color={selectedColor} /> {stats.colorCounts.get(selectedColor) ?? 0}</dd>
                </div>
            </dl>
            {stats.total > 0 && <ul className="dictation-colors">
                {[...stats.colorCounts].map(([color, count]) => (
                    <li key={color}>
                        <Swatch color={color} /> {labels.color} {colorNumbers.get(color)} <strong>{count}</strong>
                    </li>
                ))}
            </ul>}
            {rows.length > 0 && <div className="dictation-row-scroll" tabIndex={0} key={bottomUp ? 'bottom-up' : 'top-down'}>
                <ol className="dictation-rows">
                    {rows.map(({number, runs}) => (
                        <li key={number}>
                            <strong>{labels.row} {number}</strong>
                            <ul>
                                {runs.map(({column, color, count}, index) => (
                                    <li key={column} aria-label={`${labels.color} ${colorNumbers.get(color)}, ${labels.quantity} ${count}`}>
                                        <Swatch color={color} />
                                        <span>×{count}{index < runs.length - 1 && ','}</span>
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ol>
            </div>}
        </aside>
    );
}
