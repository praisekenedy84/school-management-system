import Autocomplete from '@mui/material/Autocomplete';
import CircularProgress from '@mui/material/CircularProgress';
import TextField from '@mui/material/TextField';
import type { SelectOption } from '../lib/selectOptions';

interface SearchableSelectProps {
    label: string;
    options: SelectOption[];
    value: string;
    onChange: (value: string) => void;
    loading?: boolean;
    disabled?: boolean;
    error?: boolean;
    helperText?: string;
    size?: 'small' | 'medium';
    required?: boolean;
    /** When provided, client-side filtering is disabled and this fires on input changes. */
    onSearchChange?: (search: string) => void;
    noOptionsText?: string;
}

/**
 * Searchable dropdown for foreign-key pickers. Uses MUI Autocomplete so
 * long lists (classes, teachers, guardians, …) are filterable by typing
 * instead of requiring a raw UUID.
 */
export function SearchableSelect({
    label,
    options,
    value,
    onChange,
    loading = false,
    disabled = false,
    error = false,
    helperText,
    size = 'medium',
    required = false,
    onSearchChange,
    noOptionsText = 'No matches',
}: SearchableSelectProps) {
    const selected = options.find((option) => option.id === value) ?? null;

    return (
        <Autocomplete
            fullWidth
            size={size}
            disabled={disabled}
            loading={loading}
            options={options}
            value={selected}
            onChange={(_, option) => onChange(option?.id ?? '')}
            onInputChange={onSearchChange ? (_, inputValue, reason) => {
                if (reason === 'input' || reason === 'clear') {
                    onSearchChange(inputValue);
                }
            } : undefined}
            filterOptions={onSearchChange ? (items) => items : undefined}
            isOptionEqualToValue={(left, right) => left.id === right.id}
            getOptionLabel={(option) =>
                option.secondary ? `${option.label} (${option.secondary})` : option.label
            }
            noOptionsText={loading ? 'Loading…' : noOptionsText}
            renderInput={(params) => (
                <TextField
                    {...params}
                    label={label}
                    required={required}
                    error={error}
                    helperText={helperText}
                    InputProps={{
                        ...params.InputProps,
                        endAdornment: (
                            <>
                                {loading ? <CircularProgress color="inherit" size={16} /> : null}
                                {params.InputProps.endAdornment}
                            </>
                        ),
                    }}
                />
            )}
        />
    );
}
