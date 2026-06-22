import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Dialog,
    DialogActions,
    DialogContent,
    DialogTitle,
    List,
    ListItem,
    ListItemText,
    MenuItem,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { Download, Upload } from 'lucide-react';
import { apiClient } from '../api/client';
import { downloadBlobResponse } from '../lib/downloadFile';
import { getErrorMessage } from '../lib/getErrorMessage';

interface ImportResult {
    created: number;
    failed: number;
    errors: { row: number; message: string }[];
}

/**
 * "Download template, fill it in, upload it" bulk-import flow — shared by
 * every importable module (Students, Subjects, Classes). A bad row never
 * aborts the whole sheet: the backend imports every valid row and reports
 * exactly which rows failed and why, shown below the upload button.
 */
export function ImportDialog({
    open,
    onClose,
    templateEndpoint,
    importEndpoint,
    resourceLabel,
    showSchoolPicker = false,
    schools = [],
    onImported,
}: {
    open: boolean;
    onClose: () => void;
    /** GET — downloads the fill-in-this-template .xlsx */
    templateEndpoint: string;
    /** POST multipart/form-data — { file, [school_id] } */
    importEndpoint: string;
    resourceLabel: string;
    /** show a "which school" picker — only needed for a tenant-wide admin */
    showSchoolPicker?: boolean;
    schools?: { id: string; name: string }[];
    /** called after a successful import so the page can refresh its list */
    onImported?: () => void;
}) {
    const [file, setFile] = useState<File | null>(null);
    const [schoolId, setSchoolId] = useState('');
    const [isDownloadingTemplate, setIsDownloadingTemplate] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [result, setResult] = useState<ImportResult | null>(null);
    const [error, setError] = useState<string | null>(null);

    const reset = () => {
        setFile(null);
        setSchoolId('');
        setResult(null);
        setError(null);
    };

    const handleClose = () => {
        reset();
        onClose();
    };

    const handleDownloadTemplate = async () => {
        setIsDownloadingTemplate(true);
        setError(null);
        try {
            const response = await apiClient.get(templateEndpoint, { responseType: 'blob' });
            downloadBlobResponse(response, `${resourceLabel.toLowerCase()}-template.xlsx`);
        } catch (err) {
            setError(getErrorMessage(err, 'Unable to download the template.'));
        } finally {
            setIsDownloadingTemplate(false);
        }
    };

    const handleUpload = async () => {
        if (!file) {
            return;
        }

        setIsSubmitting(true);
        setError(null);
        setResult(null);

        const formData = new FormData();
        formData.append('file', file);
        if (showSchoolPicker && schoolId) {
            formData.append('school_id', schoolId);
        }

        try {
            const { data } = await apiClient.post<ImportResult>(importEndpoint, formData);
            setResult(data);
            if (data.created > 0) {
                onImported?.();
            }
        } catch (err) {
            setError(getErrorMessage(err, 'Unable to import this file. Check it and try again.'));
        } finally {
            setIsSubmitting(false);
        }
    };

    const canUpload = Boolean(file) && (!showSchoolPicker || Boolean(schoolId)) && !isSubmitting;

    return (
        <Dialog open={open} onClose={handleClose} fullWidth maxWidth="sm">
            <DialogTitle>Import {resourceLabel}</DialogTitle>
            <DialogContent>
                <Stack spacing={2} mt={1}>
                    <Typography variant="body2" color="text.secondary">
                        Download the template, fill it in, then upload it below.
                    </Typography>

                    <Button
                        variant="outlined"
                        startIcon={<Download size={16} />}
                        onClick={handleDownloadTemplate}
                        disabled={isDownloadingTemplate}
                        sx={{ alignSelf: 'flex-start' }}
                    >
                        Download Template
                    </Button>

                    {showSchoolPicker && (
                        <TextField
                            select
                            fullWidth
                            label="School"
                            value={schoolId}
                            onChange={(e) => setSchoolId(e.target.value)}
                            helperText="Which school is this import for?"
                        >
                            {schools.map((school) => (
                                <MenuItem key={school.id} value={school.id}>
                                    {school.name}
                                </MenuItem>
                            ))}
                        </TextField>
                    )}

                    <Box>
                        <input
                            type="file"
                            accept=".xlsx,.xls,.csv"
                            onChange={(e) => setFile(e.target.files?.[0] ?? null)}
                        />
                    </Box>

                    {error && <Alert severity="error">{error}</Alert>}

                    {result && (
                        <Alert severity={result.failed > 0 ? 'warning' : 'success'}>
                            Imported {result.created} record{result.created === 1 ? '' : 's'}.
                            {result.failed > 0 && ` ${result.failed} row(s) could not be imported.`}
                        </Alert>
                    )}

                    {result && result.errors.length > 0 && (
                        <List dense sx={{ maxHeight: 200, overflow: 'auto', bgcolor: 'action.hover', borderRadius: 1 }}>
                            {result.errors.map((rowError) => (
                                <ListItem key={rowError.row}>
                                    <ListItemText
                                        primary={`Row ${rowError.row}`}
                                        secondary={rowError.message}
                                    />
                                </ListItem>
                            ))}
                        </List>
                    )}
                </Stack>
            </DialogContent>
            <DialogActions>
                <Button onClick={handleClose}>Close</Button>
                <Button
                    variant="contained"
                    startIcon={<Upload size={16} />}
                    onClick={handleUpload}
                    disabled={!canUpload}
                >
                    {isSubmitting ? 'Uploading…' : 'Upload'}
                </Button>
            </DialogActions>
        </Dialog>
    );
}
