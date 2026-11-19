export function copyCells(drawnCells, start, end, cellSize) {
    const left = Math.min(start.x, end.x);
    const top = Math.min(start.y, end.y);
    const width = Math.abs(start.x - end.x) + cellSize;
    const height = Math.abs(start.y - end.y) + cellSize;
    const cells = {};

    for (const [key, color] of Object.entries(drawnCells)) {
        const [x, y] = key.split(",").map(Number);
        if (x >= left && x < left + width && y >= top && y < top + height) {
            cells[`${x - left},${y - top}`] = color;
        }
    }

    return { cells, width, height };
}

export function pasteCells(drawnCells, clipboard, start, fromRight, cellSize, margin, gridWidth, gridHeight) {
    const { cells, width, height } = clipboard;
    const left = fromRight ? start.x - width + cellSize : start.x;
    const top = fromRight ? start.y - height + cellSize : start.y;
    const nextCells = { ...drawnCells };

    for (let y = Math.max(top, margin); y < Math.min(top + height, margin + gridHeight); y += cellSize) {
        for (let x = Math.max(left, margin); x < Math.min(left + width, margin + gridWidth); x += cellSize) {
            const color = cells[`${x - left},${y - top}`];
            const key = `${x},${y}`;
            if (color === undefined) {
                delete nextCells[key];
            } else {
                nextCells[key] = color;
            }
        }
    }

    return nextCells;
}
