<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $report->title }}</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', Arial, sans-serif;
            direction: ltr;
            color: #1f2937;
            line-height: 1.6;
            font-size: 14px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .header .subtitle {
            font-size: 18px;
            opacity: 0.9;
        }

        .report-info {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .info-item {
            flex: 1;
            min-width: 200px;
            padding: 10px;
        }

        .info-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 12px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 16px;
            color: #1f2937;
            font-weight: 700;
        }

        .section {
            margin-bottom: 40px;
            page-break-inside: avoid;
        }

        .section-title {
            font-size: 24px;
            color: #667eea;
            border-left: 5px solid #667eea;
            padding-left: 15px;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #6b7280;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: white;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .table th {
            background: #667eea;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
        }

        .table td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
        }

        .table tr:hover {
            background: #f9fafb;
        }

        .wqi-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 14px;
        }

        .wqi-excellent {
            background: #d1fae5;
            color: #065f46;
        }

        .wqi-good {
            background: #dbeafe;
            color: #1e40af;
        }

        .wqi-fair {
            background: #fef3c7;
            color: #92400e;
        }

        .wqi-poor {
            background: #fee2e2;
            color: #991b1b;
        }

        .wqi-very-poor {
            background: #fecaca;
            color: #7f1d1d;
        }

        .chart-placeholder {
            height: 300px;
            background: #f3f4f6;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            margin: 20px 0;
        }

        .conclusions {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .conclusions h3 {
            color: #1e40af;
            margin-bottom: 10px;
        }

        .recommendations {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .recommendations h3 {
            color: #92400e;
            margin-bottom: 10px;
        }

        .recommendations ul {
            margin-left: 20px;
        }

        .recommendations li {
            margin-bottom: 8px;
            line-height: 1.8;
        }

        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
            text-align: center;
            color: #6b7280;
            font-size: 12px;
        }

        .page-break {
            page-break-after: always;
        }

        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>Water Quality Monitoring System</h1>
        <div class="subtitle">Comprehensive Water Quality Analysis</div>
    </div>

    <!-- Report Info -->
    <div class="report-info">
        <div class="info-item">
            <div class="info-label">Report Number</div>
            <div class="info-value">{{ $report->report_code }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Report Type</div>
            <div class="info-value">{{ $report->report_type_name }}</div>
        </div>
        <div class="info-item">
            <div class="info-label">Creation Date</div>
            <div class="info-value">{{ $report->created_at->format('Y-m-d') }}</div>
        </div>
        @if($report->date_range)
        <div class="info-item">
            <div class="info-label">Time Period</div>
            <div class="info-value">{{ $report->date_range }}</div>
        </div>
        @endif
    </div>

    <!-- Report Title -->
    <div style="text-align: center; margin: 30px 0;">
        <h2 style="font-size: 28px; color: #1f2937;">{{ $report->title }}</h2>
        @if($report->description)
        <p style="color: #6b7280; margin-top: 10px;">{{ $report->description }}</p>
        @endif
    </div>

    <!-- Summary Statistics -->
    @if(isset($data['summary']) && !empty($data['summary']))
    <div class="section">
        <h2 class="section-title">Statistical Summary</h2>

        <div class="stats-grid">
            @foreach($data['summary'] as $key => $value)
                @if(is_numeric($value) && !in_array($key, ['comparison', 'regional_data']))
                <div class="stat-card">
                    <div class="stat-value">{{ $value }}</div>
                    <div class="stat-label">{{ ucfirst(str_replace('_', ' ', $key)) }}</div>
                </div>
                @endif
            @endforeach
        </div>

        @if(isset($data['summary']['comparison']) && is_array($data['summary']['comparison']))
        <table class="table">
            <thead>
                <tr>
                    <th>Location</th>
                    <th>Sample Count</th>
                    <th>Average WQI</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['summary']['comparison'] as $location)
                <tr>
                    <td>{{ $location['location'] ?? 'N/A' }}</td>
                    <td>{{ $location['sample_count'] ?? '0' }}</td>
                    <td>{{ $location['avg_wqi'] ?? '0' }}</td>
                    <td>
                        @php
                            $wqi = $location['avg_wqi'] ?? 0;
                            $class = $wqi >= 90 ? 'excellent' : ($wqi >= 70 ? 'good' : ($wqi >= 50 ? 'fair' : ($wqi >= 25 ? 'poor' : 'very-poor')));
                            $label = $wqi >= 90 ? 'Excellent' : ($wqi >= 70 ? 'Good' : ($wqi >= 50 ? 'Fair' : ($wqi >= 25 ? 'Poor' : 'Very Poor')));
                        @endphp
                        <span class="wqi-badge wqi-{{ $class }}">{{ $label }}</span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        @if(isset($data['summary']['regional_data']) && is_array($data['summary']['regional_data']))
        <h3 style="margin-top: 30px; margin-bottom: 15px; color: #1f2937;">Regional Data</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Governorate</th>
                    <th>Sample Count</th>
                    <th>Location Count</th>
                    <th>Average WQI</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['summary']['regional_data'] as $region)
                <tr>
                    <td>{{ $region['governorate'] ?? 'N/A' }}</td>
                    <td>{{ $region['sample_count'] ?? '0' }}</td>
                    <td>{{ $region['locations'] ?? '0' }}</td>
                    <td>{{ $region['avg_wqi'] ?? '0' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
    @endif

    <!-- Page Break -->
    <div class="page-break"></div>

    <!-- Trends Analysis -->
    @if(isset($data['trends']) && !empty($data['trends']) && is_array($data['trends']))
    <div class="section">
        <h2 class="section-title">Trends Analysis</h2>

        @if(isset($data['trends']['direction']))
        <div style="background: #f9fafb; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
            <h3 style="color: #1f2937; margin-bottom: 10px;">Overall Trend</h3>
            <p style="font-size: 18px;">
                <strong>
                    @if($data['trends']['direction'] === 'improving')
                        Noticeable improvement in water quality
                    @elseif($data['trends']['direction'] === 'declining')
                        Decline in water quality
                    @else
                        Stable water quality
                    @endif
                </strong>
            </p>
            @if(isset($data['trends']['change_rate']))
            <p style="margin-top: 10px; color: #6b7280;">Change Rate: {{ $data['trends']['change_rate'] }}</p>
            @endif
        </div>
        @endif

        <div class="chart-placeholder">
            [Chart: Water Quality Trend Over Time]
        </div>
    </div>
    @endif

    <!-- Charts -->
    @if(isset($data['charts']) && !empty($data['charts']) && is_array($data['charts']))
    <div class="section">
        <h2 class="section-title">Charts</h2>

        @foreach($data['charts'] as $chartName => $chartData)
        <div style="margin-bottom: 30px;">
            <h3 style="margin-bottom: 15px; color: #1f2937;">{{ ucfirst(str_replace('_', ' ', $chartName)) }}</h3>
            <div class="chart-placeholder">
                [Chart: {{ $chartName }}]
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <!-- Conclusions -->
    @if(isset($data['conclusions']) && !empty($data['conclusions']))
    <div class="section">
        <div class="conclusions">
            <h3>Conclusions</h3>
            <p>{{ $data['conclusions'] }}</p>
        </div>
    </div>
    @endif

    <!-- Recommendations -->
    @if(isset($data['recommendations']) && !empty($data['recommendations']))
    <div class="section">
        <div class="recommendations">
            <h3>Recommendations</h3>
            @if(is_array($data['recommendations']))
                <ul>
                    @foreach($data['recommendations'] as $recommendation)
                    <li>{{ $recommendation }}</li>
                    @endforeach
                </ul>
            @else
                <p>{{ $data['recommendations'] }}</p>
            @endif
        </div>
    </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This report was generated by the Water Quality Monitoring System</p>
        <p>Creation Date: {{ now()->format('Y-m-d H:i:s') }}</p>
        <p>© {{ date('Y') }} Water Quality Monitoring System. All rights reserved.</p>
    </div>
</body>
</html>
