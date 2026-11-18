import {useEffect, useState} from "react";
import { useDispatch, useSelector } from "react-redux";
import { useCanvasGrid } from "../hooks/useCanvasGrid";
import { drawGrid, drawVerticalGrid, getHalfCellShift, getHorizontalPosition } from "../utils/drawGrid";
import { addCell, commitDrawing, setDrawnCells } from "../store/gridSlice.js";
import { floodFill } from "../utils/floodFill";
import { copyCells, pasteCells } from "../utils/selectionClipboard.js";

export const CanvasGrid = ({ offsetDirection = null, clipboardRef }) => {
    const dispatch = useDispatch();
    const {
        gridWidth,
        gridHeight,
        cellSize,
        penColor,
        drawnCells,
        tool,
        fillColor,
        margin,
    } = useSelector((state) => state.grid);
    const horizontal = offsetDirection === "horizontal";
    const vertical = offsetDirection === "vertical";
    const extraWidth = horizontal ? cellSize / 2 : 0;
    const extraHeight = vertical && gridWidth > cellSize ? cellSize / 2 : 0;
    const canvasRef = useCanvasGrid(gridWidth + extraWidth, gridHeight + extraHeight, margin);
    const [isDrawing, setIsDrawing] = useState(false);
    const [isSelecting, setIsSelecting] = useState(false);
    const [selectionStart, setSelectionStart] = useState(null);
    const [selectionEnd, setSelectionEnd] = useState(null);
    let cursor;
    switch (tool) {
        case "fill":
            cursor = "url(/images/icons/paint-fill.svg) 10 15, auto";
            break;
        case "eraser":
            cursor = "url(/images/icons/eraser-line.svg) 10 26, auto";
            break;
        case "selection":
            cursor = "url(/images/icons/layout-grid-2-line.svg) 10 15, auto";
            break;
        default:
            // The tip is at (5, 34.8) in the 40×40 viewBox, rendered at 30×30.
            cursor = "url(/images/icons/pencil-line.svg) 4 26, auto";
    }

    const getCellCoordinates = (event) => {
        const canvas = canvasRef.current;
        if (!canvas) return null;
        const rect = canvas.getBoundingClientRect();
        if (!rect.width || !rect.height || !gridWidth || !gridHeight) return null;
        const localX = (event.clientX - rect.left) * canvas.width / rect.width - margin;
        const visibleCol = Math.floor(localX / cellSize);
        const verticalShift = vertical ? getHalfCellShift(visibleCol, cellSize) : 0;
        const localY = (event.clientY - rect.top) * canvas.height / rect.height - margin - verticalShift;
        if (localY < 0 || localY >= gridHeight) return null;
        const rowFromTop = Math.floor(localY / cellSize);
        const rowFromBottom = gridHeight / cellSize - rowFromTop;
        const rowStart = horizontal ? getHalfCellShift(rowFromBottom, cellSize) : 0;
        if (localX < rowStart || localX >= rowStart + gridWidth) return null;
        const shift = horizontal ? rowFromBottom * (cellSize / 2) : 0;
        const col = Math.floor(((localX + shift) % gridWidth) / cellSize);
        const x = margin + col * cellSize;
        const y = margin + rowFromTop * cellSize;

        return { cellKey: `${x},${y}`, x, y, visibleColumn: Math.floor((localX - rowStart) / cellSize) };
    };

    useEffect(() => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext("2d");
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (vertical) {
            drawVerticalGrid(ctx, gridWidth, gridHeight, cellSize, margin);
        } else {
            drawGrid(ctx, gridWidth, gridHeight, cellSize, margin, horizontal);
        }
        ctx.save();
        Object.entries(drawnCells).forEach(([cell, color]) => {
            let [x, y] = cell.split(",").map(Number);
            ctx.fillStyle = color;
            if (horizontal) {
                const row = (gridHeight + margin - y) / cellSize;
                x = margin + getHorizontalPosition(x - margin, row, gridWidth, cellSize);
            }
            if (vertical) {
                y += getHalfCellShift((x - margin) / cellSize, cellSize);
            }
            ctx.fillRect(x, y, cellSize, cellSize);
        });

        if (selectionStart && selectionEnd && tool === "selection") {
            const selLeft = Math.min(selectionStart.x, selectionEnd.x);
            const selTop = Math.min(selectionStart.y, selectionEnd.y);
            const selRight = Math.max(selectionStart.x, selectionEnd.x) + cellSize;
            const selBottom = Math.max(selectionStart.y, selectionEnd.y) + cellSize;
            const selWidth = selRight - selLeft;
            const selHeight = selBottom - selTop;

            ctx.strokeStyle = "rgba(0, 0, 255, 0.8)";
            ctx.lineWidth = 2;
            ctx.fillStyle = "rgba(0, 0, 255, 0.2)";
            if (horizontal) {
                for (let y = selTop; y < selBottom; y += cellSize) {
                    const row = (gridHeight + margin - y) / cellSize;
                    const rowLeft = margin + getHalfCellShift(row, cellSize);
                    const x = margin + getHorizontalPosition(selLeft - margin, row, gridWidth, cellSize);
                    const width = Math.min(selWidth, rowLeft + gridWidth - x);
                    ctx.strokeRect(x, y, width, cellSize);
                    ctx.fillRect(x, y, width, cellSize);
                    if (width < selWidth) {
                        ctx.strokeRect(rowLeft, y, selWidth - width, cellSize);
                        ctx.fillRect(rowLeft, y, selWidth - width, cellSize);
                    }
                }
            } else if (vertical) {
                for (let x = selLeft; x < selRight; x += cellSize) {
                    const y = selTop + getHalfCellShift((x - margin) / cellSize, cellSize);
                    ctx.strokeRect(x, y, cellSize, selHeight);
                    ctx.fillRect(x, y, cellSize, selHeight);
                }
            } else {
                ctx.strokeRect(selLeft, selTop, selWidth, selHeight);
                ctx.fillRect(selLeft, selTop, selWidth, selHeight);
            }
        }

        ctx.restore();
    }, [gridWidth, gridHeight, cellSize, margin, horizontal, vertical, drawnCells, tool, selectionStart, selectionEnd, canvasRef]);

    useEffect(() => {
        if (tool !== "selection") {
            setSelectionStart(null);
            setSelectionEnd(null);
            setIsSelecting(false);
        }
    }, [tool]);

    const startDrawing = (event) => {
        const cell = getCellCoordinates(event);
        if (!cell) return;
        if (tool === "fill") {
            const targetColor = drawnCells[cell.cellKey] || "white";
            const gridBounds = {
                left: margin,
                right: gridWidth + margin,
                top: margin,
                bottom: gridHeight + margin,
            };
            const newCells = floodFill(cell.cellKey, targetColor, fillColor, drawnCells, cellSize, gridBounds);
            dispatch(setDrawnCells(newCells));
            dispatch(commitDrawing());
        } else {
            setIsDrawing(true);
            if (tool === "eraser") {
                const newDrawnCells = { ...drawnCells };
                delete newDrawnCells[cell.cellKey];
                dispatch(setDrawnCells(newDrawnCells));
            } else {
                dispatch(addCell({ cell: cell.cellKey, color: penColor }));
            }
        }
    };

    const draw = (event) => {
        if (!isDrawing) return;
        const cell = getCellCoordinates(event);
        if (!cell) return;
        if (tool === "eraser") {
            const newDrawnCells = { ...drawnCells };
            delete newDrawnCells[cell.cellKey];
            dispatch(setDrawnCells(newDrawnCells));
        } else {
            const newDrawnCells = { ...drawnCells, [cell.cellKey]: penColor };
            dispatch(setDrawnCells(newDrawnCells));
        }
    };

    const stopDrawing = () => {
        setIsDrawing(false);
        dispatch(commitDrawing());
    };

    const handleSelectionStart = (event) => {
        if (event.button !== 0) return;
        const cell = getCellCoordinates(event);
        if (!cell) return;
        event.currentTarget.focus({ preventScroll: true });
        if (!isSelecting) {
            setSelectionStart(cell);
        }
        setSelectionEnd(cell);
        setIsSelecting(!isSelecting);
    };

    const handleSelectionMouseMove = (event) => {
        if (!isSelecting) return;
        const cell = getCellCoordinates(event);
        if (cell) setSelectionEnd(cell);
    };

    const handleSelectionMouseUp = (event) => {
        if (event.button !== 0 || !isSelecting) return;
        const end = getCellCoordinates(event) ?? selectionEnd;
        if (end.cellKey !== selectionStart.cellKey) {
            setSelectionEnd(end);
            setIsSelecting(false);
        }
    };

    const handleSelectionKeyDown = (event) => {
        if ((!event.ctrlKey && !event.metaKey) || !selectionStart || !selectionEnd) return;
        if (event.code === "KeyC") {
            event.preventDefault();
            clipboardRef.current = copyCells(drawnCells, selectionStart, selectionEnd, cellSize);
            setIsSelecting(false);
        }
        if (event.code === "KeyV" && clipboardRef.current) {
            event.preventDefault();
            const cells = pasteCells(
                drawnCells, clipboardRef.current, selectionStart,
                selectionEnd.visibleColumn < selectionStart.visibleColumn,
                cellSize, margin, gridWidth, gridHeight
            );
            dispatch(setDrawnCells(cells));
            dispatch(commitDrawing());
            setIsSelecting(false);
        }
    };

    const handleContextMenu = (e) => {
        if (tool !== "selection") return;
        e.preventDefault();
    };

    return (
        <canvas
            id={offsetDirection ? "offset-canvas" : "normal-canvas"}
            ref={canvasRef}
            tabIndex={tool === "selection" ? 0 : undefined}
            onKeyDown={tool === "selection" ? handleSelectionKeyDown : undefined}
            onMouseDown={tool === "selection" ? handleSelectionStart : startDrawing}
            onMouseMove={tool === "selection" ? handleSelectionMouseMove : draw}
            onMouseUp={tool === "selection" ? handleSelectionMouseUp : stopDrawing}
            onMouseLeave={tool === "selection" ? undefined : stopDrawing}
            onContextMenu={handleContextMenu}
            style={{ cursor }}
        />
    );
};

export default CanvasGrid;
