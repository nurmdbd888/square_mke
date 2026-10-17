<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Export</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f1f5f9; }
    </style>
</head>
<body>
<table>
    <thead>
        <tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            <tr>@foreach ($row as $cell)<td>{{ $cell }}</td>@endforeach</tr>
        @endforeach
    </tbody>
</table>
</body>
</html>
