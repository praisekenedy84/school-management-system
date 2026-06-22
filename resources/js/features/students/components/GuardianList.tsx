import { useState } from 'react';
import {
    Alert,
    Box,
    Button,
    IconButton,
    List,
    ListItem,
    ListItemText,
    Stack,
    TextField,
    Typography,
} from '@mui/material';
import { Trash2 } from 'lucide-react';
import { useLinkGuardian, useUnlinkGuardian } from '../api/useStudents';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import type { Guardian } from '../types/student';

/**
 * Dumb-ish list with a small inline "link guardian" form. Logic lives in the
 * useLinkGuardian/useUnlinkGuardian hooks; this component only wires the form
 * state and renders the result.
 */
export function GuardianList({ studentId, guardians }: { studentId: string; guardians: Guardian[] }) {
    const linkGuardian = useLinkGuardian(studentId);
    const unlinkGuardian = useUnlinkGuardian(studentId);
    const [guardianId, setGuardianId] = useState('');
    const [relationship, setRelationship] = useState('');
    const [serverError, setServerError] = useState<string | null>(null);

    const handleLink = async () => {
        setServerError(null);
        try {
            await linkGuardian.mutateAsync({
                guardian_id: guardianId,
                relationship: relationship || null,
                is_primary: false,
            });
            setGuardianId('');
            setRelationship('');
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to link guardian.'));
        }
    };

    const handleUnlink = (id: string) => {
        unlinkGuardian.mutate(id);
    };

    return (
        <Box>
            <Typography variant="subtitle1" gutterBottom>
                Guardians
            </Typography>

            {serverError && (
                <Alert severity="error" sx={{ mb: 2 }}>
                    {serverError}
                </Alert>
            )}

            {guardians.length === 0 ? (
                <Typography variant="body2" color="text.secondary" mb={2}>
                    No guardians linked yet.
                </Typography>
            ) : (
                <List dense>
                    {guardians.map((guardian) => (
                        <ListItem
                            key={guardian.id}
                            secondaryAction={
                                <IconButton
                                    edge="end"
                                    aria-label="Remove guardian"
                                    onClick={() => handleUnlink(guardian.id)}
                                    disabled={unlinkGuardian.isPending}
                                >
                                    <Trash2 size={18} />
                                </IconButton>
                            }
                        >
                            <ListItemText
                                primary={`${guardian.name}${guardian.is_primary ? ' (primary)' : ''}`}
                                secondary={[guardian.relationship, guardian.email].filter(Boolean).join(' · ')}
                            />
                        </ListItem>
                    ))}
                </List>
            )}

            {/* TODO: replace free-text guardian_id with a user search/picker once a guardian lookup endpoint exists. */}
            <Stack direction="row" spacing={1} mt={1}>
                <TextField
                    size="small"
                    label="Guardian User ID (UUID)"
                    value={guardianId}
                    onChange={(e) => setGuardianId(e.target.value)}
                />
                <TextField
                    size="small"
                    label="Relationship"
                    value={relationship}
                    onChange={(e) => setRelationship(e.target.value)}
                />
                <Button
                    variant="outlined"
                    onClick={handleLink}
                    disabled={!guardianId || linkGuardian.isPending}
                >
                    Link
                </Button>
            </Stack>
        </Box>
    );
}
