import { useState } from 'react';
import { SearchableSelect } from '../../../components/SearchableSelect';
import { useDebouncedValue } from '../../../lib/useDebouncedValue';
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

/** Server-backed searchable picker for teachers or guardians. */
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
    const debouncedSearch = useDebouncedValue(search, 300);
    const { data: users, isLoading } = useUsers({
        role,
        search: debouncedSearch,
        school_id: schoolId,
    });

    const options = (users ?? []).map((user) => ({
        id: user.id,
        label: user.name,
        secondary: user.email,
    }));

    return (
        <SearchableSelect
            label={label}
            options={options}
            value={value}
            onChange={onChange}
            loading={isLoading}
            disabled={disabled}
            error={error}
            helperText={helperText}
            size={size}
            required={required}
            onSearchChange={setSearch}
            noOptionsText={debouncedSearch ? 'No matches' : 'Type to search'}
        />
    );
}
