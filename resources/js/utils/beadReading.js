export function describeBeads(drawnCells, gridWidth, gridHeight, cellSize, margin) {
    const colorCounts = new Map();
    const rows = new Map();
    let total = 0;

    for (const [key, value] of Object.entries(drawnCells)) {
        const [x, y] = key.split(',').map((coordinate) => Number(coordinate) - margin);
        if (x < 0 || x >= gridWidth || y < 0 || y >= gridHeight || x % cellSize !== 0 || y % cellSize !== 0) continue;
        const color = value.toLowerCase();
        const row = (gridHeight - y) / cellSize;
        const column = x / cellSize + 1;

        total++;
        colorCounts.set(color, (colorCounts.get(color) ?? 0) + 1);
        if (!rows.has(row)) rows.set(row, []);
        rows.get(row).push({column, color});
    }

    return {total, colorCounts, rows};
}

export function orderBeadRows(rows, bottomUp, leftToRight) {
    const step = leftToRight ? 1 : -1;
    const orderedRows = [...rows].sort(([a], [b]) => bottomUp ? a - b : b - a);
    const firstRow = orderedRows[0]?.[0];
    return orderedRows.map(([gridRow, cells]) => {
        const runs = [];
        for (const {column, color} of [...cells].sort((a, b) => step * (a.column - b.column))) {
            const previous = runs.at(-1);
            if (previous?.color === color && column === previous.column + step * previous.count) {
                previous.count++;
            } else {
                runs.push({column, color, count: 1});
            }
        }
        return {number: Math.abs(gridRow - firstRow) + 1, runs};
    });
}
