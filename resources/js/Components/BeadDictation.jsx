import {useMemo} from 'react';
import {useSelector} from 'react-redux';
import {describeBeads, orderBeadRows} from '../utils/beadReading.js';

const Swatch = ({color}) => (
    <span aria-hidden="true" className="dictation-swatch" style={{backgroundColor: color}} />
);

export function BeadDictationControls({labels, open, onToggle, bottomUp, setBottomUp, leftToRight, setLeftToRight}) {
    const directionButton = (arrow, label, pressed, change) => (
        <button type="button" title={label} aria-label={label} aria-pressed={pressed} onClick={change}>
            {arrow}
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
                </div>
            )}
        </div>
    );
}

export default function BeadDictation({labels, bottomUp, leftToRight}) {
    const {drawnCells, gridWidth, gridHeight, cellSize, margin, tool, penColor, fillColor} = useSelector((state) => state.grid);
    const stats = useMemo(() => describeBeads(drawnCells, gridWidth, gridHeight, cellSize, margin),
        [drawnCells, gridWidth, gridHeight, cellSize, margin]);
    const rows = useMemo(() => orderBeadRows(stats.rows, bottomUp, leftToRight), [stats, bottomUp, leftToRight]);
    const colorNumbers = new Map([...stats.colorCounts.keys()].map((color, index) => [color, index + 1]));
    const selectedColor = (tool === 'fill' ? fillColor : penColor).toLowerCase();

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
            {rows.length > 0 && <ol className="dictation-rows" key={bottomUp ? 'bottom-up' : 'top-down'}>
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
            </ol>}
        </aside>
    );
}
