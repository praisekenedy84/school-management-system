<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Class Report Cards</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1a1a1a; }
        .letterhead { text-align: center; margin-bottom: 20px; }
        .letterhead h1 { margin: 0; font-size: 18px; }
        .letterhead p { margin: 2px 0; font-size: 11px; color: #555; }
        .meta { margin-bottom: 16px; }
        .meta td { padding: 2px 8px 2px 0; }
        table.results { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.results th, table.results td { border: 1px solid #ccc; padding: 6px 8px; font-size: 11px; text-align: left; }
        table.results th { background: #f0f0f0; }
        .subject-heading { background: #e8e8e8; font-weight: bold; }
        .footer { margin-top: 24px; font-size: 10px; color: #777; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="letterhead">
        <h1>{{ $classRoom->school?->name ?? 'School name' }}</h1>
        <p>Class Report Cards — {{ $classRoom->name }} · {{ $academicSession->name }}</p>
    </div>

    @foreach ($reportCards as $index => $card)
        <div @if($index < count($reportCards) - 1) class="page-break" @endif>
            <table class="meta">
                <tr>
                    <td><strong>Student:</strong></td>
                    <td>{{ $card['student']->full_name }}</td>
                    <td><strong>Admission No.:</strong></td>
                    <td>{{ $card['student']->admission_number }}</td>
                </tr>
            </table>

            @forelse ($card['subjects'] as $subject)
                <table class="results">
                    <thead>
                        <tr>
                            <th colspan="4" class="subject-heading">
                                {{ $subject['subject_name'] }} — Weighted score: {{ $subject['weighted_score'] }}
                            </th>
                        </tr>
                        <tr>
                            <th>Assessment</th>
                            <th>Score</th>
                            <th>Max</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subject['assessments'] as $assessment)
                            <tr>
                                <td>{{ $assessment['name'] }}</td>
                                <td>{{ $assessment['score'] ?? '-' }}</td>
                                <td>{{ $assessment['max_score'] }}</td>
                                <td>{{ $assessment['grade'] ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @empty
                <p>No published results available for this academic session.</p>
            @endforelse
        </div>
    @endforeach

    <div class="footer">
        Generated {{ $generatedAt->toDayDateTimeString() }}
    </div>
</body>
</html>
