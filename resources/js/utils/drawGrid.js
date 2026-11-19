export const getHalfCellShift = (index, cellSize) => (index % 2) * cellSize / 2;

const gridStrokeStyle = "rgba(180, 180, 180, 0.45)";

export const getHorizontalPosition = (x, row, width, cellSize) => {
    const shifted = x - row * cellSize / 2;
    return ((shifted % width) + width) % width;
};

export const drawVerticalGrid = (ctx, width, height, cellSize, margin) => {
    ctx.strokeStyle = gridStrokeStyle;
    ctx.lineWidth = 1;
    ctx.beginPath();
    for (let column = 0; column < width / cellSize; column++) {
        const x = margin + column * cellSize;
        const top = margin + getHalfCellShift(column, cellSize);
        ctx.rect(x, top, cellSize, height);
        for (let y = top + cellSize; y < top + height; y += cellSize) {
            ctx.moveTo(x, y);
            ctx.lineTo(x + cellSize, y);
        }
    }
    ctx.stroke();
};

/**
 * Draws a grid on the given canvas context with customizable cell size, margin, and optional offset.
 *
 * @param {CanvasRenderingContext2D} ctx - The canvas 2D drawing context on which the grid will be drawn.
 * @param {number} width - The width of the grid area to be drawn.
 * @param {number} height - The height of the grid area to be drawn.
 * @param {number} cellSize - The size of each cell in the grid.
 * @param {number} margin - The margin space around the grid.
 * @param {boolean} [offset=false] - Whether to offset alternate rows of the grid to create a staggered effect.
 */
export const drawGrid = (ctx, width, height, cellSize, margin, offset = false) => {
    ctx.strokeStyle = gridStrokeStyle;
    ctx.lineWidth = 1;

    if (offset) {
        ctx.beginPath();
        for (let row = 1; row <= height / cellSize; row++) {
            const y = margin + height - row * cellSize;
            const left = margin + getHalfCellShift(row, cellSize);
            ctx.rect(left, y, width, cellSize);
            for (let x = left + cellSize; x < left + width; x += cellSize) {
                ctx.moveTo(x, y);
                ctx.lineTo(x, y + cellSize);
            }
        }
        ctx.stroke();
        return;
    }

    for (let y = height + margin; y >= margin; y -= cellSize) {
        ctx.beginPath();
        ctx.moveTo(margin, y);
        ctx.lineTo(width + margin, y);
        ctx.lineWidth = (height + margin - y) % (cellSize * 5) === 0 ? 2 : 1;
        ctx.stroke();

        for (let x = margin; x <= width + margin; x += cellSize) {
            ctx.beginPath();
            ctx.moveTo(x, y);
            ctx.lineTo(x, Math.max(y - cellSize, margin));
            ctx.lineWidth = (x - margin) % (cellSize * 5) === 0 ? 2 : 1;
            ctx.stroke();
        }
    }

    ctx.fillStyle = "black";
    ctx.font = "10px Arial";
    ctx.textAlign = "center";

    for (let x = margin + cellSize * 5; x <= width + margin; x += cellSize * 5) {
        ctx.fillText((x - margin) / cellSize, x, height + margin + 10);
    }

    ctx.textAlign = "right";
    for (let y = height + margin; y >= margin; y -= cellSize * 5) {
        const yValue = (height + margin - y) / cellSize;
        ctx.fillText(yValue, margin - 5, y + 3);
    }
};
