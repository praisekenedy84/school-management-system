import { useState } from 'react';
import { FileSpreadsheet, FileText, Loader2 } from 'lucide-react';
import { apiClient } from '@/api/client';
import { downloadBlobResponse } from '@/lib/downloadFile';
import { getErrorMessage } from '@/lib/getErrorMessage';
import { Button } from '@/components/ui/button';

/**
 * Export to Excel / PDF button pair for listing pages (PRD §5.9).
 */
export function ExportButtons({
    endpoint,
    filenamePrefix,
    params,
    onError,
}: {
    endpoint: string;
    filenamePrefix: string;
    params?: Record<string, string | number | undefined>;
    onError?: (message: string) => void;
}) {
    const [loadingFormat, setLoadingFormat] = useState<'xlsx' | 'pdf' | null>(null);

    const handleExport = async (format: 'xlsx' | 'pdf') => {
        setLoadingFormat(format);
        try {
            const response = await apiClient.get(endpoint, {
                params: { ...params, format },
                responseType: 'blob',
            });
            downloadBlobResponse(response, `${filenamePrefix}.${format}`);
        } catch (error) {
            onError?.(getErrorMessage(error, 'Unable to export. Please try again.'));
        } finally {
            setLoadingFormat(null);
        }
    };

    return (
        <div className="flex items-center gap-1">
            <Button
                variant="outline"
                size="sm"
                onClick={() => handleExport('xlsx')}
                disabled={loadingFormat !== null}
            >
                {loadingFormat === 'xlsx' ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                    <FileSpreadsheet className="h-4 w-4" />
                )}
                Export CSV
            </Button>
            <Button
                variant="outline"
                size="sm"
                onClick={() => handleExport('pdf')}
                disabled={loadingFormat !== null}
            >
                {loadingFormat === 'pdf' ? (
                    <Loader2 className="h-4 w-4 animate-spin" />
                ) : (
                    <FileText className="h-4 w-4" />
                )}
                PDF
            </Button>
        </div>
    );
}
