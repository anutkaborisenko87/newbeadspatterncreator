import {createSlice} from "@reduxjs/toolkit";

const initialState = {
    margin: 20,
    gridWidth: 300,
    gridHeight: 300,
    cellSize: 10,
    penColor: "#0000ff",
    fillColor: "#0000ff",
    drawnCells: {},
    undoStack: [{}],
    redoStack: [],
    tool: "pen"
};

function resizeGrid(state, width, height) {
    const heightChange = height - state.gridHeight;
    const {margin} = state;
    const cells = {};
    for (const [cell, color] of Object.entries(state.drawnCells)) {
        const [x, oldY] = cell.split(',').map(Number);
        const y = oldY + heightChange;
        if (x >= margin && x < margin + width && y >= margin && y < margin + height) {
            cells[`${x},${y}`] = color;
        }
    }

    state.gridWidth = width;
    state.gridHeight = height;
    state.drawnCells = cells;
    state.undoStack.push({...cells});
    state.redoStack = [];
}

const gridSlice = createSlice({
    name: "grid",
    initialState,
    reducers: {
        setGridWidth: (state, action) => {
            const width = Math.max(0, Math.trunc(action.payload)) * state.cellSize;
            if (Number.isSafeInteger(width) && width !== state.gridWidth) resizeGrid(state, width, state.gridHeight);
        },
        setGridHeight: (state, action) => {
            const height = Math.max(0, Math.trunc(action.payload)) * state.cellSize;
            if (Number.isSafeInteger(height) && height !== state.gridHeight) resizeGrid(state, state.gridWidth, height);
        },
        loadEditorDraft: (_state, action) => {
            const drawing = action.payload;
            const cells = {...drawing?.drawnCells};
            return {
                ...initialState,
                gridWidth: drawing?.gridWidth ?? initialState.gridWidth,
                gridHeight: drawing?.gridHeight ?? initialState.gridHeight,
                drawnCells: cells,
                undoStack: [{...cells}],
            };
        },
        setPenColor: (state, action) => {
            state.penColor = action.payload;
        },
        setDrawnCells: (state, action) => {
            state.drawnCells = action.payload;
        },
        addCell: (state, action) => {
            const { cell, color } = action.payload;
            state.drawnCells[cell] = color;

        },
        commitDrawing: (state) => {
            state.undoStack.push({ ...state.drawnCells });
            state.redoStack = [];
        },
        undo: (state) => {
            if (state.undoStack.length === 0) return;
            while (
                state.undoStack.length > 0 &&
                JSON.stringify(state.undoStack[state.undoStack.length - 1]) === JSON.stringify(state.drawnCells)
                ) {
                state.undoStack.pop();
            }
            if (state.undoStack.length === 0) return;
            const previous = state.undoStack.pop();
            state.redoStack.push({ ...state.drawnCells });
            state.drawnCells = previous;
        },
        redo: (state) => {
            if (state.redoStack.length === 0) return;
            state.undoStack.push({ ...state.drawnCells });
            const next = state.redoStack.pop();
            state.drawnCells = next;
        },
        setTool: (state, action) => {
            state.tool = action.payload;
        },
        setFillColor: (state, action) => {
            state.fillColor = action.payload;
        }
    },
});

export const {
    setGridWidth,
    setGridHeight,
    loadEditorDraft,
    setPenColor,
    setDrawnCells,
    addCell,
    commitDrawing,
    undo,
    redo,
    setTool,
    setFillColor
} = gridSlice.actions;
export default gridSlice.reducer;
