import { useEffect, useRef } from "react";

/**
 * Sizes the canvas for the grid and its margins.
 *
 * @param {number} width - The width of the grid in pixels.
 * @param {number} height - The height of the grid in pixels.
 * @param {number} margin - The margin around the grid, in pixels.
 * @returns {React.MutableRefObject<HTMLCanvasElement | null>} A ref object associated with the canvas element.
 */
export const useCanvasGrid = (width, height, margin) => {
    const canvasRef = useRef(null);

    useEffect(() => {
        const canvas = canvasRef.current;
        canvas.width = width + margin * 2;
        canvas.height = height + margin * 2;
    }, [width, height, margin]);

    return canvasRef;
};
