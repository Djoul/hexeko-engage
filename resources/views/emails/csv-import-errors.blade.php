<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSV Import Errors Report</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            border-bottom: 2px solid #e74c3c;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        h1 {
            color: #e74c3c;
            margin: 0 0 10px 0;
            font-size: 24px;
        }
        .subtitle {
            color: #666;
            font-size: 14px;
        }
        .summary-box {
            background-color: #fef5e7;
            border-left: 4px solid #f39c12;
            padding: 15px;
            margin: 20px 0;
        }
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-value {
            font-size: 28px;
            font-weight: bold;
            color: #2c3e50;
        }
        .stat-label {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-top: 5px;
        }
        .error-summary {
            margin: 30px 0;
        }
        .error-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .error-table th {
            background-color: #ecf0f1;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid #bdc3c7;
        }
        .error-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #ecf0f1;
        }
        .error-table tr:hover {
            background-color: #f8f9fa;
        }
        .sample-errors {
            margin-top: 30px;
        }
        .error-item {
            background-color: #fff5f5;
            border-left: 3px solid #e74c3c;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .error-item-header {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .error-item-details {
            font-size: 14px;
            color: #555;
            margin-top: 8px;
        }
        .error-message {
            color: #e74c3c;
            font-style: italic;
            margin-top: 5px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
            font-size: 13px;
            color: #7f8c8d;
        }
        .attachment-note {
            background-color: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
        }
        .attachment-note strong {
            color: #2e7d32;
        }
        .error-badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #e74c3c;
            color: white;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CSV Import Errors Report</h1>
            <div class="subtitle">
                Import ID: {{ $importId }}<br>
                Date: {{ $importDate }}
                @if($financerName)
                    <br>Financer: {{ $financerName }}
                @endif
            </div>
        </div>

        <div class="summary-box">
            <strong>Hello {{ $userName }},</strong><br>
            Your CSV import has been completed with errors. Below is a detailed report of the issues encountered during the import process.
        </div>

        <div class="summary-stats">
            <div class="stat-card">
                <div class="stat-value">{{ number_format($totalRows) }}</div>
                <div class="stat-label">Total Rows</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #27ae60;">{{ number_format($processedRows) }}</div>
                <div class="stat-label">Successfully Processed</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #e74c3c;">{{ number_format($failedRows) }}</div>
                <div class="stat-label">Failed</div>
            </div>
            @if($totalDuration)
            <div class="stat-card">
                <div class="stat-value">{{ number_format($totalDuration, 1) }}s</div>
                <div class="stat-label">Duration</div>
            </div>
            @endif
        </div>

        @if(count($failedRowsDetails) > 0)
            <div class="attachment-note">
                <strong>📎 Attachment Included:</strong> A CSV file containing all {{ count($failedRowsDetails) }} error records has been attached to this email for your reference.
            </div>

            <div class="error-summary">
                <h2 style="color: #2c3e50; font-size: 18px;">Error Summary</h2>
                <table class="error-table">
                    <thead>
                        <tr>
                            <th>Error Type</th>
                            <th style="text-align: right;">Occurrences</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($errorSummary as $error => $count)
                        <tr>
                            <td>{{ $error }}</td>
                            <td style="text-align: right;">
                                <span class="error-badge">{{ number_format($count) }}</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="sample-errors">
                <h2 style="color: #2c3e50; font-size: 18px;">
                    Sample Failed Records 
                    @if(count($failedRowsDetails) > 10)
                        (Showing first 10 of {{ count($failedRowsDetails) }})
                    @endif
                </h2>
                
                @foreach(array_slice($failedRowsDetails, 0, 10) as $index => $detail)
                <div class="error-item">
                    <div class="error-item-header">
                        Record #{{ $index + 1 }}: {{ $detail['row']['email'] ?? 'No email' }}
                    </div>
                    <div class="error-item-details">
                        <strong>Name:</strong> {{ $detail['row']['first_name'] ?? '' }} {{ $detail['row']['last_name'] ?? '' }}<br>
                        @if(!empty($detail['row']['phone']))
                            <strong>Phone:</strong> {{ $detail['row']['phone'] }}<br>
                        @endif
                        @if(!empty($detail['row']['external_id']))
                            <strong>External ID:</strong> {{ $detail['row']['external_id'] }}<br>
                        @endif
                    </div>
                    <div class="error-message">
                        <strong>Error:</strong> {{ $detail['error'] ?? 'Unknown error' }}
                    </div>
                </div>
                @endforeach

                @if(count($failedRowsDetails) > 10)
                <div style="text-align: center; color: #7f8c8d; margin-top: 20px;">
                    ... and {{ count($failedRowsDetails) - 10 }} more errors. See attached CSV file for complete list.
                </div>
                @endif
            </div>
        @endif

        <div class="footer">
            <p><strong>What to do next?</strong></p>
            <ul style="margin-top: 10px; padding-left: 20px;">
                <li>Review the error messages to understand what went wrong</li>
                <li>Correct the data in your original file</li>
                <li>Re-import only the failed records using the attached CSV as reference</li>
                <li>Contact support if you need assistance</li>
            </ul>
            
            <p style="margin-top: 20px;">
                This is an automated message. Please do not reply to this email.<br>
                If you need assistance, please contact your system administrator.
            </p>
        </div>
    </div>
</body>
</html>