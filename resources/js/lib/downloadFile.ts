import type { AxiosResponse } from 'axios';

/**
 * Triggers a browser download from an axios `responseType: 'blob'` response
 * — used by every export/template-download call (`ExportButtons`,
 * `ImportDialog`). Prefers the server's `Content-Disposition` filename
 * (set automatically by Laravel Excel's `::download()` / DomPDF's
 * `::download()`) and falls back to `fallbackFilename` if that header is
 * ever missing.
 */
export function downloadBlobResponse(response: AxiosResponse<Blob>, fallbackFilename: string): void {
    const disposition = response.headers['content-disposition'] as string | undefined;
    const match = disposition?.match(/filename="?([^"]+)"?/);
    const filename = match?.[1] ?? fallbackFilename;

    const url = window.URL.createObjectURL(response.data);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}
