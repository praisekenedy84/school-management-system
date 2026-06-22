<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $receiptNumber }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1a1a1a; }
        .letterhead { text-align: center; margin-bottom: 20px; }
        .letterhead h1 { margin: 0; font-size: 18px; }
        .letterhead p { margin: 2px 0; font-size: 11px; color: #555; }
        .receipt-no { text-align: right; font-weight: bold; margin-bottom: 12px; }
        table.meta { width: 100%; margin-bottom: 16px; }
        table.meta td { padding: 2px 8px 2px 0; vertical-align: top; }
        table.alloc { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        table.alloc th, table.alloc td { border: 1px solid #ccc; padding: 6px 8px; font-size: 11px; text-align: left; }
        table.alloc th { background: #f0f0f0; }
        table.alloc td.amount, table.alloc th.amount { text-align: right; }
        .total { text-align: right; font-weight: bold; font-size: 13px; margin-top: 8px; }
        .words { margin-top: 6px; font-style: italic; }
        .footer { margin-top: 24px; font-size: 10px; color: #777; }
    </style>
</head>
<body>
    <div class="letterhead">
        <h1>{{ $slip->school?->name ?? 'School name' }}</h1>
        <p>Official Payment Receipt</p>
    </div>

    <div class="receipt-no">Receipt No: {{ $receiptNumber }}</div>

    <table class="meta">
        <tr>
            <td><strong>Student:</strong></td>
            <td>{{ $paymentDetails['student_name'] ?? '-' }}</td>
            <td><strong>Admission No.:</strong></td>
            <td>{{ $paymentDetails['admission_number'] ?? '-' }}</td>
        </tr>
        <tr>
            <td><strong>Slip No.:</strong></td>
            <td>{{ $paymentDetails['slip_number'] }}</td>
            <td><strong>Deposit date:</strong></td>
            <td>{{ $paymentDetails['deposit_date'] }}</td>
        </tr>
        <tr>
            <td><strong>Depositor:</strong></td>
            <td>{{ $paymentDetails['depositor_name'] }}</td>
            <td><strong>Bank / Teller:</strong></td>
            <td>{{ $paymentDetails['bank_name'] ?? '-' }} / {{ $paymentDetails['teller_number'] ?? '-' }}</td>
        </tr>
    </table>

    <table class="alloc">
        <thead>
            <tr>
                <th>Fee type</th>
                <th class="amount">Amount ({{ $paymentDetails['currency'] }})</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($paymentDetails['allocation'] as $line)
                <tr>
                    <td>{{ $line['fee_type'] }}</td>
                    <td class="amount">{{ number_format((float) $line['amount'], 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="total">
        Total: {{ $paymentDetails['currency'] }} {{ number_format((float) $paymentDetails['total_amount'], 2) }}
    </div>
    <div class="words">Amount in words: {{ $amountInWords }}</div>

    <div class="footer">
        Generated {{ $generatedAt->toDayDateTimeString() }}.
        This receipt records an externally-made, human-verified payment. No funds are processed by this system.
    </div>
</body>
</html>
