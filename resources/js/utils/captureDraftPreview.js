export async function captureDraftPreview(workspace) {
    if (!workspace?.isConnected) return null;
    const {clientWidth: width, clientHeight: height} = workspace;
    if (!width || !height) return null;

    const {toCanvas} = await import('html-to-image');
    const scale = Math.min(1, 1200 / width, 900 / height);
    const canvas = await toCanvas(workspace, {
        backgroundColor: '#ffffff',
        canvasWidth: Math.max(1, Math.floor(width * scale)),
        canvasHeight: Math.max(1, Math.floor(height * scale)),
        pixelRatio: 1,
        skipFonts: true,
    });

    for (const quality of [0.9, 0.75, 0.55]) {
        const image = canvas.toDataURL('image/jpeg', quality);
        if (!image.startsWith('data:image/jpeg;base64,')) return null;
        const preview = image.slice('data:image/jpeg;base64,'.length);
        if (preview.length <= 600_000) return preview;
    }

    return null;
}
