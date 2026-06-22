import { useState } from 'react';
import { Button, ButtonGroup, CircularProgress } from '@mui/material';
import { FileSpreadsheet, FileText } from 'lucide-react';
import { apiClient } from '../api/client';
import { downloadBlobResponse } from '../lib/downloadFile';
import { getErrorMessage } from '../lib/getErrorMessage';

/**
 * "Export to Excel / PDF" button pair for any listing page (PRD §5.9). Every
 * backend export endpoint shares one shape — `GET {endpoint}?format=xlsx|pdf`
 * returning a file — so this one component covers every module; pages don't
 * each write their own download logic.
 */
export function ExportButtons({
    endpoint,
    filenamePrefix,
    params,
    onError,
}: {
    /** e.g. '/subjects/export' */
    endpoint: string;
    /** used only as a fallback if the server's Content-Disposition is missing */
    filenamePrefix: string;
    /** extra query params to forward (e.g. the same filters index() is using) */
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
        <ButtonGroup variant="outlined" size="small">
            <Button
                startIcon={loadingFormat === 'xlsx' ? <CircularProgress size={14} /> : <FileSpreadsheet size={16} />}
                onClick={() => handleExport('xlsx')}
                disabled={loadingFormat !== null}
            >
                Excel
            </Button>
            <Button
                startIcon={loadingFormat === 'pdf' ? <CircularProgress size={14} /> : <FileText size={16} />}
                onClick={() => handleExport('pdf')}
                disabled={loadingFormat !== null}
            >
                PDF
            </Button>
        </ButtonGroup>
    );
}
