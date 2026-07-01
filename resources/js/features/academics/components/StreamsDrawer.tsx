import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    Chip,
    CircularProgress,
    Drawer,
    IconButton,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { Pencil, Plus, X } from 'lucide-react';
import {
    useClassStreams,
    useCreateStream,
    useDeactivateStream,
    useUpdateStream,
} from '../api/useStreams';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { ClassRoom, Stream } from '../types/academic';

/** Create, edit, and deactivate streams for a single class. */
export function StreamsDrawer({
    open,
    classRoom,
    onClose,
}: {
    open: boolean;
    classRoom: ClassRoom | null;
    onClose: () => void;
}) {
    const classId = classRoom?.id ?? '';
    const { data: streams, isLoading } = useClassStreams(classId);
    const createStream = useCreateStream(classId);
    const updateStream = useUpdateStream(classId);
    const deactivateStream = useDeactivateStream(classId);

    const [name, setName] = useState('');
    const [capacity, setCapacity] = useState('');
    const [editingStream, setEditingStream] = useState<Stream | null>(null);
    const [serverError, setServerError] = useState<string | null>(null);

    const resetForm = () => {
        setName('');
        setCapacity('');
        setEditingStream(null);
    };

    const handleSave = async () => {
        if (!name.trim()) return;
        setServerError(null);
        try {
            const payload = {
                name: name.trim(),
                capacity: capacity ? Number(capacity) : null,
            };
            if (editingStream) {
                await updateStream.mutateAsync({ id: editingStream.id, payload });
            } else {
                await createStream.mutateAsync(payload);
            }
            resetForm();
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to save stream.'));
        }
    };

    const handleEdit = (stream: Stream) => {
        setEditingStream(stream);
        setName(stream.name);
        setCapacity(stream.capacity != null ? String(stream.capacity) : '');
    };

    const handleDeactivate = (streamId: string) => {
        setServerError(null);
        deactivateStream.mutate(streamId, {
            onError: (error) => setServerError(getErrorMessage(error, 'Unable to deactivate stream.')),
        });
    };

    const handleReactivate = async (stream: Stream) => {
        setServerError(null);
        try {
            await updateStream.mutateAsync({
                id: stream.id,
                payload: { name: stream.name, capacity: stream.capacity, is_active: true },
            });
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to reactivate stream.'));
        }
    };

    return (
        <Drawer anchor="right" open={open} onClose={onClose}>
            <Box sx={{ width: 400, p: 3 }}>
                <Typography variant="h6" gutterBottom>
                    Streams — {classRoom?.name}
                </Typography>

                {serverError && (
                    <Alert severity="error" sx={{ mb: 2 }}>
                        {serverError}
                    </Alert>
                )}

                {isLoading && (
                    <Box display="flex" justifyContent="center" py={2}>
                        <CircularProgress size={24} />
                    </Box>
                )}

                <Stack direction="row" spacing={1} useFlexGap flexWrap="wrap" mb={3}>
                    {!isLoading && (streams ?? []).length === 0 && (
                        <Typography variant="body2" color="text.secondary">
                            No streams yet.
                        </Typography>
                    )}
                    {(streams ?? []).map((stream) => (
                        <Chip
                            key={stream.id}
                            label={stream.name}
                            color={stream.is_active ? 'default' : 'default'}
                            variant={stream.is_active ? 'filled' : 'outlined'}
                            onDelete={stream.is_active ? () => handleDeactivate(stream.id) : undefined}
                            deleteIcon={<X size={14} />}
                            icon={
                                <IconButton size="small" onClick={() => handleEdit(stream)} sx={{ p: 0 }}>
                                    <Pencil size={12} />
                                </IconButton>
                            }
                            onClick={() => !stream.is_active && handleReactivate(stream)}
                        />
                    ))}
                </Stack>

                <Stack spacing={2}>
                    <TextField
                        size="small"
                        fullWidth
                        label={editingStream ? 'Edit stream name' : 'New stream name'}
                        value={name}
                        onChange={(e) => setName(e.target.value)}
                        placeholder="e.g. Form 1A"
                    />
                    <TextField
                        size="small"
                        fullWidth
                        label="Capacity (optional)"
                        type="number"
                        value={capacity}
                        onChange={(e) => setCapacity(e.target.value)}
                    />
                    <Stack direction="row" spacing={1}>
                        <Button
                            variant="contained"
                            startIcon={<Plus size={16} />}
                            disabled={!name.trim() || createStream.isPending || updateStream.isPending}
                            onClick={handleSave}
                        >
                            {editingStream ? 'Update' : 'Add Stream'}
                        </Button>
                        {editingStream && (
                            <Button onClick={resetForm}>Cancel edit</Button>
                        )}
                    </Stack>
                </Stack>

                <Box mt={3} textAlign="right">
                    <Button onClick={onClose}>Close</Button>
                </Box>
            </Box>
        </Drawer>
    );
}
