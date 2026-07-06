<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IPCR Form — {{ $employee_name ?? 'Employee' }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            display: flex;
            justify-content: center;
        }
        .print-container {
            max-width: 1200px;
            width: 100%;
            background: #fff;
            padding: 20px 25px;
            box-shadow: 0 0 12px rgba(0,0,0,0.15);
        }
        @media print {
            body { background: #fff; padding: 0.5in; }
            .print-container { box-shadow: none; padding: 0; }
            .no-print { display: none !important; }
            table { page-break-inside: avoid; }
            thead { display: table-header-group; }
        }
        .center { text-align: center; }
        .right { text-align: right; }
        .bold { font-weight: 700; }
        .indent1 { padding-left: 20px; }
        .indent2 { padding-left: 40px; }
        .indent3 { padding-left: 60px; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #222;
            padding: 4px 5px;
            vertical-align: middle;
            text-align: center;
        }
        th {
            background-color: #f0f0f0;
            font-weight: 700;
        }
        .left-align { text-align: left; }
        .header-title { font-size: 18px; font-weight: 700; text-align: center; margin: 5px 0; }
        .sub-title { font-size: 15px; font-weight: 700; text-align: center; margin: 3px 0; }
        .commitment-text { margin: 12px 0; text-align: justify; font-size: 12px; line-height: 1.4; }
        .final-rating { font-size: 15px; font-weight: 700; margin: 15px 0 5px; }
        .comments-section { margin-top: 20px; border-top: 1px solid #000; padding-top: 10px; }
        .comments-row { display: flex; flex-wrap: wrap; justify-content: space-between; margin: 8px 0; }
        .legend { font-size: 9.5px; margin-top: 15px; line-height: 1.4; }
        .legend p { margin: 2px 0; }
        .rating-scale { margin-top: 10px; }
        .rating-scale table { border: none; font-size: 10px; }
        .rating-scale td { border: none; padding: 2px 6px; vertical-align: top; }
        .scale-label { font-weight: 700; width: 30px; }
        .btn-print {
            background: #2c3e50;
            color: #fff;
            border: none;
            padding: 8px 18px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin-bottom: 15px;
        }
        .btn-print:hover { background: #1a252f; }
        @media (max-width: 700px) {
            .print-container { padding: 10px; }
            table { font-size: 9px; }
            th, td { padding: 2px 3px; }
            .header-title { font-size: 15px; }
            .sub-title { font-size: 13px; }
        }
        @media print {
            .print-container { padding: 0; }
            table { font-size: 9px; }
        }
    </style>
    @if (!empty($auto_print))
    <script>
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 350);
        });
    </script>
    @endif
</head>
<body>
<div class="print-container">

    @if (!empty($show_print_button))
    <div class="no-print" style="text-align: right; margin-bottom: 10px;">
        <button class="btn-print" type="button" onclick="window.print()">Print / Save as PDF</button>
    </div>
    @endif

    <div style="text-align: right; font-weight: 700; font-size: 14px;">{{ $form_number }}</div>
    <div class="header-title">INDIVIDUAL PERFORMANCE COMMITMENT AND REVIEW FORM (IPCR)</div>
    <div class="sub-title">{{ $office_name }}</div>

    <div class="commitment-text">
        {{ $commitment_statement }}<br>
        <strong>{{ $period_window }}</strong>
    </div>

    <div style="display: flex; justify-content: space-between; margin: 8px 0;">
        <span><strong>Ratee:</strong> {{ $ratee }}</span>
        <span><strong>Date :</strong> ________________________</span>
    </div>

    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; margin: 10px 0 15px;">
        <div><strong>REVIEWED</strong><br>Supervising EPS/ Planning Officer</div>
        <div><strong>NOTED</strong><br>Chief EPS</div>
        <div><strong>Date</strong><br>________________</div>
        <div><strong>APPROVED BY</strong><br>DIRECTOR IV</div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2" style="width: 8%;">Function</th>
                <th rowspan="2" style="width: 20%;">SERVICES PROGRAMS / PROJECTS / INDICATORS</th>
                <th colspan="2" style="width: 9%;">Annual Office Target</th>
                <th colspan="2" style="width: 9%;">INDIVIDUAL ANNUAL TARGETS</th>
                <th colspan="2" style="width: 9%;">ACCOMPLISHMENTS</th>
                <th colspan="2" style="width: 8%;">Total</th>
                <th rowspan="2" style="width: 5%;">%</th>
                <th colspan="3" style="width: 9%;">Rating</th>
                <th rowspan="2" style="width: 5%;">Average</th>
                <th rowspan="2" style="width: 6%;">Weighted Score</th>
                <th rowspan="2" style="width: 8%;">Remarks</th>
            </tr>
            <tr>
                <th>Weight</th><th>Target</th>
                <th>Q3 Target</th><th>Q3 Actual</th>
                <th>Q4 Target</th><th>Q4 Actual</th>
                <th>Target</th><th>Actual</th>
                <th>Q</th><th>E</th><th>T</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sections as $section)
                @php $sectionRendered = false; @endphp
                @foreach ($section['rows'] as $row)
                    @if (($row['type'] ?? '') === 'spacer')
                        <tr aria-hidden="true"><td colspan="16" style="height: 6px; border-left: 1px solid #222; border-right: 1px solid #222;"></td></tr>
                        @continue
                    @endif
                    <tr>
                        @if (! $sectionRendered)
                            <td rowspan="{{ $section['rowspan'] }}" style="font-weight: 700; vertical-align: middle;">{{ $section['label'] }}</td>
                            @php $sectionRendered = true; @endphp
                        @endif
                        <td class="left-align {{ ($row['bold'] ?? false) ? 'bold' : '' }}@if(($row['indent'] ?? 0) > 0) indent{{ $row['indent'] }}@endif">{{ $row['indicator'] }}</td>
                        @if (($row['type'] ?? '') === 'title')
                            <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                        @elseif (! empty($row['cells']) && ($row['rowspan'] ?? 0) > 0)
                            @php $c = $row['cells']; $rs = $row['rowspan']; @endphp
                            <td rowspan="{{ $rs }}">{{ $c['weight'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['office_target'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['q3_target'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['q3_actual'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['q4_target'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['q4_actual'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['total_target'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['total_actual'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['percent'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['quality'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['efficiency'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['timeliness'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['average'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['weighted'] }}</td>
                            <td rowspan="{{ $rs }}">{{ $c['remarks'] }}</td>
                        @endif
                    </tr>
                @endforeach
            @endforeach

            <tr style="background-color: #e9ecef; font-weight: 700;">
                <td colspan="2" style="text-align: right;">TOTAL</td>
                <td>{{ $totals['weight'] }}</td>
                <td></td><td></td><td></td><td></td><td></td><td></td><td></td>
                <td>{{ $totals['percent'] }}</td>
                <td></td><td></td><td></td>
                <td>{{ $totals['average'] }}</td>
                <td>{{ $totals['weighted'] }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="final-rating">
        FINAL AVERAGE RATING: {{ $final_rating ?? '________________' }}
    </div>

    <div class="comments-section">
        <div style="font-weight: 700; font-size: 13px;">COMMENTS AND RECOMMENDATIONS FOR DEVELOPMENT PURPOSES</div>
        @if (! empty($supervisor_feedback))
            <p style="margin: 10px 0; font-size: 12px; white-space: pre-wrap;">{{ $supervisor_feedback }}</p>
        @endif
        <div class="comments-row">
            <span><strong>DATE:</strong> ________________</span>
            <span><strong>ASSESSED BY:</strong> ________________</span>
        </div>
        <div class="comments-row">
            <span><strong>DATE:</strong> ________________</span>
            <span><strong>FINAL RATING APPROVED BY:</strong> ________________</span>
        </div>
    </div>

    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; margin-top: 20px;">
        <div><strong>DISCUSSED WITH:</strong> ________________</div>
        <div><strong>CEPS/CAO:</strong> ________________</div>
        <div><strong>DIRECTOR IV:</strong> ________________</div>
    </div>

    <div style="margin-top: 15px; font-weight: 700; font-size: 13px;">
        EMPLOYEE: {{ $employee_name }}
    </div>

    <div class="legend">
        <p><strong>Legend :</strong></p>
        <p>1 - Effectiveness/Quality : The extent to which actual performance compares with targeted performance (can be measured by quantity). The degree to which objectives are achieved and the extent to which targeted problems are solved. In management, effectiveness relates to getting the right things done.</p>
        <p>2 - Efficiency : The extent to which time or resources is issued for the intended task or purpose. Measures whether targets are accomplished with a minimum amount or quantity or waste, expense, or unnecessary effort.</p>
        <p>3 - Timeliness : Measures whether the deliverable was done on time based on the requirements of the law and/or clients/stakeholders. Time-related performance indicators evaluate such things as project completion deadlines, time management skills, and other time-sensitive expectations.</p>
    </div>

    <div class="rating-scale">
        <p><strong>Rating Scale :</strong></p>
        <table>
            <tr><td class="scale-label">5</td><td>Outstanding (130% above)</td><td>Performance represents an extraordinary level of achievement in terms of quality and time, technical skills and knowledge, ingenuity, creativity and initiative. Employees at this performance level should have demonstrated exceptional job mastery in all major areas of responsibility. Employee achievement and contributions to the organization are of marked excellence.</td></tr>
            <tr><td class="scale-label">4</td><td>Very Satisfactory (115-129%)</td><td>Performance exceeded expectations. All goals, objectives and targets were achieved above the established standards.</td></tr>
            <tr><td class="scale-label">3</td><td>Satisfactory (100-114%)</td><td>Performance met expectations in terms of quality of work, efficiency and timeliness. The most critical annual goals were met.</td></tr>
            <tr><td class="scale-label">2</td><td>Unsatisfactory (51-99%)</td><td>Performance failed to meet expectations, and/or one or more of the most critical goals were not met.</td></tr>
            <tr><td class="scale-label">1</td><td>Poor (50% & below)</td><td>Performance was consistently below expectations, and/or reasonable progress toward critical goals was not made. Significant improvement is needed in one or more important areas.</td></tr>
        </table>
    </div>

    <div style="margin-top: 20px; text-align: center; font-size: 8px; color: #777;">
        This is a computer-generated document.
    </div>
</div>
</body>
</html>
