import { useState } from 'react';
import { SearchableSelect } from '../../../components/SearchableSelect';
import { useUsers, type UserLookupRole } from '../api/useUsers';

interface UserSearchSelectProps {
    role: UserLookupRole;
    label: string;
    value: string;
    onChange: (value: string) => void;
    schoolId?: string;
    error?: boolean;
    helperText?: string;
    size?: 'small' | 'medium';
    required?: boolean;
    disabled?: boolean;
}

/** Server-backed picker for teachers or guardians — preloads all active users on open. */
export function UserSearchSelect({
    role,
    label,
    value,
    onChange,
    schoolId,
    error,
    helperText,
    size = 'medium',
    required = false,
    disabled = false,
}: UserSearchSelectProps) {
    const [search, setSearch] = useState('');
    const { data: users, isLoading, isFetching } = useUsers({
        role,
        search: search || undefined,
        school_id: schoolId,
    });

    const options = (users ?? []).map((user) => ({
        id: user.id,
        label: user.name,
        secondary: user.email,
    }));

    const loading = isLoading || isFetching;
    const noOptionsText = loading
        ? 'Loading…'
        : options.length === 0
          ? search
              ? 'No matches'
              : `No active ${role === 'teacher' ? 'teachers' : 'guardians'} found`
          : 'No matches';

    return (
        <SearchableSelect
            label={label}
            options={options}
            value={value}
            onChange={onChange}
            loading={loading}
            disabled={disabled}
            error={error}
            helperText={helperText ?? (options.length > 0 && !value ? `${options.length} available — click to select` : undefined)}
            size={size}
            required={required}
            onSearchChange={setSearch}
            noOptionsText={noOptionsText}
        />
    );
}
