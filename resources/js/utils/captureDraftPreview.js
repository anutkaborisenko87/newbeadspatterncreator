export async function captureDraftPreview(workspace) {
    const source = workspace?.querySelector('canvas#normal-canvas');
    if (!source?.isConnected || !source.width || !source.height) return null;

    const scale = Math.min(1, 1200 / source.width, 900 / source.height);
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.floor(source.width * scale));
    canvas.height = Math.max(1, Math.floor(source.height * scale));
    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);
    context.drawImage(source, 0, 0, canvas.width, canvas.height);

    const prefix = 'data:image/jpeg;base64,';
    for (const quality of [0.9, 0.75, 0.55]) {
        const image = canvas.toDataURL('image/jpeg', quality);
        if (!image.startsWith(prefix)) return null;
        const preview = image.slice(prefix.length);
        if (preview.length <= 600_000) return {preview, previewFormat: 'flat-grid'};
    }

    return null;
}
